<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\Notificacao;

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

            $inicio = new \DateTime($d->area_atual_desde);
            $agora  = new \DateTime();
            $dias   = $inicio->diff($agora)->days;

            $prazo = (int) $d->prazo_resposta;

            // A expirar (prazo - 1 ou prazo)
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

            // Atrasado
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
