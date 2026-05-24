<?php

namespace App\Services;

use Exception;

class RestoreService
{
    private string $mysqlUser = 'root';
    private string $mysqlPass = ''; // password vazia
    private string $mysqlDB   = 'anferaltadocs'; // nome da tua BD

    public function restaurarBD(string $path): void
    {
        if (!is_file($path)) {
            throw new Exception("Ficheiro de backup não encontrado: {$path}");
        }

        // Extrair ZIP para pasta temporária
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_db_' . uniqid();
        mkdir($tempDir);

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception("Não foi possível abrir o ficheiro ZIP.");
        }
        $zip->extractTo($tempDir);
        $zip->close();

        // Encontrar ficheiro .sql
        $sqlFile = null;
        foreach (scandir($tempDir) as $f) {
            if (str_ends_with($f, '.sql')) {
                $sqlFile = $tempDir . DIRECTORY_SEPARATOR . $f;
                break;
            }
        }

        if (!$sqlFile) {
            throw new Exception("Nenhum ficheiro .sql encontrado dentro do backup.");
        }

        // Encontrar mysql.exe do WAMP
        $mysqlPath = $this->detetarMysql();
        if (!$mysqlPath) {
            throw new Exception("mysql.exe não encontrado no WAMP.");
        }

        // Construir comando MySQL corretamente
        $pass = $this->mysqlPass === '' ? '' : '-p"' . $this->mysqlPass . '"';

        $cmd = sprintf(
            '"%s" -u%s %s %s < "%s"',
            $mysqlPath,
            $this->mysqlUser,
            $pass,
            $this->mysqlDB,
            $sqlFile
        );

        exec($cmd, $out, $ret);

        if ($ret !== 0) {
            throw new Exception("Erro ao restaurar a base de dados.");
        }
    }

    public function restaurarFiles(string $path): void
    {
        if (!is_file($path)) {
            throw new Exception("Backup de ficheiros não encontrado: {$path}");
        }

        $destino = realpath(__DIR__ . '/../../public/uploads_publicos');

        if (!$destino) {
            throw new Exception("Diretório de destino não encontrado: uploads_publicos");
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception("Não foi possível abrir o ficheiro ZIP.");
        }

        $zip->extractTo($destino);
        $zip->close();
    }

    /**
     * DETETAR mysql.exe automaticamente (WAMP, WAMP64, XAMPP, Linux)
     */
    private function detetarMysql(): ?string
    {
        // 1) .env
        $envPath = $_ENV['MYSQL_PATH'] ?? null;
        if ($envPath && file_exists($envPath)) {
            return str_replace('\\', '/', $envPath);
        }

        // 2) Caminhos típicos do WAMP
        $possiveis = [
            'C:\\wamp\\bin\\mysql\\mysql9.1.0\\bin\\mysql.exe', // O TEU CAMINHO REAL
            'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysql.exe',
            'C:\\wamp\\bin\\mysql\\mysql8.0.31\\bin\\mysql.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysql.exe',
            'C:\\xampp\\mysql\\bin\\mysql.exe',
        ];

        foreach ($possiveis as $p) {
            if (file_exists($p)) {
                return str_replace('\\', '/', $p);
            }
        }

        // 3) Linux
        $which = trim(shell_exec('which mysql 2>/dev/null') ?? '');
        if ($which !== '' && file_exists($which)) {
            return $which;
        }

        return null;
    }
}
