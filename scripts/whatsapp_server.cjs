/**
 * scripts/whatsapp_server.cjs
 * 
 * Microservicio SaaS de WhatsApp para CRM Contable.
 * - Genera imagen QR en Base64 para escaneo directo desde la interfaz web.
 * - Gestiona estados: 'loading', 'qr_ready', 'connected', 'disconnected'.
 * - Controla endpoints REST: GET /status, POST /send-message, POST /logout.
 * - Persistencia de sesión mediante LocalAuth en .wwebjs_auth/.
 */

const { Client, LocalAuth } = require('whatsapp-web.js');
const QRCode = require('qrcode');
const express = require('express');
const cors = require('cors');
const path = require('path');
const fs = require('fs');

// Prevenir caída del proceso por promesas no controladas en Puppeteer/WhatsApp Web
process.on('unhandledRejection', (reason) => {
    console.warn('⚠️ [WhatsApp] Advertencia / Rejection interceptado:', reason?.message || reason);
});

process.on('uncaughtException', (err) => {
    console.error('❌ [WhatsApp] Excepción no controlada interceptada:', err.message);
});

// Asegurar que el directorio de trabajo sea la raíz del proyecto CRM
const projectRoot = path.resolve(__dirname, '..');
try {
    process.chdir(projectRoot);
} catch (_) {}

// Estado interno del robot (Modo SaaS)
let currentStatus = 'loading'; // 'loading' | 'authenticated' | 'qr_ready' | 'connected' | 'disconnected' | 'auth_failure' | 'error'
let qrCodeDataUrl = null;
let loadingPercent = null;
let loadingMessage = null;
let lastError = null;

// Detección automática del ejecutable de Chrome / Edge en Windows
const localAppData = process.env.LOCALAPPDATA || '';
const progFiles = process.env.ProgramFiles || 'C:\\Program Files';
const progFilesX86 = process.env['ProgramFiles(x86)'] || 'C:\\Program Files (x86)';

const possibleBrowsers = [
    process.env.PUPPETEER_EXECUTABLE_PATH,
    path.join(progFiles, 'Google\\Chrome\\Application\\chrome.exe'),
    path.join(progFilesX86, 'Google\\Chrome\\Application\\chrome.exe'),
    path.join(localAppData, 'Google\\Chrome\\Application\\chrome.exe'),
    'C:\\Program Files (x86)\\Google\\Chrome\\Application\\chrome.exe',
    'C:\\Program Files\\Google\\Chrome\\Application\\chrome.exe',
    path.join(progFiles, 'Microsoft\\Edge\\Application\\msedge.exe'),
    path.join(progFilesX86, 'Microsoft\\Edge\\Application\\msedge.exe'),
    path.join(localAppData, 'Microsoft\\Edge\\Application\\msedge.exe'),
    'C:\\Program Files (x86)\\Microsoft\\Edge\\Application\\msedge.exe',
    'C:\\Program Files\\Microsoft\\Edge\\Application\\msedge.exe',
    path.join(progFiles, 'BraveSoftware\\Brave-Browser\\Application\\brave.exe'),
    path.join(localAppData, 'BraveSoftware\\Brave-Browser\\Application\\brave.exe'),
].filter(Boolean);

let executablePath;
for (const browserPath of possibleBrowsers) {
    if (fs.existsSync(browserPath)) {
        executablePath = browserPath;
        break;
    }
}

const puppeteerOptions = {
    headless: true,
    args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--no-zygote',
        '--disable-gpu'
    ]
};

if (executablePath) {
    puppeteerOptions.executablePath = executablePath;
}

// Inicialización del cliente WhatsApp con LocalAuth
const authPath = path.join(projectRoot, '.wwebjs_auth');
let client = new Client({
    authStrategy: new LocalAuth({
        dataPath: authPath
    }),
    puppeteer: puppeteerOptions
});

// Configuración de eventos en el cliente WhatsApp
function setupClientListeners(clientInstance) {
    clientInstance.on('qr', async (qr) => {
        try {
            qrCodeDataUrl = await QRCode.toDataURL(qr, { margin: 2, scale: 6 });
            currentStatus = 'qr_ready';
            loadingPercent = null;
            loadingMessage = null;
            lastError = null;

            console.log('\n[WhatsApp] Código QR generado y disponible para escaneo en la interfaz web y terminal.');

            // Impresión en terminal como cortesía adicional
            try {
                const qrcodeTerminal = require('qrcode-terminal');
                qrcodeTerminal.generate(qr, { small: true });
            } catch (_) {}
        } catch (err) {
            console.error('Error al convertir QR a imagen DataURL:', err);
        }
    });

    clientInstance.on('authenticated', () => {
        currentStatus = 'authenticated';
        loadingMessage = 'Sesión autenticada. Sincronizando chats y mensajes...';
        lastError = null;
        console.log('🔑 Sesión de WhatsApp autenticada correctamente. Sincronizando datos...');
    });

    clientInstance.on('loading_screen', (percent, message) => {
        loadingPercent = percent;
        loadingMessage = message || 'Sincronizando chats...';
        console.log(`⏳ [WhatsApp] Sincronizando chats: ${percent}% - ${message || ''}`);
    });

    clientInstance.on('ready', () => {
        currentStatus = 'connected';
        qrCodeDataUrl = null;
        loadingPercent = 100;
        loadingMessage = 'Conectado y listo';
        lastError = null;
        console.log('✅ Robot de WhatsApp conectado y listo para despachar mensajes.');
    });

    clientInstance.on('auth_failure', (msg) => {
        currentStatus = 'auth_failure';
        qrCodeDataUrl = null;
        loadingPercent = null;
        lastError = String(msg || 'Fallo de autenticación');
        console.error('❌ Error de autenticación de WhatsApp:', msg);
    });

    clientInstance.on('disconnected', (reason) => {
        currentStatus = 'disconnected';
        qrCodeDataUrl = null;
        loadingPercent = null;
        lastError = String(reason || 'Desconectado');
        console.warn('⚠️ Sesión de WhatsApp desconectada:', reason);
    });
}

setupClientListeners(client);

// ─── Servidor Express y Rutas REST ────────────────────────────────────────────

const app = express();
app.use(cors());
app.use(express.json());

// 1. Endpoint de estado para polling reactivo desde Alpine.js
app.get('/status', (req, res) => {
    res.json({
        status: currentStatus,
        qr: qrCodeDataUrl,
        ready: currentStatus === 'connected',
        loading_percent: loadingPercent,
        loading_message: loadingMessage,
        error: lastError,
        timestamp: new Date().toISOString()
    });
});

// 2. Endpoint para desvincular la cuenta (Logout)
app.post('/logout', async (req, res) => {
    try {
        console.log('\n[WhatsApp] Solicitud de desvinculación recibida desde la interfaz web...');
        currentStatus = 'loading';
        qrCodeDataUrl = null;

        try {
            await client.logout();
        } catch (logoutErr) {
            console.warn('Aviso en logout:', logoutErr.message);
        }

        currentStatus = 'disconnected';

        // Reinicializar cliente tras breve espera para generar nuevo QR
        setTimeout(() => {
            console.log('[WhatsApp] Reinicializando cliente para nuevo escaneo...');
            client.initialize().catch(err => {
                console.error('Error al inicializar tras logout:', err.message);
            });
        }, 1200);

        return res.status(200).json({
            success: true,
            status: 'disconnected',
            message: 'Dispositivo desvinculado exitosamente. Generando nuevo código QR.'
        });
    } catch (error) {
        console.error('Error al desvincular sesión de WhatsApp:', error);
        return res.status(500).json({
            success: false,
            error: error.message || 'Error al desvincular dispositivo.'
        });
    }
});

// Endpoint para apagar limpiamente el servidor desde la interfaz web
app.post('/shutdown', async (req, res) => {
    try {
        console.log('\n[WhatsApp] Solicitud de apagado recibida desde la interfaz web...');
        res.status(200).json({
            success: true,
            message: 'Servidor de WhatsApp apagándose correctamente.'
        });

        setTimeout(async () => {
            try {
                if (client) {
                    await client.destroy();
                }
            } catch (_) {}
            process.exit(0);
        }, 500);
    } catch (error) {
        console.error('Error en apagado:', error);
        process.exit(0);
    }
});

// 3. Endpoint de despacho de mensajes
app.post('/send-message', async (req, res) => {
    try {
        const { phone, message } = req.body;

        if (!phone || !message) {
            return res.status(400).json({
                success: false,
                error: 'Se requieren los campos "phone" y "message".'
            });
        }

        if (currentStatus !== 'connected') {
            return res.status(503).json({
                success: false,
                status: currentStatus,
                error: 'El robot de WhatsApp aún no está conectado. Por favor, escanee el código QR en pantalla.',
                has_qr_pending: Boolean(qrCodeDataUrl)
            });
        }

        // Formateo del número telefónico
        let cleanPhone = String(phone).replace(/\D/g, '');

        // Si tiene 9 dígitos (celular peruano estándar), anteponer 51
        if (cleanPhone.length === 9) {
            cleanPhone = '51' + cleanPhone;
        }

        const phoneId = `${cleanPhone}@c.us`;

        // Despacho a través de whatsapp-web.js
        const sentMessage = await client.sendMessage(phoneId, message);

        return res.status(200).json({
            success: true,
            message: 'Mensaje enviado correctamente vía WhatsApp',
            phone: cleanPhone,
            phoneId: phoneId,
            messageId: sentMessage ? sentMessage.id?._serialized : null,
            timestamp: new Date().toISOString()
        });
    } catch (error) {
        console.error('Error al despachar mensaje en WhatsApp:', error);
        return res.status(500).json({
            success: false,
            error: error.message || 'Error interno al enviar mensaje por WhatsApp.'
        });
    }
});

// Endpoint para reiniciar por completo la sesión (borrar caché y generar nuevo QR)
app.post('/reset-session', async (req, res) => {
    try {
        console.log('\n[WhatsApp] Solicitud de reinicio total de sesión (hard reset)...');
        currentStatus = 'loading';
        loadingMessage = 'Limpiando sesión y reiniciando motor...';
        qrCodeDataUrl = null;
        lastError = null;

        try {
            await client.destroy();
        } catch (_) {}

        // Limpiar directorio de autenticación
        try {
            fs.rmSync(authPath, { recursive: true, force: true });
            console.log('🧹 Directorio .wwebjs_auth limpiado exitosamente.');
        } catch (rmErr) {
            console.warn('Aviso al limpiar authPath:', rmErr.message);
        }

        // Recrear instancia de cliente
        client = new Client({
            authStrategy: new LocalAuth({ dataPath: authPath }),
            puppeteer: puppeteerOptions
        });
        setupClientListeners(client);

        setTimeout(() => {
            initClient();
        }, 1500);

        return res.status(200).json({
            success: true,
            message: 'Sesión restablecida por completo. Generando nuevo código QR.'
        });
    } catch (error) {
        console.error('Error al restablecer sesión de WhatsApp:', error);
        return res.status(500).json({
            success: false,
            error: error.message || 'Error al restablecer sesión.'
        });
    }
});

// Función de inicialización resiliente con reintento ante recarga de WhatsApp Web
async function initClient() {
    try {
        currentStatus = 'loading';
        loadingMessage = 'Iniciando navegador y cargando WhatsApp Web...';
        lastError = null;

        console.log(`⏳ Inicializando cliente WhatsApp Web (LocalAuth: ${authPath})...\n`);
        await client.initialize();
    } catch (err) {
        lastError = err.message;
        console.error('❌ Error al inicializar cliente de WhatsApp:', err.message);

        // Si el contexto fue destruido por recarga interna de la página de WhatsApp, reintentar
        if (err.message && err.message.includes('Execution context was destroyed')) {
            console.log('🔄 Reintentando inicialización en 2.5s tras recarga de página...');
            setTimeout(() => {
                initClient();
            }, 2500);
            return;
        }

        currentStatus = 'error';
    }
}

// Cierre limpio de recursos al terminar proceso
const cleanExit = async () => {
    try {
        if (client) {
            await client.destroy();
        }
    } catch (_) {}
    process.exit(0);
};

process.on('SIGINT', cleanExit);
process.on('SIGTERM', cleanExit);

// Inicio del servidor HTTP y del cliente
const PORT = process.env.WHATSAPP_PORT || 3001;

app.listen(PORT, () => {
    console.log(`\n===========================================================`);
    console.log(`🚀 Microservicio SaaS de WhatsApp iniciado en http://localhost:${PORT}`);
    console.log(`📌 Endpoint de estado:   GET  http://localhost:${PORT}/status`);
    console.log(`📌 Endpoint de logout:   POST http://localhost:${PORT}/logout`);
    console.log(`📌 Endpoint de envío:    POST http://localhost:${PORT}/send-message`);
    console.log(`📌 Endpoint de reset:    POST http://localhost:${PORT}/reset-session`);
    console.log(`===========================================================`);

    initClient();
});
