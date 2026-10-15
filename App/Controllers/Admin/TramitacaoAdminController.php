<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Auth;
use App\Core\Sessao;
use App\Core\Conexao;
use App\Models\Documento;
use App\Models\DocumentoFicheiro;
use App\Models\DocumentoArea;
use App\Models\DocumentoEstado;
use App\Models\DocumentoTramitacao;
use App\Models\DocumentoTramitacaoAnexo;
use App\Models\DocumentosArquivados;
use App\Models\Notificacao;
use App\Models\DocumentoTipo;
use App\Models\Utilizador;
use App\Services\PathGuardService;
use App\Services\DocumentoExistsValidator;
use App\Services\AreaExistsValidator;
use App\Services\UtilizadorExistsValidator;
use App\Services\EstadoService;
use PDO;

class TramitacaoAdminController extends BaseController
{

    private function notificar($utilizador_id, $documento_id, $tipo, $mensagem)
    {
        Notificacao::create([
            'utilizador_id' => $utilizador_id,
            'documento_id' => $documento_id,
            'tipo' => $tipo,
            'mensagem' => $mensagem,
            'lida' => 0,
        ]);
    }

    public function documento($id)
    {
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
            SELECT 
                d.*,
                t.nome AS tipo_nome,
                a.nome AS area_nome,
                u.nome AS criador_nome
            FROM documentos d
            LEFT JOIN documento_tipos t ON t.tipo_id = d.tipo_id
            LEFT JOIN documento_areas a ON a.id = d.area_atual_id
            LEFT JOIN utilizadores u ON u.id = d.criado_por
            WHERE d.id = ?
            LIMIT 1
        ");

        $stmt->execute([$id]);
        $documento = $stmt->fetch(PDO::FETCH_OBJ);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect("/admin/tramitacao");
        }

        $anexos = DocumentoFicheiro::anexosDoDocumento($id);
        $historico = DocumentoTramitacao::porDocumento($id);

        foreach ($historico as $h) {

            $h->anexos = DocumentoTramitacaoAnexo::anexosPorHistorico($h->id);

            if (!empty($h->estado)) {

                $estado = DocumentoEstado::findByCodigo($h->estado);

                if ($estado) {
                    $h->estado_nome = $estado->nome;
                    $h->estado_codigo = $estado->codigo;
                } else {
                    $h->estado_nome = strtoupper(str_replace('_', ' ', $h->estado));
                    $h->estado_codigo = $h->estado;
                }
            } else {
                $h->estado_nome = null;
                $h->estado_codigo = null;
            }
        }

        $areas = DocumentoArea::all();
        $estados = DocumentoEstado::all();

        return $this->render('@admin/tramitacao/documento.twig', [
                    'documento' => $documento,
                    'anexos' => $anexos,
                    'historico' => $historico,
                    'areas' => $areas,
                    'estados' => $estados,
                    'user' => Auth::user(),
        ]);
    }

    public function encaminhar()
    {
        try {
            $user = Auth::user();

            if (!$user->hasPermissao('admin.tramitacao.encaminhar')) {
                return $this->error(403, "Não tem permissão para encaminhar documentos.");
            }

            $id = intval($_POST['documento_id'] ?? 0);
            $documento = DocumentoExistsValidator::validarOuFalhar($id);

            $nova_area = intval($_POST['area_id'] ?? 0);
            if ($nova_area <= 0) {
                return $this->error(422, "Área inválida.");
            }

            $comentario = trim($_POST['comentario'] ?? '');

            $estado = EstadoService::validarOuFalhar('em_tramitacao');

            $documento->update([
                'area_atual_id' => $nova_area,
                'estado_atual' => $estado,
                'area_atual_desde' => date('Y-m-d H:i:s'),
                    ], "id = {$id}");

            $mov = DocumentoTramitacao::create([
                'documento_id' => $id,
                'area_id' => $nova_area,
                'utilizador_id' => $user->id,
                'acao' => 'ENCAMINHADO',
                'estado' => $estado,
                'comentario' => $comentario ?: null,
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            // ⭐ AGORA SIM: grava anexos da tramitação
            DocumentoTramitacaoAnexo::guardarAnexos($mov->id);

            Sessao::flash('sucesso', 'Documento encaminhado com sucesso.');
            return $this->redirect("/admin/tramitacao/{$id}");
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }

    public function comentar()
    {
        try {
            $user = Auth::user();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return $this->error(405, "Método inválido.");
            }

            if (!$user->hasPermissao('admin.tramitacao.comentar')) {
                return $this->error(403, "Sem permissão para comentar.");
            }

            $documento_id = intval($_POST['documento_id'] ?? 0);
            $documento = DocumentoExistsValidator::validarOuFalhar($documento_id);

            $comentario = trim($_POST['comentario'] ?? '');
            if ($comentario === '') {
                return $this->error(422, "Comentário inválido.");
            }

            $mov = DocumentoTramitacao::create([
                'documento_id' => $documento->id,
                'utilizador_id' => $user->id,
                'acao' => 'COMENTARIO',
                'area_id' => $documento->area_atual_id,
                'comentario' => $comentario,
                'estado' => $documento->estado_atual,
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            DocumentoTramitacaoAnexo::guardarAnexos($mov->id);

            Notificacao::create([
                'utilizador_id' => $documento->criado_por,
                'documento_id' => $documento->id,
                'tipo' => 'comentario',
                'mensagem' => "O documento '{$documento->titulo}' recebeu um comentário.",
                'url' => "/admin/tramitacao/{$documento->id}",
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            Sessao::flash('sucesso', 'Comentário adicionado.');
            return $this->redirect("/admin/tramitacao/{$documento->id}");
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }

    public function estado()
    {
        try {
            $user = Auth::user();

            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                return $this->error(405, "Método inválido.");
            }

            if (!$user->hasPermissao('admin.tramitacao.estado')) {
                return $this->error(403, "Sem permissão para alterar estado.");
            }

            $documento_id = intval($_POST['documento_id'] ?? 0);
            $documento = DocumentoExistsValidator::validarOuFalhar($documento_id);

            $estado = trim($_POST['estado'] ?? '');
            $estado = EstadoService::validarOuFalhar($estado);

            $comentario = trim($_POST['comentario'] ?? '');

            $mov = DocumentoTramitacao::create([
                'documento_id' => $documento->id,
                'utilizador_id' => $user->id,
                'acao' => 'ESTADO',
                'area_id' => $documento->area_atual_id,
                'comentario' => $comentario ?: null,
                'estado' => $estado,
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            DocumentoTramitacaoAnexo::guardarAnexos($mov->id);

            $documento->update([
                'estado_atual' => $estado,
                    ], "id = {$documento->id}");

            Notificacao::create([
                'utilizador_id' => $documento->criado_por,
                'documento_id' => $documento->id,
                'tipo' => 'estado',
                'mensagem' => "O estado do documento '{$documento->titulo}' foi alterado para '{$estado}'.",
                'url' => "/admin/tramitacao/{$documento->id}",
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            Sessao::flash('sucesso', 'Estado alterado.');
            return $this->redirect("/admin/tramitacao/{$documento->id}");
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }

    public function ver($id)
    {
        try {
            $documento = Documento::find($id);

            if (!$documento) {
                return $this->error(404, "Documento não encontrado.");
            }

            $area = DocumentoArea::find($documento->area_atual_id);
            $tipo = DocumentoTipo::find($documento->tipo_id);
            $criador = Utilizador::find($documento->criado_por);

            // CORREÇÃO CRÍTICA
            $ficheiros = (new DocumentoFicheiro())
                    ->where('documento_id', '=', $id)
                    ->get();

            // CORREÇÃO CRÍTICA — faltava isto!
            $historico = DocumentoTramitacao::porDocumento($id);

            foreach ($historico as $h) {

                $h->anexos = DocumentoTramitacaoAnexo::anexosPorHistorico($h->id);
                $h->area = DocumentoArea::find($h->area_id);
                $h->utilizador = Utilizador::find($h->utilizador_id);

                if (!empty($h->estado)) {
                    $estado = DocumentoEstado::findByCodigo($h->estado);

                    if ($estado) {
                        $h->estado_nome = $estado->nome;
                        $h->estado_codigo = $estado->codigo;
                    } else {
                        $h->estado_nome = strtoupper(str_replace('_', ' ', $h->estado));
                        $h->estado_codigo = $h->estado;
                    }
                } else {
                    $h->estado_nome = null;
                    $h->estado_codigo = null;
                }
            }

            return $this->view('@admin/documentos/editar.twig', [
                        'documento' => $documento,
                        'area' => $area,
                        'tipo' => $tipo,
                        'criador' => $criador,
                        'ficheiros' => $ficheiros,
                        'historico' => $historico,
                        'tab' => 'tramitacao'
            ]);
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }

    public function abrirAnexo($id)
    {
        PathGuardService::init();

        $ficheiro = DocumentoFicheiro::find($id);

        if (!$ficheiro) {
            return $this->error(404, "O anexo solicitado não existe.");
        }

        // ⭐ Caminho correto com barras garantidas
        $path = rtrim(STORAGE_DOCUMENTOS, '/') . '/' . trim($ficheiro->caminho, '/') . '/' . $ficheiro->ficheiro;

        PathGuardService::proteger($path);

        if (!file_exists($path)) {
            return $this->error(404, "Ficheiro não encontrado.");
        }

        $mime = mime_content_type($path);

        header("Content-Type: {$mime}");
        header("Content-Disposition: inline; filename=\"" . basename($ficheiro->ficheiro) . "\"");
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit;
    }

    public function abrirAnexoTramitacao($id)
    {
        PathGuardService::init();

        $ficheiro = DocumentoTramitacaoAnexo::find($id);

        if (!$ficheiro) {
            return $this->error(404, "O anexo de tramitação solicitado não existe.");
        }

        // ⭐ Caminho corrigido com barras garantidas
        $path = rtrim(STORAGE_TRAMITACAO, '/') . '/' . trim($ficheiro->caminho, '/') . '/' . $ficheiro->ficheiro;

        PathGuardService::proteger($path);

        if (!file_exists($path)) {
            return $this->error(404, "Ficheiro de tramitação não encontrado.");
        }

        $mime = mime_content_type($path);

        header("Content-Type: {$mime}");
        header("Content-Disposition: inline; filename=\"" . basename($ficheiro->ficheiro_original ?: $ficheiro->ficheiro) . "\"");
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit;
    }

    public function lista()
    {
        $this->authorize('admin.tramitacao.ver');

        $db = Conexao::getInstancia();

        $estado = $_GET['estado'] ?? '';
        $area = $_GET['area'] ?? '';
        $criador = $_GET['criador'] ?? '';
        $tipo = $_GET['tipo'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';

        // Estados permitidos (sem arquivado)
        $estadosPermitidos = DocumentoEstado::semArquivado();
        $codigosEstados = array_map(fn($e) => strtolower(trim($e->codigo)), $estadosPermitidos);

        // SQL base
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
        WHERE 1 = 1
    ";

        $params = [];

        // Apenas aplica IN() se existirem estados
        if (!empty($codigosEstados)) {
            $placeholders = implode(',', array_fill(0, count($codigosEstados), '?'));
            $sql .= " AND LOWER(TRIM(d.estado_atual)) IN ($placeholders) ";
            $params = array_merge($params, $codigosEstados);
        }

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
        $documentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

        $areas = DocumentoArea::all();
        $estados = DocumentoEstado::all();
        $tipos = DocumentoTipo::all();
        $utilizadores = Utilizador::all();

        return $this->render('@admin/tramitacao/lista.twig', [
                    'documentos' => $documentos,
                    'areas' => $areas,
                    'estados' => $estados,
                    'tipos' => $tipos,
                    'utilizadores' => $utilizadores,
        ]);
    }

    public function arquivar()
    {
        $user = Auth::user();

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            return $this->error(405, "Método inválido.");
        }

        if (!$user->hasPermissao('admin.tramitacao.estado')) {
            return $this->error(403, "Sem permissão para arquivar documentos.");
        }

        $documento_id = intval($_POST['documento_id'] ?? 0);

        if ($documento_id <= 0) {
            return $this->error(422, "Documento inválido.");
        }

        $documento = Documento::find($documento_id);

        if (!$documento) {
            return $this->error(404, "Documento não encontrado.");
        }

        // Último ficheiro
        $ficheiro = DocumentoFicheiro::query()
                ->where('documento_id', '=', $documento->id)
                ->orderBy('criado_em', 'DESC')
                ->first();

        if (!$ficheiro) {
            return $this->error(422, "Este documento não possui ficheiro para arquivar.");
        }

        // Caminho origem
        $origem = rtrim(STORAGE_DOCUMENTOS, '/') . '/' . trim($ficheiro->caminho, '/') . '/' . $ficheiro->ficheiro;

        if (!file_exists($origem)) {
            return $this->error(500, "O ficheiro original não existe no servidor.");
        }

        // Criar subpasta por data
        $subpasta = date('Y') . '/' . date('m') . '/' . date('d') . '/';
        $pastaArquivados = rtrim(STORAGE_ARQUIVADOS, '/') . '/' . $subpasta;

        if (!is_dir($pastaArquivados)) {
            mkdir($pastaArquivados, 0777, true);
        }

        // Caminho destino (CORRIGIDO)
        $destino = rtrim($pastaArquivados, '/') . '/' . $ficheiro->ficheiro;

        if (!copy($origem, $destino)) {
            return $this->error(500, "Falha ao arquivar o ficheiro.");
        }

        // Criar registo arquivado
        DocumentosArquivados::create([
            'documento_id' => $documento->id,
            'ficheiro' => $ficheiro->ficheiro,
            'caminho' => $subpasta,
            'mime_type' => $ficheiro->mime_type,
            'tamanho' => $ficheiro->tamanho,
            'arquivado_em' => date('Y-m-d H:i:s'),
            'arquivado_por' => $user->id
        ]);

        // Atualizar documento (CORRIGIDO)
        $documento->update([
            'estado_atual' => 'arquivado',
            'arquivado_em' => date('Y-m-d H:i:s'),
            'arquivado_por_id' => $user->id,
            'area_atual_id' => null
                ], "id = {$documento->id}");

        // Histórico
        DocumentoTramitacao::create([
            'documento_id' => $documento->id,
            'area_id' => null,
            'utilizador_id' => $user->id,
            'acao' => 'ARQUIVADO',
            'estado' => 'arquivado',
            'comentario' => 'Documento arquivado.',
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        Sessao::flash('sucesso', 'Documento arquivado com sucesso.');
        return $this->redirect('/admin/documentos/arquivados');
    }

    public function dashboard()
    {
        $this->authorize('admin.tramitacao.dashboard');

        $db = Conexao::getInstancia();

        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'novo'");
        $stmt->execute();
        $novos = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'pendente'");
        $stmt->execute();
        $pendentes = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'em_tramitacao'");
        $stmt->execute();
        $em_tramitacao = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'analise'");
        $stmt->execute();
        $em_analise = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'concluido'");
        $stmt->execute();
        $concluidos = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'devolvido'");
        $stmt->execute();
        $devolvidos = $stmt->fetchColumn();

        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = 'arquivado'");
        $stmt->execute();
        $arquivados = $stmt->fetchColumn();

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
