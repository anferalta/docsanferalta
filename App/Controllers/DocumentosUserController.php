<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Auth;
use App\Core\Sessao;
use App\Core\CSRF;
use App\Models\Documento;
use App\Models\DocumentoTipo;
use App\Models\DocumentoFicheiro;
use App\Core\Conexao;

class DocumentosUserController extends BaseController
{

    /**
     * Lista apenas os documentos do utilizador autenticado
     */
    public function index()
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        $db = Conexao::getInstancia();

        $sql = "
            SELECT 
                d.*,
                t.nome AS tipo_nome,
                a.nome AS area_nome
            FROM documentos d
            LEFT JOIN documento_tipos t ON t.tipo_id = d.tipo_id
            LEFT JOIN documento_areas a ON a.id = d.area_atual_id
            WHERE d.criado_por = ?
            ORDER BY d.id DESC
        ";

        $stmt = $db->prepare($sql);
        $stmt->execute([$user->id]);

        $documentos = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {

            $doc = new Documento();

            foreach ($row as $campo => $valor) {
                if (property_exists($doc, $campo)) {
                    $doc->$campo = $valor;
                }
            }

            $doc->tipo_nome = $row['tipo_nome'];
            $doc->area_nome = $row['area_nome'];

            $documentos[] = $doc;
        }

        return $this->render('@site/documentos/index.twig', [
                    'documentos' => $documentos
        ]);
    }

    /**
     * Formulário de criação
     */
    public function criar()
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        $tipos = DocumentoTipo::all();

        return $this->render('@site/documentos/criar.twig', [
                    'tipos' => $tipos,
                    '_csrf' => CSRF::token() // token já garantido pelo middleware
        ]);
    }

    /**
     * Submissão do formulário
     */
    public function criarSubmit()
    {
        if (!CSRF::validateFromRequest()) {
            Sessao::flash('erro', 'Token CSRF inválido.');
            return $this->redirect('/documentos/criar');
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->redirect('/documentos/criar');
        }

        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        // ============================================================
        // 1. Validação dos campos
        // ============================================================
        $titulo = trim($_POST['titulo'] ?? '');
        $tipo_id = intval($_POST['tipo_id'] ?? 0);

        if ($titulo === '') {
            Sessao::flash('erro', 'O título é obrigatório.');
            return $this->redirect('/documentos/criar');
        }

        if ($tipo_id <= 0) {
            Sessao::flash('erro', 'Selecione o tipo de documento.');
            return $this->redirect('/documentos/criar');
        }

        if (empty($_FILES['ficheiros']['name'][0])) {
            Sessao::flash('erro', 'Selecione pelo menos um ficheiro.');
            return $this->redirect('/documentos/criar');
        }

        // ============================================================
        // 2. VALIDAR TODOS OS FICHEIROS ANTES DE CRIAR DOCUMENTO
        // ============================================================
        $permitidos = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp'];

        foreach ($_FILES['ficheiros']['name'] as $i => $nomeOriginal) {

            $tmp = $_FILES['ficheiros']['tmp_name'][$i];
            $tamanho = $_FILES['ficheiros']['size'][$i];

            // 2.1 Validar extensão
            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
            if (!in_array($ext, $permitidos)) {
                Sessao::flash('erro', 'Extensão inválida: ' . $ext);
                return $this->redirect('/documentos/criar');
            }

            // 2.2 Validar tamanho
            if ($tamanho > 20 * 1024 * 1024) {
                Sessao::flash('erro', 'Um ficheiro excede o limite de 20 MB.');
                return $this->redirect('/documentos/criar');
            }

            // 2.3 Verificar duplicado
            $duplicado = DocumentoFicheiro::query()
                    ->join('documentos', 'documentos.id', '=', 'documento_ficheiros.documento_id')
                    ->where('documentos.criado_por', '=', $user->id)
                    ->where('documento_ficheiros.ficheiro_original', '=', $nomeOriginal)
                    ->first();

            if ($duplicado) {
                Sessao::flash('erro', 'Já existe um ficheiro com esse nome.');
                return $this->redirect('/documentos/criar');
            }
        }

        // ============================================================
        // 3. Criar documento
        // ============================================================
        $documentoId = Documento::create([
            'titulo' => $titulo,
            'tipo_id' => $tipo_id,
            'criado_por' => $user->id,
            'estado_atual' => 'novo',
            'area_atual_id' => null,
            'area_atual_desde' => date('Y-m-d H:i:s')
        ]);

        // ============================================================
        // 4. Criar diretório baseado na data
        // ============================================================
        $baseDir = date('Y/m/d/');
        $root = realpath(__DIR__ . '/../../');
        $fullPath = $root . '/storage/documentos/' . $baseDir;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        // ============================================================
        // 5. Processar upload
        // ============================================================
        foreach ($_FILES['ficheiros']['name'] as $i => $nomeOriginal) {

            $tmp = $_FILES['ficheiros']['tmp_name'][$i];

            $novoNome = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $nomeOriginal);
            $destino = $fullPath . $novoNome;

            move_uploaded_file($tmp, $destino);

            DocumentoFicheiro::create([
                'documento_id' => $documentoId,
                'ficheiro' => $novoNome,
                'ficheiro_original' => $nomeOriginal,
                'caminho' => $baseDir, // <-- ADICIONADO
                'tamanho' => filesize($destino),
                'mime' => mime_content_type($destino),
                'criado_em' => date('Y-m-d H:i:s')
            ]);
        }

        // ============================================================
        // 6. Sucesso
        // ============================================================
        Sessao::flash('sucesso', 'Documentos carregados com sucesso.');
        return $this->redirect('/documentos/criar');
    }

    /**
     * Abrir anexo (utilizador normal)
     */
    public function abrir($idAnexo)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        $anexo = DocumentoFicheiro::find($idAnexo);

        if (!$anexo) {
            http_response_code(404);
            exit("Anexo não encontrado.");
        }

        // Garantir que o anexo pertence ao utilizador autenticado
        $documento = Documento::find($anexo->documento_id);

        if (!$documento || $documento->criado_por != $user->id) {
            http_response_code(403);
            exit("Acesso não autorizado.");
        }

        $root = realpath(__DIR__ . '/../../');
        $path = $root . '/storage/documentos/' . $anexo->caminho . $anexo->ficheiro;

        if (!file_exists($path)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        $mime = mime_content_type($path);
        $nome = basename($anexo->ficheiro);

        $inline = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'webp'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($ext, $inline)) {
            header("Content-Type: {$mime}");
            header("Content-Disposition: inline; filename=\"{$nome}\"");
            header("Content-Length: " . filesize($path));
            readfile($path);
            exit;
        }

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"{$nome}\"");
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit;
    }

    public function download($id)
    {
        $anexo = DocumentoFicheiro::find($id);

        if (!$anexo) {
            http_response_code(404);
            exit("Anexo não encontrado.");
        }

        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        // Garantir que o anexo pertence ao utilizador autenticado
        $documento = Documento::find($anexo->documento_id);

        if (!$documento || $documento->criado_por != $user->id) {
            http_response_code(403);
            exit("Acesso não autorizado.");
        }

        $root = realpath(__DIR__ . '/../../');
        $path = $root . '/storage/documentos/' . $anexo->ficheiro;

        if (!file_exists($path)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"" . $anexo->nome() . "\"");
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit;
    }
}
