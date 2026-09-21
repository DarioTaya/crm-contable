/**
 * scripts/sunafil_login.cjs
 *
 * Automatización de login en la Casilla Electrónica de SUNAFIL (Autenticación Clave SOL).
 * - Lanza Chrome fuera de la pantalla (--window-position=-32000,-32000) para un tipeo 100% invisible.
 * - Suprime infobars con --test-type y elimina banner con ignoreDefaultArgs: ['--enable-automation'].
 * - No incluye flags de sandbox incompatibles en Windows.
 * - Al completar el login, teletransporta la ventana a x:0, y:0, la maximiza con CDP y la trae al frente.
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

function cleanupPid() {
    try {
        if (fs.existsSync(pidFile)) fs.unlinkSync(pidFile);
    } catch (_) {}
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
            ignoreDefaultArgs: ['--enable-automation'],
            args: [
                '--test-type',
                '--no-first-run',
                '--no-default-browser-check',
                '--window-position=-32000,-32000',
                '--window-size=1280,800',
                '--disable-blink-features=AutomationControlled',
            ],
        };

        if (browserInfo.path) {
            launchOptions.executablePath = browserInfo.path;
        }

        browser = await puppeteer.launch(launchOptions);

        // Limpiar proceso Node cuando el usuario cierre el navegador
        browser.on('disconnected', () => {
            console.log('ℹ️ Navegador cerrado por el usuario.');
            cleanupPid();
            process.exit(0);
        });

        const pages = await browser.pages();
        page = pages.length > 0 ? pages[0] : await browser.newPage();

        page.on('close', () => {
            cleanupPid();
            try { browser.close(); } catch (_) {}
            process.exit(0);
        });

        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        );

        console.log('📡 Conectando a Casilla Electrónica SUNAFIL (100% fuera de pantalla)...');
        await page.goto(SUNAFIL_LOGIN_URL, { waitUntil: 'domcontentloaded', timeout: 30000 });

        // Si la página intermedia contiene el script de redirección inmediata a la pasarela SOL, ejecutarlo
        try {
            await page.evaluate(() => {
                if (typeof direc !== 'undefined' && direc) {
                    window.location.replace(direc);
                }
            });
        } catch (_) {}

        console.log('⏳ Esperando formulario de autenticación Clave SOL...');
        await page.waitForSelector('#txtRuc, input[name="txtRuc"]', { timeout: 35000 });

        // Asegurar que la opción RUC esté seleccionada
        try {
            const btnPorRuc = await page.$('#btnPorRuc');
            if (btnPorRuc) {
                await btnPorRuc.click();
            }
        } catch (_) {}

        // ─── Rellenar credenciales fuera de pantalla a máxima velocidad ───────
        console.log('✍️  Rellenando credenciales...');

        const selRuc = (await page.$('#txtRuc')) ? '#txtRuc' : 'input[name="txtRuc"]';
        const selUsuario = (await page.$('#txtUsuario')) ? '#txtUsuario' : 'input[name="txtUsuario"]';
        const selClave = (await page.$('#txtContrasena')) ? '#txtContrasena' : 'input[name="txtContrasena"]';

        await page.click(selRuc, { clickCount: 3 });
        await page.type(selRuc, ruc, { delay: 8 });

        await page.click(selUsuario, { clickCount: 3 });
        await page.type(selUsuario, usuario, { delay: 8 });

        await page.click(selClave, { clickCount: 3 });
        await page.type(selClave, clave, { delay: 8 });

        // ─── Hacer clic en el botón de ingresar y esperar navegación a SUNAFIL ─
        console.log('🔐 Haciendo clic en "Entrar"...');
        const selBoton = (await page.$('#btnAceptar')) ? '#btnAceptar' : 'button[type="button"].btn-block, button[type="submit"]';

        await Promise.allSettled([
            page.waitForFunction(() => {
                const href = window.location.href;
                return href.includes('sunafil.gob.pe') || href.includes('casillaelectronica') || document.querySelector('.main-sidebar') !== null;
            }, { timeout: 15000 }),
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 15000 }),
            page.click(selBoton),
        ]);

        // ─── Teletransportar la ventana a pantalla principal y maximizar ───────
        try {
            // Notificar al CRM que la sesión está lista antes de teletransportar la ventana.
            // Esto permite que el HUD modal del CRM se cierre suavemente y desaparezca por completo
            // antes de que la ventana aparezca en pantalla.
            writeSignal('ready', { browser: browserInfo.name });

            // Buffer de espera para que la notificación en el CRM desaparezca por completo
            await new Promise(r => setTimeout(r, 850));

            const cdpSession = await page.target().createCDPSession();
            const { windowId } = await cdpSession.send('Browser.getWindowForTarget');

            // Paso 1: Restablecer coordenadas a x: 0, y: 0 en estado normal
            await cdpSession.send('Browser.setWindowBounds', {
                windowId,
                bounds: {
                    left: 0,
                    top: 0,
                    windowState: 'normal',
                },
            });

            // Paso 2: Maximizar ventana en pantalla
            await cdpSession.send('Browser.setWindowBounds', {
                windowId,
                bounds: {
                    windowState: 'maximized',
                },
            });

            // Paso 3: Traer la ventana al frente
            await page.bringToFront();
            console.log(`🎉 Ventana teletransportada a x:0, y:0 y maximizada en primer plano (${browserInfo.name}).`);
        } catch (cdpErr) {
            console.log('ℹ️ Aviso al teletransportar ventana:', cdpErr.message);
            writeSignal('ready', { browser: browserInfo.name });
        }

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
                const cdp = await page.target().createCDPSession();
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
