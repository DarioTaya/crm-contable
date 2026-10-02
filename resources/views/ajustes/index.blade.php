@extends('layouts.app')

@section('title', 'Ajustes del Sistema')
@section('header', 'Ajustes del Sistema')

@section('content')
    <div x-data="panelAjustes({
            razon_social: @js($estudio->nombre ?? $ajustes->razon_social),
            nombre_comercial: @js($estudio->nombre_comercial ?? ''),
            ruc: @js($estudio->ruc ?? $ajustes->ruc),
            representante: @js($estudio->representante ?? $ajustes->representante),
            colegiatura: @js($estudio->colegiatura ?? ''),
            slogan: @js($estudio->slogan ?? ''),
            correo_corporativo: @js($estudio->correo ?? $ajustes->correo_corporativo ?? ''),
            telefono_whatsapp: @js($estudio->telefono ?? $ajustes->telefono_whatsapp ?? ''),
            direccion: @js($estudio->direccion ?? ''),
            ciudad: @js($estudio->ciudad ?? ''),
            sitio_web: @js($estudio->sitio_web ?? ''),
            sidebar_display: @js($estudio->sidebar_display ?? 'logo_nombre'),
            logo_actual: @js($estudio->logoUrl()),
            logo_nombre_actual: @js($estudio->logoNombreOriginal()),
            isotipo_actual: @js($estudio->isotipo_path ? asset('storage/'.$estudio->isotipo_path) : null),
            isotipo_nombre_actual: @js($estudio->isotipoNombreOriginal()),
            logo_reportes_actual: @js($estudio->logoReportesUrl()),
            logo_reportes_nombre_actual: @js($estudio->logoReportesNombreOriginal()),
            frecuencia_cron_horas: {{ $ajustes->frecuencia_cron_horas }},
            notificar_whatsapp: {{ $ajustes->notificar_whatsapp ? 'true' : 'false' }},
            nivel_alerta_whatsapp: @js($ajustes->nivel_alerta_whatsapp),
            enlaces: @js($enlacesRapidos ?? []),
         })"
         x-cloak
         class="space-y-6 max-w-7xl mx-auto">

        {{-- ════════════════════ TOAST FLOTANTE DE NOTIFICACIÓN ════════════════════ --}}
        <div x-show="toast.mostrar"
             x-transition:enter="transform ease-out duration-300 transition"
             x-transition:enter-start="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
             x-transition:enter-end="translate-y-0 opacity-100 sm:translate-x-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed bottom-6 right-6 z-50 flex items-center gap-3 rounded-2xl border px-4 py-3 shadow-2xl backdrop-blur"
             :class="toast.tipo === 'success' ? 'bg-emerald-900/90 text-white border-emerald-500/50' : (toast.tipo === 'info' ? 'bg-blue-900/90 text-white border-blue-500/50' : 'bg-rose-900/90 text-white border-rose-500/50')">
            <div class="flex h-7 w-7 items-center justify-center rounded-full"
                 :class="toast.tipo === 'success' ? 'bg-emerald-500/20 text-emerald-300' : (toast.tipo === 'info' ? 'bg-blue-500/20 text-blue-300' : 'bg-rose-500/20 text-rose-300')">
                <template x-if="toast.tipo === 'success'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                </template>
                <template x-if="toast.tipo === 'info'">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
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

        {{-- ════════════════════ ENCABEZADO Y TÍTULO ════════════════════ --}}
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200 pb-5">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-brand-50 border border-brand-200 text-brand-600 shadow-xs">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </div>
                    <div>
                        <h2 class="text-2xl font-bold tracking-tight text-gray-900">Ajustes del Sistema</h2>
                        <p class="text-xs text-gray-500">Identidad visual del estudio contable, equipo de trabajo, automatización RPA SUNAT y canal WhatsApp.</p>
                    </div>
                </div>
            </div>

            {{-- Botón de Acción Superior --}}
            <div class="flex items-center">
                <button type="button"
                        @click="guardarAjustes()"
                        :disabled="guardando"
                        class="inline-flex items-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white px-5 py-2.5 text-xs font-bold shadow-xs transition disabled:opacity-50 active:scale-95 cursor-pointer">
                    <svg x-show="!guardando" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <svg x-show="guardando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="guardando ? 'Guardando Ajustes...' : 'Guardar Ajustes del Sistema'"></span>
                </button>
            </div>
        </div>

        {{-- ════════════════════ NAVEGACIÓN POR PESTAÑAS (TABS HUB) ════════════════════ --}}
        <div class="border-b border-gray-200">
            <nav class="-mb-px flex space-x-2 sm:space-x-8 overflow-x-auto pb-1" aria-label="Tabs">
                <button type="button"
                        @click="tabActiva = 'perfil'"
                        :class="tabActiva === 'perfil' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 font-medium'"
                        class="whitespace-nowrap flex items-center gap-2.5 border-b-2 py-3 px-2 text-xs transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                    <span>Identidad & Marca</span>
                    <span class="rounded-full bg-blue-100 text-blue-700 text-[10px] font-bold px-2 py-0.5">Estudio</span>
                </button>


                <button type="button"
                        @click="tabActiva = 'rpa'"
                        :class="tabActiva === 'rpa' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 font-medium'"
                        class="whitespace-nowrap flex items-center gap-2.5 border-b-2 py-3 px-2 text-xs transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                    </svg>
                    <span>Motor RPA SUNAT</span>
                    <span class="flex h-2 w-2 rounded-full bg-emerald-500"></span>
                </button>

                <button type="button"
                        @click="tabActiva = 'whatsapp'"
                        :class="tabActiva === 'whatsapp' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 font-medium'"
                        class="whitespace-nowrap flex items-center gap-2.5 border-b-2 py-3 px-2 text-xs transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                    </svg>
                    <span>Integración WhatsApp</span>
                    <span class="rounded-full bg-emerald-100 text-emerald-800 text-[10px] font-black px-2 py-0.5">BETA</span>
                </button>

                <button type="button"
                        @click="tabActiva = 'enlaces'"
                        :class="tabActiva === 'enlaces' ? 'border-brand-600 text-brand-600 font-bold' : 'border-transparent text-gray-500 hover:border-gray-300 hover:text-gray-700 font-medium'"
                        class="whitespace-nowrap flex items-center gap-2.5 border-b-2 py-3 px-2 text-xs transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                    </svg>
                    <span>Consultas Rápidas</span>
                    <span class="rounded-full bg-amber-100 text-amber-800 text-[10px] font-bold px-2 py-0.5">Enlaces</span>
                </button>
            </nav>
        </div>

        {{-- ════════════════════ FORMULARIO MAESTRO DE AJUSTES ════════════════════ --}}
        <form @submit.prevent="guardarAjustes()" class="space-y-6">

            {{-- ───────────────────────────────────────────────────────────────────────────── --}}
            {{-- TAB 1: IDENTIDAD & MARCA (PERFIL DEL ESTUDIO CONTABLE)                        --}}
            {{-- ───────────────────────────────────────────────────────────────────────────── --}}
            <div x-show="tabActiva === 'perfil'" class="space-y-6">
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {{-- Tarjeta 1.1: Logotipo, Visualización y Presencia de Marca --}}
                    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-6 space-y-6">
                        <div class="border-b border-gray-100 pb-4">
                            <h3 class="text-sm font-bold text-gray-900">Logotipo e Identidad Visual</h3>
                            <p class="text-[11px] text-gray-500 mt-0.5">Se utiliza en el encabezado del CRM, reportes de auditoría y documentos membretados.</p>
                        </div>

                        {{-- 1. Logotipo Completo (Horizontal / Barra Lateral Extendida) --}}
                        <div class="space-y-2">
                            <div>
                                <label class="block font-semibold text-gray-800 text-xs">Logotipo Completo (Horizontal)</label>
                                <p class="text-[10px] text-gray-400">Imagen alargada "Isotipo + Nombre". Se muestra cuando la barra lateral está extendida.</p>
                            </div>

                            <div class="flex flex-col items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-2xl bg-gray-50/50 hover:bg-gray-50 transition text-center relative">
                                <div class="h-16 w-full max-w-[240px] rounded-xl flex items-center justify-center p-2 bg-white shadow-xs border border-gray-200 overflow-hidden mb-2.5">
                                    <template x-if="previewLogoUrl">
                                        <img :src="previewLogoUrl" alt="Vista previa del logo" class="h-full w-full object-contain">
                                    </template>
                                    <template x-if="!previewLogoUrl && form.logo_actual && !eliminarLogo">
                                        <img :src="form.logo_actual" alt="Logo actual" class="h-full w-full object-contain">
                                    </template>
                                    <template x-if="(!previewLogoUrl && !form.logo_actual) || (!previewLogoUrl && eliminarLogo)">
                                        <div class="flex items-center gap-2 text-gray-400">
                                            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-brand-600 text-white font-bold text-xs shadow-xs">
                                                {{ $estudio->iniciales() }}
                                            </div>
                                            <span class="text-xs font-semibold text-gray-500">{{ $estudio->nombreParaMostrar() }}</span>
                                        </div>
                                    </template>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label for="logo_file_input" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 shadow-xs transition">
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                        </svg>
                                        <span>Seleccionar Logotipo Completo</span>
                                    </label>
                                    <input type="file"
                                           id="logo_file_input"
                                           accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg,.png,.jpg,.jpeg,.webp,.ico,.gif,.avif"
                                           @change="seleccionarArchivo($event)"
                                           class="sr-only">

                                    <button type="button"
                                            x-show="(form.logo_actual && !eliminarLogo) || previewLogoUrl"
                                            @click="quitarLogo()"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600 hover:text-rose-700 transition"
                                            title="Eliminar logotipo">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Nombre del archivo de logotipo subido / seleccionado --}}
                                <template x-if="logo_nombre_nuevo || (form.logo_actual && form.logo_nombre_actual && !eliminarLogo)">
                                    <div class="mt-2.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white border border-gray-200 text-[11px] font-mono text-gray-700 shadow-2xs max-w-[260px] truncate"
                                         :title="logo_nombre_nuevo || form.logo_nombre_actual">
                                        <svg class="h-3.5 w-3.5 text-brand-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                        </svg>
                                        <span class="truncate" x-text="logo_nombre_nuevo || form.logo_nombre_actual"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- 2. Isotipo (Símbolo / Barra Lateral Encogida) --}}
                        <div class="space-y-2">
                            <div>
                                <label class="block font-semibold text-gray-800 text-xs">Isotipo (Solo Símbolo Cuadrado)</label>
                                <p class="text-[10px] text-gray-400">Icono o símbolo cuadrado. Se muestra cuando la barra lateral está encogida.</p>
                            </div>

                            <div class="flex flex-col items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-2xl bg-gray-50/50 hover:bg-gray-50 transition text-center relative">
                                <div class="h-16 w-16 rounded-xl flex items-center justify-center p-2 bg-white shadow-xs border border-gray-200 overflow-hidden mb-2.5">
                                    <template x-if="previewIsotipoUrl">
                                        <img :src="previewIsotipoUrl" alt="Vista previa del isotipo" class="h-full w-full object-contain">
                                    </template>
                                    <template x-if="!previewIsotipoUrl && form.isotipo_actual && !eliminarIsotipo">
                                        <img :src="form.isotipo_actual" alt="Isotipo actual" class="h-full w-full object-contain">
                                    </template>
                                    <template x-if="(!previewIsotipoUrl && !form.isotipo_actual) || (!previewIsotipoUrl && eliminarIsotipo)">
                                        <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-brand-600 text-white font-bold text-xs shadow-xs">
                                            {{ $estudio->iniciales() }}
                                        </div>
                                    </template>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label for="isotipo_file_input" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 shadow-xs transition">
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                        </svg>
                                        <span>Seleccionar Isotipo</span>
                                    </label>
                                    <input type="file"
                                           id="isotipo_file_input"
                                           accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg,.png,.jpg,.jpeg,.webp,.ico,.gif,.avif"
                                           @change="seleccionarIsotipo($event)"
                                           class="sr-only">

                                    <button type="button"
                                            x-show="(form.isotipo_actual && !eliminarIsotipo) || previewIsotipoUrl"
                                            @click="quitarIsotipo()"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600 hover:text-rose-700 transition"
                                            title="Eliminar isotipo">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Nombre del archivo de isotipo subido / seleccionado --}}
                                <template x-if="isotipo_nombre_nuevo || (form.isotipo_actual && form.isotipo_nombre_actual && !eliminarIsotipo)">
                                    <div class="mt-2.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white border border-gray-200 text-[11px] font-mono text-gray-700 shadow-2xs max-w-[260px] truncate"
                                         :title="isotipo_nombre_nuevo || form.isotipo_nombre_actual">
                                        <svg class="h-3.5 w-3.5 text-brand-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                        </svg>
                                        <span class="truncate" x-text="isotipo_nombre_nuevo || form.isotipo_nombre_actual"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- 3. Logotipo para Documentos e Impresión (Fondo Claro / Reportes PDF) --}}
                        <div class="space-y-2 pt-2 border-t border-gray-100">
                            <div>
                                <div class="flex items-center gap-1.5">
                                    <label class="block font-semibold text-gray-800 text-xs">Logotipo para Documentos e Impresión (Fondo Blanco)</label>
                                    <span class="rounded bg-sky-100 px-1.5 py-0.5 text-[9px] font-bold text-sky-800 uppercase tracking-wider">PDF y Reportes</span>
                                </div>
                                <p class="text-[10px] text-gray-400">Versión con letras oscuras para hojas membretadas, cotizaciones y reportes PDF. Si no se sube, se usará el logotipo principal.</p>
                            </div>

                            <div class="flex flex-col items-center justify-center p-4 border-2 border-dashed border-gray-300 rounded-2xl bg-white hover:bg-gray-50/60 transition text-center relative shadow-2xs">
                                <div class="h-16 w-full max-w-[240px] rounded-xl flex items-center justify-center p-2 bg-white shadow-xs border border-gray-200 overflow-hidden mb-2.5">
                                    <template x-if="previewLogoReportesUrl">
                                        <img :src="previewLogoReportesUrl" alt="Vista previa del logo para reportes" class="h-full w-full object-contain">
                                    </template>
                                    <template x-if="!previewLogoReportesUrl && form.logo_reportes_actual && !eliminarLogoReportes">
                                        <img :src="form.logo_reportes_actual" alt="Logo para reportes actual" class="h-full w-full object-contain">
                                    </template>
                                    <template x-if="(!previewLogoReportesUrl && !form.logo_reportes_actual) || (!previewLogoReportesUrl && eliminarLogoReportes)">
                                        <div class="flex items-center gap-2 text-gray-400">
                                            <span class="text-xs font-semibold text-gray-400 italic">Mismo que el logotipo principal</span>
                                        </div>
                                    </template>
                                </div>

                                <div class="flex items-center gap-2">
                                    <label for="logo_reportes_file_input" class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-50 shadow-xs transition">
                                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                                        </svg>
                                        <span>Seleccionar Logotipo para Fondo Blanco</span>
                                    </label>
                                    <input type="file"
                                           id="logo_reportes_file_input"
                                           accept="image/png,image/jpeg,image/webp,image/svg+xml,.svg,.png,.jpg,.jpeg,.webp,.ico,.gif,.avif"
                                           @change="seleccionarLogoReportes($event)"
                                           class="sr-only">

                                    <button type="button"
                                            x-show="(form.logo_reportes_actual && !eliminarLogoReportes) || previewLogoReportesUrl"
                                            @click="quitarLogoReportes()"
                                            class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600 hover:text-rose-700 transition"
                                            title="Eliminar logotipo para fondo blanco">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Nombre del archivo subido / seleccionado --}}
                                <template x-if="logo_reportes_nombre_nuevo || (form.logo_reportes_actual && form.logo_reportes_nombre_actual && !eliminarLogoReportes)">
                                    <div class="mt-2.5 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-white border border-gray-200 text-[11px] font-mono text-gray-700 shadow-2xs max-w-[260px] truncate"
                                         :title="logo_reportes_nombre_nuevo || form.logo_reportes_nombre_actual">
                                        <svg class="h-3.5 w-3.5 text-sky-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m18.375 12.739-7.693 7.693a4.5 4.5 0 0 1-6.364-6.364l10.94-10.94A3 3 0 1 1 19.5 7.372L8.552 18.32m.009-.01-.01.01m5.699-9.941-7.81 7.81a1.5 1.5 0 0 0 2.112 2.13" />
                                        </svg>
                                        <span class="truncate" x-text="logo_reportes_nombre_nuevo || form.logo_reportes_nombre_actual"></span>
                                    </div>
                                </template>
                            </div>
                        </div>

                        {{-- Preferencia de visualización en la Barra Lateral --}}
                        <div class="space-y-3">
                            <label class="block font-semibold text-gray-800 text-xs">Modo de Visualización en Barra Lateral</label>
                            <div class="grid grid-cols-1 gap-2 text-xs">
                                <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition"
                                       :class="form.sidebar_display === 'logo_nombre' ? 'border-brand-500 bg-brand-50/40 text-brand-900' : 'border-gray-200 hover:bg-gray-50 text-gray-700'">
                                    <input type="radio" value="logo_nombre" x-model="form.sidebar_display" class="text-brand-600 focus:ring-brand-500">
                                    <div class="min-w-0">
                                        <p class="font-bold">Logotipo + Nombre (Recomendado)</p>
                                        <p class="text-[10px] text-gray-500">Muestra el isotipo y el nombre de la firma contable.</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition"
                                       :class="form.sidebar_display === 'logo_solo' ? 'border-brand-500 bg-brand-50/40 text-brand-900' : 'border-gray-200 hover:bg-gray-50 text-gray-700'">
                                    <input type="radio" value="logo_solo" x-model="form.sidebar_display" class="text-brand-600 focus:ring-brand-500">
                                    <div class="min-w-0">
                                        <p class="font-bold">Solo Logotipo</p>
                                        <p class="text-[10px] text-gray-500">Diseño minimalista centrado en el símbolo corporativo.</p>
                                    </div>
                                </label>

                                <label class="flex items-center gap-3 p-3 rounded-xl border cursor-pointer transition"
                                       :class="form.sidebar_display === 'nombre_solo' ? 'border-brand-500 bg-brand-50/40 text-brand-900' : 'border-gray-200 hover:bg-gray-50 text-gray-700'">
                                    <input type="radio" value="nombre_solo" x-model="form.sidebar_display" class="text-brand-600 focus:ring-brand-500">
                                    <div class="min-w-0">
                                        <p class="font-bold">Solo Nombre</p>
                                        <p class="text-[10px] text-gray-500">Muestra el monograma de iniciales y el texto oficial.</p>
                                    </div>
                                </label>
                            </div>
                        </div>

                        {{-- Slogan o lema institucional --}}
                        <div class="space-y-1 text-xs">
                            <label class="block font-semibold text-gray-700">Lema o Slogan Institucional</label>
                            <input type="text"
                                   x-model="form.slogan"
                                   placeholder="Ej. Soluciones tributarias y financieras de confianza"
                                   class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            <p class="text-[10px] text-gray-400">Aparece debajo del nombre en el menú lateral y membretes.</p>
                        </div>
                    </div>

                    {{-- Tarjeta 1.2: Datos Fiscales, Titular y Ubicación --}}
                    <div class="lg:col-span-2 rounded-2xl border border-gray-200 bg-white shadow-xs p-6 space-y-6">
                        <div class="border-b border-gray-100 pb-4 flex items-center justify-between">
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Perfil del Estudio Contable</h3>
                                <p class="text-[11px] text-gray-500 mt-0.5">Información fiscal oficial ante SUNAT y datos de contacto de la sede.</p>
                            </div>
                            <span class="rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-[10px] font-bold text-blue-700">
                                Sede Principal
                            </span>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
                            {{-- Razón Social --}}
                            <div class="md:col-span-2">
                                <label class="block font-semibold text-gray-700 mb-1">Razón Social Oficial (SUNAT) <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       x-model="form.razon_social"
                                       required
                                       placeholder="Ej. Grupo DC Soluciones Contables SAC"
                                       class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500 font-semibold">
                            </div>

                            {{-- Nombre Comercial --}}
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Nombre Comercial de la Firma</label>
                                <input type="text"
                                       x-model="form.nombre_comercial"
                                       placeholder="Ej. DC Asesores Contables"
                                       class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            {{-- RUC --}}
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Número de RUC (11 dígitos) <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       x-model="form.ruc"
                                       maxlength="11"
                                       required
                                       placeholder="20601234567"
                                       class="w-full font-mono rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            {{-- Representante Legal --}}
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Titular / Representante Legal <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       x-model="form.representante"
                                       required
                                       placeholder="Ej. Sebastián Taya Yucra"
                                       class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            {{-- Colegiatura CPC --}}
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Matrícula CPC / Colegiatura</label>
                                <input type="text"
                                       x-model="form.colegiatura"
                                       placeholder="Ej. Matrícula CPC 14205 (CCPA)"
                                       class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            {{-- Correo Electrónico Corporativo --}}
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Correo Electrónico Corporativo</label>
                                <input type="email"
                                       x-model="form.correo_corporativo"
                                       placeholder="contacto@estudiocontable.pe"
                                       class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            {{-- Teléfono / WhatsApp --}}
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Teléfono Principal / WhatsApp</label>
                                <input type="text"
                                       x-model="form.telefono_whatsapp"
                                       placeholder="+51 987 654 321"
                                       class="w-full font-mono rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            {{-- Dirección Fiscal --}}
                            <div class="md:col-span-2">
                                <label class="block font-semibold text-gray-700 mb-1">Dirección Fiscal de la Oficina</label>
                                <input type="text"
                                       x-model="form.direccion"
                                       placeholder="Ej. Av. Mariscal Castilla 850, Of. 302"
                                       class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            {{-- Ciudad / Región --}}
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Ciudad / Departamento</label>
                                <input type="text"
                                       x-model="form.ciudad"
                                       placeholder="Ej. Arequipa, Perú"
                                       class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            {{-- Sitio Web --}}
                            <div>
                                <label class="block font-semibold text-gray-700 mb-1">Sitio Web / Portal</label>
                                <input type="text"
                                       x-model="form.sitio_web"
                                       placeholder="https://www.estudiocontable.pe"
                                       class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>

                        <div class="rounded-xl bg-gray-50 border border-gray-200 p-4 text-[11px] text-gray-600 flex items-center gap-2.5">
                            <svg class="h-5 w-5 text-brand-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" />
                            </svg>
                            <span>Estos datos encabezan automáticamente todos los reportes impresos, avisos de cobranza tributaria y fichas exportadas a clientes.</span>
                        </div>
                    </div>
                </div>
            </div>



            {{-- ───────────────────────────────────────────────────────────────────────────── --}}
            {{-- TAB 3: MOTOR RPA SUNAT                                                        --}}
            {{-- ───────────────────────────────────────────────────────────────────────────── --}}
            <div x-show="tabActiva === 'rpa'" class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-6 space-y-6">
                    <div class="border-b border-gray-100 pb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-100 text-purple-700">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Motor de Automatización (RPA)</h3>
                                <p class="text-[11px] text-gray-500">Robot de extracción del Buzón Electrónico SOL de SUNAT.</p>
                            </div>
                        </div>
                        <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                            Scheduler Activo
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                        {{-- Select Frecuencia CRON --}}
                        <div class="space-y-2">
                            <label class="block font-semibold text-gray-700">Frecuencia de Sincronización Automática</label>
                            <select x-model.number="form.frecuencia_cron_horas"
                                    class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500 font-medium">
                                <option value="2">Cada 2 horas (Monitoreo continuo intensivo)</option>
                                <option value="4">Cada 4 horas (Recomendado para alto tráfico)</option>
                                <option value="6">Cada 6 horas (Balance óptimo)</option>
                                <option value="8">Cada 8 horas (Por defecto — 3 veces al día)</option>
                                <option value="12">Cada 12 horas (Bajo consumo de recursos)</option>
                            </select>
                            <p class="text-[11px] text-gray-500 leading-normal">
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
                    </div>

                    <div class="bg-gray-50/50 p-4 rounded-xl border border-gray-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 text-[11px]">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-500">Comando Artisan de ejecución manual:</span>
                            <code class="font-mono text-[10px] bg-white border border-gray-300 px-2 py-0.5 rounded text-gray-800">php artisan buzon:sync-sunat</code>
                        </div>
                        <button type="button"
                                @click="forzarSincronizacion()"
                                :disabled="sincronizandoRpa"
                                class="inline-flex items-center gap-1.5 rounded-xl border border-purple-200 bg-white hover:bg-purple-50 text-purple-700 px-3 py-1.5 text-xs font-bold transition shadow-xs active:scale-95 disabled:opacity-50">
                            <svg class="h-3.5 w-3.5" :class="{ 'animate-spin': sincronizandoRpa }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span x-text="sincronizandoRpa ? 'Sincronizando...' : 'Sincronizar Alertas Ahora'"></span>
                        </button>
                    </div>
                </div>
            </div>

            {{-- ───────────────────────────────────────────────────────────────────────────── --}}
            {{-- TAB 4: INTEGRACIÓN WHATSAPP (BETA)                                            --}}
            {{-- ───────────────────────────────────────────────────────────────────────────── --}}
            <div x-show="tabActiva === 'whatsapp'" class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-6 space-y-6">
                    <div class="border-b border-gray-100 pb-4 flex items-center justify-between">
                        <div class="flex items-center gap-2.5">
                            <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-100 text-emerald-700">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H8.25m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0H12m4.125 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 0 1-2.555-.337A5.972 5.972 0 0 1 5.41 20.97a5.969 5.969 0 0 1-.474-.065 4.48 4.48 0 0 0 .978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25Z" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Integración WhatsApp</h3>
                                <p class="text-[11px] text-gray-500">Alertas automáticas al detectar notificaciones de SUNAT.</p>
                            </div>
                        </div>
                        <span class="rounded-full bg-emerald-100 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-black text-emerald-800 uppercase tracking-wider">
                            Beta
                        </span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 text-xs">
                        {{-- Configuración Básica --}}
                        <div class="space-y-4">
                            {{-- Toggle Switch: Habilitar Alertas Móviles --}}
                            <div class="flex items-center justify-between p-4 rounded-xl border border-gray-200 bg-gray-50/50">
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
                        </div>

                        {{-- Microservicio WhatsApp Reactivo --}}
                        <div x-data="whatsappManager()" x-init="iniciarPolling()" :class="{ 'opacity-60 pointer-events-none': !form.notificar_whatsapp }" class="space-y-4">
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
                                <template x-if="loadingPercent !== null">
                                    <div class="max-w-xs mx-auto space-y-1">
                                        <div class="w-full bg-blue-100 rounded-full h-1.5 overflow-hidden">
                                            <div class="bg-brand-600 h-1.5 rounded-full transition-all duration-300" :style="'width: ' + loadingPercent + '%'"></div>
                                        </div>
                                        <span class="text-[10px] font-mono text-gray-500" x-text="loadingPercent + '%'"></span>
                                    </div>
                                </template>
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
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-gray-200 bg-white hover:bg-gray-50 text-gray-600 text-[10px] font-bold transition shadow-xs">
                                            <span x-text="reiniciandoServicio ? 'Reiniciando...' : 'Reiniciar'"></span>
                                        </button>
                                        <button type="button"
                                                @click="desvincular()"
                                                :disabled="desvinculando"
                                                class="inline-flex items-center gap-1 px-2 py-1 rounded-lg border border-red-200 bg-white hover:bg-red-50 text-red-700 text-[10px] font-bold transition shadow-xs">
                                            <span x-text="desvinculando ? 'Desvinculando...' : 'Desvincular'"></span>
                                        </button>
                                    </div>
                                </div>

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
                                    El motor de mensajería se encuentra detenido. Puedes encenderlo con un clic:
                                </p>
                                <button type="button"
                                        @click="iniciarRobot()"
                                        :disabled="iniciandoServicio"
                                        class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2.5 text-xs font-bold transition shadow-xs active:scale-95 disabled:opacity-50">
                                    <span x-text="iniciandoServicio ? 'Encendiendo Robot...' : '⚡ Encender Robot de WhatsApp'"></span>
                                </button>
                            </div>

                            {{-- Estado: Disconnected / Auth Failure / Error --}}
                            <div x-show="status === 'disconnected' || status === 'auth_failure' || status === 'error'" class="rounded-2xl border border-red-200 bg-red-50/70 p-4 text-center text-[11px] text-red-800 space-y-2.5 shadow-xs">
                                <p class="font-bold text-red-900" x-text="status === 'error' ? 'Aviso en Motor de Mensajería' : 'Sesión Desconectada'"></p>
                                <div class="flex items-center justify-center flex-wrap gap-2 pt-1">
                                    <button type="button"
                                            @click="iniciarRobot()"
                                            :disabled="iniciandoServicio"
                                            class="inline-flex items-center gap-1 px-3 py-1 rounded-lg border border-gray-300 bg-white hover:bg-gray-50 text-gray-700 text-[11px] font-semibold shadow-xs">
                                        <span>⚡ Iniciar Motor</span>
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

            {{-- ───────────────────────────────────────────────────────────────────────────── --}}
            {{-- TAB 4: CONSULTAS RÁPIDAS (ENLACES OFICIALES)                                  --}}
            {{-- ───────────────────────────────────────────────────────────────────────────── --}}
            <div x-show="tabActiva === 'enlaces'" class="space-y-6">
                <div class="rounded-2xl border border-gray-200 bg-white shadow-xs p-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-gray-100 pb-4 mb-6">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-600 border border-amber-200">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-gray-900">Consultas Rápidas y Enlaces Oficiales</h3>
                                <p class="text-[11px] text-gray-500">Configura los accesos directos que se desplegarán en el menú superior para todo el equipo contable.</p>
                            </div>
                        </div>

                        <span class="text-xs text-gray-400 font-mono" x-text="`${enlaces.length} enlace(s) configurado(s)`"></span>
                    </div>

                    {{-- Formulario de Creación / Edición --}}
                    <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4 mb-6">
                        <div class="flex items-center justify-between mb-3">
                            <h4 class="text-xs font-bold text-gray-800 flex items-center gap-1.5">
                                <span class="h-2 w-2 rounded-full" :class="editandoEnlace ? 'bg-amber-500' : 'bg-brand-500'"></span>
                                <span x-text="editandoEnlace ? 'Editar Acceso Rápido' : 'Agregar Nuevo Acceso Rápido'"></span>
                            </h4>

                            <template x-if="editandoEnlace">
                                <button type="button"
                                        @click="cancelarEdicionEnlace()"
                                        class="text-[11px] font-semibold text-gray-500 hover:text-gray-800 transition cursor-pointer">
                                    Cancelar Edición
                                </button>
                            </template>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-12 gap-3 text-xs">
                            <div class="md:col-span-5">
                                <label class="block font-semibold text-gray-700 mb-1">Nombre o Etiqueta <span class="text-rose-500">*</span></label>
                                <input type="text"
                                       x-model="formEnlace.label"
                                       placeholder="Ej. SUNAT - Clave SOL"
                                       class="w-full rounded-xl border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            <div class="md:col-span-5">
                                <label class="block font-semibold text-gray-700 mb-1">Dirección URL <span class="text-rose-500">*</span></label>
                                <input type="url"
                                       x-model="formEnlace.url"
                                       placeholder="https://..."
                                       class="w-full rounded-xl border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>

                            <div class="md:col-span-2">
                                <label class="block font-semibold text-gray-700 mb-1">Orden</label>
                                <input type="number"
                                       x-model.number="formEnlace.orden"
                                       min="0"
                                       placeholder="1"
                                       class="w-full rounded-xl border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-2xs focus:border-brand-500 focus:ring-brand-500">
                            </div>
                        </div>

                        <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-200">
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none text-xs text-gray-700">
                                <input type="checkbox"
                                       x-model="formEnlace.activo"
                                       class="rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                                <span>Habilitado (Visible en el menú)</span>
                            </label>

                            <button type="button"
                                    @click="guardarEnlace()"
                                    :disabled="guardandoEnlace || !formEnlace.label.trim() || !formEnlace.url.trim()"
                                    class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl text-xs font-bold text-white shadow-2xs transition disabled:opacity-50 cursor-pointer active:scale-95"
                                    :class="editandoEnlace ? 'bg-amber-600 hover:bg-amber-700' : 'bg-brand-600 hover:bg-brand-700'">
                                <svg x-show="!guardandoEnlace" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <svg x-show="guardandoEnlace" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                                <span x-text="editandoEnlace ? 'Actualizar Enlace' : 'Agregar Enlace'"></span>
                            </button>
                        </div>
                    </div>

                    {{-- Tabla de Accesos Rápidos --}}
                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="w-full text-left text-xs text-gray-700">
                            <thead class="bg-gray-50 text-[11px] font-bold text-gray-500 uppercase tracking-wider border-b border-gray-200">
                                <tr>
                                    <th scope="col" class="py-2.5 px-3 w-20 text-center">Orden</th>
                                    <th scope="col" class="py-2.5 px-4">Etiqueta</th>
                                    <th scope="col" class="py-2.5 px-4">URL Destino</th>
                                    <th scope="col" class="py-2.5 px-3 text-center">Estado</th>
                                    <th scope="col" class="py-2.5 px-4 text-right">Acciones</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 bg-white">
                                <template x-for="(item, index) in enlaces" :key="item.id">
                                    <tr class="hover:bg-gray-50/80 transition" :class="editandoEnlace && formEnlace.id === item.id ? 'bg-amber-50/50' : ''">
                                        <td class="py-2.5 px-3 text-center">
                                            <div class="flex items-center justify-center gap-1">
                                                <button type="button"
                                                        @click="moverEnlace(index, -1)"
                                                        :disabled="index === 0"
                                                        class="p-0.5 rounded text-gray-400 hover:text-gray-700 disabled:opacity-20 cursor-pointer"
                                                        title="Mover arriba">
                                                    ▲
                                                </button>
                                                <span class="inline-flex items-center justify-center font-bold text-[11px] text-gray-600 bg-gray-100 rounded-md px-1.5 py-0.5" x-text="item.orden"></span>
                                                <button type="button"
                                                        @click="moverEnlace(index, 1)"
                                                        :disabled="index === enlaces.length - 1"
                                                        class="p-0.5 rounded text-gray-400 hover:text-gray-700 disabled:opacity-20 cursor-pointer"
                                                        title="Mover abajo">
                                                    ▼
                                                </button>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-4 font-semibold text-gray-900">
                                            <span x-text="item.label"></span>
                                        </td>
                                        <td class="py-2.5 px-4 text-gray-500 font-mono text-[11px]">
                                            <div class="flex items-center gap-1.5 max-w-md truncate">
                                                <a :href="item.url" target="_blank" rel="noopener noreferrer" class="text-brand-600 hover:underline truncate" :title="item.url" x-text="item.url"></a>
                                                <svg class="h-3.5 w-3.5 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                                </svg>
                                            </div>
                                        </td>
                                        <td class="py-2.5 px-3 text-center">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold"
                                                  :class="item.activo ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-600'"
                                                  x-text="item.activo ? 'Activo' : 'Inactivo'"></span>
                                        </td>
                                        <td class="py-2.5 px-4 text-right">
                                            <div class="inline-flex items-center gap-1">
                                                <button type="button"
                                                        @click="editarEnlace(item)"
                                                        class="p-1.5 rounded-lg text-gray-500 hover:text-brand-600 hover:bg-gray-100 transition cursor-pointer"
                                                        title="Editar enlace">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                    </svg>
                                                </button>
                                                <button type="button"
                                                        @click="eliminarEnlace(item.id)"
                                                        class="p-1.5 rounded-lg text-gray-500 hover:text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                                                        title="Eliminar enlace">
                                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                    </svg>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                </template>

                                <template x-if="enlaces.length === 0">
                                    <tr>
                                        <td colspan="5" class="py-6 text-center text-xs text-gray-400">
                                            No hay accesos rápidos registrados todavía. Agrega el primero usando el formulario superior.
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            {{-- ════════════════════ BARRA DE ACCIÓN INFERIOR PEGAJOSA (STICKY SAVE BAR) ════════════════════ --}}
            <div x-show="tabActiva !== 'enlaces'"
                 class="sticky bottom-4 z-30 mt-8 rounded-2xl border border-gray-200/90 bg-white/95 px-5 py-3.5 shadow-xl backdrop-blur-md flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 transition-all">
                <div class="flex items-center gap-2.5">
                    <span class="relative flex h-2.5 w-2.5">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-brand-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-brand-600"></span>
                    </span>
                    <p class="text-xs text-gray-700">
                        <span class="font-semibold text-gray-500">Configuración activa:</span>
                        <strong class="font-bold text-gray-900" x-text="tabActiva === 'perfil' ? 'Identidad & Marca' : (tabActiva === 'rpa' ? 'Motor RPA SUNAT' : 'Integración WhatsApp')"></strong>
                    </p>
                    <span class="hidden md:inline-block text-[11px] text-gray-400">· Guarda para aplicar los cambios a nivel general en el CRM</span>
                </div>

                <div class="flex items-center justify-end gap-3">
                    <button type="submit"
                            :disabled="guardando"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white px-5 py-2.5 text-xs font-bold shadow-md transition disabled:opacity-50 active:scale-95 cursor-pointer">
                        <svg x-show="!guardando" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <svg x-show="guardando" class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span x-text="guardando ? 'Guardando Ajustes...' : 'Guardar Ajustes del Sistema'"></span>
                    </button>
                </div>
            </div>

        </form>
    </div>

    {{-- ════════════════════ SCRIPTS ALPINE.JS ════════════════════ --}}
    <script>
        function panelAjustes(initialData) {
            return {
                tabActiva: 'perfil',
                form: {
                    razon_social: initialData.razon_social || '',
                    nombre_comercial: initialData.nombre_comercial || '',
                    ruc: initialData.ruc || '',
                    representante: initialData.representante || '',
                    colegiatura: initialData.colegiatura || '',
                    slogan: initialData.slogan || '',
                    correo_corporativo: initialData.correo_corporativo || '',
                    telefono_whatsapp: initialData.telefono_whatsapp || '',
                    direccion: initialData.direccion || '',
                    ciudad: initialData.ciudad || '',
                    sitio_web: initialData.sitio_web || '',
                    sidebar_display: initialData.sidebar_display || 'logo_nombre',
                    logo_actual: initialData.logo_actual || null,
                    logo_nombre_actual: initialData.logo_nombre_actual || null,
                    isotipo_actual: initialData.isotipo_actual || null,
                    isotipo_nombre_actual: initialData.isotipo_nombre_actual || null,
                    logo_reportes_actual: initialData.logo_reportes_actual || null,
                    logo_reportes_nombre_actual: initialData.logo_reportes_nombre_actual || null,
                    frecuencia_cron_horas: initialData.frecuencia_cron_horas || 8,
                    notificar_whatsapp: Boolean(initialData.notificar_whatsapp),
                    nivel_alerta_whatsapp: initialData.nivel_alerta_whatsapp || 'Solo Altas'
                },
                logoFile: null,
                logo_nombre_nuevo: null,
                previewLogoUrl: null,
                eliminarLogo: false,
                isotipoFile: null,
                isotipo_nombre_nuevo: null,
                previewIsotipoUrl: null,
                eliminarIsotipo: false,
                logoReportesFile: null,
                logo_reportes_nombre_nuevo: null,
                previewLogoReportesUrl: null,
                eliminarLogoReportes: false,
                guardando: false,
                probandoWhatsApp: false,
                sincronizandoRpa: false,
                enlaces: initialData.enlaces || [],
                formEnlace: {
                    id: null,
                    label: '',
                    url: '',
                    orden: (initialData.enlaces || []).length + 1,
                    activo: true,
                },
                editandoEnlace: false,
                guardandoEnlace: false,
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
                    }, 4000);
                },

                editarEnlace(item) {
                    this.editandoEnlace = true;
                    this.formEnlace = {
                        id: item.id,
                        label: item.label,
                        url: item.url,
                        orden: item.orden,
                        activo: Boolean(item.activo),
                    };
                },

                cancelarEdicionEnlace() {
                    this.editandoEnlace = false;
                    this.formEnlace = {
                        id: null,
                        label: '',
                        url: '',
                        orden: this.enlaces.length + 1,
                        activo: true,
                    };
                },

                async guardarEnlace() {
                    if (this.guardandoEnlace) return;

                    const label = (this.formEnlace.label || '').trim();
                    const url = (this.formEnlace.url || '').trim();

                    if (!label || !url) {
                        this.lanzarToast('Campos requeridos', 'Por favor ingresa la etiqueta y la URL del enlace.', 'error');
                        return;
                    }

                    this.guardandoEnlace = true;
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    try {
                        const esEdicion = this.editandoEnlace && this.formEnlace.id;
                        const endpoint = esEdicion
                            ? `{{ url('/ajustes/quick-links') }}/${this.formEnlace.id}`
                            : `{{ route('quick-links.store') }}`;
                        const method = esEdicion ? 'PUT' : 'POST';

                        const response = await fetch(endpoint, {
                            method: method,
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                label: label,
                                url: url,
                                orden: this.formEnlace.orden || 1,
                                activo: this.formEnlace.activo,
                            }),
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            if (esEdicion) {
                                const idx = this.enlaces.findIndex(e => e.id === this.formEnlace.id);
                                if (idx !== -1) {
                                    this.enlaces[idx] = data.link;
                                }
                            } else {
                                this.enlaces.push(data.link);
                            }

                            this.enlaces.sort((a, b) => (a.orden || 0) - (b.orden || 0));
                            this.cancelarEdicionEnlace();
                            this.lanzarToast('Éxito', data.message || 'Acceso rápido guardado.', 'success');
                        } else {
                            throw new Error(data.message || 'Error al guardar el acceso rápido.');
                        }
                    } catch (err) {
                        console.error('Error al guardar acceso rápido:', err);
                        this.lanzarToast('Error', err.message || 'No fue posible guardar el acceso.', 'error');
                    } finally {
                        this.guardandoEnlace = false;
                    }
                },

                async eliminarEnlace(id) {
                    if (!confirm('¿Confirma que desea eliminar este acceso rápido?')) return;

                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

                    try {
                        const response = await fetch(`{{ url('/ajustes/quick-links') }}/${id}`, {
                            method: 'DELETE',
                            headers: {
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                        });

                        const data = await response.json();

                        if (response.ok && data.success) {
                            this.enlaces = this.enlaces.filter(e => e.id !== id);
                            if (this.editandoEnlace && this.formEnlace.id === id) {
                                this.cancelarEdicionEnlace();
                            }
                            this.lanzarToast('Eliminado', data.message || 'Acceso rápido eliminado.', 'info');
                        } else {
                            throw new Error(data.message || 'Error al eliminar.');
                        }
                    } catch (err) {
                        console.error('Error al eliminar acceso rápido:', err);
                        this.lanzarToast('Error', err.message || 'No fue posible eliminar el enlace.', 'error');
                    }
                },

                async moverEnlace(index, delta) {
                    const newIndex = index + delta;
                    if (newIndex < 0 || newIndex >= this.enlaces.length) return;

                    const item = this.enlaces.splice(index, 1)[0];
                    this.enlaces.splice(newIndex, 0, item);

                    this.enlaces.forEach((el, idx) => {
                        el.orden = idx + 1;
                    });

                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                    try {
                        await fetch(`{{ route('quick-links.reorder') }}`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                            },
                            body: JSON.stringify({
                                ids: this.enlaces.map(e => e.id),
                            }),
                        });
                        this.lanzarToast('Orden Actualizado', 'El orden de los accesos rápidos se ha guardado.', 'success');
                    } catch (err) {
                        console.warn('Error sincronizando orden:', err);
                    }
                },

                seleccionarArchivo(event) {
                    const file = event.target.files[0];
                    if (!file) return;

                    if (file.size > 5 * 1024 * 1024) {
                        this.lanzarToast('Archivo demasiado grande', 'El logotipo debe pesar menos de 5MB.', 'error');
                        event.target.value = '';
                        return;
                    }

                    this.logoFile = file;
                    this.logo_nombre_nuevo = file.name;
                    this.eliminarLogo = false;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.previewLogoUrl = e.target.result;
                    };
                    reader.readAsDataURL(file);
                },

                quitarLogo() {
                    this.logoFile = null;
                    this.logo_nombre_nuevo = null;
                    this.previewLogoUrl = null;
                    this.eliminarLogo = true;
                    const fileInput = document.getElementById('logo_file_input');
                    if (fileInput) fileInput.value = '';
                },

                seleccionarIsotipo(event) {
                    const file = event.target.files[0];
                    if (!file) return;

                    if (file.size > 5 * 1024 * 1024) {
                        this.lanzarToast('Archivo demasiado grande', 'El isotipo debe pesar menos de 5MB.', 'error');
                        event.target.value = '';
                        return;
                    }

                    this.isotipoFile = file;
                    this.isotipo_nombre_nuevo = file.name;
                    this.eliminarIsotipo = false;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.previewIsotipoUrl = e.target.result;
                    };
                    reader.readAsDataURL(file);
                },

                quitarIsotipo() {
                    this.isotipoFile = null;
                    this.isotipo_nombre_nuevo = null;
                    this.previewIsotipoUrl = null;
                    this.eliminarIsotipo = true;
                    const fileInput = document.getElementById('isotipo_file_input');
                    if (fileInput) fileInput.value = '';
                },

                seleccionarLogoReportes(event) {
                    const file = event.target.files[0];
                    if (!file) return;

                    if (file.size > 5 * 1024 * 1024) {
                        this.lanzarToast('Archivo demasiado grande', 'El logotipo debe pesar menos de 5MB.', 'error');
                        event.target.value = '';
                        return;
                    }

                    this.logoReportesFile = file;
                    this.logo_reportes_nombre_nuevo = file.name;
                    this.eliminarLogoReportes = false;
                    const reader = new FileReader();
                    reader.onload = (e) => {
                        this.previewLogoReportesUrl = e.target.result;
                    };
                    reader.readAsDataURL(file);
                },

                quitarLogoReportes() {
                    this.logoReportesFile = null;
                    this.logo_reportes_nombre_nuevo = null;
                    this.previewLogoReportesUrl = null;
                    this.eliminarLogoReportes = true;
                    const fileInput = document.getElementById('logo_reportes_file_input');
                    if (fileInput) fileInput.value = '';
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

                        const formData = new FormData();
                        formData.append('razon_social', this.form.razon_social);
                        formData.append('nombre', this.form.razon_social);
                        formData.append('nombre_comercial', this.form.nombre_comercial || '');
                        formData.append('ruc', this.form.ruc);
                        formData.append('representante', this.form.representante);
                        formData.append('colegiatura', this.form.colegiatura || '');
                        formData.append('slogan', this.form.slogan || '');
                        formData.append('correo_corporativo', this.form.correo_corporativo || '');
                        formData.append('correo', this.form.correo_corporativo || '');
                        formData.append('telefono_whatsapp', this.form.telefono_whatsapp || '');
                        formData.append('telefono', this.form.telefono_whatsapp || '');
                        formData.append('direccion', this.form.direccion || '');
                        formData.append('ciudad', this.form.ciudad || '');
                        formData.append('sitio_web', this.form.sitio_web || '');
                        formData.append('sidebar_display', this.form.sidebar_display || 'logo_nombre');
                        formData.append('frecuencia_cron_horas', this.form.frecuencia_cron_horas);
                        formData.append('notificar_whatsapp', this.form.notificar_whatsapp ? '1' : '0');
                        formData.append('nivel_alerta_whatsapp', this.form.nivel_alerta_whatsapp);

                        if (this.logoFile) {
                            formData.append('logo', this.logoFile);
                        }
                        if (this.eliminarLogo) {
                            formData.append('eliminar_logo', '1');
                        }
                        if (this.isotipoFile) {
                            formData.append('isotipo', this.isotipoFile);
                        }
                        if (this.eliminarIsotipo) {
                            formData.append('eliminar_isotipo', '1');
                        }
                        if (this.logoReportesFile) {
                            formData.append('logo_reportes', this.logoReportesFile);
                        }
                        if (this.eliminarLogoReportes) {
                            formData.append('eliminar_logo_reportes', '1');
                        }

                        const response = await fetch('{{ route('ajustes.update') }}', {
                            method: 'POST',
                            headers: {
                                'X-CSRF-TOKEN': csrfToken,
                                'Accept': 'application/json'
                            },
                            body: formData
                        });

                        const data = await response.json();

                        if (!response.ok) {
                            const errorMsg = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Error al guardar los ajustes.');
                            throw new Error(errorMsg);
                        }

                        if (data.logo_url !== undefined) {
                            this.form.logo_actual = data.logo_url;
                            this.form.logo_nombre_actual = data.logo_nombre || (this.logoFile ? this.logoFile.name : null);
                            this.previewLogoUrl = null;
                            this.logoFile = null;
                            this.logo_nombre_nuevo = null;
                            this.eliminarLogo = false;
                        }
                        if (data.isotipo_url !== undefined) {
                            this.form.isotipo_actual = data.isotipo_url;
                            this.form.isotipo_nombre_actual = data.isotipo_nombre || (this.isotipoFile ? this.isotipoFile.name : null);
                            this.previewIsotipoUrl = null;
                            this.isotipoFile = null;
                            this.isotipo_nombre_nuevo = null;
                            this.eliminarIsotipo = false;
                        }
                        if (data.logo_reportes_url !== undefined) {
                            this.form.logo_reportes_actual = data.logo_reportes_url;
                            this.form.logo_reportes_nombre_actual = data.logo_reportes_nombre || (this.logoReportesFile ? this.logoReportesFile.name : null);
                            this.previewLogoReportesUrl = null;
                            this.logoReportesFile = null;
                            this.logo_reportes_nombre_nuevo = null;
                            this.eliminarLogoReportes = false;
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
                status: 'offline',
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
                        if (document.hidden || this.consultando) return;
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
