@extends('layouts.app')

@section('title', 'Equipo y Usuarios')
@section('header', 'Gestión de Equipo & Control de Acceso')

@section('content')
<div class="space-y-6">

    {{-- Encabezado & Botón Crear --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Equipo del Estudio Contable</h2>
            <p class="text-sm text-gray-500">Administración de usuarios, roles de seguridad y distribución de cartera de clientes.</p>
        </div>
        <a href="{{ route('usuarios.create') }}"
           class="inline-flex items-center gap-2 rounded-xl bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-brand-500 transition active:scale-95">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
            </svg>
            <span>Nuevo Miembro</span>
        </a>
    </div>

    {{-- Tarjetas Resumen --}}
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Total Usuarios</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-blue-50 text-brand-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-gray-900">{{ $usuarios->count() }}</p>
            <p class="mt-1 text-xs text-gray-500">Miembros activos e inactivos</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Administradores</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-50 text-purple-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-purple-700">{{ $usuarios->where('rol', 'admin')->count() }}</p>
            <p class="mt-1 text-xs text-gray-500">Control total & configuración</p>
        </div>

        <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-xs">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold uppercase tracking-wider text-gray-400">Asistentes Contables</span>
                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-emerald-50 text-emerald-600">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 14.15v4.25c0 1.094-.787 2.036-1.872 2.18-2.087.277-4.216.42-6.378.42s-4.291-.143-6.378-.42c-1.085-.144-1.872-1.086-1.872-2.18v-4.25m16.5 0a2.18 2.18 0 0 0 .75-1.661V8.706c0-1.081-.768-2.015-1.837-2.175a48.114 48.114 0 0 0-3.413-.387m4.5 8.006c-.194.165-.42.295-.673.38A23.978 23.978 0 0 1 12 15.75c-2.648 0-5.195-.429-7.577-1.22a2.016 2.016 0 0 1-.673-.38m0 0A2.18 2.18 0 0 1 3 12.489V8.706c0-1.081.768-2.015 1.837-2.175a48.111 48.111 0 0 1 3.413-.387m7.5 0V5.25A2.25 2.25 0 0 0 13.5 3h-3a2.25 2.25 0 0 0-2.25 2.25v.894m7.5 0a48.667 48.667 0 0 0-7.5 0M12 12.75h.008v.008H12v-.008Z" />
                    </svg>
                </span>
            </div>
            <p class="mt-2 text-2xl font-bold text-emerald-700">{{ $usuarios->where('rol', 'asistente')->count() }}</p>
            <p class="mt-1 text-xs text-gray-500">Operación sobre cartera asignada</p>
        </div>
    </div>

    {{-- Tabla de Usuarios --}}
    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-xs">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="border-b border-gray-200 bg-gray-50/75 text-xs font-semibold uppercase tracking-wider text-gray-500">
                    <tr>
                        <th class="px-6 py-4">Usuario</th>
                        <th class="px-6 py-4">Rol & Permisos</th>
                        <th class="px-6 py-4">Estado</th>
                        <th class="px-6 py-4">Cartera Asignada</th>
                        <th class="px-6 py-4 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    @forelse ($usuarios as $user)
                        <tr class="hover:bg-gray-50/50 transition">
                            {{-- Nombre, Email y Teléfono --}}
                            <td class="px-6 py-4">
                                <div class="flex items-center gap-3">
                                    <a href="{{ route('usuarios.show', $user) }}"
                                       title="Ver Perfil de {{ $user->name }}"
                                       class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl font-bold text-white shadow-xs transition-transform hover:scale-105
                                        {{ $user->isAdmin() ? 'bg-purple-600' : 'bg-brand-600' }}"
                                       style="{{ $user->isAdmin() ? 'background-color: #9333ea; color: #ffffff;' : 'background-color: #2563eb; color: #ffffff;' }}">
                                        {{ $user->iniciales() }}
                                    </a>
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <a href="{{ route('usuarios.show', $user) }}"
                                               class="font-bold text-gray-900 hover:text-brand-600 transition">
                                                {{ $user->name }}
                                            </a>
                                            @if ($user->id === auth()->id())
                                                <span class="rounded-md bg-brand-50 px-2 py-0.5 text-[10px] font-semibold text-brand-700 border border-brand-200">Tú</span>
                                            @endif
                                        </div>
                                        <span class="block text-xs text-gray-500">{{ $user->email }}</span>
                                        @if ($user->telefono)
                                            <span class="block text-xs text-gray-400 mt-0.5">📞 {{ $user->telefono }}</span>
                                        @endif
                                    </div>
                                </div>
                            </td>

                            {{-- Rol --}}
                            <td class="px-6 py-4">
                                @if ($user->isAdmin())
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-purple-50 px-3 py-1 text-xs font-semibold text-purple-700 border border-purple-200/60 shadow-xs">
                                        👑 Administrador
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700 border border-blue-200/60 shadow-xs">
                                        👷 Asistente
                                    </span>
                                @endif
                            </td>

                            {{-- Estado Activo/Inactivo --}}
                            <td class="px-6 py-4">
                                @if ($user->activo)
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700 border border-emerald-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Activo
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 px-2.5 py-0.5 text-xs font-medium text-rose-700 border border-rose-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span>
                                        Desactivado
                                    </span>
                                @endif
                            </td>

                            {{-- Cartera de Clientes Asignados --}}
                            <td class="px-6 py-4">
                                @if ($user->isAdmin())
                                    <span class="text-xs font-medium text-gray-500 italic">Acceso Total a todos los clientes</span>
                                @else
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-gray-900">{{ $user->clientes_count }}</span>
                                        <span class="text-xs text-gray-500">{{ Str::plural('cliente', $user->clientes_count) }} asignados</span>
                                    </div>
                                @endif
                            </td>

                            {{-- Acciones --}}
                            <td class="px-6 py-4 text-right">
                                <div class="inline-flex items-center gap-2">
                                    @if ($user->isAdmin())
                                        <a href="{{ route('usuarios.show', $user) }}"
                                           title="Ver Perfil de {{ $user->name }}"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs hover:bg-brand-50 hover:text-brand-600 hover:border-brand-200 transition">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            <span>Ver Perfil</span>
                                        </a>
                                    @else
                                        <a href="{{ route('usuarios.show', $user) }}"
                                           title="Gestionar Asistente {{ $user->name }}"
                                           class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs hover:bg-brand-50 hover:text-brand-600 hover:border-brand-200 transition">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75" />
                                            </svg>
                                            <span>Gestionar</span>
                                        </a>
                                    @endif

                                    @if ($user->id !== auth()->id())
                                        <form action="{{ route('usuarios.toggle-activo', $user) }}" method="POST" class="inline"
                                              onsubmit="return confirm('¿Está seguro de cambiar el estado de este usuario?')">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 rounded-lg border px-3 py-1.5 text-xs font-medium transition
                                                    {{ $user->activo ? 'border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-100' : 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' }}">
                                                {{ $user->activo ? 'Desactivar' : 'Activar' }}
                                            </button>
                                        </form>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-8 text-center text-gray-400">
                                No hay usuarios registrados en este estudio.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection
