<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Services\DatabaseBackupService;
use App\Services\FileBackupService;
use App\Services\RestoreService;
use App\Services\BackupLogger;
use App\Core\Sessao;

class BackupsAdminController extends BaseController
{

    private string $dirBD;
    private string $dirFiles;

    public function __construct()
    {
        parent::__construct();

        $this->dirBD = realpath(__DIR__ . '/../../../backups/BaseDados');
        $this->dirFiles = realpath(__DIR__ . '/../../../backups/Ficheiros');

        if (!$this->dirBD) {
            $this->dirBD = __DIR__ . '/../../../backups/BaseDados';
        }
        if (!$this->dirFiles) {
            $this->dirFiles = __DIR__ . '/../../../backups/Ficheiros';
        }

        $this->dirBD = rtrim(str_replace('\\', '/', $this->dirBD), '/') . '/';
        $this->dirFiles = rtrim(str_replace('\\', '/', $this->dirFiles), '/') . '/';
    }

    public function index()
    {
        $this->authorizeAny([
            'admin.backups.bd.ver',
            'admin.backups.files.ver',
            'admin.backups.agendamentos.ver'
        ]);

        $backupsDB = $this->listar($this->dirBD);
        $backupsFiles = $this->listar($this->dirFiles);

        $ultimoBD = $backupsDB[0]['data'] ?? null;
        $ultimoFiles = $backupsFiles[0]['data'] ?? null;

        $dashboard = [
            'totalBD' => count($backupsDB),
            'totalFiles' => count($backupsFiles),
            'ultimoBD' => $ultimoBD,
            'ultimoFiles' => $ultimoFiles,
            'estadoBD' => $this->avaliarEstadoBackup($ultimoBD),
            'estadoFiles' => $this->avaliarEstadoBackup($ultimoFiles),
        ];

        $logs = \App\Models\LogSistema::ultimos(50);

        $agendamentosService = new \App\Services\AgendamentosService();
        $cron = $agendamentosService->listar();

        return $this->render('admin/backups/index.twig', [
                    'backupsDB' => $backupsDB,
                    'backupsFiles' => $backupsFiles,
                    'dashboard' => $dashboard,
                    'logs' => $logs,
                    'cron' => $cron,
        ]);
    }

    public function criarBD()
    {
        $this->authorize('admin.backups.bd.criar');

        try {
            $ficheiro = (new DatabaseBackupService())->criar();
            BackupLogger::registar('BD', $ficheiro, true);
            Sessao::flash('sucesso', 'Backup da base de dados criado.');
        } catch (\Exception $e) {
            BackupLogger::registar('BD', '', false, $e->getMessage());
            Sessao::flash('erro', 'Erro ao criar backup: ' . $e->getMessage());
        }

        return $this->redirect('/admin/backups');
    }

    public function criarFiles()
    {
        $this->authorize('admin.backups.files.criar');

        try {
            $ficheiro = (new FileBackupService())->criar();
            BackupLogger::registar('FILES', $ficheiro, true);
            Sessao::flash('sucesso', 'Backup dos ficheiros criado com sucesso.');
        } catch (\Exception $e) {
            BackupLogger::registar('FILES', '', false, $e->getMessage());
            Sessao::flash('erro', 'Erro ao criar backup: ' . $e->getMessage());
        }

        return $this->redirect('/admin/backups');
    }

    public function download(string $ficheiro)
    {
        $this->authorizeAny([
            'admin.backups.bd.download',
            'admin.backups.files.download'
        ]);

        $ficheiro = $this->decodePath($ficheiro);
        $path = $this->resolverCaminho($ficheiro);

        if (!$path) {
            Sessao::flash('erro', 'Ficheiro não encontrado.');
            return $this->redirect('/admin/backups');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }

    public function restaurarConfirmar(string $ficheiro)
    {
        $this->authorize('admin.backups.bd.restaurar.confirmar');

        $ficheiro = $this->decodePath($ficheiro);

        return $this->render('admin/backups/restaurar_confirmar.twig', [
                    'ficheiro' => $ficheiro,
        ]);
    }

    public function restaurarExecutar(string $ficheiro)
    {
        $this->authorize('admin.backups.bd.restaurar.executar');

        $ficheiro = $this->decodePath($ficheiro);
        $path = $this->resolverCaminho($ficheiro);

        if (!$path) {
            Sessao::flash('erro', 'Ficheiro não encontrado.');
            return $this->redirect('/admin/backups');
        }

        try {
            (new RestoreService())->restaurarBD($path);
            Sessao::flash('sucesso', 'Base de dados restaurada com sucesso.');
        } catch (\Exception $e) {
            Sessao::flash('erro', 'Erro ao restaurar: ' . $e->getMessage());
        }

        return $this->redirect('/admin/backups');
    }

    public function delete(string $ficheiro)
    {
        $this->authorizeAny([
            'admin.backups.bd.apagar',
            'admin.backups.files.apagar'
        ]);

        $ficheiro = $this->decodePath($ficheiro);
        $path = $this->resolverCaminho($ficheiro);

        if (!$path) {
            Sessao::flash('erro', 'Ficheiro não encontrado.');
            return $this->redirect('/admin/backups');
        }

        unlink($path);

        Sessao::flash('sucesso', 'Backup eliminado.');
        return $this->redirect('/admin/backups');
    }

    private function getWampServices(): array
    {
        $services = [];

        exec('sc query state= all', $output);

        foreach ($output as $line) {
            if (stripos($line, 'wampapache') !== false) {
                $services['apache'] = trim(explode(':', $line)[1]);
            }
            if (stripos($line, 'wampmysqld') !== false) {
                $services['mysql'] = trim(explode(':', $line)[1]);
            }
        }

        return $services;
    }

    private function detectarServicoApache(): ?string
    {
        exec('sc query state= all', $output);

        foreach ($output as $line) {
            if (stripos($line, 'apache') !== false) {
                return trim(explode(':', $line)[1]);
            }
        }

        return null;
    }

    private function restartService(string $service): bool
    {
        exec("net stop {$service}", $outStop, $codeStop);
        sleep(1);
        exec("net start {$service}", $outStart, $codeStart);

        return $codeStart === 0;
    }

    private function apacheEstaAtivo(): bool
    {
        exec('sc query type= service state= all', $output);

        foreach ($output as $line) {
            if (stripos($line, 'apache') !== false && stripos($line, 'RUNNING') !== false) {
                return true;
            }
        }

        return false;
    }

    private function garantirApacheAtivo(string $service)
    {
        if ($this->apacheEstaAtivo()) {
            return;
        }

        // Tentar arrancar novamente
        exec("net start {$service}");
        sleep(1);

        if ($this->apacheEstaAtivo()) {
            return;
        }

        // Fallback final: reiniciar WAMP
        exec('"C:\wamp64\wampmanager.exe" -restart');
    }

    public function restaurarEReiniciar(string $ficheiro)
    {
        $this->authorize('admin.backups.bd.restaurar.executar');

        // Corrigir caminho
        $ficheiro = $this->decodePath($ficheiro);
        $path = $this->resolverCaminho($ficheiro);

        if (!$path) {
            Sessao::flash('erro', 'Ficheiro não encontrado.');
            return $this->redirect('/admin/backups');
        }

        try {
            // Restaurar BD
            (new RestoreService())->restaurarBD($path);

            // Detetar serviço Apache
            $apache = $this->detectarServicoApache();

            if ($apache) {
                // Reiniciar Apache
                $ok = $this->restartService($apache);

                // Garantir que arrancou
                $this->garantirApacheAtivo($apache);

                if (!$ok) {
                    Sessao::flash('erro', 'BD restaurada, mas o Apache não arrancou automaticamente.');
                    return $this->redirect('/admin/backups');
                }
            }

            Sessao::flash('sucesso', 'Base de dados restaurada e Apache reiniciado com sucesso.');
        } catch (\Exception $e) {
            Sessao::flash('erro', 'Erro: ' . $e->getMessage());
        }

        return $this->redirect('/admin/backups');
    }

    private function listar(string $baseDir): array
    {
        $lista = [];

        if (!is_dir($baseDir)) {
            return [];
        }

        $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $ficheiro) {
            if ($ficheiro->isFile() && $ficheiro->getExtension() === 'zip') {

                $rel = str_replace('\\', '/', $ficheiro->getPathname());
                $rel = str_replace($baseDir, '', $rel);
                $rel = ltrim($rel, '/');

                $lista[] = [
                    'nome' => $ficheiro->getFilename(),
                    'path' => $rel,
                    'tamanho' => round($ficheiro->getSize() / 1024 / 1024, 2) . ' MB',
                    'data' => date('Y-m-d H:i:s', $ficheiro->getMTime()),
                ];
            }
        }

        usort($lista, fn($a, $b) => strcmp($b['data'], $a['data']));

        return $lista;
    }

    private function decodePath(string $ficheiro): string
    {
        return str_replace('---', '/', $ficheiro);
    }

    private function resolverCaminho(string $ficheiro): ?string
    {
        $pastas = [$this->dirBD, $this->dirFiles];

        foreach ($pastas as $pasta) {

            if (!is_dir($pasta)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $f) {

                if (strpos($f->getPathname(), 'restore_db_') !== false) {
                    continue;
                }

                $rel = str_replace('\\', '/', $f->getPathname());
                $rel = str_replace($pasta, '', $rel);
                $rel = ltrim($rel, '/');

                if ($rel === $ficheiro) {
                    return $f->getPathname();
                }
            }
        }

        return null;
    }

    private function avaliarEstadoBackup(?string $data): array
    {
        if (!$data) {
            return ['estado' => 'nenhum', 'cor' => 'secondary', 'mensagem' => 'Nenhum backup encontrado'];
        }

        $timestamp = strtotime($data);
        $agora = time();
        $diferencaHoras = ($agora - $timestamp) / 3600;

        if ($diferencaHoras < 24) {
            return ['estado' => 'ok', 'cor' => 'success', 'mensagem' => 'Backup recente'];
        }

        if ($diferencaHoras < 72) {
            return ['estado' => 'aviso', 'cor' => 'warning', 'mensagem' => 'Backup com mais de 24h'];
        }

        return ['estado' => 'critico', 'cor' => 'danger', 'mensagem' => 'Backup atrasado'];
    }
}
