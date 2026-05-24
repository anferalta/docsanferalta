<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Models\LogSistema;
use App\Core\Sessao;

class LogsSistemaAdminController extends BaseController
{

    private function aplicarFiltros($query)
    {
        $tipo = trim(filter_input(INPUT_GET, 'tipo', FILTER_UNSAFE_RAW) ?? '');
        $ip = filter_input(INPUT_GET, 'ip', FILTER_VALIDATE_IP) ?: null;
        $dataInicio = trim(filter_input(INPUT_GET, 'data_inicio', FILTER_UNSAFE_RAW) ?? '');
        $dataFim = trim(filter_input(INPUT_GET, 'data_fim', FILTER_UNSAFE_RAW) ?? '');
        $mensagem = trim(filter_input(INPUT_GET, 'mensagem', FILTER_UNSAFE_RAW) ?? '');

        if ($tipo) {
            $query->where('tipo', '=', $tipo);
        }

        if ($ip) {
            $query->where('ip', '=', $ip);
        }

        if ($dataInicio) {
            $query->where('criado_em', '>=', $dataInicio . ' 00:00:00');
        }

        if ($dataFim) {
            $query->where('criado_em', '<=', $dataFim . ' 23:59:59');
        }

        if ($mensagem) {
            $query->where('mensagem', 'LIKE', "%{$mensagem}%");
        }

        return $query;
    }

    public function index()
    {
        $this->authorize('admin.logs.ver');

        $porPagina = 50;
        $pagina = max(1, (int) ($_GET['p'] ?? 1));

        $query = LogSistema::query();
        $this->aplicarFiltros($query);

        $total = $query->count();
        $paginas = max(1, ceil($total / $porPagina));

        if ($pagina > $paginas) {
            $pagina = $paginas;
        }

        $offset = ($pagina - 1) * $porPagina;

        $logs = $query
                ->orderBy('id', 'DESC')
                ->limit($porPagina)
                ->offset($offset)
                ->get();

        return $this->render('admin/logs/index.twig', [
                    'logs' => $logs,
                    'pagina' => $pagina,
                    'paginas' => $paginas,
                    'filtros' => [
                        'tipo' => $_GET['tipo'] ?? '',
                        'ip' => $_GET['ip'] ?? '',
                        'data_inicio' => $_GET['data_inicio'] ?? '',
                        'data_fim' => $_GET['data_fim'] ?? '',
                        'mensagem' => $_GET['mensagem'] ?? '',
                    ],
        ]);
    }

    public function exportar()
    {
        $this->authorize('admin.logs.exportar');

        $query = LogSistema::query();
        $this->aplicarFiltros($query);

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="logs_sistema.csv"');

        $f = fopen('php://output', 'w');
        fputcsv($f, ['ID', 'Tipo', 'Mensagem', 'Ficheiro', 'Linha', 'IP', 'Criado em']);

        foreach ($query->orderBy('id', 'DESC')->chunk(500) as $chunk) {
            foreach ($chunk as $log) {
                fputcsv($f, [
                    $log->id,
                    $log->tipo,
                    $log->mensagem,
                    $log->ficheiro,
                    $log->linha,
                    $log->ip,
                    $log->criado_em,
                ]);
            }
        }

        fclose($f);
        exit;
    }

    public function detalhes($id)
    {
        $this->authorize('admin.logs.ver');

        $log = LogSistema::find($id);

        if (!$log) {
            Sessao::flash('erro', 'Registo não encontrado.');
            return $this->redirect('/admin/logs');
        }

        return $this->render('admin/logs/detalhes.twig', [
                    'log' => $log
        ]);
    }
}
