@extends('layouts.app')

@section('title', 'Códigos SUNAT')
@section('header', 'Códigos SUNAT')

@section('content')
    <div class="space-y-6">
        <div class="flex flex-col gap-4 border-b border-gray-200 pb-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-3">
                <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-brand-200 bg-brand-50 text-brand-600 shadow-xs">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                    </svg>
                </div>
                <div>
                    <h2 class="text-2xl font-bold tracking-tight text-gray-900">Códigos SUNAT</h2>
                    <p class="text-xs text-gray-500">Tributos y medios de pago de este estudio. El código del tributo es numérico. Si lo cambia, las deudas de ese código se actualizan. Puede eliminar un código que todavía no se usó.</p>
                </div>
            </div>
            <a href="{{ route('ajustes.index') }}" class="inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-3.5 py-2 text-xs font-semibold text-gray-700 shadow-2xs transition hover:bg-gray-50">Volver a ajustes</a>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">
                {{ $errors->first() }}
            </div>
        @endif

        <section class="space-y-4">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Tributos</h3>
                <p class="mt-0.5 text-[11px] text-gray-500">El código de renta mensual que marque con un régimen es el que se propone al cliente de ese régimen.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($tributos as $tributo)
                    <div class="flex h-full flex-col space-y-2 rounded-2xl border border-gray-200 bg-white p-4 shadow-xs">
                    <form method="POST" action="{{ route('ajustes.codigos.tributos.update', $tributo) }}" class="flex flex-1 flex-col space-y-3">
                        @csrf
                        @method('PUT')
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-mono text-sm font-bold text-gray-900">{{ $tributo->codigo }}</p>
                                <p class="text-[10px] text-gray-500">{{ $formularios[$tributo->formulario]['titulo'] ?? 'Otros' }} · {{ $roles[$tributo->rol] ?? $tributo->rol }}</p>
                            </div>
                            @if ($tributo->sistema)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">Sistema</span>
                            @else
                                <span class="rounded-full px-2 py-0.5 text-[10px] font-bold {{ $tributo->activo ? 'bg-emerald-100 text-emerald-800' : 'bg-gray-100 text-gray-500' }}">{{ $tributo->activo ? 'Activo' : 'Inactivo' }}</span>
                            @endif
                        </div>

                        @if ($tributo->sistema)
                            <input type="hidden" name="rol" value="{{ $tributo->rol }}">
                            <input type="hidden" name="formulario" value="{{ $tributo->formulario }}">
                            <input type="hidden" name="activo" value="1">
                        @else
                            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                                <label class="block text-[11px] font-semibold text-gray-700">
                                    Formulario
                                    <select name="formulario" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs">
                                        @foreach ($formularios as $clave => $formulario)
                                            <option value="{{ $clave }}" @selected($tributo->formulario === $clave)>{{ $formulario['titulo'] }}</option>
                                        @endforeach
                                    </select>
                                </label>
                                <label class="block text-[11px] font-semibold text-gray-700">
                                    Tipo
                                    <select name="rol" onchange="this.form.querySelector('[data-regimenes]').classList.toggle('hidden', this.value !== 'renta_mensual')" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs">
                                        @foreach ($roles as $clave => $etiqueta)
                                            <option value="{{ $clave }}" @selected($tributo->rol === $clave)>{{ $etiqueta }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>
                        @endif

                        <label class="block text-[11px] font-semibold text-gray-700">
                            Código
                            <input type="text" name="codigo" value="{{ $tributo->codigo }}" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 font-mono text-xs">
                        </label>
                        <label class="block text-[11px] font-semibold text-gray-700">
                            Denominación
                            <input type="text" name="denominacion" value="{{ $tributo->denominacion }}" maxlength="180" required class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs">
                        </label>
                        <label class="block text-[11px] font-semibold text-gray-700">
                            Nombre corto
                            <input type="text" name="nombre_corto" value="{{ $tributo->nombre_corto }}" maxlength="80" class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs">
                        </label>

                        <div data-regimenes class="{{ $tributo->rol === 'renta_mensual' ? '' : 'hidden' }}">
                            <p class="text-[11px] font-semibold text-gray-700">Se propone a los clientes de</p>
                            <div class="mt-1 flex flex-wrap gap-3">
                                @foreach ($regimenes as $clave => $etiqueta)
                                    <label class="inline-flex items-center gap-1.5 text-[11px] text-gray-700">
                                        <input type="checkbox" name="regimenes[]" value="{{ $clave }}" @checked(in_array($clave, $tributo->regimenes ?? [], true)) class="rounded border-gray-300 text-brand-600">
                                        {{ $etiqueta }}
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        @unless ($tributo->sistema)
                            <label class="inline-flex items-center gap-2 text-[11px] font-semibold text-gray-700">
                                <input type="checkbox" name="activo" value="1" @checked($tributo->activo) class="rounded border-gray-300 text-brand-600">
                                Visible en Tributos y pagos
                            </label>
                        @endunless

                        <div class="mt-auto flex items-center justify-between gap-2 border-t border-gray-100 pt-3">
                            <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-700">Guardar</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('ajustes.codigos.tributos.destroy', $tributo) }}" onsubmit="return confirm('¿Eliminar el tributo {{ $tributo->codigo }}?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-[11px] font-bold text-rose-600 hover:text-rose-800">Eliminar</button>
                    </form>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('ajustes.codigos.tributos.store') }}" class="space-y-3 rounded-2xl border border-dashed border-brand-300 bg-brand-50/40 p-4">
                @csrf
                <h4 class="text-sm font-bold text-gray-900">Nuevo tributo</h4>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                    <label class="block text-[11px] font-semibold text-gray-700">
                        Código
                        <input type="text" name="codigo" value="{{ old('codigo') }}" inputmode="numeric" maxlength="4" pattern="[0-9]{4}" required placeholder="5442" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 font-mono text-xs">
                    </label>
                    <label class="block text-[11px] font-semibold text-gray-700 md:col-span-1 xl:col-span-3">
                        Denominación
                        <input type="text" name="denominacion" value="{{ old('denominacion') }}" maxlength="180" required placeholder="Como figura en la constancia de SUNAT" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs">
                    </label>
                    <label class="block text-[11px] font-semibold text-gray-700">
                        Nombre corto
                        <input type="text" name="nombre_corto" value="{{ old('nombre_corto') }}" maxlength="80" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs">
                    </label>
                    <label class="block text-[11px] font-semibold text-gray-700">
                        Formulario
                        <select name="formulario" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs">
                            @foreach ($formularios as $clave => $formulario)
                                <option value="{{ $clave }}" @selected(old('formulario', 'otro') === $clave)>{{ $formulario['titulo'] }} · {{ $formulario['detalle'] }}</option>
                            @endforeach
                        </select>
                    </label>
                    <label class="block text-[11px] font-semibold text-gray-700">
                        Tipo
                        <select name="rol" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs">
                            @foreach ($roles as $clave => $etiqueta)
                                <option value="{{ $clave }}" @selected(old('rol', 'otro') === $clave)>{{ $etiqueta }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="text-[11px] font-semibold text-gray-700">
                        Régimen, si es renta mensual
                        <div class="mt-2 flex flex-wrap gap-3 font-normal">
                            @foreach ($regimenes as $clave => $etiqueta)
                                <label class="inline-flex items-center gap-1.5">
                                    <input type="checkbox" name="regimenes[]" value="{{ $clave }}" @checked(in_array($clave, old('regimenes', []), true)) class="rounded border-gray-300 text-brand-600">
                                    {{ $etiqueta }}
                                </label>
                            @endforeach
                        </div>
                    </div>
                </div>
                <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-700">Agregar tributo</button>
            </form>
        </section>

        <section class="space-y-4">
            <div>
                <h3 class="text-sm font-bold text-gray-900">Medios de pago</h3>
                <p class="mt-0.5 text-[11px] text-gray-500">Aparecen al registrar un pago de tributo. El código es el que se guarda en el abono; la denominación es lo que ve el contador.</p>
            </div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($medios as $medio)
                    <div class="flex flex-col rounded-2xl border border-gray-200 bg-white shadow-xs">
                    <form method="POST" action="{{ route('ajustes.codigos.medios.update', $medio) }}" class="flex flex-1 flex-col space-y-3 p-4">
                        @csrf
                        @method('PUT')
                        <div class="flex items-center justify-between gap-2">
                            @if ($medio->sistema)
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-bold text-slate-600">Sistema</span>
                            @else
                                <span></span>
                            @endif
                        </div>
                        <label class="block text-[11px] font-semibold text-gray-700">
                            Código
                            <input type="text" name="codigo" value="{{ $medio->codigo }}" maxlength="40" required class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 font-mono text-xs">
                        </label>
                        <label class="block text-[11px] font-semibold text-gray-700">
                            Denominación
                            <input type="text" name="denominacion" value="{{ $medio->denominacion }}" maxlength="120" required class="mt-1 w-full rounded-lg border border-gray-300 px-2 py-1.5 text-xs">
                        </label>
                        <label class="inline-flex items-center gap-2 text-[11px] font-semibold text-gray-700">
                            <input type="checkbox" name="activo" value="1" @checked($medio->activo) class="rounded border-gray-300 text-brand-600">
                            Visible al registrar pagos
                        </label>
                        <div class="mt-auto flex items-center justify-between border-t border-gray-100 pt-3">
                            <button type="submit" class="rounded-lg bg-brand-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-brand-700">Guardar</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('ajustes.codigos.medios.destroy', $medio) }}" onsubmit="return confirm('¿Eliminar el medio {{ $medio->denominacion }}?')" class="px-4 pb-3">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="text-[11px] font-bold text-rose-600 hover:text-rose-800">Eliminar</button>
                    </form>
                    </div>
                @endforeach
            </div>

            <form method="POST" action="{{ route('ajustes.codigos.medios.store') }}" class="grid grid-cols-1 gap-3 rounded-2xl border border-dashed border-emerald-300 bg-emerald-50/50 p-4 md:grid-cols-[8rem_1fr_auto] md:items-end">
                @csrf
                <label class="block text-[11px] font-semibold text-gray-700">
                    Código
                    <input type="text" name="codigo" value="{{ old('codigo') }}" maxlength="40" required placeholder="bcp" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 font-mono text-xs">
                </label>
                <label class="block text-[11px] font-semibold text-gray-700">
                    Denominación
                    <input type="text" name="denominacion" value="{{ old('denominacion') }}" maxlength="120" required placeholder="Banco de Crédito del Perú" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-2 py-1.5 text-xs">
                </label>
                <button type="submit" class="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700">Agregar medio</button>
            </form>
        </section>
    </div>
@endsection
