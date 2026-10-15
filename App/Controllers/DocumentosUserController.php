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
use App\Services\PathGuardService;

class DocumentosUserController extends BaseController
{

    private string $root;
    private string $storageDocs;

    public function __construct()
    {
        parent::__construct();

        // Caminho correto e unificado
        $this->root = STORAGE_PATH;
        $this->storageDocs = STORAGE_DOCUMENTOS . '/';
    }

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
        $csrf = CSRF::token();

        return $this->render('@site/documentos/criar.twig', [
            'tipos' => $tipos,
            '_csrf' => $csrf
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

        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        $titulo = trim($_POST['titulo'] ?? '');
        $tipo_id = intval($_POST['tipo_id'] ?? 0);

        if ($titulo === '' || $tipo_id <= 0) {
            Sessao::flash('erro', 'Dados inválidos.');
            return $this->redirect('/documentos/criar');
        }

        if (empty($_FILES['ficheiros']['name'][0])) {
            Sessao::flash('erro', 'Selecione pelo menos um ficheiro.');
            return $this->redirect('/documentos/criar');
        }

        // Criar documento
        $documentoId = Documento::create([
            'titulo' => $titulo,
            'tipo_id' => $tipo_id,
            'criado_por' => $user->id,
            'estado_atual' => 'novo',
            'area_atual_id' => null,
            'area_atual_desde' => date('Y-m-d H:i:s')
        ]);

        // Caminho seguro
        $ano = date('Y');
        $mes = date('m');
        $dia = date('d');

        $subpasta = "$ano/$mes/$dia/";
        $base = STORAGE_DOCUMENTOS . '/' . $subpasta;

        PathGuardService::init();
        PathGuardService::proteger($base);

        if (!is_dir($base)) {
            mkdir($base, 0777, true);
            file_put_contents($base . '/.keep', 'sentinel');
        }

        foreach ($_FILES['ficheiros']['name'] as $i => $nomeOriginal) {

            $tmp = $_FILES['ficheiros']['tmp_name'][$i];
            $erro = $_FILES['ficheiros']['error'][$i];
            $tamanho = $_FILES['ficheiros']['size'][$i];

            if ($erro !== UPLOAD_ERR_OK) {
                continue;
            }

            if (!is_uploaded_file($tmp)) {
                continue;
            }

            // Nome seguro
            $nomeSeguro = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $nomeOriginal);
            $nomeGuardado = uniqid() . '_' . $nomeSeguro;

            $destino = $base . $nomeGuardado;
            PathGuardService::proteger($destino);

            if (!move_uploaded_file($tmp, $destino)) {
                continue;
            }

            DocumentoFicheiro::create([
                'documento_id' => $documentoId,
                'ficheiro' => $nomeGuardado,
                'ficheiro_original' => $nomeOriginal,
                'caminho' => $subpasta,
                'tamanho' => $tamanho,
                'mime_type' => mime_content_type($destino),
                'hash' => hash_file('sha256', $destino),
                'criado_em' => date('Y-m-d H:i:s')
            ]);
        }

        Sessao::flash('sucesso', 'Documentos carregados com sucesso.');
        return $this->redirect('/documentos/criar');
    }

    /**
     * Abrir anexo (utilizador normal)
     */
    public function abrir($idAnexo)
    {
        PathGuardService::init();

        $user = Auth::user();
        if (!$user) return $this->redirect('/login');

        $anexo = DocumentoFicheiro::find($idAnexo);
        if (!$anexo) {
            http_response_code(404);
            exit("Anexo não encontrado.");
        }

        $documento = Documento::find($anexo->documento_id);
        if (!$documento || $documento->criado_por != $user->id) {
            http_response_code(403);
            exit("Acesso não autorizado.");
        }

        $path = STORAGE_DOCUMENTOS . '/' . $anexo->caminho . $anexo->ficheiro;

        PathGuardService::proteger($path);

        if (!file_exists($path)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        $mime = mime_content_type($path);
        $nome = basename($anexo->ficheiro);

        header("Content-Type: {$mime}");
        header("Content-Disposition: inline; filename=\"{$nome}\"");
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit;
    }

    public function download($id)
    {
        PathGuardService::init();

        $user = Auth::user();
        if (!$user) return $this->redirect('/login');

        $anexo = DocumentoFicheiro::find($id);
        if (!$anexo) {
            http_response_code(404);
            exit("Anexo não encontrado.");
        }

        $documento = Documento::find($anexo->documento_id);
        if (!$documento || $documento->criado_por != $user->id) {
            http_response_code(403);
            exit("Acesso não autorizado.");
        }

        $path = STORAGE_DOCUMENTOS . '/' . $anexo->caminho . $anexo->ficheiro;

        PathGuardService::proteger($path);

        if (!file_exists($path)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"" . basename($anexo->ficheiro) . "\"");
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit;
    }

    public function preview($idAnexo)
    {
        PathGuardService::init();

        $user = Auth::user();
        if (!$user) {
            return $this->redirect('/login');
        }

        $anexo = DocumentoFicheiro::find($idAnexo);

        if (!$anexo) {
            http_response_code(404);
            exit("Anexo não encontrado.");
        }

        $documento = Documento::find($anexo->documento_id);

        if (!$documento || $documento->criado_por != $user->id) {
            http_response_code(403);
            exit("Acesso não autorizado.");
        }

        // Caminho físico CORRIGIDO
        $path = STORAGE_DOCUMENTOS . '/' . trim($anexo->caminho, '/') . '/' . $anexo->ficheiro;

        PathGuardService::proteger($path);

        if (!file_exists($path)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        $mime = mime_content_type($path);
        $nome = basename($anexo->ficheiro);

        // Caminho público CORRIGIDO
        $publicPath = '/storage/documentos/' . trim($anexo->caminho, '/') . '/' . $anexo->ficheiro;

        // ADMIN deve usar template do ADMIN
        return $this->render('@site/documentos/preview.twig', [
            'mime' => $mime,
            'nome' => $nome,
            'caminho' => $publicPath
        ]);
    }
}
