@extends('layouts.app')

@section('title', 'Nuevo Miembro del Equipo')
@section('header', 'Registrar Nuevo Usuario')

@section('content')
<div class="max-w-4xl space-y-6">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Crear Nuevo Usuario</h2>
            <p class="text-sm text-gray-500">Configure los datos de acceso, rol y cartera de clientes asignada.</p>
        </div>
        <a href="{{ route('usuarios.index') }}"
           class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            <span>Volver al Equipo</span>
        </a>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <div class="font-bold mb-1">Por favor corrija los siguientes errores:</div>
            <ul class="list-disc pl-5 space-y-0.5 text-xs">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('usuarios.store') }}" method="POST"
          x-data="{
              rol: '{{ old('rol', 'asistente') }}',
              filtroCliente: '',
              seleccionarTodos() {
                  document.querySelectorAll('.check-cliente').forEach(el => el.checked = true);
              },
              deseleccionarTodos() {
                  document.querySelectorAll('.check-cliente').forEach(el => el.checked = false);
              }
          }"
          class="space-y-6">
        @csrf

        {{-- Datos Básicos --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs space-y-5">
            <h3 class="text-base font-bold text-gray-900 border-b border-gray-100 pb-3 flex items-center gap-2">
                <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-brand-600 text-xs font-bold">1</span>
                <span>Información Personal y Acceso</span>
            </h3>

            <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                {{-- Nombre Completo --}}
                <div>
                    <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                        Nombre Completo <span class="text-rose-500">*</span>
                    </label>
                    <input type="text"
                           id="name"
                           name="name"
                           value="{{ old('name') }}"
                           required
                           placeholder="Ej. Juan Pérez Quispe"
                           class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                </div>

                {{-- Correo Electrónico --}}
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                        Correo Electrónico (Usuario) <span class="text-rose-500">*</span>
                    </label>
                    <input type="email"
                           id="email"
                           name="email"
                           value="{{ old('email') }}"
                           required
                           placeholder="juan@estudiocontable.pe"
                           class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                </div>

                {{-- Teléfono / WhatsApp --}}
                <div>
                    <label for="telefono" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                        Teléfono / WhatsApp (Opcional)
                    </label>
                    <input type="text"
                           id="telefono"
                           name="telefono"
                           value="{{ old('telefono') }}"
                           placeholder="987654321"
                           class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                </div>

                {{-- Contraseña --}}
                <div x-data="{ verPass: false }">
                    <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                        Contraseña de Acceso <span class="text-rose-500">*</span>
                    </label>
                    <div class="relative">
                        <input :type="verPass ? 'text' : 'password'"
                               id="password"
                               name="password"
                               required
                               placeholder="Mínimo 6 caracteres"
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 pr-10 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                        <button type="button"
                                @click="verPass = !verPass"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition cursor-pointer"
                                :title="verPass ? 'Ocultar contraseña' : 'Ver contraseña'">
                            <svg x-show="!verPass" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg x-show="verPass" x-cloak class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>
            </div>

            {{-- Selección de Rol --}}
            <div class="pt-2">
                <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-2">
                    Rol en el Estudio <span class="text-rose-500">*</span>
                </label>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    {{-- Rol Asistente --}}
                    <label class="relative flex cursor-pointer rounded-xl border p-4 shadow-xs transition"
                           :class="rol === 'asistente' ? 'border-brand-600 bg-brand-50/40 ring-2 ring-brand-500/20' : 'border-gray-200 bg-white hover:bg-gray-50'">
                        <input type="radio" name="rol" value="asistente" x-model="rol" class="sr-only">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-blue-100 text-blue-700 font-bold">
                                👷
                            </span>
                            <div>
                                <span class="block text-sm font-bold text-gray-900">Asistente Contable</span>
                                <span class="block text-xs text-gray-500 mt-0.5">Opera únicamente los clientes asignados en su cartera. No puede eliminar clientes ni modificar ajustes del sistema.</span>
                            </div>
                        </div>
                    </label>

                    {{-- Rol Administrador --}}
                    <label class="relative flex cursor-pointer rounded-xl border p-4 shadow-xs transition"
                           :class="rol === 'admin' ? 'border-purple-600 bg-purple-50/40 ring-2 ring-purple-500/20' : 'border-gray-200 bg-white hover:bg-gray-50'">
                        <input type="radio" name="rol" value="admin" x-model="rol" class="sr-only">
                        <div class="flex items-start gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-700 font-bold">
                                👑
                            </span>
                            <div>
                                <span class="block text-sm font-bold text-gray-900">Administrador / Titular</span>
                                <span class="block text-xs text-gray-500 mt-0.5">Control absoluto del estudio. Ve todos los clientes, gestiona el equipo, configura scrapers y bots del sistema.</span>
                            </div>
                        </div>
                    </label>
                </div>
            </div>
        </div>

        {{-- Asignación de Cartera de Clientes --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs space-y-4"
             x-show="rol === 'asistente'"
             x-transition>
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-3">
                <div>
                    <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-brand-600 text-xs font-bold">2</span>
                        <span>Asignación de Cartera de Clientes</span>
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">Seleccione los clientes que este asistente podrá ver y operar.</p>
                </div>

                {{-- Acciones Rápidas Selección --}}
                <div class="flex items-center gap-2">
                    <button type="button" @click="seleccionarTodos()" class="text-xs font-semibold text-brand-600 hover:text-brand-800 hover:underline">
                        Marcar todos
                    </button>
                    <span class="text-gray-300">|</span>
                    <button type="button" @click="deseleccionarTodos()" class="text-xs font-semibold text-gray-500 hover:text-gray-700 hover:underline">
                        Desmarcar todos
                    </button>
                </div>
            </div>

            {{-- Buscador en vivo de clientes --}}
            <div class="relative">
                <input type="text"
                       x-model="filtroCliente"
                       placeholder="Buscar cliente por RUC o Razón Social..."
                       class="w-full rounded-xl border border-gray-200 bg-gray-50/70 py-2 pl-9 pr-3.5 text-xs text-gray-800 placeholder-gray-400 focus:border-brand-500 focus:bg-white focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                    </svg>
                </div>
            </div>

            {{-- Lista de Clientes con Checkboxes --}}
            <div class="max-h-72 overflow-y-auto divide-y divide-gray-100 rounded-xl border border-gray-200 bg-gray-50/30 p-2">
                @forelse ($clientes as $cliente)
                    <label class="flex items-center justify-between rounded-lg p-2.5 hover:bg-white hover:shadow-xs transition cursor-pointer select-none"
                           x-show="filtroCliente === '' || '{{ strtolower($cliente->razon_social . ' ' . $cliente->ruc) }}'.includes(filtroCliente.toLowerCase())">
                        <div class="flex items-center gap-3">
                            <input type="checkbox"
                                   name="clientes[]"
                                   value="{{ $cliente->id }}"
                                   class="check-cliente h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            <div>
                                <span class="block text-xs font-bold text-gray-900">{{ $cliente->razon_social }}</span>
                                <span class="block text-[11px] text-gray-500 font-mono">RUC: {{ $cliente->ruc }}</span>
                            </div>
                        </div>
                        <span class="rounded-md bg-gray-100 px-2 py-0.5 text-[10px] font-medium text-gray-600">
                            {{ $cliente->regimen_tributario?->value ?? 'General' }}
                        </span>
                    </label>
                @empty
                    <div class="py-6 text-center text-xs text-gray-400">
                        No hay clientes registrados en el estudio aún.
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Botones de Acción --}}
        <div class="flex items-center justify-end gap-3 pt-2">
            <a href="{{ route('usuarios.index') }}"
               class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition">
                Cancelar
            </a>
            <button type="submit"
                    class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-brand-500 transition active:scale-95">
                Registrar Usuario
            </button>
        </div>
    </form>

</div>
@endsection
