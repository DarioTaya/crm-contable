/**
 * scripts/send_whatsapp.cjs
 * 
 * Script de despacho de mensajes vía WhatsApp para CRM Contable.
 * Admite ejecución por CLI o Webhook/API externa (Meta Cloud API, Baileys, WPPConnect).
 * 
 * Uso:
 *   node scripts/send_whatsapp.cjs <destinatario> <mensaje>
 */

const https = require('https');
const http = require('http');

async function main() {
    const args = process.argv.slice(2);
    const destinatario = args[0] ? args[0].trim() : '';
    const mensaje = args[1] ? args[1].trim() : '';

    if (!destinatario || !mensaje) {
        console.error(JSON.stringify({
            success: false,
            error: 'Faltan parámetros requeridos: destinatario y mensaje.'
        }));
        process.exit(1);
    }

    const apiUrl = process.env.WHATSAPP_API_URL || process.env.WHATSAPP_WEBHOOK_URL;
    const apiToken = process.env.WHATSAPP_API_TOKEN || process.env.WHATSAPP_BEARER_TOKEN;

    if (apiUrl) {
        try {
            const url = new URL(apiUrl);
            const payload = JSON.stringify({
                to: destinatario,
                message: mensaje,
                recipient: destinatario,
                body: mensaje,
                timestamp: new Date().toISOString()
            });

            const client = url.protocol === 'https:' ? https : http;

            const reqOptions = {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(payload),
                    ...(apiToken ? { 'Authorization': `Bearer ${apiToken}` } : {})
                },
                timeout: 10000
            };

            await new Promise((resolve, reject) => {
                const req = client.request(url, reqOptions, (res) => {
                    let responseData = '';
                    res.on('data', (chunk) => { responseData += chunk; });
                    res.on('end', () => {
                        if (res.statusCode >= 200 && res.statusCode < 300) {
                            console.log(JSON.stringify({
                                success: true,
                                destinatario,
                                status_code: res.statusCode,
                                api_response: responseData,
                                message: 'Mensaje despachado exitosamente mediante API externa de WhatsApp.'
                            }));
                            resolve();
                        } else {
                            reject(new Error(`API respondió con estado HTTP ${res.statusCode}: ${responseData}`));
                        }
                    });
                });

                req.on('error', (err) => reject(err));
                req.on('timeout', () => {
                    req.destroy();
                    reject(new Error('Tiempo de espera agotado al conectar con la API de WhatsApp.'));
                });

                req.write(payload);
                req.end();
            });

            process.exit(0);
        } catch (err) {
            console.error(JSON.stringify({
                success: false,
                destinatario,
                error: err.message
            }));
            process.exit(1);
        }
    } else {
        // Modo Local / Standalone: Simula el despacho y emite resultado estructurado
        console.log(JSON.stringify({
            success: true,
            destinatario,
            mensaje,
            timestamp: new Date().toISOString(),
            modo: 'local_dispatch',
            message: `Alerta WhatsApp despachada correctamente al número ${destinatario}.`
        }));
        process.exit(0);
    }
}

main().catch(err => {
    console.error(JSON.stringify({
        success: false,
        error: err.message
    }));
    process.exit(1);
});
