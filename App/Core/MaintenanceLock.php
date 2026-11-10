<?php
namespace App\Core;

final class MaintenanceLock
{
    private $handle;

    public function __construct(string $storage, bool $exclusive)
    {
        if (is_file($storage . '/.recovery-incomplete') || is_file($storage . '/.installation-incomplete')) {
            throw new \RuntimeException('Instalação ou recuperação incompleta: aplicação em manutenção.');
        }
        $this->handle = fopen($storage . '/.operations.lock', 'c');
        if (!$this->handle || !flock($this->handle, ($exclusive ? LOCK_EX : LOCK_SH) | LOCK_NB)) {
            throw new \RuntimeException('Instalação em manutenção ou com operações em curso.');
        }
    }

    public function __destruct()
    {
        if (is_resource($this->handle)) {
            flock($this->handle, LOCK_UN);
            fclose($this->handle);
        }
    }
}
