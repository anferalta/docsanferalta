<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Auth;
use App\Models\Documento;
use App\Models\DocumentoTipo;

class DocumentosUserController extends BaseController
{

    /**
     * Lista apenas os documentos do utilizador autenticado
     */
    public function index()
    {
        $user = Auth::user();

        $documentos = Documento::query()
                ->where('criado_por', '=', $user->id)
                ->orderBy('id', 'DESC')
                ->get();

        return $this->render('documentos_user/index.twig', [
                    'documentos' => $documentos
        ]);
    }

    /**
     * Formulário de criação
     */
    public function criar()
    {
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
        $user = Auth::user();

        // Validar campos
        if (empty($_POST['titulo'])) {
            return $this->redirect('/documentos/criar?erro=titulo');
        }

        if (empty($_POST['tipo_id'])) {
            return $this->redirect('/documentos/criar?erro=tipo');
        }

        // Validar ficheiros
        if (empty($_FILES['ficheiros']['name'][0])) {
            return $this->redirect('/documentos/criar?erro=ficheiros');
        }

        // Diretório base
        $baseDir = 'uploads/documentos/' . date('Y/m/d/');

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0777, true);
        }

        // Processar cada ficheiro
        foreach ($_FILES['ficheiros']['name'] as $i => $nomeOriginal) {

            $tmp = $_FILES['ficheiros']['tmp_name'][$i];
            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

            $novoNome = uniqid() . '.' . $ext;
            $destino = $baseDir . $novoNome;

            move_uploaded_file($tmp, $destino);

            Documento::create([
                'titulo' => $_POST['titulo'],
                'tipo_id' => $_POST['tipo_id'],
                'ficheiro' => $novoNome,
                'ficheiro_original' => $nomeOriginal,
                'caminho' => $baseDir,
                'mime_type' => mime_content_type($destino),
                'tamanho' => filesize($destino),
                'criado_por' => $user->id,
                'estado_atual' => 'novo'
            ]);
        }

        return $this->redirect('/documentos?sucesso=1');
    }
}
