/**
 * scripts/validate_sol.cjs
 *
 * Validación 100% headless y silenciosa de credenciales Clave SOL en SUNAT.
 * No abre ninguna ventana ni muestra nada en pantalla.
 *
 * Salida JSON:
 * {
 *    success: boolean, // si el proceso de verificación pudo ejecutarse
 *    valid: boolean,   // si las credenciales son correctas en SUNAT
 *    message: string,  // mensaje descriptivo para el usuario
 *    razon_social?: string // si se pudo extraer de SUNAT
 * }
 */

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const [,, ruc, usuario, clave] = process.argv;

if (!ruc || !usuario || !clave) {
    console.log(JSON.stringify({
        success: false,
        valid: false,
        message: 'Faltan parámetros requeridos (RUC, Usuario SOL o Clave SOL).'
    }));
    process.exit(0);
}

function getChromePath() {
    if (process.env.PUPPETEER_EXECUTABLE_PATH && fs.existsSync(process.env.PUPPETEER_EXECUTABLE_PATH)) {
        return process.env.PUPPETEER_EXECUTABLE_PATH;
    }
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
            return p;
        }
    }
    return undefined;
}

const SUNAT_LOGIN_URL = 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm';

// Safety timeout: 25 seconds
const timeoutTimer = setTimeout(() => {
    console.log(JSON.stringify({
        success: false,
        valid: false,
        message: 'El servidor de SUNAT demoró demasiado en responder (Tiempo de espera agotado).'
    }));
    process.exit(0);
}, 25000);

(async () => {
    let browser;
    try {
        const chromePath = getChromePath();
        browser = await puppeteer.launch({
            headless: 'new',
            executablePath: chromePath,
            defaultViewport: { width: 1280, height: 800 },
            ignoreDefaultArgs: ['--enable-automation'],
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--test-type',
                '--disable-blink-features=AutomationControlled',
                '--disable-gpu',
                '--disable-dev-shm-usage',
            ],
        });

        const page = await browser.newPage();
        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        );

        let dialogMessage = null;
        page.on('dialog', async dialog => {
            dialogMessage = dialog.message();
            await dialog.dismiss();
        });

        await page.goto(SUNAT_LOGIN_URL, { waitUntil: 'domcontentloaded', timeout: 18000 });

        try {
            const btnPorRuc = await page.$('#btnPorRuc');
            if (btnPorRuc) {
                await btnPorRuc.click();
                await new Promise(r => setTimeout(r, 300));
            }
        } catch (_) {}

        await page.waitForSelector('#txtRuc, input[name="txtRuc"]', { timeout: 15000 });

        const selRuc = (await page.$('#txtRuc')) ? '#txtRuc' : 'input[name="txtRuc"]';
        const selUsuario = (await page.$('#txtUsuario')) ? '#txtUsuario' : 'input[name="txtUsuario"]';
        const selClave = (await page.$('#txtContrasena')) ? '#txtContrasena' : 'input[name="txtContrasena"]';

        await page.click(selRuc, { clickCount: 3 });
        await page.type(selRuc, ruc, { delay: 10 });

        await page.click(selUsuario, { clickCount: 3 });
        await page.type(selUsuario, usuario, { delay: 10 });

        await page.click(selClave, { clickCount: 3 });
        await page.type(selClave, clave, { delay: 10 });

        const selBoton = (await page.$('#btnAceptar')) ? '#btnAceptar' : 'button[type="submit"]';
        
        await Promise.allSettled([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 10000 }).catch(() => {}),
            page.click(selBoton),
        ]);

        // Esperar resultado: éxito o error
        const startTime = Date.now();
        let authResult = null;

        while (Date.now() - startTime < 14000) {
            try {
                const currentUrl = page.url();

                // 1. Caso Error explícito de autenticación en OAuth
                if (currentUrl.includes('/oauth2/error') || currentUrl.includes('error?')) {
                    const bodyText = await page.evaluate(() => {
                        try {
                            return (document && document.body && document.body.innerText) ? document.body.innerText : '';
                        } catch (_) {
                            return '';
                        }
                    }).catch(() => '');

                    let detail = 'El Usuario o Clave SOL ingresados no coinciden o no existen en SUNAT.';
                    if (bodyText.includes('incorrectos o no existen') || bodyText.includes('incorrecto')) {
                        detail = 'El Usuario o Clave SOL ingresados no coinciden o no existen en SUNAT.';
                    } else if (bodyText.includes('Falla en la autenticación')) {
                        detail = 'Falla en la autenticación de SUNAT: Credenciales SOL incorrectas.';
                    }
                    authResult = {
                        success: true,
                        valid: false,
                        message: detail
                    };
                    break;
                }

                // 2. Caso Éxito confirmado: Ingreso al portal SOL (MenuInternet)
                if (
                    (currentUrl.includes('MenuInternet.htm') || currentUrl.includes('AutenticaMenuInternet.htm')) &&
                    !currentUrl.includes('oauth2') &&
                    !currentUrl.includes('error')
                ) {
                    const dashboardData = await page.evaluate(() => {
                        try {
                            const hasLoginForm = document.querySelector('#txtRuc, input[name="txtRuc"]') !== null;
                            if (hasLoginForm) return null;

                            const b = (document && document.body && document.body.innerText) ? document.body.innerText : '';
                            const hasWelcome = b.includes('Bienvenido') || (b.includes('Salir') && b.includes('Buzón Electrónico'));
                            const hasMenu = document.querySelector('#divMenu, #aBuzon, .menu-superior') !== null;

                            if (hasWelcome || hasMenu) {
                                let razonSocial = null;
                                const matchBienvenido = b.match(/Bienvenido,\s*([^\n\r]+)/i);
                                if (matchBienvenido) {
                                    razonSocial = matchBienvenido[1].trim();
                                } else {
                                    const matchRuc = b.match(/(?:RUC|R\.U\.C\.)\s*:\s*\d+\s*-\s*([A-Z0-9\s.,&'-]+)/i);
                                    if (matchRuc) razonSocial = matchRuc[1].trim();
                                }
                                return { ok: true, razonSocial };
                            }
                        } catch (_) {}
                        return null;
                    }).catch(() => null);

                    if (dashboardData && dashboardData.ok) {
                        authResult = {
                            success: true,
                            valid: true,
                            message: '¡Credenciales Clave SOL verificadas exitosamente con SUNAT!',
                            razon_social: dashboardData.razonSocial || undefined
                        };
                        break;
                    }
                }

                // 3. Caso Error en la misma página de login (alerta visible en formulario)
                if (currentUrl.includes('oauth2') || currentUrl.includes('loginMenuSol')) {
                    const alertText = await page.evaluate(() => {
                        try {
                            const alertEl = document.querySelector('#divMensaje, .alert-danger, .alert');
                            if (alertEl && alertEl.innerText && alertEl.innerText.trim().length > 3) {
                                const txt = alertEl.innerText.trim();
                                if (!txt.includes('No se encontraron coincidencias para')) {
                                    return txt;
                                }
                            }
                        } catch (_) {}
                        return null;
                    }).catch(() => null);

                    if (alertText) {
                        authResult = {
                            success: true,
                            valid: false,
                            message: `SUNAT indica: ${alertText}`
                        };
                        break;
                    }
                }

                if (dialogMessage) {
                    authResult = {
                        success: true,
                        valid: false,
                        message: `Aviso de SUNAT: ${dialogMessage}`
                    };
                    break;
                }
            } catch (_) {
                // Ignorar excepciones transitorias durante redirecciones de navegación
            }

            await new Promise(r => setTimeout(r, 400));
        }

        if (!authResult) {
            // Verificación final después del bucle
            const currentUrl = page.url();
            if (currentUrl.includes('/oauth2/error')) {
                authResult = {
                    success: true,
                    valid: false,
                    message: 'El Usuario o Clave SOL ingresados no coinciden o no existen en SUNAT.'
                };
            } else if (currentUrl.includes('MenuInternet.htm')) {
                authResult = {
                    success: true,
                    valid: true,
                    message: '¡Credenciales Clave SOL verificadas exitosamente con SUNAT!'
                };
            } else {
                authResult = {
                    success: false,
                    valid: false,
                    message: 'No se recibió respuesta concluyente de SUNAT en el tiempo esperado.'
                };
            }
        }

        clearTimeout(timeoutTimer);
        await browser.close();
        console.log(JSON.stringify(authResult));
        process.exit(0);

    } catch (err) {
        clearTimeout(timeoutTimer);
        if (browser) {
            try { await browser.close(); } catch (_) {}
        }
        console.log(JSON.stringify({
            success: false,
            valid: false,
            message: `Error al conectar con SUNAT: ${err.message}`
        }));
        process.exit(0);
    }
})();
