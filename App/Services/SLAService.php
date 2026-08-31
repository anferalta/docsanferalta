<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Notificacao;
use App\Services\BackupLogger;

class SLAService
{
    public static function verificarSLA()
    {
        $docs = Documento::query()
            ->join('documento_areas', 'documento_areas.id', '=', 'documentos.area_atual_id')
            ->whereNotNull('documentos.area_atual_id')
            ->select(
                'documentos.id',
                'documentos.titulo',
                'documentos.area_atual_desde',
                'documento_areas.prazo_resposta',
                'documentos.criado_por'
            )
            ->get();

        foreach ($docs as $d) {

            // ============================
            // 1. Validar integridade mínima
            // ============================
            if (empty($d->area_atual_desde) || empty($d->prazo_resposta)) {
                BackupLogger::registar(
                    'SLA',
                    "DOC {$d->id}",
                    false,
                    "Documento com dados incompletos para SLA"
                );
                continue;
            }

            // ============================
            // 2. Validar data
            // ============================
            try {
                $inicio = new \DateTime($d->area_atual_desde);
            } catch (\Exception $e) {
                BackupLogger::registar(
                    'SLA',
                    "DOC {$d->id}",
                    false,
                    "Data inválida em area_atual_desde"
                );
                continue;
            }

            $agora  = new \DateTime();
            $dias   = $inicio->diff($agora)->days;

            // ============================
            // 3. Validar prazo
            // ============================
            $prazo = (int) $d->prazo_resposta;

            if ($prazo <= 0 || $prazo > 365) {
                BackupLogger::registar(
                    'SLA',
                    "DOC {$d->id}",
                    false,
                    "Prazo inválido: {$prazo}"
                );
                continue;
            }

            // ============================
            // 4. A expirar
            // ============================
            if ($dias == $prazo - 1 || $dias == $prazo) {
                Notificacao::create([
                    'utilizador_id' => $d->criado_por,
                    'documento_id' => $d->id,
                    'tipo' => 'sla_alerta',
                    'mensagem' => "O documento '{$d->titulo}' está a aproximar-se do prazo.",
                    'url' => "/admin/tramitacao/{$d->id}",
                    'criado_em' => date('Y-m-d H:i:s')
                ]);
            }

            // ============================
            // 5. Atrasado
            // ============================
            if ($dias > $prazo) {
                Notificacao::create([
                    'utilizador_id' => $d->criado_por,
                    'documento_id' => $d->id,
                    'tipo' => 'sla_atrasado',
                    'mensagem' => "O documento '{$d->titulo}' ultrapassou o prazo.",
                    'url' => "/admin/tramitacao/{$d->id}",
                    'criado_em' => date('Y-m-d H:i:s')
                ]);
            }
        }
    }
}
