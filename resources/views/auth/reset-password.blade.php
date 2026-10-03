<!DOCTYPE html>
<html lang="es" class="h-full bg-sidebar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Establecer Nueva Contraseña — {{ $estudio?->nombreParaMostrar() ?? 'DeContador CRM' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-marca.variables />
</head>
<body class="h-full bg-sidebar text-slate-900 antialiased selection:bg-sidebar selection:text-white"
      x-data="{ showPass: false, showConfirm: false }">

    @php
        $estudio = $estudio ?? \App\Models\Estudio::first();
        $estudioNombre = $estudio ? $estudio->nombreParaMostrar() : 'DeContador';
        $estudioLogoUrl = $estudio?->logoUrl();
        $estudioIsotipoUrl = $estudio?->isotipoUrl();
        $estudioIniciales = $estudio ? $estudio->iniciales() : 'DC';
        $sitioWeb = $estudio?->sitio_web ?: 'https://decontador.com';
    @endphp

    <div class="min-h-full flex flex-col lg:flex-row">

        {{-- ═════════════════════════════════════════════════════════════════════════
             COLUMNA IZQUIERDA: SHOWCASE SAAS - ESTÁNDARES DE SEGURIDAD CONTABLE
             ═════════════════════════════════════════════════════════════════════════ --}}
        <div class="relative flex-1 bg-sidebar text-white flex flex-col justify-between p-8 sm:p-12 lg:p-16 overflow-hidden border-b lg:border-b-0 lg:border-r border-white/10">

            <div class="pointer-events-none absolute -top-48 -left-48 w-[420px] h-[420px] rounded-full bg-brand-500/10 blur-[120px]"></div>

            {{-- Cabecera con Logotipo --}}
            <div class="relative z-10 flex items-center justify-between">
                <a href="{{ route('login') }}" class="inline-flex items-center gap-3.5 group">
                    @if ($estudioLogoUrl)
                        <img src="{{ $estudioLogoUrl }}" alt="{{ $estudioNombre }}" class="h-9 w-auto max-w-[210px] object-contain">
                    @elseif ($estudioIsotipoUrl)
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 p-1.5 shadow-md border border-white/15">
                            <img src="{{ $estudioIsotipoUrl }}" alt="{{ $estudioNombre }}" class="h-full w-full object-contain rounded">
                        </div>
                        <span class="text-xl font-black tracking-tight text-white group-hover:text-brand-300 transition">{{ $estudioNombre }}</span>
                    @else
                        <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-white font-black text-lg shadow-lg shadow-brand-500/30 ring-2 ring-white/10">
                            {{ $estudioIniciales }}
                        </div>
                        <span class="text-xl font-black tracking-tight text-white group-hover:text-brand-300 transition">{{ $estudioNombre }}</span>
                    @endif
                </a>

                <div class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold text-slate-300 backdrop-blur-xs">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                    <span>Canal Seguro Cifrado</span>
                </div>
            </div>

            {{-- Bloque Central --}}
            <div class="relative z-10 my-10 lg:my-auto max-w-xl space-y-6">
                <div class="space-y-3.5">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3.5 py-1 text-xs font-semibold text-emerald-300">
                        <svg class="h-3.5 w-3.5 text-emerald-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                        <span>Protección de Identidad & Auditoría Fiscal</span>
                    </div>

                    <h1 class="text-3xl sm:text-4xl lg:text-[2.75rem] font-extrabold tracking-tight text-white leading-[1.12]">
                        Restablecimiento seguro de <span class="text-brand-300">credenciales.</span>
                    </h1>

                    <p class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-lg">
                        Tu usuario administra expedientes tributarios, credenciales SOL y libros contables oficiales. Define una contraseña robusta para mantener blindada la cartera del estudio.
                    </p>
                </div>

                {{-- Tarjeta de Estándares de Seguridad --}}
                <div class="rounded-2xl border border-white/10 bg-slate-900/60 p-5 backdrop-blur-xl shadow-2xl space-y-3.5">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <span class="text-xs font-bold tracking-wide uppercase text-slate-200">Requerimientos de Seguridad</span>
                        <span class="text-[11px] text-slate-400">Algoritmo Bcrypt + AES-256</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-300">
                        <li class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold shrink-0">✓</span>
                            <span>Mínimo 6 caracteres alfanuméricos</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold shrink-0">✓</span>
                            <span>Evita números de RUC de clientes o fechas de cumpleaños</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-emerald-500/20 text-emerald-400 text-[10px] font-bold shrink-0">✓</span>
                            <span>Cierre automático de sesiones previas en otros dispositivos</span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Pie Institucional --}}
            <div class="relative z-10 pt-6 border-t border-white/10 text-xs text-slate-400 flex items-center justify-between">
                <span>Resguardo de secreto tributario y fiscal.</span>
                <span class="text-slate-300 font-medium">{{ $estudioNombre }}</span>
            </div>
        </div>

        {{-- ═════════════════════════════════════════════════════════════════════════
             COLUMNA DERECHA: FORMULARIO DE NUEVA CONTRASEÑA
             ═════════════════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 bg-white flex items-center justify-center p-6 sm:p-12 lg:p-16">
            <div class="w-full max-w-md space-y-6">

                {{-- Enlace Volver a Login --}}
                <div>
                    <a href="{{ route('login') }}"
                       class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-500 hover:text-brand-600 transition">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        <span>Volver a iniciar sesión</span>
                    </a>
                </div>

                {{-- Título y Subtítulo --}}
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
                        Restablecer tu contraseña
                    </h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">
                        Establece tu nueva contraseña de acceso al sistema del estudio.
                    </p>
                </div>

                {{-- Errores de Validación --}}
                @if ($errors->any())
                    <div class="rounded-xl border border-rose-500/30 bg-rose-50 p-4 text-xs text-rose-700">
                        <div class="flex items-start gap-2.5">
                            <svg class="h-4 w-4 shrink-0 text-rose-500 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                            </svg>
                            <div class="space-y-1">
                                @foreach ($errors->all() as $error)
                                    <p class="font-medium">{{ $error }}</p>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                {{-- Formulario de Actualización --}}
                <form action="{{ route('password.update') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="token" value="{{ $token }}">

                    {{-- Correo Electrónico --}}
                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Correo electrónico
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                            </div>
                            <input type="email"
                                   id="email"
                                   name="email"
                                   value="{{ old('email', $email) }}"
                                   required
                                   autocomplete="email"
                                   placeholder="usuario@estudio.pe"
                                   class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        </div>
                    </div>

                    {{-- Nueva Contraseña con Ojito --}}
                    <div>
                        <label for="password" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Nueva contraseña
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input :type="showPass ? 'text' : 'password'"
                                   id="password"
                                   name="password"
                                   required
                                   autofocus
                                   autocomplete="new-password"
                                   placeholder="Mínimo 6 caracteres"
                                   class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-11 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">

                            {{-- Botón con Ojito --}}
                            <button type="button"
                                    @click="showPass = !showPass"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-700 focus:outline-none cursor-pointer"
                                    :title="showPass ? 'Ocultar contraseña' : 'Ver contraseña'"
                                    aria-label="Alternar visibilidad de contraseña">
                                <svg x-show="showPass" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <svg x-show="!showPass" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Confirmar Contraseña con Ojito --}}
                    <div>
                        <label for="password_confirmation" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Confirmar nueva contraseña
                        </label>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input :type="showConfirm ? 'text' : 'password'"
                                   id="password_confirmation"
                                   name="password_confirmation"
                                   required
                                   autocomplete="new-password"
                                   placeholder="Vuelve a escribir la contraseña"
                                   class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-11 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">

                            {{-- Botón con Ojito --}}
                            <button type="button"
                                    @click="showConfirm = !showConfirm"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-700 focus:outline-none cursor-pointer"
                                    :title="showConfirm ? 'Ocultar confirmación' : 'Ver confirmación'"
                                    aria-label="Alternar visibilidad de confirmación">
                                <svg x-show="showConfirm" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                <svg x-show="!showConfirm" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Botón Guardar --}}
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 rounded-xl bg-brand-600 py-3 px-4 text-sm font-bold text-white shadow-lg shadow-brand-600/25 transition duration-150 hover:bg-brand-700 hover:shadow-brand-700/35 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:scale-[0.99] cursor-pointer">
                            <span>Guardar nueva contraseña</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </button>
                    </div>
                </form>

                {{-- Distintivo de Seguridad Inferior --}}
                <div class="pt-2 text-center text-xs text-slate-400 flex items-center justify-center gap-1.5">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <span>Conexión segura &middot; tus datos fiscales están cifrados</span>
                </div>

            </div>
        </div>

    </div>

</body>
</html>
