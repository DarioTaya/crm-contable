/**
 * scripts/sunafil_login.cjs
 *
 * Punto de entrada para el inicio de sesión de 1 clic en la Casilla Electrónica de SUNAFIL.
 * Delega la ejecución al motor centralizado `scripts/lib/portal_engine.cjs`.
 *
 * Uso:
 *   node scripts/sunafil_login.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL>
 */

const { ejecutarLoginPortal } = require('./lib/portal_engine.cjs');

const [,, ruc, usuario, clave] = process.argv;

if (!ruc || !usuario || !clave) {
    console.error('❌ Uso: node scripts/sunafil_login.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL>');
    process.exit(1);
}

const SUNAFIL_LOGIN_URL = 'https://casillaelectronica.sunafil.gob.pe/si.inbox/Login/SUNAT';

console.log(`🚀 Iniciando sesión SUNAFIL para RUC: ${ruc} / Usuario: ${usuario}`);

ejecutarLoginPortal({
    portal: 'sunafil',
    ruc,
    usuario,
    clave,
    urlEntrada: SUNAFIL_LOGIN_URL,
    esSunafil: true,
});
