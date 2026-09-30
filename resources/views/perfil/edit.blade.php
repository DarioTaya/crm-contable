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

                <div x-data="{ verActual: false }">
                    <label class="block font-semibold text-gray-700 mb-1">Contraseña Actual</label>
                    <div class="relative">
                        <input :type="verActual ? 'text' : 'password'"
                               name="current_password"
                               required
                               autocomplete="current-password"
                               placeholder="••••••••"
                               class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 pr-10 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                        <button type="button"
                                @click="verActual = !verActual"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition cursor-pointer"
                                :title="verActual ? 'Ocultar contraseña' : 'Ver contraseña'">
                            <svg x-show="!verActual" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg x-show="verActual" x-cloak class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div x-data="{ verNueva: false }">
                    <label class="block font-semibold text-gray-700 mb-1">Nueva Contraseña (mínimo 6 caracteres)</label>
                    <div class="relative">
                        <input :type="verNueva ? 'text' : 'password'"
                               name="password"
                               required
                               autocomplete="new-password"
                               placeholder="••••••••"
                               class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 pr-10 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                        <button type="button"
                                @click="verNueva = !verNueva"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition cursor-pointer"
                                :title="verNueva ? 'Ocultar contraseña' : 'Ver contraseña'">
                            <svg x-show="!verNueva" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg x-show="verNueva" x-cloak class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                <div x-data="{ verConfirmar: false }">
                    <label class="block font-semibold text-gray-700 mb-1">Confirmar Nueva Contraseña</label>
                    <div class="relative">
                        <input :type="verConfirmar ? 'text' : 'password'"
                               name="password_confirmation"
                               required
                               autocomplete="new-password"
                               placeholder="••••••••"
                               class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 pr-10 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                        <button type="button"
                                @click="verConfirmar = !verConfirmar"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition cursor-pointer"
                                :title="verConfirmar ? 'Ocultar contraseña' : 'Ver contraseña'">
                            <svg x-show="!verConfirmar" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg x-show="verConfirmar" x-cloak class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
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
