@extends('layouts.app')

@section('title', 'Clientes')
@section('header', 'Clientes')

@section('content')
<div x-data="hudAccesoRapido()">
    {{-- Filtros por Estado en el Estudio Contable --}}
    <div class="mb-5 flex flex-wrap items-center gap-2 border-b border-gray-200 pb-3">
        <a href="{{ route('clientes.index', array_merge(request()->except('estado', 'page'), ['estado' => 'todos'])) }}"
           class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $estadoFiltro === 'todos' ? 'bg-brand-600 text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
            <span>Todos</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $estadoFiltro === 'todos' ? 'bg-brand-700 text-white' : 'bg-gray-100 text-gray-600' }}">
                {{ $conteoTodos }}
            </span>
        </a>
        <a href="{{ route('clientes.index', array_merge(request()->except('estado', 'page'), ['estado' => 'activo'])) }}"
           class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $estadoFiltro === 'activo' ? 'bg-emerald-600 text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
            <span class="h-2 w-2 rounded-full {{ $estadoFiltro === 'activo' ? 'bg-white' : 'bg-emerald-500' }}"></span>
            <span>Activos en Estudio</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $estadoFiltro === 'activo' ? 'bg-emerald-700 text-white' : 'bg-emerald-50 text-emerald-700' }}">
                {{ $conteoActivos }}
            </span>
        </a>
        <a href="{{ route('clientes.index', array_merge(request()->except('estado', 'page'), ['estado' => 'suspendido'])) }}"
           class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $estadoFiltro === 'suspendido' ? 'bg-amber-600 text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
            <span class="h-2 w-2 rounded-full {{ $estadoFiltro === 'suspendido' ? 'bg-white' : 'bg-amber-500' }}"></span>
            <span>Suspendidos</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $estadoFiltro === 'suspendido' ? 'bg-amber-700 text-white' : 'bg-amber-50 text-amber-700' }}">
                {{ $conteoSuspendidos }}
            </span>
        </a>
        <a href="{{ route('clientes.index', array_merge(request()->except('estado', 'page'), ['estado' => 'retirado'])) }}"
           class="inline-flex items-center gap-2 rounded-lg px-3 py-1.5 text-xs font-semibold transition {{ $estadoFiltro === 'retirado' ? 'bg-slate-700 text-white shadow-xs' : 'bg-white border border-gray-200 text-gray-700 hover:bg-gray-50' }}">
            <span class="h-2 w-2 rounded-full {{ $estadoFiltro === 'retirado' ? 'bg-white' : 'bg-slate-400' }}"></span>
            <span>Retirados / Baja</span>
            <span class="rounded-full px-1.5 py-0.2 text-[10px] {{ $estadoFiltro === 'retirado' ? 'bg-slate-800 text-white' : 'bg-slate-100 text-slate-700' }}">
                {{ $conteoRetirados }}
            </span>
        </a>

        @if (auth()->user()?->isAdmin() && ($totalEnPapelera ?? 0) > 0)
            <div class="h-4 w-px bg-gray-300 mx-1"></div>
            <a href="{{ route('clientes.papelera') }}"
               class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 transition hover:bg-rose-100 shadow-2xs">
                <span>🗑️ Papelera</span>
                <span class="rounded-full bg-rose-600 px-1.5 py-0.2 text-[10px] font-bold text-white">
                    {{ $totalEnPapelera }}
                </span>
            </a>
        @endif
    </div>

    {{-- Barra de acciones --}}
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        {{-- Búsqueda en Tiempo Real --}}
        <form method="GET" action="{{ route('clientes.index') }}" @submit.prevent="ejecutarBusquedaInmediata()" class="relative w-full sm:max-w-md">
            @if ($estadoFiltro !== 'todos')
                <input type="hidden" name="estado" value="{{ $estadoFiltro }}">
            @endif
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5">
                <svg x-show="!buscando" class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
                <svg x-show="buscando" x-cloak class="h-4 w-4 animate-spin text-brand-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
            </div>
            <input
                type="text"
                name="buscar"
                x-model="buscar"
                @input="filtrarEnTiempoReal()"
                @keydown.escape="limpiarBusqueda()"
                placeholder="Buscar en tiempo real por RUC o Razón Social…"
                class="block w-full rounded-lg border border-gray-300 bg-white py-2.5 pl-10 pr-10 text-sm text-gray-700 placeholder-gray-400 shadow-xs transition focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20"
                autocomplete="off"
            >
            {{-- Botón Limpiar (✕) --}}
            <div x-show="buscar.length > 0" x-cloak class="absolute inset-y-0 right-0 flex items-center pr-2.5">
                <button
                    type="button"
                    @click="limpiarBusqueda()"
                    class="rounded-full p-1 text-gray-400 hover:bg-gray-100 hover:text-gray-600 focus:outline-none transition"
                    title="Limpiar búsqueda (Esc)"
                >
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </form>

        <div class="flex w-full flex-wrap items-center gap-2 sm:w-auto">
            <button type="button"
                    onclick="window.dispatchEvent(new CustomEvent('abrir-carga-masiva-clientes'))"
                    title="Cargar varios clientes desde una plantilla. El alta de uno en uno no cambia."
                    class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-5 py-2.5 text-sm font-medium text-gray-700 shadow-xs transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-brand-500/30">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                </svg>
                Carga masiva
            </button>
            <a href="{{ route('clientes.create') }}"
               class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-5 py-2.5 text-sm font-medium text-white shadow-xs transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                </svg>
                Nuevo Cliente
            </a>
        </div>
    </div>

    {{-- Tabla de clientes --}}
    <div id="tabla-clientes-wrapper"
         @click="if ($event.target.closest('a') && $event.target.closest('.pagination, nav[role=\'navigation\']')) {
             const link = $event.target.closest('a');
             if (link && link.href) {
                 $event.preventDefault();
                 ejecutarConsultaServidor(link.href);
             }
         }"
         class="overflow-x-auto rounded-xl border border-gray-200 bg-white shadow-xs">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3.5 text-center text-xs font-semibold uppercase tracking-wider text-gray-500 w-14">N°</th>
                    <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">RUC</th>
                    <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Razón Social</th>
                    <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Régimen</th>
                    <th class="px-6 py-3.5 text-left text-xs font-semibold uppercase tracking-wider text-gray-500">Estado Estudio</th>
                    <th class="px-6 py-3.5 text-right text-xs font-semibold uppercase tracking-wider text-gray-500">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($clientes as $cliente)
                    <tr class="transition hover:bg-gray-50/80 cliente-row"
                        data-cliente-ruc="{{ $cliente->ruc }}"
                        data-cliente-razon="{{ mb_strtolower($cliente->razon_social) }}"
                        x-show="coincide('{{ $cliente->ruc }}', '{{ addslashes(mb_strtolower($cliente->razon_social)) }}')">
                        <td class="whitespace-nowrap px-4 py-4 text-center">
                            <span class="inline-flex h-6 min-w-[26px] px-1.5 items-center justify-center rounded-md bg-gray-100 border border-gray-300 text-xs font-mono font-black text-gray-700 shadow-xs"
                                  title="Cliente N° {{ $cliente->numero }}">
                                {{ $cliente->numero }}
                            </span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm font-mono text-gray-700">{{ $cliente->ruc }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ $cliente->razon_social }}</td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <x-ui.badge-regimen :regimen="$cliente->regimen_tributario" />
                        </td>
                        <td class="whitespace-nowrap px-6 py-4">
                            @if ($cliente->isActivo())
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-1 text-xs font-semibold text-emerald-700 shadow-xs">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                    Activo
                                </span>
                            @elseif ($cliente->isSuspendido())
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-1 text-xs font-semibold text-amber-700 shadow-xs"
                                      title="{{ $cliente->notas_estado ? 'Suspensión: '.$cliente->notas_estado : 'Suspendido temporalmente' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                    Suspendido
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-slate-100 border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 shadow-xs"
                                      title="{{ $cliente->motivo_baja ? 'Motivo: '.$cliente->motivo_baja : 'Retirado del estudio' }}">
                                    <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                                    Retirado @if($cliente->mes_baja) <span class="font-normal text-[11px] text-slate-500">({{ \Carbon\Carbon::createFromFormat('Y-m', $cliente->mes_baja)->translatedFormat('M Y') }})</span> @endif
                                </span>
                            @endif
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            <div class="flex items-center justify-end gap-2">
                                {{-- Botón 1: Expediente --}}
                                <a href="{{ route('clientes.show', $cliente) }}"
                                   title="Ver Expediente"
                                   class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-xs transition hover:bg-gray-50 hover:text-gray-900 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    <span>Ver</span>
                                </a>

                                {{-- Botón 2: AFPnet --}}
                                @if (!empty($cliente->credencial?->usuario_afp))
                                    <a href="https://www.afpnet.com.pe/"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       title="AFPnet - Portal Oficial (Usuario: {{ $cliente->credencial->usuario_afp }})"
                                       class="inline-flex items-center gap-1.5 rounded-lg bg-sky-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-sky-700 hover:shadow active:scale-95 focus:outline-none focus:ring-2 focus:ring-sky-500/40">
                                        <span class="text-xs">💼</span>
                                        <span>AFP</span>
                                    </a>
                                @else
                                    <button type="button"
                                            disabled
                                            title="Sin credenciales AFPnet registradas"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-400 opacity-40 cursor-not-allowed select-none">
                                        <span class="text-xs grayscale opacity-70">💼</span>
                                        <span>AFP</span>
                                    </button>
                                @endif

                                {{-- Botón 3: Detracciones Banco de la Nación --}}
                                @if (!empty($cliente->credencial?->cuenta_detracciones))
                                    <a href="https://bancaporinternet.bn.com.pe/BNWeb/Inicio"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       title="Banco de la Nación - Detracciones (Cta: {{ $cliente->credencial->cuenta_detracciones }})"
                                       class="inline-flex items-center gap-1.5 rounded-lg bg-red-700 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-red-800 hover:shadow active:scale-95 focus:outline-none focus:ring-2 focus:ring-red-600/40">
                                        <span class="text-xs">🏦</span>
                                        <span>DETRAC.</span>
                                    </a>
                                @else
                                    <button type="button"
                                            disabled
                                            title="Sin cuenta de detracciones registrada"
                                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-gray-100 px-2.5 py-1.5 text-xs font-medium text-gray-400 opacity-40 cursor-not-allowed select-none">
                                        <span class="text-xs grayscale opacity-70">🏦</span>
                                        <span>DETRAC.</span>
                                    </button>
                                @endif

                                {{-- Botón 4: SUNAT (1 Clic) --}}
                                <button type="button"
                                   @click.stop="abrirMenuSunat($event, '{{ route('clientes.sunat', $cliente) }}', '{{ addslashes($cliente->razon_social) }}', '{{ $cliente->ruc }}')"
                                   title="Accesos SUNAT con Clave SOL"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-emerald-700 hover:shadow active:scale-95 focus:outline-none focus:ring-2 focus:ring-emerald-500/40">
                                    <span class="text-xs">🚀</span>
                                    <span>SUNAT</span>
                                </button>

                                {{-- Botón 5: SUNAFIL (1 Clic) --}}
                                <a href="{{ route('clientes.sunafil', $cliente) }}"
                                   @click.prevent="lanzarAcceso('{{ route('clientes.sunafil', $cliente) }}', 'SUNAFIL', '{{ addslashes($cliente->razon_social) }}', '{{ $cliente->ruc }}')"
                                   title="Ingreso Automático a Casilla Electrónica SUNAFIL (1 Clic)"
                                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-2.5 py-1.5 text-xs font-semibold text-white shadow-xs transition hover:bg-blue-700 hover:shadow active:scale-95 focus:outline-none focus:ring-2 focus:ring-blue-500/40">
                                    <span class="text-xs">👷</span>
                                    <span>SUNAFIL</span>
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center">
                            <div class="mx-auto max-w-lg rounded-2xl border border-indigo-100 bg-gradient-to-b from-indigo-50/60 to-white p-8 shadow-xs">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-600 text-2xl text-white shadow-md shadow-indigo-200">
                                    🏢
                                </div>
                                <h3 class="mt-4 text-base font-bold text-gray-900">¡Bienvenido a tu CRM Contable!</h3>
                                <p class="mt-2 text-xs text-gray-600 leading-relaxed">
                                    Tu sistema está listo para operar en estado <span class="inline-flex items-center rounded-md bg-emerald-50 px-2 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">0 Kilómetros</span>. Comienza personalizando la información de tu Estudio y registrando tu primer cliente.
                                </p>
                                <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                                    @if(auth()->user()?->isAdmin())
                                        <a href="{{ route('ajustes.index') }}" class="inline-flex items-center gap-1.5 rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50 hover:border-gray-400">
                                            <span>⚙️ Configurar Estudio</span>
                                        </a>
                                    @endif
                                    <a href="{{ route('clientes.create') }}" class="inline-flex items-center gap-1.5 rounded-xl bg-brand-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-brand-700 active:scale-95">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                        <span>Registrar Primer Cliente</span>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                @endforelse

                {{-- Fila de Estado Vacío para Búsqueda en Tiempo Real --}}
                <tr x-show="buscar.trim() !== '' && !hayCoincidenciasVisibles" x-cloak id="fila-sin-coincidencias">
                    <td colspan="6" class="px-6 py-12 text-center">
                        <div class="mx-auto flex max-w-sm flex-col items-center">
                            <div class="flex h-12 w-12 items-center justify-center rounded-full bg-amber-50 text-amber-500 mb-3 border border-amber-200">
                                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-gray-900">Sin coincidencias para la búsqueda</p>
                            <p class="mt-1 text-xs text-gray-500">Ningún cliente coincide con "<span x-text="buscar" class="font-bold text-gray-700"></span>"</p>
                            <button type="button" @click="limpiarBusqueda()" class="mt-3 inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 transition">
                                <span>Limpiar filtro</span>
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>

        {{-- Paginación --}}
        @if ($clientes->hasPages())
            <div class="border-t border-gray-200 bg-gray-50 px-6 py-3">
                {{ $clientes->links() }}
            </div>
        @endif
    </div>

    {{-- Resumen --}}
    <div id="resumen-clientes">
        <p class="mt-4 text-xs text-gray-400">
            Mostrando {{ $clientes->firstItem() ?? 0 }} – {{ $clientes->lastItem() ?? 0 }} de {{ $clientes->total() }} clientes
        </p>
    </div>

    @include('clientes.partials.menu-accesos-sunat')

    {{-- ════════════════════ MODAL HUD PREMIUM DE ACCESO RÁPIDO ════════════════════ --}}
    <div x-show="abierto"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 scale-95"
         x-transition:enter-end="opacity-100 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 scale-100"
         x-transition:leave-end="opacity-0 scale-95"
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 backdrop-blur-xs p-4">
        
        <div class="relative w-full max-w-md overflow-hidden rounded-2xl bg-white p-6 shadow-2xl border border-slate-100"
             @click.away="if (completado || error) abierto = false">
            
            {{-- Encabezado con Icono y Portal --}}
            <div class="flex items-center gap-4 mb-4">
                <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-2xl shadow-inner"
                     :class="portal === 'SUNAT' ? 'bg-emerald-50 text-emerald-600 border border-emerald-200' : 'bg-blue-50 text-blue-600 border border-blue-200'">
                    <span x-text="icono"></span>
                </div>
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold uppercase tracking-wider px-2 py-0.5 rounded-full"
                              :class="portal === 'SUNAT' ? 'bg-emerald-100 text-emerald-800' : 'bg-blue-100 text-blue-800'"
                              x-text="portal === 'SUNAT' ? 'Portal SUNAT SOL' : 'Casilla Electrónica SUNAFIL'">
                        </span>
                    </div>
                    <h3 class="text-base font-bold text-slate-900 truncate mt-1" x-text="clienteNombre"></h3>
                    <p class="text-xs font-mono text-slate-500">RUC: <span x-text="clienteRuc" class="font-semibold text-slate-700"></span></p>
                </div>
                <template x-if="error">
                    <button type="button" @click="abierto = false" class="text-slate-400 hover:text-slate-600 text-lg font-bold leading-none">&times;</button>
                </template>
            </div>

            {{-- Estado de Error si ocurrió alguno --}}
            <template x-if="error">
                <div class="mb-4 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-xs text-rose-700 flex items-start gap-2.5">
                    <span class="text-base">⚠️</span>
                    <div class="flex-1">
                        <p class="font-bold">No se pudo iniciar el acceso automático</p>
                        <p class="mt-0.5" x-text="error"></p>
                    </div>
                </div>
            </template>

            {{-- Barra de Progreso y Etapas --}}
            <template x-if="!error">
                <div class="space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-medium text-slate-600 flex items-center gap-2">
                            <template x-if="!completado">
                                <svg class="animate-spin h-3.5 w-3.5" :class="portal === 'SUNAT' ? 'text-emerald-600' : 'text-blue-600'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                            </template>
                            <template x-if="completado">
                                <span class="text-emerald-600 font-bold">✓</span>
                            </template>
                            <span x-text="faseTexto"></span>
                        </span>
                        <span class="font-mono font-bold text-slate-700" x-text="progreso + '%'"></span>
                    </div>

                    {{-- Pista de progreso animada --}}
                    <div class="h-2.5 w-full overflow-hidden rounded-full bg-slate-100 shadow-inner">
                        <div class="h-full rounded-full transition-all duration-300 ease-out"
                             :class="portal === 'SUNAT' ? 'bg-gradient-to-r from-emerald-500 to-teal-400' : 'bg-gradient-to-r from-blue-600 to-indigo-500'"
                             :style="'width: ' + progreso + '%'">
                        </div>
                    </div>

                    <div class="flex items-center justify-between pt-1 text-[11px] text-slate-400">
                        <span x-text="(nombreNavegador || 'Navegador') + ' · ventana normal'"></span>
                        <span x-text="completado ? 'Portal listo en pantalla' : 'Conexión cifrada'"></span>
                    </div>
                </div>
            </template>

            {{-- Botón de acción en caso de error --}}
            <template x-if="error">
                <div class="mt-4 flex justify-end">
                    <button type="button"
                            @click="abierto = false"
                            class="rounded-lg bg-slate-100 hover:bg-slate-200 px-4 py-2 text-xs font-semibold text-slate-700 transition">
                        Entendido
                    </button>
                </div>
            </template>
        </div>
    </div>
</div>

<script>
    function hudAccesoRapido() {
        return {
            // ═══════════════ BÚSQUEDA EN TIEMPO REAL ═══════════════
            buscar: '{{ addslashes(request('buscar', '')) }}',
            buscando: false,
            debounceTimer: null,
            hayCoincidenciasVisibles: true,
            clientesLocales: [
                @foreach ($clientes as $c)
                { ruc: '{{ $c->ruc }}', razon: '{{ addslashes(mb_strtolower($c->razon_social)) }}' },
                @endforeach
            ],

            init() {
                this.recalcularCoincidencias();
            },

            coincide(ruc, razon) {
                if (!this.buscar || !this.buscar.trim()) return true;
                const q = this.buscar.trim().toLowerCase();
                const r = (ruc || '').toLowerCase();
                const s = (razon || '').toLowerCase();
                return r.includes(q) || s.includes(q);
            },

            recalcularCoincidencias() {
                if (!this.buscar || !this.buscar.trim()) {
                    this.hayCoincidenciasVisibles = true;
                    return;
                }
                const q = this.buscar.trim().toLowerCase();
                this.hayCoincidenciasVisibles = this.clientesLocales.some(c => 
                    (c.ruc && c.ruc.toLowerCase().includes(q)) || (c.razon && c.razon.toLowerCase().includes(q))
                );
            },

            filtrarEnTiempoReal() {
                // 1. Filtrado instantáneo en memoria / DOM (0 milisegundos de latencia)
                this.recalcularCoincidencias();

                // 2. Consulta en segundo plano con debounce para buscar en toda la base de datos sin recargar
                if (this.debounceTimer) clearTimeout(this.debounceTimer);
                this.buscando = true;
                this.debounceTimer = setTimeout(() => {
                    this.ejecutarConsultaServidor();
                }, 280);
            },

            ejecutarBusquedaInmediata() {
                if (this.debounceTimer) clearTimeout(this.debounceTimer);
                this.ejecutarConsultaServidor();
            },

            limpiarBusqueda() {
                this.buscar = '';
                this.recalcularCoincidencias();
                if (this.debounceTimer) clearTimeout(this.debounceTimer);
                this.ejecutarConsultaServidor();
            },

            async ejecutarConsultaServidor(urlParam = null) {
                this.buscando = true;
                try {
                    let fetchUrl;
                    if (urlParam) {
                        fetchUrl = new URL(urlParam, window.location.origin);
                    } else {
                        fetchUrl = new URL('{{ route('clientes.index') }}', window.location.origin);
                        if (this.buscar.trim()) {
                            fetchUrl.searchParams.set('buscar', this.buscar.trim());
                        }
                    }

                    const res = await fetch(fetchUrl.toString(), {
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    if (res.ok) {
                        const html = await res.text();
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');

                        const nuevoWrapper = doc.getElementById('tabla-clientes-wrapper');
                        const actualWrapper = document.getElementById('tabla-clientes-wrapper');
                        if (nuevoWrapper && actualWrapper) {
                            actualWrapper.innerHTML = nuevoWrapper.innerHTML;
                        }

                        const nuevoResumen = doc.getElementById('resumen-clientes');
                        const actualResumen = document.getElementById('resumen-clientes');
                        if (nuevoResumen && actualResumen) {
                            actualResumen.innerHTML = nuevoResumen.innerHTML;
                        }

                        // Actualizar la URL en la barra del navegador sin refrescar
                        window.history.replaceState({}, '', fetchUrl.toString());

                        // Actualizar la lista local de clientes para reactividad instantánea
                        if (actualWrapper) {
                            const filas = actualWrapper.querySelectorAll('.cliente-row');
                            this.clientesLocales = Array.from(filas).map(f => ({
                                ruc: f.getAttribute('data-cliente-ruc') || '',
                                razon: (f.getAttribute('data-cliente-razon') || '').toLowerCase()
                            }));

                            // Re-inicializar directivas interactivas de Alpine en el nuevo DOM
                            if (window.Alpine) {
                                window.Alpine.initTree(actualWrapper);
                            }
                        }

                        this.recalcularCoincidencias();
                    }
                } catch (err) {
                    console.error('Error al consultar clientes:', err);
                } finally {
                    this.buscando = false;
                }
            },

            // ═══════════════ MODAL HUD ACCESO RÁPIDO ═══════════════
            abierto: false,
            portal: 'SUNAT',
            clienteNombre: '',
            clienteRuc: '',
            progreso: 0,
            faseTexto: '',
            icono: '🚀',
            nombreNavegador: 'Google Chrome',
            completado: false,
            error: null,
            animTimer: null,
            menuSunat: { abierto: false, top: 0, left: 0, url: '', nombre: '', ruc: '' },
            _ignorarCierreSunat: false,

            abrirMenuSunat(event, url, nombre, ruc) {
                document.querySelectorAll('[data-ancla-sunat]').forEach((nodo) => nodo.removeAttribute('data-ancla-sunat'));
                event.currentTarget.setAttribute('data-ancla-sunat', '1');
                this.menuSunat.url = url;
                this.menuSunat.nombre = nombre;
                this.menuSunat.ruc = ruc;
                window.posicionarMenuAccesosSunat(this.menuSunat);
                this.menuSunat.abierto = true;
                if (!this._oyendoScrollSunat) {
                    this._oyendoScrollSunat = true;
                    const seguir = () => {
                        if (!this.menuSunat.abierto) {
                            return;
                        }
                        window.posicionarMenuAccesosSunat(this.menuSunat);
                    };
                    window.addEventListener('scroll', seguir, true);
                    window.addEventListener('resize', seguir);
                }
            },

            elegirAccesoSunat(destino) {
                const url = this.menuSunat.url + '?destino=' + encodeURIComponent(destino);
                const nombre = this.menuSunat.nombre;
                const ruc = this.menuSunat.ruc;
                this.menuSunat.abierto = false;
                this.lanzarAcceso(url, 'SUNAT', nombre, ruc);
            },

            async lanzarAcceso(url, portal, nombre, ruc) {
                if (this.animTimer) clearInterval(this.animTimer);
                this.portal = portal;
                this.clienteNombre = nombre;
                this.clienteRuc = ruc;
                this.icono = portal === 'SUNAT' ? '🚀' : '👷';
                this.progreso = 15;
                this.faseTexto = portal === 'SUNAT'
                    ? 'Ingresando a SUNAT...'
                    : 'Ingresando a SUNAFIL...';
                this.completado = false;
                this.error = null;
                this.abierto = true;

                // Animación fluida sincronizada mientras el navegador inicia y se autentica
                let step = 0;
                this.animTimer = setInterval(() => {
                    step++;
                    if (step === 1 && this.progreso < 40) {
                        this.progreso = 38;
                        this.faseTexto = 'Preparando el navegador...';
                    } else if (step === 2 && this.progreso < 70) {
                        this.progreso = 68;
                        this.faseTexto = portal === 'SUNAT'
                            ? 'Ingresando a SUNAT...'
                            : 'Ingresando a SUNAFIL...';
                    } else if (step >= 3 && this.progreso < 92) {
                        this.progreso = Math.min(92, this.progreso + 4);
                        this.faseTexto = 'La ventana se abre cuando la sesión ya está lista...';
                    }
                }, 550);

                try {
                    const res = await fetch(url, {
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        }
                    });

                    clearInterval(this.animTimer);
                    const data = await res.json();

                    if (res.ok && data.success) {
                        const bName = data.browser || 'Google Chrome';
                        this.nombreNavegador = bName;
                        this.progreso = 100;
                        this.completado = true;
                        this.faseTexto = '¡Sesión lista!';
                        // Se cierra de inmediato para desaparecer por completo antes de que la ventana tome el foco
                        this.abierto = false;
                    } else {
                        this.error = data.message || 'No se pudo iniciar la sesión automática.';
                        this.progreso = 0;
                    }
                } catch (err) {
                    clearInterval(this.animTimer);
                    this.error = 'Ocurrió un error al contactar al servidor.';
                    this.progreso = 0;
                }
            }
        };
    }
</script>
    @include('clientes.partials.carga-masiva')
@endsection
