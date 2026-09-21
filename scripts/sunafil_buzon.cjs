/**
 * scripts/sunafil_buzon.cjs
 *
 * Scraper headless avanzado 'Sniper' para la Casilla Electrónica de SUNAFIL.
 * - Modo headless ('new') 100% silencioso y en segundo plano.
 * - Autenticación con Clave SOL en el portal SUNAFIL (RUC, Usuario, Clave SOL).
 * - Excluye expresamente 'Página de inicio' y 'Sunafil te Orienta'.
 * - Navegación específica 'Sniper' por las 4 bandejas críticas del menú lateral:
 *     A) Actos Administrativos -> Acciones Previas
 *     B) Notificaciones de Fiscalización
 *     C) Notificaciones de Cobranza
 *     D) Alertas -> Formalización y Seguridad y Salud en el Trabajo
 * - FIX CRÍTICO:
 *     - Espera explícita de actualización del contenedor de la tabla central (DataTables).
 *     - Validación de tabla vacía: si existe 'td.dataTables_empty' o 'Ningún dato disponible en esta tabla',
 *       retorna array vacío para esa sección y continúa con la siguiente bandeja.
 *     - Extracción estricta sobre 'tbody > tr' directos del Data Grid central (excluye cualquier tabla de sidebar o menú).
 *     - Mapeo estricto de columnas:
 *         * Asunto: 'Tipo de Requerimiento' (ej. CARTAS DISUASIVAS GENERAL)
 *         * Fecha: 'Fecha de Depósito'
 *         * Expediente: 'Registro' (ej. 0000007676 - 2026 - SUNAFIL)
 *         * Filtro Anti-Leídos: Si 'Fecha Acuse de Recibo' tiene fecha registrada, se ignora la fila.
 *
 * Uso:
 *   node scripts/sunafil_buzon.cjs <RUC> <USUARIO_SOL> <CLAVE_SOL>
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

// ─── 3. Utilidades de Texto y Fecha ──────────────────────────────────────────
function normalizarTexto(s) {
    return (s || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim()
        .toLowerCase();
}

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
        return isoMatch[4]
            ? `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]} ${isoMatch[4]}`
            : `${isoMatch[1]}-${isoMatch[2]}-${isoMatch[3]} 08:00:00`;
    }

    return new Date().toISOString().slice(0, 19).replace('T', ' ');
}

// ─── 4. Diccionario Laboral de Alta Prioridad SUNAFIL ─────────────────────────
function calcularPrioridadSunafil(asunto, contenido = '') {
    const texto = `${asunto} ${contenido}`.toLowerCase();

    const palabrasAlta = [
        'carta disuasiva', 'cartas disuasivas', 'disuasiv',
        'inductiv', 'carta inductiva', 'cartas inductivas',
        'resoluci', 'resolución', 'resolucion',
        'infracci', 'infracción', 'infraccion', 'acta de infraccion', 'acta de infracción',
        'formalizaci', 'formalización', 'formalizacion',
        'sst', 'seguridad y salud',
        'comparecencia', 'inspectiva', 'multa', 'requerimiento',
        'medida', 'sancion', 'sanción', 'paralizacion', 'paralización', 'cierre'
    ];

    for (const palabra of palabrasAlta) {
        if (palabra === 'sst') {
            if (/\bsst\b/i.test(texto)) {
                return 'Alta';
            }
            continue;
        }
        if (texto.includes(palabra)) {
            return 'Alta';
        }
    }

    const palabrasMedia = [
        'aviso', 'recordatorio', 'prevenci', 'orientaci', 'informativ',
        'comunicacion', 'comunicación', 'plazo', 'cronograma'
    ];

    for (const palabra of palabrasMedia) {
        if (texto.includes(palabra)) {
            return 'Media';
        }
    }

    return 'Baja';
}

const CABECERAS_BASURA = [
    'nro', 'número', 'numero', 'fecha', 'asunto', 'documento', 'estado', 'acciones',
    'total', 'tipo', 'remitente', 'descargar', 'ver', 'detalle', 'tipo de requerimiento',
    'fecha de deposito', 'registro', 'acuse', 'fecha acuse de recibo'
];

const NOMBRES_MENU_EXCLUIDOS = [
    'actos administrativos',
    'acciones previas',
    'notificaciones de fiscalizacion',
    'notificaciones de fiscalización',
    'notificaciones de cobranza',
    'alertas',
    'formalizacion',
    'formalización',
    'seguridad y salud en el trabajo',
    'pagina de inicio',
    'sunafil te orienta',
    'te orienta',
    'cerrar sesion',
    'bandeja',
    'casilla',
    'menu',
    'sidebar'
];

function esAsuntoInvalido(asunto) {
    if (!asunto) return true;
    const t = normalizarTexto(asunto);
    if (t.length < 4) return true;

    // Descartar cabeceras conocidas
    for (const b of CABECERAS_BASURA) {
        if (t === b || t.startsWith(b + ' ')) {
            return true;
        }
    }

    // Descartar nombres de menús o navegación del sidebar
    for (const m of NOMBRES_MENU_EXCLUIDOS) {
        if (t === m) {
            return true;
        }
    }

    return false;
}

// ─── 5. Configuración de Bandejas 'Sniper' ─────────────────────────────────────
const BANDEJAS_OBJETIVO = [
    {
        codigo: 'ACCIONES_PREVIAS',
        nombre: 'Acciones Previas',
        menuPadre: 'Actos Administrativos',
        subItem: 'Acciones Previas',
    },
    {
        codigo: 'NOTIF_FISCALIZACION',
        nombre: 'Notificaciones de Fiscalización',
        menuPadre: 'Notificaciones de Fiscalización',
        subItem: null,
    },
    {
        codigo: 'NOTIF_COBRANZA',
        nombre: 'Notificaciones de Cobranza',
        menuPadre: 'Notificaciones de Cobranza',
        subItem: null,
    },
    {
        codigo: 'ALERTAS_FORMALIZACION',
        nombre: 'Alertas - Formalización',
        menuPadre: 'Alertas',
        subItem: 'Formalización',
    },
    {
        codigo: 'ALERTAS_SST',
        nombre: 'Alertas - Seguridad y Salud en el Trabajo',
        menuPadre: 'Alertas',
        subItem: 'Seguridad y Salud en el Trabajo',
    },
];

const SUNAFIL_LOGIN_URL = 'https://casillaelectronica.sunafil.gob.pe/si.inbox/Login/SUNAT';

// Timeout global de seguridad (100 segundos) para no colgar procesos PHP
const safetyTimeout = setTimeout(() => {
    console.error('Timeout de seguridad excedido en sunafil_buzon.cjs');
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
        const page = await browser.newPage();

        await page.setUserAgent(
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36'
        );

        // Intercepción pasiva secundaria de respuestas JSON
        page.on('response', async (res) => {
            try {
                const url = res.url().toLowerCase();
                const contentType = res.headers()['content-type'] || '';
                if (
                    contentType.includes('application/json') ||
                    url.includes('casilla') ||
                    url.includes('notifica') ||
                    url.includes('bandeja') ||
                    url.includes('listar')
                ) {
                    const text = await res.text();
                    try {
                        const data = JSON.parse(text);
                        const items = Array.isArray(data)
                            ? data
                            : (data.rows || data.data || data.mensajes || data.notificaciones || data.items || data.lista || []);

                        if (Array.isArray(items) && items.length > 0) {
                            items.forEach(it => {
                                const asunto = it.desTipoRequerimiento || it.tipoRequerimiento || it.nomRequerimiento || it.desAsunto || it.asunto || it.tema || '';
                                const fecha = it.fecDeposito || it.fechaDeposito || it.fecNotificacion || it.fecha || '';
                                const registro = it.nroRegistro || it.numRegistro || it.registro || it.nroExpediente || '';
                                const fechaAcuse = it.fecAcuseRecibo || it.fecAcuse || it.fechaAcuse || '';
                                const cuerpo = it.desCuerpo || it.cuerpo || it.contenido || it.mensaje || '';

                                // Si tiene fecha de acuse o está marcado leído, ignorar
                                const tieneAcuse = fechaAcuse && String(fechaAcuse).trim().length > 5 && /\d{2}\/\d{2}\/\d{4}/.test(String(fechaAcuse));
                                if (tieneAcuse || it.flgLeido === '1' || it.leido === true) {
                                    return;
                                }

                                if (asunto && !esAsuntoInvalido(String(asunto))) {
                                    const contenidoFormateado = `Registro: ${registro || 'S/N'}\nTipo de Requerimiento: ${asunto}\n${cuerpo ? cuerpo : 'Documento anexo en Casilla Electrónica SUNAFIL.'}`;
                                    interceptedMessages.push({
                                        asunto: String(asunto).trim(),
                                        fecha: normalizarFecha(fecha),
                                        contenido: contenidoFormateado,
                                        estado: 'Pendiente',
                                        prioridad: calcularPrioridadSunafil(asunto, contenidoFormateado),
                                        plataforma: 'SUNAFIL',
                                        registro: String(registro).trim()
                                    });
                                }
                            });
                        }
                    } catch (_) {}
                }
            } catch (_) {}
        });

        // ─── Paso 1: Login en SUNAFIL con Clave SOL ─────────────────────────
        await page.goto(SUNAFIL_LOGIN_URL, { waitUntil: 'domcontentloaded', timeout: 50000 });

        try {
            await page.evaluate(() => {
                if (typeof direc !== 'undefined' && direc) {
                    window.location.replace(direc);
                }
            });
        } catch (_) {}

        await page.waitForSelector('#txtRuc, input[name="txtRuc"]', { timeout: 35000 });

        try {
            const btnPorRuc = await page.$('#btnPorRuc');
            if (btnPorRuc) {
                await btnPorRuc.click();
                await new Promise(r => setTimeout(r, 400));
            }
        } catch (_) {}

        const selRuc = (await page.$('#txtRuc')) ? '#txtRuc' : 'input[name="txtRuc"]';
        const selUsuario = (await page.$('#txtUsuario')) ? '#txtUsuario' : 'input[name="txtUsuario"]';
        const selClave = (await page.$('#txtContrasena')) ? '#txtContrasena' : 'input[name="txtContrasena"]';

        await page.click(selRuc, { clickCount: 3 });
        await page.type(selRuc, ruc, { delay: 20 });

        await page.click(selUsuario, { clickCount: 3 });
        await page.type(selUsuario, usuario, { delay: 20 });

        await page.click(selClave, { clickCount: 3 });
        await page.type(selClave, clave, { delay: 20 });

        const selBoton = (await page.$('#btnAceptar')) ? '#btnAceptar' : 'button[type="button"].btn-block, button[type="submit"]';

        await Promise.allSettled([
            page.waitForNavigation({ waitUntil: 'networkidle2', timeout: 30000 }),
            page.click(selBoton),
        ]);

        await new Promise(r => setTimeout(r, 2500));

        // ─── Paso 2: Navegación 'Sniper' por las Bandejas Objetivas ───────────
        const getActiveContexts = () => [page, ...page.frames()];

        for (const bandeja of BANDEJAS_OBJETIVO) {
            try {
                let menuClickeado = false;
                const contexts = getActiveContexts();

                for (const ctx of contexts) {
                    try {
                        const clickExitoso = await ctx.evaluate((padreText, hijoText) => {
                            const normalize = (s) => (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
                            const pTarget = normalize(padreText);

                            // Buscar exclusivamente en elementos del sidebar o barra de navegación
                            const items = Array.from(document.querySelectorAll('a, button, span, li, div.nav-item, .sidebar a, .treeview a'));

                            let padreEl = null;
                            for (const el of items) {
                                const t = normalize(el.textContent);
                                if (t.includes('pagina de inicio') || t.includes('te orienta') || t.includes('cerrar sesion')) {
                                    continue;
                                }
                                if (t === pTarget || (t.includes(pTarget) && t.length < pTarget.length + 25)) {
                                    padreEl = el;
                                    break;
                                }
                            }

                            if (!padreEl) return false;

                            padreEl.click();
                            return true;
                        }, bandeja.menuPadre, bandeja.subItem);

                        if (clickExitoso) {
                            menuClickeado = true;

                            if (bandeja.subItem) {
                                await new Promise(r => setTimeout(r, 900));

                                await ctx.evaluate((hijoText) => {
                                    const normalize = (s) => (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();
                                    const hTarget = normalize(hijoText);
                                    const keyword = hTarget.split(' ')[0];

                                    const subItems = Array.from(document.querySelectorAll('a, button, span, li, .treeview-menu a, .dropdown-item, .sidebar a'));
                                    for (const el of subItems) {
                                        const t = normalize(el.textContent);
                                        if (t.includes('pagina de inicio') || t.includes('te orienta')) continue;
                                        if (t === hTarget || t.includes(hTarget) || t.includes(keyword)) {
                                            el.click();
                                            return true;
                                        }
                                    }
                                    return false;
                                }, bandeja.subItem);
                            }

                            break;
                        }
                    } catch (_) {}
                }

                // A) Espera explícita a que el contenedor de la tabla central (.table, table tbody, DataTables) se actualice
                try {
                    await page.waitForFunction(() => {
                        const tbl = document.querySelector('.dataTables_wrapper table, table.dataTable, table.table, table tbody');
                        return !!tbl;
                    }, { timeout: 6000 });
                } catch (_) {}

                await new Promise(r => setTimeout(r, 2000));

                // ─── Paso 3: Mapeo Riguroso del Data Grid y Validación de Tabla Vacía ─
                for (const ctx of getActiveContexts()) {
                    try {
                        const resultadoBandeja = await ctx.evaluate((bandejaInfo) => {
                            const normalize = (s) => (s || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim().toLowerCase();

                            // Localizar exclusivamente tablas del contenedor principal (excluyendo cualquier sidebar/nav)
                            const todasTablas = Array.from(document.querySelectorAll('.dataTables_wrapper table, table.dataTable, table.table, table'));
                            const tablasPrincipales = todasTablas.filter(t => {
                                if (t.closest('nav, aside, .sidebar, .sidebar-menu, .treeview, #sidebar, header, footer, .navbar')) {
                                    return false;
                                }
                                return !!t.querySelector('tbody');
                            });

                            if (tablasPrincipales.length === 0) {
                                return [];
                            }

                            const alertasExtraidas = [];

                            for (const tabla of tablasPrincipales) {
                                // B) Validación de Tabla Vacía:
                                // Buscar si existe un elemento td con la clase dataTables_empty o texto 'Ningún dato disponible en esta tabla'
                                const tdVacio = tabla.querySelector('td.dataTables_empty, .dataTables_empty');
                                if (tdVacio) {
                                    // Tabla vacía: retorna array vacío y pasa a la siguiente sección
                                    continue;
                                }

                                const textoTbody = normalize(tabla.querySelector('tbody')?.textContent || '');
                                if (
                                    textoTbody.includes('ningun dato disponible en esta tabla') ||
                                    textoTbody.includes('ningún dato disponible en esta tabla') ||
                                    textoTbody.includes('sin registros') ||
                                    textoTbody.includes('no se encontraron registros')
                                ) {
                                    continue;
                                }

                                // 1. Mapeo de columnas a partir del thead de la tabla principal
                                const headerCells = Array.from(tabla.querySelectorAll('thead th, thead td, tr:first-child th'));
                                let colTipoReq = -1;
                                let colFechaDeposito = -1;
                                let colRegistro = -1;
                                let colFechaAcuse = -1;

                                headerCells.forEach((th, idx) => {
                                    const t = normalize(th.textContent);
                                    if (t.includes('requerimiento') || t.includes('tipo req') || t.includes('tipo de documento')) {
                                        if (colTipoReq === -1) colTipoReq = idx;
                                    }
                                    if (t.includes('deposito') || (t.includes('fecha') && !t.includes('acuse'))) {
                                        if (colFechaDeposito === -1) colFechaDeposito = idx;
                                    }
                                    if (t.includes('registro') || t.includes('expediente')) {
                                        if (colRegistro === -1) colRegistro = idx;
                                    }
                                    if (t.includes('acuse')) {
                                        colFechaAcuse = idx;
                                    }
                                });

                                // Si colTipoReq no fue detectado por thead, buscar columnas alternativas
                                if (colTipoReq === -1 && headerCells.length >= 3) {
                                    headerCells.forEach((th, idx) => {
                                        const t = normalize(th.textContent);
                                        if (t.includes('asunto') || t.includes('tema') || t.includes('descripcion')) {
                                            colTipoReq = idx;
                                        }
                                    });
                                }

                                // C) Extracción Correcta: Iterar estrictamente sobre los tr (filas) hijos directos del tbody
                                const filas = Array.from(tabla.querySelectorAll('tbody > tr'));

                                for (const row of filas) {
                                    const cells = Array.from(row.querySelectorAll(':scope > td'));
                                    if (cells.length < 2) continue;

                                    // Validación adicional de fila vacía
                                    if (cells.some(c => c.classList.contains('dataTables_empty') || c.textContent.includes('Ningún dato disponible'))) {
                                        continue;
                                    }

                                    // Crucial: Evaluar Fecha Acuse de Recibo
                                    // Si la columna 'Fecha Acuse de Recibo' tiene fecha o valor, IGNORAR (ya leído)
                                    if (colFechaAcuse !== -1 && cells[colFechaAcuse]) {
                                        const acuseTxt = (cells[colFechaAcuse].textContent || '').trim();
                                        if (acuseTxt.length >= 8 && /\d{2}\/\d{2}\/\d{4}/.test(acuseTxt)) {
                                            continue; // Ya leído / acusado
                                        }
                                    }

                                    // Extraer Asunto (Tipo de Requerimiento)
                                    let asunto = '';
                                    if (colTipoReq !== -1 && cells[colTipoReq]) {
                                        asunto = (cells[colTipoReq].textContent || '').trim();
                                    }

                                    if (!asunto || asunto.length < 4) {
                                        continue;
                                    }

                                    // Extraer Fecha (Fecha de Depósito)
                                    let fechaDeposito = '';
                                    if (colFechaDeposito !== -1 && cells[colFechaDeposito]) {
                                        fechaDeposito = (cells[colFechaDeposito].textContent || '').trim();
                                    }

                                    // Extraer Expediente (Registro)
                                    let registro = '';
                                    if (colRegistro !== -1 && cells[colRegistro]) {
                                        registro = (cells[colRegistro].textContent || '').trim();
                                    }

                                    const style = window.getComputedStyle(row);
                                    const esNegrita = style.fontWeight === 'bold' || parseInt(style.fontWeight) >= 600 ||
                                                      row.classList.contains('bold') || row.classList.contains('unread');

                                    alertasExtraidas.push({
                                        asunto: asunto,
                                        fecha_deposito: fechaDeposito,
                                        registro: registro || 'S/N',
                                        bandeja: bandejaInfo.nombre,
                                        es_negrita: esNegrita
                                    });
                                }
                            }

                            return alertasExtraidas;
                        }, bandeja);

                        if (Array.isArray(resultadoBandeja) && resultadoBandeja.length > 0) {
                            resultadoBandeja.forEach(f => {
                                if (!esAsuntoInvalido(f.asunto)) {
                                    const contenidoArmado = `Registro: ${f.registro}\nBandeja: ${f.bandeja}\nTipo de Requerimiento: ${f.asunto}\nFecha Depósito: ${f.fecha_deposito}\n\nEl documento anexo se encuentra disponible en la Casilla Electrónica de SUNAFIL.`;
                                    resultadoAlertas.push({
                                        plataforma: 'SUNAFIL',
                                        asunto: f.asunto.trim(),
                                        fecha_recepcion: normalizarFecha(f.fecha_deposito),
                                        contenido: contenidoArmado,
                                        estado: 'Pendiente',
                                        prioridad: calcularPrioridadSunafil(f.asunto, contenidoArmado),
                                        registro: f.registro
                                    });
                                }
                            });
                        }
                    } catch (_) {}
                }
            } catch (errBandeja) {
                console.error(`Aviso procesando bandeja ${bandeja.nombre}:`, errBandeja.message);
            }
        }

        // ─── Paso 4: Unificar con mensajes interceptados limpios ─────────────
        const mapaFinal = new Map();

        resultadoAlertas.forEach(al => {
            if (!esAsuntoInvalido(al.asunto)) {
                const key = `${al.fecha_recepcion}|${al.asunto.toLowerCase()}|${al.registro || ''}`;
                mapaFinal.set(key, al);
            }
        });

        interceptedMessages.forEach(it => {
            if (!esAsuntoInvalido(it.asunto)) {
                const key = `${it.fecha}|${it.asunto.toLowerCase()}|${it.registro || ''}`;
                if (!mapaFinal.has(key)) {
                    mapaFinal.set(key, {
                        plataforma: 'SUNAFIL',
                        asunto: it.asunto,
                        fecha_recepcion: it.fecha,
                        contenido: it.contenido,
                        estado: 'Pendiente',
                        prioridad: it.prioridad || calcularPrioridadSunafil(it.asunto, it.contenido)
                    });
                }
            }
        });

        const salida = Array.from(mapaFinal.values());

        clearTimeout(safetyTimeout);
        console.log(JSON.stringify(salida));

    } catch (error) {
        console.error('Error general durante extracción del Buzón SUNAFIL:', error.message);
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
