<?php

namespace App\Models;

use App\Traits\BelongsToEstudioViaCliente;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Comprobante extends Model
{
    use BelongsToEstudioViaCliente, HasFactory;

    /**
     * Tabla asociada al modelo.
     *
     * @var string
     */
    protected $table = 'comprobantes';

    /**
     * Atributos asignables en masa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'estudio_id',
        'cliente_id',
        'periodo',
        'tipo_registro',
        'tipo_comprobante',
        'serie',
        'correlativo',
        'ruc_tercero',
        'razon_social_tercero',
        'moneda',
        'base_imponible',
        'igv',
        'total',
        'estado_sire',
        'estado_descarga',
        'estado_xml',
        'estado_cdr',
        'estado_pdf',
        'ruta_xml',
        'ruta_pdf',
        'ruta_cdr',
        'mensaje_soap',
        'intentos_soap',
    ];

    /**
     * Casts de tipos nativos.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'base_imponible' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'intentos_soap' => 'integer',
        ];
    }

    /**
     * Relación con el cliente titular del expediente/registro.
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Genera la ruta estructurada oficial:
     * clientes/{id_cliente}/sire/{anio}/{mes}/{tipo_registro}/
     */
    public static function structuredStoragePath(int $clienteId, string $periodo, string $tipoRegistro): string
    {
        $partes = explode('-', $periodo);
        $anio = strlen($partes[0]) === 4 ? $partes[0] : ($partes[1] ?? date('Y'));
        $mes = strlen($partes[0]) === 4 ? ($partes[1] ?? date('m')) : $partes[0];
        $mes = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $tipoSlug = match (strtoupper(trim($tipoRegistro))) {
            'RCE', 'COMPRAS' => 'compras',
            'RVIE', 'VENTAS' => 'ventas',
            default => strtolower(trim($tipoRegistro)),
        };

        return "clientes/{$clienteId}/sire/{$anio}/{$mes}/{$tipoSlug}";
    }

    /**
     * Genera la ruta relativa dentro de storage/app/ con soporte tanto para ID como para RUC legacy.
     */
    public static function storagePathFor(int|string $clienteIdentifier, string $periodo, string $tipoRegistro): string
    {
        if (is_numeric($clienteIdentifier) && (int) $clienteIdentifier < 1000000000) {
            return self::structuredStoragePath((int) $clienteIdentifier, $periodo, $tipoRegistro);
        }

        $partes = explode('-', $periodo);
        $anio = strlen($partes[0]) === 4 ? $partes[0] : ($partes[1] ?? date('Y'));
        $mes = strlen($partes[0]) === 4 ? ($partes[1] ?? date('m')) : $partes[0];
        $mes = str_pad($mes, 2, '0', STR_PAD_LEFT);
        $tipoRegistro = strtoupper(trim($tipoRegistro));

        return "comprobantes/{$clienteIdentifier}/{$anio}/{$mes}/{$tipoRegistro}";
    }

    /**
     * Obtiene el directorio relativo dentro de storage/app/ para este comprobante.
     */
    public function getStorageDirectoryAttribute(): string
    {
        $clienteId = (int) ($this->cliente_id ?: ($this->cliente?->id ?? 1));

        return self::structuredStoragePath($clienteId, $this->periodo, $this->tipo_registro);
    }

    /**
     * Obtiene el nombre base para los archivos físicos según estándar SUNAT:
     * {ruc_emisor}-{tipo_comprobante}-{serie}-{correlativo}
     */
    public function getNombreArchivoBaseAttribute(): string
    {
        $esCompra = $this->tipo_registro === 'RCE';
        $emisorRuc = $esCompra ? ($this->ruc_tercero ?: '20000000001') : ($this->cliente?->ruc ?? '00000000000');
        $tipoDoc = $this->tipo_comprobante ?: '01';
        $serie = $this->serie ?: 'F001';
        $correlativo = $this->correlativo ?: '00000001';

        return sprintf('%s-%s-%s-%s', $emisorRuc, $tipoDoc, $serie, $correlativo);
    }

    /**
     * Obtiene la ruta física absoluta en el disco para este comprobante.
     */
    public function getAbsoluteStorageDirectoryAttribute(): string
    {
        return storage_path('app/'.$this->storage_directory);
    }

    /**
     * Determina si el archivo XML existe físicamente en el disco.
     */
    public function getTieneXmlAttribute(): bool
    {
        if (empty($this->ruta_xml)) {
            return false;
        }

        return file_exists(storage_path('app/'.$this->ruta_xml)) || Storage::disk('local')->exists($this->ruta_xml);
    }

    /**
     * Determina si el archivo PDF existe físicamente en el disco.
     */
    public function getTienePdfAttribute(): bool
    {
        if (empty($this->ruta_pdf)) {
            return false;
        }

        return file_exists(storage_path('app/'.$this->ruta_pdf)) || Storage::disk('local')->exists($this->ruta_pdf);
    }

    /**
     * Determina si el archivo CDR existe físicamente en el disco.
     */
    public function getTieneCdrAttribute(): bool
    {
        if (empty($this->ruta_cdr)) {
            return false;
        }

        return file_exists(storage_path('app/'.$this->ruta_cdr)) || Storage::disk('local')->exists($this->ruta_cdr);
    }

    /**
     * Devuelve la serie y correlativo formateados (ej. F001-00001234).
     */
    public function getNumeroComprobanteAttribute(): string
    {
        if (! empty($this->serie) && ! empty($this->correlativo)) {
            return "{$this->serie}-{$this->correlativo}";
        }

        return $this->serie ?: ($this->correlativo ?: '-');
    }

    /**
     * Devuelve el nombre legible del tipo de comprobante según catálogo SUNAT.
     */
    public function getDescripcionTipoComprobanteAttribute(): string
    {
        return match ($this->tipo_comprobante) {
            '01' => 'Factura',
            '03' => 'Boleta de Venta',
            '07' => 'Nota de Crédito',
            '08' => 'Nota de Débito',
            'R' => 'Recibo por Honorarios',
            '02' => 'Recibo por Honorarios',
            '09' => 'Guía de Remisión',
            '14' => 'Recibo Serv. Públicos',
            default => $this->tipo_comprobante ?: 'Comprobante',
        };
    }

    /**
     * Determina si el comprobante tiene todos sus archivos listos y disponibles.
     */
    public function getEstaCompletamenteDisponibleAttribute(): bool
    {
        return ($this->tiene_xml || $this->estado_xml === 'disponible') &&
               ($this->tiene_cdr || $this->estado_cdr === 'disponible') &&
               ($this->tiene_pdf || $this->estado_pdf === 'disponible');
    }
}
