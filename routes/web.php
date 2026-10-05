<?php

use App\Http\Controllers\AbonoDeudaController;
use App\Http\Controllers\AjusteSistemaController;
use App\Http\Controllers\AlertaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CargaMasivaClienteController;
use App\Http\Controllers\CargaMasivaControlController;
use App\Http\Controllers\CatalogoSunatController;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\ComprobanteController;
use App\Http\Controllers\ConstanciaDeclaracionController;
use App\Http\Controllers\ControlBalanceController;
use App\Http\Controllers\ControlDeudaController;
use App\Http\Controllers\ControlMensualController;
use App\Http\Controllers\CronogramaSunatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificacionController;
use App\Http\Controllers\OperacionController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\QuickLinkController;
use App\Http\Controllers\SelectorEmpresaController;
use App\Http\Controllers\TipoCambioController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VisorXmlController;
use App\Http\Middleware\RecordarEmpresaActiva;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Rutas Públicas de Autenticación
|--------------------------------------------------------------------------
*/
Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Recuperación de Contraseña
Route::get('/forgot-password', [PasswordResetController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('password.reset');
Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('password.update');

/*
|--------------------------------------------------------------------------
| Rutas Protegidas (Requieren Sesión Activa)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', RecordarEmpresaActiva::class])->group(function () {

    // Dashboard Principal
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Control Mensual de Impuestos
    Route::get('/control-impuestos', [ControlMensualController::class, 'index'])->name('control.index');
    Route::get('/control', fn () => redirect()->route('control.index'));
    Route::post('/control/update', [ControlMensualController::class, 'update'])->name('control.update');
    Route::post('/control/liquidacion', [ControlMensualController::class, 'guardarLiquidacion'])->name('control.liquidacion');
    Route::post('/control/liquidacion/importar', [ConstanciaDeclaracionController::class, 'previsualizar'])->name('control.liquidacion.importar');
    Route::post('/control/liquidacion/limpiar', [ControlMensualController::class, 'limpiarLiquidacion'])->name('control.liquidacion.limpiar');
    Route::post('/control/deuda', [ControlMensualController::class, 'getDeuda'])->name('control.deuda');
    Route::post('/control/calcular-igv-justo', [ControlMensualController::class, 'calcularIgvJusto'])->name('control.calcular_igv_justo');
    Route::get('/control/carga-masiva/plantilla', [CargaMasivaControlController::class, 'plantilla'])->name('control.carga_masiva.plantilla');
    Route::post('/control/carga-masiva/previsualizar', [CargaMasivaControlController::class, 'previsualizar'])->name('control.carga_masiva.previsualizar');
    Route::post('/control/carga-masiva/aplicar', [CargaMasivaControlController::class, 'aplicar'])->name('control.carga_masiva.aplicar');

    // Control de Deudas (Dashboard)
    Route::get('/control-deudas', [ControlDeudaController::class, 'index'])->name('control_deudas.index');

    // Abonos de Deudas
    Route::post('/abonos-deudas', [AbonoDeudaController::class, 'store'])->name('abonos_deudas.store');
    Route::delete('/abonos-deudas/{abonoDeuda}', [AbonoDeudaController::class, 'destroy'])->name('abonos_deudas.destroy');

    // Control Anual de Balances
    Route::get('/control-balances', [ControlBalanceController::class, 'index'])->name('balances.index');
    Route::post('/control-balances/update', [ControlBalanceController::class, 'update'])->name('balances.update');

    // Buzón de Alertas SUNAT / SUNAFIL
    Route::get('/alertas', [AlertaController::class, 'index'])->name('alertas.index');
    Route::post('/alertas/sync-all', [AlertaController::class, 'syncAll'])->name('alertas.sync-all');
    Route::post('/alertas/sync-sunafil', [AlertaController::class, 'syncSunafil'])->name('alertas.sync-sunafil');
    Route::post('/alertas/{alerta}/mark-as-read', [AlertaController::class, 'markAsRead'])->name('alertas.mark-as-read');

    // Bóveda SIRE / Comprobantes
    Route::get('/comprobantes', [ComprobanteController::class, 'index'])->name('comprobantes.index');
    Route::get('/comprobantes/sync-status', [ComprobanteController::class, 'syncStatus'])->name('comprobantes.sync-status');
    Route::post('/comprobantes/sincronizar-lote', [ComprobanteController::class, 'sincronizarLote'])->name('comprobantes.sincronizar-lote');
    Route::post('/comprobantes/importar', [ComprobanteController::class, 'importar'])->name('comprobantes.importar');
    Route::get('/comprobantes/zip', [ComprobanteController::class, 'downloadZip'])->name('comprobantes.zip');
    Route::get('/comprobantes/export', [ComprobanteController::class, 'exportExcel'])->name('comprobantes.export');
    Route::post('/comprobantes/{comprobante}/reintentar-descarga', [ComprobanteController::class, 'reintentarDescarga'])->name('comprobantes.reintentar-descarga');
    Route::get('/comprobantes/{comprobante}/download/{tipo}', [ComprobanteController::class, 'downloadFile'])->name('comprobantes.download');

    // Visor XML y Conversor PDF
    Route::get('/visor-xml', [VisorXmlController::class, 'index'])->name('cpe.visor');
    Route::post('/visor-xml/parse', [VisorXmlController::class, 'parse'])->name('cpe.parse');
    Route::post('/visor-xml/descargar-pdf', [VisorXmlController::class, 'descargarPdf'])->name('cpe.descargar-pdf');

    // Acceso rápido de empresas (barra superior). La cartera no se incrusta en el layout.
    Route::get('/acceso-rapido/empresas', [SelectorEmpresaController::class, 'index'])->name('acceso-rapido.empresas');
    Route::post('/acceso-rapido/empresa', [SelectorEmpresaController::class, 'seleccionar'])->name('acceso-rapido.seleccionar');
    Route::post('/acceso-rapido/fijar', [SelectorEmpresaController::class, 'fijar'])->name('acceso-rapido.fijar');

    // Clientes y Expediente Digital
    Route::get('/clientes', [ClienteController::class, 'index'])->name('clientes.index');
    Route::get('/clientes/crear', [ClienteController::class, 'create'])->name('clientes.create');
    Route::post('/clientes', [ClienteController::class, 'store'])->name('clientes.store');
    Route::post('/clientes/validar-ruc', [ClienteController::class, 'validarRuc'])->name('clientes.validar-ruc');
    Route::post('/clientes/validar-sol', [ClienteController::class, 'validarCredencialesSol'])->name('clientes.validar-sol');
    Route::get('/clientes/carga-masiva/plantilla', [CargaMasivaClienteController::class, 'plantilla'])->name('clientes.carga_masiva.plantilla');
    Route::post('/clientes/carga-masiva/leer', [CargaMasivaClienteController::class, 'leer'])->name('clientes.carga_masiva.leer');
    Route::post('/clientes/carga-masiva/consultar', [CargaMasivaClienteController::class, 'consultar'])->name('clientes.carga_masiva.consultar');
    Route::post('/clientes/carga-masiva/registrar', [CargaMasivaClienteController::class, 'registrar'])->name('clientes.carga_masiva.registrar');

    // Rutas de Administración de Clientes, Papelera y Baja Lógica
    Route::middleware('admin')->group(function () {
        Route::get('/clientes/papelera', [ClienteController::class, 'papelera'])->name('clientes.papelera');
        Route::post('/clientes/restaurar-masivo', [ClienteController::class, 'restaurarMasivo'])->name('clientes.restaurar-masivo');
        Route::delete('/clientes/purgar-masivo', [ClienteController::class, 'purgarMasivo'])->name('clientes.purgar-masivo');
        Route::delete('/clientes/eliminar-masivo', [ClienteController::class, 'eliminarMasivo'])->name('clientes.eliminar-masivo');
        Route::post('/clientes/{id}/restaurar', [ClienteController::class, 'restaurar'])->name('clientes.restaurar');
        Route::delete('/clientes/{id}/forzar-eliminacion', [ClienteController::class, 'forzarEliminacion'])->name('clientes.forzar-eliminacion');
        Route::delete('/clientes/{cliente}', [ClienteController::class, 'destroy'])->name('clientes.destroy');
    });

    // Expediente del cliente. Cada ruta declara la habilidad de ClientePolicy
    // que la protege: el asistente solo alcanza su cartera asignada y el
    // administrador gobierna todo el estudio. El aislamiento entre estudios lo
    // resuelve antes EstudioScope, en el enlace del modelo.
    // Secciones del expediente. La ficha permanece en /clientes/{cliente}.
    // Notificaciones, impuestos y SIRE tienen ruta propia; ?tab= solo
    // redirige a esa ruta para no romper enlaces ya guardados.
    Route::get('/clientes/{cliente}/notificaciones', [ClienteController::class, 'show'])->name('clientes.notificaciones')->can('view', 'cliente');
    Route::get('/clientes/{cliente}/impuestos', [ClienteController::class, 'show'])->name('clientes.operaciones')->can('view', 'cliente');
    Route::get('/clientes/{cliente}/operaciones', [ClienteController::class, 'redirigirOperaciones'])->name('clientes.operaciones.legado')->can('view', 'cliente');
    Route::get('/clientes/{cliente}/sire', [ClienteController::class, 'show'])->name('clientes.sire')->can('view', 'cliente');
    Route::get('/clientes/{cliente}', [ClienteController::class, 'show'])->name('clientes.show')->can('view', 'cliente');
    Route::put('/clientes/{cliente}/datos-fiscales', [ClienteController::class, 'updateDatosFiscales'])->name('clientes.datos-fiscales.update')->can('update', 'cliente');
    Route::put('/clientes/{cliente}/estado-estudio', [ClienteController::class, 'updateEstadoEstudio'])->name('clientes.estado-estudio.update')->can('update', 'cliente');
    Route::post('/clientes/{cliente}/actualizar-estado-sunat', [ClienteController::class, 'actualizarEstadoSunat'])->name('clientes.actualizar-estado-sunat')->can('update', 'cliente');
    Route::put('/clientes/{cliente}/credenciales', [ClienteController::class, 'updateCredenciales'])->name('clientes.credenciales.update')->can('gestionarCredenciales', 'cliente');
    Route::put('/clientes/{cliente}/api-credentials', [ClienteController::class, 'updateApiCredentials'])->name('clientes.api-credentials.update')->can('gestionarCredenciales', 'cliente');
    Route::get('/clientes/{cliente}/sunat', [ClienteController::class, 'loginSunat'])->name('clientes.sunat')->can('sincronizar', 'cliente');
    Route::get('/clientes/{cliente}/sunafil', [ClienteController::class, 'loginSunafil'])->name('clientes.sunafil')->can('sincronizar', 'cliente');
    Route::post('/clientes/{cliente}/buzon/sync', [ClienteController::class, 'syncBuzon'])->name('clientes.buzon.sync')->can('sincronizar', 'cliente');
    Route::post('/clientes/{cliente}/sunafil/sync', [ClienteController::class, 'syncSunafilBuzon'])->name('clientes.sunafil.sync')->can('sincronizar', 'cliente');
    Route::patch('/notificaciones/{notificacion}/toggle', [NotificacionController::class, 'toggleLeido'])->name('notificaciones.toggle');
    Route::post('/clientes/{cliente}/operaciones', [OperacionController::class, 'storeOrUpdate'])->name('clientes.operaciones.store')->can('registrarOperaciones', 'cliente');

    // Cronograma Tributario SUNAT
    Route::get('/tipo-cambio', [TipoCambioController::class, 'index'])->name('tipo-cambio.index');
    Route::post('/tipo-cambio/sincronizar', [TipoCambioController::class, 'sincronizar'])->name('tipo-cambio.sincronizar');
    Route::get('/tipo-cambio/actual', [TipoCambioController::class, 'actual'])->name('tipo-cambio.actual');

    Route::get('/cronogramas', [CronogramaSunatController::class, 'index'])->name('cronogramas.index');
    Route::post('/cronogramas/update', [CronogramaSunatController::class, 'update'])->name('cronogramas.update');
    Route::post('/cronogramas/autogenerar-sire', [CronogramaSunatController::class, 'autogenerarSire'])->name('cronogramas.autogenerar-sire');
    Route::post('/cronogramas/calcular-balances', [CronogramaSunatController::class, 'calcularBalances'])->name('cronogramas.calcular-balances');
    Route::post('/cronogramas/restablecer', [CronogramaSunatController::class, 'restablecer'])->name('cronogramas.restablecer');

    // Perfil del Usuario en Sesión (Disponible para cualquier rol)
    Route::get('/perfil', [PerfilController::class, 'edit'])->name('perfil.edit');
    Route::put('/perfil', [PerfilController::class, 'update'])->name('perfil.update');
    Route::put('/perfil/password', [PerfilController::class, 'updatePassword'])->name('perfil.password');

    /*
    |--------------------------------------------------------------------------
    | Rutas Exclusivas para el Administrador del Estudio
    |--------------------------------------------------------------------------
    */
    Route::middleware('admin')->group(function () {

        // Gestión de Equipo & Control de Acceso (RBAC)
        Route::get('/usuarios', [UsuarioController::class, 'index'])->name('usuarios.index');
        Route::get('/usuarios/crear', [UsuarioController::class, 'create'])->name('usuarios.create');
        Route::post('/usuarios', [UsuarioController::class, 'store'])->name('usuarios.store');
        Route::get('/usuarios/{usuario}', [UsuarioController::class, 'show'])->name('usuarios.show');
        Route::get('/usuarios/{usuario}/editar', [UsuarioController::class, 'edit'])->name('usuarios.edit');
        Route::put('/usuarios/{usuario}', [UsuarioController::class, 'update'])->name('usuarios.update');
        Route::patch('/usuarios/{usuario}/toggle-activo', [UsuarioController::class, 'toggleActivo'])->name('usuarios.toggle-activo');

        // Ajustes del Sistema & Configuración de Bots
        Route::get('/ajustes', [AjusteSistemaController::class, 'index'])->name('ajustes.index');
        Route::get('/ajustes/marca', [AjusteSistemaController::class, 'previsualizarMarca'])->name('ajustes.marca');
        Route::post('/ajustes', [AjusteSistemaController::class, 'update'])->name('ajustes.update');
        Route::post('/ajustes/test-whatsapp', [AjusteSistemaController::class, 'testWhatsApp'])->name('ajustes.test-whatsapp');
        Route::get('/ajustes/whatsapp-status', [AjusteSistemaController::class, 'whatsappStatus'])->name('ajustes.whatsapp-status');
        Route::post('/ajustes/whatsapp-logout', [AjusteSistemaController::class, 'whatsappLogout'])->name('ajustes.whatsapp-logout');
        Route::post('/ajustes/whatsapp-start', [AjusteSistemaController::class, 'whatsappStart'])->name('ajustes.whatsapp-start');
        Route::post('/ajustes/whatsapp-stop', [AjusteSistemaController::class, 'whatsappStop'])->name('ajustes.whatsapp-stop');
        Route::post('/ajustes/whatsapp-restart', [AjusteSistemaController::class, 'whatsappRestart'])->name('ajustes.whatsapp-restart');
        Route::post('/ajustes/whatsapp-reset-session', [AjusteSistemaController::class, 'whatsappResetSession'])->name('ajustes.whatsapp-reset-session');

        // Accesos Rápidos (Consultas Rápidas / Enlaces Oficiales)
        Route::post('/ajustes/quick-links', [QuickLinkController::class, 'store'])->name('quick-links.store');
        Route::put('/ajustes/quick-links/{quickLink}', [QuickLinkController::class, 'update'])->name('quick-links.update');
        Route::delete('/ajustes/quick-links/{quickLink}', [QuickLinkController::class, 'destroy'])->name('quick-links.destroy');
        Route::post('/ajustes/quick-links/reorder', [QuickLinkController::class, 'reorder'])->name('quick-links.reorder');

        Route::get('/ajustes/codigos-sunat', [CatalogoSunatController::class, 'index'])->name('ajustes.codigos');
        Route::post('/ajustes/codigos-sunat/tributos', [CatalogoSunatController::class, 'storeTributo'])->name('ajustes.codigos.tributos.store');
        Route::put('/ajustes/codigos-sunat/tributos/{catalogoTributo}', [CatalogoSunatController::class, 'updateTributo'])->name('ajustes.codigos.tributos.update');
        Route::delete('/ajustes/codigos-sunat/tributos/{catalogoTributo}', [CatalogoSunatController::class, 'destroyTributo'])->name('ajustes.codigos.tributos.destroy');
        Route::post('/ajustes/codigos-sunat/medios', [CatalogoSunatController::class, 'storeMedio'])->name('ajustes.codigos.medios.store');
        Route::put('/ajustes/codigos-sunat/medios/{catalogoMedioPago}', [CatalogoSunatController::class, 'updateMedio'])->name('ajustes.codigos.medios.update');
        Route::delete('/ajustes/codigos-sunat/medios/{catalogoMedioPago}', [CatalogoSunatController::class, 'destroyMedio'])->name('ajustes.codigos.medios.destroy');
    });
});
