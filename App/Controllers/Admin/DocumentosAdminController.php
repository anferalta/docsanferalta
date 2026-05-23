<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Models\DocumentoTipo;
use App\Models\Documento;
use App\Core\Auth;
use App\Core\Sessao;
use App\Core\Conexao;
use App\Models\Utilizador;
use App\Models\DocumentoTramitacao;
use App\Models\DocumentoEstado;

class DocumentosAdminController extends BaseController
{

    private function autorizar(string $permissao)
    {
        $user = \App\Core\Auth::user();

        // Admin tem acesso total
        if ($user && $user->isAdmin()) {
            return true;
        }

        // Verificar permissão
        if (!\App\Core\Permission::tem($permissao)) {
            http_response_code(403);
            exit("Acesso negado. Permissão necessária: $permissao");
        }

        return true;
    }

    private array $extensoesPermitidas = ['pdf', 'txt', 'doc', 'docx', 'xls', 'xlsx', 'png', 'jpg', 'jpeg'];
    private array $mimePermitidos = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/png',
        'image/jpeg'
    ];

    public function index()
    {
        $db = Conexao::getInstancia();
        $user = Auth::user();
        $acl = new \App\Core\Acl();

        // PERFIL DO UTILIZADOR
        $perfil = strtolower($user->perfil->nome ?? '');

        // PERFIS QUE PODEM VER TODOS OS DOCUMENTOS
        $podeVerTodos = (
                in_array($perfil, ['admin', 'gestor', 'supervisor']) || $acl->has('admin.documentos.ver_todos')
        );

        // ============================
        // 1. Filtros
        // ============================
        $tipo_id = $_GET['tipo_id'] ?? null;
        $estado_atual = $_GET['estado_atual'] ?? null;
        $area_atual_id = $_GET['area_atual_id'] ?? null;
        $data_inicio = $_GET['data_inicio'] ?? null;
        $data_fim = $_GET['data_fim'] ?? null;
        $pesquisa = $_GET['q'] ?? null;

        // CORREÇÃO CRÍTICA — garantir que só filtra se vier preenchido
        $utilizador_id = (isset($_GET['utilizador']) && $_GET['utilizador'] !== '') ? intval($_GET['utilizador']) : null;

        // ============================
        // Ordenação segura
        // ============================
        $ordenar = $_GET['sort'] ?? 'id';
        $direcao = strtolower($_GET['dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';

        $colunas = [
            'ficheiro' => 'd.ficheiro_original',
            'titulo' => 'd.titulo',
            'tipo' => 't.nome',
            'tamanho' => 'd.tamanho',
            'criador' => 'u.nome',
            'data' => 'd.criado_em',
            'id' => 'd.id'
        ];

        if (!isset($colunas[$ordenar])) {
            $ordenar = 'id';
        }

        $orderBy = $colunas[$ordenar] . " " . $direcao;

        // ============================
        // 2. Query base
        // ============================
        $sql = "
SELECT SQL_CALC_FOUND_ROWS
    d.*,
    t.nome AS tipo_nome,
    u.nome AS criador_nome,
    a.nome AS area_nome
FROM documentos d
LEFT JOIN documento_tipos t ON t.tipo_id = d.tipo_id
LEFT JOIN utilizadores u ON u.id = d.criado_por
LEFT JOIN documento_areas a ON a.id = d.area_atual_id
WHERE 1=1
  AND d.estado_atual != 'arquivado'
";

        // ============================
        // 3. ACL — aplicar filtro de visibilidade
        // ============================
        if (!$podeVerTodos) {
            $sql .= " AND d.criado_por = " . intval($user->id);
        }

        // ============================
        // 4. Filtros
        // ============================
        if ($tipo_id) {
            $sql .= " AND d.tipo_id = " . intval($tipo_id);
        }

        if ($utilizador_id !== null && $podeVerTodos) {
            $sql .= " AND d.criado_por = " . intval($utilizador_id);
        }

        if ($data_inicio) {
            $sql .= " AND d.criado_em >= " . $db->quote($data_inicio . " 00:00:00");
        }

        if ($data_fim) {
            $sql .= " AND d.criado_em <= " . $db->quote($data_fim . " 23:59:59");
        }

        if ($pesquisa) {
            $pesq = "%" . addslashes($pesquisa) . "%";
            $sql .= " AND (d.titulo LIKE " . $db->quote($pesq) . "
               OR d.ficheiro_original LIKE " . $db->quote($pesq) . ")";
        }

        if ($estado_atual) {
            $sql .= " AND d.estado_atual = " . $db->quote($estado_atual);
        }

        if ($area_atual_id) {
            $sql .= " AND d.area_atual_id = " . intval($area_atual_id);
        }

        // ============================
        // 5. Ordenação
        // ============================
        $sql .= " ORDER BY $orderBy";

        // ============================
        // 6. Paginação
        // ============================
        $porPagina = 20;
        $pagina = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
        $offset = ($pagina - 1) * $porPagina;

        $sql .= " LIMIT $porPagina OFFSET $offset";

        // ============================
        // 7. Executar
        // ============================
        $stmt = $db->query($sql);
        $documentos = $stmt->fetchAll(\PDO::FETCH_CLASS, Documento::class);

        $total = $db->query("SELECT FOUND_ROWS()")->fetchColumn();
        $paginacao = $this->paginacao($total, $porPagina, $pagina);

        // ============================
        // 8. Dados auxiliares
        // ============================
        $tipos = DocumentoTipo::all();
        $utilizadores = (new Utilizador())->orderBy('nome')->get();
        $areas = $db->query("SELECT id, nome FROM documento_areas WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();

        // ============================
        // 9. Renderizar
        // ============================
        return $this->render('@admin/documentos/index.twig', [
                    'documentos' => $documentos,
                    'tipos' => $tipos,
                    'areas' => $areas,
                    'utilizadores' => $utilizadores,
                    'estado_atual' => $estado_atual,
                    'area_atual_id' => $area_atual_id,
                    'paginacao' => $paginacao
        ]);
    }

    public function criar()
    {
        $tipos = DocumentoTipo::all();

        return $this->render('@admin/documentos/criar.twig', [
                    'tipos' => $tipos
        ]);
    }

    public function criarSubmit()
    {
        $user = Auth::user();

        // ============================
        // 1. Validação
        // ============================
        $erro = \App\Services\DocumentoValidator::validarCriacao($_POST, $_FILES);

        if ($erro) {
            Sessao::flash('erro', $erro);
            return $this->redirect('/admin/documentos/criar');
        }

        // ============================
        // 2. Upload + gravação
        // ============================
        try {
            \App\Services\DocumentoUploader::processarUpload($_POST, $_FILES, $user);
        } catch (\Exception $e) {
            Sessao::flash('erro', $e->getMessage());
            return $this->redirect('/admin/documentos/criar');
        }

        // ============================
        // 3. Sucesso
        // ============================
        Sessao::flash('sucesso', 'Documentos carregados com sucesso.');
        return $this->redirect('/admin/documentos');
    }

    public function editar($id)
    {
        $documento = Documento::find($id);
        $estados = DocumentoEstado::semArquivado();

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        $tipos = DocumentoTipo::all();

        // HISTÓRICO (correto)
        $historico = \App\Models\DocumentoTramitacao::porDocumento($id);

        // ÁREAS
        $areas = \App\Models\DocumentoArea::all();

        // ESTADOS
        $estados = \App\Models\DocumentoEstado::all();

        // USER
        $user = Auth::user();

        return $this->render('@admin/documentos/editar.twig', [
                    'documento' => $documento,
                    'tipos' => $tipos,
                    'historico' => $historico,
                    'areas' => $areas,
                    'estados' => $estados,
                    'user' => $user
        ]);
    }

    public function editarSubmit($id)
    {
        $documento = Documento::find($id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        $user = Auth::user();

        // Validação
        $erro = \App\Services\DocumentoValidator::validarEdicao($_POST);

        if ($erro) {
            Sessao::flash('erro', $erro);
            return $this->redirect("/admin/documentos/editar/{$id}");
        }

        // Atualizar título e tipo
        $documento->update(
                [
                    'titulo' => trim($_POST['titulo']),
                    'tipo_id' => $_POST['tipo_id']
                ],
                "id = :id",
                ['id' => $documento->id]
        );

        // Substituir ficheiro (opcional)
        if (!empty($_FILES['ficheiro']['name'])) {
            try {
                \App\Services\DocumentoUploader::substituirFicheiro($documento, $_FILES['ficheiro'], $user);
            } catch (\Exception $e) {
                Sessao::flash('erro', $e->getMessage());
                return $this->redirect("/admin/documentos/editar/{$id}");
            }
        }

        Sessao::flash('sucesso', 'Documento atualizado com sucesso.');
        return $this->redirect('/admin/documentos');
    }

    public function verFicheiro($ano, $mes, $dia, $ficheiro)
    {
        try {
            $path = \App\Services\DocumentoFileService::resolverCaminhoSeguro($ano, $mes, $dia, $ficheiro);
        } catch (\Exception $e) {

            error_log("Ficheiro não encontrado: $ano/$mes/$dia/$ficheiro");

            http_response_code(404);
            echo "Ficheiro não encontrado.";
            return;
        }

        header("Content-Type: " . mime_content_type($path));
        header("Content-Length: " . filesize($path));
        readfile($path);
        exit;
    }

    public static function validarEdicao(array $post): ?string
    {
        $titulo = trim($post['titulo'] ?? '');
        if ($titulo === '') {
            return 'O título é obrigatório.';
        }

        if (empty($post['tipo_id'])) {
            return 'Selecione o tipo de documento.';
        }

        return null;
    }

    public static function substituirFicheiro(Documento $documento, array $ficheiro, $user): void
    {
        $tmp = $ficheiro['tmp_name'];
        $nomeOriginal = $ficheiro['name'];
        $tamanho = $ficheiro['size'];
        $erro = $ficheiro['error'];

        if ($erro !== UPLOAD_ERR_OK) {
            throw new \Exception("Erro ao enviar o ficheiro: {$nomeOriginal}");
        }

        if (!is_uploaded_file($tmp)) {
            throw new \Exception("Ficheiro inválido: {$nomeOriginal}");
        }

        // ============================
        // 1. Construção segura da pasta
        // ============================
        $root = dirname(__DIR__, 2);

        $ano = date('Y');
        $mes = date('m');
        $dia = date('d');

        if (!$ano || !$mes || !$dia) {
            throw new \Exception("Erro interno: data inválida ($ano/$mes/$dia).");
        }

        $subpasta = "$ano/$mes/$dia";
        $base = $root . "/storage/documentos/$subpasta/";

        if (!is_dir($base)) {
            mkdir($base, 0777, true);
        }

        // ============================
        // 2. Guardar novo ficheiro
        // ============================
        $nomeSeguro = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nomeOriginal);
        $nomeGuardado = uniqid() . '_' . $nomeSeguro;

        $destino = $base . $nomeGuardado;

        if (!move_uploaded_file($tmp, $destino)) {
            throw new \Exception("Erro ao guardar o ficheiro: {$nomeOriginal}");
        }

        // ============================
        // 3. Apagar ficheiro antigo
        // ============================
        $antigo = $root . '/storage/documentos/' . $documento->caminho . $documento->ficheiro;

        if (file_exists($antigo)) {
            unlink($antigo);
        }

        // ============================
        // 4. Atualizar BD
        // ============================
        $documento->ficheiro = $nomeGuardado;
        $documento->ficheiro_original = $nomeOriginal;
        $documento->caminho = $subpasta . '/';
        $documento->tamanho = $tamanho;
        $documento->mime_type = mime_content_type($destino);
        $documento->hash = hash_file('sha256', $destino);
        $documento->save();
    }

    public function ficheirosExistentes()
    {
        $user = Auth::user();
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
        SELECT ficheiro_original 
        FROM documentos 
        WHERE criado_por = :uid
    ");
        $stmt->execute(['uid' => $user->id]);

        $nomes = $stmt->fetchAll(PDO::FETCH_COLUMN);

        header('Content-Type: application/json');
        echo json_encode($nomes);
        exit;
    }

    public function ver($id)
    {
        $documento = Documento::find($id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        $caminho = rtrim($documento->caminho, '/') . '/' . $documento->ficheiro;

        if (!file_exists($caminho)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        $ficheiro = basename($caminho);
        $ext = strtolower(pathinfo($ficheiro, PATHINFO_EXTENSION));

        // Extensões que DEVEM abrir no Google Docs Viewer
        $usarGoogleDocs = ['docx', 'xlsx', 'pptx'];

        if (in_array($ext, $usarGoogleDocs)) {

            // URL pública para o ficheiro
            $urlFicheiro = urlencode(url("/admin/documentos/download/$id"));

            // Viewer do Google
            $viewer = "https://docs.google.com/viewer?url={$urlFicheiro}&embedded=true";

            header("Location: $viewer");
            exit;
        }

        // MIME types corretos
        $mime = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'txt' => 'text/plain'
        ];

        header("Content-Type: " . ($mime[$ext] ?? mime_content_type($caminho)));
        header("Content-Disposition: inline; filename=\"$ficheiro\"");
        header("Content-Length: " . filesize($caminho));

        readfile($caminho);
        exit;
    }

    public function abrir($id)
    {
        $documento = Documento::find($id);

        if (!$documento) {
            http_response_code(404);
            exit("Documento não encontrado.");
        }

        // Caminho absoluto do ficheiro original
        $base = realpath(__DIR__ . '/../../../storage/documentos');
        $origem = $base . '/' . $documento->caminho . $documento->ficheiro;

        if (!file_exists($origem)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        $ext = strtolower(pathinfo($origem, PATHINFO_EXTENSION));
        $ficheiro = basename($origem);

        // Extensões que abrem inline
        $inline = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt'];

        if (in_array($ext, $inline)) {
            header("Content-Type: " . mime_content_type($origem));
            header("Content-Disposition: inline; filename=\"$ficheiro\"");
            header("Content-Length: " . filesize($origem));
            readfile($origem);
            exit;
        }

        // Extensões que devem ser convertidas para PDF
        $convertiveis = ['docx', 'xlsx', 'pptx'];

        if (in_array($ext, $convertiveis)) {

            // Caminho do PDF convertido
            $pdfDestino = $base . '/' . $documento->caminho . $documento->id . '.pdf';

            // Converter apenas se ainda não existir
            if (!file_exists($pdfDestino)) {
                $ok = $this->converterDocxParaPdf($origem, $pdfDestino);

                if (!$ok) {
                    // fallback → download direto
                    header("Content-Type: application/octet-stream");
                    header("Content-Disposition: attachment; filename=\"$ficheiro\"");
                    header("Content-Length: " . filesize($origem));
                    readfile($origem);
                    exit;
                }
            }

            // Abrir PDF inline
            header("Content-Type: application/pdf");
            header("Content-Disposition: inline; filename=\"preview.pdf\"");
            header("Content-Length: " . filesize($pdfDestino));
            readfile($pdfDestino);
            exit;
        }

        // Fallback para qualquer outro tipo → download
        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$ficheiro\"");
        header("Content-Length: " . filesize($origem));
        readfile($origem);
        exit;
    }

    public function abrir_raw($id)
    {
        // 1) Obter documento
        $documento = Documento::find($id);

        if (!$documento) {
            http_response_code(404);
            exit("Documento não encontrado.");
        }

        // 2) Caminho absoluto do ficheiro original
        $base = realpath(__DIR__ . '/../../../storage/documentos');
        $caminho = $base . '/' . $documento->caminho . $documento->ficheiro;

        if (!file_exists($caminho)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        // 3) Determinar MIME
        $mime = mime_content_type($caminho);
        $ficheiro = basename($caminho);

        // 4) Headers seguros para entrega direta
        header("Content-Type: $mime");
        header("Content-Disposition: inline; filename=\"$ficheiro\"");
        header("Content-Length: " . filesize($caminho));

        // 5) Entregar ficheiro
        readfile($caminho);
        exit;
    }

    /**
     * Converte DOCX/XLSX/PPTX para PDF usando LibreOffice (modo headless)
     * com cache inteligente (não reconverte se já existir).
     *
     * @param string $origem  Caminho absoluto do ficheiro original
     * @param string $pdfDestino Caminho absoluto do PDF final
     * @return bool  true se o PDF foi criado ou já existia, false se falhou
     */
    private function converterDocxParaPdf($origem, $pdfDestino)
    {
        // Cache inteligente
        if (file_exists($pdfDestino) && filesize($pdfDestino) > 0) {
            return true;
        }

        $dir = dirname($pdfDestino);
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Caminho do LibreOffice
        $soffice = '"C:\Program Files\LibreOffice\program\soffice.exe"';

        // Comando
        $cmd = $soffice
                . ' --headless --nologo --nofirststartwizard'
                . ' --convert-to pdf'
                . ' "' . $origem . '"'
                . ' --outdir "' . $dir . '"';

        // LOG para debug
        file_put_contents(__DIR__ . '/../../../storage/logs/conversao.log',
                date('Y-m-d H:i:s') . "\nCMD: $cmd\n", FILE_APPEND);

        exec($cmd . " 2>&1", $output, $returnCode);

        // LOG do output
        file_put_contents(__DIR__ . '/../../../storage/logs/conversao.log',
                "RETORNO: $returnCode\n" . print_r($output, true) . "\n\n", FILE_APPEND);

        // Verificar se o PDF foi criado
        return file_exists($pdfDestino) && filesize($pdfDestino) > 0;
    }

    public function download($id)
    {
        $documento = Documento::find($id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        $caminho = rtrim($documento->caminho, '/') . '/' . $documento->ficheiro;

        if (!file_exists($caminho)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        $ficheiro = basename($caminho);

        header("Content-Type: application/octet-stream");
        header("Content-Disposition: attachment; filename=\"$ficheiro\"");
        header("Content-Length: " . filesize($caminho));

        readfile($caminho);
        exit;
    }

    public function eliminar($id)
    {
        $documento = Documento::find($id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        $root = dirname(__DIR__, 3);
        $ficheiro = $root . '/storage/documentos/' . $documento->caminho . $documento->ficheiro;

        if (file_exists($ficheiro)) {
            unlink($ficheiro);
        }

        $documento->delete($documento->id);

        Sessao::flash('sucesso', 'Documento eliminado com sucesso.');
        return $this->redirect('/admin/documentos');
    }

    public function downloadMultiple()
    {
        $user = Auth::user();

        if (empty($_POST['docs']) || !is_array($_POST['docs'])) {
            Sessao::flash('erro', 'Nenhum documento selecionado.');
            return $this->redirect('/admin/documentos');
        }

        try {
            $zipPath = \App\Services\DocumentoZipService::criarZip($_POST['docs'], $user);
        } catch (\Exception $e) {
            Sessao::flash('erro', $e->getMessage());
            return $this->redirect('/admin/documentos');
        }

        header("Content-Type: application/zip");
        header("Content-Disposition: attachment; filename=\"" . basename($zipPath) . "\"");
        header("Content-Length: " . filesize($zipPath));

        readfile($zipPath);
        unlink($zipPath);
        exit;
    }

    public function arquivados()
    {
        $this->autorizar('admin.documentos.arquivados.ver');

        $docModel = new Documento();

        $docModel->leftJoin(
                'documento_tipos',
                'documento_tipos.tipo_id',
                '=',
                'documentos.tipo_id'
        );

        $docModel->where('documentos.estado_atual', '=', 'arquivado');
        $docModel->orderBy('documentos.arquivado_em', 'DESC');

        $documentos = $docModel->get();

        return $this->render('@admin/documentos/arquivados.twig', [
                    'documentos' => $documentos
        ]);
    }

    public function verArquivado($id)
    {
        $this->autorizar('admin.documentos.arquivados.ver');

        $documento = Documento::find($id);

        if (!$documento || $documento->estado_atual !== 'arquivado') {
            http_response_code(404);
            exit("Documento arquivado não encontrado.");
        }

        // AQUI ESTÁ A CORREÇÃO
        $historico = DocumentoTramitacao::porDocumento($id);

        return $this->render('@admin/documentos/ver_arquivado.twig', [
                    'documento' => $documento,
                    'historico' => $historico
        ]);
    }

    public function recuperarArquivado($id)
    {
        $this->autorizar('admin.documentos.arquivados.recuperar');

        $documento = Documento::find($id);

        if (!$documento || $documento->estado_atual !== 'arquivado') {
            http_response_code(404);
            exit("Documento arquivado não encontrado.");
        }

        // Guardar a área onde estava antes de arquivar
        $areaAnterior = $documento->area_atual_id;

        // Recuperar o documento
        $documento->estado_atual = 'novo';
        $documento->arquivado_em = null;
        $documento->arquivado_por_id = null;

        // 🔥 Mantém a área original
        $documento->area_atual_id = $areaAnterior;

        $documento->save();

        // Registar tramitação
        DocumentoTramitacao::create([
            'documento_id' => $id,
            'area_id' => $areaAnterior,
            'utilizador_id' => Auth::id(),
            'acao' => 'RECUPERAR',
            'estado' => 'recuperado',
            'comentario' => 'Documento recuperado do arquivo para produção.',
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        Sessao::flash('sucesso', 'Documento recuperado com sucesso.');

        return $this->redirect("/admin/documentos/editar/{$id}");
    }

    public function arquivar($id)
    {
        $this->authorize('admin.documentos.arquivar');

        $documento = Documento::find($id);

        if (!$documento) {
            Sessao::flash('erro', 'Documento não encontrado.');
            return $this->redirect('/admin/documentos');
        }

        // Evitar arquivar duas vezes
        if ($documento->estado_atual === 'arquivado') {
            Sessao::flash('info', 'Este documento já se encontra arquivado.');
            return $this->redirect("/admin/documentos/editar/{$id}");
        }

        // Atualizar documento
        $documento->update([
            'estado_atual' => 'arquivado',
            'arquivado_em' => date('Y-m-d H:i:s'),
            'arquivado_por_id' => Auth::user()->id,
            'area_atual_id' => null,
            'estado' => 0
                ], "id = {$documento->id}");

        // Registar histórico
        DocumentoTramitacao::create([
            'documento_id' => $id,
            'area_id' => null,
            'utilizador_id' => Auth::user()->id,
            'acao' => 'ARQUIVADO',
            'estado' => 'arquivado',
            'comentario' => 'Documento arquivado.',
            'criado_em' => date('Y-m-d H:i:s')
        ]);

        Sessao::flash('sucesso', 'Documento arquivado com sucesso.');
        return $this->redirect('/admin/documentos/arquivados');
    }

    private function paginacao($total, $porPagina, $paginaAtual)
    {
        $totalPaginas = ceil($total / $porPagina);
        if ($totalPaginas <= 1)
            return '';

        // Copiar GET mas remover o "url" que o router injeta
        $params = $_GET;
        unset($params['url'], $params['page']);

        // Construir query string limpa
        $baseQuery = http_build_query($params, '', '&', PHP_QUERY_RFC3986);

        $html = '<nav><ul class="pagination">';

        for ($i = 1; $i <= $totalPaginas; $i++) {
            $active = $i == $paginaAtual ? 'active' : '';

            $query = $baseQuery ? $baseQuery . '&page=' . $i : 'page=' . $i;

            $html .= "<li class='page-item $active'>
                    <a class='page-link' href='/admin/documentos?$query'>$i</a>
                  </li>";
        }

        $html .= '</ul></nav>';

        return $html;
    }

    public function criarAjax()
    {
        ini_set('display_errors', 1);
        ini_set('display_startup_errors', 1);
        error_reporting(E_ALL);

        header('Content-Type: application/json');

        try {
            $user = Auth::user();

            // ============================
            // 1. Validar título
            // ============================
            $titulo = trim($_POST['titulo'] ?? '');
            if ($titulo === '') {
                echo json_encode(['erro' => 'O título é obrigatório.']);
                exit;
            }

            // ============================
            // 2. Validar tipo
            // ============================
            $tipo_id = $_POST['tipo_id'] ?? null;
            if (!$tipo_id) {
                echo json_encode(['erro' => 'Selecione o tipo de documento.']);
                exit;
            }

            // ============================
            // 3. Validar ficheiros
            // ============================
            if (!isset($_FILES['ficheiros']) || empty($_FILES['ficheiros']['name'][0])) {
                echo json_encode(['erro' => 'Nenhum ficheiro enviado.']);
                exit;
            }

            $ficheiros = $_FILES['ficheiros'];
            $total = count($ficheiros['name']);

            if ($total > 10) {
                echo json_encode(['erro' => 'Máximo de 10 ficheiros por envio.']);
                exit;
            }

            // ============================
            // 4. Configuração
            // ============================
            $extPermitidas = [
                'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx',
                'txt', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'zip', 'rar', '7z'
            ];

            $extPerigosas = [
                'php', 'php3', 'php4', 'php5', 'phtml',
                'exe', 'msi', 'bat', 'cmd', 'sh', 'ps1',
                'js', 'html', 'htm', 'svg', 'dll', 'sys', 'com'
            ];

            $limitesPorExt = [
                'txt' => 2 * 1024 * 1024,
                'pdf' => 20 * 1024 * 1024,
                'jpg' => 10 * 1024 * 1024,
                'jpeg' => 10 * 1024 * 1024,
                'png' => 10 * 1024 * 1024,
                'gif' => 5 * 1024 * 1024,
                'webp' => 10 * 1024 * 1024,
                'zip' => 50 * 1024 * 1024,
                'rar' => 50 * 1024 * 1024,
                '7z' => 50 * 1024 * 1024,
                'doc' => 10 * 1024 * 1024,
                'docx' => 10 * 1024 * 1024,
                'xls' => 10 * 1024 * 1024,
                'xlsx' => 10 * 1024 * 1024,
                'ppt' => 20 * 1024 * 1024,
                'pptx' => 20 * 1024 * 1024,
            ];

            $finfo = new \finfo(FILEINFO_MIME_TYPE);

            // ============================
            // 5. Criar subpasta
            // ============================
            $subpasta = date('Y/m/d/');
            $base = realpath(__DIR__ . '/../../../public/uploads/documentos');

            if ($base === false) {
                $base = __DIR__ . '/../../../public/uploads/documentos';
            }

            if (!is_dir($base)) {
                mkdir($base, 0777, true);
            }

            if ($base === false) {
                echo json_encode(['erro' => 'Diretório base não encontrado.']);
                exit;
            }

            $destinoBase = $base . DIRECTORY_SEPARATOR . $subpasta;

            if (!is_dir($destinoBase)) {
                mkdir($destinoBase, 0777, true);
            }

            // ============================
            // 6. Processar ficheiros
            // ============================
            for ($i = 0; $i < $total; $i++) {

                $nomeOriginal = trim($ficheiros['name'][$i]);
                $nomeOriginal = rtrim($nomeOriginal, ". \t\n\r\0\x0B");

                $tmp = $ficheiros['tmp_name'][$i];
                $erro = $ficheiros['error'][$i];
                $tamanho = $ficheiros['size'][$i];

                if ($erro !== UPLOAD_ERR_OK) {
                    echo json_encode(['erro' => "Erro ao enviar: {$nomeOriginal}"]);
                    exit;
                }

                if (!is_uploaded_file($tmp)) {
                    echo json_encode(['erro' => "Ficheiro inválido: {$nomeOriginal}"]);
                    exit;
                }

                $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

                if (!in_array($ext, $extPermitidas, true)) {
                    echo json_encode(['erro' => "Tipo não permitido: {$nomeOriginal}"]);
                    exit;
                }

                if ($ext === 'txt') {
                    $partes = explode('.', $nomeOriginal);
                    $extAnterior = strtolower($partes[count($partes) - 2] ?? '');

                    if (in_array($extAnterior, $extPerigosas, true)) {
                        echo json_encode(['erro' => "Tipo não permitido: {$nomeOriginal}"]);
                        exit;
                    }
                }

                $limite = $limitesPorExt[$ext] ?? (10 * 1024 * 1024);
                if ($tamanho > $limite) {
                    echo json_encode(['erro' => "{$nomeOriginal} excede o limite."]);
                    exit;
                }

                $mimeReal = $finfo->file($tmp) ?: 'application/octet-stream';

                // ============================
                // 6.1 CALCULAR HASH NO TMP
                // ============================
                $hash = hash_file('sha256', $tmp);

                // ============================
                // 6.2 VERIFICAR DUPLICADO
                // ============================
                $existe = Documento::query()
                        ->where('hash', '=', $hash)
                        ->where('criado_por', '=', $user->id)
                        ->first();

                if ($existe) {
                    echo json_encode(['erro' => "Ficheiro duplicado: {$nomeOriginal}"]);
                    exit;
                }

                // ============================
                // 6.3 MOVER E GRAVAR
                // ============================
                $nomeSeguro = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nomeOriginal);
                $nomeGuardado = uniqid('', true) . '_' . $nomeSeguro;
                $destino = $destinoBase . $nomeGuardado;

                // Criar pasta se não existir
                if (!is_dir($base)) {
                    mkdir($base, 0777, true);
                }

                // Nome seguro
                $nomeSeguro = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nomeOriginal);
                $nomeGuardado = uniqid() . '_' . $nomeSeguro;

                // Caminho final correto
                $destino = rtrim($base, '/\\') . '/' . $nomeGuardado;

                // Mover ficheiro
                if (!move_uploaded_file($tmp, $destino)) {
                    Sessao::flash('erro', "Erro ao guardar o ficheiro: {$nomeOriginal}");
                    return $this->redirect('/admin/documentos/criar');
                }


                Documento::create([
                    'titulo' => $titulo,
                    'ficheiro' => $nomeGuardado,
                    'ficheiro_original' => $nomeOriginal,
                    'mime_type' => $mimeReal,
                    'tamanho' => $tamanho,
                    'hash' => $hash,
                    'criado_por' => $user->id,
                    'caminho' => $subpasta,
                    'tipo_id' => $tipo_id
                ]);
            }

            echo json_encode(['sucesso' => true]);
            exit;
        } catch (\Throwable $e) {
            echo json_encode([
                'erro' => 'Erro interno no servidor.',
                'detalhe' => $e->getMessage(),
                'linha' => $e->getLine(),
                'ficheiro' => $e->getFile()
            ]);
            exit;
        }
    }
}
