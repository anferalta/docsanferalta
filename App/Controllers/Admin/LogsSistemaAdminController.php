<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Models\LogSistema;
use App\Core\Sessao;

class LogsSistemaAdminController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.logs.ver');

        $porPagina = 50;
        $pagina = isset($_GET['p']) ? max(1, (int)$_GET['p']) : 1;
        $offset = ($pagina - 1) * $porPagina;

        $query = LogSistema::query();

        // Filtros seguros
        if (!empty($_GET['tipo'])) {
            $query->where('tipo', '=', $_GET['tipo']);
        }

        if (!empty($_GET['ip'])) {
            $query->where('ip', '=', $_GET['ip']);
        }

        if (!empty($_GET['data_inicio'])) {
            $query->where('criado_em', '>=', $_GET['data_inicio'] . ' 00:00:00');
        }

        if (!empty($_GET['data_fim'])) {
            $query->where('criado_em', '<=', $_GET['data_fim'] . ' 23:59:59');
        }

        if (!empty($_GET['mensagem'])) {
            $query->where('mensagem', 'LIKE', '%' . $_GET['mensagem'] . '%');
        }

        // Contagem total com filtros
        $total = $query->count();

        // Buscar logs paginados
        $logs = $query
            ->orderBy('id', 'DESC')
            ->limit($porPagina)
            ->offset($offset)
            ->get();

        $paginas = (int)ceil($total / $porPagina);

        return $this->render('admin/logs/index.twig', [
            'logs'    => $logs,
            'pagina'  => $pagina,
            'paginas' => $paginas,
            'filtros' => [
                'tipo'        => $_GET['tipo']        ?? '',
                'ip'          => $_GET['ip']          ?? '',
                'data_inicio' => $_GET['data_inicio'] ?? '',
                'data_fim'    => $_GET['data_fim']    ?? '',
                'mensagem'    => $_GET['mensagem']    ?? '',
            ],
        ]);
    }

    public function exportar()
    {
        $this->authorize('admin.logs.exportar');

        $query = LogSistema::query();

        // Reaplicar filtros
        if (!empty($_GET['tipo'])) {
            $query->where('tipo', '=', $_GET['tipo']);
        }

        if (!empty($_GET['ip'])) {
            $query->where('ip', '=', $_GET['ip']);
        }

        if (!empty($_GET['data_inicio'])) {
            $query->where('criado_em', '>=', $_GET['data_inicio'] . ' 00:00:00');
        }

        if (!empty($_GET['data_fim'])) {
            $query->where('criado_em', '<=', $_GET['data_fim'] . ' 23:59:59');
        }

        if (!empty($_GET['mensagem'])) {
            $query->where('mensagem', 'LIKE', '%' . $_GET['mensagem'] . '%');
        }

        $logs = $query->orderBy('id', 'DESC')->get();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="logs_sistema.csv"');

        $f = fopen('php://output', 'w');

        fputcsv($f, ['ID', 'Tipo', 'Mensagem', 'Ficheiro', 'Linha', 'IP', 'Criado em']);

        foreach ($logs as $log) {
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