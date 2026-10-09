@extends('layouts.app')

@section('title', 'Documento Oficial — Buzón Electrónico')
@section('header', 'Documento Oficial')

@section('content')
<div class="mx-auto max-w-2xl py-8">
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 bg-gradient-to-r from-amber-500/10 via-amber-50 to-white px-6 py-5">
            <div class="flex items-center gap-3">
                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-700 shadow-2xs">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-gray-900">Comunicado Oficial Textual</h2>
                    <p class="text-xs text-gray-500">Notificación sin archivo PDF anexo en SUNAT SOL</p>
                </div>
            </div>
        </div>

        <div class="space-y-5 p-6">
            <div class="rounded-xl border border-amber-200 bg-amber-50/70 p-4 text-xs text-amber-900 leading-relaxed">
                Esta comunicación oficial fue registrada directamente como texto en el Buzón Electrónico SOL y no cuenta con un documento PDF anexo descargable en los servidores de SUNAT. El texto íntegro está disponible en el CRM.
            </div>

            <div class="rounded-xl border border-gray-100 bg-gray-50/60 p-4 text-xs space-y-2.5">
                <div class="flex items-center justify-between py-1 border-b border-gray-200/60">
                    <span class="font-medium text-gray-500">Cliente</span>
                    <span class="font-bold text-gray-900">{{ $alerta->cliente?->razon_social ?? 'Cliente #' . $alerta->cliente_id }}</span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-gray-200/60">
                    <span class="font-medium text-gray-500">RUC</span>
                    <span class="font-mono text-gray-800">{{ $alerta->cliente?->ruc ?? '-' }}</span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-gray-200/60">
                    <span class="font-medium text-gray-500">Plataforma</span>
                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider {{ $alerta->plataforma === 'SUNAT' ? 'bg-sky-100 text-sky-800' : 'bg-red-100 text-red-900' }}">
                        {{ $alerta->plataforma }}
                    </span>
                </div>
                <div class="flex items-center justify-between py-1 border-b border-gray-200/60">
                    <span class="font-medium text-gray-500">Asunto Oficial</span>
                    <span class="font-semibold text-gray-900 text-right max-w-sm">{{ $alerta->asunto }}</span>
                </div>
                <div class="flex items-center justify-between py-1">
                    <span class="font-medium text-gray-500">Fecha de Recepción</span>
                    <span class="font-mono text-gray-700">{{ $alerta->fecha_recepcion ? $alerta->fecha_recepcion->format('d/m/Y H:i:s') : '-' }}</span>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-3 pt-2">
                <button type="button"
                        onclick="window.close()"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs hover:bg-gray-50 transition">
                    Cerrar Pestaña
                </button>

                @if($alerta->cliente)
                    <a href="{{ route('clientes.show', ['cliente' => $alerta->cliente_id, 'tab' => 'notificaciones']) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg border border-brand-300 bg-brand-50 px-3.5 py-2 text-xs font-semibold text-brand-700 hover:bg-brand-100 transition shadow-2xs">
                        Ver en Expediente
                    </a>
                @endif

                <a href="{{ route('alertas.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-gray-900 px-4 py-2 text-xs font-semibold text-white shadow-xs hover:bg-black transition">
                    Ir al Buzón Global
                </a>
            </div>
        </div>
    </div>
</div>
@endsection
