/**
 * scripts/sunat_login.cjs
 *
 * Login en SUNAT Operaciones en Línea (Clave SOL).
 * La ventana nace fuera de la pantalla y ahí se escriben RUC, usuario y clave,
 * así el usuario nunca ve el tecleo. El CRM recibe la señal "opening" y, un
 * segundo después, recién ahí se muestra el módulo ya iniciado.
 *
 * Uso:
 *   node scripts/sunat_login.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL> [URL_DESTINO]
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const puppeteer = require('puppeteer');

// ─── Validar argumentos ───────────────────────────────────────────────────────
const [,, ruc, usuario, clave, destinoArg] = process.argv;

if (!ruc || !usuario || !clave) {
    console.error('❌ Uso: node scripts/sunat_login.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL> [URL_DESTINO]');
    process.exit(1);
}

// ─── Archivo de Control de Proceso (PID) por RUC ─────────────────────────────
const pidFile = path.join(__dirname, '..', 'storage', 'framework', `login_pid_sunat_${ruc}.pid`);
try {
    fs.writeFileSync(pidFile, process.pid.toString());
} catch (_) {}

// El navegador sobrevive al proceso Node, así que su PID se guarda aparte: si
// queda abierto de un acceso anterior, retiene el perfil y el siguiente intento
// se queda colgado al navegar.
const browserPidFile = path.join(__dirname, '..', 'storage', 'framework', `login_browser_sunat_${ruc}.pid`);

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
        // En Windows, verificar si el PID sigue activo antes de llamar a taskkill
        // Si el proceso ya fue cerrado por el usuario, process.kill(pid, 0) lanza excepción instantáneamente (0ms)
        process.kill(pid, 0);
        if (process.platform === 'win32') {
            execSync(`taskkill /F /T /PID ${pid}`, { stdio: 'ignore' });
        } else {
            process.kill(pid, 'SIGKILL');
        }
    } catch (_) {
        // El proceso ya no existe, 0ms consumidos
    }
}

function recordarNavegador(browser) {
    try {
        const pid = browser.process() ? browser.process().pid : null;
        if (pid) {
            fs.writeFileSync(browserPidFile, String(pid));
        }
    } catch (_) {}
}

// ─── Archivo de Señal de Sincronización en Tiempo Real con el CRM ─────────────
const signalFile = path.join(__dirname, '..', 'storage', 'framework', `login_ready_sunat_${ruc}.json`);

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

function destinoSeguro(valor) {
    if (!valor || !valor.startsWith('https://')) return null;
    try {
        const host = new URL(valor).hostname;
        if (host === 'sunat.gob.pe' || host.endsWith('.sunat.gob.pe')) return valor;
    } catch (_) {}
    return null;
}

const destinoUrl = destinoSeguro(destinoArg);

function directorioPerfil() {
    const base = process.env.LOCALAPPDATA
        || process.env.HOME
        || process.env.USERPROFILE
        || path.join(__dirname, '..', 'storage', 'app');
    const dir = path.join(base, 'CrmContable', 'navegador', 'sunat', ruc);
    fs.mkdirSync(path.join(dir, 'Default'), { recursive: true });
    const prefs = path.join(dir, 'Default', 'Preferences');
    if (!fs.existsSync(prefs)) {
        fs.writeFileSync(prefs, JSON.stringify({
            profile: { name: 'SUNAT' },
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

/**
 * Espera a que se complete la redirección del portal con sondeos rápidos de 150ms.
 */
async function esperarEstabilidad(page, limiteMs = 15000) {
    const fin = Date.now() + limiteMs;
    let anterior = '';
    let quietas = 0;

    while (Date.now() < fin) {
        await new Promise((resolve) => setTimeout(resolve, 150));

        const actual = page.url();
        const enPasarela = actual.includes('api-seguridad') || actual.includes('autentica');

        // Si ya aterrizó en la página de destino de SUNAT y no hay formulario pendiente:
        if (!enPasarela) {
            const pendiente = await page.$('#txtRuc, input[name="txtRuc"]').catch(() => null);
            if (!pendiente) {
                return;
            }
        }

        if (actual === anterior) {
            quietas++;
        } else {
            quietas = 0;
            anterior = actual;
        }

        if (quietas >= 2) {
            const pendiente = await page.$('#txtRuc, input[name="txtRuc"]').catch(() => null);
            if (!pendiente) {
                return;
            }
        }
    }
}

/**
 * Completa el formulario de Clave SOL si SUNAT lo muestra y espera a que la
 * cadena OAuth devuelva la sesión al portal. Navegar antes de ese aterrizaje
 * deja al usuario de vuelta en el login.
 */
async function autenticar(page, esperaFormulario = 30000) {
    const inicio = Date.now();

    const formulario = await page
        .waitForSelector('#txtRuc, input[name="txtRuc"]', { timeout: esperaFormulario, visible: true })
        .catch(() => null);

    if (!formulario) {
        return false;
    }

    await ocultarVentana(page);

    try {
        if (await page.$('#btnPorRuc')) {
            await pulsar(page, '#btnPorRuc');
        }
    } catch (_) {}

    console.log('✍️  Rellenando credenciales fuera de la pantalla...');
    await escribirCampo(page, (await page.$('#txtRuc')) ? '#txtRuc' : 'input[name="txtRuc"]', ruc);
    await escribirCampo(page, (await page.$('#txtUsuario')) ? '#txtUsuario' : 'input[name="txtUsuario"]', usuario);
    await escribirCampo(page, (await page.$('#txtContrasena')) ? '#txtContrasena' : 'input[name="txtContrasena"]', clave);
    await ocultarVentana(page);

    console.log('🔐 Enviando Clave SOL...');
    await pulsar(page, (await page.$('#btnAceptar')) ? '#btnAceptar' : 'button[type="submit"]');

    try {
        await page.waitForFunction(() => !document.querySelector('#txtRuc, input[name="txtRuc"]'), { timeout: 30000 });
    } catch (_) {
        const aviso = await page.evaluate(() => {
            const nodo = document.querySelector('.alert, .error, #spanError, [class*="error"]');
            return nodo ? nodo.innerText.trim() : '';
        }).catch(() => '');
        throw new Error(aviso || 'SUNAT no aceptó la Clave SOL de este cliente.');
    }

    await esperarEstabilidad(page, 20000);
    await ocultarVentana(page);
    console.log(`🔓 Sesión SOL lista en ${Date.now() - inicio} ms.`);

    return true;
}

// ─── Localizar navegador predeterminado del sistema operativo ────────────────
function getBrowserInfo() {
    if (process.env.PUPPETEER_EXECUTABLE_PATH && fs.existsSync(process.env.PUPPETEER_EXECUTABLE_PATH)) {
        return { path: process.env.PUPPETEER_EXECUTABLE_PATH, name: 'Personalizado' };
    }

    const localAppData = process.env.LOCALAPPDATA || '';
    const progFiles = process.env.ProgramFiles || 'C:\\Program Files';
    const progFilesX86 = process.env['ProgramFiles(x86)'] || 'C:\\Program Files (x86)';

    // 1. Verificación directa en el sistema de archivos (0.01ms, instantáneo)
    const directCandidates = [
        { path: path.join(progFiles, 'Google\\Chrome\\Application\\chrome.exe'), name: 'Google Chrome' },
        { path: path.join(progFilesX86, 'Google\\Chrome\\Application\\chrome.exe'), name: 'Google Chrome' },
        { path: path.join(localAppData, 'Google\\Chrome\\Application\\chrome.exe'), name: 'Google Chrome' },
        { path: path.join(progFiles, 'Microsoft\\Edge\\Application\\msedge.exe'), name: 'Microsoft Edge' },
        { path: path.join(progFilesX86, 'Microsoft\\Edge\\Application\\msedge.exe'), name: 'Microsoft Edge' },
        { path: path.join(localAppData, 'Microsoft\\Edge\\Application\\msedge.exe'), name: 'Microsoft Edge' },
        { path: path.join(progFiles, 'BraveSoftware\\Brave-Browser\\Application\\brave.exe'), name: 'Brave' },
        { path: path.join(localAppData, 'BraveSoftware\\Brave-Browser\\Application\\brave.exe'), name: 'Brave' },
    ];

    for (const c of directCandidates) {
        if (c.path && fs.existsSync(c.path)) {
            return c;
        }
    }

    // 2. Si no se encontró en rutas fijas, consultar el Registro de Windows
    let browserPath = null;
    let browserName = 'Google Chrome';

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

    if (browserPath) {
        const lower = browserPath.toLowerCase();
        if (lower.includes('chrome')) browserName = 'Google Chrome';
        else if (lower.includes('edge') || lower.includes('msedge')) browserName = 'Microsoft Edge';
        else if (lower.includes('brave')) browserName = 'Brave';
        else browserName = path.basename(browserPath, '.exe');
    }

    return { path: browserPath, name: browserName };
}

// ─── URL del portal SOL (flujo OAuth2 → formulario de login) ──────────────────
const SUNAT_LOGIN_URL = 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm';

(async () => {
    console.log(`🚀 Iniciando sesión SUNAT para RUC: ${ruc} / Usuario: ${usuario}`);

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
                '--disable-features=Translate,OptimizationHints,MediaRouter',
                '--disable-component-update',
                '--disable-default-apps',
                '--no-pings',
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
        // aparecer, el script la daba por válida y el usuario terminaba frente
        // a la pantalla de Clave SOL. Partir de cero es más rápido que adivinar
        // si la cookie todavía sirve.
        const sesionCdp = await page.createCDPSession();
        await sesionCdp.send('Network.clearBrowserCookies').catch(() => {});

        // Se entra directo al módulo pedido: SUNAT rebota a su pantalla de
        // Clave SOL arrastrando el destino y, al autenticar, abre justo ahí.
        // Iniciar sesión primero y navegar después reinicia el circuito OAuth
        // y devuelve al usuario al login.
        const urlEntrada = destinoUrl || SUNAT_LOGIN_URL;
        console.log(`📡 Entrando a ${urlEntrada} (fuera de la pantalla)...`);
        await page.goto(urlEntrada, { waitUntil: 'domcontentloaded', timeout: 30000 });
        await ocultarVentana(page);

        await autenticar(page);

        // A veces el portal se queda en su pasarela con el código OAuth en la
        // barra y no termina de soltar el módulo. Con la sesión ya creada,
        // volver a pedir la URL entra derecho.
        for (let intento = 0; intento < 2; intento++) {
            const enPasarela = page.url().includes('api-seguridad');
            const pideClave = Boolean(await page.$('#txtRuc, input[name="txtRuc"]'));

            if (!enPasarela && !pideClave) {
                break;
            }

            console.log('🔁 Reintentando la entrada al módulo...');
            await page.goto(urlEntrada, { waitUntil: 'domcontentloaded', timeout: 25000 }).catch(() => {});
            await autenticar(page, 12000);
            await esperarEstabilidad(page, 15000);
        }

        console.log(`📍 Módulo en pantalla: ${page.url().slice(0, 140)}`);
        await ocultarVentana(page);

        // ─── Teletransportar la ventana a pantalla principal y maximizar ───────
        await mostrarVentanaNormal(page, ventana, browserInfo.name);
        console.log(`🎉 Ventana normal de ${browserInfo.name} en primer plano.`);

        console.log(`✅ Login completado. ${browserInfo.name} disponible para el usuario.`);

        // Mantener el proceso vivo hasta que el usuario cierre el navegador
        await new Promise(() => {});

    } catch (error) {
        console.error('❌ Error durante la automatización:', error.message);
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
                await page.screenshot({ path: 'scripts/sunat_error.png' });
            }
        } catch (_) {}

        process.exit(1);
    }
})();
