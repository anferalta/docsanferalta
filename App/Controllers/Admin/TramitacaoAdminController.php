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

    private function guardarAnexos(int $tramitacao_id): void
    {
        if (empty($_FILES['anexos']['name'][0])) {
            return;
        }

        $baseDir = ROOT_PATH . '/public/uploads/tramitacao/' . $tramitacao_id . '/';

        if (!is_dir($baseDir)) {
            mkdir($baseDir, 0775, true);
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
     * Página principal de TRAMITAÇÃO do documento
     */
    public function documento($id)
    {
        $documento = Documento::find($id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/tramitacao');
        }

        // HISTÓRICO — usa o método correto do teu modelo
        $historico = DocumentoTramitacao::filtrar($id, []);

        // ÁREAS
        $areas = DocumentoArea::all();

        // ESTADOS
        $estados = \App\Models\DocumentoEstado::all();

        return $this->render('@admin/tramitacao/documento.twig', [
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
            return $this->redirect('/admin/tramitacao');
        }

        $documento = Documento::find($id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/tramitacao');
        }

        // Atualizar documento
        $documento->update([
            'area_atual_id' => $nova_area,
            'estado_atual' => 'em_tramitacao',
            'area_atual_desde' => date('Y-m-d H:i:s')
                ], "id = {$id}");

        // Histórico
        $mov = DocumentoTramitacao::create([
            'documento_id' => $id,
            'area_id' => $nova_area,
            'utilizador_id' => Auth::user()->id,
            'acao' => 'ENCAMINHADO',
            'estado' => 'em_tramitacao',
            'comentario' => $comentario,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        // Anexos
        $this->guardarAnexos($mov->id);

        // Notificação
        $this->notificar(
                $documento->criado_por,
                $documento->id,
                'encaminhamento',
                "O documento #{$documento->id} foi encaminhado."
        );

        Sessao::flash('sucesso', 'Documento encaminhado com sucesso.');
        return $this->redirect("/admin/tramitacao/{$id}");
    }

    /**
     * COMENTAR
     */
    public function comentar()
    {
        $user = Auth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Sessao::flash('erro', 'Método inválido.');
            return $this->redirect('/admin/tramitacao');
        }

        if (!$user->hasPermissao('admin.tramitacao.comentar')) {
            Sessao::flash('erro', 'Sem permissão.');
            return $this->redirect('/admin/tramitacao');
        }

        $documento_id = intval($_POST['documento_id'] ?? 0);
        $comentario = trim($_POST['comentario'] ?? '');

        if ($documento_id <= 0 || $comentario === '') {
            Sessao::flash('erro', 'Comentário inválido.');
            return $this->redirect('/admin/tramitacao');
        }

        $documento = Documento::find($documento_id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/tramitacao');
        }

        // Histórico
        $mov = DocumentoTramitacao::create([
            'documento_id' => $documento->id,
            'utilizador_id' => $user->id,
            'acao' => 'COMENTARIO',
            'area_id' => $documento->area_atual_id,
            'comentario' => $comentario,
            'estado' => $documento->estado_atual,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        // Anexos
        $this->guardarAnexos($mov->id);

        // Notificação
        Notificacao::create([
            'utilizador_id' => $documento->criado_por,
            'mensagem' => "O documento '{$documento->titulo}' recebeu um comentário.",
            'url' => "/admin/tramitacao/{$documento->id}",
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        Sessao::flash('sucesso', 'Comentário adicionado.');
        return $this->redirect("/admin/tramitacao/{$documento->id}");
    }

    /**
     * ALTERAR ESTADO
     */
    public function estado()
    {
        $user = Auth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Sessao::flash('erro', 'Método inválido.');
            return $this->redirect('/admin/tramitacao');
        }

        if (!$user->hasPermissao('admin.tramitacao.estado')) {
            Sessao::flash('erro', 'Sem permissão.');
            return $this->redirect('/admin/tramitacao');
        }

        $documento_id = intval($_POST['documento_id'] ?? 0);
        $estado = trim($_POST['estado'] ?? '');
        $comentario = trim($_POST['comentario'] ?? '');

        if ($documento_id <= 0 || $estado === '') {
            Sessao::flash('erro', 'Dados inválidos.');
            return $this->redirect('/admin/tramitacao');
        }

        $documento = Documento::find($documento_id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/tramitacao');
        }

        // Histórico
        $mov = DocumentoTramitacao::create([
            'documento_id' => $documento->id,
            'utilizador_id' => $user->id,
            'acao' => 'ESTADO',
            'area_id' => $documento->area_atual_id,
            'comentario' => $comentario ?: null,
            'estado' => $estado,
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        // Anexos
        $this->guardarAnexos($mov->id);

        // Atualizar documento
        $documento->update([
            'estado_atual' => $estado
                ], "id = {$documento->id}");

        // Notificação
        Notificacao::create([
            'utilizador_id' => $documento->criado_por,
            'documento_id' => $documento->id,
            'tipo' => 'estado',
            'mensagem' => "O estado do documento '{$documento->titulo}' foi alterado para '{$estado}'.",
            'url' => "/admin/tramitacao/{$documento->id}",
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        Sessao::flash('sucesso', 'Estado alterado.');
        return $this->redirect("/admin/tramitacao/{$documento->id}");
    }

    /**
     * LISTA DE DOCUMENTOS EM TRAMITAÇÃO
     */
    public function lista()
    {
        $this->authorize('admin.tramitacao.ver');

        $db = \App\Core\Conexao::getInstancia();

        // FILTROS
        $estado = $_GET['estado'] ?? '';
        $area = $_GET['area'] ?? '';
        $criador = $_GET['criador'] ?? '';
        $tipo = $_GET['tipo'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';

        $sql = "
        SELECT 
            d.id,
            d.titulo,
            d.estado_atual,
            d.criado_em,
            d.area_atual_desde,
            t.nome AS tipo_nome,
            a.nome AS area_atual_nome,
            a.prazo_resposta,
            u.nome AS criador_nome
        FROM documentos d
        LEFT JOIN documento_tipos t ON t.tipo_id = d.tipo_id
        LEFT JOIN documento_areas a ON a.id = d.area_atual_id
        LEFT JOIN utilizadores u ON u.id = d.criado_por
        WHERE d.estado_atual IN ('novo', 'pendente', 'analise', 'em_analise', 'em_tramitacao', 'concluido')
    ";

        $params = [];

        if ($estado !== '') {
            $sql .= " AND d.estado_atual = ? ";
            $params[] = $estado;
        }

        if ($area !== '') {
            $sql .= " AND a.nome = ? ";
            $params[] = $area;
        }

        if ($criador !== '') {
            $sql .= " AND u.nome LIKE ? ";
            $params[] = "%{$criador}%";
        }

        if ($tipo !== '') {
            $sql .= " AND t.tipo_id = ? ";
            $params[] = $tipo;
        }

        if ($dataInicio !== '') {
            $sql .= " AND DATE(d.criado_em) >= ? ";
            $params[] = $dataInicio;
        }

        if ($dataFim !== '') {
            $sql .= " AND DATE(d.criado_em) <= ? ";
            $params[] = $dataFim;
        }

        $sql .= " ORDER BY d.criado_em DESC ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $documentos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($documentos as &$doc) {
            if (!empty($doc['area_atual_desde']) && !empty($doc['prazo_resposta'])) {
                $inicio = new \DateTime($doc['area_atual_desde']);
                $agora = new \DateTime();

                $dias = $inicio->diff($agora)->days;
                $prazo = (int) $doc['prazo_resposta'];

                if ($dias <= $prazo) {
                    $doc['sla'] = 'ok';
                } elseif ($dias <= $prazo + 2) {
                    $doc['sla'] = 'alerta';
                } else {
                    $doc['sla'] = 'atrasado';
                }

                $doc['dias_parado'] = $dias;
            } else {
                $doc['sla'] = 'indefinido';
                $doc['dias_parado'] = null;
            }
        }

        // LISTAS PARA SELECTS
        $areas = \App\Models\DocumentoArea::all();
        $estados = \App\Models\DocumentoEstado::all();
        $tipos = \App\Models\DocumentoTipo::all();
        $utilizadores = \App\Models\Utilizador::all();

        return $this->render('@admin/tramitacao/lista.twig', [
                    'documentos' => $documentos,
                    'areas' => $areas,
                    'estados' => $estados,
                    'tipos' => $tipos,
                    'utilizadores' => $utilizadores,
        ]);
    }

    /**
     * ARQUIVAR DOCUMENTO
     */
    public function arquivar()
    {
        $user = Auth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            Sessao::flash('erro', 'Método inválido.');
            return $this->redirect('/admin/tramitacao');
        }

        if (!$user->hasPermissao('admin.tramitacao.estado')) {
            Sessao::flash('erro', 'Sem permissão.');
            return $this->redirect('/admin/tramitacao');
        }

        $documento_id = intval($_POST['documento_id'] ?? 0);

        if ($documento_id <= 0) {
            Sessao::flash('erro', 'Documento inválido.');
            return $this->redirect('/admin/tramitacao');
        }

        $documento = Documento::find($documento_id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/tramitacao');
        }

        // Histórico
        $mov = DocumentoTramitacao::create([
            'documento_id' => $documento->id,
            'utilizador_id' => $user->id,
            'acao' => 'ESTADO',
            'area_id' => $documento->area_atual_id,
            'comentario' => 'Documento arquivado.',
            'estado' => 'arquivado',
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        // Anexos (se existirem)
        $this->guardarAnexos($mov->id);

        // Atualizar documento
        $documento->update([
            'estado_atual' => 'arquivado'
                ], "id = {$documento->id}");

        // Notificação ao criador
        Notificacao::create([
            'utilizador_id' => $documento->criado_por,
            'documento_id' => $documento->id,
            'tipo' => 'estado',
            'mensagem' => "O documento '{$documento->titulo}' foi arquivado.",
            'url' => "/admin/tramitacao/{$documento->id}",
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        Sessao::flash('sucesso', 'Documento arquivado com sucesso.');
        return $this->redirect("/admin/tramitacao/{$documento->id}");
    }

    public function dashboard()
    {
        $this->authorize('admin.tramitacao.dashboard');

        $db = \App\Core\Conexao::getInstancia();

        // NOVOS
        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'novo'");
        $stmt->execute();
        $novos = $stmt->fetchColumn();

        // PENDENTES
        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'pendente'");
        $stmt->execute();
        $pendentes = $stmt->fetchColumn();

        // EM TRAMITAÇÃO
        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_tramitacao'");
        $stmt->execute();
        $em_tramitacao = $stmt->fetchColumn();

        // EM ANÁLISE
        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'analise'");
        $stmt->execute();
        $em_analise = $stmt->fetchColumn();

        // CONCLUÍDOS
        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'concluido'");
        $stmt->execute();
        $concluidos = $stmt->fetchColumn();
        
        // DEVOLVIDOS
        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'devolvido'");
        $stmt->execute();
        $devolvidos = $stmt->fetchColumn();

        // ARQUIVADOS
        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'arquivado'");
        $stmt->execute();
        $arquivados = $stmt->fetchColumn();
      
        // TOTAL
        $total = $novos + $pendentes + $em_tramitacao + $em_analise + $concluidos + $arquivados + $devolvidos;
        if ($total == 0) {
            $total = 1;
        }

        return $this->render('@admin/tramitacao/dashboard.twig', [
                    'novos' => $novos,
                    'pendentes' => $pendentes,
                    'em_tramitacao' => $em_tramitacao,
                    'em_analise' => $em_analise,
                    'concluidos' => $concluidos,
                    'devolvidos' => $devolvidos,
                    'arquivados' => $arquivados,
                    'total' => $total,
        ]);
    }
}
