<?php

namespace App\Services;

use ZipArchive;
use App\Services\PathGuardService;
use App\Services\BackupLogger;

class BackupIntegrityService
{
    public function analisar(string $ficheiro, string $tipo): array
    {
        PathGuardService::init();
        PathGuardService::proteger($ficheiro);

        $path = $ficheiro;

        if (!is_file($path)) {
            throw new \Exception("Backup não encontrado: {$path}");
        }

        $detalhes = [
            'tamanho'        => $this->tamanho($path),
            'data'           => date('Y-m-d H:i:s', filemtime($path)),
            'encriptado'     => str_ends_with($path, '.enc'),
            'password_ok'    => true,
            'zip_ok'         => false,
            'sql_ok'         => true,
            'permissoes_ok'  => $this->permissoes($path),
            'ficheiros'      => []
        ];

        // ------------------ DESENCRIPTAR SE NECESSÁRIO ------------------
        if ($detalhes['encriptado']) {
            $pathTmp = $this->desencriptarTemporario($path);

            if (!$pathTmp) {
                $detalhes['password_ok'] = false;
                return $detalhes;
            }

            PathGuardService::proteger($pathTmp);
            $path = $pathTmp;
        }

        // ------------------ VALIDAR ZIP ------------------
        $zip = new ZipArchive();
        if ($zip->open($path) === true) {
            $detalhes['zip_ok'] = true;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $detalhes['ficheiros'][] = $zip->getNameIndex($i);
            }

            $zip->close();
        }

        // ------------------ VALIDAR SQL (apenas BD) ------------------
        if ($tipo === 'bd') {
            $detalhes['sql_ok'] = $this->validarSQL($path);
        }

        return $detalhes;
    }

    private function tamanho(string $path): string
    {
        PathGuardService::proteger($path);

        $bytes = filesize($path);
        return round($bytes / 1024 / 1024, 2) . ' MB';
    }

    private function permissoes(string $path): bool
    {
        PathGuardService::proteger($path);
        return is_readable($path);
    }

    private function desencriptarTemporario(string $path): ?string
    {
        PathGuardService::proteger($path);

        $password = getenv('BACKUP_PASSWORD');

        if (!$password) {
            BackupLogger::registar('BACKUP_INTEGRITY', $path, false, "BACKUP_PASSWORD não definido");
            return null;
        }

        $conteudo = openssl_decrypt(
            file_get_contents($path),
            'AES-256-CBC',
            $password,
            0,
            substr($password, 0, 16)
        );

        if (!$conteudo) {
            BackupLogger::registar('BACKUP_INTEGRITY', $path, false, "Falha ao desencriptar backup");
            return null;
        }

        $tmp = sys_get_temp_dir() . '/backup_tmp_' . uniqid() . '.zip';
        PathGuardService::proteger($tmp);

        file_put_contents($tmp, $conteudo);

        return $tmp;
    }

    private function validarSQL(string $zipPath): bool
    {
        PathGuardService::proteger($zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return false;
        }

        $sql = null;

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $nome = $zip->getNameIndex($i);

            if (str_ends_with($nome, '.sql')) {
                $sql = $zip->getFromName($nome);
                break;
            }
        }

        $zip->close();

        if (!$sql) return false;
        if (strlen($sql) < 100) return false;
        if (!str_contains($sql, 'CREATE TABLE')) return false;

        return true;
    }
}
