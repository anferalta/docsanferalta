<?php
namespace App\Core;

/** Atomic fixed-window limiter for a single-server installation. */
final class LoginLimiter
{
    public function __construct(private string $directory)
    {
        if (!is_dir($directory) && !mkdir($directory, 0770, true) && !is_dir($directory)) {
            throw new \RuntimeException('Não foi possível iniciar a proteção do login.');
        }
    }

    public function consume(string $key, int $limit, int $window = 900): bool
    {
        $file = $this->directory . '/' . hash('sha256', $key) . '.json';
        $handle = fopen($file, 'c+');
        if (!$handle || !flock($handle, LOCK_EX)) {
            throw new \RuntimeException('Proteção do login indisponível.');
        }
        try {
            $data = json_decode(stream_get_contents($handle), true);
            $now = time();
            if (!is_array($data) || ($data['until'] ?? 0) <= $now) {
                $data = ['until' => $now + $window, 'count' => 0];
            }
            $allowed = $data['count'] < $limit;
            $data['count'] = min($limit, $data['count'] + 1);
            rewind($handle);
            if (!ftruncate($handle, 0) || fwrite($handle, json_encode($data, JSON_THROW_ON_ERROR)) === false || !fflush($handle)) {
                throw new \RuntimeException('Não foi possível atualizar a proteção do login.');
            }
            return $allowed;
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
