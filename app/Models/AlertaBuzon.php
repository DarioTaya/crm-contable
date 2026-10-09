<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AlertaBuzon extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    /**
     * Nombre de la tabla asociada.
     *
     * @var string
     */
    protected $table = 'alertas_buzon';

    /**
     * Atributos que se pueden asignar de manera masiva.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'cliente_id',
        'plataforma',
        'asunto',
        'numero_notificacion',
        'contenido',
        'fecha_recepcion',
        'estado',
        'prioridad',
        'tiene_pdf',
        'ruta_pdf',
        'nombre_archivo_pdf',
        'ruta_constancia_pdf',
        'nombre_archivo_constancia_pdf',
        'notificado_whatsapp_at',
    ];

    /**
     * Atributos dinámicos que se añaden a la serialización JSON.
     *
     * @var array<int, string>
     */
    protected $appends = [
        'contenido_formateado',
    ];

    /**
     * Atributos casteados a tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cliente_id' => 'integer',
            'tiene_pdf' => 'boolean',
            'fecha_recepcion' => 'datetime',
            'notificado_whatsapp_at' => 'datetime',
        ];
    }

    /**
     * Relación inversa: Cada alerta pertenece a un cliente.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Scope para filtrar alertas con estado Pendiente.
     */
    public function scopePendientes(Builder $query): Builder
    {
        return $query->where('estado', 'Pendiente');
    }

    /**
     * Scope para filtrar alertas con estado Leído.
     */
    public function scopeLeidas(Builder $query): Builder
    {
        return $query->where('estado', 'Leído');
    }

    /**
     * Scope para filtrar alertas de Alta Prioridad.
     */
    public function scopeAltaPrioridad(Builder $query): Builder
    {
        return $query->where('prioridad', 'Alta');
    }

    /**
     * Scope para filtrar por plataforma emisora (SUNAT o SUNAFIL).
     */
    public function scopePorPlataforma(Builder $query, string $plataforma): Builder
    {
        return $query->where('plataforma', $plataforma);
    }

    /**
     * Scope para alertas aún no notificadas por WhatsApp.
     */
    public function scopeNoNotificadasWhatsApp(Builder $query): Builder
    {
        return $query->whereNull('notificado_whatsapp_at');
    }

    /**
     * Scope para alertas ya notificadas por WhatsApp.
     */
    public function scopeNotificadasWhatsApp(Builder $query): Builder
    {
        return $query->whereNotNull('notificado_whatsapp_at');
    }

    /**
     * Verifica si la alerta se encuentra en estado Pendiente.
     */
    public function esPendiente(): bool
    {
        return $this->estado === 'Pendiente';
    }

    /**
     * Verifica si la alerta es de alta prioridad.
     */
    public function esAltaPrioridad(): bool
    {
        return $this->prioridad === 'Alta';
    }

    /**
     * Verifica si la alerta ya fue despachada por WhatsApp.
     */
    public function yaFueNotificadaWhatsApp(): bool
    {
        return $this->notificado_whatsapp_at !== null;
    }

    /**
     * Verifica si la alerta cuenta con un documento PDF disponible o generable.
     */
    public function tieneDocumentoPdf(): bool
    {
        return (bool) $this->tiene_pdf || ! empty($this->ruta_pdf);
    }

    /**
     * Scope para filtrar alertas con archivo PDF disponible.
     */
    public function scopeConPdf(Builder $query): Builder
    {
        return $query->where('tiene_pdf', true)->orWhereNotNull('ruta_pdf');
    }

    /**
     * Verifica si la alerta cuenta con constancia de notificación PDF disponible.
     */
    public function tieneConstanciaPdf(): bool
    {
        return ! empty($this->ruta_constancia_pdf);
    }

    /**
     * Retorna el nombre de archivo sugerido para la descarga del documento PDF.
     */
    public function nombreDescargaPdf(): string
    {
        if (! empty($this->nombre_archivo_pdf)) {
            return $this->nombre_archivo_pdf;
        }

        $ruc = $this->cliente?->ruc ?? 'SUNAT';
        $fecha = $this->fecha_recepcion ? $this->fecha_recepcion->format('Ymd') : date('Ymd');
        $slug = Str::slug(mb_substr($this->asunto, 0, 30), '_');

        return "Notificacion_{$this->plataforma}_{$ruc}_{$fecha}_{$slug}.pdf";
    }

    /**
     * Retorna el nombre de archivo sugerido para la constancia de notificación PDF.
     */
    public function nombreDescargaConstanciaPdf(): string
    {
        if (! empty($this->nombre_archivo_constancia_pdf)) {
            return $this->nombre_archivo_constancia_pdf;
        }

        $ruc = $this->cliente?->ruc ?? 'SUNAT';
        $fecha = $this->fecha_recepcion ? $this->fecha_recepcion->format('Ymd') : date('Ymd');
        $numero = ! empty($this->numero_notificacion) ? Str::slug($this->numero_notificacion, '_') : 'constancia';

        return "Constancia_Notificacion_{$this->plataforma}_{$ruc}_{$fecha}_{$numero}.pdf";
    }

    /**
     * Retorna el contenido de la notificación depurado, con enlaces oficiales y sin elementos rotos de SUNAT.
     */
    public function getContenidoFormateadoAttribute(): string
    {
        $html = (string) ($this->contenido ?? '');
        if (trim($html) === '') {
            return '';
        }

        // 1. Sustituir variables de plantilla no resueltas por SUNAT
        if ($this->cliente) {
            $html = str_replace('$comunicado.razon_social', e($this->cliente->razon_social), $html);
        }
        $html = str_replace('$comunicado.des_tip_doc', 'Resolución', $html);
        $html = str_replace('$comunicado.num_doc', e($this->numero_notificacion ?? ''), $html);

        // 2. Eliminar imágenes espaciadoras rotas (blank.gif) de SUNAT
        $html = preg_replace('/<img[^>]*blank\.gif[^>]*>/i', '', $html);

        // 3. Convertir rutas relativas de imágenes o recursos a URLs absolutas de SUNAT
        $html = preg_replace('/src="\/([a-zA-Z0-9_\-\/]+(\.gif|\.png|\.jpg|\.jpeg)?)"/i', 'src="https://ww1.sunat.gob.pe/$1"', $html);

        // 4. Si la notificación cuenta con PDF, convertir llamadas JS a enlaces de descarga directa del CRM
        if ($this->tieneDocumentoPdf()) {
            $html = preg_replace(
                '/href="JavaScript:goArchivoDescarga\([^)]+\)"/i',
                'href="/alertas/'.$this->id.'/descargar-pdf?tipo=documento" target="_blank" class="font-bold text-rose-600 underline hover:text-rose-800 transition" title="Descargar documento oficial PDF"',
                $html
            );
        } else {
            $html = preg_replace(
                '/href="JavaScript:goArchivoDescarga\([^)]+\)"/i',
                'href="javascript:void(0)" class="text-gray-500 cursor-not-allowed" title="Archivo no disponible"',
                $html
            );
        }

        return $html;
    }
}
