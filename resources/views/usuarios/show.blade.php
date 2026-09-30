@extends('layouts.app')

@section('title', 'Perfil de Usuario: ' . $usuario->name)
@section('header', 'Gestión de Equipo')

@section('content')
<div class="max-w-5xl mx-auto space-y-6"
     x-data="{
         modoEdicion: {{ (($modoEdicion ?? false) || $errors->any()) ? 'true' : 'false' }},
         rol: '{{ old('rol', $usuario->rol) }}',
         filtroCliente: '',
         filtroClienteVisualizacion: '',
         seleccionarTodos() {
             document.querySelectorAll('.check-cliente').forEach(el => el.checked = true);
         },
         deseleccionarTodos() {
             document.querySelectorAll('.check-cliente').forEach(el => el.checked = false);
         }
     }">

    {{-- Navegación superior y Acciones Principales --}}
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between border-b border-gray-200/80 pb-4">
        <div class="flex items-center gap-3">
            <a href="{{ route('usuarios.index') }}"
               class="inline-flex h-9 w-9 items-center justify-center rounded-xl border border-gray-200 bg-white text-gray-600 shadow-xs hover:bg-gray-50 hover:text-gray-900 transition"
               title="Volver al Equipo">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                </svg>
            </a>
            <div>
                <div class="flex items-center gap-2 text-xs font-semibold text-gray-500">
                    <a href="{{ route('usuarios.index') }}" class="hover:text-brand-600 transition">Equipo</a>
                    <span>/</span>
                    <span class="text-gray-900" x-text="modoEdicion ? 'Editar Perfil' : 'Ficha de Usuario'">Ficha de Usuario</span>
                </div>
                <h1 class="text-xl font-bold tracking-tight text-gray-900">
                    {{ $usuario->name }}
                    @if ($usuario->id === auth()->id())
                        <span class="ml-2 inline-flex items-center rounded-md bg-brand-50 px-2 py-0.5 text-xs font-semibold text-brand-700 border border-brand-200/60">
                            Tu Cuenta
                        </span>
                    @endif
                </h1>
            </div>
        </div>

        <div class="flex items-center gap-2">
            {{-- Botón para alternar a Modo Edición --}}
            <button type="button"
                    x-show="!modoEdicion"
                    @click="modoEdicion = true"
                    class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2 text-xs font-bold text-white shadow-xs hover:bg-brand-500 transition active:scale-95 cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125" />
                </svg>
                <span>Editar Información</span>
            </button>

            {{-- Botón para cancelar edición y volver a la ficha --}}
            <button type="button"
                    x-show="modoEdicion"
                    x-cloak
                    @click="modoEdicion = false"
                    class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition cursor-pointer">
                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
                <span>Cancelar</span>
            </button>

            {{-- Acción de Activar/Desactivar para usuarios que no son uno mismo --}}
            @if ($usuario->id !== auth()->id())
                <form action="{{ route('usuarios.toggle-activo', $usuario) }}" method="POST" class="inline"
                      onsubmit="return confirm('¿Está seguro de cambiar el estado de este usuario?')">
                    @csrf
                    @method('PATCH')
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-semibold transition shadow-xs cursor-pointer
                            {{ $usuario->activo ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                        <span>{{ $usuario->activo ? 'Desactivar Cuenta' : 'Activar Cuenta' }}</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    {{-- Banner de Alerta de Errores en Modo Edición --}}
    @if ($errors->any())
        <div class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 shadow-xs">
            <div class="font-bold mb-1 flex items-center gap-2">
                <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                </svg>
                <span>Por favor corrija los siguientes campos:</span>
            </div>
            <ul class="list-disc pl-5 space-y-0.5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- Hero Card de Identidad del Perfil --}}
    <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs">
        <div class="flex flex-col gap-5 sm:flex-row sm:items-center sm:justify-between">
            <div class="flex items-center gap-4">
                {{-- Gran Avatar de Perfil con Monograma de Iniciales estilizado por Rol --}}
                <div class="flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl shadow-md select-none tracking-wider font-black text-2xl text-white {{ $usuario->isAdmin() ? 'bg-purple-600 ring-4 ring-purple-100 border border-purple-500/30' : 'bg-brand-600 ring-4 ring-blue-100 border border-blue-500/30' }}"
                     style="{{ $usuario->isAdmin() ? 'background-color: #9333ea; color: #ffffff;' : 'background-color: #2563eb; color: #ffffff;' }}">
                    <span>{{ $usuario->iniciales() }}</span>
                </div>

                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-gray-900 leading-tight">{{ $usuario->name }}</h2>
                        {{-- Insignia de Rol --}}
                        @if ($usuario->isAdmin())
                            <span class="inline-flex items-center gap-1 rounded-full bg-purple-50 px-2.5 py-0.5 text-xs font-semibold text-purple-700 border border-purple-200">
                                👑 Administrador
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 px-2.5 py-0.5 text-xs font-semibold text-blue-700 border border-blue-200">
                                👷 Asistente Contable
                            </span>
                        @endif

                        {{-- Insignia de Estado --}}
                        @if ($usuario->activo)
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Cuenta Activa
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-semibold text-rose-700 border border-rose-200">
                                <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                Inactiva
                            </span>
                        @endif
                    </div>

                    <p class="text-xs text-gray-500 flex items-center gap-2">
                        <span>{{ $usuario->email }}</span>
                        @if ($usuario->telefono)
                            <span>•</span>
                            <span class="font-medium text-gray-700">{{ $usuario->telefono }}</span>
                        @endif
                    </p>
                </div>
            </div>

            {{-- Métricas Rápidas / Datos del Perfil --}}
            <div class="flex flex-wrap gap-4 border-t border-gray-100 pt-3 sm:border-t-0 sm:pt-0 text-xs">
                <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2">
                    <p class="text-[10px] uppercase font-bold text-gray-400">Estudio Contable</p>
                    <p class="font-semibold text-gray-800">{{ $usuario->estudio?->nombre ?? 'Estudio Principal' }}</p>
                </div>

                <div class="rounded-xl bg-gray-50/80 border border-gray-100 px-3.5 py-2">
                    <p class="text-[10px] uppercase font-bold text-gray-400">Cartera Asignada</p>
                    <p class="font-semibold text-gray-800">
                        {{ $usuario->isAdmin() ? 'Acceso Total (100%)' : count($assignedClientIds) . ' Clientes' }}
                    </p>
                </div>
            </div>
        </div>
    </div>

    {{-- ═════════════════════════════════ VISTA 1: FICHA DE PERFIL (MODO LECTURA) ═════════════════════════════════ --}}
    <div x-show="!modoEdicion" class="space-y-6">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">

            {{-- Tarjeta 1: Información Personal y de Acceso --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                            </svg>
                        </span>
                        <span>Información Personal</span>
                    </h3>
                    <button type="button" @click="modoEdicion = true" class="text-xs font-semibold text-brand-600 hover:text-brand-700 cursor-pointer">
                        Editar
                    </button>
                </div>

                <dl class="space-y-3.5 text-xs">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase text-gray-400">Nombre Completo</dt>
                        <dd class="mt-0.5 font-bold text-gray-900 text-sm">{{ $usuario->name }}</dd>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-[11px] font-semibold uppercase text-gray-400">DNI / Documento</dt>
                            <dd class="mt-0.5 font-semibold text-gray-800">{{ $usuario->dni ?: 'No registrado' }}</dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase text-gray-400">Cargo / Puesto</dt>
                            <dd class="mt-0.5 font-semibold text-brand-700">{{ $usuario->cargoParaMostrar() }}</dd>
                        </div>
                    </div>

                    <div>
                        <dt class="text-[11px] font-semibold uppercase text-gray-400">Correo Electrónico (Login)</dt>
                        <dd class="mt-0.5 font-medium text-gray-800 flex items-center gap-2">
                            <span>{{ $usuario->email }}</span>
                            <a href="mailto:{{ $usuario->email }}" class="text-brand-600 hover:underline text-[11px]">Contactar</a>
                        </dd>
                    </div>

                    <div>
                        <dt class="text-[11px] font-semibold uppercase text-gray-400">Teléfono / WhatsApp</dt>
                        <dd class="mt-0.5 font-medium text-gray-800 flex items-center gap-2">
                            @if ($usuario->telefono)
                                <span>{{ $usuario->telefono }}</span>
                                <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $usuario->telefono) }}" target="_blank" class="inline-flex items-center gap-1 text-emerald-600 hover:text-emerald-700 font-semibold text-[11px]">
                                    <span>WhatsApp</span>
                                </a>
                            @else
                                <span class="text-gray-400 italic">No registrado</span>
                            @endif
                        </dd>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <dt class="text-[11px] font-semibold uppercase text-gray-400">Fecha de Cumpleaños</dt>
                            <dd class="mt-0.5 font-medium text-gray-800 flex items-center gap-1.5 flex-wrap">
                                @if ($usuario->fecha_nacimiento)
                                    <span>🎂 {{ $usuario->fecha_nacimiento->format('d/m/Y') }}</span>
                                    @if ($usuario->esCumpleanosHoy())
                                        <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-black text-amber-800 border border-amber-300 animate-pulse">
                                            ¡Hoy Cumple Años! 🎉
                                        </span>
                                    @endif
                                @else
                                    <span class="text-gray-400 italic">No registrada</span>
                                @endif
                            </dd>
                        </div>
                        <div>
                            <dt class="text-[11px] font-semibold uppercase text-gray-400">Fecha de Contratación</dt>
                            <dd class="mt-0.5 font-medium text-gray-800">
                                @if ($usuario->fecha_ingreso)
                                    <span>📅 {{ $usuario->fecha_ingreso->format('d/m/Y') }}</span>
                                @else
                                    <span class="text-gray-400 italic">No registrada</span>
                                @endif
                            </dd>
                        </div>
                    </div>

                    @if ($usuario->fecha_cese)
                        <div>
                            <dt class="text-[11px] font-semibold uppercase text-rose-500">Fecha de Cese Laboral</dt>
                            <dd class="mt-0.5 font-bold text-rose-700 flex items-center gap-1.5">
                                <span>🛑 {{ $usuario->fecha_cese->format('d/m/Y') }}</span>
                                <span class="rounded bg-rose-100 text-rose-800 text-[10px] px-1.5 py-0.5 font-semibold">Baja efectuada</span>
                            </dd>
                        </div>
                    @endif

                    @if ($usuario->direccion)
                        <div>
                            <dt class="text-[11px] font-semibold uppercase text-gray-400">Dirección</dt>
                            <dd class="mt-0.5 font-medium text-gray-800">{{ $usuario->direccion }}</dd>
                        </div>
                    @endif

                    <div>
                        <dt class="text-[11px] font-semibold uppercase text-gray-400">Estudio Contable</dt>
                        <dd class="mt-0.5 font-medium text-gray-800">{{ $usuario->estudio?->nombre ?? 'Estudio Principal' }}</dd>
                    </div>
                </dl>
            </div>

            {{-- Tarjeta 2: Seguridad y Control de Acceso --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs space-y-4">
                <div class="flex items-center justify-between border-b border-gray-100 pb-3">
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                        </span>
                        <span>Seguridad y Permisos</span>
                    </h3>
                    <button type="button" @click="modoEdicion = true" class="text-xs font-semibold text-purple-600 hover:text-purple-700 cursor-pointer">
                        Cambiar Clave
                    </button>
                </div>

                <dl class="space-y-3.5 text-xs">
                    <div>
                        <dt class="text-[11px] font-semibold uppercase text-gray-400">Rol de Acceso al CRM</dt>
                        <dd class="mt-1">
                            @if ($usuario->isAdmin())
                                <p class="font-bold text-purple-900">👑 Administrador General</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">Control completo de clientes, usuarios, bots RPA, cronogramas y configuración del estudio.</p>
                            @else
                                <p class="font-bold text-blue-900">👷 Asistente Operativo</p>
                                <p class="text-[11px] text-gray-500 mt-0.5">Acceso exclusivo a la cartera de clientes que le ha sido explícitamente asignada.</p>
                            @endif
                        </dd>
                    </div>

                    <div>
                        <dt class="text-[11px] font-semibold uppercase text-gray-400">Estado del Acceso</dt>
                        <dd class="mt-1">
                            <span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium {{ $usuario->activo ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-rose-50 text-rose-700 border border-rose-200' }}">
                                {{ $usuario->activo ? '✅ Habilitado para iniciar sesión' : '⛔ Acceso bloqueado' }}
                            </span>
                        </dd>
                    </div>

                    <div x-data="{ verPass: false, copiado: false }">
                        <dt class="text-[11px] font-semibold uppercase text-gray-400">Contraseña de Acceso</dt>
                        <dd class="mt-1 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xs font-semibold"
                                      :class="verPass ? 'text-gray-900 bg-gray-100 px-2 py-0.5 rounded' : 'text-gray-400 tracking-widest'">
                                    <span x-show="!verPass">••••••••••••</span>
                                    <span x-show="verPass" x-cloak>{{ $usuario->password_plain ?: 'admin123' }}</span>
                                </span>
                                <button type="button"
                                        @click="verPass = !verPass"
                                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition cursor-pointer"
                                        :title="verPass ? 'Ocultar contraseña' : 'Ver contraseña'">
                                    <svg x-show="!verPass" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                    <svg x-show="verPass" x-cloak class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </button>
                                <button type="button"
                                        @click="navigator.clipboard.writeText('{{ $usuario->password_plain ?: 'admin123' }}'); copiado = true; setTimeout(() => copiado = false, 2000)"
                                        class="p-1 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition cursor-pointer"
                                        title="Copiar contraseña al portapapeles">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                    </svg>
                                </button>
                                <span x-show="copiado" x-cloak class="text-[10px] text-emerald-600 font-bold bg-emerald-50 px-1.5 py-0.5 rounded">¡Copiado!</span>
                            </div>
                            <button type="button" @click="modoEdicion = true" class="text-[11px] font-semibold text-brand-600 hover:underline cursor-pointer">
                                Actualizar Clave
                            </button>
                        </dd>
                    </div>
                </dl>
            </div>
        </div>

        {{-- Tarjeta 3: Cartera de Clientes Asignados --}}
        <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-3 gap-2">
                <div>
                    <h3 class="text-sm font-bold text-gray-900 flex items-center gap-2">
                        <span class="flex h-7 w-7 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                        </span>
                        <span>Cartera de Clientes Asignada</span>
                    </h3>
                    <p class="text-xs text-gray-500 mt-0.5">Empresas clientes que este usuario puede consultar y gestionar.</p>
                </div>

                @if ($usuario->isAsistente())
                    <button type="button" @click="modoEdicion = true" class="text-xs font-semibold text-brand-600 hover:text-brand-700 cursor-pointer self-start sm:self-auto">
                        Gestionar Cartera
                    </button>
                @endif
            </div>

            @if ($usuario->isAdmin())
                {{-- Banner Informativo para el Administrador --}}
                <div class="rounded-xl border border-purple-200 bg-gradient-to-r from-purple-50/70 to-indigo-50/70 p-5 text-xs text-purple-900 flex items-start gap-3">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-purple-100 text-purple-700 font-bold">
                        👑
                    </span>
                    <div class="space-y-1">
                        <p class="font-bold text-sm text-purple-950">Acceso Total e Irrestricto a Todos los Clientes</p>
                        <p class="text-purple-800 leading-relaxed">
                            Al contar con el rol de <span class="font-semibold">Administrador / Titular</span>, este usuario tiene acceso garantizado al 100% de los clientes registrados en el estudio contable. Puede consultar declaraciones, impuestos, deudas, libros electrónicos, balances y lanzar ejecuciones de bots automáticos sin restricciones de cartera.
                        </p>
                    </div>
                </div>
            @else
                {{-- Listado de Clientes del Asistente --}}
                @php
                    $clientesAsignados = $clientes->whereIn('id', $assignedClientIds);
                @endphp

                @if ($clientesAsignados->isEmpty())
                    <div class="rounded-xl border border-dashed border-gray-300 p-8 text-center text-xs text-gray-500 space-y-2">
                        <p class="font-medium text-gray-700">Este asistente no tiene clientes asignados en este momento.</p>
                        <p class="text-gray-400">Haga clic en «Editar Información» para asignarle clientes de su estudio contable.</p>
                        <div class="pt-2">
                            <button type="button" @click="modoEdicion = true" class="rounded-xl bg-brand-600 text-white px-4 py-2 font-bold hover:bg-brand-500 transition">
                                Asignar Clientes Ahora
                            </button>
                        </div>
                    </div>
                @else
                    {{-- Buscador en vivo para la visualización --}}
                    <div class="relative">
                        <input type="text"
                               x-model="filtroClienteVisualizacion"
                               placeholder="Filtrar por RUC o Razón Social..."
                               class="w-full rounded-xl border border-gray-200 bg-gray-50/60 py-2 pl-9 pr-3.5 text-xs text-gray-800 placeholder-gray-400 focus:border-brand-500 focus:bg-white focus:outline-none">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 max-h-80 overflow-y-auto pr-1">
                        @foreach ($clientesAsignados as $cliente)
                            <div class="rounded-xl border border-gray-200 bg-gray-50/40 p-3 flex items-center justify-between text-xs hover:bg-white hover:shadow-xs transition"
                                 x-show="filtroClienteVisualizacion === '' || '{{ strtolower($cliente->razon_social . ' ' . $cliente->ruc) }}'.includes(filtroClienteVisualizacion.toLowerCase())">
                                <div>
                                    <p class="font-bold text-gray-900 leading-tight">{{ $cliente->razon_social }}</p>
                                    <p class="text-[11px] font-mono text-gray-500 mt-0.5">RUC: {{ $cliente->ruc }}</p>
                                </div>
                                <span class="rounded-md bg-white border border-gray-200 px-2 py-0.5 text-[10px] font-semibold text-gray-600">
                                    {{ $cliente->regimen_tributario?->value ?? 'General' }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            @endif
        </div>
    </div>

    {{-- ═════════════════════════════════ VISTA 2: FORMULARIO DE EDICIÓN INTEGRADO ═════════════════════════════════ --}}
    <div x-show="modoEdicion" x-cloak class="space-y-6">
        <form action="{{ route('usuarios.update', $usuario) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            {{-- Bloque 1: Datos Personales & Acceso --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs space-y-5">
                <div class="border-b border-gray-100 pb-3 flex items-center justify-between">
                    <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                        <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-brand-600 text-xs font-bold">1</span>
                        <span>Información Personal y Credenciales</span>
                    </h3>
                    <span class="text-xs text-gray-400">Modificando perfil</span>
                </div>

                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    {{-- Nombre Completo --}}
                    <div>
                        <label for="name" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Nombre Completo <span class="text-rose-500">*</span>
                        </label>
                        <input type="text"
                               id="name"
                               name="name"
                               value="{{ old('name', $usuario->name) }}"
                               required
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>

                    {{-- Correo Electrónico --}}
                    <div>
                        <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Correo Electrónico (Login) <span class="text-rose-500">*</span>
                        </label>
                        <input type="email"
                               id="email"
                               name="email"
                               value="{{ old('email', $usuario->email) }}"
                               required
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>

                    {{-- Teléfono / WhatsApp --}}
                    <div>
                        <label for="telefono" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Teléfono / WhatsApp
                        </label>
                        <input type="text"
                               id="telefono"
                               name="telefono"
                               value="{{ old('telefono', $usuario->telefono) }}"
                               placeholder="987654321"
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>

                    {{-- DNI / Documento de Identidad --}}
                    <div>
                        <label for="dni" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            DNI / Documento de Identidad
                        </label>
                        <input type="text"
                               id="dni"
                               name="dni"
                               value="{{ old('dni', $usuario->dni) }}"
                               placeholder="8 dígitos"
                               maxlength="15"
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>

                    {{-- Cargo / Puesto --}}
                    <div>
                        <label for="cargo" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Cargo en el Estudio Contable
                        </label>
                        <input type="text"
                               id="cargo"
                               name="cargo"
                               value="{{ old('cargo', $usuario->cargo) }}"
                               placeholder="Ej. Asistente Contable, Tributario, Planillas..."
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>

                    {{-- Fecha de Nacimiento / Cumpleaños --}}
                    <div>
                        <label for="fecha_nacimiento" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5 flex items-center justify-between">
                            <span>Fecha de Cumpleaños / Nacimiento</span>
                            <span class="text-brand-600 font-normal lowercase">🎂 Experiencia CRM</span>
                        </label>
                        <input type="date"
                               id="fecha_nacimiento"
                               name="fecha_nacimiento"
                               value="{{ old('fecha_nacimiento', $usuario->fecha_nacimiento?->format('Y-m-d')) }}"
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>

                    {{-- Fecha de Ingreso / Contratación --}}
                    <div>
                        <label for="fecha_ingreso" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Fecha de Contratación / Ingreso
                        </label>
                        <input type="date"
                               id="fecha_ingreso"
                               name="fecha_ingreso"
                               value="{{ old('fecha_ingreso', $usuario->fecha_ingreso?->format('Y-m-d')) }}"
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>

                    {{-- Fecha de Cese Laboral --}}
                    <div>
                        <label for="fecha_cese" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Fecha de Cese Laboral <span class="text-gray-400 font-normal lowercase">(opcional)</span>
                        </label>
                        <input type="date"
                               id="fecha_cese"
                               name="fecha_cese"
                               value="{{ old('fecha_cese', $usuario->fecha_cese?->format('Y-m-d')) }}"
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>

                    {{-- Dirección Domiciliaria --}}
                    <div>
                        <label for="direccion" class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Dirección Domiciliaria
                        </label>
                        <input type="text"
                               id="direccion"
                               name="direccion"
                               value="{{ old('direccion', $usuario->direccion) }}"
                               placeholder="Av. o Calle, Urbanización, Ciudad"
                               class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                    </div>

                    {{-- Contraseña (opcional para editar) con visor de clave actual --}}
                    <div x-data="{ verPassInput: false, verPassActual: false, copiado: false }">
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-gray-700">
                                Cambiar Contraseña <span class="text-gray-400 font-normal lowercase">(dejar en blanco para mantener)</span>
                            </label>
                        </div>
                        <div class="relative">
                            <input :type="verPassInput ? 'text' : 'password'"
                                   id="password"
                                   name="password"
                                   placeholder="••••••••••••"
                                   class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 pr-10 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                            <button type="button"
                                    @click="verPassInput = !verPassInput"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition cursor-pointer"
                                    :title="verPassInput ? 'Ocultar contraseña' : 'Ver contraseña'">
                                <svg x-show="!verPassInput" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <svg x-show="verPassInput" x-cloak class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                            </button>
                        </div>
                        <div class="mt-2 flex items-center justify-between text-[11px] text-gray-500 bg-gray-50 border border-gray-200/80 rounded-lg px-2.5 py-1.5">
                            <span class="flex items-center gap-1.5">
                                <span>Clave actual registrada:</span>
                                <span class="font-mono font-bold text-gray-800">
                                    <span x-show="!verPassActual">••••••••</span>
                                    <span x-show="verPassActual" x-cloak>{{ $usuario->password_plain ?: 'admin123' }}</span>
                                </span>
                            </span>
                            <div class="flex items-center gap-2">
                                <button type="button" @click="verPassActual = !verPassActual" class="text-brand-600 hover:underline font-semibold cursor-pointer" x-text="verPassActual ? 'Ocultar' : 'Ver clave'"></button>
                                <span>•</span>
                                <button type="button" @click="navigator.clipboard.writeText('{{ $usuario->password_plain ?: 'admin123' }}'); copiado = true; setTimeout(() => copiado = false, 2000)" class="text-gray-600 hover:text-gray-900 cursor-pointer" x-text="copiado ? '¡Copiada!' : 'Copiar'"></button>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Estado y Rol --}}
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2 pt-3 border-t border-gray-100">
                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Estado de la Cuenta <span class="text-rose-500">*</span>
                        </label>
                        @if ($usuario->id === auth()->id())
                            <div class="flex items-center gap-2 text-xs text-gray-500 py-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700 border border-emerald-200">
                                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                    Activo (Tu cuenta actual)
                                </span>
                                <input type="hidden" name="activo" value="1">
                            </div>
                        @else
                            <select name="activo"
                                    class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                                <option value="1" {{ old('activo', $usuario->activo) ? 'selected' : '' }}>✅ Cuenta Activa (Permite el acceso al CRM)</option>
                                <option value="0" {{ ! old('activo', $usuario->activo) ? 'selected' : '' }}>⛔ Cuenta Desactivada (Bloquea el acceso al CRM)</option>
                            </select>
                        @endif
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-wider text-gray-700 mb-1.5">
                            Rol de Acceso <span class="text-rose-500">*</span>
                        </label>
                        @if ($usuario->id === auth()->id())
                            <div class="flex items-center gap-2 text-xs text-gray-500 py-2">
                                <span class="inline-flex items-center gap-1.5 rounded-full bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700 border border-purple-200">
                                    👑 Administrador (Tu rol)
                                </span>
                                <input type="hidden" name="rol" value="admin">
                            </div>
                        @else
                            <select name="rol"
                                    x-model="rol"
                                    class="w-full rounded-xl border border-gray-300 bg-white py-2 px-3.5 text-sm text-gray-900 focus:border-brand-500 focus:ring-2 focus:ring-brand-500/20 focus:outline-none">
                                <option value="asistente">👷 Asistente Contable (Opera solo clientes asignados)</option>
                                <option value="admin">👑 Administrador / Titular (Control total del sistema)</option>
                            </select>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Bloque 2: Cartera de Clientes Asignados (Si el rol es Asistente) --}}
            <div class="rounded-2xl border border-gray-200 bg-white p-6 shadow-xs space-y-4"
                 x-show="rol === 'asistente'"
                 x-transition>
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 pb-3">
                    <div>
                        <h3 class="text-base font-bold text-gray-900 flex items-center gap-2">
                            <span class="flex h-6 w-6 items-center justify-center rounded-md bg-brand-50 text-brand-600 text-xs font-bold">2</span>
                            <span>Asignación de Cartera de Clientes</span>
                        </h3>
                        <p class="text-xs text-gray-500 mt-0.5">Seleccione los clientes que este asistente podrá ver y gestionar.</p>
                    </div>

                    <div class="flex items-center gap-2">
                        <button type="button" @click="seleccionarTodos()" class="text-xs font-semibold text-brand-600 hover:text-brand-800 hover:underline cursor-pointer">
                            Marcar todos
                        </button>
                        <span class="text-gray-300">|</span>
                        <button type="button" @click="deseleccionarTodos()" class="text-xs font-semibold text-gray-500 hover:text-gray-700 hover:underline cursor-pointer">
                            Desmarcar todos
                        </button>
                    </div>
                </div>

                <div class="relative">
                    <input type="text"
                           x-model="filtroCliente"
                           placeholder="Buscar cliente por RUC o Razón Social..."
                           class="w-full rounded-xl border border-gray-200 bg-gray-50/70 py-2 pl-9 pr-3.5 text-xs text-gray-800 placeholder-gray-400 focus:border-brand-500 focus:bg-white focus:outline-none">
                    <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-gray-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                </div>

                <div class="max-h-72 overflow-y-auto divide-y divide-gray-100 rounded-xl border border-gray-200 bg-gray-50/30 p-2">
                    @forelse ($clientes as $cliente)
                        <label class="flex items-center justify-between rounded-lg p-2.5 hover:bg-white hover:shadow-xs transition cursor-pointer select-none"
                               x-show="filtroCliente === '' || '{{ strtolower($cliente->razon_social . ' ' . $cliente->ruc) }}'.includes(filtroCliente.toLowerCase())">
                            <div class="flex items-center gap-3">
                                <input type="checkbox"
                                       name="clientes[]"
                                       value="{{ $cliente->id }}"
                                       {{ in_array($cliente->id, old('clientes', $assignedClientIds)) ? 'checked' : '' }}
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

            {{-- Botones de Acción del Formulario --}}
            <div class="flex items-center justify-end gap-3 pt-2">
                <button type="button"
                        @click="modoEdicion = false"
                        class="rounded-xl border border-gray-200 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50 transition cursor-pointer">
                    Cancelar
                </button>
                <button type="submit"
                        class="rounded-xl bg-brand-600 px-6 py-2.5 text-sm font-bold text-white shadow-xs hover:bg-brand-500 transition active:scale-95 cursor-pointer">
                    Guardar Cambios
                </button>
            </div>
        </form>
    </div>

</div>
@endsection
