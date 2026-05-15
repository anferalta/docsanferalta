<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Models\DocumentoTipo;
use App\Models\Documento;
use App\Core\Auth;
use App\Core\Sessao;
use App\Core\Conexao;
use App\Models\Utilizador;

class DocumentosAdminController extends BaseController
{

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

        // ============================
        // 1. Filtros
        // ============================
        $tipo_id = $_GET['tipo_id'] ?? null;
        $utilizador_id = $_GET['utilizador'] ?? null;
        $data_inicio = $_GET['data_inicio'] ?? null;
        $data_fim = $_GET['data_fim'] ?? null;
        $pesquisa = $_GET['q'] ?? null;
        $estado_atual = $_GET['estado_atual'] ?? null;
        $area_atual_id = $_GET['area_atual_id'] ?? null;

        // Ordenação
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
        LEFT JOIN documento_tipos t ON t.id = d.tipo_id
        LEFT JOIN utilizadores u ON u.id = d.criado_por
        LEFT JOIN documento_areas a ON a.id = d.area_atual_id
        WHERE 1=1
    ";

        // ============================
        // 3. ACL
        // ============================
        if (!$acl->has('admin.documentos.ver_todos')) {
            $sql .= " AND d.criado_por = " . intval($user->id);
        }

        // ============================
        // 4. Filtros
        // ============================
        if ($tipo_id) {
            $sql .= " AND d.tipo_id = " . intval($tipo_id);
        }

        if ($utilizador_id && $acl->has('admin.documentos.ver_todos')) {
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
                    'tramitacao' => $historico, //  ✔ CORRETO
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

        // MIME types corretos para Chrome
        $mime = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'txt' => 'text/plain',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation'
        ];

        header("Content-Type: " . ($mime[$ext] ?? 'application/octet-stream'));
        header("Content-Disposition: inline; filename=\"$ficheiro\"");
        header("Content-Length: " . filesize($caminho));

        readfile($caminho);
        exit;
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
