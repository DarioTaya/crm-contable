@extends('layouts.app')

@section('title', 'Mi Perfil de Usuario')
@section('header', 'Mi Perfil')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">

    {{-- Encabezado --}}
    <div class="flex items-center justify-between border-b border-gray-200 pb-5">
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 items-center justify-center rounded-xl shadow-xs {{ $user->isAdmin() ? 'bg-gold-50 border border-gold-500/40 text-gold-600' : 'bg-slate-50 border border-slate-200 text-slate-600' }}">
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
                {{ $user->isAdmin() ? 'bg-gold-50 text-gold-600 border border-gold-500/40' : ($user->isContador() ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-50 text-slate-600 border border-slate-200') }}">
                <x-cuenta.insignia :rol="$user->rol" :variante="$user->insigniaClave()" tamano="sm" />
                {{ $user->etiquetaRol() }}
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

            <form action="{{ route('perfil.update') }}" method="POST" enctype="multipart/form-data" class="space-y-4 text-xs"
                  x-data="{
                      previewUrl: null,
                      eliminarFoto: false,
                      fotoActual: @js($user->fotoPerfilUrl()),
                      insignia: @js(old('insignia', $user->insigniaClave())),
                      insigniaTocada: true,
                      seleccionarFoto(e) {
                          const file = e.target.files[0];
                          if (file) {
                              this.eliminarFoto = false;
                              this.previewUrl = URL.createObjectURL(file);
                          }
                      },
                      quitarFoto() {
                          this.previewUrl = null;
                          this.eliminarFoto = true;
                          if (this.$refs.fotoInput) {
                              this.$refs.fotoInput.value = '';
                          }
                      }
                  }">
                @csrf
                @method('PUT')
                <input type="hidden" name="eliminar_foto" :value="eliminarFoto ? '1' : '0'">

                {{-- Sección Foto de Perfil / Avatar Premium --}}
                <div class="flex items-center gap-4 p-3 rounded-2xl bg-gray-50 border border-gray-200 mb-3">
                    <div class="relative shrink-0">
                        <template x-if="previewUrl">
                            <img :src="previewUrl" alt="Vista previa" class="h-16 w-16 rounded-full object-cover ring-2 shadow-sm {{ $user->isAdmin() ? 'ring-ink' : 'ring-slate-300' }}">
                        </template>
                        <template x-if="!previewUrl && fotoActual && !eliminarFoto">
                            <img :src="fotoActual" alt="{{ $user->name }}" class="h-16 w-16 rounded-full object-cover ring-2 shadow-sm {{ $user->isAdmin() ? 'ring-ink' : 'ring-slate-300' }}">
                        </template>
                        <template x-if="(!previewUrl && !fotoActual) || (!previewUrl && eliminarFoto)">
                            <div class="flex h-16 w-16 items-center justify-center rounded-full font-bold text-lg text-white shadow-sm ring-2 {{ $user->isAdmin() ? 'ring-ink bg-gold-600' : 'ring-slate-300 bg-slate-600' }}">
                                {{ $user->iniciales() }}
                            </div>
                        </template>

                        @foreach (\App\Models\User::INSIGNIAS as $claveInsignia)
                            <x-cuenta.insignia :rol="$user->rol" :variante="$claveInsignia" tamano="sm" class="absolute -bottom-0.5 -right-0.5" x-show="insignia === '{{ $claveInsignia }}'" x-cloak />
                        @endforeach
                    </div>

                    <div class="min-w-0 flex-1 space-y-1">
                        <label class="block font-bold text-gray-800 text-xs">Foto de Perfil</label>
                        <p class="text-[11px] text-gray-500">Se mostrará en la barra superior del CRM. Formatos: JPG, PNG o WEBP (máx. 5MB).</p>

                        <div class="flex items-center gap-2 pt-1">
                            <label class="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-white border border-gray-300 text-xs font-semibold text-gray-700 hover:bg-gray-100 shadow-2xs transition active:scale-95">
                                <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.827 6.175A2.31 2.31 0 0 1 5.186 7.23c-.38.054-.757.112-1.134.175C2.999 7.58 2.25 8.507 2.25 9.574V18a2.25 2.25 0 0 0 2.25 2.25h15A2.25 2.25 0 0 0 21.75 18V9.574c0-1.067-.75-1.994-1.802-2.169a47.865 47.865 0 0 0-1.134-.175 2.31 2.31 0 0 1-1.64-1.055l-.822-1.316a2.192 2.192 0 0 0-1.736-1.039 48.774 48.774 0 0 0-5.232 0 2.192 2.192 0 0 0-1.736 1.039l-.821 1.316Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 12.75a4.5 4.5 0 1 1-9 0 4.5 4.5 0 0 1 9 0ZM18.75 10.5h.008v.008h-.008V10.5Z" />
                                </svg>
                                <span>Subir Foto</span>
                                <input type="file"
                                       name="foto_perfil"
                                       x-ref="fotoInput"
                                       accept="image/png,image/jpeg,image/webp,image/gif"
                                       @change="seleccionarFoto($event)"
                                       class="sr-only">
                            </label>

                            <button type="button"
                                    x-show="previewUrl || (fotoActual && !eliminarFoto)"
                                    @click="quitarFoto()"
                                    class="inline-flex items-center gap-1 text-[11px] font-semibold text-rose-600 hover:text-rose-700 transition px-2 py-1 cursor-pointer">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                </svg>
                                <span>Quitar Foto</span>
                            </button>
                        </div>
                    </div>
                </div>

                <x-cuenta.selector-insignia :fijo="$user->rol" />

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
                    <label class="block font-semibold text-gray-700 mb-1">DNI / Documento de Identidad</label>
                    <input type="text"
                           name="dni"
                           value="{{ old('dni', $user->dni) }}"
                           placeholder="8 dígitos"
                           maxlength="15"
                           class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Cargo / Puesto</label>
                    <input type="text"
                           name="cargo"
                           value="{{ old('cargo', $user->cargo) }}"
                           placeholder="Ej. Asistente Contable, Titular, etc."
                           class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1 flex items-center justify-between">
                        <span>Fecha de Cumpleaños / Nacimiento</span>
                        <span class="text-brand-600 font-normal">🎂 Experiencia CRM</span>
                    </label>
                    <input type="date"
                           name="fecha_nacimiento"
                           value="{{ old('fecha_nacimiento', $user->fecha_nacimiento?->format('Y-m-d')) }}"
                           class="w-full rounded-xl border border-gray-300 px-3.5 py-2.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:ring-brand-500">
                </div>

                <div>
                    <label class="block font-semibold text-gray-700 mb-1">Dirección Domiciliaria</label>
                    <input type="text"
                           name="direccion"
                           value="{{ old('direccion', $user->direccion) }}"
                           placeholder="Av. o Calle, Urbanización, Ciudad"
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
