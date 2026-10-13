/**
 * scripts/sunat_login.cjs
 *
 * Login automatizado en SUNAT Operaciones en Línea (Clave SOL).
 * - Arquitectura 100% invisible: la ventana arranca fuera de pantalla (-32000, -32000).
 *   El usuario nunca ve la pantalla de login ni el tipeo de RUC, usuario o contraseña.
 *   La ventana aparece maximizada en primer plano una vez que la sesión está iniciada.
 * - Sin modo --app que bloquee ventanas emergentes (el calendario de facturas y recibos
 *   por honorarios se abre de forma nativa en su tamaño estándar sin pantallas en blanco).
 * - Control de concurrencia y limpieza de procesos y lockfiles para evitar el error:
 *   "The browser is already running...".
 * - Detección dinámica del perfil de Google Chrome del sistema en cualquier máquina
 *   (sea la máquina del administrador, un asistente o un cliente que adquiera el CRM).
 * - Sin infobar negro de advertencia de automatización.
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

// ─── Archivos de Control de Proceso (PID) por RUC ─────────────────────────────
const pidFile = path.join(__dirname, '..', 'storage', 'framework', `login_pid_sunat_${ruc}.pid`);
const browserPidFile = path.join(__dirname, '..', 'storage', 'framework', `login_browser_sunat_${ruc}.pid`);
const signalFile = path.join(__dirname, '..', 'storage', 'framework', `login_ready_sunat_${ruc}.json`);

try {
    fs.writeFileSync(pidFile, process.pid.toString());
} catch (_) {}

function cleanupPid() {
    try {
        if (fs.existsSync(pidFile)) fs.unlinkSync(pidFile);
    } catch (_) {}
    try {
        if (fs.existsSync(browserPidFile)) fs.unlinkSync(browserPidFile);
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

function writeSignal(status, extra = {}) {
    try {
        fs.writeFileSync(signalFile, JSON.stringify({
            status,
            ruc,
            timestamp: Date.now(),
            ...extra,
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
const SUNAT_LOGIN_URL = 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm';
const urlEntrada = destinoUrl || SUNAT_LOGIN_URL;

// ─── Detección Dinámica del Perfil Activo de Chrome en el Equipo ───────────────
function detectarUsuarioChromeLocal() {
    try {
        const localAppData = process.env.LOCALAPPDATA || '';
        const localStatePath = path.join(localAppData, 'Google', 'Chrome', 'User Data', 'Local State');
        if (!fs.existsSync(localStatePath)) {
            return null;
        }
        const state = JSON.parse(fs.readFileSync(localStatePath, 'utf8'));
        const cache = state?.profile?.info_cache;
        if (!cache || typeof cache !== 'object') {
            return null;
        }

        let mejorPerfil = null;
        let maxActiveTime = -1;

        for (const [key, p] of Object.entries(cache)) {
            const activeTime = p.active_time || 0;
            // Priorizar perfiles con cuenta de Google consentida o mayor actividad
            if (p.is_consented_primary_account || (p.user_name && p.user_name.includes('@')) || activeTime > maxActiveTime) {
                maxActiveTime = activeTime;
                mejorPerfil = {
                    name: p.name || p.gaia_given_name || 'Contador',
                    gaia_name: p.gaia_name || p.name || 'Contador',
                    gaia_given_name: p.gaia_given_name || p.name || 'Contador',
                    user_name: p.user_name || '',
                    avatar_icon: p.avatar_icon || 'chrome://theme/IDR_PROFILE_AVATAR_26',
                };
            }
        }
        return mejorPerfil;
    } catch (_) {
        return null;
    }
}

// ─── Gestión de Perfil y Limpieza de Concurrencia ─────────────────────────────
function directorioPerfil() {
    const base = process.env.LOCALAPPDATA
        || process.env.HOME
        || process.env.USERPROFILE
        || path.join(__dirname, '..', 'storage', 'app');
    const dir = path.join(base, 'CrmContable', 'navegador', 'sunat', ruc);
    fs.mkdirSync(path.join(dir, 'Default'), { recursive: true });

    // Detectar automáticamente el usuario activo de Google Chrome en ESTA máquina
    const usuarioDetectado = detectarUsuarioChromeLocal() || {
        name: 'Contador',
        gaia_name: 'Estudio Contable',
        gaia_given_name: 'Contador',
        user_name: '',
        avatar_icon: 'chrome://theme/IDR_PROFILE_AVATAR_26',
    };

    // Configurar preferencias de usuario con la identidad detectada
    const prefsPath = path.join(dir, 'Default', 'Preferences');
    try {
        const prefs = {
            profile: {
                name: usuarioDetectado.name,
                gaia_name: usuarioDetectado.gaia_name,
                gaia_given_name: usuarioDetectado.gaia_given_name,
                user_name: usuarioDetectado.user_name,
                avatar_icon: usuarioDetectado.avatar_icon,
            },
            bookmark_bar: { show_on_all_tabs: false },
            browser: { show_home_button: false },
        };
        fs.writeFileSync(prefsPath, JSON.stringify(prefs));
    } catch (_) {}

    // Configurar Local State con la identidad detectada
    const localStatePath = path.join(dir, 'Local State');
    try {
        const localState = {
            profile: {
                info_cache: {
                    Default: {
                        name: usuarioDetectado.name,
                        gaia_name: usuarioDetectado.gaia_name,
                        gaia_given_name: usuarioDetectado.gaia_given_name,
                        user_name: usuarioDetectado.user_name,
                        avatar_icon: usuarioDetectado.avatar_icon,
                        is_using_default_avatar: false,
                        is_using_default_name: false,
                    },
                },
            },
        };
        fs.writeFileSync(localStatePath, JSON.stringify(localState));
    } catch (_) {}

    return dir;
}

function limpiarPerfilYProcesos(dir) {
    // 1. Terminar proceso anterior por PID registrado
    if (fs.existsSync(browserPidFile)) {
        try {
            const pid = parseInt(fs.readFileSync(browserPidFile, 'utf8'), 10);
            if (pid && !isNaN(pid)) {
                if (process.platform === 'win32') {
                    execSync(`taskkill /F /T /PID ${pid}`, { stdio: 'ignore' });
                } else {
                    process.kill(pid, 'SIGKILL');
                }
            }
        } catch (_) {}
        try { fs.unlinkSync(browserPidFile); } catch (_) {}
    }

    // 2. En Windows, matar cualquier instancia huérfana de Chrome asociada a este RUC
    if (process.platform === 'win32') {
        try {
            execSync(`wmic process where "name='chrome.exe' and commandline like '%sunat\\\\${ruc}%'" call terminate`, { stdio: 'ignore' });
        } catch (_) {}
    }

    // 3. Eliminar archivos de bloqueo (lockfiles) para evitar que Puppeteer falle
    const lockFiles = ['lockfile', 'SingletonLock', 'SingletonCookie', 'SingletonSocket', 'DevToolsActivePort'];
    for (const file of lockFiles) {
        try {
            const filePath = path.join(dir, file);
            if (fs.existsSync(filePath)) {
                fs.unlinkSync(filePath);
            }
        } catch (_) {}
    }
}

// ─── Control de Ventana por CDP (Ocultar / Mostrar) ───────────────────────────
async function ocultarVentana(page) {
    try {
        const cdp = await page.createCDPSession();
        const { windowId } = await cdp.send('Browser.getWindowForTarget');
        await cdp.send('Browser.setWindowBounds', {
            windowId,
            bounds: { windowState: 'normal', left: -32000, top: -32000, width: 1280, height: 800 },
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

        // Paso 1: Restablecer coordenadas a x: 0, y: 0 en estado normal
        await cdp.send('Browser.setWindowBounds', {
            windowId,
            bounds: { left: 0, top: 0, windowState: 'normal' },
        });

        // Paso 2: Maximizar en pantalla principal
        await cdp.send('Browser.setWindowBounds', {
            windowId,
            bounds: { windowState: 'maximized' },
        });

        await page.bringToFront();
    } catch (error) {
        console.log('ℹ️ Aviso al restaurar ventana:', error.message);
        try { await page.bringToFront(); } catch (_) {}
    }
}

// ─── Autenticación en Pasarela SOL ───────────────────────────────────────────
async function autenticar(page, esperaFormulario = 25000) {
    const inicio = Date.now();

    const formulario = await page
        .waitForSelector('#txtRuc, input[name="txtRuc"]', { timeout: esperaFormulario, visible: true })
        .catch(() => null);

    if (!formulario) {
        return false;
    }

    try {
        if (await page.$('#btnPorRuc')) {
            await page.click('#btnPorRuc');
        }
    } catch (_) {}

    console.log('✍️  Rellenando credenciales fuera de la pantalla (100% invisible)...');
    // Escritura con eventos nativos de teclado vía CDP (rápido, sin demoras y 100% compatible con los listeners de SUNAT)
    await page.click('#txtRuc, input[name="txtRuc"]', { clickCount: 3 });
    await page.type('#txtRuc, input[name="txtRuc"]', ruc, { delay: 0 });

    await page.click('#txtUsuario, input[name="txtUsuario"]', { clickCount: 3 });
    await page.type('#txtUsuario, input[name="txtUsuario"]', usuario, { delay: 0 });

    await page.click('#txtContrasena, input[name="txtContrasena"]', { clickCount: 3 });
    await page.type('#txtContrasena, input[name="txtContrasena"]', clave, { delay: 0 });

    console.log('🔐 Enviando Clave SOL...');
    const boton = (await page.$('#btnAceptar')) ? '#btnAceptar' : 'button[type="submit"]';
    await page.click(boton);

    try {
        await page.waitForFunction(() => !document.querySelector('#txtRuc, input[name="txtRuc"]'), { timeout: 25000 });
    } catch (_) {
        const aviso = await page.evaluate(() => {
            const nodo = document.querySelector('.alert, .error, #spanError, [class*="error"]');
            return nodo ? nodo.innerText.trim() : '';
        }).catch(() => '');
        throw new Error(aviso || 'SUNAT no aceptó la Clave SOL de este cliente.');
    }

    console.log(`🔓 Sesión SOL lista en ${Date.now() - inicio} ms.`);
    return true;
}

// ─── Localizar Navegador en el Sistema Operativo ─────────────────────────────
function getBrowserInfo() {
    if (process.env.PUPPETEER_EXECUTABLE_PATH && fs.existsSync(process.env.PUPPETEER_EXECUTABLE_PATH)) {
        return { path: process.env.PUPPETEER_EXECUTABLE_PATH, name: 'Personalizado' };
    }

    const localAppData = process.env.LOCALAPPDATA || '';
    const progFiles = process.env.ProgramFiles || 'C:\\Program Files';
    const progFilesX86 = process.env['ProgramFiles(x86)'] || 'C:\\Program Files (x86)';

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

// ─── Proceso Principal ────────────────────────────────────────────────────────
(async () => {
    console.log(`🚀 Iniciando sesión SUNAT para RUC: ${ruc} / Usuario: ${usuario}`);

    let browser;
    let page;
    let browserInfo = { path: undefined, name: 'Google Chrome' };

    try {
        browserInfo = getBrowserInfo();
        console.log(`🌐 Navegador detectado: ${browserInfo.name} (${browserInfo.path || 'por defecto de puppeteer'})`);
        writeSignal('starting', { browser: browserInfo.name });

        const dirPerfil = directorioPerfil();
        limpiarPerfilYProcesos(dirPerfil);

        // Opciones de lanzamiento 100% invisibles fuera de pantalla:
        // - Inicia en coordenadas -32000, -32000 sin --start-maximized inicial.
        // - El usuario nunca ve la pantalla de login ni el llenado de credenciales.
        // - Sin modo --app para permitir popups (calendario de SUNAT, reportes).
        const launchOptions = {
            headless: false,
            defaultViewport: null,
            userDataDir: dirPerfil,
            ignoreDefaultArgs: ['--enable-automation'],
            args: [
                '--window-position=-32000,-32000',
                '--no-first-run',
                '--no-default-browser-check',
                '--disable-backgrounding-occluded-windows',
                '--disable-renderer-backgrounding',
                '--disable-background-timer-throttling',
                '--disable-features=Translate,OptimizationHints,MediaRouter,TabGroups,TabOrganization',
                '--no-pings',
            ],
        };

        if (browserInfo.path) {
            launchOptions.executablePath = browserInfo.path;
            if (browserInfo.path.toLowerCase().includes('firefox')) {
                launchOptions.browser = 'firefox';
            }
        }

        browser = await puppeteer.launch(launchOptions);
        recordarNavegador(browser);

        // Sin intervención de CDP en popups para permitir que Chrome gestione
        // el calendario de SUNAT de forma 100% nativa sin parpadeos de pantalla.

        browser.on('disconnected', () => {
            console.log('ℹ️ Navegador cerrado por el usuario.');
            cleanupPid();
            process.exit(0);
        });

        const pages = await browser.pages();
        page = pages.length > 0 ? pages[0] : await browser.newPage();

        // Ocultar detección de automatización limpiamente
        await page.evaluateOnNewDocument(() => {
            try {
                Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
            } catch (_) {}
        });

        const ventana = await ocultarVentana(page);

        const inicioAutenticacion = Date.now();
        console.log(`📡 Conectando a ${urlEntrada} fuera de pantalla...`);
        await page.goto(urlEntrada, { waitUntil: 'domcontentloaded', timeout: 30000 });

        console.log(`⏳ Esperando formulario de autenticación SOL...`);
        await autenticar(page);

        console.log(`📍 Módulo en pantalla: ${page.url().slice(0, 140)}`);

        // Teletransportar inmediatamente la ventana maximizada a primer plano una vez autenticado
        await mostrarVentanaNormal(page, ventana, browserInfo.name);
        console.log(`🎉 Ventana de ${browserInfo.name} en primer plano.`);
        console.log(`✅ Login completado en ${Date.now() - inicioAutenticacion} ms.`);
        writeSignal('ready', { browser: browserInfo.name });

        // Mantener el proceso Node activo mientras el usuario utiliza el navegador libremente
        await new Promise(() => {});

    } catch (error) {
        console.error('❌ Error durante la automatización:', error.message);
        cleanupPid();
        writeSignal('error', { message: error.message, browser: browserInfo.name });

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
