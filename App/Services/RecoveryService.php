<?php
namespace App\Services;

use PDO;
use ZipArchive;

/** Full logical recovery to an empty database and empty private storage. */
final class RecoveryService
{
    private const DIRECTORIES = ['documentos', 'documentos_arquivados', 'tramitacao', 'uploads', 'agendamentos'];
    private const FORMAT = 'anferaltadocs-recovery-v1';

    public function __construct(private ?PDO $pdo, private string $storage, private array $legacyRoots = []) {}

    public function create(string $output, string $password): array
    {
        if ($this->pdo === null) throw new \RuntimeException('Ligação de origem necessária para criar o backup.');
        self::password($password);
        if (!is_dir($this->storage) || is_link($this->storage)) throw new \RuntimeException('Armazenamento inválido.');
        if (file_exists($output) || !is_dir(dirname($output))) throw new \RuntimeException('O destino do backup deve ser novo e o diretório deve existir.');
        $this->assertSupportedObjects();
        $tables = $this->pdo->query('SHOW FULL TABLES')->fetchAll(PDO::FETCH_NUM);
        if (!$tables) throw new \RuntimeException('A base de dados está vazia.');
        $stage = self::stage(dirname($output));
        $locked = false;
        try {
            // READ locks cover MyISAM as well as InnoDB and block external writers.
            $this->pdo->exec('SET SESSION lock_wait_timeout=30');
            $names = array_map(static fn($t) => DatabaseConnection::identifier($t[0]), $tables);
            $this->pdo->exec('LOCK TABLES ' . implode(', ', array_map(static fn($n) => $n . ' READ', $names)));
            $locked = true;
            $manifest = ['format' => self::FORMAT, 'created_at' => gmdate('c'), 'server' => $this->pdo->getAttribute(PDO::ATTR_SERVER_VERSION), 'directories' => self::DIRECTORIES, 'tables' => [], 'entries' => []];
            foreach ($tables as [$table, $type]) {
                if ($type !== 'BASE TABLE') throw new \RuntimeException('Objetos SQL não suportados.');
                $id = DatabaseConnection::identifier($table);
                $schema = $this->pdo->query('SHOW CREATE TABLE ' . $id)->fetch(PDO::FETCH_NUM)[1];
                $schemaName = 'database/' . $table . '.sql';
                self::put($stage . '/' . $schemaName, $schema . ";\n");
                $columns = [];
                foreach ($this->pdo->query('SHOW FULL COLUMNS FROM ' . $id)->fetchAll(PDO::FETCH_ASSOC) as $column) {
                    if (!preg_match('/\b(?:VIRTUAL|STORED) GENERATED\b/i', $column['Extra'])) $columns[] = $column['Field'];
                }
                $dataName = 'database/' . $table . '.jsonl';
                $handle = fopen($stage . '/' . $dataName, 'xb');
                if (!$handle) throw new \RuntimeException('Não foi possível criar o snapshot.');
                $count = 0;
                $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, false);
                try {
                    $query = $this->pdo->query('SELECT ' . implode(',', array_map([DatabaseConnection::class, 'identifier'], $columns)) . ' FROM ' . $id);
                    while ($row = $query->fetch(PDO::FETCH_NUM)) {
                        $line = json_encode(array_map(static fn($v) => $v === null ? null : base64_encode((string) $v), $row), JSON_THROW_ON_ERROR) . "\n";
                        self::write($handle, $line);
                        $count++;
                    }
                    $query->closeCursor();
                } finally {
                    $this->pdo->setAttribute(PDO::MYSQL_ATTR_USE_BUFFERED_QUERY, true);
                    fclose($handle);
                }
                $manifest['tables'][$table] = ['schema' => $schemaName, 'data' => $dataName, 'columns' => $columns, 'rows' => $count];
            }
            foreach (self::DIRECTORIES as $directory) {
                $source = $this->storage . '/' . $directory;
                if (!is_dir($source)) continue;
                if (is_link($source)) throw new \RuntimeException('Links no armazenamento não são suportados.');
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file->isLink()) throw new \RuntimeException('Links no armazenamento não são suportados.');
                    if (!$file->isFile()) continue;
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($this->storage) + 1));
                    self::safePath('storage/' . $relative);
                    $destination = $stage . '/storage/' . $relative;
                    self::directory(dirname($destination));
                    if (!copy($file->getPathname(), $destination)) throw new \RuntimeException('Falha ao copiar ficheiro do snapshot.');
                }
            }
            // Preserve historical uploads too, without restoring them into a public
            // directory. New uploads must always use the private storage tree.
            foreach ($this->legacyRoots as $label => $source) {
                self::safePath((string) $label);
                if (str_contains((string) $label, '/')) throw new \RuntimeException('Etiqueta de armazenamento inválida.');
                if (!is_dir($source)) continue;
                if (is_link($source)) throw new \RuntimeException('Links no armazenamento legado não são suportados.');
                foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($source, \FilesystemIterator::SKIP_DOTS)) as $file) {
                    if ($file->isLink()) throw new \RuntimeException('Links no armazenamento legado não são suportados.');
                    if (!$file->isFile() || $file->getFilename() === '.htaccess') continue;
                    $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($source) + 1));
                    $name = 'storage/uploads/legacy/' . $label . '/' . $relative;
                    self::safePath($name);
                    $destination = $stage . '/' . $name;
                    self::directory(dirname($destination));
                    if (is_file($destination) && !hash_equals(hash_file('sha256', $destination), hash_file('sha256', $file->getPathname()))) throw new \RuntimeException('Conflito no arquivo de uploads legados.');
                    if (!copy($file->getPathname(), $destination)) throw new \RuntimeException('Falha ao preservar upload legado.');
                }
            }
            $this->pdo->exec('UNLOCK TABLES');
            $locked = false;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if (!$file->isFile()) continue;
                $name = str_replace('\\', '/', substr($file->getPathname(), strlen($stage) + 1));
                $manifest['entries'][$name] = ['sha256' => hash_file('sha256', $file->getPathname()), 'bytes' => $file->getSize()];
            }
            self::put($stage . '/manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $partial = $stage . '/archive.partial';
            $zip = new ZipArchive();
            if ($zip->open($partial, ZipArchive::CREATE | ZipArchive::EXCL) !== true) throw new \RuntimeException('Não foi possível abrir o arquivo.');
            try {
                foreach (array_merge(array_keys($manifest['entries']), ['manifest.json']) as $name) {
                    if (!$zip->addFile($stage . '/' . $name, $name) || !$zip->setEncryptionName($name, ZipArchive::EM_AES_256, $password)) throw new \RuntimeException('Falha ao encriptar o backup.');
                }
            } catch (\Throwable $e) { $zip->close(); throw $e; }
            if (!$zip->close()) throw new \RuntimeException('Falha ao finalizar o backup.');
            // Read and hash every encrypted entry before publishing the file.
            $this->inspect($partial, $password);
            if (!rename($partial, $output)) throw new \RuntimeException('Não foi possível publicar o backup.');
            chmod($output, 0600);
            return ['path' => $output, 'tables' => count($manifest['tables']), 'entries' => count($manifest['entries']), 'sha256' => hash_file('sha256', $output), 'key_id' => substr(hash('sha256', $password), 0, 16)];
        } finally {
            if ($locked) $this->pdo->exec('UNLOCK TABLES');
            self::cleanup($stage);
        }
    }

    public function inspect(string $archive, string $password): array
    {
        $stage = self::stage(sys_get_temp_dir());
        try { return $this->extractVerified($archive, $password, $stage); }
        finally { self::cleanup($stage); }
    }

    public function restore(string $archive, string $password): array
    {
        if ($this->pdo === null) throw new \RuntimeException('Ligação de destino necessária para recuperar o backup.');
        self::password($password);
        $database = (string) $this->pdo->query('SELECT DATABASE()')->fetchColumn();
        $lockName = 'anferalta-restore-' . substr(hash('sha256', $database), 0, 32);
        $lock = $this->pdo->prepare('SELECT GET_LOCK(?,0)'); $lock->execute([$lockName]);
        if ((int) $lock->fetchColumn() !== 1) throw new \RuntimeException('Outro restauro está em execução.');
        $stage = null;
        try {
            if ($this->pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN)) throw new \RuntimeException('O destino da recuperação deve ser uma base de dados vazia.');
            if (is_link($this->storage) || (file_exists($this->storage) && !is_dir($this->storage))) throw new \RuntimeException('Destino de armazenamento inválido.');
            if (is_dir($this->storage) && count(scandir($this->storage)) !== 2) throw new \RuntimeException('O armazenamento de destino deve estar vazio.');
            self::directory(dirname($this->storage));
            $stage = self::stage(dirname($this->storage));
            // Authentication, paths, hashes, schema and row streams are checked before DB writes.
            $manifest = $this->extractVerified($archive, $password, $stage);
            foreach ($manifest['tables'] as $table => $meta) {
                $statements = MigrationService::statements(file_get_contents($stage . '/' . $meta['schema']));
                $prefix = 'CREATE TABLE ' . DatabaseConnection::identifier($table) . ' (';
                if (count($statements) !== 1 || !str_starts_with($statements[0], $prefix)
                    || preg_match('/\b(?:DATA\s+DIRECTORY|INDEX\s+DIRECTORY|TABLESPACE)\b/i', $statements[0])) throw new \RuntimeException('Estrutura SQL do backup não suportada.');
            }
            self::directory($this->storage);
            $marker = $this->storage . '/.recovery-incomplete';
            self::put($marker, 'Recovery in progress; do not serve this installation.');
            $this->pdo->exec('SET FOREIGN_KEY_CHECKS=0');
            try {
                foreach ($manifest['tables'] as $meta) $this->pdo->exec(file_get_contents($stage . '/' . $meta['schema']));
                $this->pdo->beginTransaction();
                foreach ($manifest['tables'] as $table => $meta) {
                    $columns = implode(',', array_map([DatabaseConnection::class, 'identifier'], $meta['columns']));
                    $insert = $this->pdo->prepare('INSERT INTO ' . DatabaseConnection::identifier($table) . ' (' . $columns . ') VALUES (' . implode(',', array_fill(0, count($meta['columns']), '?')) . ')');
                    $handle = fopen($stage . '/' . $meta['data'], 'rb');
                    $rows = 0;
                    try {
                        while (($line = fgets($handle)) !== false) {
                            $values = self::row($line, count($meta['columns']));
                            $insert->execute($values); $rows++;
                        }
                    } finally { fclose($handle); }
                    if ($rows !== $meta['rows']) throw new \RuntimeException('Contagem de registos incorreta.');
                }
                $this->pdo->commit();
                self::assertForeignKeys($this->pdo);
                foreach (self::DIRECTORIES as $directory) {
                    $source = $stage . '/storage/' . $directory;
                    if (is_dir($source)) {
                        if (!rename($source, $this->storage . '/' . $directory)) throw new \RuntimeException('Falha ao instalar ficheiros recuperados.');
                    } else self::directory($this->storage . '/' . $directory);
                }
                // Confirm the installed file tree too, not just the archive.
                foreach ($manifest['entries'] as $name => $meta) {
                    if (!str_starts_with($name, 'storage/')) continue;
                    $path = $this->storage . '/' . substr($name, 8);
                    if (!hash_equals($meta['sha256'], hash_file('sha256', $path))) throw new \RuntimeException('Falha na verificação final dos ficheiros.');
                }
                if (!unlink($marker)) throw new \RuntimeException('Não foi possível concluir a recuperação.');
            } catch (\Throwable $e) {
                if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                // DDL is not transactional: keep the maintenance marker on any failure.
                throw $e;
            } finally { $this->pdo->exec('SET FOREIGN_KEY_CHECKS=1'); }
            return ['tables' => count($manifest['tables']), 'entries' => count($manifest['entries']), 'verified' => true];
        } finally {
            if ($stage !== null) self::cleanup($stage);
            $unlock = $this->pdo->prepare('SELECT RELEASE_LOCK(?)'); $unlock->execute([$lockName]);
        }
    }

    private function extractVerified(string $archive, string $password, string $stage): array
    {
        self::password($password);
        $zip = new ZipArchive();
        // Some Windows libzip builds reject their own AES-encrypted empty entries
        // under CHECKCONS. Authenticate/decrypt and hash EVERY entry instead.
        $openResult = $zip->open($archive);
        if ($openResult !== true) throw new \RuntimeException('Arquivo inválido (código ZIP ' . $openResult . ').');
        try {
            $zip->setPassword($password);
            $index = $zip->locateName('manifest.json');
            if ($index === false || $zip->statIndex($index)['size'] > 16 * 1024 * 1024) throw new \RuntimeException('Manifesto inválido.');
            $raw = $zip->getFromName('manifest.json');
            if ($raw === false) throw new \RuntimeException('Password incorreta ou manifesto corrompido.');
            $manifest = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
            if (($manifest['format'] ?? '') !== self::FORMAT || ($manifest['directories'] ?? []) !== self::DIRECTORIES || empty($manifest['tables']) || !is_array($manifest['entries'] ?? null)) throw new \RuntimeException('Formato de recuperação não suportado.');
            if ($zip->numFiles !== count($manifest['entries']) + 1 || $zip->numFiles > 100000) throw new \RuntimeException('Conteúdo inesperado no arquivo.');
            $total = 0; $seen = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i); $name = $stat['name'];
                self::safePath($name);
                if (isset($seen[strtolower($name)])) throw new \RuntimeException('Caminhos duplicados no arquivo.');
                $seen[strtolower($name)] = true;
                if (($stat['encryption_method'] ?? 0) !== ZipArchive::EM_AES_256) throw new \RuntimeException('Todas as entradas devem estar encriptadas.');
                if ($name === 'manifest.json') continue;
                $meta = $manifest['entries'][$name] ?? null;
                if (!is_array($meta) || !is_int($meta['bytes'] ?? null) || $meta['bytes'] < 0 || $meta['bytes'] !== $stat['size'] || !preg_match('/\A[a-f0-9]{64}\z/', $meta['sha256'] ?? '')) throw new \RuntimeException('Metadados de integridade inválidos.');
                $total += $meta['bytes'];
                if ($total > disk_free_space($stage) - 32 * 1024 * 1024) throw new \RuntimeException('Espaço insuficiente para verificar o backup.');
                $destination = $stage . '/' . $name; self::directory(dirname($destination));
                $input = $zip->getStream($name); $output = fopen($destination, 'xb');
                if (!$input || !$output) throw new \RuntimeException('Password incorreta ou entrada ilegível.');
                try { $bytes = stream_copy_to_stream($input, $output); }
                finally { fclose($input); fclose($output); }
                if ($bytes !== $meta['bytes'] || !hash_equals($meta['sha256'], hash_file('sha256', $destination))) throw new \RuntimeException('Falha de integridade no arquivo.');
            }
            $expected = [];
            foreach ($manifest['tables'] as $table => $meta) {
                DatabaseConnection::identifier($table);
                if (($meta['schema'] ?? '') !== 'database/' . $table . '.sql' || ($meta['data'] ?? '') !== 'database/' . $table . '.jsonl'
                    || !is_array($meta['columns'] ?? null) || !$meta['columns'] || !is_int($meta['rows'] ?? null) || $meta['rows'] < 0) throw new \RuntimeException('Tabela inválida no manifesto.');
                foreach ($meta['columns'] as $column) DatabaseConnection::identifier($column);
                foreach ([$meta['schema'], $meta['data']] as $name) {
                    if (!isset($manifest['entries'][$name])) throw new \RuntimeException('Backup incompleto.');
                    $expected[$name] = true;
                }
                $rows = 0; $handle = fopen($stage . '/' . $meta['data'], 'rb');
                try { while (($line = fgets($handle)) !== false) { self::row($line, count($meta['columns'])); $rows++; } }
                finally { fclose($handle); }
                if ($rows !== $meta['rows']) throw new \RuntimeException('Snapshot de dados incompleto.');
            }
            foreach ($manifest['entries'] as $name => $meta) {
                if (!isset($expected[$name]) && !preg_match('~\Astorage/(documentos|documentos_arquivados|tramitacao|uploads|agendamentos)/~', $name)) throw new \RuntimeException('Entrada fora do conjunto de recuperação.');
            }
            return $manifest;
        } finally { $zip->close(); }
    }

    private function assertSupportedObjects(): void
    {
        foreach (['TRIGGERS' => 'TRIGGER_SCHEMA', 'ROUTINES' => 'ROUTINE_SCHEMA', 'EVENTS' => 'EVENT_SCHEMA', 'VIEWS' => 'TABLE_SCHEMA'] as $table => $column) {
            if ((int) $this->pdo->query("SELECT COUNT(*) FROM information_schema.{$table} WHERE {$column}=DATABASE()")->fetchColumn() !== 0) throw new \RuntimeException('O backup requer suporte adicional para views, triggers, eventos ou rotinas.');
        }
    }

    private static function assertForeignKeys(PDO $pdo): void
    {
        $rows = $pdo->query('SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA=DATABASE() AND REFERENCED_TABLE_NAME IS NOT NULL ORDER BY TABLE_NAME, CONSTRAINT_NAME, ORDINAL_POSITION')->fetchAll(PDO::FETCH_ASSOC);
        $groups = [];
        foreach ($rows as $row) $groups[$row['TABLE_NAME'] . ':' . $row['CONSTRAINT_NAME']][] = $row;
        foreach ($groups as $columns) {
            $notNull = []; $join = [];
            foreach ($columns as $c) { $child = 'c.' . DatabaseConnection::identifier($c['COLUMN_NAME']); $notNull[] = $child . ' IS NOT NULL'; $join[] = 'p.' . DatabaseConnection::identifier($c['REFERENCED_COLUMN_NAME']) . '=' . $child; }
            $sql = 'SELECT COUNT(*) FROM ' . DatabaseConnection::identifier($columns[0]['TABLE_NAME']) . ' c WHERE ' . implode(' AND ', $notNull) . ' AND NOT EXISTS (SELECT 1 FROM ' . DatabaseConnection::identifier($columns[0]['REFERENCED_TABLE_NAME']) . ' p WHERE ' . implode(' AND ', $join) . ')';
            if ((int) $pdo->query($sql)->fetchColumn() !== 0) throw new \RuntimeException('O backup contém referências órfãs.');
        }
    }

    private static function row(string $line, int $count): array
    {
        $values = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($values) || !array_is_list($values) || count($values) !== $count) throw new \RuntimeException('Linha de dados inválida.');
        return array_map(static function ($value) { if ($value === null) return null; if (!is_string($value) || ($decoded = base64_decode($value, true)) === false) throw new \RuntimeException('Campo inválido no snapshot.'); return $decoded; }, $values);
    }

    private static function password(string $password): void
    {
        if (strlen($password) < 32 || !ZipArchive::isEncryptionMethodSupported(ZipArchive::EM_AES_256)) throw new \RuntimeException('É necessária uma chave de backup com pelo menos 32 caracteres e suporte ZIP AES-256.');
    }

    private static function safePath(string $name): void
    {
        if ($name === '' || str_contains($name, '\\') || str_contains($name, ':') || preg_match('/[\x00-\x1f]/', $name) || str_starts_with($name, '/')) throw new \RuntimeException('Caminho inseguro no arquivo.');
        foreach (explode('/', $name) as $part) if (in_array($part, ['', '.', '..'], true) || preg_match('/[. ]$/', $part) || preg_match('/\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|$)/i', $part)) throw new \RuntimeException('Caminho inseguro no arquivo.');
    }

    private static function stage(string $parent): string
    {
        $stage = rtrim(str_replace('\\', '/', $parent), '/') . '/recovery-stage-' . bin2hex(random_bytes(12));
        if (!mkdir($stage, 0700)) throw new \RuntimeException('Não foi possível criar a área privada temporária.');
        return $stage;
    }

    private static function cleanup(string $stage): void
    {
        if (!preg_match('/\Arecovery-stage-[a-f0-9]{24}\z/', basename($stage)) || is_link($stage)) throw new \RuntimeException('Área temporária inválida.');
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($stage, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $item) {
            $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($stage);
    }

    private static function directory(string $path): void
    {
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) throw new \RuntimeException('Falha ao criar diretório privado.');
    }

    private static function put(string $path, string $content): void
    {
        self::directory(dirname($path));
        if (file_put_contents($path, $content, LOCK_EX) !== strlen($content)) throw new \RuntimeException('Falha ao escrever snapshot.');
    }

    private static function write($handle, string $content): void
    {
        $offset = 0;
        while ($offset < strlen($content)) { $written = fwrite($handle, substr($content, $offset)); if ($written === false || $written === 0) throw new \RuntimeException('Falha ao escrever dados.'); $offset += $written; }
    }
}
