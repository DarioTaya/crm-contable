@extends('layouts.app')

@section('title', 'Onboarding — Nuevo Cliente')
@section('header', 'Onboarding — Nuevo Cliente')

@section('content')
    <div x-data="onboardingApp()" x-cloak class="mx-auto max-w-4xl space-y-8">
        <form method="POST"
              action="{{ route('clientes.store') }}"
              x-ref="form"
              @submit.prevent="handleSubmit()"
              autocomplete="off"
              class="space-y-8">
            @csrf

            {{-- El navegador vuelca aquí el usuario y la clave guardados, y no en Clave SOL ni Client ID. --}}
            <div aria-hidden="true">
                <input type="text" tabindex="-1" autocomplete="username" style="position:fixed;left:-10000px;top:0;width:200px;height:40px;" aria-hidden="true">
                <input type="password" tabindex="-1" autocomplete="current-password" style="position:fixed;left:-10000px;top:0;width:200px;height:40px;" aria-hidden="true">
            </div>

            {{-- ═══════════════════════════ SECCIÓN 1: DATOS FISCALES ═══════════════════════════ --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-xs">
                <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-100 text-brand-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Datos Fiscales</h2>
                            <p class="text-xs text-gray-500">Información tributaria verificada ante SUNAT</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium transition"
                          :class="{
                              'bg-gray-100 text-gray-600': rucInfo.status === 'idle',
                              'bg-amber-50 text-amber-700 border border-amber-200': rucInfo.status === 'validating',
                              'bg-emerald-50 text-emerald-700 border border-emerald-200': rucInfo.status === 'valid',
                              'bg-rose-50 text-rose-700 border border-rose-200': rucInfo.status === 'invalid',
                              'bg-yellow-50 text-yellow-800 border border-yellow-200': rucInfo.status === 'duplicate'
                          }">
                        <span class="h-1.5 w-1.5 rounded-full"
                              :class="{
                                  'bg-gray-400': rucInfo.status === 'idle',
                                  'bg-amber-500 animate-pulse': rucInfo.status === 'validating',
                                  'bg-emerald-500': rucInfo.status === 'valid',
                                  'bg-rose-500': rucInfo.status === 'invalid',
                                  'bg-yellow-500': rucInfo.status === 'duplicate'
                              }"></span>
                        <span x-text="rucInfo.badgeText || 'Esperando RUC'"></span>
                    </span>
                </div>

                <div class="grid grid-cols-1 gap-x-6 gap-y-5 p-6 sm:grid-cols-2">
                    {{-- RUC con validación matemática Módulo 11 y auto-completado --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="ruc" class="block text-sm font-medium text-gray-700">
                                RUC <span class="text-red-500">*</span>
                            </label>
                            <span class="text-[11px] font-mono text-gray-400" x-text="`${ruc.length}/11 dígitos`"></span>
                        </div>
                        <div class="relative">
                            <input
                                type="text"
                                id="ruc"
                                name="ruc"
                                x-model="ruc"
                                @input="onRucInput($event)"
                                @blur="buscarRucSiEstaCompleto()"
                                maxlength="11"
                                placeholder="20601234567"
                                required
                                class="block w-full rounded-lg border px-3.5 py-2.5 text-sm font-mono text-gray-900 shadow-xs transition placeholder-gray-400 focus:outline-none focus:ring-2"
                                :class="{
                                    'border-gray-300 focus:border-brand-500 focus:ring-brand-500/20': rucInfo.status === 'idle',
                                    'border-amber-400 ring-2 ring-amber-400/20': rucInfo.status === 'validating',
                                    'border-emerald-500 ring-2 ring-emerald-500/20': rucInfo.status === 'valid',
                                    'border-rose-400 ring-2 ring-rose-400/20': rucInfo.status === 'invalid',
                                    'border-yellow-400 ring-2 ring-yellow-400/20': rucInfo.status === 'duplicate'
                                }"
                            >
                            {{-- Indicador visual de estado --}}
                            <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3">
                                <template x-if="rucInfo.status === 'validating'">
                                    <svg class="h-4 w-4 animate-spin text-amber-500" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </template>
                                <template x-if="rucInfo.status === 'valid'">
                                    <svg class="h-5 w-5 text-emerald-600" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                    </svg>
                                </template>
                                <template x-if="rucInfo.status === 'invalid'">
                                    <svg class="h-5 w-5 text-rose-500" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                    </svg>
                                </template>
                                <template x-if="rucInfo.status === 'duplicate'">
                                    <svg class="h-5 w-5 text-yellow-600" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.17 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd" />
                                    </svg>
                                </template>
                            </div>
                        </div>
                        <template x-if="rucInfo.message">
                            <p class="mt-1.5 text-xs flex items-center gap-1"
                               :class="{
                                   'text-emerald-700': rucInfo.status === 'valid',
                                   'text-rose-600 font-medium': rucInfo.status === 'invalid',
                                   'text-yellow-700 font-medium': rucInfo.status === 'duplicate',
                                   'text-gray-500': rucInfo.status === 'validating'
                               }">
                                <span x-text="rucInfo.message"></span>
                            </p>
                        </template>
                        @error('ruc')
                            <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Razón Social / Denominación con aviso de auto-completado --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="razon_social" class="block text-sm font-medium text-gray-700">
                                Razón Social <span class="text-red-500">*</span>
                            </label>
                            <template x-if="razonSocialAuto">
                                <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-1.5 py-0.5">
                                    <span>✓ Obtenido de SUNAT</span>
                                </span>
                            </template>
                        </div>
                        <input
                            type="text"
                            id="razon_social"
                            name="razon_social"
                            x-model="razonSocial"
                            @input="razonSocialAuto = false"
                            value="{{ old('razon_social') }}"
                            placeholder="EMPRESA DEMO S.A.C."
                            required
                            class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('razon_social') border-red-400 ring-2 ring-red-400/20 @enderror"
                        >
                        @error('razon_social')
                            <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Régimen Tributario --}}
                    <div>
                        <label for="regimen_tributario" class="mb-1.5 block text-sm font-medium text-gray-700">
                            Régimen Tributario <span class="text-red-500">*</span>
                        </label>
                        <select
                            id="regimen_tributario"
                            name="regimen_tributario"
                            x-model="regimenTributario"
                            required
                            class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('regimen_tributario') border-red-400 ring-2 ring-red-400/20 @enderror"
                        >
                            <option value="" disabled {{ old('regimen_tributario') ? '' : 'selected' }}>Seleccionar régimen…</option>
                            @foreach (\App\Enums\RegimenTributario::opciones() as $value => $label)
                                <option value="{{ $value }}" @selected(old('regimen_tributario') === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                        @error('regimen_tributario')
                            <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Correo --}}
                    <div>
                        <label for="correo" class="mb-1.5 block text-sm font-medium text-gray-700">Correo electrónico</label>
                        <input
                            type="email"
                            id="correo"
                            name="correo"
                            value="{{ old('correo') }}"
                            placeholder="contacto@empresa.pe"
                            class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('correo') border-red-400 ring-2 ring-red-400/20 @enderror"
                        >
                        @error('correo')
                            <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- Teléfono --}}
                    <div>
                        <label for="telefono" class="mb-1.5 block text-sm font-medium text-gray-700">Teléfono / WhatsApp</label>
                        <input
                            type="text"
                            id="telefono"
                            name="telefono"
                            value="{{ old('telefono') }}"
                            placeholder="987 654 321"
                            class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('telefono') border-red-400 ring-2 ring-red-400/20 @enderror"
                        >
                        @error('telefono')
                            <p class="mt-1.5 text-xs text-red-500">{{ $message }}</p>
                        @enderror
                    </div>

                    {{-- ── SITUACIÓN EN EL PADRÓN OFICIAL SUNAT (AUTO-COMPLETADO) ── --}}
                    <div class="sm:col-span-2 border-t border-gray-100 pt-6 space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <h3 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <span class="text-base">🏛️</span>
                                Situación en el Padrón Oficial SUNAT
                            </h3>
                            <template x-if="estadoContribuyente">
                                <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                                    Sincronizado con SUNAT
                                </span>
                            </template>
                        </div>

                        {{-- Hidden inputs para enviar con el POST --}}
                        <input type="hidden" name="estado_contribuyente" x-model="estadoContribuyente">
                        <input type="hidden" name="condicion_domicilio" x-model="condicionDomicilio">
                        <input type="hidden" name="tipo_contribuyente" x-model="tipoContribuyente">

                        {{-- Fila 1: Tipo, Estado y Condición alineados con los estilos del formulario --}}
                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            {{-- Tipo de Contribuyente --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Tipo de Contribuyente</label>
                                <div class="flex items-center h-[42px] px-3.5 rounded-lg border border-gray-300 bg-gray-50 text-sm text-gray-800 shadow-xs truncate">
                                    <span x-text="tipoContribuyente || 'Pendiente de RUC'"></span>
                                </div>
                            </div>

                            {{-- Estado del Contribuyente --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Estado del Contribuyente</label>
                                <div class="flex items-center h-[42px] px-3.5 rounded-lg border border-gray-300 bg-gray-50 shadow-xs">
                                    <template x-if="estadoContribuyente">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                              :class="estadoContribuyente === 'ACTIVO' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                              x-text="estadoContribuyente"></span>
                                    </template>
                                    <template x-if="!estadoContribuyente">
                                        <span class="text-xs text-gray-400 italic">Esperando RUC…</span>
                                    </template>
                                </div>
                            </div>

                            {{-- Condición de Domicilio --}}
                            <div>
                                <label class="block text-xs font-semibold text-gray-700 mb-1.5">Condición de Domicilio</label>
                                <div class="flex items-center h-[42px] px-3.5 rounded-lg border border-gray-300 bg-gray-50 shadow-xs">
                                    <template x-if="condicionDomicilio">
                                        <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                              :class="condicionDomicilio === 'HABIDO' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                              x-text="condicionDomicilio"></span>
                                    </template>
                                    <template x-if="!condicionDomicilio">
                                        <span class="text-xs text-gray-400 italic">Esperando RUC…</span>
                                    </template>
                                </div>
                            </div>
                        </div>

                        {{-- Fila 2: Dirección Fiscal Registrada --}}
                        <div>
                            <div class="flex items-center justify-between mb-1.5">
                                <label for="direccion_fiscal" class="block text-xs font-semibold text-gray-700">
                                    Dirección Fiscal Registrada
                                </label>
                                <template x-if="direccionFiscalAuto">
                                    <span class="inline-flex items-center gap-1 text-[11px] font-medium text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-1.5 py-0.5">
                                        <span>✓ Obtenido de SUNAT</span>
                                    </span>
                                </template>
                            </div>
                            <input
                                type="text"
                                id="direccion_fiscal"
                                name="direccion_fiscal"
                                x-model="direccionFiscal"
                                @input="direccionFiscalAuto = false"
                                value="{{ old('direccion_fiscal') }}"
                                placeholder="Ej: CALLE 4 MZA. G LOTE 1 URB. LA LAGUNA"
                                class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                            >
                        </div>

                        {{-- Fila 3: Ubicación / Ubigeo (Ubigeo, Distrito, Provincia, Departamento) --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label for="ubigeo" class="block text-xs font-medium text-gray-600 mb-1">Ubigeo</label>
                                <input
                                    type="text"
                                    id="ubigeo"
                                    name="ubigeo"
                                    x-model="ubigeo"
                                    value="{{ old('ubigeo') }}"
                                    placeholder="040409"
                                    maxlength="10"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm font-mono text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                >
                            </div>
                            <div>
                                <label for="distrito" class="block text-xs font-medium text-gray-600 mb-1">Distrito</label>
                                <input
                                    type="text"
                                    id="distrito"
                                    name="distrito"
                                    x-model="distrito"
                                    value="{{ old('distrito') }}"
                                    placeholder="ORCOPAMPA"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                >
                            </div>
                            <div>
                                <label for="provincia" class="block text-xs font-medium text-gray-600 mb-1">Provincia</label>
                                <input
                                    type="text"
                                    id="provincia"
                                    name="provincia"
                                    x-model="provincia"
                                    value="{{ old('provincia') }}"
                                    placeholder="CASTILLA"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                >
                            </div>
                            <div>
                                <label for="departamento" class="block text-xs font-medium text-gray-600 mb-1">Departamento</label>
                                <input
                                    type="text"
                                    id="departamento"
                                    name="departamento"
                                    x-model="departamento"
                                    value="{{ old('departamento') }}"
                                    placeholder="AREQUIPA"
                                    class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                >
                            </div>
                        </div>
                    </div>

                    {{-- Buen Contribuyente SUNAT --}}
                    <div class="sm:col-span-2 rounded-xl border border-emerald-200 bg-emerald-50/40 p-4">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input type="checkbox"
                                   name="es_buen_contribuyente"
                                   value="1"
                                   {{ old('es_buen_contribuyente') ? 'checked' : '' }}
                                   class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                            <div>
                                <span class="text-xs font-bold text-gray-900">⭐ Calidad de Buen Contribuyente / UESP (SUNAT)</span>
                                <p class="text-[11px] text-gray-600 mt-0.5">
                                    Activa el régimen de vencimiento especial en el Cronograma Oficial SUNAT para Buenos Contribuyentes y Unidades Ejecutoras del Sector Público.
                                </p>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════ SECCIÓN 2: CREDENCIALES DE ACCESO ═══════════════════════ --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-xs">
                <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </div>
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Credenciales de Acceso</h2>
                            <p class="text-xs text-gray-500">Las contraseñas se almacenan cifradas con AES-256 en la base de datos</p>
                        </div>
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 px-2.5 py-1 text-[11px] font-medium text-slate-700">
                        <svg class="h-3 w-3 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                        </svg>
                        Cifrado AES-256
                    </span>
                </div>

                <div class="space-y-6 p-6">
                    {{-- Clave SOL con comprobación silenciosa --}}
                    <div class="rounded-xl border border-gray-100 bg-gray-50/50 p-4">
                        <div class="mb-3 flex items-center gap-2">
                            <h3 class="text-sm font-semibold text-gray-800">Clave SOL (SUNAT)</h3>
                            <span class="rounded bg-sky-100 px-1.5 py-0.5 text-[10px] font-medium text-sky-700">Portal SOL</span>
                        </div>

                        <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                            <div>
                                <label for="usuario_sol" class="mb-1.5 block text-xs font-medium text-gray-600">Usuario SOL</label>
                                <input
                                    type="text"
                                    id="usuario_sol"
                                    name="usuario_sol"
                                    x-model="usuarioSol"
                                    @input="onSolChange()"
                                    value="{{ old('usuario_sol') }}"
                                    placeholder="MODDATOS"
                                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm font-mono uppercase text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                >
                            </div>
                            <div>
                                <label for="clave_sol" class="mb-1.5 block text-xs font-medium text-gray-600">Clave SOL</label>
                                <div class="relative">
                                    <input
                                        :type="mostrarClaveSol ? 'text' : 'password'"
                                        id="clave_sol"
                                        name="clave_sol"
                                        x-model="claveSol"
                                        autocomplete="new-password"
                                        autocapitalize="off"
                                        spellcheck="false"
                                        data-1p-ignore
                                        data-lpignore="true"
                                        data-valor-inicial=""
                                        readonly
                                        @focus="prepararCampoManual($event)"
                                        @input="registrarEdicionManual($event, 'clave')"
                                        @blur="revertirCampoSiNoFueEditado('clave_sol', false)"
                                        placeholder="••••••••"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 pr-10 text-sm font-mono text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                    <button
                                        type="button"
                                        @click="mostrarClaveSol = !mostrarClaveSol"
                                        tabindex="-1"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <template x-if="!mostrarClaveSol">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </template>
                                        <template x-if="mostrarClaveSol">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        </template>
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Feedback inline de estado Clave SOL --}}
                        <template x-if="solMessage">
                            <div class="mt-3 flex items-start gap-2 rounded-lg p-2.5 text-xs transition"
                                 :class="{
                                     'bg-emerald-50 text-emerald-800 border border-emerald-200': solStatus === 'valid',
                                     'bg-rose-50 text-rose-800 border border-rose-200': solStatus === 'invalid' || solStatus === 'error',
                                     'bg-blue-50 text-blue-800 border border-blue-200': solStatus === 'testing'
                                 }">
                                <template x-if="solStatus === 'valid'">
                                    <svg class="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.857-9.809a.75.75 0 00-1.214-.882l-3.483 4.79-1.88-1.88a.75.75 0 10-1.06 1.061l2.5 2.5a.75.75 0 001.137-.089l4-5.5z" clip-rule="evenodd" />
                                    </svg>
                                </template>
                                <template x-if="solStatus === 'invalid' || solStatus === 'error'">
                                    <svg class="h-4 w-4 text-rose-500 shrink-0 mt-0.5" viewBox="0 0 20 20" fill="currentColor">
                                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.28 7.22a.75.75 0 00-1.06 1.06L8.94 10l-1.72 1.72a.75.75 0 101.06 1.06L10 11.06l1.72 1.72a.75.75 0 101.06-1.06L11.06 10l1.72-1.72a.75.75 0 00-1.06-1.06L10 8.94 8.28 7.22z" clip-rule="evenodd" />
                                    </svg>
                                </template>
                                <template x-if="solStatus === 'testing'">
                                    <svg class="h-4 w-4 text-blue-600 animate-spin shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                    </svg>
                                </template>
                                <span class="leading-relaxed font-medium" x-text="solMessage"></span>
                            </div>
                        </template>
                    </div>

                    <hr class="border-gray-100">

                    {{-- Credenciales API SUNAT: opcionales --}}
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Credenciales API SUNAT</h3>
                            <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">Opcional</span>
                        </div>
                        <p class="mb-3 text-[11px] text-gray-500">Si aún no tienes el Client ID y el Client Secret, puedes registrar el cliente igual. Sirven para SIRE y la facturación electrónica.</p>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                            <div>
                                <label for="sunat_client_id" class="mb-1.5 block text-xs font-medium text-gray-600">Client ID</label>
                                <input
                                    type="text"
                                    id="sunat_client_id"
                                    name="sunat_client_id"
                                    value="{{ old('sunat_client_id') }}"
                                    data-valor-inicial="{{ old('sunat_client_id') }}"
                                    autocomplete="off"
                                    autocapitalize="off"
                                    spellcheck="false"
                                    data-1p-ignore
                                    data-lpignore="true"
                                    readonly
                                    @focus="prepararCampoManual($event)"
                                    @input="registrarEdicionManual($event, 'clientId')"
                                    @blur="revertirCampoSiNoFueEditado('sunat_client_id', false)"
                                    placeholder="Solo si ya lo tienes"
                                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm font-mono text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                >
                            </div>
                            <div>
                                <label for="sunat_client_secret" class="mb-1.5 block text-xs font-medium text-gray-600">Client Secret</label>
                                <div class="relative">
                                    <input
                                        type="text"
                                        id="sunat_client_secret"
                                        name="sunat_client_secret"
                                        value="{{ old('sunat_client_secret') }}"
                                        autocomplete="off"
                                        autocapitalize="off"
                                        spellcheck="false"
                                        data-1p-ignore
                                        data-lpignore="true"
                                        placeholder="••••••••"
                                        :style="mostrarClaveApi ? '' : '-webkit-text-security: disc'"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 pr-10 text-sm font-mono text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                    <button
                                        type="button"
                                        @click="mostrarClaveApi = !mostrarClaveApi"
                                        tabindex="-1"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none"
                                        title="Mostrar / ocultar clave">
                                        <template x-if="!mostrarClaveApi">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </template>
                                        <template x-if="mostrarClaveApi">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        </template>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    {{-- AFP --}}
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">AFPnet</h3>
                            <a href="https://www.afpnet.com.pe/"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700 hover:underline"
                               title="Abrir AFPnet Oficial">
                                <span>💼 Abrir AFPnet ↗</span>
                            </a>
                        </div>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                            <div>
                                <label for="usuario_afp" class="mb-1.5 block text-xs font-medium text-gray-600">Usuario AFP</label>
                                <input
                                    type="text"
                                    id="usuario_afp"
                                    name="usuario_afp"
                                    value="{{ old('usuario_afp') }}"
                                    placeholder="usuario_afp"
                                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                >
                            </div>
                            <div>
                                <label for="clave_afp" class="mb-1.5 block text-xs font-medium text-gray-600">Clave AFP</label>
                                <div class="relative">
                                    <input
                                        :type="mostrarClaveAfp ? 'text' : 'password'"
                                        id="clave_afp"
                                        name="clave_afp"
                                        placeholder="••••••••"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 pr-10 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                    <button
                                        type="button"
                                        @click="mostrarClaveAfp = !mostrarClaveAfp"
                                        tabindex="-1"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <template x-if="!mostrarClaveAfp">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </template>
                                        <template x-if="mostrarClaveAfp">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        </template>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <hr class="border-gray-100">

                    {{-- Detracciones --}}
                    <div>
                        <div class="mb-3 flex items-center justify-between">
                            <h3 class="text-sm font-semibold text-gray-700">Detracciones (Banco de la Nación)</h3>
                            <a href="https://bancaporinternet.bn.com.pe/BNWeb/Inicio"
                               target="_blank"
                               rel="noopener noreferrer"
                               class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-700 hover:underline"
                               title="Abrir Banco de la Nación por Internet">
                                <span>🏦 Abrir BN por Internet ↗</span>
                            </a>
                        </div>
                        <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-3">
                            <div>
                                <label for="dni_titular" class="mb-1.5 block text-xs font-medium text-gray-600">DNI Titular</label>
                                <input
                                    type="text"
                                    id="dni_titular"
                                    name="dni_titular"
                                    value="{{ old('dni_titular') }}"
                                    maxlength="8"
                                    placeholder="45678901"
                                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm font-mono text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('dni_titular') border-red-400 ring-2 ring-red-400/20 @enderror"
                                >
                            </div>
                            <div>
                                <label for="cuenta_detracciones" class="mb-1.5 block text-xs font-medium text-gray-600">N° Cuenta Detracciones</label>
                                <input
                                    type="text"
                                    id="cuenta_detracciones"
                                    name="cuenta_detracciones"
                                    value="{{ old('cuenta_detracciones') }}"
                                    maxlength="50"
                                    placeholder="00-000-000000"
                                    class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm font-mono text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 @error('cuenta_detracciones') border-red-400 ring-2 ring-red-400/20 @enderror"
                                >
                            </div>
                            <div>
                                <label for="clave_detracciones" class="mb-1.5 block text-xs font-medium text-gray-600">Clave Detracciones (Internet)</label>
                                <div class="relative">
                                    <input
                                        :type="mostrarClaveDetracciones ? 'text' : 'password'"
                                        id="clave_detracciones"
                                        name="clave_detracciones"
                                        placeholder="••••••••"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 pr-10 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                    <button
                                        type="button"
                                        @click="mostrarClaveDetracciones = !mostrarClaveDetracciones"
                                        tabindex="-1"
                                        class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                        <template x-if="!mostrarClaveDetracciones">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                            </svg>
                                        </template>
                                        <template x-if="mostrarClaveDetracciones">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                            </svg>
                                        </template>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        {{-- Acceso Adicional 1 (Libre / Bancos - ej. BCP) --}}
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-700">
                                    Acceso Adicional 1 (Bancos / Libre - Ej. BCP)
                                </h3>
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">Opcional</span>
                            </div>
                            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-3">
                                <div>
                                    <label for="banco_1_nombre" class="mb-1.5 block text-xs font-medium text-gray-600">Nombre o Entidad</label>
                                    <input
                                        type="text"
                                        id="banco_1_nombre"
                                        name="banco_1_nombre"
                                        value="{{ old('banco_1_nombre') }}"
                                        placeholder="Ej: Telecrédito BCP"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                </div>
                                <div>
                                    <label for="banco_1_usuario" class="mb-1.5 block text-xs font-medium text-gray-600">Usuario / N° Tarjeta</label>
                                    <input
                                        type="text"
                                        id="banco_1_usuario"
                                        name="banco_1_usuario"
                                        value="{{ old('banco_1_usuario') }}"
                                        placeholder="Ej: 4557 0000 0000 0000"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm font-mono text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                </div>
                                <div>
                                    <label for="banco_1_clave" class="mb-1.5 block text-xs font-medium text-gray-600">Clave / Contraseña</label>
                                    <div class="relative">
                                        <input
                                            :type="mostrarClaveBanco1 ? 'text' : 'password'"
                                            id="banco_1_clave"
                                            name="banco_1_clave"
                                            placeholder="••••••••"
                                            class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 pr-10 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                        >
                                        <button
                                            type="button"
                                            @click="mostrarClaveBanco1 = !mostrarClaveBanco1"
                                            tabindex="-1"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                            <template x-if="!mostrarClaveBanco1">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </template>
                                            <template x-if="mostrarClaveBanco1">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                </svg>
                                            </template>
                                        </button>
                                    </div>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="banco_1_notas" class="mb-1.5 block text-xs font-medium text-gray-600">Notas / Detalles Adicionales</label>
                                    <input
                                        type="text"
                                        id="banco_1_notas"
                                        name="banco_1_notas"
                                        value="{{ old('banco_1_notas') }}"
                                        placeholder="Ej: Para descarga de movimientos bancarios y extractos"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                </div>
                            </div>
                        </div>

                        <hr class="border-gray-100">

                        {{-- Acceso Adicional 2 (Libre / Bancos - ej. BBVA) --}}
                        <div>
                            <div class="mb-3 flex items-center justify-between">
                                <h3 class="text-sm font-semibold text-gray-700">
                                    Acceso Adicional 2 (Bancos / Libre - Ej. BBVA)
                                </h3>
                                <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium text-gray-600">Opcional</span>
                            </div>
                            <div class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-3">
                                <div>
                                    <label for="banco_2_nombre" class="mb-1.5 block text-xs font-medium text-gray-600">Nombre o Entidad</label>
                                    <input
                                        type="text"
                                        id="banco_2_nombre"
                                        name="banco_2_nombre"
                                        value="{{ old('banco_2_nombre') }}"
                                        placeholder="Ej: BBVA Net Cash"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                </div>
                                <div>
                                    <label for="banco_2_usuario" class="mb-1.5 block text-xs font-medium text-gray-600">Usuario / N° Tarjeta</label>
                                    <input
                                        type="text"
                                        id="banco_2_usuario"
                                        name="banco_2_usuario"
                                        value="{{ old('banco_2_usuario') }}"
                                        placeholder="Ej: BBVA-USER-123"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm font-mono text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                </div>
                                <div>
                                    <label for="banco_2_clave" class="mb-1.5 block text-xs font-medium text-gray-600">Clave / Contraseña</label>
                                    <div class="relative">
                                        <input
                                            :type="mostrarClaveBanco2 ? 'text' : 'password'"
                                            id="banco_2_clave"
                                            name="banco_2_clave"
                                            placeholder="••••••••"
                                            class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 pr-10 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                        >
                                        <button
                                            type="button"
                                            @click="mostrarClaveBanco2 = !mostrarClaveBanco2"
                                            tabindex="-1"
                                            class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 focus:outline-none">
                                            <template x-if="!mostrarClaveBanco2">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                                </svg>
                                            </template>
                                            <template x-if="mostrarClaveBanco2">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />
                                                </svg>
                                            </template>
                                        </button>
                                    </div>
                                </div>
                                <div class="sm:col-span-3">
                                    <label for="banco_2_notas" class="mb-1.5 block text-xs font-medium text-gray-600">Notas / Detalles Adicionales</label>
                                    <input
                                        type="text"
                                        id="banco_2_notas"
                                        name="banco_2_notas"
                                        value="{{ old('banco_2_notas') }}"
                                        placeholder="Ej: Token digital en celular de administración"
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs placeholder-gray-400 focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ═══════════════════════ BOTONES DE ACCIÓN ═══════════════════════ --}}
            <div class="flex items-center justify-between pt-2 pb-6">
                <p class="text-xs text-gray-500">
                    <span class="text-red-500">*</span> Campos obligatorios para el expediente digital.
                </p>

                <div class="flex items-center gap-3">
                    <a href="{{ route('clientes.index') }}"
                       class="rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-xs transition hover:bg-gray-50">
                        Cancelar
                    </a>
                    <button
                        type="submit"
                        class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-6 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>Registrar Cliente</span>
                    </button>
                </div>
            </div>
        </form>

        {{-- ════════════════════════════════════════════════════════════════════════════ --}}
        {{-- MODAL DE COMPROBACIÓN: DISEÑO ELEGANTE Y PROFESIONAL CRM (HUMAN SENIOR)      --}}
        {{-- ════════════════════════════════════════════════════════════════════════════ --}}
        <div x-show="modalOpen"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-gray-900/50 backdrop-blur-xs"
             style="display: none;">

            <div x-show="modalOpen"
                 x-transition:enter="transition ease-out duration-200 transform"
                 x-transition:enter-start="opacity-0 scale-95"
                 x-transition:enter-end="opacity-100 scale-100"
                 x-transition:leave="transition ease-in duration-150 transform"
                 x-transition:leave-start="opacity-100 scale-100"
                 x-transition:leave-end="opacity-0 scale-95"
                 class="w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white text-gray-800 shadow-2xl">

                {{-- Cabecera corporativa y limpia --}}
                <div class="border-b border-gray-100 bg-gray-50/80 px-6 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-50 text-brand-600 border border-brand-100 shadow-xs">
                            <template x-if="modalStatus === 'validating'">
                                <svg class="h-5 w-5 animate-spin text-brand-600" viewBox="0 0 24 24" fill="none">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                            </template>
                            <template x-if="modalStatus === 'success'">
                                <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                                </svg>
                            </template>
                            <template x-if="modalStatus === 'error'">
                                <svg class="h-5 w-5 text-rose-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                                </svg>
                            </template>
                        </div>
                        <div>
                            <h3 class="text-sm font-semibold text-gray-900 leading-tight">
                                <template x-if="modalStatus === 'validating'">
                                    <span>Comprobando Acceso con SUNAT</span>
                                </template>
                                <template x-if="modalStatus === 'success'">
                                    <span>¡Credenciales Verificadas!</span>
                                </template>
                                <template x-if="modalStatus === 'error'">
                                    <span>Observación en Credenciales SOL</span>
                                </template>
                            </h3>
                            <p class="text-xs text-gray-500 mt-0.5">Validación de seguridad en segundo plano</p>
                        </div>
                    </div>
                    <span class="rounded-full bg-gray-100 px-2.5 py-0.5 text-[10px] font-semibold text-gray-600 uppercase tracking-wider">
                        Portal SOL
                    </span>
                </div>

                {{-- Cuerpo del modal --}}
                <div class="p-6 space-y-4">
                    <p class="text-xs text-gray-600 leading-relaxed">
                        El sistema está validando las credenciales directamente con el portal de SUNAT de forma segura:
                    </p>

                    {{-- Pasos claros y formales --}}
                    <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 space-y-2.5">
                        {{-- Paso 1 --}}
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-700">✓</span>
                                <span class="text-gray-700 font-medium">Formato y algoritmo Módulo 11 de RUC</span>
                            </div>
                            <span class="text-[11px] font-medium text-emerald-600">Correcto</span>
                        </div>

                        {{-- Paso 2 --}}
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold"
                                      :class="{
                                          'bg-emerald-100 text-emerald-700': modalStep > 2 || modalStatus === 'success',
                                          'bg-brand-100 text-brand-700': modalStep === 2 && modalStatus === 'validating',
                                          'bg-gray-100 text-gray-400': modalStep < 2
                                      }">
                                    <template x-if="modalStep > 2 || modalStatus === 'success'">
                                        <span>✓</span>
                                    </template>
                                    <template x-if="modalStep <= 2 && modalStatus !== 'success'">
                                        <span>2</span>
                                    </template>
                                </span>
                                <span :class="modalStep >= 2 ? 'text-gray-700 font-medium' : 'text-gray-400'">
                                    Conexión segura con servidor SUNAT
                                </span>
                            </div>
                            <span class="text-[11px] font-medium"
                                  :class="{
                                      'text-emerald-600': modalStep > 2 || modalStatus === 'success',
                                      'text-brand-600': modalStep === 2 && modalStatus === 'validating',
                                      'text-gray-400': modalStep < 2
                                  }"
                                  x-text="modalStep > 2 || modalStatus === 'success' ? 'Conectado' : (modalStep === 2 ? 'Conectando...' : 'Pendiente')">
                            </span>
                        </div>

                        {{-- Paso 3 --}}
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center gap-2.5">
                                <span class="flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-bold"
                                      :class="{
                                          'bg-emerald-100 text-emerald-700': modalStep > 3 || modalStatus === 'success',
                                          'bg-rose-100 text-rose-700': modalStatus === 'error',
                                          'bg-brand-100 text-brand-700': modalStep === 3 && modalStatus === 'validating',
                                          'bg-gray-100 text-gray-400': modalStep < 3 && modalStatus !== 'error'
                                      }">
                                    <template x-if="modalStep > 3 || modalStatus === 'success'">
                                        <span>✓</span>
                                    </template>
                                    <template x-if="modalStatus === 'error'">
                                        <span>✕</span>
                                    </template>
                                    <template x-if="modalStep <= 3 && modalStatus === 'validating'">
                                        <span>3</span>
                                    </template>
                                </span>
                                <span :class="modalStep >= 3 ? 'text-gray-700 font-medium' : 'text-gray-400'">
                                    Autenticación de Usuario y Clave SOL
                                </span>
                            </div>
                            <span class="text-[11px] font-medium"
                                  :class="{
                                      'text-emerald-600': modalStep > 3 || modalStatus === 'success',
                                      'text-rose-600 font-semibold': modalStatus === 'error',
                                      'text-brand-600': modalStep === 3 && modalStatus === 'validating',
                                      'text-gray-400': modalStep < 3 && modalStatus !== 'error'
                                  }"
                                  x-text="modalStatus === 'error' ? 'Rechazado' : (modalStep > 3 || modalStatus === 'success' ? 'Autorizado' : (modalStep === 3 ? 'Verificando...' : 'Pendiente'))">
                            </span>
                        </div>
                    </div>

                    {{-- Alerta de Error limpia y legible --}}
                    <template x-if="modalStatus === 'error'">
                        <div class="rounded-xl border border-rose-200 bg-rose-50/80 p-3.5 text-xs text-rose-900 space-y-1.5">
                            <div class="flex items-center gap-1.5 font-semibold text-rose-700">
                                <svg class="h-4 w-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                </svg>
                                <span>Detalle de la observación:</span>
                            </div>
                            <p class="leading-relaxed text-rose-800" x-text="modalError"></p>
                            <p class="text-[11px] text-rose-600 pt-0.5">
                                Por favor verifique que el Usuario y la Clave SOL coincidan con los datos registrados en SUNAT.
                            </p>
                        </div>
                    </template>

                    {{-- Alerta de Éxito --}}
                    <template x-if="modalStatus === 'success'">
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50/80 p-3.5 text-xs text-emerald-900 space-y-1">
                            <div class="flex items-center gap-1.5 font-semibold text-emerald-700">
                                <svg class="h-4 w-4 shrink-0 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                <span>Credenciales confirmadas en SUNAT</span>
                            </div>
                            <p class="text-emerald-800">
                                Se estableció conexión exitosa. Guardando el expediente del cliente en el CRM...
                            </p>
                        </div>
                    </template>
                </div>

                {{-- Footer del modal con botones de acción --}}
                <div class="border-t border-gray-100 bg-gray-50/80 px-6 py-3.5 flex items-center justify-between">
                    <template x-if="modalStatus === 'validating'">
                        <div class="flex items-center gap-2 text-xs text-gray-500 w-full justify-center">
                            <svg class="h-3.5 w-3.5 animate-spin text-brand-600" fill="none" viewBox="0 0 24 24">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Comprobando en segundo plano...</span>
                        </div>
                    </template>

                    <template x-if="modalStatus === 'error'">
                        <div class="flex items-center justify-between w-full gap-3">
                            <button
                                type="button"
                                @click="guardarSinValidar()"
                                class="text-xs text-gray-500 hover:text-gray-700 underline focus:outline-none">
                                Guardar de todos modos (sin validar)
                            </button>
                            <button
                                type="button"
                                @click="cerrarModalYCorregir()"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-4 py-2 text-xs font-medium text-white shadow-xs transition hover:bg-gray-800 focus:outline-none focus:ring-2 focus:ring-gray-400">
                                <span>Corregir credenciales</span>
                            </button>
                        </div>
                    </template>

                    <template x-if="modalStatus === 'success'">
                        <div class="flex items-center gap-2 text-xs text-emerald-600 w-full justify-center font-medium">
                            <svg class="h-3.5 w-3.5 animate-spin" viewBox="0 0 24 24" fill="none">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                            </svg>
                            <span>Guardando cliente...</span>
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>

    {{-- Script Alpine.js para la lógica del Onboarding --}}
    <script>
        function onboardingApp() {
            return {
                ruc: '{{ old('ruc', '') }}',
                razonSocial: '{{ old('razon_social', '') }}',
                razonSocialAuto: false,
                regimenTributario: '{{ old('regimen_tributario', '') }}',
                estadoContribuyente: '{{ old('estado_contribuyente', '') }}',
                condicionDomicilio: '{{ old('condicion_domicilio', '') }}',
                tipoContribuyente: '{{ old('tipo_contribuyente', '') }}',
                direccionFiscal: '{{ old('direccion_fiscal', '') }}',
                direccionFiscalAuto: false,
                ubigeo: '{{ old('ubigeo', '') }}',
                distrito: '{{ old('distrito', '') }}',
                provincia: '{{ old('provincia', '') }}',
                departamento: '{{ old('departamento', '') }}',
                usuarioSol: '{{ old('usuario_sol', '') }}',
                claveSol: '',
                claveSolTocada: false,
                clientIdTocado: false,
                rucConsultaSeq: 0,
                rucEnConsulta: '',
                rucSincronizado: '',
                ultimoRucProcesado: '{{ old('ruc', '') }}',

                mostrarClaveSol: false,
                mostrarClaveApi: false,
                mostrarClaveAfp: false,
                mostrarClaveDetracciones: false,
                mostrarClaveBanco1: false,
                mostrarClaveBanco2: false,

                // Estado de validación RUC
                rucInfo: {
                    status: 'idle', // idle, validating, valid, invalid, duplicate
                    message: '',
                    badgeText: 'Esperando RUC',
                    tipo: ''
                },

                // Estado de validación Clave SOL
                solStatus: 'untested', // untested, testing, valid, invalid, error
                solMessage: '',
                solValidado: false,

                // Estado del Modal de Comprobación
                modalOpen: false,
                modalStep: 1,
                modalStatus: 'validating', // validating, success, error
                modalError: '',

                init() {
                    if (this.ruc.length === 11) {
                        this.rucEnConsulta = this.ruc;
                        this.validarRucCompleto();
                    }

                    this.$nextTick(() => this.vigilarAutocompletado());
                },

                vigilarAutocompletado() {
                    [80, 400, 1200].forEach((espera) => {
                        setTimeout(() => this.revertirAutocompletado(), espera);
                    });
                },

                prepararCampoManual(event) {
                    const el = event.target;
                    window.setTimeout(() => {
                        if (document.activeElement === el) {
                            el.removeAttribute('readonly');
                        }
                    }, 0);
                },

                registrarEdicionManual(event, campo) {
                    if (event?.inputType === 'insertReplacementText') {
                        return;
                    }

                    if (campo === 'clave') {
                        this.claveSolTocada = true;
                        this.onSolChange();
                        return;
                    }

                    this.clientIdTocado = true;
                },

                revertirAutocompletado() {
                    this.revertirCampoSiNoFueEditado('clave_sol', true);
                    this.revertirCampoSiNoFueEditado('sunat_client_id', true);
                },

                revertirCampoSiNoFueEditado(id, desdeTimer = false) {
                    const el = document.getElementById(id);
                    if (!el || (desdeTimer && document.activeElement === el)) {
                        return;
                    }

                    const tocado = id === 'clave_sol' ? this.claveSolTocada : this.clientIdTocado;
                    if (tocado) {
                        return;
                    }

                    const inicial = el.dataset.valorInicial || '';
                    if (el.value !== inicial) {
                        el.value = inicial;
                        if (id === 'clave_sol') {
                            this.claveSol = inicial;
                        }
                    }

                    el.setAttribute('readonly', 'readonly');
                },

                // ─── LÓGICA DE VALIDACIÓN RUC (MÓDULO 11 OFICIAL SUNAT) ───────
                onRucInput(event) {
                    const crudo = event?.target?.value ?? this.ruc;
                    const limpio = String(crudo).replace(/\D/g, '').slice(0, 11);
                    if (event?.target && event.target.value !== limpio) {
                        event.target.value = limpio;
                    }

                    this.ruc = limpio;

                    if (limpio === this.ultimoRucProcesado) {
                        return;
                    }

                    this.ultimoRucProcesado = limpio;
                    this.solValidado = false;
                    this.rucConsultaSeq++;

                    if (this.ruc.length === 0) {
                        this.rucEnConsulta = '';
                        this.rucSincronizado = '';
                        this.rucInfo = {
                            status: 'idle',
                            message: '',
                            badgeText: 'Esperando RUC',
                            tipo: ''
                        };
                        this.estadoContribuyente = '';
                        this.condicionDomicilio = '';
                        this.tipoContribuyente = '';
                        this.direccionFiscal = '';
                        this.direccionFiscalAuto = false;
                        this.ubigeo = '';
                        this.distrito = '';
                        this.provincia = '';
                        this.departamento = '';
                        return;
                    }

                    if (this.ruc.length < 11) {
                        this.rucInfo = {
                            status: 'idle',
                            message: '',
                            badgeText: 'Esperando RUC',
                            tipo: ''
                        };
                        return;
                    }

                    this.rucEnConsulta = this.ruc;
                    this.validarRucCompleto();
                },

                buscarRucSiEstaCompleto() {
                    if (this.ruc.length !== 11 || this.ruc === this.rucEnConsulta) {
                        return;
                    }

                    this.ultimoRucProcesado = this.ruc;
                    this.rucEnConsulta = this.ruc;
                    this.rucConsultaSeq++;
                    this.validarRucCompleto();
                },

                validarRucChecksum(ruc) {
                    if (!/^(10|15|16|17|20)\d{9}$/.test(ruc)) {
                        return false;
                    }
                    const factores = [5, 4, 3, 2, 7, 6, 5, 4, 3, 2];
                    let suma = 0;
                    for (let i = 0; i < 10; i++) {
                        suma += parseInt(ruc[i], 10) * factores[i];
                    }
                    const resto = 11 - (suma % 11);
                    let dv = resto;
                    if (resto === 10) dv = 0;
                    if (resto === 11) dv = 1;

                    return parseInt(ruc[10], 10) === dv;
                },

                validarRucCompleto() {
                    const ruc = this.ruc;
                    const prefijo = ruc.slice(0, 2);

                    if (!['10', '15', '16', '17', '20'].includes(prefijo)) {
                        this.rucInfo = {
                            status: 'invalid',
                            message: 'El RUC debe comenzar con 10, 20, 15, 16 o 17.',
                            badgeText: 'Prefijo no válido',
                            tipo: ''
                        };
                        return false;
                    }

                    const esValido = this.validarRucChecksum(ruc);
                    if (!esValido) {
                        this.rucInfo = {
                            status: 'invalid',
                            message: 'El número no supera la validación matemática oficial de SUNAT (dígito verificador incorrecto).',
                            badgeText: 'RUC Inválido',
                            tipo: ''
                        };
                        return false;
                    }

                    let tipoDesc = 'Persona Jurídica';
                    if (prefijo === '10') tipoDesc = 'Persona Natural con Negocio';
                    else if (['15', '16', '17'].includes(prefijo)) tipoDesc = 'Contribuyente Especial';

                    this.rucInfo = {
                        status: 'valid',
                        message: `✓ RUC válido ante SUNAT (${tipoDesc})`,
                        badgeText: '✓ RUC Válido',
                        tipo: tipoDesc
                    };

                    this.verificarRucEnServidor();
                    return true;
                },

                async verificarRucEnServidor() {
                    if (this.ruc.length !== 11 || this.rucInfo.status === 'invalid') return;

                    const seq = this.rucConsultaSeq;
                    const rucConsultado = this.ruc;

                    this.rucInfo.status = 'validating';
                    this.rucInfo.badgeText = 'Consultando...';

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const res = await fetch('{{ route('clientes.validar-ruc') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ ruc: rucConsultado })
                        });

                        const data = await res.json();
                        if (seq !== this.rucConsultaSeq || this.ruc !== rucConsultado) {
                            return;
                        }

                        if (data.duplicate) {
                            this.rucInfo = {
                                status: 'duplicate',
                                message: data.message,
                                badgeText: '⚠️ Ya Registrado',
                                tipo: ''
                            };
                        } else if (data.valid) {
                            this.rucInfo.status = 'valid';
                            this.rucInfo.badgeText = '✓ RUC Válido';
                            this.rucInfo.message = data.message;

                            const reemplazar = this.rucSincronizado !== '' && this.rucSincronizado !== rucConsultado && Boolean(data.razon_social);
                            this.aplicarDatosSunat(data, reemplazar);
                            if (data.razon_social) {
                                this.rucSincronizado = rucConsultado;
                            }
                        } else if (data.message) {
                            this.rucInfo.status = 'invalid';
                            this.rucInfo.badgeText = 'RUC Inválido';
                            this.rucInfo.message = data.message;
                        }
                    } catch (_) {
                        if (seq !== this.rucConsultaSeq || this.ruc !== rucConsultado) {
                            return;
                        }
                        this.rucInfo.status = 'valid';
                        this.rucInfo.badgeText = '✓ RUC Válido';
                    }
                },

                aplicarDatosSunat(data, reemplazar) {
                    if (data.razon_social && (reemplazar || !this.razonSocial || this.razonSocial.trim() === '')) {
                        this.razonSocial = data.razon_social;
                        this.razonSocialAuto = true;
                    }
                    if (data.estado_sunat) {
                        this.estadoContribuyente = data.estado_sunat;
                    } else if (reemplazar) {
                        this.estadoContribuyente = '';
                    }
                    if (data.condicion_sunat) {
                        this.condicionDomicilio = data.condicion_sunat;
                    } else if (reemplazar) {
                        this.condicionDomicilio = '';
                    }
                    if (data.tipo_persona) {
                        this.tipoContribuyente = data.tipo_persona;
                    } else if (reemplazar) {
                        this.tipoContribuyente = '';
                    }
                    if (data.direccion_sunat && (reemplazar || !this.direccionFiscal || this.direccionFiscal.trim() === '')) {
                        this.direccionFiscal = data.direccion_sunat;
                        this.direccionFiscalAuto = true;
                    } else if (reemplazar) {
                        this.direccionFiscal = '';
                        this.direccionFiscalAuto = false;
                    }
                    if (data.ubigeo) {
                        this.ubigeo = data.ubigeo;
                    } else if (reemplazar) {
                        this.ubigeo = '';
                    }
                    if (data.distrito) {
                        this.distrito = data.distrito;
                    } else if (reemplazar) {
                        this.distrito = '';
                    }
                    if (data.provincia) {
                        this.provincia = data.provincia;
                    } else if (reemplazar) {
                        this.provincia = '';
                    }
                    if (data.departamento) {
                        this.departamento = data.departamento;
                    } else if (reemplazar) {
                        this.departamento = '';
                    }
                },

                // ─── LÓGICA DE VALIDACIÓN CLAVE SOL (INLINE Y MODAL) ──────────
                onSolChange() {
                    this.usuarioSol = this.usuarioSol.toUpperCase();
                    this.solValidado = false;
                    this.solStatus = 'untested';
                    this.solMessage = '';
                },

                async probarConexionSol() {
                    if (!this.ruc || !this.usuarioSol || !this.claveSol) return;

                    if (!this.validarRucChecksum(this.ruc)) {
                        this.solStatus = 'invalid';
                        this.solMessage = 'Primero debe ingresar un RUC válido según el algoritmo de SUNAT.';
                        return;
                    }

                    this.solStatus = 'testing';
                    this.solMessage = 'Comprobando credenciales con SUNAT en segundo plano...';

                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const res = await fetch('{{ route('clientes.validar-sol') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                ruc: this.ruc,
                                usuario_sol: this.usuarioSol,
                                clave_sol: this.claveSol
                            })
                        });

                        const data = await res.json();

                        if (data.valid) {
                            this.solStatus = 'valid';
                            this.solValidado = true;
                            this.solMessage = data.message || 'Credenciales Clave SOL validadas con éxito ante SUNAT.';
                            if (data.razon_social && (!this.razonSocial || this.razonSocial.trim().length === 0)) {
                                this.razonSocial = data.razon_social;
                                this.razonSocialAuto = true;
                            }
                        } else {
                            this.solStatus = 'invalid';
                            this.solValidado = false;
                            this.solMessage = data.message || 'No se pudo validar el acceso con SUNAT.';
                        }
                    } catch (err) {
                        this.solStatus = 'error';
                        this.solValidado = false;
                        this.solMessage = 'Error temporal de conexión con el servidor: ' + err.message;
                    }
                },

                // ─── SUBMIT DEL FORMULARIO CON MODAL DE COMPROBACIÓN ──────────
                async handleSubmit() {
                    // Validar campos requeridos
                    if (!this.ruc || !this.razonSocial || !this.regimenTributario) {
                        this.$refs.form.reportValidity();
                        return;
                    }

                    if (this.rucInfo.status === 'invalid') {
                        alert('El RUC ingresado no es válido. Por favor verifique el número.');
                        document.getElementById('ruc')?.focus();
                        return;
                    }

                    if (this.rucInfo.status === 'duplicate') {
                        if (!confirm('Este RUC ya se encuentra registrado en el sistema. ¿Desea continuar de todos modos?')) {
                            return;
                        }
                    }

                    // Si no tiene Usuario o Clave SOL, registrar directamente
                    if (!this.usuarioSol.trim() || !this.claveSol.trim()) {
                        this.$refs.form.submit();
                        return;
                    }

                    // Si ya fue validado previamente con éxito, enviar directamente
                    if (this.solValidado) {
                        this.$refs.form.submit();
                        return;
                    }

                    // Lanzar modal de comprobación
                    this.modalOpen = true;
                    this.modalStatus = 'validating';
                    this.modalStep = 1;
                    this.modalError = '';

                    // Paso 1: Verificación de RUC Módulo 11 (250ms)
                    await new Promise(r => setTimeout(r, 250));
                    this.modalStep = 2;

                    // Paso 2: Conexión con SUNAT en segundo plano (350ms)
                    await new Promise(r => setTimeout(r, 350));
                    this.modalStep = 3;

                    // Paso 3: Llamar al backend headless
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                        const res = await fetch('{{ route('clientes.validar-sol') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': token,
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({
                                ruc: this.ruc,
                                usuario_sol: this.usuarioSol,
                                clave_sol: this.claveSol
                            })
                        });

                        const data = await res.json();

                        if (data.valid) {
                            this.modalStep = 4;
                            this.modalStatus = 'success';
                            this.solValidado = true;

                            if (data.razon_social && (!this.razonSocial || this.razonSocial.trim().length === 0)) {
                                this.razonSocial = data.razon_social;
                                this.razonSocialAuto = true;
                            }

                            // Pausa para celebrar el éxito y enviar formulario
                            await new Promise(r => setTimeout(r, 600));
                            this.$refs.form.submit();
                        } else {
                            this.modalStatus = 'error';
                            this.modalError = data.message || 'El Usuario o Clave SOL no corresponden a este RUC en SUNAT.';
                        }
                    } catch (err) {
                        this.modalStatus = 'error';
                        this.modalError = 'No se pudo conectar con el portal de SUNAT: ' + err.message;
                    }
                },

                cerrarModalYCorregir() {
                    this.modalOpen = false;
                    setTimeout(() => {
                        document.getElementById('clave_sol')?.focus();
                    }, 100);
                },

                guardarSinValidar() {
                    this.modalOpen = false;
                    this.solValidado = true; // Forzar bypass
                    setTimeout(() => {
                        this.$refs.form.submit();
                    }, 50);
                }
            };
        }
    </script>
@endsection
