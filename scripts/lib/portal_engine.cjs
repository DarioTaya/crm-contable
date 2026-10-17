/**
 * scripts/lib/portal_engine.cjs
 *
 * Motor centralizado de autenticación 1-Clic para portales estatales (SUNAT SOL y SUNAFIL).
 *
 * Principios de ingeniería aplicados:
 * 1. Velocidad extrema (2.5s – 3.5s):
 *    - Cero llamadas a `wmic` (que bloqueaban 2-3s por clic en Windows).
 *    - Verificación instantánea de procesos vivos vía syscall `process.kill(pid, 0)` (< 0.05ms).
 *    - Perfil de navegador unificado y pre-calentado por portal (`sunat_portal` / `sunafil_portal`),
 *      conservando en disco el caché HTTP de todos los scripts/CSS/fuentes de SUNAT y el caché GPU,
 *      pero limpiando las cookies de sesión (`Network.clearBrowserCookies`) en 1ms antes de cada ingreso.
 * 2. Cero conflictos entre empresas o sesiones antiguas (1 hora después o cambio de RUC):
 *    - Registro global de sesión activa por portal (`active_session_sunat.json` / `active_session_sunafil.json`)
 *      más control por RUC. Si quedó una ventana abierta de otra empresa, se cierra limpiamente antes de abrir la nueva.
 * 3. Cero parpadeo en calendarios/popups de SUNAT (ej. Consultar Recibos por Honorarios):
 *    - Sin flags experimentales que alteren el compositor gráfico de Windows DWM.
 *    - Restauración geométrica completa (`left: 60, top: 40, width: 1280, height: 820` -> `maximized`).
 *    - Desconexión total de CDP (`browser.disconnect()`) apenas el portal está en primer plano:
 *      el navegador queda 100% nativo, sin `Target.setAutoAttach`, sin `waitForDebuggerOnStart`
 *      ni hooks de inspección mientras el contador trabaja en SUNAT o SUNAFIL.
 * 4. Respeto al navegador predeterminado del sistema operativo (Chrome, Edge, Brave) e identidad real del usuario.
 */

const fs = require('fs');
const path = require('path');
const { execSync } = require('child_process');
const puppeteer = require('puppeteer');

const FRAMEWORK_DIR = path.join(__dirname, '..', '..', 'storage', 'framework');
const BROWSER_CACHE_FILE = path.join(FRAMEWORK_DIR, 'browser_detected_cache.json');
const BROWSER_CACHE_TTL_MS = 120 * 1000; // 2 minutos

// ─── Utilidades de Procesos y Archivos Atómicos ──────────────────────────────

function ensureFrameworkDir() {
    try {
        if (!fs.existsSync(FRAMEWORK_DIR)) {
            fs.mkdirSync(FRAMEWORK_DIR, { recursive: true });
        }
    } catch (_) {}
}

/**
 * Escribe un archivo JSON de forma atómica (write tmp + rename) para evitar que PHP
 * lea un archivo truncado o a medio escribir en Windows.
 */
function writeJsonAtomic(filePath, data) {
    ensureFrameworkDir();
    const tmpPath = `${filePath}.${process.pid}.${Date.now()}.tmp`;
    try {
        fs.writeFileSync(tmpPath, JSON.stringify(data), 'utf8');
        fs.renameSync(tmpPath, filePath);
    } catch (_) {
        try {
            fs.writeFileSync(filePath, JSON.stringify(data), 'utf8');
        } catch (__) {}
        try {
            if (fs.existsSync(tmpPath)) fs.unlinkSync(tmpPath);
        } catch (__) {}
    }
}

/**
 * Verifica si un PID sigue activo en el sistema operativo en ~0.01ms (sin abrir subshells).
 */
function isProcessAlive(pid) {
    const num = Number(pid);
    if (!num || isNaN(num) || num <= 0 || num === process.pid) {
        return false;
    }
    try {
        process.kill(num, 0);
        return true;
    } catch (err) {
        return Boolean(err && err.code === 'EPERM');
    }
}

/**
 * Termina un árbol de procesos únicamente si el PID sigue realmente activo.
 */
function terminateProcessTree(pid) {
    const num = Number(pid);
    if (!isProcessAlive(num)) {
        return;
    }
    try {
        if (process.platform === 'win32') {
            execSync(`taskkill /F /T /PID ${num}`, { stdio: 'ignore', timeout: 3000 });
        } else {
            process.kill(num, 'SIGKILL');
        }
    } catch (_) {}
}

// ─── Detección del Navegador Predeterminado del Sistema Operativo ────────────

function resolveCandidatesByVendor() {
    const localAppData = process.env.LOCALAPPDATA || '';
    const progFiles = process.env.ProgramFiles || 'C:\\Program Files';
    const progFilesX86 = process.env['ProgramFiles(x86)'] || 'C:\\Program Files (x86)';

    return {
        chrome: [
            { path: path.join(progFiles, 'Google\\Chrome\\Application\\chrome.exe'), name: 'Google Chrome', family: 'chrome' },
            { path: path.join(progFilesX86, 'Google\\Chrome\\Application\\chrome.exe'), name: 'Google Chrome', family: 'chrome' },
            { path: path.join(localAppData, 'Google\\Chrome\\Application\\chrome.exe'), name: 'Google Chrome', family: 'chrome' },
        ],
        edge: [
            { path: path.join(progFilesX86, 'Microsoft\\Edge\\Application\\msedge.exe'), name: 'Microsoft Edge', family: 'edge' },
            { path: path.join(progFiles, 'Microsoft\\Edge\\Application\\msedge.exe'), name: 'Microsoft Edge', family: 'edge' },
            { path: path.join(localAppData, 'Microsoft\\Edge\\Application\\msedge.exe'), name: 'Microsoft Edge', family: 'edge' },
        ],
        brave: [
            { path: path.join(progFiles, 'BraveSoftware\\Brave-Browser\\Application\\brave.exe'), name: 'Brave', family: 'brave' },
            { path: path.join(progFilesX86, 'BraveSoftware\\Brave-Browser\\Application\\brave.exe'), name: 'Brave', family: 'brave' },
            { path: path.join(localAppData, 'BraveSoftware\\Brave-Browser\\Application\\brave.exe'), name: 'Brave', family: 'brave' },
        ],
    };
}

function firstExisting(list) {
    for (const item of list) {
        if (item.path && fs.existsSync(item.path)) {
            return item;
        }
    }
    return null;
}

/**
 * Detecta el navegador predeterminado configurado por el usuario en Windows
 * (Chrome, Microsoft Edge, Brave, etc.), con caché en disco para latencia ~0ms.
 */
function getBrowserInfo() {
    if (process.env.PUPPETEER_EXECUTABLE_PATH && fs.existsSync(process.env.PUPPETEER_EXECUTABLE_PATH)) {
        const customPath = process.env.PUPPETEER_EXECUTABLE_PATH;
        const lower = customPath.toLowerCase();
        const family = lower.includes('edge') ? 'edge' : (lower.includes('brave') ? 'brave' : 'chrome');
        return { path: customPath, name: 'Personalizado', family };
    }

    try {
        if (fs.existsSync(BROWSER_CACHE_FILE)) {
            const cached = JSON.parse(fs.readFileSync(BROWSER_CACHE_FILE, 'utf8'));
            if (
                cached
                && cached.timestamp
                && (Date.now() - cached.timestamp) < BROWSER_CACHE_TTL_MS
                && cached.path
                && fs.existsSync(cached.path)
            ) {
                return { path: cached.path, name: cached.name, family: cached.family || 'chrome' };
            }
        }
    } catch (_) {}

    const vendors = resolveCandidatesByVendor();
    let detected = null;

    // 1. Consultar primero el navegador predeterminado del usuario en el Registro de Windows
    if (process.platform === 'win32') {
        const regKeys = [
            'HKCU\\Software\\Microsoft\\Windows\\Shell\\Associations\\UrlAssociations\\https\\UserChoice',
            'HKCU\\Software\\Microsoft\\Windows\\Shell\\Associations\\UrlAssociations\\http\\UserChoice',
        ];

        for (const regKey of regKeys) {
            if (detected) break;
            try {
                const stdout = execSync(`reg query "${regKey}" /v ProgId`, {
                    encoding: 'utf8',
                    stdio: ['ignore', 'pipe', 'ignore'],
                    timeout: 1200,
                });
                const match = stdout.match(/ProgId\s+REG_SZ\s+(\S+)/i);
                const progId = match ? match[1].trim() : '';
                if (!progId) continue;

                const lowerProg = progId.toLowerCase();
                if (lowerProg.includes('chrome')) {
                    detected = firstExisting(vendors.chrome);
                } else if (lowerProg.includes('msedge') || lowerProg.includes('edge')) {
                    detected = firstExisting(vendors.edge);
                } else if (lowerProg.includes('brave')) {
                    detected = firstExisting(vendors.brave);
                } else {
                    const cmdOut = execSync(`reg query "HKCR\\${progId}\\shell\\open\\command" /ve`, {
                        encoding: 'utf8',
                        stdio: ['ignore', 'pipe', 'ignore'],
                        timeout: 1200,
                    });
                    const cmdMatch = cmdOut.match(/REG_SZ\s+("([^"]+)"|([^\s]+))/i);
                    const exePath = cmdMatch ? (cmdMatch[2] || cmdMatch[3]) : null;
                    if (exePath && fs.existsSync(exePath)) {
                        const lowerExe = exePath.toLowerCase();
                        if (lowerExe.includes('chrome')) {
                            detected = { path: exePath, name: 'Google Chrome', family: 'chrome' };
                        } else if (lowerExe.includes('msedge') || lowerExe.includes('edge')) {
                            detected = { path: exePath, name: 'Microsoft Edge', family: 'edge' };
                        } else if (lowerExe.includes('brave')) {
                            detected = { path: exePath, name: 'Brave', family: 'brave' };
                        }
                    }
                }
            } catch (_) {}
        }
    }

    // 2. Respaldo ordenado si el registro no devolvió un navegador compatible con CDP
    if (!detected) {
        detected = firstExisting(vendors.chrome)
            || firstExisting(vendors.edge)
            || firstExisting(vendors.brave)
            || { path: undefined, name: 'Google Chrome', family: 'chrome' };
    }

    if (detected.path) {
        writeJsonAtomic(BROWSER_CACHE_FILE, {
            path: detected.path,
            name: detected.name,
            family: detected.family,
            timestamp: Date.now(),
        });
    }

    return detected;
}

// ─── Sincronización de Identidad del Perfil Local (Sin aspecto de Invitado) ──

function detectarIdentidadNavegadorLocal(family = 'chrome') {
    try {
        const localAppData = process.env.LOCALAPPDATA || '';
        const baseUserData = family === 'edge'
            ? path.join(localAppData, 'Microsoft', 'Edge', 'User Data')
            : (family === 'brave'
                ? path.join(localAppData, 'BraveSoftware', 'Brave-Browser', 'User Data')
                : path.join(localAppData, 'Google', 'Chrome', 'User Data'));

        const localStatePath = path.join(baseUserData, 'Local State');
        if (!fs.existsSync(localStatePath)) {
            return null;
        }

        const state = JSON.parse(fs.readFileSync(localStatePath, 'utf8'));
        const cache = state?.profile?.info_cache;
        if (!cache || typeof cache !== 'object') {
            return null;
        }

        let mejorKey = null;
        let mejorPerfil = null;
        let mejorScore = -1;

        for (const [key, p] of Object.entries(cache)) {
            if (!p || typeof p !== 'object') continue;
            const activeTime = Number(p.active_time || 0);
            const bonus = (p.is_consented_primary_account ? 1e12 : 0)
                + (p.user_name && String(p.user_name).includes('@') ? 5e11 : 0);
            const score = activeTime + bonus;

            if (score > mejorScore) {
                mejorScore = score;
                mejorKey = key;
                mejorPerfil = p;
            }
        }

        if (!mejorPerfil) {
            return null;
        }

        const sourceProfileDir = path.join(baseUserData, mejorKey);
        const pictureFileName = mejorPerfil.gaia_picture_file_name || 'Google Profile Picture.png';
        const pictureSourcePath = path.join(sourceProfileDir, pictureFileName);

        return {
            rawInfoCache: mejorPerfil,
            name: mejorPerfil.name || mejorPerfil.gaia_given_name || 'Contador',
            gaia_name: mejorPerfil.gaia_name || mejorPerfil.name || 'Estudio Contable',
            gaia_given_name: mejorPerfil.gaia_given_name || mejorPerfil.name || 'Contador',
            user_name: mejorPerfil.user_name || '',
            avatar_icon: mejorPerfil.avatar_icon || 'chrome://theme/IDR_PROFILE_AVATAR_26',
            pictureSourcePath: fs.existsSync(pictureSourcePath) ? pictureSourcePath : null,
            pictureFileName,
        };
    } catch (_) {
        return null;
    }
}

/**
 * Configura el directorio de perfil sin sobrescribir destructivamente las claves internas
 * de Chrome/Edge, preservando el caché HTTP/GPU y aplicando el nombre, color y foto del usuario.
 */
function configurarIdentidadEnPerfil(dir, family = 'chrome') {
    const defaultDir = path.join(dir, 'Default');
    fs.mkdirSync(defaultDir, { recursive: true });

    const identidad = detectarIdentidadNavegadorLocal(family);
    const nombre = identidad?.name || 'Contador';
    const gaiaName = identidad?.gaia_name || 'Estudio Contable';
    const gaiaGivenName = identidad?.gaia_given_name || nombre;
    const userName = identidad?.user_name || '';
    const avatarIcon = identidad?.avatar_icon || 'chrome://theme/IDR_PROFILE_AVATAR_26';

    // Copiar foto de perfil de Google si existe en el navegador principal del usuario
    let hasPicture = false;
    if (identidad?.pictureSourcePath) {
        const destPic = path.join(defaultDir, identidad.pictureFileName);
        try {
            if (!fs.existsSync(destPic)) {
                fs.copyFileSync(identidad.pictureSourcePath, destPic);
            }
            hasPicture = fs.existsSync(destPic);
        } catch (_) {}
    }

    // Actualizar Default/Preferences haciendo merge suave (sin destruir esquema de Chrome)
    const prefsPath = path.join(defaultDir, 'Preferences');
    try {
        let prefs = {};
        if (fs.existsSync(prefsPath)) {
            try {
                prefs = JSON.parse(fs.readFileSync(prefsPath, 'utf8')) || {};
            } catch (_) {
                prefs = {};
            }
        }

        prefs.profile = {
            ...(prefs.profile || {}),
            name: nombre,
            gaia_name: gaiaName,
            gaia_given_name: gaiaGivenName,
            user_name: userName,
            avatar_icon: avatarIcon,
            using_default_name: false,
            using_default_avatar: false,
            using_gaia_avatar: hasPicture,
        };
        prefs.bookmark_bar = { ...(prefs.bookmark_bar || {}), show_on_all_tabs: false };
        prefs.browser = {
            ...(prefs.browser || {}),
            show_home_button: false,
            has_seen_welcome_page: true,
        };
        prefs.signin = {
            ...(prefs.signin || {}),
            allowed_on_next_startup: false,
        };

        fs.writeFileSync(prefsPath, JSON.stringify(prefs), 'utf8');
    } catch (_) {}

    // Actualizar Local State haciendo merge suave en profile.info_cache.Default
    const localStatePath = path.join(dir, 'Local State');
    try {
        let localState = {};
        if (fs.existsSync(localStatePath)) {
            try {
                localState = JSON.parse(fs.readFileSync(localStatePath, 'utf8')) || {};
            } catch (_) {
                localState = {};
            }
        }

        localState.profile = localState.profile || {};
        localState.profile.info_cache = localState.profile.info_cache || {};
        const currentDefault = localState.profile.info_cache.Default || {};
        const rawSource = identidad?.rawInfoCache || {};

        localState.profile.info_cache.Default = {
            ...currentDefault,
            name: nombre,
            gaia_name: gaiaName,
            gaia_given_name: gaiaGivenName,
            user_name: userName,
            avatar_icon: avatarIcon,
            is_using_default_avatar: false,
            is_using_default_name: false,
            is_ephemeral: false,
            ...(rawSource.profile_color_seed !== undefined ? { profile_color_seed: rawSource.profile_color_seed } : {}),
            ...(rawSource.profile_highlight_color !== undefined ? { profile_highlight_color: rawSource.profile_highlight_color } : {}),
            ...(rawSource.default_avatar_fill_color !== undefined ? { default_avatar_fill_color: rawSource.default_avatar_fill_color } : {}),
            ...(rawSource.default_avatar_stroke_color !== undefined ? { default_avatar_stroke_color: rawSource.default_avatar_stroke_color } : {}),
            ...(hasPicture ? { gaia_picture_file_name: identidad.pictureFileName } : {}),
        };

        fs.writeFileSync(localStatePath, JSON.stringify(localState), 'utf8');
    } catch (_) {}
}

// ─── Limpieza de Concurrencia y Sesiones Previas (Multi-Empresa Inteligente) ─

function eliminarLockfiles(dir) {
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

/**
 * Cierra cualquier sesión automatizada previa del mismo portal (sea de esta empresa o de
 * otra empresa que el contador dejó abierta hace 1 hora) y prepara un perfil cálido.
 */
function prepararEntornoYPerfil(portal, ruc, family = 'chrome') {
    ensureFrameworkDir();

    const activeSessionFile = path.join(FRAMEWORK_DIR, `active_session_${portal}.json`);

    // 1. Revisar el registro global de sesión activa del portal
    if (fs.existsSync(activeSessionFile)) {
        try {
            const prev = JSON.parse(fs.readFileSync(activeSessionFile, 'utf8'));
            if (prev?.browserPid) terminateProcessTree(prev.browserPid);
            if (prev?.nodePid) terminateProcessTree(prev.nodePid);
        } catch (_) {}
        try { fs.unlinkSync(activeSessionFile); } catch (_) {}
    }

    // 2. Revisar archivos PID o señales huérfanas de cualquier RUC para este portal en storage/framework
    try {
        const files = fs.readdirSync(FRAMEWORK_DIR);
        const browserPrefix = `login_browser_${portal}_`;
        const nodePrefix = `login_pid_${portal}_`;
        const readyPrefix = `login_ready_${portal}_`;

        for (const f of files) {
            const fullPath = path.join(FRAMEWORK_DIR, f);
            if (f.startsWith(browserPrefix) || f.startsWith(nodePrefix)) {
                try {
                    const pid = parseInt(fs.readFileSync(fullPath, 'utf8'), 10);
                    if (pid && pid !== process.pid) {
                        terminateProcessTree(pid);
                    }
                    fs.unlinkSync(fullPath);
                } catch (_) {}
            } else if (f.startsWith(readyPrefix) && f !== `login_ready_${portal}_${ruc}.json`) {
                try { fs.unlinkSync(fullPath); } catch (_) {}
            }
        }
    } catch (_) {}

    // 3. Seleccionar directorio de perfil cálido compartido por portal (con fallback por RUC si estuviera bloqueado)
    const base = process.env.LOCALAPPDATA
        || process.env.HOME
        || process.env.USERPROFILE
        || path.join(__dirname, '..', '..', 'storage', 'app');

    let dir = path.join(base, 'CrmContable', 'navegador', `${portal}_sesion`);
    fs.mkdirSync(path.join(dir, 'Default'), { recursive: true });
    eliminarLockfiles(dir);

    // Si algún lockfile sigue bloqueado en el sistema de archivos, usar el perfil específico del RUC
    const lockTest = path.join(dir, 'lockfile');
    if (fs.existsSync(lockTest)) {
        try {
            fs.unlinkSync(lockTest);
        } catch (_) {
            dir = path.join(base, 'CrmContable', 'navegador', portal, ruc);
            fs.mkdirSync(path.join(dir, 'Default'), { recursive: true });
            eliminarLockfiles(dir);
        }
    }

    configurarIdentidadEnPerfil(dir, family);

    return { dir, activeSessionFile };
}

// ─── Control de Ventana (Invisible en Login -> Maximizada Nativa al Abrir) ───

async function ocultarVentanaInicial(page) {
    try {
        const cdp = await page.createCDPSession();
        const { windowId } = await cdp.send('Browser.getWindowForTarget');
        await cdp.send('Browser.setWindowBounds', {
            windowId,
            bounds: { windowState: 'normal', left: -32000, top: -32000, width: 1280, height: 820 },
        });
        return { cdp, windowId };
    } catch (_) {
        return null;
    }
}

/**
 * Restaura la ventana a coordenadas normales en pantalla principal y la maximiza en primer plano.
 */
async function mostrarVentanaEnPrimerPlano(page, ventana, browserPid) {
    try {
        const cdp = ventana?.cdp || await page.createCDPSession();
        const windowId = ventana?.windowId || (await cdp.send('Browser.getWindowForTarget')).windowId;

        // 1. Restaurar desde fuera de pantalla a estado normal en pantalla principal
        await cdp.send('Browser.setWindowBounds', {
            windowId,
            bounds: { left: 0, top: 0, width: 1280, height: 820, windowState: 'normal' },
        });

        // 2. Maximizar en pantalla principal
        await cdp.send('Browser.setWindowBounds', {
            windowId,
            bounds: { windowState: 'maximized' },
        });

        await page.bringToFront();

        // 3. Garantizar foco en primer plano en Windows (incluso si otra ventana tenía foco activo)
        if (process.platform === 'win32' && browserPid) {
            try {
                execSync(
                    `mshta vbscript:Execute("CreateObject(""WScript.Shell"").AppActivate(${browserPid}):close")`,
                    { stdio: 'ignore', timeout: 1000 }
                );
            } catch (_) {}
        }

        // 4. Desadjuntar sesión CDP de ventana para dejar el navegador libre de instrumentación
        await cdp.detach().catch(() => {});
    } catch (error) {
        console.log('ℹ️ Aviso al restaurar ventana:', error.message);
        try { await page.bringToFront(); } catch (_) {}
    }
}

// ─── Escudo Visual en Pasarela de Login ──────────────────────────────────────

async function instalarEscudoInvisibleLogin(page, esSunafil = false) {
    await page.evaluateOnNewDocument((modoSunafil) => {
        try {
            Object.defineProperty(navigator, 'webdriver', { get: () => undefined });
        } catch (_) {}

        // En SUNAFIL: interceptar inmediatamente la variable `direc` de redirección a SOL
        // para saltar a api-seguridad en 0ms sin esperar a que carguen imágenes o estilos de la portada.
        if (modoSunafil) {
            let valorDirec;
            try {
                Object.defineProperty(window, 'direc', {
                    configurable: true,
                    get() {
                        return valorDirec;
                    },
                    set(val) {
                        valorDirec = val;
                        if (typeof val === 'string' && val.startsWith('https://')) {
                            window.location.replace(val);
                        }
                    },
                });
            } catch (_) {}
        }
    }, esSunafil);
}

// ─── Autenticación Ultrarrápida y Segura en Pasarela Clave SOL ───────────────

async function autenticarEnPasarelaSol(page, { ruc, usuario, clave, esperaFormulario = 20000 }) {
    const inicio = Date.now();

    const formulario = await page
        .waitForSelector('#txtRuc, input[name="txtRuc"]', { timeout: esperaFormulario, visible: true })
        .catch(() => null);

    if (!formulario) {
        return false;
    }

    try {
        const btnPorRuc = await page.$('#btnPorRuc');
        if (btnPorRuc) {
            await btnPorRuc.click();
        }
    } catch (_) {}

    console.log('✍️  Ingresando credenciales Clave SOL...');
    await page.click('#txtRuc, input[name="txtRuc"]', { clickCount: 3 });
    await page.type('#txtRuc, input[name="txtRuc"]', ruc, { delay: 0 });

    await page.click('#txtUsuario, input[name="txtUsuario"]', { clickCount: 3 });
    await page.type('#txtUsuario, input[name="txtUsuario"]', usuario, { delay: 0 });

    await page.click('#txtContrasena, input[name="txtContrasena"]', { clickCount: 3 });
    await page.type('#txtContrasena, input[name="txtContrasena"]', clave, { delay: 0 });

    console.log('🔐 Enviando formulario Clave SOL...');
    const boton = (await page.$('#btnAceptar')) || (await page.$('button[type="submit"]'));
    if (boton) {
        await boton.click();
    }

    // Esperar a que el formulario inicie navegación o desaparezca el campo RUC
    try {
        await page.waitForFunction(
            () => !window.location.hostname.includes('api-seguridad.sunat.gob.pe') || !document.querySelector('#txtRuc, input[name="txtRuc"]'),
            { timeout: 20000 }
        );
    } catch (_) {}

    const limite = Date.now() + 18000;
    while (Date.now() < limite) {
        const urlActual = page.url();
        const enPasarela = urlActual.includes('api-seguridad.sunat.gob.pe');

        if (!enPasarela && urlActual.startsWith('https://')) {
            console.log(`🔓 Sesión SOL autenticada en ${Date.now() - inicio} ms.`);
            return true;
        }

        // Revisar si hay mensaje de error visible en api-seguridad
        const estadoPasarela = await page.evaluate(() => {
            const selectoresError = '#spanError, #divMensajeError, #lblMensaje, .alert-danger, .error-message, .error';
            const nodosError = Array.from(document.querySelectorAll(selectoresError));
            let textoError = '';

            for (const nodo of nodosError) {
                if (nodo && nodo.offsetParent !== null) {
                    const txt = (nodo.innerText || '').trim();
                    if (txt.length > 3) {
                        textoError = txt;
                        break;
                    }
                }
            }

            const inputRuc = document.querySelector('#txtRuc, input[name="txtRuc"]');
            if (!textoError && !inputRuc) {
                const bodyText = (document.body?.innerText || '').trim();
                if (/(clave.*incorrect|usuario.*incorrect|no existe|datos.*inv[aá]lid|ha sido bloquead|intentos fallidos)/i.test(bodyText)) {
                    textoError = 'SUNAT rechazó las credenciales Clave SOL. Verifica el RUC, usuario y contraseña.';
                }
            }

            const btnContinuar = document.querySelector(
                '#btnContinuar, #btnCerrar, #btnFinalizar, input[value*="Continuar" i], button.btn-primary:not(#btnAceptar)'
            );
            if (!inputRuc && btnContinuar && !textoError) {
                btnContinuar.click();
            }

            return { textoError };
        }).catch(() => null);

        if (estadoPasarela?.textoError) {
            throw new Error(estadoPasarela.textoError);
        }

        await new Promise((r) => setTimeout(r, 100));
    }

    if (!page.url().includes('loginMenuSol')) {
        return true;
    }

    throw new Error('SUNAT no aceptó la Clave SOL o el servicio de autenticación no respondió a tiempo.');
}

/**
 * El callback de OAuth todavía está canjeando el código. No hay que
 * dar por abierto el servicio en esa página intermedia.
 *
 * @param {string} url
 */
function intercambioOauthPendiente(url) {
    try {
        return new URL(url).searchParams.has('code');
    } catch (_) {
        return false;
    }
}

/**
 * @returns {Promise<'login'|'puente'|'listo'|'navegando'>}
 */
async function estadoDelPortal(page) {
    const url = page.url();
    if (!url.startsWith('https://')) {
        return 'navegando';
    }

    const enPasarela = url.includes('api-seguridad.sunat.gob.pe') || url.includes('/oauth2/') || url.includes('loginMenuSol');
    const estado = await page.evaluate(() => {
        const input = document.querySelector('#txtRuc, input[name="txtRuc"]');
        if (input) {
            return 'login';
        }

        const texto = (document.body?.innerText || '').trim();
        const html = document.documentElement?.innerHTML || '';
        if (html.includes('oauth2/authen') && texto.length < 80) {
            return 'puente';
        }

        return 'listo';
    }).catch(() => 'navegando');

    if (estado === 'login') {
        return 'login';
    }
    if (estado === 'navegando' || enPasarela) {
        return estado === 'listo' ? 'puente' : estado;
    }

    return estado;
}

/**
 * Espera a que el servicio quede abierto o a que la Clave SOL esté visible
 * para escribirla. El puente OAuth, sin formulario, no cuenta como destino.
 *
 * @returns {Promise<'login'|'puente'|'listo'|'navegando'>}
 */
async function esperarPortal(page, timeout = 20000) {
    const limite = Date.now() + timeout;
    let previa = '';
    let estableDesde = 0;
    let ultimo = 'navegando';

    while (Date.now() < limite) {
        ultimo = await estadoDelPortal(page);
        const actual = page.url();
        const asentado = ultimo === 'listo' || ultimo === 'login';
        if (asentado && !intercambioOauthPendiente(actual) && actual === previa) {
            if (!estableDesde) {
                estableDesde = Date.now();
            }
            const espera = ultimo === 'login' ? 400 : 800;
            if (Date.now() - estableDesde >= espera) {
                return ultimo;
            }
        } else {
            estableDesde = 0;
            previa = actual;
        }

        await new Promise((r) => setTimeout(r, 150));
    }

    return ultimo;
}

/**
 * Trámites, declaraciones y e-renta son clientes OAuth distintos. La Clave SOL
 * se escribe en la pasarela del servicio elegido. Si esa pasarela vuelve a
 * mostrarse, se completa ahí mismo: saltar desde Trámites deja el login abierto.
 */
async function completarIngresoSunat(page, { ruc, usuario, clave }) {
    const credenciales = { ruc, usuario, clave };

    await autenticarEnPasarelaSol(page, credenciales);
    const estado = await esperarPortal(page);

    if (estado === 'login' || estado === 'puente') {
        console.log('🔁 El servicio abrió su propia Clave SOL. Ingresando...');
        await autenticarEnPasarelaSol(page, credenciales);
        await esperarPortal(page);
    }
}

// ─── Orquestador Principal de Lanzamiento ────────────────────────────────────

async function ejecutarLoginPortal({
    portal,          // 'sunat' | 'sunafil'
    ruc,
    usuario,
    clave,
    urlEntrada,
    esSunafil = false,
}) {
    ensureFrameworkDir();

    const pidFile = path.join(FRAMEWORK_DIR, `login_pid_${portal}_${ruc}.pid`);
    const browserPidFile = path.join(FRAMEWORK_DIR, `login_browser_${portal}_${ruc}.pid`);
    const signalFile = path.join(FRAMEWORK_DIR, `login_ready_${portal}_${ruc}.json`);

    function writeSignal(status, extra = {}) {
        writeJsonAtomic(signalFile, {
            status,
            ruc,
            timestamp: Date.now(),
            ...extra,
        });
    }

    let browser = null;
    let browserInfo = { path: undefined, name: 'Google Chrome', family: 'chrome' };
    let activeSessionFile = null;

    function cleanupPid() {
        for (const f of [pidFile, browserPidFile, activeSessionFile]) {
            if (!f) continue;
            try {
                if (fs.existsSync(f)) fs.unlinkSync(f);
            } catch (_) {}
        }
    }

    try {
        const t0 = Date.now();
        browserInfo = getBrowserInfo();
        console.log(`🌐 Navegador predeterminado: ${browserInfo.name} (${browserInfo.path || 'por defecto'})`);
        writeSignal('starting', { browser: browserInfo.name });

        const entorno = prepararEntornoYPerfil(portal, ruc, browserInfo.family);
        activeSessionFile = entorno.activeSessionFile;

        try {
            fs.writeFileSync(pidFile, String(process.pid), 'utf8');
        } catch (_) {}

        const launchOptions = {
            headless: false,
            defaultViewport: null,
            userDataDir: entorno.dir,
            ignoreDefaultArgs: ['--enable-automation'],
            args: [
                '--window-position=-32000,-32000',
                '--window-size=1280,820',
                '--no-first-run',
                '--no-default-browser-check',
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
        const browserProcess = browser.process();
        const browserPid = browserProcess ? browserProcess.pid : null;

        if (browserPid) {
            try {
                fs.writeFileSync(browserPidFile, String(browserPid), 'utf8');
            } catch (_) {}
            writeJsonAtomic(activeSessionFile, {
                portal,
                ruc,
                browserPid,
                nodePid: process.pid,
                timestamp: Date.now(),
            });
        }

        // Cuando el proceso real del navegador sea cerrado por el usuario, limpiar archivos PID y terminar Node
        if (browserProcess) {
            browserProcess.once('exit', () => {
                console.log('ℹ️ Navegador cerrado por el usuario.');
                cleanupPid();
                process.exit(0);
            });
        }

        const pages = await browser.pages();
        const page = pages.length > 0 ? pages[0] : await browser.newPage();

        const ventana = await ocultarVentanaInicial(page);
        await instalarEscudoInvisibleLogin(page, esSunafil);

        // Limpiar únicamente las cookies de sesión (conservando intacto el caché HTTP de scripts/CSS/fuentes en disco)
        try {
            const cdpCookies = ventana?.cdp || await page.createCDPSession();
            await cdpCookies.send('Network.clearBrowserCookies');
        } catch (_) {}

        // Cada servicio de SUNAT tiene su propio cliente OAuth. El ingreso empieza
        // en la URL elegida (Trámites, PDT 621 o e-renta) para que la Clave SOL
        // quede en ese aplicativo y no en el menú de Trámites.
        console.log(`📡 Conectando a ${urlEntrada}...`);
        await page.goto(urlEntrada, { waitUntil: 'domcontentloaded', timeout: 25000 });

        // En SUNAFIL, si la variable `direc` ya estaba definida al cargar el DOM, ejecutar el reemplazo inmediato
        if (esSunafil) {
            await page.evaluate(() => {
                if (typeof direc !== 'undefined' && direc) {
                    window.location.replace(direc);
                }
            }).catch(() => {});
        }

        if (esSunafil) {
            await autenticarEnPasarelaSol(page, { ruc, usuario, clave });
        } else {
            await completarIngresoSunat(page, { ruc, usuario, clave });
        }

        console.log(`📍 Portal listo: ${page.url().slice(0, 140)}`);

        // Mostrar y maximizar ventana en primer plano y desconectar CDP para uso 100% nativo sin parpadeos
        await mostrarVentanaEnPrimerPlano(page, ventana, browserPid);

        // Notificar al CRM únicamente cuando la ventana ya está maximizada y visible en pantalla
        writeSignal('ready', { browser: browserInfo.name });
        console.log(`✅ Acceso completado en ${Date.now() - t0} ms (${browserInfo.name}).`);

        // Desconectar el depurador CDP de Puppeteer: el navegador queda libre de automatización
        // (cero parpadeo en calendarios/popups de Recibos por Honorarios y cero huella de bot).
        await browser.disconnect().catch(() => {});

        // Mantener vivo el proceso vigilante ligero hasta que browserProcess emita 'exit'
        await new Promise(() => {});
    } catch (error) {
        console.error(`❌ Error durante el acceso a ${portal.toUpperCase()}:`, error.message);
        cleanupPid();
        writeSignal('error', {
            message: error.message || `No se pudo iniciar sesión en ${portal.toUpperCase()}.`,
            browser: browserInfo.name,
        });

        // Cerrar la instancia oculta si falló la autenticación para no dejar ventanas rotas
        if (browser) {
            try {
                await browser.close();
            } catch (_) {}
        }

        process.exit(1);
    }
}

module.exports = {
    ejecutarLoginPortal,
    getBrowserInfo,
};
