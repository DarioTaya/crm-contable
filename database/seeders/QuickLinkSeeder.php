<?php

namespace Database\Seeders;

use App\Models\QuickLink;
use Illuminate\Database\Seeder;

class QuickLinkSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $links = [
            [
                'label' => 'SUNAT - Clave SOL (Operaciones en Línea)',
                'url' => 'https://api-seguridad.sunat.gob.pe/v1/clientessops/cl-ti-itmenu/inicio',
                'orden' => 1,
            ],
            [
                'label' => 'SUNAT - Consulta RUC Oficial',
                'url' => 'https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/FrameCriterioBusquedaWeb.jsp',
                'orden' => 2,
            ],
            [
                'label' => 'SUNAT - Tipo de Cambio Oficial',
                'url' => 'https://e-consulta.sunat.gob.pe/cl-at-ittipcam/tcS01Alias',
                'orden' => 3,
            ],
            [
                'label' => 'SBS - Tipo de Cambio Contable SBS',
                'url' => 'https://www.sbs.gob.pe/app/pp/sistip_orden/paginas/vistas/reg_consulta.aspx',
                'orden' => 4,
            ],
            [
                'label' => 'SUNAFIL - Casilla Electrónica',
                'url' => 'https://casillaelectronica.sunafil.gob.pe/',
                'orden' => 5,
            ],
            [
                'label' => 'AFPnet - Declaración y Pago AFP',
                'url' => 'https://www.afpnet.com.pe/',
                'orden' => 6,
            ],
        ];

        foreach ($links as $link) {
            QuickLink::firstOrCreate(
                ['label' => $link['label']],
                [
                    'url' => $link['url'],
                    'orden' => $link['orden'],
                    'activo' => true,
                ]
            );
        }
    }
}
