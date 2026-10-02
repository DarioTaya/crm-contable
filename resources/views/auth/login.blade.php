<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acceso al Sistema — {{ $estudio?->nombreParaMostrar() ?? 'DeContador CRM' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-950 text-slate-900 antialiased selection:bg-brand-500 selection:text-white"
      x-data="{ showPass: false, modalRegistro: false }">

    @php
        $estudio = $estudio ?? \App\Models\Estudio::first();
        $estudioNombre = $estudio ? $estudio->nombreParaMostrar() : 'DeContador';
        $estudioLogoUrl = $estudio?->logoUrl();
        $estudioIsotipoUrl = $estudio?->isotipoUrl();
        $estudioIniciales = $estudio ? $estudio->iniciales() : 'DC';
        $sitioWeb = $estudio?->sitio_web ?: 'https://decontador.com';
        $sitioWebHost = parse_url($sitioWeb, PHP_URL_HOST) ?: 'decontador.com';
    @endphp

    <div class="min-h-full flex flex-col lg:flex-row">

        {{-- ═════════════════════════════════════════════════════════════════════════
             COLUMNA IZQUIERDA: SHOWCASE PREMIUM SAAS - NÚCLEO OPERATIVO CONTABLE
             ═════════════════════════════════════════════════════════════════════════ --}}
        <div class="relative flex-1 bg-gradient-to-br from-slate-950 via-slate-900 to-brand-950 text-white flex flex-col justify-between p-8 sm:p-12 lg:p-16 overflow-hidden border-b lg:border-b-0 lg:border-r border-slate-800/80">

            {{-- Resplandores y Mallas Ambientales de Alta Gama --}}
            <div class="pointer-events-none absolute -top-48 -left-48 w-[500px] h-[500px] rounded-full bg-brand-600/15 blur-[120px]"></div>
            <div class="pointer-events-none absolute top-1/2 -right-40 w-96 h-96 rounded-full bg-blue-500/10 blur-[100px]"></div>
            <div class="pointer-events-none absolute -bottom-40 left-1/4 w-[450px] h-[450px] rounded-full bg-indigo-600/15 blur-[120px]"></div>

            {{-- Cabecera de Marca --}}
            <div class="relative z-10 flex items-center justify-between">
                <div class="flex items-center gap-3.5">
                    @if ($estudioLogoUrl)
                        <img src="{{ $estudioLogoUrl }}" alt="{{ $estudioNombre }}" class="h-9 w-auto max-w-[210px] object-contain">
                    @elseif ($estudioIsotipoUrl)
                        <div class="flex h-11 w-11 items-center justify-center rounded-xl bg-white/10 p-1.5 shadow-md border border-white/15">
                            <img src="{{ $estudioIsotipoUrl }}" alt="{{ $estudioNombre }}" class="h-full w-full object-contain rounded">
                        </div>
                        <span class="text-xl font-black tracking-tight text-white">{{ $estudioNombre }}</span>
                    @else
                        <div class="inline-flex h-11 w-11 items-center justify-center rounded-xl bg-brand-600 text-white font-black text-lg shadow-lg shadow-brand-500/30 ring-2 ring-white/10">
                            {{ $estudioIniciales }}
                        </div>
                        <span class="text-xl font-black tracking-tight text-white">{{ $estudioNombre }}</span>
                    @endif
                </div>

                <div class="hidden sm:inline-flex items-center gap-1.5 rounded-full border border-white/10 bg-white/5 px-3 py-1 text-[11px] font-semibold text-slate-300 backdrop-blur-xs">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                    <span>Consola Estudio v1.0</span>
                </div>
            </div>

            {{-- Bloque Central: Propuesta de Valor para el Contador Profesional --}}
            <div class="relative z-10 my-10 lg:my-auto max-w-xl space-y-7">
                <div class="space-y-3.5">
                    <div class="inline-flex items-center gap-2 rounded-full border border-brand-500/30 bg-brand-500/10 px-3.5 py-1 text-xs font-semibold text-brand-300">
                        <svg class="h-3.5 w-3.5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 13.5l10.5-11.25L12 10.5h8.25L9.75 21.75 12 13.5H3.75z" />
                        </svg>
                        <span>Sistema Operativo de Gestión & Auditoría Tributaria</span>
                    </div>

                    <h1 class="text-3xl sm:text-4xl lg:text-[2.75rem] font-extrabold tracking-tight text-white leading-[1.12]">
                        El estándar de control inteligente para tu <span class="text-transparent bg-clip-text bg-gradient-to-r from-brand-300 via-blue-200 to-indigo-200">estudio contable.</span>
                    </h1>

                    <p class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-lg">
                        Centraliza la supervisión tributaria de toda tu cartera: alertas preventivas de Buzón SOL, conciliación documental SIRE y liquidaciones de impuestos con precisión fiduciaria.
                    </p>
                </div>

                {{-- Consola de Telemetría Operativa (HUD Contable) --}}
                <div class="rounded-2xl border border-white/10 bg-slate-900/60 p-5 backdrop-blur-xl shadow-2xl space-y-4">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <div class="flex items-center gap-2.5">
                            <span class="relative flex h-2.5 w-2.5">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-emerald-500"></span>
                            </span>
                            <span class="text-xs font-bold tracking-wide uppercase text-slate-200">Consola Operativa en Tiempo Real</span>
                        </div>
                        <span class="text-[11px] font-mono font-medium text-emerald-400 bg-emerald-500/10 px-2 py-0.5 rounded border border-emerald-500/20">
                            Sincronizado
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        {{-- Métrica 1: Buzón SOL --}}
                        <div class="rounded-xl border border-white/5 bg-white/[0.03] p-3 space-y-1">
                            <p class="text-[11px] font-medium text-slate-400 flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-brand-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                                Buzón Electrónico
                            </p>
                            <p class="text-base font-extrabold text-white tabular-nums">
                                {{ ($totalAlertas ?? 0) > 0 ? $totalAlertas . ' alertas' : 'Sin pendientes' }}
                            </p>
                            <p class="text-[10px] text-slate-400 truncate">SUNAT & SUNAFIL</p>
                        </div>

                        {{-- Métrica 2: Cartera Activa --}}
                        <div class="rounded-xl border border-white/5 bg-white/[0.03] p-3 space-y-1">
                            <p class="text-[11px] font-medium text-slate-400 flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-emerald-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Z" />
                                </svg>
                                Empresas
                            </p>
                            <p class="text-base font-extrabold text-white tabular-nums">
                                {{ ($totalClientes ?? 0) > 0 ? $totalClientes . ' clientes' : 'Padrón activo' }}
                            </p>
                            <p class="text-[10px] text-slate-400 truncate">RMT y RG</p>
                        </div>

                        {{-- Métrica 3: Integración SIRE --}}
                        <div class="rounded-xl border border-white/5 bg-white/[0.03] p-3 space-y-1">
                            <p class="text-[11px] font-medium text-slate-400 flex items-center gap-1.5">
                                <svg class="h-3.5 w-3.5 text-blue-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                                </svg>
                                Bóveda SIRE
                            </p>
                            <p class="text-base font-extrabold text-white tabular-nums">
                                RVIE / RCE
                            </p>
                            <p class="text-[10px] text-slate-400 truncate">Conciliación XML</p>
                        </div>
                    </div>

                    {{-- Chip de Seguridad Fiduciaria --}}
                    <div class="pt-1 flex items-center gap-2 text-[11px] text-slate-400 border-t border-white/5">
                        <svg class="h-3.5 w-3.5 text-brand-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <span>Credenciales SOL y confidencialidad protegidas mediante cifrado AES-256</span>
                    </div>
                </div>
            </div>

            {{-- Pie de Columna Izquierda: Sello Institucional --}}
            <div class="relative z-10 pt-6 border-t border-white/10 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-xs text-slate-400">
                <p>
                    Plataforma especializada para profesionales contables colegiados.
                </p>
                <p class="font-medium text-slate-300">
                    {{ $estudioNombre }} &copy; {{ date('Y') }}
                </p>
            </div>
        </div>

        {{-- ═════════════════════════════════════════════════════════════════════════
             COLUMNA DERECHA: CONSOLA DE ACCESO PROFESIONAL
             ═════════════════════════════════════════════════════════════════════════ --}}
        <div class="flex-1 bg-white flex items-center justify-center p-6 sm:p-12 lg:p-16">
            <div class="w-full max-w-md space-y-6">

                {{-- Barra Superior de Conexión y Navegación --}}
                <div class="flex items-center justify-between text-xs">
                    <a href="{{ $sitioWeb }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="inline-flex items-center gap-1.5 font-medium text-slate-500 hover:text-brand-600 transition">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                        </svg>
                        <span>Visitar portal web</span>
                    </a>

                    <span class="inline-flex items-center gap-1.5 text-[11px] font-medium text-emerald-700 bg-emerald-50 px-2.5 py-1 rounded-full border border-emerald-200">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                        Estación Segura TLS
                    </span>
                </div>

                {{-- Título y Propósito --}}
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
                        Iniciar Sesión
                    </h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">
                        Ingresa tus credenciales autorizadas para acceder al estudio.
                    </p>
                </div>

                {{-- Pestañas de Contexto de Acceso --}}
                <div class="grid grid-cols-2 gap-1 rounded-xl bg-slate-100 p-1 text-xs font-semibold select-none">
                    <button type="button"
                            class="rounded-lg bg-white py-2 text-center text-slate-900 shadow-xs cursor-default">
                        Acceso Autorizado
                    </button>
                    <button type="button"
                            @click="modalRegistro = true"
                            class="rounded-lg py-2 text-center text-slate-500 hover:text-slate-800 transition cursor-pointer">
                        Personal del Estudio
                    </button>
                </div>

                {{-- Notificación de Estado / Éxito --}}
                @if (session('status'))
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-50 p-4 text-xs font-medium text-emerald-800 flex items-start gap-2.5">
                        <svg class="h-4 w-4 shrink-0 text-emerald-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

                @if (session('success'))
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-50 p-4 text-xs font-medium text-emerald-800 flex items-start gap-2.5">
                        <svg class="h-4 w-4 shrink-0 text-emerald-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>{{ session('success') }}</span>
                    </div>
                @endif

                {{-- Errores de Validación o Autenticación --}}
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

                {{-- Formulario Principal de Acceso --}}
                <form action="{{ route('login.post') }}" method="POST" class="space-y-4">
                    @csrf

                    {{-- Campo: Correo Electrónico --}}
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
                                   value="{{ old('email', $rememberedEmail ?? '') }}"
                                   required
                                   autofocus
                                   autocomplete="username"
                                   placeholder="usuario@estudio.pe"
                                   class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        </div>
                    </div>

                    {{-- Campo: Contraseña con Ojito Interactivo --}}
                    <div>
                        <div class="flex items-center justify-between mb-1.5">
                            <label for="password" class="block text-xs font-semibold text-slate-700">
                                Contraseña
                            </label>
                        </div>
                        <div class="relative">
                            <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-400">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                            </div>
                            <input :type="showPass ? 'text' : 'password'"
                                   id="password"
                                   name="password"
                                   value="{{ old('password', $rememberedPassword ?? '') }}"
                                   required
                                   autocomplete="current-password"
                                   placeholder="••••••••••••"
                                   class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-11 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">

                            {{-- Botón con Ojito (Icono de Ojo Abierto / Ojo Tachado) --}}
                            <button type="button"
                                    @click="showPass = !showPass"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3.5 text-slate-400 hover:text-slate-700 focus:outline-none cursor-pointer"
                                    :title="showPass ? 'Ocultar contraseña' : 'Ver contraseña'"
                                    aria-label="Alternar visibilidad de contraseña">
                                {{-- Ojo Cerrado / Tachado (cuando la clave está visible y se quiere ocultar) --}}
                                <svg x-show="showPass" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                </svg>
                                {{-- Ojo Normal (cuando la clave está oculta y se quiere ver) --}}
                                <svg x-show="!showPass" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                            </button>
                        </div>
                    </div>

                    {{-- Fila: Recordarme en este equipo & ¿Olvidaste tu contraseña? --}}
                    <div class="flex items-center justify-between pt-1">
                        <label for="remember" class="flex items-center gap-2 cursor-pointer select-none text-xs text-slate-600 hover:text-slate-900 transition">
                            <input type="checkbox"
                                   id="remember"
                                   name="remember"
                                   value="1"
                                   @checked(old('remember', $rememberDevice ?? false))
                                   class="h-4 w-4 rounded border-slate-300 bg-white text-brand-600 focus:ring-brand-500">
                            <span>Recordar sesión en este equipo</span>
                        </label>

                        <a href="{{ route('password.request') }}"
                           class="text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline transition">
                            ¿Olvidaste tu contraseña?
                        </a>
                    </div>

                    {{-- Botón Principal de Acceso --}}
                    <div class="pt-2">
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 rounded-xl bg-brand-600 py-3 px-4 text-sm font-bold text-white shadow-lg shadow-brand-600/25 transition duration-150 hover:bg-brand-700 hover:shadow-brand-700/35 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:scale-[0.99] cursor-pointer">
                            <span>Ingresar al Sistema</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </button>
                    </div>
                </form>

                {{-- Separador de Ámbito --}}
                <div class="relative py-1">
                    <div class="absolute inset-0 flex items-center">
                        <div class="w-full border-t border-slate-200"></div>
                    </div>
                    <div class="relative flex justify-center text-[11px] uppercase tracking-wider font-semibold">
                        <span class="bg-white px-3 text-slate-400">Control de Acceso Asignado</span>
                    </div>
                </div>

                {{-- Indicador de Roles Operativos --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-3 flex items-center justify-between text-xs text-slate-600">
                    <span class="inline-flex items-center gap-1.5 font-medium">
                        <span class="h-2 w-2 rounded-full bg-purple-500"></span>
                        Administradores
                    </span>
                    <span class="text-slate-300">&bull;</span>
                    <span class="inline-flex items-center gap-1.5 font-medium">
                        <span class="h-2 w-2 rounded-full bg-brand-500"></span>
                        Asistentes Contables
                    </span>
                    <span class="text-slate-300">&bull;</span>
                    <span class="inline-flex items-center gap-1.5 font-medium">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        Auditores
                    </span>
                </div>

                {{-- Enlace Informativo de Alta de Usuarios --}}
                <div class="text-center text-xs text-slate-500">
                    <span>¿Formas parte del equipo y no tienes credenciales?</span>
                    <button type="button"
                            @click="modalRegistro = true"
                            class="font-semibold text-brand-600 hover:text-brand-700 hover:underline ml-1 cursor-pointer">
                        Consulta cómo obtener acceso
                    </button>
                </div>

                {{-- Distintivo Inferior de Seguridad --}}
                <div class="pt-2 text-center text-xs text-slate-400 flex items-center justify-center gap-1.5">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <span>Conexión cifrada &middot; secreto profesional y confidencialidad fiscal resguardados</span>
                </div>

            </div>
        </div>

    </div>

    {{-- Modal Informativo de Creación de Cuentas --}}
    <div x-show="modalRegistro"
         x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/70 backdrop-blur-xs"
         @keydown.escape.window="modalRegistro = false">
        <div @click.outside="modalRegistro = false"
             class="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl border border-slate-200 space-y-4">
            <div class="flex items-center justify-between border-b border-slate-100 pb-3">
                <div class="flex items-center gap-2">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-brand-50 text-brand-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z" />
                        </svg>
                    </div>
                    <h3 class="font-bold text-slate-900 text-sm">Alta de Usuarios en el Estudio</h3>
                </div>
                <button type="button" @click="modalRegistro = false" class="text-slate-400 hover:text-slate-600 cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="text-xs text-slate-600 leading-relaxed space-y-2.5">
                <p>
                    Por políticas de seguridad, reserva fiduciaria y asignación de cartera en <strong>{{ $estudioNombre }}</strong>, las cuentas de asistentes no son de libre registro público.
                </p>
                <div class="rounded-xl bg-slate-50 border border-slate-200 p-3 space-y-1 text-slate-700">
                    <p class="font-semibold text-slate-900">¿Cómo se activa un nuevo asistente?</p>
                    <p class="text-[11px] text-slate-500">El Titular o Administrador del estudio registra tu cuenta desde el panel de Equipo, asignándote los clientes y permisos pertinentes.</p>
                </div>
                @if ($estudio?->correo)
                    <p class="text-[11px] text-slate-500">
                        Canal de coordinación: <a href="mailto:{{ $estudio->correo }}" class="font-semibold text-brand-600 hover:underline">{{ $estudio->correo }}</a>
                    </p>
                @endif
            </div>

            <div class="pt-2">
                <button type="button"
                        @click="modalRegistro = false"
                        class="w-full rounded-xl bg-slate-900 py-2.5 text-xs font-bold text-white hover:bg-slate-800 transition cursor-pointer">
                    Comprendido
                </button>
            </div>
        </div>
    </div>

</body>
</html>
