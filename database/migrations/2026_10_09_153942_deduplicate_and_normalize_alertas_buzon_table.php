<?php

use App\Models\AlertaBuzon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $alertas = AlertaBuzon::orderBy('id', 'asc')->get();

        $mojibakeMap = [
            'Ã³' => 'ó',
            'Ãº' => 'ú',
            'Ã¡' => 'á',
            'Ã©' => 'é',
            'Ã­' => 'í',
            'Ã±' => 'ñ',
            'NÂ°' => 'N°',
            'Â°' => '°',
        ];

        $grupos = [];

        foreach ($alertas as $alerta) {
            $asuntoLimpio = trim(preg_replace('/^ASUNTO:\s*/i', '', (string) $alerta->asunto));
            $asuntoLimpio = strtr($asuntoLimpio, $mojibakeMap);
            $asuntoLimpio = preg_replace('/\s+/', ' ', $asuntoLimpio);

            $key = $alerta->cliente_id.'_'.$alerta->plataforma.'_'.mb_strtolower($asuntoLimpio);

            $grupos[$key][] = [
                'model' => $alerta,
                'asunto_limpio' => $asuntoLimpio,
            ];
        }

        foreach ($grupos as $items) {
            if (count($items) === 1) {
                $item = $items[0];
                if ($item['model']->asunto !== $item['asunto_limpio']) {
                    $item['model']->asunto = $item['asunto_limpio'];
                    $item['model']->saveQuietly();
                }

                continue;
            }

            // Ordenar candidatos: primero los que tienen PDF físico o ruta_pdf, luego mayor ID
            usort($items, function ($a, $b) {
                $aScore = (! empty($a['model']->ruta_pdf) ? 2 : 0) + ($a['model']->tiene_pdf ? 1 : 0);
                $bScore = (! empty($b['model']->ruta_pdf) ? 2 : 0) + ($b['model']->tiene_pdf ? 1 : 0);

                if ($aScore !== $bScore) {
                    return $bScore <=> $aScore;
                }

                return $b['model']->id <=> $a['model']->id;
            });

            $ganador = $items[0]['model'];
            $asuntoFinal = $items[0]['asunto_limpio'];

            // Preservar estado 'Leído' si alguno de los duplicados ya fue marcado
            $algunoLeido = collect($items)->contains(fn ($it) => $it['model']->estado === 'Leído');
            $algunaWA = collect($items)->first(fn ($it) => ! empty($it['model']->notificado_whatsapp_at));

            $ganador->asunto = $asuntoFinal;
            if ($algunoLeido) {
                $ganador->estado = 'Leído';
            }
            if ($algunaWA && empty($ganador->notificado_whatsapp_at)) {
                $ganador->notificado_whatsapp_at = $algunaWA['model']->notificado_whatsapp_at;
            }
            $ganador->saveQuietly();

            // Eliminar los duplicados perdedores
            for ($i = 1; $i < count($items); $i++) {
                $items[$i]['model']->delete();
            }
        }

        // Limpieza de duplicados en la tabla notificaciones tradicional si existe
        if (DB::getSchemaBuilder()->hasTable('notificaciones')) {
            $notifs = DB::table('notificaciones')->get();
            $notifGrupos = [];
            foreach ($notifs as $n) {
                $asuntoNorm = trim(preg_replace('/^ASUNTO:\s*/i', '', (string) $n->asunto));
                $asuntoNorm = strtr($asuntoNorm, $mojibakeMap);
                $key = $n->cliente_id.'_'.$n->fecha.'_'.mb_strtolower($asuntoNorm);
                $notifGrupos[$key][] = [
                    'id' => $n->id,
                    'asunto_norm' => $asuntoNorm,
                ];
            }

            foreach ($notifGrupos as $nItems) {
                if (count($nItems) === 1) {
                    DB::table('notificaciones')->where('id', $nItems[0]['id'])->update(['asunto' => $nItems[0]['asunto_norm']]);

                    continue;
                }

                // Conservar el primero y actualizar su asunto
                $primero = $nItems[0];
                DB::table('notificaciones')->where('id', $primero['id'])->update(['asunto' => $primero['asunto_norm']]);

                // Eliminar el resto
                for ($j = 1; $j < count($nItems); $j++) {
                    DB::table('notificaciones')->where('id', $nItems[$j]['id'])->delete();
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // No destructivo, la deduplicación y normalización es irreversible.
    }
};
