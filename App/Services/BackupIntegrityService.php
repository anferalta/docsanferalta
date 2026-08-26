<?php

namespace App\Services;

use ZipArchive;

class BackupIntegrityService
{
    public function analisar(string $ficheiro, string $tipo): array
    {
        $path = $ficheiro;

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
            $path = $this->desencriptarTemporario($path);

            if (!$path) {
                $detalhes['password_ok'] = false;
                return $detalhes;
            }
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
        $bytes = filesize($path);
        return round($bytes / 1024 / 1024, 2) . ' MB';
    }

    private function permissoes(string $path): bool
    {
        return is_readable($path);
    }

    private function desencriptarTemporario(string $path): ?string
    {
        $password = getenv('BACKUP_PASSWORD');

        if (!$password) {
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
            return null;
        }

        $tmp = sys_get_temp_dir() . '/backup_tmp_' . uniqid() . '.zip';
        file_put_contents($tmp, $conteudo);

        return $tmp;
    }

    private function validarSQL(string $zipPath): bool
    {
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
        if (strlen($sql) < 100) return false; // SQL demasiado pequeno
        if (!str_contains($sql, 'CREATE TABLE')) return false;

        return true;
    }
}
