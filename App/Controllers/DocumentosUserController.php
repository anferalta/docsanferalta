<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Auth;
use App\Models\Documento;
use App\Models\DocumentoTipo;
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
                    'tipos' => $tipos
        ]);
    }

    /**
     * Submissão do formulário
     */
    public function criarSubmit()
    {
        // 1. Garantir que é POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->redirect('/documentos/criar');
        }

        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        // 2. Validação dos campos obrigatórios
        $titulo = trim($_POST['titulo'] ?? '');
        $tipo_id = intval($_POST['tipo_id'] ?? 0);

        if ($titulo === '') {
            return $this->redirect('/documentos/criar?erro=titulo');
        }

        if ($tipo_id <= 0) {
            return $this->redirect('/documentos/criar?erro=tipo');
        }

        // 3. Validar ficheiros
        if (empty($_FILES['ficheiros']['name'][0])) {
            return $this->redirect('/documentos/criar?erro=ficheiros');
        }

        // ============================================================
        // 4. Criar diretório baseado na data (ABSOLUTO + CORRIGIDO)
        // ============================================================
        // Caminho relativo guardado na BD
        $baseDir = date('Y/m/d/');

        // Caminho absoluto até à raiz do projeto
        // __DIR__ = App/Controllers
        // /../../.. = volta até à raiz do projeto
        $root = realpath(__DIR__ . '/../../');

        // Caminho absoluto final onde os ficheiros serão guardados
        $fullPath = $root . '/storage/documentos/' . $baseDir;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        // ============================================================
        // 5. Processar cada ficheiro enviado
        // ============================================================
        foreach ($_FILES['ficheiros']['name'] as $i => $nomeOriginal) {

            $tmp = $_FILES['ficheiros']['tmp_name'][$i];
            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

            // Nome único
            $novoNome = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $nomeOriginal);
            $destino = $fullPath . $novoNome;

            // Mover ficheiro
            if (!move_uploaded_file($tmp, $destino)) {
                return $this->redirect('/documentos/criar?erro=upload');
            }

            // 6. Criar objeto Documento
            $doc = new Documento();

            $doc->titulo = $titulo;
            $doc->tipo_id = $tipo_id;
            $doc->ficheiro = $novoNome;
            $doc->ficheiro_original = $nomeOriginal;

            // Guardar apenas o caminho relativo
            $doc->caminho = $baseDir;

            $doc->mime_type = mime_content_type($destino);
            $doc->tamanho = filesize($destino);
            $doc->criado_por = $user->id;

            // Estado inicial
            $doc->estado = 1;
            $doc->estado_atual = 'novo';
            $doc->area_atual_id = null;

            $doc->save();
        }

        // 7. Sucesso
        return $this->redirect('/documentos?sucesso=1');
    }

    public function abrir($ano, $mes, $dia, $ficheiro)
    {
        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        $root = realpath(__DIR__ . '/../../');
        $path = $root . "/storage/documentos/$ano/$mes/$dia/$ficheiro";

        if (!file_exists($path)) {
            http_response_code(404);
            echo "Ficheiro não encontrado.";
            exit;
        }

        $mime = mime_content_type($path);
        header("Content-Type: $mime");
        header("Content-Length: " . filesize($path));

        readfile($path);
        exit;
    }
}
