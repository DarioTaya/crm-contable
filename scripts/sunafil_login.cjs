/**
 * scripts/sunafil_login.cjs
 *
 * Login en la Casilla Electrónica de SUNAFIL con Clave SOL.
 * La ventana permanece fuera de la pantalla mientras se escriben RUC, usuario
 * y clave. El CRM recibe la señal "opening" y, un segundo después, se muestra
 * la casilla ya iniciada.
 *
 * Uso:
 *   node scripts/sunafil_login.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL>
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const puppeteer = require('puppeteer');

// ─── Validar argumentos ───────────────────────────────────────────────────────
const [,, ruc, usuario, clave] = process.argv;

if (!ruc || !usuario || !clave) {
    console.error('❌ Uso: node scripts/sunafil_login.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL>');
    process.exit(1);
}

// ─── Archivo de Control de Proceso (PID) por RUC ─────────────────────────────
const pidFile = path.join(__dirname, '..', 'storage', 'framework', `login_pid_sunafil_${ruc}.pid`);
try {
    fs.writeFileSync(pidFile, process.pid.toString());
} catch (_) {}

// El navegador sobrevive al proceso Node, así que su PID se guarda aparte: si
// queda abierto de un acceso anterior, retiene el perfil y el siguiente intento
// se queda colgado al navegar.
const browserPidFile = path.join(__dirname, '..', 'storage', 'framework', `login_browser_sunafil_${ruc}.pid`);

function cleanupPid() {
    try {
        if (fs.existsSync(pidFile)) fs.unlinkSync(pidFile);
    } catch (_) {}
    try {
        if (fs.existsSync(browserPidFile)) fs.unlinkSync(browserPidFile);
    } catch (_) {}
}

function cerrarNavegadorAnterior() {
    let pid = 0;
    try {
        if (!fs.existsSync(browserPidFile)) {
            return;
        }
        pid = parseInt(fs.readFileSync(browserPidFile, 'utf8'), 10);
        fs.unlinkSync(browserPidFile);
    } catch (_) {
        return;
    }

    if (!pid) {
        return;
    }

    try {
        if (process.platform === 'win32') {
            execSync(`taskkill /F /T /PID ${pid}`, { stdio: 'ignore' });
        } else {
            process.kill(pid, 'SIGKILL');
        }
    } catch (_) {}
}

function recordarNavegador(browser) {
    try {
        const pid = browser.process() ? browser.process().pid : null;
        if (pid) {
            fs.writeFileSync(browserPidFile, String(pid));
        }
    } catch (_) {}
}

function directorioPerfil() {
    const base = process.env.LOCALAPPDATA
        || process.env.HOME
        || process.env.USERPROFILE
        || path.join(__dirname, '..', 'storage', 'app');
    const dir = path.join(base, 'CrmContable', 'navegador', 'sunafil', ruc);
    fs.mkdirSync(path.join(dir, 'Default'), { recursive: true });
    const prefs = path.join(dir, 'Default', 'Preferences');
    if (!fs.existsSync(prefs)) {
        fs.writeFileSync(prefs, JSON.stringify({
            profile: { name: 'SUNAFIL' },
            bookmark_bar: { show_on_all_tabs: true },
        }));
    }
    return dir;
}

async function ocultarVentana(page) {
    try {
        const cdp = await page.createCDPSession();
        const { windowId } = await cdp.send('Browser.getWindowForTarget');
        await cdp.send('Browser.setWindowBounds', {
            windowId,
            bounds: { windowState: 'normal', left: -32000, top: -32000, width: 1100, height: 700 },
        });
        return { cdp, windowId };
    } catch (_) {
        return null;
    }
}

async function mostrarVentanaNormal(page, ventana, browserName) {
    writeSignal('opening', { browser: browserName });
    await new Promise((resolve) => setTimeout(resolve, 1200));
    try {
        const cdp = ventana?.cdp || await page.createCDPSession();
        const windowId = ventana?.windowId || (await cdp.send('Browser.getWindowForTarget')).windowId;
        await cdp.send('Browser.setWindowBounds', {
            windowId,
            bounds: { windowState: 'normal', left: 48, top: 32, width: 1280, height: 820 },
        });
        await cdp.send('Browser.setWindowBounds', {
            windowId,
            bounds: { windowState: 'maximized' },
        });
        await page.bringToFront();
    } catch (error) {
        console.log('ℹ️ No se pudo restaurar la ventana:', error.message);
        try { await page.bringToFront(); } catch (_) {}
    }
}

/**
 * El formulario de SOL valida con sus propios manejadores de teclado: asignar
 * el value por DOM deja la clave sin registrar y el portal rechaza el ingreso.
 * Puppeteer inyecta los eventos en el renderer, así que no se ve nada en
 * pantalla aunque la ventana esté fuera de cuadro.
 */
async function escribirCampo(page, selector, valor) {
    await page.click(selector, { clickCount: 3 });
    await page.type(selector, valor, { delay: 0 });
}

async function pulsar(page, selector) {
    await page.click(selector);
}

// ─── Archivo de Señal de Sincronización en Tiempo Real con el CRM ─────────────
const signalFile = path.join(__dirname, '..', 'storage', 'framework', `login_ready_sunafil_${ruc}.json`);

function writeSignal(status, extra = {}) {
    try {
        fs.writeFileSync(signalFile, JSON.stringify({
            status,
            ruc,
            timestamp: Date.now(),
            ...extra
        }));
    } catch (_) {}
}

// ─── Localizar navegador predeterminado del sistema operativo ────────────────
function getBrowserInfo() {
    if (process.env.PUPPETEER_EXECUTABLE_PATH && fs.existsSync(process.env.PUPPETEER_EXECUTABLE_PATH)) {
        return { path: process.env.PUPPETEER_EXECUTABLE_PATH, name: 'Personalizado' };
    }

    let browserPath = null;
    let browserName = 'Google Chrome';

    // 1. Detección en milisegundos del navegador predeterminado de Windows en el Registro
    try {
        const stdout = execSync(
            'reg query "HKCU\\Software\\Microsoft\\Windows\\Shell\\Associations\\UrlAssociations\\http\\UserChoice" /v ProgId',
            { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }
        );
        const match = stdout.match(/ProgId\s+REG_SZ\s+(\S+)/i);
        const progId = match ? match[1].trim() : '';

        if (progId) {
            const cmdOut = execSync(
                `reg query "HKCR\\${progId}\\shell\\open\\command" /ve`,
                { encoding: 'utf8', stdio: ['ignore', 'pipe', 'ignore'] }
            );
            const cmdMatch = cmdOut.match(/REG_SZ\s+("([^"]+)"|([^\s]+))/i);
            const exePath = cmdMatch ? (cmdMatch[2] || cmdMatch[3]) : null;
            if (exePath && fs.existsSync(exePath)) {
                browserPath = exePath;
            }
        }
    } catch (_) {}

    // 2. Si no se resolvió por registro, usar lista de candidatos del sistema
    if (!browserPath) {
        const localAppData = process.env.LOCALAPPDATA || '';
        const progFiles = process.env.ProgramFiles || 'C:\\Program Files';
        const progFilesX86 = process.env['ProgramFiles(x86)'] || 'C:\\Program Files (x86)';

        const candidates = [
            path.join(progFiles, 'Google\\Chrome\\Application\\chrome.exe'),
            path.join(progFilesX86, 'Google\\Chrome\\Application\\chrome.exe'),
            path.join(localAppData, 'Google\\Chrome\\Application\\chrome.exe'),
            'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
            'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
            path.join(progFiles, 'Microsoft\\Edge\\Application\\msedge.exe'),
            path.join(progFilesX86, 'Microsoft\\Edge\\Application\\msedge.exe'),
            path.join(localAppData, 'Microsoft\\Edge\\Application\\msedge.exe'),
            'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
            'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
            path.join(progFiles, 'BraveSoftware\\Brave-Browser\\Application\\brave.exe'),
            path.join(localAppData, 'BraveSoftware\\Brave-Browser\\Application\\brave.exe'),
        ];
        for (const p of candidates) {
            if (p && fs.existsSync(p)) {
                browserPath = p;
                break;
            }
        }
    }

    // 3. Determinar nombre amigable del navegador
    if (browserPath) {
        const lower = browserPath.toLowerCase();
        if (lower.includes('chrome')) browserName = 'Google Chrome';
        else if (lower.includes('edge') || lower.includes('msedge')) browserName = 'Microsoft Edge';
        else if (lower.includes('brave')) browserName = 'Brave';
        else browserName = path.basename(browserPath, '.exe');
    }

    return { path: browserPath, name: browserName };
}

// ─── URL del portal Casilla Electrónica SUNAFIL (acceso con Clave SOL) ─────────
const SUNAFIL_LOGIN_URL = 'https://casillaelectronica.sunafil.gob.pe/si.inbox/Login/SUNAT';

(async () => {
    console.log(`🚀 Iniciando sesión SUNAFIL para RUC: ${ruc} / Usuario: ${usuario}`);

    let browser;
    let page;
    let browserInfo = { path: undefined, name: 'Google Chrome' };

    try {
        browserInfo = getBrowserInfo();
        console.log(`🌐 Navegador detectado: ${browserInfo.name} (${browserInfo.path || 'por defecto de puppeteer'})`);
        writeSignal('starting', { browser: browserInfo.name });

        // Opciones de lanzamiento optimizadas para Windows (con ícono nativo de Google Chrome / Edge en la barra de tareas)
        const launchOptions = {
            headless: false,
            defaultViewport: null,
            userDataDir: directorioPerfil(),
            ignoreDefaultArgs: ['--enable-automation'],
            args: [
                '--test-type',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-blink-features=AutomationControlled',
                '--window-position=-32000,-32000',
                '--window-size=1100,700',
                // Fuera de la pantalla, Chrome congela la pestaña y el login se
                // arrastra. Estos tres interruptores la mantienen a full ritmo.
                '--disable-backgrounding-occluded-windows',
                '--disable-renderer-backgrounding',
                '--disable-background-timer-throttling',
            ],
        };

        if (browserInfo.path) {
            launchOptions.executablePath = browserInfo.path;
            if (browserInfo.path.toLowerCase().includes('firefox')) {
                launchOptions.browser = 'firefox';
            }
        }

        cerrarNavegadorAnterior();
        browser = await puppeteer.launch(launchOptions);
        recordarNavegador(browser);

        // Limpiar proceso Node cuando el usuario cierre el navegador
        browser.on('disconnected', () => {
            console.log('ℹ️ Navegador cerrado por el usuario.');
            cleanupPid();
            process.exit(0);
        });

        const pages = await browser.pages();
        page = pages.length > 0 ? pages[0] : await browser.newPage();
        const ventana = await ocultarVentana(page);

        page.on('close', () => {
            cleanupPid();
            try { browser.close(); } catch (_) {}
            process.exit(0);
        });

        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        );

        // Una sesión vieja a medio vencer hacía que el formulario tardara en
        // aparecer y el script la daba por válida, dejando al usuario frente a
        // la pantalla de Clave SOL. Partir de cero es más rápido que adivinar.
        const sesionCdp = await page.createCDPSession();
        await sesionCdp.send('Network.clearBrowserCookies').catch(() => {});

        console.log('📡 Conectando a Casilla Electrónica SUNAFIL (100% fuera de pantalla)...');
        await page.goto(SUNAFIL_LOGIN_URL, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await ocultarVentana(page);

        // Si la página intermedia contiene el script de redirección inmediata a la pasarela SOL, ejecutarlo
        try {
            await page.evaluate(() => {
                if (typeof direc !== 'undefined' && direc) {
                    window.location.replace(direc);
                }
            });
        } catch (_) {}

        console.log('⏳ Esperando formulario de autenticación Clave SOL...');
        const formulario = await page.waitForSelector('#txtRuc, input[name="txtRuc"]', { timeout: 30000, visible: true }).catch(() => null);

        await ocultarVentana(page);

        if (!formulario) {
            console.log('ℹ️ La casilla abrió sin pedir Clave SOL.');
            await mostrarVentanaNormal(page, ventana, browserInfo.name);
            console.log(`✅ Casilla SUNAFIL lista en ${browserInfo.name}.`);
            await new Promise(() => {});
        }

        try {
            if (await page.$('#btnPorRuc')) {
                await pulsar(page, '#btnPorRuc');
            }
        } catch (_) {}

        console.log('✍️  Rellenando credenciales fuera de la pantalla...');

        const selRuc = (await page.$('#txtRuc')) ? '#txtRuc' : 'input[name="txtRuc"]';
        const selUsuario = (await page.$('#txtUsuario')) ? '#txtUsuario' : 'input[name="txtUsuario"]';
        const selClave = (await page.$('#txtContrasena')) ? '#txtContrasena' : 'input[name="txtContrasena"]';

        await escribirCampo(page, selRuc, ruc);
        await escribirCampo(page, selUsuario, usuario);
        await escribirCampo(page, selClave, clave);
        await ocultarVentana(page);

        console.log('🔐 Enviando Clave SOL...');
        const selBoton = (await page.$('#btnAceptar')) ? '#btnAceptar' : 'button[type="submit"]';
        await pulsar(page, selBoton);

        try {
            await page.waitForFunction(() => !document.querySelector('#txtRuc, input[name="txtRuc"]'), { timeout: 30000 });
        } catch (_) {
            const aviso = await page.evaluate(() => {
                const nodo = document.querySelector('.alert, .error, #spanError, [class*="error"]');
                return nodo ? nodo.innerText.trim() : '';
            }).catch(() => '');
            throw new Error(aviso || 'SUNAFIL no aceptó la Clave SOL de este cliente.');
        }

        // La casilla puede demorar en resolver sus redirecciones. Si no llega a
        // tiempo, igual se muestra: la sesión SOL ya está iniciada.
        await page.waitForFunction(() => {
            const href = window.location.href;
            return href.includes('sunafil.gob.pe') || href.includes('casillaelectronica') || document.querySelector('.main-sidebar') !== null;
        }, { timeout: 20000 }).catch(() => {
            console.log('ℹ️ La casilla todavía estaba resolviendo; se muestra igual.');
        });

        await ocultarVentana(page);

        // ─── Teletransportar la ventana a pantalla principal y maximizar ───────
        await mostrarVentanaNormal(page, ventana, browserInfo.name);
        console.log(`🎉 Ventana normal de ${browserInfo.name} en primer plano.`);

        console.log(`✅ Login SUNAFIL completado. ${browserInfo.name} disponible para el usuario.`);

        // Mantener el proceso vivo hasta que el usuario cierre el navegador
        await new Promise(() => {});

    } catch (error) {
        console.error('❌ Error durante la automatización SUNAFIL:', error.message);
        cleanupPid();
        writeSignal('error', { message: error.message, browser: browserInfo.name });

        // Si ocurre un error, teletransportar la ventana a la pantalla para diagnóstico visual
        try {
            if (page) {
                const cdp = await page.createCDPSession();
                const { windowId } = await cdp.send('Browser.getWindowForTarget');
                await cdp.send('Browser.setWindowBounds', {
                    windowId,
                    bounds: { left: 0, top: 0, windowState: 'normal' },
                });
                await page.bringToFront();
                await page.screenshot({ path: 'scripts/sunafil_error.png' });
            }
        } catch (_) {}

        process.exit(1);
    }
})();
