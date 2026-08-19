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
use App\Models\Notificacao;
use App\Models\DocumentoTipo;
use App\Models\Utilizador;
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

    private function guardarAnexos(int $tramitacao_id): void
    {
        try {

            if (empty($_FILES['anexos']['name'][0])) {
                return;
            }

            $baseDir = ROOT_PATH . '/public/uploads/tramitacao/' . $tramitacao_id . '/';

            if (!is_dir($baseDir) && !mkdir($baseDir, 0775, true)) {
                throw new \Exception("Não foi possível criar o diretório de anexos.");
            }

            foreach ($_FILES['anexos']['name'] as $i => $nomeOriginal) {

                if (!is_uploaded_file($_FILES['anexos']['tmp_name'][$i])) {
                    continue;
                }

                $tmp = $_FILES['anexos']['tmp_name'][$i];
                $nomeSeguro = preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($nomeOriginal));
                $nomeGuardado = uniqid('anx_', true) . '_' . $nomeSeguro;

                // Validação de MIME
                $mime = mime_content_type($tmp);
                $permitidos = [
                    'application/pdf',
                    'image/jpeg',
                    'image/png',
                    'image/gif',
                    'image/webp'
                ];

                if (!in_array($mime, $permitidos)) {
                    throw new \Exception("Tipo de ficheiro não permitido: {$mime}");
                }

                // Validação de tamanho (máx. 20MB)
                if ($_FILES['anexos']['size'][$i] > 20 * 1024 * 1024) {
                    throw new \Exception("O ficheiro excede o tamanho máximo permitido (20MB).");
                }

                if (move_uploaded_file($tmp, $baseDir . $nomeGuardado)) {
                    DocumentoTramitacaoAnexo::create([
                        'tramitacao_id' => $tramitacao_id,
                        'ficheiro' => $nomeGuardado,
                        'nome_original' => $nomeOriginal,
                    ]);
                } else {
                    throw new \Exception("Falha ao guardar o ficheiro.");
                }
            }
        } catch (\Exception $e) {
            // Erro interno → página 500
            $this->error(500, $e->getMessage());
        }
    }

    /**
     * Página principal de TRAMITAÇÃO do documento
     */
    public function documento($id)
    {
        $db = Conexao::getInstancia();

        // Documento completo
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

        // Anexos do documento
        $anexos = DocumentoFicheiro::anexosDoDocumento($id);

        // Histórico completo
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

        // Áreas e estados
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

    /**
     * ENCAMINHAR DOCUMENTO
     */
    public function encaminhar()
    {
        try {

            $user = Auth::user();

            if (!$user->hasPermissao('admin.tramitacao.encaminhar')) {
                return $this->error(403, "Não tem permissão para encaminhar documentos.");
            }

            $id = $_POST['documento_id'] ?? null;
            $nova_area = $_POST['area_id'] ?? null;
            $comentario = trim($_POST['comentario'] ?? '');

            if (!$id || !$nova_area) {
                return $this->error(422, "Dados inválidos.");
            }

            $documento = Documento::find($id);

            if (!$documento) {
                return $this->error(404, "Documento não encontrado.");
            }

            $documento->update([
                'area_atual_id' => $nova_area,
                'estado_atual' => 'em_tramitacao',
                'area_atual_desde' => date('Y-m-d H:i:s'),
                    ], "id = {$id}");

            $mov = DocumentoTramitacao::create([
                'documento_id' => $id,
                'area_id' => $nova_area,
                'utilizador_id' => $user->id,
                'acao' => 'ENCAMINHADO',
                'estado' => 'em_tramitacao',
                'comentario' => $comentario ?: null,
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            $this->guardarAnexos($mov->id);

            Sessao::flash('sucesso', 'Documento encaminhado com sucesso.');
            return $this->redirect("/admin/tramitacao/{$id}");
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }

    /**
     * COMENTAR
     */
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
            $comentario = trim($_POST['comentario'] ?? '');

            if ($documento_id <= 0 || $comentario === '') {
                return $this->error(422, "Comentário inválido.");
            }

            $documento = Documento::find($documento_id);

            if (!$documento) {
                return $this->error(404, "Documento não encontrado.");
            }

            // Histórico — gravar na coluna CORRETA: estado
            $mov = DocumentoTramitacao::create([
                'documento_id' => $documento->id,
                'utilizador_id' => $user->id,
                'acao' => 'COMENTARIO',
                'area_id' => $documento->area_atual_id,
                'comentario' => $comentario,
                'estado' => $documento->estado_atual,
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            $this->guardarAnexos($mov->id);

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

    /**
     * ALTERAR ESTADO
     */
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
            $estado = trim($_POST['estado'] ?? '');
            $comentario = trim($_POST['comentario'] ?? '');

            if ($documento_id <= 0 || $estado === '') {
                return $this->error(422, "Dados inválidos.");
            }

            $documento = Documento::find($documento_id);

            if (!$documento) {
                return $this->error(404, "Documento não encontrado.");
            }

            // Histórico — gravar na coluna CORRETA: estado
            $mov = DocumentoTramitacao::create([
                'documento_id' => $documento->id,
                'utilizador_id' => $user->id,
                'acao' => 'ESTADO',
                'area_id' => $documento->area_atual_id,
                'comentario' => $comentario ?: null,
                'estado' => $estado,
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            $this->guardarAnexos($mov->id);

            // Atualizar estado do documento
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

            // Carregar área atual
            $area = DocumentoAreas::find($documento->area_atual_id);

            if (!$area) {
                return $this->error(404, "A área atual do documento não existe.");
            }

            // Carregar tipo
            $tipo = DocumentoTipo::find($documento->tipo_id);

            if (!$tipo) {
                return $this->error(404, "O tipo do documento não existe.");
            }

            // Carregar criador
            $criador = Utilizador::find($documento->criado_por);

            if (!$criador) {
                return $this->error(404, "O utilizador criador do documento não existe.");
            }

            // Carregar ficheiros
            $ficheiros = DocumentoFicheiro::where('documento_id', $id)->get();

            // Carregar histórico
            $historico = DocumentoTramitacao::where('documento_id', $id)
                    ->orderBy('criado_em', 'ASC')
                    ->get();

            return $this->view('@admin/documentos/tabs/tramitacao.twig', [
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
        try {

            $ficheiro = DocumentoFicheiro::find($id);

            if (!$ficheiro) {
                return $this->error(404, "O anexo solicitado não existe.");
            }

            // Caminho correto para a tua estrutura
            $path = __DIR__ . '/../../../storage/documentos/' . $ficheiro->ficheiro;

            // Se o ficheiro foi removido da BD mas ainda existe o registo
            if ($ficheiro->removido_em ?? false) {
                return $this->error(410, "Este anexo foi removido.", [
                            'back_url' => "/admin/tramitacao/{$ficheiro->documento_id}"
                ]);
            }

            if (!file_exists($path)) {
                return $this->error(404, "O ficheiro não existe no disco.");
            }

            $mime = $ficheiro->mime ?? mime_content_type($path);

            header("Content-Type: {$mime}");

            // Inline para PDF e imagens
            if (in_array($mime, [
                        'application/pdf',
                        'image/jpeg',
                        'image/png',
                        'image/gif',
                        'image/webp'
                    ])) {
                header("Content-Disposition: inline; filename=\"{$ficheiro->ficheiro_original}\"");
            } else {
                header("Content-Disposition: attachment; filename=\"{$ficheiro->ficheiro_original}\"");
            }

            readfile($path);
            exit;
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
    }

    /**
     * LISTA DE DOCUMENTOS EM TRAMITAÇÃO
     */
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
        WHERE LOWER(TRIM(d.estado_atual)) IN (
    'novo',
    'pendente',
    'analise',
    'em_analise',
    'em_tramitacao',
    'concluido'
)
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

    /**
     * ARQUIVAR DOCUMENTO
     */
    public function arquivar()
    {
        try {

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

            // Histórico — gravar na coluna CORRETA: estado
            $mov = DocumentoTramitacao::create([
                'documento_id' => $documento->id,
                'utilizador_id' => $user->id,
                'acao' => 'ESTADO',
                'area_id' => $documento->area_atual_id,
                'comentario' => 'Documento arquivado.',
                'estado' => 'arquivado', // ✔ CORRETO
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            $this->guardarAnexos($mov->id);

            // Atualizar estado do documento
            $documento->update([
                'estado_atual' => 'arquivado',
                    ], "id = {$documento->id}");

            Notificacao::create([
                'utilizador_id' => $documento->criado_por,
                'documento_id' => $documento->id,
                'tipo' => 'estado',
                'mensagem' => "O documento '{$documento->titulo}' foi arquivado.",
                'url' => "/admin/tramitacao/{$documento->id}",
                'criado_em' => date('Y-m-d H:i:s'),
            ]);

            Sessao::flash('sucesso', 'Documento arquivado com sucesso.');
            return $this->redirect("/admin/tramitacao/{$documento->id}");
        } catch (\Exception $e) {
            return $this->error(500, $e->getMessage());
        }
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
