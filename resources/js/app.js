import Alpine from 'alpinejs';

/**
 * Alpine se sirve desde el bundle, no desde un CDN: la aplicacion debe
 * renderizar por completo sin conexion a internet.
 *
 * Chart.js pesa mas que el resto del bundle junto. Se carga aparte, y
 * unicamente cuando la pagina trae un grafico, para que el resto de pantallas
 * no paguen ese parseo en cada navegacion.
 */
window.Alpine = Alpine;

Alpine.start();

if (document.getElementById('obligacionesChart') || document.getElementById('balancesChart') || document.getElementById('tipoCambioChart')) {
    import('chart.js/auto').then(({ default: Chart }) => {
        window.Chart = Chart;
        document.dispatchEvent(new Event('crm:chart-ready'));
    });
}
