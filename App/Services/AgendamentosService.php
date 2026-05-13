<?php

namespace App\Services;

class AgendamentosService {

    private string $dir;

    public function __construct() {
        $this->dir = realpath(__DIR__ . '/../../backups/Agendamentos');

        if (!$this->dir) {
            throw new \Exception("Diretório de agendamentos não encontrado.");
        }
    }

    public function listar(): array {
        $agendamentos = [];

        foreach (scandir($this->dir) as $ficheiro) {
            if ($ficheiro === '.' || $ficheiro === '..')
                continue;

            $path = $this->dir . DIRECTORY_SEPARATOR . $ficheiro;

            if (!is_file($path))
                continue;

            $dados = $this->parse($path);

            $agendamentos[$ficheiro] = $dados;
        }

        return $agendamentos;
    }

    public function parse(string $path): array {
        $conteudo = @file_get_contents($path);

        if (!$conteudo) {
            return [
                'nome' => basename($path),
                'frequencia' => 'desconhecido',
                'ativo' => false,
                'ultima_execucao' => null,
                'proxima_execucao' => null,
            ];
        }

        $linhas = explode("\n", $conteudo);
        $dados = [];

        foreach ($linhas as $linha) {
            $linha = trim($linha);
            if (!$linha || !str_contains($linha, '='))
                continue;

            [$chave, $valor] = explode('=', $linha, 2);
            $dados[$chave] = trim($valor);
        }

        return [
            'nome' => $dados['nome'] ?? basename($path),
            'frequencia' => $dados['frequencia'] ?? 'desconhecido',
            'ativo' => isset($dados['ativo']) ? (bool) $dados['ativo'] : false,
            'ultima_execucao' => $dados['ultima_execucao'] ?? null,
            'proxima_execucao' => $dados['proxima_execucao'] ?? null,
        ];
    }

    public function guardar(string $ficheiro, array $dados): bool {
        $path = $this->dir . DIRECTORY_SEPARATOR . $ficheiro;

        $conteudo = "";
        foreach ($dados as $k => $v) {
            $conteudo .= "$k=$v\n";
        }

        return file_put_contents($path, $conteudo) !== false;
    }

    public function eliminar(string $ficheiro): bool {
        $path = $this->dir . DIRECTORY_SEPARATOR . $ficheiro;
        return is_file($path) ? unlink($path) : false;
    }

    public function ativar(string $ficheiro): bool {
        $dados = $this->parse($this->dir . '/' . $ficheiro);
        $dados['ativo'] = 1;
        return $this->guardar($ficheiro, $dados);
    }

    public function desativar(string $ficheiro): bool {
        $dados = $this->parse($this->dir . '/' . $ficheiro);
        $dados['ativo'] = 0;
        return $this->guardar($ficheiro, $dados);
    }

    public function getPath(string $ficheiro): string {
        return $this->dir . DIRECTORY_SEPARATOR . $ficheiro;
    }
}
