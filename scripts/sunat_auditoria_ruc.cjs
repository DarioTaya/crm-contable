/**
 * scripts/sunat_auditoria_ruc.cjs
 *
 * Scraper forense headless para la Consulta RUC Extendida de SUNAT (e-consultaruc.sunat.gob.pe).
 * Extrae en paralelo (vía sesiones y fetch interno en página) toda la ficha tributaria pública:
 * - Datos generales, Tipo de Contribuyente, Fecha de Inscripción, Fecha de Inicio de Actividades
 * - Estado del Contribuyente (incluyendo Fecha de Baja si aplica) y Condición de Domicilio Fiscal
 * - Actividades Económicas (CIIU Principal y Secundarias)
 * - Sistema de Emisión Electrónica y Fecha desde que es Emisor Electrónico
 * - Padrones Especiales (Agente de Retención, Percepción, Buen Contribuyente, Excluido, etc.)
 * - Representantes Legales vigentes (Tipo Doc, Nro Doc, Nombre, Cargo, Fecha Desde)
 * - Deuda en Cobranza Coactiva remitida a centrales de riesgo (Monto, Período, Fecha Inicio, Entidad)
 * - Omisiones Tributarias de los últimos 5 años
 * - Cantidad de Trabajadores y Prestadores de Servicios (últimos 12 períodos PLAME)
 * - Establecimientos Anexos declarados (Depósitos, Sucursales, Locales Comerciales)
 * - Actas Probatorias e Información Histórica de Bajas / Condición No Hallado o No Habido
 *
 * Uso:
 *   node scripts/sunat_auditoria_ruc.cjs <RUC>
 */

const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer');

const [,, rucArg] = process.argv;
const ruc = (rucArg || '').replace(/\D/g, '');

if (ruc.length !== 11) {
    console.log(JSON.stringify({
        success: false,
        error: 'El RUC debe tener exactamente 11 dígitos.'
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
        'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
        path.join(progFiles, 'Microsoft\\Edge\\Application\\msedge.exe'),
    ];

    for (const candidate of candidates) {
        if (candidate && fs.existsSync(candidate)) {
            return candidate;
        }
    }
    return undefined;
}

(async () => {
    let browser = null;
    const t0 = Date.now();

    try {
        browser = await puppeteer.launch({
            headless: 'new',
            executablePath: getChromePath(),
            args: [
                '--no-sandbox',
                '--disable-setuid-sandbox',
                '--disable-dev-shm-usage',
                '--disable-blink-features=AutomationControlled',
                '--disable-gpu',
                '--no-first-run',
            ]
        });

        const page = await browser.newPage();
        await page.setUserAgent('Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36');

        // Bloquear imágenes y fuentes innecesarias para máxima velocidad
        await page.setRequestInterception(true);
        page.on('request', (req) => {
            const type = req.resourceType();
            if (type === 'image' || type === 'font' || type === 'media') {
                req.abort();
            } else {
                req.continue();
            }
        });

        await page.goto('https://e-consultaruc.sunat.gob.pe/cl-ti-itmrconsruc/FrameCriterioBusquedaWeb.jsp', {
            waitUntil: 'domcontentloaded',
            timeout: 12000
        });

        await page.waitForSelector('#txtRuc', { timeout: 8000 });
        await page.type('#txtRuc', ruc);

        await Promise.all([
            page.waitForNavigation({ waitUntil: 'domcontentloaded', timeout: 12000 }),
            page.click('#btnAceptar')
        ]);

        const payload = await page.evaluate(async () => {
            const clean = (str) => (str || '').replace(/\s+/g, ' ').trim();

            const rawFields = {};
            document.querySelectorAll('.list-group-item').forEach((el) => {
                const headings = el.querySelectorAll('.list-group-item-heading');
                const texts = el.querySelectorAll('.list-group-item-text');

                if (headings.length === 1 && texts.length >= 1) {
                    const key = clean(headings[0].innerText);
                    const val = Array.from(texts).map(t => clean(t.innerText)).join(' | ');
                    rawFields[key] = val;
                } else if (headings.length >= 2) {
                    const rowCols = el.querySelectorAll('.col-sm-3, .col-sm-4, .col-sm-5, .col-sm-6, .col-sm-7, .col-sm-9');
                    if (rowCols.length >= 2) {
                        for (let i = 0; i < rowCols.length - 1; i += 2) {
                            const k = clean(rowCols[i].innerText);
                            const v = clean(rowCols[i + 1].innerText);
                            if (k) rawFields[k] = v;
                        }
                    } else {
                        const key = clean(headings[0].innerText);
                        rawFields[key] = clean(el.innerText);
                    }
                } else if (headings.length === 1) {
                    const key = clean(headings[0].innerText);
                    const full = clean(el.innerText);
                    rawFields[key] = full.startsWith(key) ? clean(full.slice(key.length)) : full;
                }

                // Tabla interna para Actividades Económicas, Comprobantes, Padrones, etc.
                const innerTable = el.querySelector('table');
                if (innerTable && headings.length >= 1) {
                    const key = clean(headings[0].innerText);
                    const rows = Array.from(innerTable.querySelectorAll('tr'))
                        .map(tr => clean(tr.innerText))
                        .filter(Boolean);
                    if (rows.length > 0) {
                        rawFields[key] = rows.join(' ; ');
                    }
                }
            });

            // Ejecutar consultas paralelas a las sub-secciones usando la sesión activa de SUNAT
            const subForms = [
                'formRepLeg',
                'formInfoDeudaCoactiva',
                'formInfoOmisionTributaria',
                'formNumTrabajd',
                'formLocAnex',
                'forminfoHist',
                'formActPro'
            ];

            const subResults = {};

            await Promise.all(subForms.map(async (fName) => {
                const f = document.forms[fName];
                if (!f) {
                    subResults[fName] = { available: false, tables: [], messages: [] };
                    return;
                }

                const formData = new URLSearchParams(new FormData(f));
                try {
                    const res = await fetch(f.action, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: formData.toString()
                    });
                    const buffer = await res.arrayBuffer();
                    const decoder = new TextDecoder('iso-8859-1');
                    const html = decoder.decode(buffer);
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    const tables = [];
                    doc.querySelectorAll('table').forEach(t => {
                        const rows = [];
                        t.querySelectorAll('tr').forEach(tr => {
                            const cells = Array.from(tr.querySelectorAll('th, td')).map(c => clean(c.innerText));
                            if (cells.length > 0 && cells.some(c => c !== '')) {
                                rows.push(cells);
                            }
                        });
                        if (rows.length > 0) {
                            tables.push(rows);
                        }
                    });

                    const messages = Array.from(doc.querySelectorAll('.panel-body, .list-group-item-text, h5'))
                        .map(e => clean(e.innerText))
                        .filter(txt => txt.length > 10 && txt.length < 350);

                    subResults[fName] = { available: true, tables, messages };
                } catch (err) {
                    subResults[fName] = { available: false, error: err.message, tables: [], messages: [] };
                }
            }));

            return { rawFields, subResults };
        });

        await browser.close();
        browser = null;

        console.log(JSON.stringify({
            success: true,
            ruc,
            latencia_ms: Date.now() - t0,
            ...payload
        }));
    } catch (error) {
        if (browser) {
            try { await browser.close(); } catch (_) {}
        }
        console.log(JSON.stringify({
            success: false,
            ruc,
            latencia_ms: Date.now() - t0,
            error: error.message || 'Error al consultar el portal de SUNAT.'
        }));
    }
})();
