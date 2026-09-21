@extends('layouts.app')

@section('title', 'Ajustes del Sistema')
@section('header', 'Ajustes del Sistema')

@section('content')
    <div x-data="panelAjustes({
            razon_social: @js($ajustes->razon_social),
            ruc: @js($ajustes->ruc),
            representante: @js($ajustes->representante),
            correo_corporativo: @js($ajustes->correo_corporativo ?? ''),
            telefono_whatsapp: @js($ajustes->telefono_whatsapp ?? ''),
            frecuencia_cron_horas: {{ $ajustes->frecuencia_cron_horas }},
            notificar_whatsapp: {{ $ajustes->notificar_whatsapp ? 'true' : 'false' }},
            nivel_alerta_whatsapp: @js($ajustes->nivel_alerta_whatsapp),
        })"
         x-cloak
         class="space-y-8 max-w-7xl mx-auto">

        {{-- ════════════════════ TOAST FLOTANTE DE NOTIFICACIÓN ════════════════════ --}}
        <div x-show="toast.mostrar"
             x-transition:enter="transform ease-out duration-300 transition"
             x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
             x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-xl border px-4 py-3 shadow-xl backdrop-blur"
             :class="toast.tipo === 'success' ? 'bg-emerald-900/90 text-white border-emerald-500/50' : 'bg-rose-900/90 text-white border-rose-500/50'">
            <div class="flex h-7 w-7 items-center justify-center rounded-full"
                 :class="toast.tipo === 'success' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-rose-500/20 text-rose-300'">
                <template x-if="toast.tipo === 'success'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </template>
                <template x-if="toast.tipo === 'error'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </template>
            </div>
            <div>
                <p class="text-xs font-bold" x-text="toast.titulo"></p>
                <p class="text-[11px] opacity-90" x-text="toast.mensaje"></p>
            </div>
        </div>

        {{-- ════════════════════ ENCABEZADO Y DESCRIPCIÓN ════════════════════ --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200 pb-5">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 border border-brand-200 text-brand-600 shadow-xs">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Configuración Global</h2>
                        <p class="text-xs text-gray-500">Parámetros maestros del estudio contable, cadencia del robot RPA de SUNAT y canales de notificación.</p>
                    </div>
                </div>
            </div>

            {{-- Botón Guardar Cambios Superior --}}
            <div>
                <button type="button"
                        @click="guardarAjustes()"
                        :disabled="guardando"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white px-5 py-2.5 text-xs font-bold shadow-xs transition disabled:opacity-50 active:scale-95">
                    <svg x-show="!guardando" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <svg x-show="guardando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="guardando ? 'Guardando Ajustes...' : 'Guardar Ajustes'"></span>
                </button>
            </div>
        </div>

        {{-- ════════════════════ GRID DE 3 TARJETAS PREMIUM ════════════════════ --}}
        <form @submit.prevent="guardarAjustes()" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            {{-- ───────── CARD 1: PERFIL DEL ESTUDIO CONTABLE ───────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xs flex flex-col justify-between overflow-hidden">
                <div>
                    <div class="border-b border-gray-100 bg-gray-50/75 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-100 text-blue-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Perfil del Estudio Contable</h3>
                                <p class="text-[11px] text-gray-400">Identificación oficial de la firma</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-blue-50 border border-blue-200 px-2 py-0.5 text-[10px] font-bold text-blue-700">
                            Matriz
                        </span>
                    </div>

                    <div class="p-6 space-y-4 text-xs">
                        {{-- Razón Social --}}
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Razón Social o Nombre Comercial</label>
                            <input type="text"
                                   x-model="form.razon_social"
                                   required
                                   placeholder="Ej. Grupo DC Soluciones Contables SAC"
                                   class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                        </div>

                        {{-- RUC --}}
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">RUC de la Empresa</label>
                            <input type="text"
                                   x-model="form.ruc"
                                   maxlength="11"
                                   required
                                   placeholder="20601234567"
                                   class="w-full font-mono rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                        </div>

                        {{-- Representante Legal --}}
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Representante Legal / Titular</label>
                            <input type="text"
                                   x-model="form.representante"
                                   required
                                   placeholder="Ej. Sebastián Taya Yucra"
                                   class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                        </div>

                        {{-- Correo Corporativo --}}
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Correo Electrónico Corporativo</label>
                            <input type="email"
                                   x-model="form.correo_corporativo"
                                   placeholder="contacto@grupodc.pe"
                                   class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                        </div>

                        {{-- Teléfono de Contacto --}}
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1">Teléfono Principal / WhatsApp</label>
                            <input type="text"
                                   x-model="form.telefono_whatsapp"
                                   placeholder="+51 987 654 321"
                                   class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50/50 p-4 border-t border-gray-100 text-[11px] text-gray-500 flex items-center gap-2">
                    <svg class="h-4 w-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                    </svg>
                    <span>Estos datos encabezan los reportes y comunicaciones a clientes.</span>
                </div>
            </div>

            {{-- ───────── CARD 2: MOTOR DE AUTOMATIZACIÓN (RPA) ───────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xs flex flex-col justify-between overflow-hidden">
                <div>
                    <div class="border-b border-gray-100 bg-gray-50/75 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-100 text-purple-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Motor de Automatización (RPA)</h3>
                                <p class="text-[11px] text-gray-400">Scraper del Buzón Electrónico SOL</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2 py-0.5 text-[10px] font-bold text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Scheduler Activo
                        </span>
                    </div>

                    <div class="p-6 space-y-5 text-xs">
                        {{-- Select Frecuencia CRON --}}
                        <div>
                            <label class="block font-semibold text-gray-700 mb-1.5">Frecuencia de Sincronización Automática</label>
                            <select x-model.number="form.frecuencia_cron_horas"
                                    class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500 font-medium">
                                <option value="2">Cada 2 horas (Monitoreo continuo intensivo)</option>
                                <option value="4">Cada 4 horas (Recomendado para alto tráfico)</option>
                                <option value="6">Cada 6 horas (Balance óptimo)</option>
                                <option value="8">Cada 8 horas (Por defecto — 3 veces al día)</option>
                                <option value="12">Cada 12 horas (Bajo consumo de recursos)</option>
                            </select>
                            <p class="mt-1.5 text-[11px] text-gray-500 leading-normal">
                                El proceso en segundo plano consulta la bandeja de todos los clientes activos con credenciales SOL según este intervalo.
                            </p>
                        </div>

                        {{-- Panel de Información de la Última Ejecución --}}
                        <div class="rounded-xl border border-gray-200 bg-gray-50/80 p-4 space-y-2">
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-gray-500 font-medium">Última Sincronización Exitosa:</span>
                                <span class="font-bold text-gray-800 font-mono">
                                    {{ $ajustes->ultima_sincronizacion_at ? $ajustes->ultima_sincronizacion_at->translatedFormat('d M Y, h:i A') : 'Aún no ejecutada' }}
                                </span>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-gray-500 font-medium">Cadencia Actual:</span>
                                <span class="font-bold text-brand-600" x-text="'Cada ' + form.frecuencia_cron_horas + ' horas'"></span>
                            </div>
                            <div class="flex items-center justify-between text-[11px]">
                                <span class="text-gray-500 font-medium">Filtro de Ruido:</span>
                                <span class="font-bold text-emerald-700">Anti-Spam Activado</span>
                            </div>
                        </div>

                        <div class="rounded-xl bg-purple-50/70 border border-purple-200/80 p-3.5 text-[11px] text-purple-900 leading-relaxed">
                            <span class="font-bold block mb-0.5">💡 Ejecución Inteligente</span>
                            El programador de tareas aborta tempranamente si no ha transcurrido la ventana horaria configurada, evitando sobrecargar el portal de SUNAT.
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50/50 p-4 border-t border-gray-100 text-[11px] text-gray-500 flex items-center justify-between">
                    <div class="flex items-center gap-1.5">
                        <span>Comando:</span>
                        <code class="font-mono text-[10px] bg-white border border-gray-200 px-1.5 py-0.5 rounded text-gray-700">buzon:sync-sunat</code>
                    </div>
                    <button type="button"
                            @click="forzarSincronizacion()"
                            :disabled="sincronizandoRpa"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-purple-200 bg-white hover:bg-purple-50 text-purple-700 px-2.5 py-1 text-[11px] font-bold transition shadow-xs active:scale-95 disabled:opacity-50">
                        <svg class="h-3 w-3" :class="{ 'animate-spin': sincronizandoRpa }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span x-text="sincronizandoRpa ? 'Sincronizando...' : 'Sincronizar Ahora'"></span>
                    </button>
                </div>
            </div>

            {{-- ───────── CARD 3: INTEGRACIÓN WHATSAPP (BETA) ───────── --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xs flex flex-col justify-between overflow-hidden">
                <div>
                    <div class="border-b border-gray-100 bg-gray-50/75 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-100 text-emerald-700">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Integración WhatsApp</h3>
                                <p class="text-[11px] text-gray-400">Alertas directas al teléfono móvil</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-emerald-100 border border-emerald-200 px-2 py-0.5 text-[10px] font-black text-emerald-800 uppercase tracking-wider">
                            Beta
                        </span>
                    </div>

                    <div class="p-6 space-y-5 text-xs">
                        {{-- Toggle Switch: Habilitar Alertas Móviles --}}
                        <div class="flex items-center justify-between p-3.5 rounded-xl border border-gray-200 bg-gray-50/50">
                            <div>
                                <span class="block font-bold text-gray-900">Habilitar Alertas Móviles</span>
                                <span class="block text-[11px] text-gray-500">Enviar aviso al detectar notificaciones de SUNAT</span>
                            </div>

                            <button type="button"
                                    @click="form.notificar_whatsapp = !form.notificar_whatsapp"
                                    class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus:ring-2 focus:ring-emerald-500/20"
                                    :class="form.notificar_whatsapp ? 'bg-emerald-600' : 'bg-gray-300'"
                                    role="switch"
                                    :aria-checked="form.notificar_whatsapp">
                                <span class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow-md ring-0 transition duration-200 ease-in-out"
                                      :class="form.notificar_whatsapp ? 'translate-x-5' : 'translate-x-0'"></span>
                            </button>
                        </div>

                        {{-- Número de Destino WhatsApp --}}
                        <div :class="{ 'opacity-60 pointer-events-none': !form.notificar_whatsapp }">
                            <label class="block font-semibold text-gray-700 mb-1">Número de WhatsApp para Notificaciones</label>
                            <input type="text"
                                   x-model="form.telefono_whatsapp"
                                   placeholder="Ej. +51987654321"
                                   class="w-full font-mono rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            <span class="text-[10px] text-gray-400 mt-1 block">Incluye el código de país (ej. +51 para Perú).</span>
                        </div>

                        {{-- Nivel de Alerta --}}
                        <div :class="{ 'opacity-60 pointer-events-none': !form.notificar_whatsapp }">
                            <label class="block font-semibold text-gray-700 mb-1">Filtro de Despacho Móvil</label>
                            <select x-model="form.nivel_alerta_whatsapp"
                                    class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500 font-medium">
                                <option value="Solo Altas">Solo Alta Prioridad (Coactivas, Órdenes de Pago, Embargos, Multas)</option>
                                <option value="Todas">Todas las notificaciones y avisos oficiales</option>
                            </select>
                        </div>

                        {{-- ── Módulo Reactivo WhatsApp SaaS (x-data="whatsappManager()") ── --}}
                        <div x-data="whatsappManager()" x-init="iniciarPolling()" :class="{ 'opacity-60 pointer-events-none': !form.notificar_whatsapp }" class="pt-1 space-y-3">
                            
                            {{-- Estado: Loading --}}
                            <div x-show="status === 'loading'" class="rounded-2xl border border-blue-200 bg-blue-50/60 p-4 text-center space-y-3 shadow-xs">
                                <svg class="h-6 w-6 animate-spin text-brand-600 mx-auto" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                <div>
                                    <p class="text-xs font-bold text-gray-800" x-text="loadingMessage || 'Iniciando motor de mensajería...'"></p>
                                    <p class="text-[11px] text-gray-500 mt-0.5">Sincronizando con el microservicio local (Puerto 3001)</p>
                                </div>

                                {{-- Barra de Progreso si está disponible --}}
                                <template x-if="loadingPercent !== null">
                                    <div class="max-w-xs mx-auto space-y-1">
                                        <div class="w-full bg-blue-100 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-brand-600 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + loadingPercent + '%'"></div>
                                        </div>
                                        <span class="text-[10px] font-mono text-gray-500" x-text="loadingPercent + '%'"></span>
                                    </div>
                                </template>

                                {{-- Botones de recuperación si demora más de 6 segundos --}}
                                <div x-show="segundosEnLoading >= 6" class="pt-2.5 border-t border-blue-100 flex items-center justify-center flex-wrap gap-2 text-[10px]">
                                    <span class="text-gray-400 w-full mb-1">¿Tarda en responder? Opciones rápidas:</span>
                                    <button type="button"
                                            @click="reiniciarRobot()"
                                            :disabled="reiniciandoServicio"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 font-bold shadow-xs">
                                        <svg class="h-3 w-3" :class="{ 'animate-spin': reiniciandoServicio }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        <span>Forzar Reinicio</span>
                                    </button>
                                    <button type="button"
                                            @click="detenerRobot()"
                                            :disabled="deteniendoServicio"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-red-200 bg-white hover:bg-red-50 text-red-600 font-bold shadow-xs">
                                        <span>Apagar</span>
                                    </button>
                                    <button type="button"
                                            @click="restablecerSesion()"
                                            :disabled="restableciendoSesion"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-amber-300 bg-white hover:bg-amber-50 text-amber-700 font-bold shadow-xs">
                                        <span>Limpiar y Re-escanear</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Estado: Authenticated (Sincronizando Chats) --}}
                            <div x-show="status === 'authenticated'" class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 space-y-3 shadow-xs">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-2">
                                        <span class="relative flex h-2.5 w-2.5">
                                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                                        </span>
                                        <span class="text-xs font-bold text-emerald-900">🔑 Sesión Autenticada</span>
                                    </div>
                                    <span class="text-[10px] font-mono font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded-full" x-text="(loadingPercent || 0) + '%'"></span>
                                </div>
                                <p class="text-[11px] text-emerald-800 leading-normal" x-text="loadingMessage || 'Sincronizando chats y mensajes de WhatsApp...'"></p>
                                
                                <div class="w-full bg-emerald-200/80 rounded-full h-1.5 overflow-hidden">
                                    <div class="bg-emerald-600 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + (loadingPercent || 20) + '%'"></div>
                                </div>

                                <div class="flex items-center justify-end gap-2 pt-1">
                                    <button type="button"
                                            @click="reiniciarRobot()"
                                            :disabled="reiniciandoServicio"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-gray-200 bg-white text-gray-700 text-[10px] font-bold hover:bg-gray-50 shadow-xs">
                                        <span>Reiniciar</span>
                                    </button>
                                    <button type="button"
                                            @click="detenerRobot()"
                                            :disabled="deteniendoServicio"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-red-200 bg-white text-red-600 text-[10px] font-bold hover:bg-red-50 shadow-xs">
                                        <span>Apagar</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Estado: QR Ready --}}
                            <div x-show="status === 'qr_ready'" class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 text-center space-y-3 shadow-xs">
                                <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 border border-emerald-300 text-emerald-800 text-[11px] font-black tracking-wide uppercase">
                                    <span class="h-2 w-2 rounded-full bg-emerald-600 animate-pulse"></span>
                                    <span>Código QR Listo</span>
                                </div>
                                <div>
                                    <h4 class="text-xs font-bold text-gray-900">Escanea este QR con tu WhatsApp</h4>
                                    <p class="text-[11px] text-gray-500 mt-0.5">WhatsApp &gt; Dispositivos vinculados &gt; Vincular un dispositivo</p>
                                </div>
                                <div class="bg-white p-3 rounded-2xl border border-gray-200 shadow-md inline-block mx-auto">
                                    <template x-if="qr">
                                        <img :src="qr" class="w-44 h-44 mx-auto rounded-lg object-contain" alt="Código QR WhatsApp" />
                                    </template>
                                </div>
                                <div class="flex items-center justify-center gap-2 pt-1">
                                    <button type="button"
                                            @click="reiniciarRobot()"
                                            :disabled="reiniciandoServicio"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-gray-300 bg-white text-gray-700 text-[10px] font-bold hover:bg-gray-50 shadow-xs">
                                        <span>Regenerar QR</span>
                                    </button>
                                    <button type="button"
                                            @click="detenerRobot()"
                                            :disabled="deteniendoServicio"
                                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg border border-red-200 bg-white text-red-600 text-[10px] font-bold hover:bg-red-50 shadow-xs">
                                        <span>Apagar</span>
                                    </button>
                                </div>
                            </div>

                            {{-- Estado: Connected --}}
                            <div x-show="status === 'connected'" class="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 space-y-3 shadow-xs">
                                <div class="flex items-center justify-between">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-emerald-100 border border-emerald-300 text-emerald-800 text-[11px] font-black">
                                        <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                        <span>✅ Robot Vinculado y Activo</span>
                                    </span>
                                    <div class="flex items-center gap-1.5">
                                        <button type="button"
                                                @click="reiniciarRobot()"
                                                :disabled="reiniciandoServicio"
                                                title="Reiniciar microservicio"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-gray-600 text-[10px] font-bold transition shadow-xs active:scale-95 disabled:opacity-50">
                                            <svg class="h-3 w-3 text-gray-500" :class="{ 'animate-spin': reiniciandoServicio }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                            </svg>
                                            <span x-text="reiniciandoServicio ? 'Reiniciando...' : 'Reiniciar'"></span>
                                        </button>
                                        <button type="button"
                                                @click="detenerRobot()"
                                                :disabled="deteniendoServicio"
                                                title="Detener servicio"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-gray-600 text-[10px] font-bold transition shadow-xs active:scale-95 disabled:opacity-50">
                                            <svg class="h-3 w-3 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 7.5A2.25 2.25 0 0 1 7.5 5.25h9a2.25 2.25 0 0 1 2.25 2.25v9a2.25 2.25 0 0 1-2.25 2.25h-9a2.25 2.25 0 0 1-2.25-2.25v-9Z" />
                                            </svg>
                                            <span x-text="deteniendoServicio ? 'Deteniendo...' : 'Apagar'"></span>
                                        </button>
                                        <button type="button"
                                                @click="desvincular()"
                                                :disabled="desvinculando"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-red-200 bg-white hover:bg-red-50 text-red-700 text-[10px] font-bold transition shadow-xs active:scale-95 disabled:opacity-50">
                                            <svg class="h-3 w-3 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                            </svg>
                                            <span x-text="desvinculando ? 'Desvinculando...' : 'Desvincular'"></span>
                                        </button>
                                    </div>
                                </div>

                                {{-- Botón de prueba secundario --}}
                                <button type="button"
                                        @click="probarWhatsApp()"
                                        :disabled="probandoWhatsApp || !form.notificar_whatsapp"
                                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl border border-emerald-600/30 bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 text-xs font-bold transition shadow-xs active:scale-95 disabled:opacity-50">
                                    <svg x-show="!probandoWhatsApp" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                                    </svg>
                                    <svg x-show="probandoWhatsApp" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="probandoWhatsApp ? 'Enviando prueba...' : '📲 Enviar Alerta de Prueba'"></span>
                                </button>
                            </div>

                            {{-- Estado: Offline --}}
                            <div x-show="status === 'offline'" class="rounded-2xl border border-amber-200 bg-amber-50/70 p-4 space-y-3 text-[11px] text-amber-900 shadow-xs">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-1.5 font-bold">
                                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                                        <span>Robot de WhatsApp Apagado</span>
                                    </div>
                                    <span class="text-[10px] text-amber-700 bg-amber-100/80 px-2 py-0.5 rounded-full font-mono">Puerto 3001</span>
                                </div>
                                <p class="leading-relaxed text-amber-800">
                                    El motor de mensajería se encuentra detenido. Puedes encenderlo con un solo clic desde aquí sin necesidad de abrir consolas ni terminales.
                                </p>
                                <button type="button"
                                        @click="iniciarRobot()"
                                        :disabled="iniciandoServicio"
                                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 text-xs font-bold transition shadow-xs active:scale-95 disabled:opacity-50">
                                    <svg x-show="!iniciandoServicio" class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M5.25 5.653c0-.856.917-1.398 1.667-.986l11.54 6.347a1.125 1.125 0 0 1 0 1.972l-11.54 6.347a1.125 1.125 0 0 1-1.667-.986V5.653Z" />
                                    </svg>
                                    <svg x-show="iniciandoServicio" class="h-4 w-4 animate-spin text-white" fill="none" viewBox="0 0 24 24">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                    </svg>
                                    <span x-text="iniciandoServicio ? 'Encendiendo Robot...' : '⚡ Encender Robot de WhatsApp'"></span>
                                </button>
                            </div>

                            {{-- Estado: Disconnected / Auth Failure / Error --}}
                            <div x-show="status === 'disconnected' || status === 'auth_failure' || status === 'error'" class="rounded-2xl border border-red-200 bg-red-50/70 p-4 text-center text-[11px] text-red-800 space-y-2.5 shadow-xs">
                                <p class="font-bold text-red-900" x-text="status === 'error' ? 'Aviso en Motor de Mensajería' : 'Sesión Desconectada'"></p>
                                <p class="text-[10px] text-red-600" x-text="errorMessage || 'La sesión con WhatsApp no está activa. Puedes reiniciar el servicio o restablecerla para obtener un nuevo QR.'"></p>
                                <div class="flex items-center justify-center flex-wrap gap-2 pt-1">
                                    <button type="button"
                                            @click="iniciarRobot()"
                                            :disabled="iniciandoServicio"
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-[11px] font-semibold shadow-xs">
                                        <span>⚡ Iniciar Motor</span>
                                    </button>
                                    <button type="button"
                                            @click="reiniciarRobot()"
                                            :disabled="reiniciandoServicio"
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-[11px] font-semibold shadow-xs">
                                        <svg class="h-3 w-3 text-gray-500" :class="{ 'animate-spin': reiniciandoServicio }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                        </svg>
                                        <span>Reiniciar</span>
                                    </button>
                                    <button type="button"
                                            @click="restablecerSesion()"
                                            :disabled="restableciendoSesion"
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-red-300 bg-white hover:bg-red-50 text-red-700 text-[11px] font-bold shadow-xs">
                                        <span>🧹 Limpiar y Re-escanear</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-gray-50/50 p-4 border-t border-gray-100 text-[11px] text-gray-500 flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full bg-emerald-500"></span>
                    <span>Despacho móvil con formateo instantáneo de mensajes.</span>
                </div>
            </div>
        </form>

        {{-- ════════════════════ BARRA INFERIOR DE ACCIÓN FLOTANTE / FIJA ════════════════════ --}}
        <div class="flex items-center justify-between bg-white border border-gray-200 p-4 rounded-2xl shadow-xs">
            <div class="text-xs text-gray-500">
                <span>¿Deseas restablecer los valores? Puedes recargar la página antes de guardar.</span>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('dashboard') }}"
                   class="rounded-xl border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="button"
                        @click="guardarAjustes()"
                        :disabled="guardando"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white px-5 py-2 text-xs font-bold shadow-xs transition disabled:opacity-50 active:scale-95">
                    <svg x-show="!guardando" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <svg x-show="guardando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="guardando ? 'Guardando...' : 'Guardar Ajustes del Sistema'"></span>
                </button>
            </div>
        </div>
    </div>

    {{-- ════════════════════ COMPONENTE ALPINE.JS ════════════════════ --}}
    <script>
        function panelAjustes(initialData) {
            return {
                form: {
                    razon_social: initialData.razon_social || '',
                    ruc: initialData.ruc || '',
                    representante: initialData.representante || '',
                    correo_corporativo: initialData.correo_corporativo || '',
                    telefono_whatsapp: initialData.telefono_whatsapp || '',
                    frecuencia_cron_horas: initialData.frecuencia_cron_horas || 8,
                    notificar_whatsapp: Boolean(initialData.notificar_whatsapp),
                    nivel_alerta_whatsapp: initialData.nivel_alerta_whatsapp || 'Solo Altas'
                },
                guardando: false,
                probandoWhatsApp: false,
                sincronizandoRpa: false,
                toast: {
                    mostrar: false,
                    titulo: '',
                    mensaje: '',
                    tipo: 'success'
                },

                lanzarToast(titulo, mensaje, tipo = 'success') {
                    this.toast.titulo = titulo;
                    this.toast.mensaje = mensaje;
                    this.toast.tipo = tipo;
                    this.toast.mostrar = true;

                    setTimeout(() => {
                        this.toast.mostrar = false;
                    }, 3500);
                },

                async forzarSincronizacion() {
                    if (this.sincronizandoRpa) return;
                    this.sincronizandoRpa = true;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('alertas.sync-all') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Error durante la sincronización de alertas.');
                        }

                        this.lanzarToast('¡Sincronización Exitosa!', data.message || 'Alertas sincronizadas correctamente.', 'success');
                    } catch (err) {
                        console.error('Error al forzar sincronización:', err);
                        this.lanzarToast('Error de Sincronización', err.message, 'error');
                    } finally {
                        this.sincronizandoRpa = false;
                    }
                },

                async guardarAjustes() {
                    if (this.guardando) return;
                    this.guardando = true;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('ajustes.update') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify(this.form)
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Error al guardar los ajustes.');
                            throw new Error(errorMsg);
                        }

                        this.lanzarToast('¡Ajustes Actualizados!', data.message || 'La configuración se guardó correctamente.', 'success');
                    } catch (err) {
                        console.error('Error al guardar ajustes:', err);
                        this.lanzarToast('Error al Guardar', err.message, 'error');
                    } finally {
                        this.guardando = false;
                    }
                },

                async probarWhatsApp() {
                    if (this.probandoWhatsApp) return;

                    const numero = (this.form.telefono_whatsapp || '').trim();
                    if (!numero) {
                        this.lanzarToast('Número Requerido', 'Por favor ingresa un número de teléfono para enviar la prueba.', 'error');
                        return;
                    }

                    this.probandoWhatsApp = true;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('ajustes.test-whatsapp') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                telefono_whatsapp: numero
                            })
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            throw new Error(data.message || 'Error al despachar la alerta de prueba.');
                        }

                        this.lanzarToast('¡Prueba Enviada!', data.message || 'Alerta de prueba enviada exitosamente.', 'success');
                    } catch (err) {
                        console.error('Error al probar WhatsApp:', err);
                        this.lanzarToast('Error en Despacho', err.message, 'error');
                    } finally {
                        this.probandoWhatsApp = false;
                    }
                },

                init() {
                    window.panelAjustesInstancia = this;
                }
            };
        }

        function whatsappManager() {
            return {
                status: 'offline', // Inicialmente offline para evitar el spinner prematuro
                qr: null,
                loadingPercent: null,
                loadingMessage: null,
                errorMessage: null,
                consultando: false,
                desvinculando: false,
                iniciandoServicio: false,
                deteniendoServicio: false,
                reiniciandoServicio: false,
                restableciendoSesion: false,
                segundosEnLoading: 0,
                timer: null,

                iniciarPolling() {
                    this.consultarEstado();
                    this.timer = setInterval(() => {
                        // Evita saturar el navegador si la pestaña está oculta o hay consulta en curso
                        if (document.hidden || this.consultando) {
                            return;
                        }
                        this.consultarEstado();
                    }, 3000);
                },

                async consultarEstado() {
                    if (this.consultando) return;
                    this.consultando = true;

                    try {
                        const response = await fetch('{{ route('ajustes.whatsapp-status') }}', {
                            headers: { 'Accept': 'application/json' }
                        });

                        if (response.ok) {
                            const data = await response.json();
                            this.status = data.status || 'offline';
                            this.qr = data.qr || null;
                            this.loadingPercent = data.loading_percent ?? null;
                            this.loadingMessage = data.loading_message ?? null;
                            this.errorMessage = data.error ?? null;

                            if (this.status === 'loading' || this.status === 'authenticated') {
                                this.segundosEnLoading += 3;
                            } else {
                                this.segundosEnLoading = 0;
                            }
                        } else {
                            this.status = 'offline';
                            this.qr = null;
                            this.segundosEnLoading = 0;
                        }
                    } catch (err) {
                        this.status = 'offline';
                        this.qr = null;
                        this.segundosEnLoading = 0;
                    } finally {
                        this.consultando = false;
                    }
                },

                async iniciarRobot() {
                    if (this.iniciandoServicio) return;
                    this.iniciandoServicio = true;
                    this.status = 'loading';
                    this.loadingMessage = 'Iniciando proceso del robot en segundo plano...';
                    this.segundosEnLoading = 0;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('ajustes.whatsapp-start') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (response.ok) {
                            if (window.panelAjustesInstancia) {
                                window.panelAjustesInstancia.lanzarToast('Robot Iniciado', data.message || 'Iniciando conexión con WhatsApp...', 'success');
                            }
                            setTimeout(() => this.consultarEstado(), 1500);
                        } else {
                            throw new Error(data.message || 'No se pudo iniciar el servicio de WhatsApp.');
                        }
                    } catch (err) {
                        this.status = 'offline';
                        if (window.panelAjustesInstancia) {
                            window.panelAjustesInstancia.lanzarToast('Error al Iniciar', err.message, 'error');
                        }
                    } finally {
                        this.iniciandoServicio = false;
                    }
                },

                async detenerRobot() {
                    if (this.deteniendoServicio) return;
                    if (!confirm('¿Deseas detener el microservicio de WhatsApp? Las alertas móviles no se despacharán hasta que lo vuelvas a encender.')) {
                        return;
                    }
                    this.deteniendoServicio = true;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('ajustes.whatsapp-stop') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (response.ok) {
                            this.status = 'offline';
                            this.qr = null;
                            this.segundosEnLoading = 0;
                            if (window.panelAjustesInstancia) {
                                window.panelAjustesInstancia.lanzarToast('Robot Detenido', data.message || 'El robot de WhatsApp fue detenido.', 'info');
                            }
                        }
                    } catch (err) {
                        console.error('Error al detener robot:', err);
                    } finally {
                        this.deteniendoServicio = false;
                    }
                },

                async reiniciarRobot() {
                    if (this.reiniciandoServicio) return;
                    this.reiniciandoServicio = true;
                    this.status = 'loading';
                    this.loadingMessage = 'Reiniciando motor de WhatsApp...';
                    this.segundosEnLoading = 0;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('ajustes.whatsapp-restart') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (response.ok) {
                            if (window.panelAjustesInstancia) {
                                window.panelAjustesInstancia.lanzarToast('Robot Reiniciado', data.message || 'Reiniciando sesión de WhatsApp...', 'success');
                            }
                            setTimeout(() => this.consultarEstado(), 2000);
                        } else {
                            throw new Error(data.message || 'No se pudo reiniciar.');
                        }
                    } catch (err) {
                        this.status = 'offline';
                        if (window.panelAjustesInstancia) {
                            window.panelAjustesInstancia.lanzarToast('Error al Reiniciar', err.message, 'error');
                        }
                    } finally {
                        this.reiniciandoServicio = false;
                    }
                },

                async restablecerSesion() {
                    if (this.restableciendoSesion) return;
                    if (!confirm('¿Deseas restablecer la sesión por completo? Se limpiarán los archivos temporales de WhatsApp y se generará un nuevo código QR limpio para escanear.')) {
                        return;
                    }
                    this.restableciendoSesion = true;
                    this.status = 'loading';
                    this.loadingMessage = 'Limpiando sesión y generando nuevo QR...';
                    this.segundosEnLoading = 0;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('ajustes.whatsapp-reset-session') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (response.ok) {
                            if (window.panelAjustesInstancia) {
                                window.panelAjustesInstancia.lanzarToast('Sesión Restablecida', data.message || 'Generando nuevo código QR...', 'success');
                            }
                            setTimeout(() => this.consultarEstado(), 2000);
                        } else {
                            throw new Error(data.message || 'No se pudo restablecer la sesión.');
                        }
                    } catch (err) {
                        this.status = 'offline';
                        if (window.panelAjustesInstancia) {
                            window.panelAjustesInstancia.lanzarToast('Error', err.message, 'error');
                        }
                    } finally {
                        this.restableciendoSesion = false;
                    }
                },

                async desvincular() {
                    if (this.desvinculando) return;
                    if (!confirm('¿Estás seguro de que deseas desvincular este dispositivo de WhatsApp? Se cerrará la sesión actual.')) {
                        return;
                    }

                    this.desvinculando = true;

                    try {
                        const tokenEl = document.querySelector('meta[name="csrf-token"]');
                        const csrfToken = tokenEl ? tokenEl.getAttribute('content') : '';

                        const response = await fetch('{{ route('ajustes.whatsapp-logout') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            }
                        });

                        const data = await response.json();
                        if (response.ok) {
                            this.status = 'loading';
                            this.loadingMessage = 'Cerrando sesión en WhatsApp...';
                            this.qr = null;
                            if (window.panelAjustesInstancia) {
                                window.panelAjustesInstancia.lanzarToast('Dispositivo Desvinculado', data.message || 'Sesión cerrada correctamente.', 'info');
                            }
                            setTimeout(() => this.consultarEstado(), 1200);
                        } else {
                            alert(data.message || 'Error al desvincular dispositivo.');
                        }
                    } catch (err) {
                        console.error('Error al desvincular WhatsApp:', err);
                    } finally {
                        this.desvinculando = false;
                    }
                }
            };
        }
    </script>
@endsection
