/**
 * scripts/sunat_login.cjs
 *
 * Punto de entrada para el inicio de sesión de 1 clic en SUNAT Operaciones en Línea (Clave SOL).
 * Delega la ejecución al motor centralizado `scripts/lib/portal_engine.cjs`.
 *
 * Uso:
 *   node scripts/sunat_login.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL> [URL_DESTINO]
 */

const { ejecutarLoginPortal } = require('./lib/portal_engine.cjs');

const [,, ruc, usuario, clave, destinoArg] = process.argv;

if (!ruc || !usuario || !clave) {
    console.error('❌ Uso: node scripts/sunat_login.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL> [URL_DESTINO]');
    process.exit(1);
}

function destinoSeguro(valor) {
    if (!valor || !valor.startsWith('https://')) return null;
    try {
        const host = new URL(valor).hostname;
        if (host === 'sunat.gob.pe' || host.endsWith('.sunat.gob.pe')) return valor;
    } catch (_) {}
    return null;
}

const SUNAT_LOGIN_URL = 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm';
const urlEntrada = destinoSeguro(destinoArg) || SUNAT_LOGIN_URL;

console.log(`🚀 Iniciando sesión SUNAT para RUC: ${ruc} / Usuario: ${usuario}`);

ejecutarLoginPortal({
    portal: 'sunat',
    ruc,
    usuario,
    clave,
    urlEntrada,
    esSunafil: false,
});
