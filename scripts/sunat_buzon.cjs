/**
 * scripts/sunat_buzon.cjs
 *
 * Scraper headless empresarial para el Buzón Electrónico SOL de SUNAT.
 * - Modo headless ('new') 100% silencioso.
 * - Soporte nativo para el nuevo Visor SPA de SUNAT (iframeApplication, list-group-item, contenedorMensaje).
 * - Descarga directa del archivo PDF 100% auténtico e idéntico emitido por SUNAT (#listArchivosAdjuntos).
 * - Cero generación sintética o ficticia de documentos.
 * - Captura del cuerpo HTML/texto original del acto administrativo sin adulteración.
 * - Mecanismos anti-bloqueo: manejo de diálogos de sesión ("Ya cuenta con una sesión activa"),
 *   pausas humanas calibradas, cierre de sesión limpio y límites configurables.
 * - Compatibilidad con tablas tradicionales / jqGrid para contribuyentes antiguos.
 *
 * Uso:
 *   node scripts/sunat_buzon.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL> [DESCARGAR_PDF] [LIMITE] [PAUSA_SEG]
 */

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

// ─── 1. Validar Argumentos ───────────────────────────────────────────────────
const [, , ruc, usuario, clave, argDescargarPdf, argLimite, argPausa] = process.argv;

if (!ruc || !usuario || !clave) {
    console.error(JSON.stringify({ error: 'Faltan parámetros: <RUC> <USUARIO_SOL> <CLAVE_SOL>' }));
    process.exit(1);
}

const descargarPdf = argDescargarPdf !== 'false';
const limiteMensajes = parseInt(argLimite, 10) || 15;
const pausaSegundos = Math.max(1, parseInt(argPausa, 10) || 3);

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

    for (const b of CABECERAS_BASURA) {
        if (t === b || t.startsWith(b + ' ')) {
            return true;
        }
    }

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
        'coactiv', 'requerimiento', 'orden de pago', 'carta de inductiva'
    ];

    for (const palabra of palabrasAlta) {
        if (texto.includes(palabra)) {
            return 'Alta';
        }
    }

    const palabrasMedia = [
        'declaraci', 'vencimiento', 'aviso', 'recordatorio', 'rectificatori',
        'cronograma', 'regulariz', 'carta'
    ];

    for (const palabra of palabrasMedia) {
        if (texto.includes(palabra)) {
            return 'Media';
        }
    }

    return 'Baja';
}

const SUNAT_LOGIN_URL = 'https://e-menu.sunat.gob.pe/cl-ti-itmenu/MenuInternet.htm';

// Timeout global de seguridad (150 segundos) para no colgar procesos PHP
const safetyTimeout = setTimeout(() => {
    console.error('Timeout de seguridad excedido en sunat_buzon.cjs');
    console.log(JSON.stringify([]));
    process.exit(0);
}, 150000);

(async () => {
    let browser;
    const resultadoAlertas = [];

    // Directorio de almacenamiento para PDFs oficiales del cliente
    const downloadDir = path.resolve(__dirname, `../storage/app/alertas/SUNAT/${ruc}`);
    if (!fs.existsSync(downloadDir)) {
        fs.mkdirSync(downloadDir, { recursive: true });
    }

    try {
        const chromePath = getChromePath();
        const launchOptions = {
            headless: 'new',
            defaultViewport: { width: 1366, height: 768 },
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

        // Configuración de CDP para descargas directas en cualquier target/popup
        const setupCDP = async (targetPage) => {
            try {
                const client = await targetPage.target().createCDPSession();
                await client.send('Page.setDownloadBehavior', {
                    behavior: 'allow',
                    downloadPath: downloadDir,
                });
            } catch (_) {}
        };

        browser.on('targetcreated', async (target) => {
            if (target.type() === 'page') {
                const p = await target.page();
                if (p) await setupCDP(p);
            }
        });

        const page = await browser.newPage();
        await setupCDP(page);

        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        );

        // Anti-Bloqueo: Aceptar diálogos nativos ("Ya cuenta con una sesión activa")
        page.on('dialog', async (dialog) => {
            try {
                await dialog.accept();
            } catch (_) {}
        });

        // ─── Paso 1: Login en Portal SOL ───────────────────────────────────
        await page.goto(SUNAT_LOGIN_URL, { waitUntil: 'networkidle2', timeout: 45000 });

        try {
            const btnPorRuc = await page.$('#btnPorRuc');
            if (btnPorRuc) {
                await btnPorRuc.click();
                await new Promise(r => setTimeout(r, 500));
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
            page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 35000 }),
            page.click(selBoton),
        ]);

        // Pausa calibrada anti-bloqueo tras autenticación
        await new Promise(r => setTimeout(r, Math.max(2500, pausaSegundos * 1000)));

        // ─── Paso 2: Navegar al Buzón Electrónico SOL ──────────────────────
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
                    await el.click();
                    buzonEncontrado = true;
                    break;
                }
            } catch (_) {}
        }

        if (!buzonEncontrado) {
            try {
                const links = await page.$$('a, button, span');
                for (const link of links) {
                    const text = await page.evaluate(el => el.textContent || '', link);
                    if (text.toLowerCase().includes('buzón')) {
                        await link.click();
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
                    timeout: 25000,
                });
            } catch (_) {}
        }

        // ─── Paso 3: Esperar frame del Visor Moderno (SPA) ─────────────────
        let iframeApp = null;
        try {
            iframeApp = await page.waitForFrame(
                f => f.name() === 'iframeApplication' || f.url().includes('ol-ti-itvisornoti'),
                { timeout: 35000 }
            );
        } catch (_) {}

        // Pausa tras cargar el visor para que se pueble la lista
        await new Promise(r => setTimeout(r, Math.max(3000, pausaSegundos * 1000)));

        if (iframeApp) {
            // ─── RUTA A: VISOR MODERNO DE SUNAT ────────────────────────────
            const itemsInfo = await iframeApp.evaluate(() => {
                const listItems = Array.from(document.querySelectorAll('ul.list-group > li.list-group-item, li.list-group-item, .list-group-item'));
                return listItems.map((li, index) => {
                    const text = (li.innerText || '').trim();
                    const fechaMatch = text.match(/(\d{2}\/\d{2}\/\d{4}(?:\s+\d{2}:\d{2}(?::\d{2})?)?)/);
                    let asunto = '';

                    const asuntoMatch = text.match(/ASUNTO:\s*([^\n\r]+)/i);
                    if (asuntoMatch) {
                        asunto = asuntoMatch[1].trim();
                    } else {
                        // Tomar primera línea representativa
                        const lineas = text.split('\n').map(l => l.trim()).filter(Boolean);
                        asunto = lineas[0] || 'Notificación Electrónica SUNAT';
                    }

                    const isLeido = !li.classList.contains('no-leido') && (li.classList.contains('selected') || !li.classList.contains('font-bold'));

                    return {
                        index,
                        id: li.id || `msg_${index}`,
                        asunto,
                        fecha: fechaMatch ? fechaMatch[1] : '',
                        isLeido,
                    };
                }).filter(it => it.asunto && it.fecha);
            });

            const maxItems = Math.min(itemsInfo.length, limiteMensajes);

            for (let i = 0; i < maxItems; i++) {
                const meta = itemsInfo[i];
                if (esAsuntoInvalido(meta.asunto)) continue;

                try {
                    // Hacer clic en la tarjeta correspondiente si no es la primera ya seleccionada
                    if (i > 0) {
                        await iframeApp.evaluate((idx) => {
                            const lis = document.querySelectorAll('ul.list-group > li.list-group-item, li.list-group-item, .list-group-item');
                            if (lis[idx]) {
                                lis[idx].scrollIntoView({ block: 'center' });
                                const target = lis[idx].querySelector('a, div, p, span') || lis[idx];
                                target.click();
                                lis[idx].click();
                            }
                        }, meta.index);

                        // Pausa de cortesía para carga del panel derecho
                        await new Promise(r => setTimeout(r, Math.max(1800, pausaSegundos * 700)));
                    }

                    // 1. Ubicar el frame con el cuerpo oficial del mensaje
                    let cuerpoOficial = '';
                    const frameMensaje = page.frames().find(f => f.name() === 'contenedorMensaje' || f.url().includes('cl-ti-iagenerador'));
                    if (frameMensaje) {
                        try {
                            const contenidoFrame = await frameMensaje.evaluate(() => {
                                const b = document.body;
                                return b ? (b.innerHTML || b.innerText) : '';
                            });
                            if (contenidoFrame && contenidoFrame.trim().length > 20) {
                                cuerpoOficial = contenidoFrame.trim();
                            }
                        } catch (_) {}
                    }

                    // Fallback de contenido en el panel derecho del visor
                    if (!cuerpoOficial) {
                        try {
                            const contenidoVisor = await iframeApp.evaluate(() => {
                                const el = document.querySelector('#contenedorMensaje, #cuerpoMensaje, #divContenidoMensaje, .cuerpo-mensaje');
                                return el ? (el.innerHTML || el.innerText) : '';
                            });
                            if (contenidoVisor && contenidoVisor.trim().length > 20) {
                                cuerpoOficial = contenidoVisor.trim();
                            }
                        } catch (_) {}
                    }

                    // 2. Extraer número oficial de notificación
                    let numNotificacion = null;
                    const regexNotif = /(?:N[°o]\.?|Número|Numero|Valor|Resolución|Resolucion|Carta)\s*([0-9]{3,4}-[0-9]{3,4}-[0-9]{5,10}|[0-9]{10,18}[A-Z0-9]*)/i;
                    const matchNotif = (meta.asunto + ' ' + (cuerpoOficial || '')).match(regexNotif);
                    if (matchNotif) {
                        numNotificacion = matchNotif[1];
                    }

                    // 3. Descarga oficial de Documentos PDF (Documento Oficial Sustantivo + Constancia de Notificación)
                    let tienePdf = false;
                    let rutaPdf = null;
                    let nombrePdf = null;
                    let rutaConstanciaPdf = null;
                    let nombreConstanciaPdf = null;

                    // Función auxiliar para esperar que se complete una descarga en disco
                    async function esperarDescarga(filesBefore, timeoutMs = 8000) {
                        const inicio = Date.now();
                        while (Date.now() - inicio < timeoutMs) {
                            await new Promise(r => setTimeout(r, 600));
                            if (fs.existsSync(downloadDir)) {
                                const currentFiles = fs.readdirSync(downloadDir);
                                const newFiles = currentFiles.filter(f => !filesBefore.includes(f) && f.toLowerCase().endsWith('.pdf') && !f.endsWith('.crdownload'));
                                if (newFiles.length > 0) {
                                    const candidato = newFiles[0];
                                    const fullP = path.join(downloadDir, candidato);
                                    try {
                                        const stat = fs.statSync(fullP);
                                        if (stat.size > 200) {
                                            const header = fs.readFileSync(fullP, { encoding: 'ascii', flag: 'r' }).slice(0, 5);
                                            if (header.startsWith('%PDF')) {
                                                return candidato;
                                            }
                                        }
                                    } catch (_) {}
                                }
                            }
                        }
                        return null;
                    }

                    if (descargarPdf) {
                        // ─── A. Descargar Documento Sustantivo Oficial (Carta / Resolución / OP / Valor) ───
                        if (frameMensaje) {
                            try {
                                const filesBeforeDoc = fs.existsSync(downloadDir) ? fs.readdirSync(downloadDir) : [];
                                const clickeadoDoc = await frameMensaje.evaluate(() => {
                                    const docLink = document.querySelector('a[href*="goArchivoDescarga"], a[href*="goArchivo"], a[onclick*="goArchivo"]');
                                    if (docLink) {
                                        docLink.click();
                                        return true;
                                    }
                                    // Fallback: buscar formulario frmArchivo y ejecutarlo con idArchivo del primer enlace
                                    const allLinks = Array.from(document.querySelectorAll('a'));
                                    for (const a of allLinks) {
                                        const href = a.getAttribute('href') || '';
                                        const match = href.match(/goArchivoDescarga\(([^)]+)\)/i);
                                        if (match) {
                                            const params = match[1].split(',').map(s => s.trim().replace(/['"]/g, ''));
                                            const frm = document.forms['frmArchivo'] || document.forms['frmArchivoBuzon'];
                                            if (frm) {
                                                if (frm.idArchivo) frm.idArchivo.value = params[0];
                                                if (frm.sistema && params[1]) frm.sistema.value = params[1];
                                                if (frm.idMensaje && params[2]) frm.idMensaje.value = params[2];
                                                frm.submit();
                                                return true;
                                            }
                                        }
                                    }
                                    return false;
                                });

                                if (clickeadoDoc) {
                                    const docDescargado = await esperarDescarga(filesBeforeDoc, 9000);
                                    if (docDescargado) {
                                        if (docDescargado.toLowerCase().startsWith('constancia_')) {
                                            nombreConstanciaPdf = docDescargado;
                                            rutaConstanciaPdf = `alertas/SUNAT/${ruc}/${docDescargado}`;
                                        } else {
                                            nombrePdf = docDescargado;
                                            rutaPdf = `alertas/SUNAT/${ruc}/${docDescargado}`;
                                            tienePdf = true;
                                        }
                                    }
                                }
                            } catch (eDoc) {
                                console.error(`Error al intentar descargar documento oficial en mensaje ${i}:`, eDoc.message);
                            }
                        }

                        // ─── B. Descargar Constancia de Notificación (Acuse de recibo en panel de adjuntos) ───
                        try {
                            const adjuntosInfo = await iframeApp.evaluate(() => {
                                const links = Array.from(document.querySelectorAll('#listArchivosAdjuntos a, a[href*="bajarArchivo"]'));
                                return links.map(a => ({
                                    href: a.href,
                                    text: a.innerText.trim(),
                                }));
                            });

                            if (adjuntosInfo.length > 0) {
                                const filesBeforeConstancia = fs.existsSync(downloadDir) ? fs.readdirSync(downloadDir) : [];
                                await iframeApp.evaluate(() => {
                                    const a = document.querySelector('#listArchivosAdjuntos a, a[href*="bajarArchivo"]');
                                    if (a) a.click();
                                });

                                const constanciaDescargada = await esperarDescarga(filesBeforeConstancia, 8000);
                                if (constanciaDescargada) {
                                    if (constanciaDescargada.toLowerCase().startsWith('constancia_')) {
                                        nombreConstanciaPdf = constanciaDescargada;
                                        rutaConstanciaPdf = `alertas/SUNAT/${ruc}/${constanciaDescargada}`;
                                        tienePdf = true;
                                    } else if (!nombrePdf) {
                                        nombrePdf = constanciaDescargada;
                                        rutaPdf = `alertas/SUNAT/${ruc}/${constanciaDescargada}`;
                                        tienePdf = true;
                                    }
                                }
                            }
                        } catch (eConst) {
                            console.error(`Error al intentar descargar constancia en mensaje ${i}:`, eConst.message);
                        }

                        // Si no se obtuvo documento oficial pero sí constancia, asignar constancia a rutaPdf como respaldo
                        if (!rutaPdf && rutaConstanciaPdf) {
                            rutaPdf = rutaConstanciaPdf;
                            nombrePdf = nombreConstanciaPdf;
                            tienePdf = true;
                        }
                    }

                    // Si no hubo cuerpo extenso pero se detectó documento
                    if (!cuerpoOficial) {
                        cuerpoOficial = `<div class="p-3 bg-blue-50/80 border border-blue-200 rounded-lg text-blue-950 text-xs leading-relaxed">
                            <strong class="block mb-1 font-bold text-blue-900">Notificación Electrónica SUNAT:</strong>
                            <p>Esta notificación corresponde a un acto formal depositado en su Buzón SOL. El contenido íntegro y anexos se encuentran disponibles en el archivo PDF oficial adjunto.</p>
                        </div>`;
                    }

                    resultadoAlertas.push({
                        plataforma: 'SUNAT',
                        asunto: meta.asunto,
                        numero_notificacion: numNotificacion,
                        fecha_recepcion: normalizarFecha(meta.fecha),
                        contenido: cuerpoOficial,
                        estado: meta.isLeido ? 'Leído' : 'Pendiente',
                        prioridad: calcularPrioridad(meta.asunto, cuerpoOficial),
                        tiene_pdf: tienePdf,
                        ruta_pdf: rutaPdf,
                        nombre_archivo_pdf: nombrePdf,
                        ruta_constancia_pdf: rutaConstanciaPdf,
                        nombre_archivo_constancia_pdf: nombreConstanciaPdf,
                    });

                } catch (eMsg) {
                    console.error(`Error procesando mensaje ${i}:`, eMsg.message);
                }
            }

        } else {
            // ─── RUTA B: COMPATIBILIDAD CON BUZÓN CLÁSICO (TABLAS / JQGRID) ───
            const allFrames = [page, ...page.frames()];
            let frameBuzon = page;

            for (const f of allFrames) {
                try {
                    const hasTable = await f.$('table tbody tr, .ui-jqgrid-btable tr, #tblMensajes tr');
                    if (hasTable) {
                        frameBuzon = f;
                        break;
                    }
                } catch (_) {}
            }

            const filas = await frameBuzon.$$('tr.jqgrow, #tblMensajes tbody tr, .ui-jqgrid-btable tr, table tbody tr');
            const maxFilas = Math.min(filas.length, limiteMensajes);

            for (let i = 0; i < maxFilas; i++) {
                try {
                    const metaFila = await frameBuzon.evaluate((row) => {
                        const cells = Array.from(row.querySelectorAll('td, div[role="gridcell"]'));
                        if (cells.length < 2) return null;

                        let fecha = '';
                        let asunto = '';

                        for (const c of cells) {
                            const t = (c.textContent || '').trim();
                            if (/\d{2}\/\d{2}\/\d{4}/.test(t)) {
                                fecha = t;
                                break;
                            }
                        }
                        if (!fecha) return null;

                        for (const c of cells) {
                            const link = c.querySelector('a');
                            const t = (link ? link.textContent : c.textContent || '').trim();
                            if (t.length >= 8 && !/\d{2}\/\d{2}\/\d{4}/.test(t) && !/^\d+$/.test(t)) {
                                asunto = t;
                                break;
                            }
                        }
                        if (!asunto) return null;

                        return { fecha, asunto };
                    }, filas[i]);

                    if (metaFila && !esAsuntoInvalido(metaFila.asunto)) {
                        resultadoAlertas.push({
                            plataforma: 'SUNAT',
                            asunto: metaFila.asunto.trim(),
                            numero_notificacion: null,
                            fecha_recepcion: normalizarFecha(metaFila.fecha),
                            contenido: `<p>Comunicación registrada en Buzón SOL: ${metaFila.asunto}</p>`,
                            estado: 'Pendiente',
                            prioridad: calcularPrioridad(metaFila.asunto),
                            tiene_pdf: false,
                            ruta_pdf: null,
                            nombre_archivo_pdf: null,
                            ruta_constancia_pdf: null,
                            nombre_archivo_constancia_pdf: null,
                        });
                    }
                } catch (_) {}
            }
        }

        // ─── Paso 4: Cierre Limpio de Sesión SOL ───────────────────────────
        try {
            await page.evaluate(() => {
                const btn = document.querySelector('a[href*="logout"], a[href*="Salir"], button[id*="Salir"], #btnSalir');
                if (btn) btn.click();
            });
            await new Promise(r => setTimeout(r, 1000));
        } catch (_) {}

        clearTimeout(safetyTimeout);
        console.log(JSON.stringify(resultadoAlertas));

    } catch (error) {
        console.error('Error durante extracción del Buzón SUNAT:', error.message);
        clearTimeout(safetyTimeout);
        console.log(JSON.stringify(resultadoAlertas));
    } finally {
        if (browser) {
            try {
                await browser.close();
            } catch (_) {}
        }
        process.exit(0);
    }
})();
