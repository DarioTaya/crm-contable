import Alpine from 'alpinejs';
import Chart from 'chart.js/auto';

/**
 * Alpine y Chart.js se sirven desde el bundle, no desde un CDN: la aplicacion
 * debe renderizar por completo sin conexion a internet (requisito de la
 * distribucion de escritorio).
 *
 * Se exponen en `window` porque las vistas heredadas los invocan desde bloques
 * <script> en linea. Esa dependencia desaparece a medida que esas vistas se
 * migren a componentes.
 */
window.Alpine = Alpine;
window.Chart = Chart;

Alpine.start();
