<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Conexao;

class Permissao extends Model {

    protected string $table = 'permissoes';
    protected string $primaryKey = 'id';
    public ?int $id = null;
    public ?string $codigo = null;
    public ?string $descricao = null;
    protected array $permitidos = [
        'codigo',
        'descricao'
    ];

    /**
     * Listar ordenado
     */
    public static function allOrdered(): array {
        $permissoes = static::query()
                ->orderBy('codigo', 'ASC')
                ->get();

        $grupos = [];

        foreach ($permissoes as $p) {

            // admin.backups.bd.ver → backups.bd
            // admin.documentos.ver → documentos
            // admin.documento-tipos.ver → documento-tipos
            $partes = explode('.', $p->codigo);

            // remover o "admin"
            array_shift($partes);

            // remover a ação final (ver, criar, editar, apagar...)
            array_pop($partes);

            // reconstruir o grupo
            $grupo = implode('.', $partes);

            // nome amigável
            $grupoNome = ucfirst(str_replace('-', ' ', $grupo));

            if (!isset($grupos[$grupoNome])) {
                $grupos[$grupoNome] = [];
            }

            $grupos[$grupoNome][] = [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nome' => $p->descricao
            ];
        }

        ksort($grupos); // ordenar alfabeticamente

        return $grupos;
    }

    /**
     * Pesquisar por texto
     */
    public static function pesquisar(string $texto): array {
        $db = Conexao::getInstancia();

        if ($texto === '') {
            return $db->query("SELECT * FROM permissoes ORDER BY codigo")
                            ->fetchAll(\PDO::FETCH_CLASS, self::class);
        }

        $stmt = $db->prepare("
            SELECT * FROM permissoes
            WHERE codigo LIKE :t OR descricao LIKE :t
            ORDER BY codigo
        ");
        $stmt->execute(['t' => "%$texto%"]);

        return $stmt->fetchAll(\PDO::FETCH_CLASS, self::class);
    }

    /**
     * Verificar se existe permissão com este código
     */
    public static function existeCodigo(string $codigo): bool {
        $stmt = Conexao::getInstancia()->prepare("
            SELECT COUNT(*) FROM permissoes WHERE codigo = :c
        ");
        $stmt->execute(['c' => $codigo]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Verificar se existe permissão com este código, exceto um ID
     */
    public static function existeCodigoEmOutro(string $codigo, int $id): bool {
        $stmt = Conexao::getInstancia()->prepare("
            SELECT COUNT(*) FROM permissoes 
            WHERE codigo = :c AND id != :id
        ");
        $stmt->execute([
            'c' => $codigo,
            'id' => $id
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * Buscar permissão por código
     */
    public static function findByCodigo(string $codigo): ?self {
        $stmt = Conexao::getInstancia()->prepare("
            SELECT * FROM permissoes WHERE codigo = :c LIMIT 1
        ");
        $stmt->execute(['c' => $codigo]);

        $stmt->setFetchMode(\PDO::FETCH_CLASS, self::class);
        return $stmt->fetch() ?: null;
    }

    /**
     * Apagar permissão + ligações
     */
    public function delete($id): bool {
        $db = Conexao::getInstancia();

        // remover ligações a perfis
        $db->prepare("DELETE FROM perfis_permissoes WHERE permissao_id = ?")
                ->execute([$id]);

        // apagar permissão
        $stmt = $db->prepare("DELETE FROM permissoes WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Paginação
     */
    public static function paginar(int $pagina, int $porPagina, string $pesquisa = ''): array {
        $db = Conexao::getInstancia();
        $offset = ($pagina - 1) * $porPagina;

        $sql = "SELECT * FROM permissoes";
        $params = [];

        if ($pesquisa !== '') {
            $sql .= " WHERE codigo LIKE :q OR descricao LIKE :q";
            $params['q'] = "%$pesquisa%";
        }

        $sql .= " ORDER BY codigo LIMIT :limit OFFSET :offset";

        $stmt = $db->prepare($sql);
        $stmt->bindValue(':limit', $porPagina, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        $stmt->execute();

        return $stmt->fetchAll(\PDO::FETCH_CLASS, self::class);
    }

    /**
     * Total com filtros
     */
    public static function total(string $pesquisa = ''): int {
        $db = Conexao::getInstancia();

        if ($pesquisa === '') {
            return (int) $db->query("SELECT COUNT(*) FROM permissoes")->fetchColumn();
        }

        $stmt = $db->prepare("
            SELECT COUNT(*) FROM permissoes 
            WHERE codigo LIKE :q OR descricao LIKE :q
        ");
        $stmt->execute(['q' => "%$pesquisa%"]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Ordenação segura
     */
    public static function ordenar(string $ordem, string $direcao): array {
        $permitidos = ['codigo', 'descricao', 'id'];
        $direcoes = ['ASC', 'DESC'];

        if (!in_array($ordem, $permitidos)) {
            $ordem = 'codigo';
        }

        if (!in_array(strtoupper($direcao), $direcoes)) {
            $direcao = 'ASC';
        }

        $sql = "SELECT * FROM permissoes ORDER BY $ordem $direcao";

        return Conexao::getInstancia()
                        ->query($sql)
                        ->fetchAll(\PDO::FETCH_CLASS, self::class);
    }

    /**
     * Sincronizar permissões com ficheiro de configuração
     */
    public static function sincronizar(): bool {
        $db = Conexao::getInstancia();
        $permissoes = require __DIR__ . '/../Config/permissoes.php';

        $db->exec("DELETE FROM permissoes");

        $sql = "INSERT INTO permissoes (codigo, descricao) VALUES (:codigo, :descricao)";
        $stmt = $db->prepare($sql);

        foreach ($permissoes as $p) {
            $stmt->execute([
                'codigo' => $p['codigo'],
                'descricao' => $p['descricao']
            ]);
        }

        return true;
    }
}
