@extends('layouts.app')

@section('title', 'Papelera de Clientes')
@section('header', 'Papelera de Reciclaje — Clientes Archivados')

@section('content')
<div class="space-y-6" x-data="papeleraSeleccion()">

    {{-- Encabezado & Volver --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <h2 class="text-xl font-bold text-gray-900">Papelera de Clientes</h2>
                <span class="inline-flex items-center rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-xs font-bold text-rose-700">
                    {{ $totalEnPapelera }} {{ Str::plural('archivado', $totalEnPapelera) }}
                </span>
            </div>
            <p class="text-sm text-gray-500 mt-0.5">
                Bajas lógicas protegidas. Los comprobantes SIRE, declaraciones PDT y notas históricas permanecen resguardados para auditorías de SUNAT.
            </p>
        </div>
        <a href="{{ route('clientes.index') }}"
           class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            <span>Volver a Clientes Activos</span>
        </a>
    </div>

    {{-- Banner Informativo de Auditoría y Seguridad --}}
    <div class="flex items-start gap-3 rounded-2xl border border-amber-200 bg-amber-50/80 p-4 text-xs text-amber-900 shadow-xs">
        <svg class="h-5 w-5 shrink-0 text-amber-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
        </svg>
        <div>
            <span class="font-bold">Protección de Datos Tributarios (Soft Deletes):</span>
            Los clientes en papelera no consumen recursos en los bots ni aparecen en la cartera de los asistentes. Al restaurarlos, recuperan su número correlativo y su expediente completo sin ninguna pérdida de información.
        </div>
    </div>

    {{-- Buscador en la papelera --}}
    <div class="flex items-center justify-between">
        <form method="GET" action="{{ route('clientes.papelera') }}" class="relative w-full sm:max-w-md">
            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-gray-400">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                </svg>
            </div>
            <input type="text"
                   name="buscar"
                   value="{{ request('buscar') }}"
                   placeholder="Buscar en papelera por RUC o Razón Social..."
                   class="w-full rounded-xl border border-gray-200 bg-white py-2 pl-9 pr-3 text-xs text-gray-800 placeholder-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
        </form>
    </div>

    {{-- Tabla de Clientes en Papelera --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="w-10 px-3 py-4 text-center">
                            <input type="checkbox"
                                   @change="alternarTodos($event.target.checked)"
                                   :checked="todosSeleccionados()"
                                   x-effect="$el.indeterminate = algunosSeleccionados()"
                                   title="Seleccionar o deseleccionar todos los clientes archivados"
                                   class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition cursor-pointer">
                        </th>
                        <th class="px-6 py-4">Cliente / Contribuyente</th>
                        <th class="px-6 py-4">Régimen & RUC</th>
                        <th class="px-6 py-4">Fecha de Baja</th>
                        <th class="px-6 py-4">Expediente Preservado</th>
                        <th class="px-6 py-4 text-right">Acciones de Recuperación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($clientes as $cliente)
                        <tr class="hover:bg-gray-50/50 transition"
                            :class="estaSeleccionado('{{ $cliente->id }}') ? 'bg-rose-50/30' : ''">
                            <td class="w-10 px-3 py-4 text-center">
                                <input type="checkbox"
                                       value="{{ $cliente->id }}"
                                       data-papelera-id="{{ $cliente->id }}"
                                       x-model="seleccionados"
                                       class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500 transition cursor-pointer">
                            </td>
                            {{-- Razón Social y Número --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-200 font-bold text-slate-600 text-xs shadow-xs">
                                        N° {{ $cliente->numero }}
                                    </div>
                                    <div>
                                        <span class="block font-bold text-gray-900">{{ $cliente->razon_social }}</span>
                                        <span class="block text-xs text-gray-400">{{ $cliente->correo ?? 'Sin correo registrado' }}</span>
                                    </div>
                                </div>
                            </td>

                            {{-- RUC y Régimen --}}
                            <td class="px-6 py-4">
                                <span class="block font-mono text-xs font-bold text-gray-800">{{ $cliente->ruc }}</span>
                                <x-ui.badge-regimen :regimen="$cliente->regimen_tributario" class="mt-0.5" />
                            </td>

                            {{-- Fecha de Eliminación --}}
                            <td class="px-6 py-4">
                                <span class="block text-xs font-semibold text-rose-700">
                                    {{ $cliente->deleted_at ? $cliente->deleted_at->format('d/m/Y H:i') : '-' }}
                                </span>
                                <span class="block text-[10px] text-gray-400">
                                    {{ $cliente->deleted_at ? $cliente->deleted_at->diffForHumans() : '' }}
                                </span>
                            </td>

                            {{-- Registros Preservados --}}
                            <td class="px-6 py-4">
                                <div class="flex flex-col gap-0.5 text-xs text-gray-600">
                                    <span class="inline-flex items-center gap-1.5 font-medium">
                                        📄 {{ $cliente->comprobantes_count }} comprobantes SIRE
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-gray-500 text-[11px]">
                                        📊 {{ $cliente->operaciones_count }} declaraciones PDT
                                    </span>
                                    <span class="inline-flex items-center gap-1.5 text-gray-400 text-[10px]">
                                        🔔 {{ $cliente->alertas_count }} alertas de buzón
                                    </span>
                                </div>
                            </td>

                            {{-- Acciones: Restaurar / Eliminar Definitivamente --}}
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    {{-- Botón Restaurar --}}
                                    <form action="{{ route('clientes.restaurar', $cliente->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('¿Desea restaurar este cliente a la cartera activa del estudio?')">
                                        @csrf
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-2xs hover:bg-emerald-100 transition active:scale-95">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                                            </svg>
                                            <span>Restaurar</span>
                                        </button>
                                    </form>

                                    {{-- Botón Eliminar Definitivamente --}}
                                    <form action="{{ route('clientes.forzar-eliminacion', $cliente->id) }}" method="POST" class="inline"
                                          onsubmit="return confirm('🚨 ¡ADVERTENCIA CRÍTICA!\n\nEsta acción purgará FÍSICAMENTE de la base de datos:\n• Todos los comprobantes electrónicos\n• Declaraciones y pagos PDT\n• Credenciales SOL y bancarias\n\nEsta acción NO se puede deshacer.\n\n¿Eliminar definitivamente?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="inline-flex items-center gap-1 rounded-lg border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-semibold text-rose-700 shadow-2xs hover:bg-rose-100 hover:text-rose-900 transition active:scale-95">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                            <span>Purgar</span>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-12 text-center text-gray-400">
                                <div class="flex flex-col items-center justify-center gap-2">
                                    <span class="text-3xl">🗑️</span>
                                    <p class="font-medium text-sm text-gray-500">La papelera de reciclaje está vacía</p>
                                    <p class="text-xs text-gray-400">No hay clientes archivados por baja lógica.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($clientes->hasPages())
            <div class="border-t border-gray-200 px-6 py-4">
                {{ $clientes->links() }}
            </div>
        @endif
    </div>

    {{-- ═══════════════ BARRA FLOTANTE DE ACCIONES MASIVAS EN PAPELERA ═══════════════ --}}
    <div x-show="seleccionados.length > 0"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 scale-95"
         class="fixed bottom-6 left-1/2 -translate-x-1/2 z-40 flex items-center gap-3 rounded-2xl border border-gray-700/60 bg-gray-900/95 px-5 py-3 text-white shadow-2xl backdrop-blur-md">
        <div class="flex items-center gap-2.5 pr-3 border-r border-gray-700">
            <span class="flex h-6 min-w-6 px-1.5 items-center justify-center rounded-full bg-brand-500 text-xs font-bold text-white shadow-xs" x-text="seleccionados.length"></span>
            <span class="text-xs font-semibold text-gray-100" x-text="seleccionados.length === 1 ? 'archivado seleccionado' : 'archivados seleccionados'"></span>
        </div>

        <button type="button"
                @click="deseleccionarTodos()"
                class="rounded-lg px-2.5 py-1 text-xs font-medium text-gray-300 hover:text-white hover:bg-gray-800 transition">
            Deseleccionar
        </button>

        {{-- Formulario Restaurar Masivo --}}
        <form method="POST" action="{{ route('clientes.restaurar-masivo') }}" @submit.prevent="confirmarRestauracionMasiva($event.target)">
            @csrf
            <template x-for="id in seleccionados" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-emerald-700 active:scale-95 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                </svg>
                <span>Restaurar Seleccionados</span>
            </button>
        </form>

        {{-- Formulario Purgar Masivo --}}
        <form method="POST" action="{{ route('clientes.purgar-masivo') }}" @submit.prevent="confirmarPurgaMasiva($event.target)">
            @csrf
            @method('DELETE')
            <template x-for="id in seleccionados" :key="id">
                <input type="hidden" name="ids[]" :value="id">
            </template>
            <button type="submit"
                    class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-rose-700 active:scale-95 transition">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                </svg>
                <span>Purgar Definitivamente</span>
            </button>
        </form>
    </div>

</div>

<script>
    function papeleraSeleccion() {
        return {
            seleccionados: [],

            estaSeleccionado(id) {
                return this.seleccionados.some(s => String(s) === String(id));
            },

            obtenerIdsVisibles() {
                const checks = document.querySelectorAll('input[type="checkbox"][data-papelera-id]');
                return Array.from(checks).map(cb => cb.getAttribute('data-papelera-id')).filter(Boolean);
            },

            todosSeleccionados() {
                const visibles = this.obtenerIdsVisibles();
                if (visibles.length === 0) return false;
                return visibles.every(id => this.estaSeleccionado(id));
            },

            algunosSeleccionados() {
                const visibles = this.obtenerIdsVisibles();
                if (visibles.length === 0) return false;
                const marcados = visibles.filter(id => this.estaSeleccionado(id));
                return marcados.length > 0 && marcados.length < visibles.length;
            },

            alternarTodos(marcar) {
                const visibles = this.obtenerIdsVisibles();
                if (marcar) {
                    const set = new Set([...this.seleccionados.map(String), ...visibles.map(String)]);
                    this.seleccionados = Array.from(set);
                } else {
                    const visiblesSet = new Set(visibles.map(String));
                    this.seleccionados = this.seleccionados.filter(id => !visiblesSet.has(String(id)));
                }
            },

            deseleccionarTodos() {
                this.seleccionados = [];
            },

            confirmarRestauracionMasiva(formElement) {
                if (this.seleccionados.length === 0) return;
                const n = this.seleccionados.length;
                if (confirm(`¿Desea restaurar ${n} ${n === 1 ? 'cliente seleccionado' : 'clientes seleccionados'} a la cartera activa del estudio?`)) {
                    formElement.submit();
                }
            },

            confirmarPurgaMasiva(formElement) {
                if (this.seleccionados.length === 0) return;
                const n = this.seleccionados.length;
                const msg = `🚨 ¡ADVERTENCIA CRÍTICA!\n\nEsta acción purgará FÍSICAMENTE de la base de datos ${n} ${n === 1 ? 'cliente' : 'clientes'}:\n• Comprobantes electrónicos SIRE asociados\n• Declaraciones PDT y operaciones\n• Credenciales SOL y bancarias\n\nEsta acción NO se puede deshacer.\n\n¿Eliminar definitivamente ${n} contribuyentes?`;
                if (confirm(msg)) {
                    formElement.submit();
                }
            }
        };
    }
</script>
@endsection
