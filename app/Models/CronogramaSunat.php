<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CronogramaSunat extends Model
{
    use HasFactory;

    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'cronogramas_sunat';

    /**
     * Atributos asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'anio',
        'tipo',
        'mes',
        'periodo',
        'digito_0',
        'digito_1',
        'digito_2_3',
        'digito_4_5',
        'digito_6_7',
        'digito_8_9',
        'buenos_contribuyentes',
        'fecha_vencimiento_general',
        'descripcion',
    ];

    /**
     * Atributos casteados a tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
            'digito_0' => 'date:Y-m-d',
            'digito_1' => 'date:Y-m-d',
            'digito_2_3' => 'date:Y-m-d',
            'digito_4_5' => 'date:Y-m-d',
            'digito_6_7' => 'date:Y-m-d',
            'digito_8_9' => 'date:Y-m-d',
            'buenos_contribuyentes' => 'date:Y-m-d',
            'fecha_vencimiento_general' => 'date:Y-m-d',
        ];
    }

    /**
     * Retorna la fecha de vencimiento según el último dígito del RUC y la condición de Buen Contribuyente.
     */
    public function getFechaPorDigito(string $ultimoDigito, bool $esBuenContribuyente = false): ?Carbon
    {
        if ($esBuenContribuyente && $this->buenos_contribuyentes) {
            $val = $this->buenos_contribuyentes;
            $str = $val instanceof Carbon ? $val->toDateString() : (string) $val;

            return Carbon::createFromFormat('!Y-m-d', substr($str, 0, 10), 'America/Lima');
        }

        $ultimoCaracter = substr(trim((string) $ultimoDigito), -1);
        $digito = is_numeric($ultimoCaracter) ? (int) $ultimoCaracter : 0;

        $fecha = match ($digito) {
            0 => $this->digito_0,
            1 => $this->digito_1,
            2, 3 => $this->digito_2_3,
            4, 5 => $this->digito_4_5,
            6, 7 => $this->digito_6_7,
            8, 9 => $this->digito_8_9,
            default => $this->fecha_vencimiento_general ?? $this->digito_0,
        };

        $target = $fecha ?? $this->fecha_vencimiento_general;

        if (! $target) {
            return null;
        }

        $str = $target instanceof Carbon ? $target->toDateString() : (string) $target;

        return Carbon::createFromFormat('!Y-m-d', substr($str, 0, 10), 'America/Lima');
    }

    /**
     * Nombre formal del mes en español.
     */
    public function getNombreMesAttribute(): string
    {
        if (! $this->mes) {
            return $this->descripcion ?? 'Anual';
        }

        $meses = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Setiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        return $meses[$this->mes] ?? "Mes {$this->mes}";
    }

    /**
     * Scope para filtrar por año.
     */
    public function scopePorAnio(Builder $query, int $anio): Builder
    {
        return $query->where('anio', $anio);
    }

    /**
     * Scope para filtrar por tipo de obligación.
     */
    public function scopePorTipo(Builder $query, string $tipo): Builder
    {
        return $query->where('tipo', $tipo);
    }
}
