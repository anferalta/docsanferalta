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
            'tipos' => $tipos
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

        // Validação
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

        // Criar documento
        $documentoId = Documento::create([
            'titulo' => $titulo,
            'tipo_id' => $tipo_id,
            'criado_por' => $user->id,
            'estado_atual' => 'novo',
            'area_atual_id' => null
        ]);

        // Diretório por data
        $baseDir = date('Y/m/d/');
        $root = realpath(__DIR__ . '/../../');
        $fullPath = $root . '/storage/documentos/' . $baseDir;

        if (!is_dir($fullPath)) {
            mkdir($fullPath, 0777, true);
        }

        // Processar anexos
        foreach ($_FILES['ficheiros']['name'] as $i => $nomeOriginal) {

            $tmp = $_FILES['ficheiros']['tmp_name'][$i];
            $novoNome = uniqid() . '_' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', $nomeOriginal);
            $destino = $fullPath . $novoNome;

            if (!move_uploaded_file($tmp, $destino)) {
                return $this->redirect('/documentos/criar?erro=upload');
            }

            DocumentoFicheiro::create([
                'documento_id' => $documentoId,
                'ficheiro' => $baseDir . $novoNome,
                'tamanho' => filesize($destino),
                'mime' => mime_content_type($destino),
                'criado_em' => date('Y-m-d H:i:s')
            ]);
        }

        return $this->redirect('/documentos?sucesso=1');
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

        $root = realpath(__DIR__ . '/../../');
        $path = $root . '/storage/documentos/' . $anexo->ficheiro;

        if (!file_exists($path)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        header("Content-Type: " . mime_content_type($path));
        header("Content-Length: " . filesize($path));

        readfile($path);
        exit;
    }
}
