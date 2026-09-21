@extends('layouts.app')

@section('title', 'Papelera de Clientes')
@section('header', 'Papelera de Reciclaje — Clientes Archivados')

@section('content')
<div class="space-y-6">

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
                        <th class="px-6 py-4">Cliente / Contribuyente</th>
                        <th class="px-6 py-4">Régimen & RUC</th>
                        <th class="px-6 py-4">Fecha de Baja</th>
                        <th class="px-6 py-4">Expediente Preservado</th>
                        <th class="px-6 py-4 text-right">Acciones de Recuperación</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($clientes as $cliente)
                        <tr class="hover:bg-gray-50/50 transition">
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
                                <span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600 mt-0.5">
                                    {{ $cliente->regimen_tributario?->value }}
                                </span>
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
                            <td colspan="5" class="py-12 text-center text-gray-400">
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

</div>
@endsection
