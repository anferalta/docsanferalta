<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Auth;
use App\Models\Documento;
use App\Models\DocumentoArea;
use App\Models\DocumentoTramitacao;
use App\Models\DocumentoTramitacaoAnexo;
use App\Models\Notificacao;
use App\Core\Sessao;

class TramitacaoAdminController extends BaseController
{

    /**
     * Criar notificação
     */
    private function notificar($utilizador_id, $documento_id, $tipo, $mensagem)
    {
        Notificacao::create([
            'utilizador_id' => $utilizador_id,
            'documento_id' => $documento_id,
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'lida' => 0
        ]);
    }

    /**
     * Guarda anexos de um movimento de tramitação
     */
    private function guardarAnexos(int $tramitacao_id): void
    {
        if (empty($_FILES['anexos']['name'][0])) {
            return;
        }

        $baseDir = ROOT_PATH . '/public/uploads/tramitacao/' . $tramitacao_id . '/';

        if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true) && !is_dir($baseDir)) {
            return;
        }

        foreach ($_FILES['anexos']['name'] as $i => $nomeOriginal) {
            if (!is_uploaded_file($_FILES['anexos']['tmp_name'][$i])) {
                continue;
            }

            $tmp = $_FILES['anexos']['tmp_name'][$i];
            $nomeSeguro = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($nomeOriginal));
            $nomeGuardado = uniqid('anx_', true) . '_' . $nomeSeguro;

            if (move_uploaded_file($tmp, $baseDir . $nomeGuardado)) {
                DocumentoTramitacaoAnexo::create([
                    'tramitacao_id' => $tramitacao_id,
                    'ficheiro' => $nomeGuardado,
                    'nome_original' => $nomeOriginal,
                ]);
            }
        }
    }

    /**
     * DASHBOARD DE TRAMITAÇÃO
     */
    public function dashboard()
    {
        $this->authorize('admin.tramitacao.dashboard');

        $db = \App\Core\Conexao::getInstancia();

        $pendentes = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'pendente'")->fetchColumn();
        $em_tramitacao = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_tramitacao'")->fetchColumn();
        $em_analise = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual IN ('analise','em_analise')")->fetchColumn();
        $concluidos = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'concluido'")->fetchColumn();
        $arquivados = $db->query("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'arquivado'")->fetchColumn();

        return $this->render('@admin/tramitacao/dashboard.twig', [
                    'pendentes' => $pendentes,
                    'em_tramitacao' => $em_tramitacao,
                    'em_analise' => $em_analise,
                    'concluidos' => $concluidos,
                    'arquivados' => $arquivados
        ]);
    }

    /**
     * VER TRAMITAÇÃO DE UM DOCUMENTO
     */
    public function index($documento_id)
    {
        $documento = Documento::find($documento_id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        // HISTÓRICO CORRETO
        $historico = \App\Models\DocumentoTramitacao::porDocumento($documento_id);

        // ÁREAS
        $areas = \App\Models\DocumentoArea::all();

        // ESTADOS
        $estados = \App\Models\DocumentoEstado::all();

        return $this->render('@admin/tramitacao/index.twig', [
                    'documento' => $documento,
                    'historico' => $historico,
                    'areas' => $areas,
                    'estados' => $estados,
                    'user' => Auth::user()
        ]);
    }

    /**
     * ENCAMINHAR DOCUMENTO
     */
    public function encaminhar()
    {
        $this->authorize('admin.tramitacao.encaminhar');

        $id = $_POST['documento_id'] ?? null;
        $nova_area = $_POST['area_id'] ?? null;
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$id || !$nova_area) {
            Sessao::flash('erro', 'Dados inválidos.');
            return $this->redirect('/admin/documentos');
        }

        // Buscar documento
        $documento = Documento::find($id);
        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        // UPDATE direto
        $db = \App\Core\Conexao::getInstancia();

        $sql = "UPDATE documentos 
            SET area_atual_id = ?, estado_atual = ? 
            WHERE id = ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $nova_area,
            'em_tramitacao',
            $id
        ]);

        // Registar histórico
        DocumentoTramitacao::registar(
                $id,
                $nova_area,
                Auth::user()->id,
                'ENCAMINHADO',
                'em_tramitacao',
                $comentario
        );

        // Notificar criador
        $this->notificar(
                $documento->criado_por,
                $documento->id,
                'encaminhamento',
                "O documento #{$documento->id} foi encaminhado para uma nova área."
        );

        // Sucesso + redirect
        Sessao::flash('sucesso', 'Documento encaminhado com sucesso.');
        return $this->redirect("/admin/documentos/editar/{$documento->id}?tab=tramitacao");
    }

    public function verAnexo($historicoId, $ficheiro)
    {
        // ============================
        // 1. Validar histórico
        // ============================
        $historicoId = intval($historicoId);

        if ($historicoId <= 0) {
            http_response_code(404);
            echo "Anexo inválido.";
            return;
        }

        $historico = \App\Models\DocumentoTramitacao::find($historicoId);

        if (!$historico) {
            http_response_code(404);
            echo "Registo de tramitação não encontrado.";
            return;
        }

        // ============================
        // 2. Resolver caminho seguro
        // ============================
        try {
            $path = \App\Services\TramitacaoFileService::resolverCaminhoSeguro(
                    $historicoId,
                    $ficheiro
            );
        } catch (\Exception $e) {

            error_log("Anexo não encontrado: $historicoId/$ficheiro");

            http_response_code(404);
            echo "Ficheiro não encontrado.";
            return;
        }

        // ============================
        // 3. Enviar ficheiro
        // ============================
        header("Content-Type: " . mime_content_type($path));
        header("Content-Length: " . filesize($path));
        header("Content-Disposition: inline; filename=\"" . basename($path) . "\"");

        readfile($path);
        exit;
    }

    /**
     * ADICIONAR COMENTÁRIO
     */
    public function comentar()
    {
        $user = Auth::user();

        // ============================
        // 1. Validar método e permissões
        // ============================
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Sessao::flash('erro', 'Método inválido.');
            return $this->redirect('/admin/documentos');
        }

        if (!$user->hasPermissao('admin.tramitacao.comentar')) {
            Sessao::flash('erro', 'Não tem permissão para comentar documentos.');
            return $this->redirect('/admin/documentos');
        }

        // ============================
        // 2. Validar inputs
        // ============================
        $documento_id = intval($_POST['documento_id'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if ($documento_id <= 0 || $comentario === '') {
            Sessao::flash('erro', 'Comentário inválido.');
            return $this->redirect('/admin/documentos');
        }

        $documento = Documento::find($documento_id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        // ============================
        // 3. Criar registo de histórico
        // ============================
        $historico = \App\Models\DocumentoTramitacao::create([
            'documento_id' => $documento->id,
            'utilizador_id' => $user->id,
            'acao' => 'COMENTARIO',
            'area_id' => $documento->area_atual_id,
            'comentario' => $comentario,
            'estado' => $documento->estado_atual, // ← CORREÇÃO
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        // ============================
        // 4. Guardar anexos (se existirem)
        // ============================
        if (!empty($_FILES['anexos']['name'][0])) {

            $ficheiros = \App\Services\TramitacaoFileService::guardarAnexos(
                    $historico->id,
                    $_FILES['anexos']
            );

            foreach ($ficheiros as $f) {
                \App\Models\DocumentoTramitacaoAnexo::create([
                    'tramitacao_id' => $historico->id,
                    'ficheiro' => $f['ficheiro'],
                    'nome_original' => $f['nome_original'],
                    'mime_type' => $f['mime_type'],
                    'tamanho' => $f['tamanho']
                ]);
            }
        }

        // ============================
        // 5. Notificar criador
        // ============================
        \App\Models\Notificacao::create([
            'utilizador_id' => $documento->criado_por,
            'mensagem' => "O documento '{$documento->titulo}' recebeu um novo comentário.",
            'url' => "/admin/tramitacao/{$documento->id}",
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        // ============================
        // 6. Sucesso
        // ============================
        Sessao::flash('sucesso', 'Comentário adicionado com sucesso.');
        return $this->redirect("/admin/documentos/editar/{$documento->id}#tabTramitacao");
    }

    public function estado()
    {
        $user = Auth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Sessao::flash('erro', 'Método inválido.');
            return $this->redirect('/admin/documentos');
        }

        if (!$user->hasPermissao('admin.tramitacao.estado')) {
            Sessao::flash('erro', 'Não tem permissão para alterar o estado.');
            return $this->redirect('/admin/documentos');
        }

        $documento_id = intval($_POST['documento_id'] ?? 0);
        $estado = trim($_POST['estado'] ?? '');
        $comentario = trim($_POST['comentario'] ?? '');

        if ($documento_id <= 0 || $estado === '') {
            Sessao::flash('erro', 'Dados inválidos.');
            return $this->redirect('/admin/documentos');
        }

        $documento = Documento::find($documento_id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        // ============================
        // 3. Histórico
        // ============================
        $historico = \App\Models\DocumentoTramitacao::create([
            'documento_id' => $documento->id,
            'utilizador_id' => $user->id,
            'acao' => 'ESTADO',
            'area_id' => $documento->area_atual_id,
            'comentario' => $comentario ?: null,
            'estado' => $estado,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        // ============================
        // 4. Anexos
        // ============================
        if (!empty($_FILES['anexos']['name'][0])) {

            $ficheiros = \App\Services\TramitacaoFileService::guardarAnexos(
                    $historico->id,
                    $_FILES['anexos']
            );

            foreach ($ficheiros as $f) {
                \App\Models\DocumentoTramitacaoAnexo::create([
                    'tramitacao_id' => $historico->id,
                    'ficheiro' => $f['ficheiro'],
                    'nome_original' => $f['nome_original'],
                    'mime_type' => $f['mime_type'],
                    'tamanho' => $f['tamanho']
                ]);
            }
        }

        // ============================
        // 5. Atualizar estado do documento
        // ============================
        $documento->update([
            'estado_atual' => $estado,
            'area_atual_id' => $documento->area_atual_id
                ], "id = {$documento->id}");

        // ============================
        // 6. Notificação
        // ============================
        \App\Models\Notificacao::create([
            'utilizador_id' => $documento->criado_por,
            'documento_id' => $documento->id,
            'tipo' => 'estado',
            'mensagem' => "O estado do documento '{$documento->titulo}' foi alterado para '{$estado}'.",
            'url' => "/admin/tramitacao/{$documento->id}",
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        Sessao::flash('sucesso', 'Estado alterado com sucesso.');
        return $this->redirect("/admin/documentos/editar/{$documento->id}#tabTramitacao");
    }

    /**
     * ALTERAR ESTADO
     */
    public function mudarEstado()
    {
        $this->authorize('admin.tramitacao.mudar_estado');

        $doc_id = (int) ($_POST['documento_id'] ?? 0);
        $estado = $_POST['estado'] ?? null;
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$doc_id || !$estado) {
            return $this->redirect('/admin/documentos');
        }

        $documento = Documento::find($doc_id);

        // ============================
        // REGISTAR HISTÓRICO
        // ============================
        DocumentoTramitacao::registar(
                $doc_id,
                $documento->area_atual_id, // ← ÁREA CORRETA
                Auth::id(),
                'ESTADO', // ← AÇÃO CORRETA
                $estado, // ← NOVO ESTADO
                $comentario
        );

        // Guardar anexos
        $tramitacao_id = DocumentoTramitacao::ultimoId();
        $this->guardarAnexos($tramitacao_id);

        // Atualizar estado do documento
        Documento::updateEstado($doc_id, $estado);

        // Notificar criador
        if ($documento) {
            $this->notificar(
                    $documento->criado_por,
                    $doc_id,
                    'estado',
                    "O documento #{$doc_id} mudou para o estado: {$estado}."
            );
        }

        return $this->redirect("/admin/tramitacao/$doc_id");
    }

    /**
     * ARQUIVAR DOCUMENTO
     */
    public function arquivar()
    {
        $this->authorize('admin.tramitacao.arquivar');

        $doc_id = (int) ($_POST['documento_id'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if (!$doc_id) {
            return $this->redirect('/admin/documentos');
        }

        $documento = Documento::find($doc_id);

        DocumentoTramitacao::registar(
                $doc_id,
                null,
                Auth::id(),
                'ARQUIVADO',
                'arquivado',
                $comentario
        );

        $tramitacao_id = DocumentoTramitacao::ultimoId();
        $this->guardarAnexos($tramitacao_id);

        Documento::arquivar($doc_id, Auth::id());

        if ($documento) {
            $this->notificar(
                    $documento->criado_por,
                    $doc_id,
                    'arquivado',
                    "O documento #{$doc_id} foi arquivado."
            );
        }

        return $this->redirect("/admin/tramitacao/$doc_id");
    }

    public function lista()
    {
        $this->authorize('admin.tramitacao.ver');

        $db = \App\Core\Conexao::getInstancia();

        $sql = "
        SELECT 
            d.id,
            d.titulo,
            d.estado_atual,
            d.criado_em,
            t.nome AS tipo_nome,
            a.nome AS area_atual_nome,
            u.nome AS criador_nome
        FROM documentos d
        LEFT JOIN documento_tipos t ON t.id = d.tipo_id
        LEFT JOIN documento_areas a ON a.id = d.area_atual_id
        LEFT JOIN utilizadores u ON u.id = d.criado_por
        WHERE d.estado_atual != 'arquivado'
        ORDER BY d.criado_em DESC
    ";

        $documentos = $db->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('@admin/tramitacao/lista.twig', [
                    'documentos' => $documentos
        ]);
    }
}
