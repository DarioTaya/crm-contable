@extends('layouts.app')

@section('title', 'Mi Perfil de Usuario')
@section('header', 'Mi Perfil')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between border-b border-gray-200 pb-5">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-purple-50 border border-purple-200 text-purple-700 shadow-xs">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                </svg>
            </div>
            <div>
                <h2 class="text-2xl font-bold tracking-tight text-gray-900">Mi Perfil y Cuenta</h2>
                <p class="text-xs text-gray-500">Gestiona tus datos de acceso personal, contacto y seguridad.</p>
            </div>
        </div>
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold shadow-xs
                {{ $user->isAdmin() ? 'bg-purple-50 text-purple-700 border border-purple-200' : 'bg-blue-50 text-blue-700 border border-blue-200' }}">
                {{ $user->isAdmin() ? '👑 Administrador' : '👷 Asistente Contable' }}
            </span>
        </div>
    </div>

    @if ($errors->any())
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-700">
            <ul class="list-disc list-inside space-y-1">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

        {{-- Tarjeta 1: Información Personal --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
            <div class="flex items-center gap-3 border-b border-gray-100 pb-4 mb-4">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0 0 12 15.75a7.488 7.488 0 0 0-5.982 2.975m11.963 0a9 9 0 1 0-11.963 0m11.963 0A8.966 8.966 0 0 1 12 21a8.966 8.966 0 0 1-5.982-2.275M15 9.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Datos Personales</h3>
                    <p class="text-[11px] text-gray-400">Identificación visible en el sistema</p>
                </div>
            </div>

            <form action="{{ route('perfil.update') }}" method="POST" class="space-y-4 text-xs">
                @csrf
                @method('PUT')

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Nombre Completo</label>
                    <input type="text"
                           name="name"
                           value="{{ old('name', $user->name) }}"
                           required
                           class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Correo Electrónico (Login)</label>
                    <input type="email"
                           name="email"
                           value="{{ old('email', $user->email) }}"
                           required
                           class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Teléfono Personal / Celular</label>
                    <input type="text"
                           name="telefono"
                           value="{{ old('telefono', $user->telefono) }}"
                           placeholder="+51 987 654 321"
                           class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Estudio Contable Asignado</label>
                    <input type="text"
                           disabled
                           value="{{ $user->estudio?->nombre ?? 'Estudio Principal' }}"
                           class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-xs text-gray-500 shadow-xs cursor-not-allowed">
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-brand-600 hover:bg-brand-700 text-white px-4 py-2.5 text-xs font-bold shadow-xs transition active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        <span>Guardar Datos Personales</span>
                    </button>
                </div>
            </form>
        </div>

        {{-- Tarjeta 2: Cambio de Contraseña --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
            <div class="flex items-center gap-3 border-b border-gray-100 pb-4 mb-4">
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-50 text-amber-700">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                </span>
                <div>
                    <h3 class="text-sm font-bold text-gray-900">Seguridad de la Cuenta</h3>
                    <p class="text-[11px] text-gray-400">Actualiza tu contraseña de acceso</p>
                </div>
            </div>

            <form action="{{ route('perfil.password') }}" method="POST" class="space-y-4 text-xs">
                @csrf
                @method('PUT')

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Contraseña Actual</label>
                    <input type="password"
                           name="current_password"
                           required
                           autocomplete="current-password"
                           placeholder="••••••••"
                           class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Nueva Contraseña (mínimo 6 caracteres)</label>
                    <input type="password"
                           name="password"
                           required
                           autocomplete="new-password"
                           placeholder="••••••••"
                           class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Confirmar Nueva Contraseña</label>
                    <input type="password"
                           name="password_confirmation"
                           required
                           autocomplete="new-password"
                           placeholder="••••••••"
                           class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div class="pt-2">
                    <button type="submit"
                            class="inline-flex items-center gap-2 rounded-xl bg-amber-600 hover:bg-amber-700 text-white px-4 py-2.5 text-xs font-bold shadow-xs transition active:scale-95">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <span>Actualizar Contraseña</span>
                    </button>
                </div>
            </form>
        </div>

    </div>
</div>
@endsection
