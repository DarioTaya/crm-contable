/**
 * scripts/sync_buzon.cjs
 *
 * Scraper headless avanzado para extraer notificaciones del Buzón Electrónico de SUNAT (SOL).
 * - Ejecución headless ('new') invisible y silenciosa.
 * - Soporte para intercepción de respuestas AJAX/JSON en segundo plano (page.on('response')).
 * - Manejo profundo y transversal de iframes en el portal SOL.
 * - Espera estricta de elementos reales y normalización de fecha para MySQL (YYYY-MM-DD).
 * - Imprime el arreglo JSON resultante en stdout para el consumo de Laravel.
 *
 * Uso:
 *   node scripts/sync_buzon.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL>
 */

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

// ─── Validar argumentos ───────────────────────────────────────────────────────
const [,, ruc, usuario, clave] = process.argv;

if (!ruc || !usuario || !clave) {
    console.error(JSON.stringify({ error: 'Faltan parámetros: <RUC> <USUARIO_SOL> <CLAVE_SOL>' }));
    process.exit(1);
}

// ─── Localizar navegador en el sistema ────────────────────────────────────────
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

// ─── Normalizar fecha a formato ISO YYYY-MM-DD para MySQL ────────────────────
function normalizarFecha(str) {
    if (!str) return new Date().toISOString().slice(0, 10);
    const match = str.match(/(\d{2})\/(\d{2})\/(\d{4})/);
    if (match) {
        return `${match[3]}-${match[2]}-${match[1]}`;
    }
    const isoMatch = str.match(/(\d{4})-(\d{2})-(\d{2})/);
    if (isoMatch) {
        return isoMatch[0];
    }
    return new Date().toISOString().slice(0, 10);
}

const SUNAT_LOGIN_URL = 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm';

(async () => {
    let browser;
    const interceptedItems = [];

    try {
        const chromePath = getChromePath();
        const launchOptions = {
            headless: 'new',
            defaultViewport: { width: 1280, height: 800 },
            ignoreDefaultArgs: ['--enable-automation'],
            args: [
                '--test-type',
                '--disable-blink-features=AutomationControlled',
                '--disable-features=IsolateOrigins,site-per-process',
            ],
        };

        if (chromePath) {
            launchOptions.executablePath = chromePath;
        }

        browser = await puppeteer.launch(launchOptions);
        const page = await browser.newPage();

        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        );

        // ─── Intercepción de respuestas AJAX/JSON de la red ──────────────────
        page.on('response', async (res) => {
            try {
                const url = res.url();
                const headers = res.headers();
                const contentType = headers['content-type'] || '';

                if (contentType.includes('application/json') || url.includes('json') || url.includes('buzon') || url.includes('mensaje') || url.includes('notifica')) {
                    const text = await res.text();
                    try {
                        const json = JSON.parse(text);
                        const records = Array.isArray(json)
                            ? json
                            : (json.rows || json.data || json.mensajes || json.notificaciones || json.list || json.items || []);

                        if (Array.isArray(records) && records.length > 0) {
                            records.forEach(r => {
                                const fechaRaw = r.fecEnvio || r.fecNotif || r.fecha || r.fecEmision || r.fecDoc || r.date || '';
                                const asuntoRaw = r.desAsunto || r.asunto || r.nomMensaje || r.mensaje || r.desMensaje || r.subject || '';
                                const esLeido = (r.estLectura === '1' || r.estLectura === 1 || r.leido === true || r.estado === 'Leído');

                                if (fechaRaw && asuntoRaw) {
                                    interceptedItems.push({
                                        fecha: normalizarFecha(fechaRaw),
                                        asunto: String(asuntoRaw).trim(),
                                        leido: esLeido,
                                        emisor: 'SUNAT',
                                    });
                                }
                            });
                        }
                    } catch (_) {}
                }
            } catch (_) {}
        });

        // 1. Iniciar Sesión en SUNAT SOL
        await page.goto(SUNAT_LOGIN_URL, { waitUntil: 'networkidle2', timeout: 45000 });

        try {
            const btnPorRuc = await page.$('#btnPorRuc');
            if (btnPorRuc) {
                await btnPorRuc.click();
                await new Promise(r => setTimeout(r, 400));
            }
        } catch (_) {}

        await page.waitForSelector('#txtRuc, input[name="txtRuc"]', { timeout: 25000 });

        const selRuc = (await page.$('#txtRuc')) ? '#txtRuc' : 'input[name="txtRuc"]';
        const selUsuario = (await page.$('#txtUsuario')) ? '#txtUsuario' : 'input[name="txtUsuario"]';
        const selClave = (await page.$('#txtContrasena')) ? '#txtContrasena' : 'input[name="txtContrasena"]';

        await page.type(selRuc, ruc, { delay: 20 });
        await page.type(selUsuario, usuario, { delay: 20 });
        await page.type(selClave, clave, { delay: 20 });

        const selBoton = (await page.$('#btnAceptar')) ? '#btnAceptar' : 'button[type="submit"]';

        await Promise.allSettled([
            page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 25000 }),
            page.click(selBoton),
        ]);

        await new Promise(r => setTimeout(r, 2500));

        // 2. Localizar y Navegar a Buzón Electrónico
        let buzonEncontrado = false;
        const buzonSelectors = [
            '#aBuzon',
            '#btnBuzon',
            'a[title*="Buzón"]',
            'a[title*="buzon"]',
            'a[href*="Buzon"]',
            'a[href*="buzon"]',
            'span[title*="Buzón"]',
            '#divBuzon a',
            '#divBuzon',
        ];

        for (const selector of buzonSelectors) {
            try {
                const el = await page.$(selector);
                if (el) {
                    await Promise.allSettled([
                        page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }),
                        el.click(),
                    ]);
                    buzonEncontrado = true;
                    break;
                }
            } catch (_) {}
        }

        if (!buzonEncontrado) {
            try {
                const links = await page.$$('a, button, span');
                for (const link of links) {
                    const text = await page.evaluate(el => el.textContent, link);
                    if (text && text.toLowerCase().includes('buzón')) {
                        await Promise.allSettled([
                            page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 15000 }),
                            link.click(),
                        ]);
                        buzonEncontrado = true;
                        break;
                    }
                }
            } catch (_) {}
        }

        if (!buzonEncontrado) {
            try {
                await page.goto('https://e-menu.sunat.gob.pe/cl-ti-itmenu/BuzonInternet.htm', {
                    waitUntil: 'networkidle2',
                    timeout: 20000,
                });
            } catch (_) {}
        }

        // 3. Manejo de iframes y espera de carga AJAX
        // Dar tiempo a las peticiones dinámicas dentro de los iframes
        await new Promise(r => setTimeout(r, 3000));

        // Esperar si existe algún iframe para que cargue
        const waitFrames = async (retries = 5) => {
            for (let i = 0; i < retries; i++) {
                const frames = page.frames();
                for (const frame of frames) {
                    try {
                        const hasRows = await frame.$('table tbody tr, .ui-jqgrid-btable tr, tr.jqgrow, #tblMensajes tr, .table tr');
                        if (hasRows) return;
                    } catch (_) {}
                }
                await new Promise(r => setTimeout(r, 1000));
            }
        };
        await waitFrames();

        // 4. Extracción desde el DOM en todos los frames
        let domItems = [];
        const frames = [page, ...page.frames()];

        for (const frame of frames) {
            try {
                const filas = await frame.$$('table tbody tr, .ui-jqgrid-btable tr, tr.jqgrow, #tblMensajes tr, .table tbody tr, div[role="row"]');
                if (filas.length > 0) {
                    const extraidos = await frame.evaluate(() => {
                        const items = [];
                        const rows = document.querySelectorAll('table tbody tr, .ui-jqgrid-btable tr, tr.jqgrow, #tblMensajes tr, .table tbody tr, div[role="row"]');

                        rows.forEach(row => {
                            const cells = row.querySelectorAll('td, div[role="gridcell"]');
                            if (cells.length >= 2) {
                                let fechaTexto = '';
                                let asuntoTexto = '';
                                let esLeido = true;

                                cells.forEach(cell => {
                                    const t = (cell.textContent || '').trim();
                                    // Detectar fecha DD/MM/YYYY o YYYY-MM-DD
                                    if (/\d{2}\/\d{2}\/\d{4}/.test(t) || /\d{4}-\d{2}-\d{2}/.test(t)) {
                                        if (!fechaTexto) fechaTexto = t;
                                    } else if (t.length >= 4 && !asuntoTexto && !/^\d+$/.test(t)) {
                                        asuntoTexto = t;
                                    }
                                });

                                // Detección de lectura (clases visuales o estilos)
                                const classList = row.className || '';
                                if (classList.includes('no-leido') || classList.includes('unread') || row.querySelector('.no-leido, .unread, [title*="No leído"], [title*="Pendiente"]')) {
                                    esLeido = false;
                                }

                                if (fechaTexto && asuntoTexto) {
                                    items.push({
                                        fecha: fechaTexto,
                                        asunto: asuntoTexto,
                                        leido: esLeido,
                                        emisor: 'SUNAT',
                                    });
                                }
                            }
                        });
                        return items;
                    });

                    if (extraidos.length > 0) {
                        domItems = extraidos.map(m => ({
                            ...m,
                            fecha: normalizarFecha(m.fecha),
                        }));
                        break;
                    }
                }
            } catch (_) {}
        }

        // 5. Unificar resultados (priorizando intercepción AJAX, con respaldo de DOM)
        const mapaUnicos = new Map();

        // Agregar primero los interceptados por red
        interceptedItems.forEach(item => {
            const key = `${item.fecha}|${item.asunto.toLowerCase()}`;
            if (!mapaUnicos.has(key)) {
                mapaUnicos.set(key, item);
            }
        });

        // Agregar los obtenidos del DOM que no estén ya
        domItems.forEach(item => {
            const key = `${item.fecha}|${item.asunto.toLowerCase()}`;
            if (!mapaUnicos.has(key)) {
                mapaUnicos.set(key, item);
            }
        });

        const resultadoFinal = Array.from(mapaUnicos.values());

        // 6. Imprimir salida JSON pura para consumo en Laravel
        console.log(JSON.stringify(resultadoFinal));

    } catch (error) {
        console.error('Error durante extracción del Buzón:', error.message);
        // Si hay error en la navegación o credenciales, devolver los interceptados si existen o arreglo vacío
        console.log(JSON.stringify(interceptedItems));
    } finally {
        if (browser) {
            await browser.close();
        }
        process.exit(0);
    }
})();
