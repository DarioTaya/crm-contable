@extends('layouts.app')

@section('title', 'Control de Deudas')
@section('header', 'Control de Deudas')

@section('content')
<div class="container mx-auto" x-data="controlDeudas()">
    {{-- ═══════════════════════════════ ENCABEZADO ═══════════════════════════════ --}}
    <div class="mb-5 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-3">
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Control de Deudas</h2>
                <span class="rounded bg-red-100 border border-red-300 px-2 py-0.5 text-xs font-bold text-red-800">
                    SUNAT
                </span>
            </div>
            <p class="mt-0.5 text-xs text-gray-500">
                Seguimiento de deudas tributarias pendientes (IGV Justo, Fraccionamientos, etc.) con sistema de pagos parciales.
            </p>
        </div>
        <a href="{{ route('control.index') }}"
           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-bold text-gray-700 shadow-xs transition hover:bg-gray-50">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
            </svg>
            Volver a Impuestos
        </a>
    </div>

    {{-- ═══════════════════════════════ TARJETAS DE RESUMEN (Estilo Stripe) ═══════════════════════════════ --}}
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        {{-- Total Deuda Histórica --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] uppercase font-bold text-gray-500 tracking-wider">Deuda Histórica</p>
                    <p class="text-2xl font-black text-gray-900 mt-1">S/ {{ number_format($totalDeudaHistorica, 2) }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-blue-100">
                    <svg class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
            </div>
            <p class="text-[10px] text-gray-400 mt-2">Total de deudas registradas históricamente</p>
        </div>

        {{-- Total Recaudado --}}
        <div class="rounded-2xl border border-green-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] uppercase font-bold text-green-600 tracking-wider">Total Recaudado</p>
                    <p class="text-2xl font-black text-green-700 mt-1">S/ {{ number_format($totalRecaudado, 2) }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-green-100">
                    <svg class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
            </div>
            <p class="text-[10px] text-gray-400 mt-2">Suma de todos los abonos realizados</p>
        </div>

        {{-- Saldo Por Cobrar --}}
        <div class="rounded-2xl border border-red-200 bg-white p-5 shadow-sm ring-1 ring-red-100">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] uppercase font-bold text-red-500 tracking-wider">Saldo Por Cobrar</p>
                    <p class="text-2xl font-black text-red-600 mt-1">S/ {{ number_format($totalSaldoPorCobrar, 2) }}</p>
                </div>
                <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-red-100">
                    <svg class="h-6 w-6 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
            </div>
            <p class="text-[10px] text-gray-400 mt-2">Monto total pendiente de cobro</p>
        </div>
    </div>

    {{-- ═══════════════════════════════ TABLA DE DEUDAS PENDIENTES ═══════════════════════════════ --}}
    <div class="rounded-2xl border border-gray-200 bg-white shadow-sm overflow-hidden">
        <div class="px-5 py-3.5 border-b border-gray-200 bg-gray-50 flex items-center justify-between">
            <h3 class="text-sm font-bold text-gray-700 flex items-center gap-2">
                <svg class="h-4 w-4 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0ZM3.75 12h.007v.008H3.75V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Zm-.375 5.25h.007v.008H3.75v-.008Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                </svg>
                Deudas Pendientes
            </h3>
            <span class="rounded-full bg-red-100 px-2.5 py-0.5 text-[10px] font-bold text-red-700">
                {{ $deudas->count() }} {{ $deudas->count() === 1 ? 'deuda' : 'deudas' }}
            </span>
        </div>

        @if($deudas->isEmpty())
            <div class="p-12 text-center">
                <svg class="mx-auto h-12 w-12 text-green-400" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
                <p class="text-gray-500 text-base font-semibold mt-3">¡No hay deudas pendientes!</p>
                <p class="text-gray-400 text-sm mt-1">Todas las obligaciones tributarias están al día.</p>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50/80 border-b border-gray-200">
                            <th class="text-left py-3 px-4 font-bold text-gray-600 text-xs">Cliente</th>
                            <th class="text-center py-3 px-3 font-bold text-gray-600 text-xs">Periodo</th>
                            <th class="text-left py-3 px-3 font-bold text-gray-600 text-xs">Tipo de Deuda</th>
                            <th class="text-center py-3 px-3 font-bold text-gray-600 text-xs">Vencimiento</th>
                            <th class="text-right py-3 px-3 font-bold text-gray-600 text-xs">Deuda Original</th>
                            <th class="text-right py-3 px-3 font-bold text-gray-600 text-xs">Total Abonado</th>
                            <th class="text-right py-3 px-4 font-bold text-gray-600 text-xs">Saldo Pendiente</th>
                            <th class="text-center py-3 px-3 font-bold text-gray-600 text-xs">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($deudas as $deuda)
                        <tr class="hover:bg-blue-50/30 transition-colors">
                            <td class="py-3 px-4">
                                <a href="{{ route('clientes.show', $deuda->cliente) }}"
                                   class="font-semibold text-gray-800 hover:text-brand-600 transition text-xs">
                                    {{ $deuda->cliente->razon_social }}
                                </a>
                                <p class="text-[10px] font-mono text-gray-400">{{ $deuda->cliente->ruc }}</p>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <span class="rounded bg-gray-100 border border-gray-200 px-2 py-0.5 text-[11px] font-bold text-gray-700">
                                    {{ $deuda->periodo }}
                                </span>
                            </td>
                            <td class="py-3 px-3">
                                <span class="inline-flex items-center rounded-full bg-indigo-100 px-2.5 py-0.5 text-[10px] font-bold text-indigo-700">
                                    IGV Justo / Fraccionamiento
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center text-xs">
                                @if($deuda->vencimiento_deuda)
                                    <span @class([
                                        'font-semibold',
                                        'text-red-600' => $deuda->vencimiento_deuda->isPast(),
                                        'text-amber-600' => !$deuda->vencimiento_deuda->isPast() && $deuda->vencimiento_deuda->diffInDays(now()) <= 7,
                                        'text-gray-600' => !$deuda->vencimiento_deuda->isPast() && $deuda->vencimiento_deuda->diffInDays(now()) > 7,
                                    ])>
                                        {{ $deuda->vencimiento_deuda->format('d/m/Y') }}
                                    </span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </td>
                            <td class="py-3 px-3 text-right text-xs text-gray-700 font-medium">
                                S/ {{ number_format($deuda->monto_deuda_inicial, 2) }}
                            </td>
                            <td class="py-3 px-3 text-right text-xs text-green-600 font-semibold">
                                S/ {{ number_format($deuda->total_abonado, 2) }}
                            </td>
                            <td class="py-3 px-4 text-right">
                                <span class="text-red-600 font-black text-sm">
                                    S/ {{ number_format($deuda->saldo_pendiente, 2) }}
                                </span>
                            </td>
                            <td class="py-3 px-3 text-center">
                                <button
                                    @click="abrirModalAbono({{ $deuda->id }}, '{{ addslashes($deuda->cliente->razon_social) }}', '{{ $deuda->periodo }}', {{ $deuda->monto_deuda_inicial }}, {{ $deuda->total_abonado }}, {{ $deuda->saldo_pendiente }})"
                                    class="inline-flex items-center gap-1 rounded-lg bg-brand-600 px-2.5 py-1.5 text-[10px] font-bold text-white shadow-xs transition hover:bg-brand-700 active:scale-95">
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                    Añadir Abono
                                </button>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{-- ═══════════════════════════════ MODAL: REGISTRAR ABONO ═══════════════════════════════ --}}
    <div x-show="showModal" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
         @keydown.escape.window="showModal = false">
        <div @click.away="showModal = false"
             class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 overflow-hidden transform transition-all"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100">

            {{-- Header --}}
            <div class="bg-gradient-to-r from-green-600 to-emerald-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <h3 class="text-lg font-semibold text-white flex items-center gap-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Registrar Abono
                    </h3>
                    <button @click="showModal = false" class="text-white/80 hover:text-white transition">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>
                <p class="text-green-100 text-sm mt-1" x-text="modalCliente + ' — ' + modalPeriodo"></p>
            </div>

            {{-- Resumen financiero --}}
            <div class="px-6 pt-4">
                <div class="grid grid-cols-3 gap-2.5 text-center">
                    <div class="bg-gray-50 rounded-lg p-2.5">
                        <p class="text-[9px] font-bold uppercase text-gray-500 tracking-wider">Deuda</p>
                        <p class="text-sm font-black text-gray-700 mt-0.5" x-text="'S/ ' + parseFloat(modalDeuda).toFixed(2)"></p>
                    </div>
                    <div class="bg-green-50 rounded-lg p-2.5">
                        <p class="text-[9px] font-bold uppercase text-green-600 tracking-wider">Abonado</p>
                        <p class="text-sm font-black text-green-700 mt-0.5" x-text="'S/ ' + parseFloat(modalAbonado).toFixed(2)"></p>
                    </div>
                    <div class="rounded-lg p-2.5" :class="modalSaldo > 0 ? 'bg-red-50' : 'bg-gray-50'">
                        <p class="text-[9px] font-bold uppercase tracking-wider" :class="modalSaldo > 0 ? 'text-red-600' : 'text-gray-500'">Saldo</p>
                        <p class="text-sm font-black mt-0.5" :class="modalSaldo > 0 ? 'text-red-700' : 'text-gray-500'" x-text="'S/ ' + parseFloat(modalSaldo).toFixed(2)"></p>
                    </div>
                </div>
            </div>

            {{-- Formulario --}}
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Monto del Abono</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs font-bold">S/</span>
                        <input type="number" x-model="abonoMonto" step="0.01" min="0.01"
                            :max="modalSaldo"
                            class="w-full pl-10 rounded-lg border-gray-300 shadow-sm text-sm focus:ring-green-500 focus:border-green-500"
                            placeholder="0.00">
                    </div>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Fecha de Pago</label>
                    <input type="date" x-model="abonoFecha"
                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-700 mb-1">Observación</label>
                    <input type="text" x-model="abonoObs"
                        class="w-full rounded-lg border-gray-300 shadow-sm text-sm focus:ring-green-500 focus:border-green-500"
                        placeholder="Opcional...">
                </div>
            </div>

            {{-- Footer --}}
            <div class="bg-gray-50 px-6 py-3.5 flex justify-end gap-3">
                <button @click="showModal = false"
                    class="px-4 py-2 text-xs font-medium text-gray-700 hover:text-gray-900 transition-colors">
                    Cancelar
                </button>
                <button @click="guardarAbono()"
                    :disabled="saving || !abonoMonto || !abonoFecha"
                    class="px-5 py-2 bg-green-600 text-white text-xs font-bold rounded-lg hover:bg-green-700 transition-colors disabled:opacity-50 flex items-center gap-2 shadow-sm active:scale-95">
                    <svg x-show="saving" class="animate-spin h-3.5 w-3.5" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <span x-text="saving ? 'Guardando...' : 'Registrar Abono'"></span>
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function controlDeudas() {
    return {
        showModal: false,
        saving: false,
        modalOperacionId: null,
        modalCliente: '',
        modalPeriodo: '',
        modalDeuda: 0,
        modalAbonado: 0,
        modalSaldo: 0,
        abonoMonto: '',
        abonoFecha: new Date().toISOString().split('T')[0],
        abonoObs: '',

        abrirModalAbono(operacionId, cliente, periodo, deuda, abonado, saldo) {
            this.modalOperacionId = operacionId;
            this.modalCliente = cliente;
            this.modalPeriodo = periodo;
            this.modalDeuda = deuda;
            this.modalAbonado = abonado;
            this.modalSaldo = saldo;
            this.abonoMonto = '';
            this.abonoFecha = new Date().toISOString().split('T')[0];
            this.abonoObs = '';
            this.showModal = true;
        },

        async guardarAbono() {
            this.saving = true;
            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
                const response = await fetch('{{ route("abonos_deudas.store") }}', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': csrfToken,
                        'Accept': 'application/json',
                    },
                    body: JSON.stringify({
                        operacion_id: this.modalOperacionId,
                        monto: parseFloat(this.abonoMonto),
                        fecha_pago: this.abonoFecha,
                        observacion: this.abonoObs || null,
                    }),
                });

                if (response.ok) {
                    this.showModal = false;
                    window.location.reload();
                } else {
                    const data = await response.json();
                    alert(data.message || 'Error al guardar el abono.');
                }
            } catch (error) {
                console.error('Error:', error);
                alert('Error de conexión.');
            } finally {
                this.saving = false;
            }
        }
    };
}
</script>
@endsection
