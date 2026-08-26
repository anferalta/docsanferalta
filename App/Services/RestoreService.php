<?php

namespace App\Services;

use Exception;
use ZipArchive;

class RestoreService
{
    private string $mysqlUser = 'root';
    private string $mysqlPass = '';
    private string $mysqlDB   = 'anferaltadocs';

    /**
     * Restaurar Base de Dados
     */
    public function restaurarBD(string $path): void
    {
        if (!is_file($path)) {
            throw new Exception("Ficheiro de backup não encontrado: {$path}");
        }

        // Suporte a ZIP encriptado (.zip.enc)
        if (str_ends_with($path, '.enc')) {
            $path = $this->desencriptar($path);
        }

        // Validar ZIP
        if (!$this->validarZip($path)) {
            throw new Exception("Backup inválido — ZIP corrompido.");
        }

        // Pasta temporária
        $tempDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'restore_db_' . uniqid();
        mkdir($tempDir);

        // Extrair ZIP
        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception("Não foi possível abrir o ficheiro ZIP.");
        }
        $zip->extractTo($tempDir);
        $zip->close();

        // Encontrar ficheiros .sql
        $sqlFiles = glob($tempDir . '/*.sql');
        if (empty($sqlFiles)) {
            throw new Exception("Nenhum ficheiro .sql encontrado dentro do backup.");
        }

        // Detetar mysql.exe
        $mysqlPath = $this->detetarMysql();
        if (!$mysqlPath) {
            throw new Exception("mysql.exe não encontrado no sistema.");
        }

        // Restaurar cada ficheiro SQL
        foreach ($sqlFiles as $sqlFile) {

            if (!$this->validarSQL($sqlFile)) {
                throw new Exception("Ficheiro SQL inválido ou corrompido: {$sqlFile}");
            }

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
                throw new Exception("Erro ao restaurar SQL: {$sqlFile}");
            }
        }

        // Limpar pasta temporária
        $this->limparTemp($tempDir);
    }

    /**
     * Restaurar ficheiros
     */
    public function restaurarFiles(string $path): void
    {
        if (!is_file($path)) {
            throw new Exception("Backup de ficheiros não encontrado: {$path}");
        }

        $destino = realpath(__DIR__ . '/../../public/uploads_publicos');

        if (!$destino) {
            throw new Exception("Diretório de destino não encontrado: uploads_publicos");
        }

        // Validar ZIP
        if (!$this->validarZip($path)) {
            throw new Exception("Backup inválido — ZIP corrompido.");
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception("Não foi possível abrir o ficheiro ZIP.");
        }

        $zip->extractTo($destino);
        $zip->close();
    }

    /**
     * Desencriptar ZIP (.enc)
     */
    private function desencriptar(string $encPath): string
    {
        $password = $_ENV['BACKUP_PASSWORD'] ?? null;
        if (!$password) {
            throw new Exception("Backup encriptado mas BACKUP_PASSWORD não está definido.");
        }

        $zipPath = str_replace('.enc', '', $encPath);

        $cmd = "openssl enc -aes-256-cbc -d -in \"$encPath\" -out \"$zipPath\" -k \"$password\"";
        exec($cmd, $out, $ret);

        if ($ret !== 0 || !file_exists($zipPath)) {
            throw new Exception("Falha ao desencriptar o backup.");
        }

        return $zipPath;
    }

    /**
     * Validar ZIP
     */
    private function validarZip(string $ficheiro): bool
    {
        if (!file_exists($ficheiro) || filesize($ficheiro) < 1024) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($ficheiro) !== true) {
            return false;
        }

        $ok = $zip->numFiles > 0;
        $zip->close();

        return $ok;
    }

    /**
     * Validar SQL
     */
    private function validarSQL(string $ficheiro): bool
    {
        if (!file_exists($ficheiro) || filesize($ficheiro) < 1024) {
            return false;
        }

        $conteudo = file_get_contents($ficheiro);

        return (
            str_contains($conteudo, 'CREATE TABLE') ||
            str_contains($conteudo, 'INSERT INTO')
        );
    }

    /**
     * Limpar pasta temporária
     */
    private function limparTemp(string $dir): void
    {
        foreach (glob($dir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($dir);
    }

    /**
     * DETETAR mysql.exe automaticamente
     */
    private function detetarMysql(): ?string
    {
        // 1) .env
        $envPath = $_ENV['MYSQL_PATH'] ?? null;
        if ($envPath && file_exists($envPath)) {
            return str_replace('\\', '/', $envPath);
        }

        // 2) Caminhos típicos
        $possiveis = [
            'C:\\wamp\\bin\\mysql\\mysql9.1.0\\bin\\mysql.exe',
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
