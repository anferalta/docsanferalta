<?php
namespace App\Services;

use PDO;

final class MigrationService
{
    public function __construct(private PDO $pdo, private string $directory) {}

    public function status(): array
    {
        $files = glob($this->directory . '/*.sql');
        sort($files, SORT_STRING);
        $hasLedger = in_array('schema_migrations', $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN), true);
        $applied = $hasLedger ? $this->pdo->query('SELECT version, checksum, state FROM schema_migrations')->fetchAll(PDO::FETCH_UNIQUE | PDO::FETCH_ASSOC) : [];
        $result = [];
        foreach ($files as $file) {
            $version = basename($file);
            $checksum = hash_file('sha256', $file);
            $old = $applied[$version] ?? null;
            if ($old && !hash_equals($old['checksum'], $checksum)) throw new \RuntimeException('Migração já registada foi alterada: ' . $version);
            if ($old && $old['state'] !== 'applied') throw new \RuntimeException('Migração interrompida: ' . $version . '. Recuperar o backup antes de prosseguir.');
            $result[] = ['version' => $version, 'checksum' => $checksum, 'applied' => $old !== null, 'file' => $file];
        }
        foreach (array_keys($applied) as $version) {
            if (!in_array($version, array_column($result, 'version'), true)) throw new \RuntimeException('Versão de base de dados não suportada: ' . $version);
        }
        return $result;
    }

    public function migrate(?callable $backupExisting = null): array
    {
        $name = 'anferalta-migrations-' . substr(hash('sha256', (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn()), 0, 32);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?, 0)');
        $lock->execute([$name]);
        if ((int) $lock->fetchColumn() !== 1) throw new \RuntimeException('Outra migração está em execução.');
        try {
            $pending = array_values(array_filter($this->status(), static fn($m) => !$m['applied']));
            if (!$pending) return [];
            $tables = $this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
            if (array_diff($tables, ['schema_migrations'])) {
                if ($backupExisting === null) throw new \RuntimeException('É obrigatório criar um backup completo antes de migrar uma instalação existente.');
                $backupExisting();
            }
            $this->pdo->exec("CREATE TABLE IF NOT EXISTS schema_migrations (version VARCHAR(190) PRIMARY KEY, checksum CHAR(64) NOT NULL, state VARCHAR(20) NOT NULL, applied_at DATETIME NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            $insert = $this->pdo->prepare("INSERT INTO schema_migrations(version,checksum,state) VALUES (?,?,'applying')");
            $done = $this->pdo->prepare("UPDATE schema_migrations SET state='applied', applied_at=NOW() WHERE version=?");
            foreach ($pending as $migration) {
                $insert->execute([$migration['version'], $migration['checksum']]);
                // MySQL DDL commits implicitly; a dirty ledger must remain visible on failure.
                foreach (self::statements(file_get_contents($migration['file'])) as $sql) $this->pdo->exec($sql);
                $done->execute([$migration['version']]);
            }
            return array_column($pending, 'version');
        } finally {
            $unlock = $this->pdo->prepare('SELECT RELEASE_LOCK(?)');
            $unlock->execute([$name]);
        }
    }

    public static function statements(string $sql): array
    {
        $result = []; $buffer = ''; $quote = null; $comment = null;
        for ($i = 0, $length = strlen($sql); $i < $length; $i++) {
            $c = $sql[$i]; $next = $sql[$i + 1] ?? '';
            if ($comment === 'line') { if ($c === "\n") { $comment = null; $buffer .= "\n"; } continue; }
            if ($comment === 'block') { if ($c === '*' && $next === '/') { $comment = null; $i++; $buffer .= ' '; } continue; }
            if ($quote !== null) {
                $buffer .= $c;
                if ($c === '\\' && $quote !== '`' && $next !== '') { $buffer .= $next; $i++; }
                elseif ($c === $quote) { if ($next === $quote) { $buffer .= $next; $i++; } else $quote = null; }
                continue;
            }
            if (($c === '-' && $next === '-' && ctype_space($sql[$i + 2] ?? ' ')) || $c === '#') { $comment = 'line'; continue; }
            if ($c === '/' && $next === '*') { $comment = 'block'; $i++; continue; }
            if (in_array($c, ["'", '"', '`'], true)) { $quote = $c; $buffer .= $c; continue; }
            if ($c === ';') { if (trim($buffer) !== '') $result[] = trim($buffer); $buffer = ''; }
            else $buffer .= $c;
        }
        if ($quote !== null || $comment === 'block') throw new \RuntimeException('SQL incompleto.');
        if (trim($buffer) !== '') $result[] = trim($buffer);
        return $result;
    }
}
