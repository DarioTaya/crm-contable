/**
 * scripts/sunat_buzon.cjs
 *
 * Scraper headless avanzado para el Buzón Electrónico SOL de SUNAT.
 * - Modo headless ('new') 100% oculto y silencioso.
 * - Login en portal SOL con RUC, Usuario y Clave SOL.
 * - Scraping dirigido: prioriza carpetas clave (MIS ETIQUETAS, Valores, Resoluciones, Avisos).
 * - Filtro DOM estricto: evita filas de resumen interno de deudas/pagos ('Total :', 'Pago', 'Tributo', etc.).
 * - Lectura profunda: hace clic en cada mensaje y extrae el cuerpo HTML/texto completo.
 * - Manejo de PDFs: detecta notificaciones con anexos PDF y provee fallback estructurado.
 * - Filtro Anti-Spam: ignora RVIE, RCE, Comprobantes, etc.
 * - Diccionario Tributario de Alta Prioridad (Esquela, Omiso, Multa, Embargo, etc.).
 *
 * Uso:
 *   node scripts/sunat_buzon.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL>
 */

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

// ─── 1. Validar Argumentos ───────────────────────────────────────────────────
const [, , ruc, usuario, clave] = process.argv;

if (!ruc || !usuario || !clave) {
    console.error(JSON.stringify({ error: 'Faltan parámetros: <RUC> <USUARIO_SOL> <CLAVE_SOL>' }));
    process.exit(1);
}

// ─── 2. Localizar Navegador en Windows ────────────────────────────────────────
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

// ─── 3. Normalizar Fecha a formato MySQL (YYYY-MM-DD HH:mm:ss) ────────────────
function normalizarFecha(str) {
    if (!str) return new Date().toISOString().slice(0, 19).replace('T', ' ');

    // DD/MM/YYYY HH:mm:ss
    const fullMatch = str.match(/(\d{2})\/(\d{2})\/(\d{4})\s+(\d{2}):(\d{2})(?::(\d{2}))?/);
    if (fullMatch) {
        const seg = fullMatch[6] || '00';
        return `${fullMatch[3]}-${fullMatch[2]}-${fullMatch[1]} ${fullMatch[4]}:${fullMatch[5]}:${seg}`;
    }

    // DD/MM/YYYY
    const dateMatch = str.match(/(\d{2})\/(\d{2})\/(\d{4})/);
    if (dateMatch) {
        return `${dateMatch[3]}-${dateMatch[2]}-${dateMatch[1]} 08:00:00`;
    }

    // YYYY-MM-DD
    const isoMatch = str.match(/(\d{4})-(\d{2})-(\d{2})(?:\s+(\d{2}:\d{2}:\d{2}))?/);
    if (isoMatch) {
        return isoMatch[4] ? `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]} ${isoMatch[4]}` : `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]} 08:00:00`;
    }

    return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

// ─── 4. Listas de Exclusión y Filtro Anti-Spam ────────────────────────────────
const CABECERAS_BASURA = [
    'periodo', 'tributo', 't.asociado', 'documento', 'pago', 'total :', 'total:',
    'saldo', 'interes', 'importe', 'deuda', 'subtotal', 'sub-total', 'orden', 'tipo'
];

const TERMINOS_SPAM = [
    'rvie', 'rce', 'plataforma de confirmación', 'plataforma de confirmacion',
    'reporte resumen', 'comprobantes', 'factura electrónica', 'factura electronica'
];

function esAsuntoInvalido(asunto) {
    if (!asunto) return true;
    const t = asunto.trim().toLowerCase();

    if (t.length < 5) return true;

    // Descartar cabeceras de tablas internas
    for (const b of CABECERAS_BASURA) {
        if (t === b || t.startsWith(b + ' ')) {
            return true;
        }
    }

    // Descartar términos de spam conocidos
    for (const s of TERMINOS_SPAM) {
        if (t.includes(s)) {
            return true;
        }
    }

    return false;
}

// ─── 5. Diccionario Tributario de Alta Prioridad ──────────────────────────────
function calcularPrioridad(asunto, contenido = '') {
    const texto = `${asunto} ${contenido}`.toLowerCase();

    const palabrasAlta = [
        'esquela', 'omiso', 'multa', 'embargo', 'valor', 'resolución', 'resolucion',
        'coactiv', 'requerimiento', 'orden de pago'
    ];

    for (const palabra of palabrasAlta) {
        if (texto.includes(palabra)) {
            return 'Alta';
        }
    }

    const palabrasMedia = [
        'declaraci', 'vencimiento', 'aviso', 'recordatorio', 'rectificatori',
        'cronograma', 'regulariz'
    ];

    for (const palabra of palabrasMedia) {
        if (texto.includes(palabra)) {
            return 'Media';
        }
    }

    return 'Baja';
}

const PDF_FALLBACK_TEXT = 'El contenido detallado o documento anexo se encuentra en formato PDF. Por favor, acceda directamente al portal SUNAT desde el Expediente del Cliente para descargarlo.';

const SUNAT_LOGIN_URL = 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm';

// Timeout global de seguridad (100 segundos) para no colgar procesos PHP
const safetyTimeout = setTimeout(() => {
    console.error('Timeout de seguridad excedido en sunat_buzon.cjs');
    console.log(JSON.stringify([]));
    process.exit(0);
}, 100000);

(async () => {
    let browser;
    const resultadoAlertas = [];
    const interceptedMessages = [];

    try {
        const chromePath = getChromePath();
        const launchOptions = {
            headless: 'new',
            defaultViewport: { width: 1280, height: 800 },
            ignoreDefaultArgs: ['--enable-automation'],
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
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

        // Intercepción pasiva de respuestas JSON de red con datos de mensajes
        page.on('response', async (res) => {
            try {
                const url = res.url();
                const contentType = res.headers()['content-type'] || '';
                if (contentType.includes('application/json') || url.includes('buzon') || url.includes('mensaje') || url.includes('notifica')) {
                    const text = await res.text();
                    try {
                        const data = JSON.parse(text);
                        const items = Array.isArray(data) ? data : (data.rows || data.data || data.mensajes || data.notificaciones || data.items || []);
                        if (Array.isArray(items) && items.length > 0) {
                            items.forEach(it => {
                                const asunto = it.desAsunto || it.asunto || it.nomMensaje || it.subject || '';
                                const fecha = it.fecEnvio || it.fecNotif || it.fecha || it.fecEmision || '';
                                const cuerpo = it.desCuerpo || it.cuerpo || it.contenido || it.mensaje || '';
                                if (asunto && !esAsuntoInvalido(String(asunto))) {
                                    interceptedMessages.push({
                                        asunto: String(asunto).trim(),
                                        fecha: normalizarFecha(fecha),
                                        contenido: (cuerpo && String(cuerpo).trim().length > 15) ? String(cuerpo).trim() : PDF_FALLBACK_TEXT,
                                        estado: (it.estLectura === '1' || it.leido) ? 'Leído' : 'Pendiente',
                                        prioridad: calcularPrioridad(asunto, cuerpo),
                                        plataforma: 'SUNAT'
                                    });
                                }
                            });
                        }
                    } catch (_) {}
                }
            } catch (_) {}
        });

        // ─── Paso 1: Iniciar sesión en SOL ──────────────────────────────────
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

        await page.type(selRuc, ruc, { delay: 15 });
        await page.type(selUsuario, usuario, { delay: 15 });
        await page.type(selClave, clave, { delay: 15 });

        const selBoton = (await page.$('#btnAceptar')) ? '#btnAceptar' : 'button[type="submit"]';

        await Promise.allSettled([
            page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }),
            page.click(selBoton),
        ]);

        await new Promise(r => setTimeout(r, 2000));

        // ─── Paso 2: Localizar y Navegar al Buzón Electrónico ────────────────
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

        // ─── Paso 3: Identificar frame activo y Scraping Dirigido ───────────
        await new Promise(r => setTimeout(r, 3000));

        const allFrames = [page, ...page.frames()];
        let frameBuzon = null;

        for (const frame of allFrames) {
            try {
                const hasTable = await frame.$('table tbody tr, .ui-jqgrid-btable tr, tr.jqgrow, #tblMensajes tr');
                if (hasTable) {
                    frameBuzon = frame;
                    break;
                }
            } catch (_) {}
        }

        if (!frameBuzon) {
            frameBuzon = page;
        }

        // Scraping Dirigido: Intentar hacer clic en carpetas de alta relevancia ('MIS ETIQUETAS', 'Valores', 'Resoluciones')
        try {
            const carpetasInteres = ['MIS ETIQUETAS', 'Valores', 'Resoluciones', 'Notificaciones'];
            for (const nombreCarpeta of carpetasInteres) {
                const clickeado = await frameBuzon.evaluate((nombre) => {
                    const elements = Array.from(document.querySelectorAll('a, span, div.tree-node, td'));
                    for (const el of elements) {
                        const txt = (el.textContent || '').trim();
                        if (txt.toLowerCase() === nombre.toLowerCase() || txt.includes(nombre)) {
                            el.click();
                            return true;
                        }
                    }
                    return false;
                }, nombreCarpeta);

                if (clickeado) {
                    await new Promise(r => setTimeout(r, 1200));
                    break;
                }
            }
        } catch (_) {}

        // ─── Paso 4: Extraer filas reales de notificaciones (Filtrando Basura) ───
        // Buscamos filas que tengan enlace o columna de asunto y columna con fecha válida
        const filasCandidatas = await frameBuzon.$$('tr.jqgrow, #tblMensajes tbody tr, .ui-jqgrid-btable tr, table.table tbody tr, table tbody tr');

        const filasMensajesValidos = [];

        for (const fila of filasCandidatas) {
            try {
                const metaFila = await frameBuzon.evaluate((row) => {
                    const cells = Array.from(row.querySelectorAll('td, div[role="gridcell"]'));
                    if (cells.length < 2) return null;

                    let fechaEncontrada = '';
                    let asuntoEncontrado = '';
                    let esPendiente = true;

                    // Buscar celda que contenga una fecha válida
                    for (const c of cells) {
                        const t = (c.textContent || '').trim();
                        if (/\d{2}\/\d{2}\/\d{4}/.test(t) || /\d{4}-\d{2}-\d{2}/.test(t)) {
                            fechaEncontrada = t;
                            break;
                        }
                    }

                    // Si no tiene fecha, es cabecera o fila de resumen (descartar)
                    if (!fechaEncontrada) return null;

                    // Buscar celda de asunto (priorizar enlaces o columnas intermedias descriptivas)
                    for (const c of cells) {
                        const link = c.querySelector('a');
                        const t = (link ? link.textContent : c.textContent || '').trim();

                        if (t.length >= 8 && !/\d{2}\/\d{2}\/\d{4}/.test(t) && !/^\d+$/.test(t)) {
                            asuntoEncontrado = t;
                            break;
                        }
                    }

                    if (!asuntoEncontrado) return null;

                    const cl = row.className || '';
                    if (cl.includes('leido') && !cl.includes('no-leido')) {
                        esPendiente = false;
                    }

                    return {
                        fecha: fechaEncontrada,
                        asunto: asuntoEncontrado,
                        esPendiente: esPendiente
                    };
                }, fila);

                if (metaFila && !esAsuntoInvalido(metaFila.asunto)) {
                    filasMensajesValidos.push({ fila, meta: metaFila });
                }
            } catch (_) {}
        }

        // ─── Paso 5: Lectura profunda del cuerpo de cada mensaje válido ──────
        const maxProcesar = Math.min(filasMensajesValidos.length, 15);

        for (let i = 0; i < maxProcesar; i++) {
            try {
                const { fila, meta } = filasMensajesValidos[i];
                let contenidoHtml = null;

                // CRÍTICO: Hacer clic en el enlace del asunto para abrir el detalle completo
                try {
                    const enlaceClic = await fila.$('a, td:nth-child(2) a, td:nth-child(3) a, td.c-pointer, td');
                    if (enlaceClic) {
                        await enlaceClic.click();
                        await new Promise(r => setTimeout(r, 1400));

                        // Buscar contenedor del cuerpo del mensaje en todos los frames/modales
                        const framesToCheck = [frameBuzon, page, ...page.frames()];
                        for (const f of framesToCheck) {
                            try {
                                const selectorCuerpo = await f.$(
                                    '#divDetalle, #divMensaje, .modal-body, .ui-jqdialog-content, #cuerpoMensaje, #vistaMensaje, .mensaje-cuerpo, #divContenido, #txtMensaje, #divNotificacion'
                                );
                                if (selectorCuerpo) {
                                    const textoExtraido = await f.evaluate(el => el.innerHTML || el.innerText, selectorCuerpo);
                                    if (textoExtraido && textoExtraido.trim().length > 25) {
                                        contenidoHtml = textoExtraido.trim();
                                        break;
                                    }
                                }
                            } catch (_) {}
                        }

                        // Intentar cerrar el modal para no bloquear los siguientes clics
                        for (const f of framesToCheck) {
                            try {
                                const btnCerrar = await f.$(
                                    '#btnCerrar, .btn-close, .modal-header .close, #btnVolver, button[data-dismiss="modal"], #btnRegresar'
                                );
                                if (btnCerrar) {
                                    await btnCerrar.click();
                                    await new Promise(r => setTimeout(r, 350));
                                    break;
                                }
                            } catch (_) {}
                        }
                    }
                } catch (eClick) {
                    console.error(`Error al abrir mensaje ${i}:`, eClick.message);
                }

                // Si no se extrajo cuerpo o es muy corto (PDF anexo), inyectar fallback de PDF
                if (!contenidoHtml || contenidoHtml.length < 20) {
                    contenidoHtml = PDF_FALLBACK_TEXT;
                }

                resultadoAlertas.push({
                    plataforma: 'SUNAT',
                    asunto: meta.asunto.trim(),
                    fecha_recepcion: normalizarFecha(meta.fecha),
                    contenido: contenidoHtml.trim(),
                    estado: meta.esPendiente ? 'Pendiente' : 'Leído',
                    prioridad: calcularPrioridad(meta.asunto, contenidoHtml),
                });

            } catch (errFila) {
                console.error(`Error procesando mensaje válido ${i}:`, errFila.message);
            }
        }

        // ─── Paso 6: Unificar con mensajes interceptados limpios ─────────────
        const mapaFinal = new Map();

        resultadoAlertas.forEach(al => {
            if (!esAsuntoInvalido(al.asunto)) {
                const key = `${al.fecha_recepcion}|${al.asunto.toLowerCase()}`;
                mapaFinal.set(key, al);
            }
        });

        interceptedMessages.forEach(it => {
            if (!esAsuntoInvalido(it.asunto)) {
                const key = `${it.fecha}|${it.asunto.toLowerCase()}`;
                if (!mapaFinal.has(key)) {
                    mapaFinal.set(key, {
                        plataforma: 'SUNAT',
                        asunto: it.asunto,
                        fecha_recepcion: it.fecha,
                        contenido: it.contenido || PDF_FALLBACK_TEXT,
                        estado: it.estado || 'Pendiente',
                        prioridad: it.prioridad || calcularPrioridad(it.asunto),
                    });
                }
            }
        });

        const salida = Array.from(mapaFinal.values());

        clearTimeout(safetyTimeout);
        console.log(JSON.stringify(salida));

    } catch (error) {
        console.error('Error general durante extracción del Buzón SUNAT:', error.message);
        clearTimeout(safetyTimeout);
        const fallback = Array.from(new Set(resultadoAlertas.concat(interceptedMessages)))
            .filter(item => !esAsuntoInvalido(item.asunto));
        console.log(JSON.stringify(fallback));
    } finally {
        if (browser) {
            await browser.close();
        }
        process.exit(0);
    }
})();
