<?php

namespace App\Services;

class AgendamentosService
{
    private string $ficheiro;

    public function __construct()
    {
        $dir = realpath(__DIR__ . '/../../storage/agendamentos')
            ?: (__DIR__ . '/../../storage/agendamentos');

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $this->ficheiro = $dir . '/agendamentos.json';

        if (!file_exists($this->ficheiro)) {
            file_put_contents($this->ficheiro, json_encode([]));
        }
    }

    /**
     * Listar todos os agendamentos
     */
    public function listar(): array
    {
        $dados = json_decode(file_get_contents($this->ficheiro), true);
        return $dados ?: [];
    }

    /**
     * Guardar todos os agendamentos
     */
    private function guardar(array $dados): void
    {
        file_put_contents($this->ficheiro, json_encode($dados, JSON_PRETTY_PRINT));
    }

    /**
     * Criar ou atualizar um agendamento
     */
    public function definir(string $nome, string $frequencia, bool $ativo): void
    {
        $todos = $this->listar();

        $todos[$nome] = [
            'nome'            => $nome,
            'frequencia'      => $frequencia,
            'ativo'           => $ativo,
            'ultima_execucao' => $todos[$nome]['ultima_execucao'] ?? null,
            'proxima_execucao'=> $this->calcularProximaExecucao($frequencia),
        ];

        $this->guardar($todos);
    }

    /**
     * Ativar tarefa
     */
    public function ativar(string $nome): void
    {
        $todos = $this->listar();
        if (!isset($todos[$nome])) return;

        $todos[$nome]['ativo'] = true;
        $todos[$nome]['proxima_execucao'] = $this->calcularProximaExecucao($todos[$nome]['frequencia']);

        $this->guardar($todos);
    }

    /**
     * Desativar tarefa
     */
    public function desativar(string $nome): void
    {
        $todos = $this->listar();
        if (!isset($todos[$nome])) return;

        $todos[$nome]['ativo'] = false;

        $this->guardar($todos);
    }

    /**
     * Eliminar tarefa
     */
    public function eliminar(string $nome): void
    {
        $todos = $this->listar();
        unset($todos[$nome]);
        $this->guardar($todos);
    }

    /**
     * Registar execução
     */
    public function registarExecucao(string $nome): void
    {
        $todos = $this->listar();
        if (!isset($todos[$nome])) return;

        $todos[$nome]['ultima_execucao'] = date('Y-m-d H:i:s');
        $todos[$nome]['proxima_execucao'] = $this->calcularProximaExecucao($todos[$nome]['frequencia']);

        $this->guardar($todos);
    }

    /**
     * Calcular próxima execução (cron simplificado)
     */
    private function calcularProximaExecucao(string $cron): ?string
    {
        // Exemplo: "0 3 * * *"
        [$min, $hora, $diaMes, $mes, $diaSemana] = explode(' ', $cron);

        $agora = time();

        // Próxima execução: hoje à hora/minuto especificado
        $proxima = mktime($hora, $min, 0);

        if ($proxima <= $agora) {
            $proxima = strtotime('+1 day', $proxima);
        }

        return date('Y-m-d H:i:s', $proxima);
    }
}
