<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Auth;
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

            // Carregar anexos

            $documentos[] = $doc;
        }

        return $this->render('documentos_user/index.twig', [
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

        return $this->render('documentos_user/criar.twig', [
                    'tipos' => $tipos,
                    'erro' => $_GET['erro'] ?? null,
                    'sucesso' => $_GET['sucesso'] ?? null
        ]);
    }

    /**
     * Submissão do formulário
     */
    public function criarSubmit()
    {
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
            return $this->redirect('/documentos/criar?erro=titulo');
        }

        if ($tipo_id <= 0) {
            return $this->redirect('/documentos/criar?erro=tipo');
        }

        if (empty($_FILES['ficheiros']['name'][0])) {
            return $this->redirect('/documentos/criar?erro=ficheiros');
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
                return $this->redirect('/documentos/criar?erro=extensao');
            }

            // 2.2 Validar tamanho
            if ($tamanho > 20 * 1024 * 1024) {
                return $this->redirect('/documentos/criar?erro=tamanho');
            }

            // 2.3 Verificar duplicado (mesmo utilizador + mesmo nome original)
            $duplicado = DocumentoFicheiro::query()
                    ->join('documentos', 'documentos.id', '=', 'documento_ficheiros.documento_id')
                    ->where('documentos.criado_por', '=', $user->id)
                    ->where('documento_ficheiros.ficheiro_original', '=', $nomeOriginal)
                    ->first();

            if ($duplicado) {
                return $this->redirect('/documentos/criar?erro=ficheiro_duplicado');
            }
        }

        // ============================================================
        // 3. Criar documento (só agora!)
        // ============================================================
        $documentoId = Documento::create([
            'titulo' => $titulo,
            'tipo_id' => $tipo_id,
            'criado_por' => $user->id,
            'estado_atual' => 'novo',
            'area_atual_id' => null
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
        // 5. Processar upload (agora já é seguro)
        // ============================================================
        foreach ($_FILES['ficheiros']['name'] as $i => $nomeOriginal) {

            $tmp = $_FILES['ficheiros']['tmp_name'][$i];

            $novoNome = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $nomeOriginal);
            $destino = $fullPath . $novoNome;

            move_uploaded_file($tmp, $destino);

            DocumentoFicheiro::create([
                'documento_id' => $documentoId,
                'ficheiro' => $baseDir . $novoNome,
                'ficheiro_original' => $nomeOriginal,
                'tamanho' => filesize($destino),
                'mime' => mime_content_type($destino),
                'criado_em' => date('Y-m-d H:i:s')
            ]);
        }

        return $this->redirect('/documentos/criar?sucesso=1');
    }

    /**
     * Abrir anexo (utilizador normal)
     */
    public function abrir($idAnexo)
    {
        // 1. Verificar login
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        // 2. Buscar o anexo
        $anexo = DocumentoFicheiro::find($idAnexo);

        if (!$anexo) {
            http_response_code(404);
            exit("Anexo não encontrado.");
        }

        // 3. Construir caminho absoluto
        $root = realpath(__DIR__ . '/../../');
        $path = $root . '/storage/documentos/' . $anexo->ficheiro;

        if (!file_exists($path)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        // 4. Determinar MIME
        $mime = mime_content_type($path);
        $nome = basename($anexo->ficheiro);

        // 5. Tipos que podem abrir inline
        $inline = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'webp'];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        // 6. Se for inline → abrir no browser
        if (in_array($ext, $inline)) {
            header("Content-Type: {$mime}");
            header("Content-Disposition: inline; filename=\"{$nome}\"");
            header("Content-Length: " . filesize($path));
            readfile($path);
            exit;
        }

        // 7. Fallback → download
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

        $root = realpath(__DIR__ . '/../../'); // utilizador sobe 2 níveis
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
