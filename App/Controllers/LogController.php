<?php
namespace App\Controllers;

use App\Core\Log;
use App\Core\Sessao;
use Twig\Loader\FilesystemLoader;
use Twig\Environment;

class LogController
{
    private Environment $twig;
    private Log $log;
    private Sessao $sess;

    public function __construct()
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../views');
        $this->twig = new Environment($loader);

        $this->log = new Log();
        $this->sess = new Sessao();
    }

    public function index(): void
    {
        echo $this->twig->render('logs/list.twig', [
            'logs' => $this->log->listar(200),
            'flash' => $this->sess->flash(),
            'usuario_nome' => $_SESSION['usuario_nome'] ?? null
        ]);
    }
}