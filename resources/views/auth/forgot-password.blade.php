<!DOCTYPE html>
<html lang="es" class="h-full bg-sidebar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recuperar Acceso — {{ $estudio?->nombreParaMostrar() ?? 'DeContador CRM' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <x-marca.variables />
</head>
<body class="h-full bg-sidebar text-slate-900 antialiased selection:bg-sidebar selection:text-white">

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
             COLUMNA IZQUIERDA: SHOWCASE SAAS - BLINDAJE & SEGURIDAD FISCAL
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
                    <span>Protocolo de Recuperación</span>
                </div>
            </div>

            {{-- Bloque Central --}}
            <div class="relative z-10 my-10 lg:my-auto max-w-xl space-y-6">
                <div class="space-y-3.5">
                    <div class="inline-flex items-center gap-2 rounded-full border border-brand-500/30 bg-brand-500/10 px-3.5 py-1 text-xs font-semibold text-brand-300">
                        <svg class="h-3.5 w-3.5 text-brand-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                        </svg>
                        <span>Validación Criptográfica de Identidad</span>
                    </div>

                    <h1 class="text-3xl sm:text-4xl lg:text-[2.75rem] font-extrabold tracking-tight text-white leading-[1.12]">
                        Recuperación de acceso al <span class="text-brand-300">sistema contable.</span>
                    </h1>

                    <p class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-lg">
                        El resguardo de credenciales SOL, estados financieros y la reserva tributaria de los clientes exige una estricta validación. Te asistimos en la reanudación segura de tu sesión.
                    </p>
                </div>

                {{-- Tarjeta de Parámetros de Seguridad --}}
                <div class="rounded-2xl border border-white/10 bg-slate-900/60 p-5 backdrop-blur-xl shadow-2xl space-y-3.5">
                    <div class="flex items-center justify-between border-b border-white/10 pb-3">
                        <span class="text-xs font-bold tracking-wide uppercase text-slate-200">Garantías de Seguridad</span>
                        <span class="text-[11px] font-mono text-emerald-400">Rate Limiter Activo</span>
                    </div>

                    <ul class="space-y-2.5 text-xs text-slate-300">
                        <li class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500/20 text-brand-400 text-[10px] font-bold shrink-0">✓</span>
                            <span>Token temporal firmado con expiración a los 60 minutos</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500/20 text-brand-400 text-[10px] font-bold shrink-0">✓</span>
                            <span>Protección contra bombardeo de correos y fuerza bruta</span>
                        </li>
                        <li class="flex items-center gap-2.5">
                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-brand-500/20 text-brand-400 text-[10px] font-bold shrink-0">✓</span>
                            <span>Despacho exclusivo a cuentas verificadas en el estudio</span>
                        </li>
                    </ul>
                </div>
            </div>

            {{-- Pie Institucional --}}
            <div class="relative z-10 pt-6 border-t border-white/10 text-xs text-slate-400 flex items-center justify-between">
                <span>Plataforma de gestión tributaria profesional.</span>
                <span class="text-slate-300 font-medium">{{ $estudioNombre }}</span>
            </div>
        </div>

        {{-- ═════════════════════════════════════════════════════════════════════════
             COLUMNA DERECHA: FORMULARIO DE SOLICITUD
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
                        <span>Volver al inicio de sesión</span>
                    </a>
                </div>

                {{-- Título y Subtítulo --}}
                <div>
                    <h2 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-slate-900">
                        ¿Olvidaste tu contraseña?
                    </h2>
                    <p class="text-sm font-medium text-slate-500 mt-1">
                        Ingresa el correo electrónico asignado en el estudio para enviarte un enlace de recuperación seguro.
                    </p>
                </div>

                {{-- Estado de Envío Satisfactorio --}}
                @if (session('status'))
                    <div class="rounded-xl border border-emerald-500/30 bg-emerald-50 p-4 text-xs font-medium text-emerald-800 flex items-start gap-2.5">
                        <svg class="h-4 w-4 shrink-0 text-emerald-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>{{ session('status') }}</span>
                    </div>
                @endif

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

                {{-- Formulario de Solicitud --}}
                <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
                    @csrf

                    <div>
                        <label for="email" class="block text-xs font-semibold text-slate-700 mb-1.5">
                            Correo corporativo registrado
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
                                   value="{{ old('email') }}"
                                   required
                                   autofocus
                                   autocomplete="email"
                                   placeholder="usuario@estudio.pe"
                                   class="w-full rounded-xl border border-slate-300 bg-white py-2.5 pl-10 pr-3.5 text-sm text-slate-900 placeholder-slate-400 transition focus:border-brand-600 focus:bg-white focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        </div>
                    </div>

                    <div class="pt-2">
                        <button type="submit"
                                class="w-full flex items-center justify-center gap-2 rounded-xl bg-brand-600 py-3 px-4 text-sm font-bold text-white shadow-lg shadow-brand-600/25 transition duration-150 hover:bg-brand-700 hover:shadow-brand-700/35 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 active:scale-[0.99] cursor-pointer">
                            <span>Enviar enlace de recuperación</span>
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 12 3.269 3.125A59.769 59.769 0 0 1 21.485 12 59.768 59.768 0 0 1 3.27 20.875L5.999 12Zm0 0h7.5" />
                            </svg>
                        </button>
                    </div>
                </form>

                {{-- Recordatorio de Soporte Interno --}}
                <div class="rounded-xl border border-slate-200 bg-slate-50/80 p-4 text-xs text-slate-600 space-y-1">
                    <p class="font-semibold text-slate-900">¿No tienes acceso a tu buzón de correo?</p>
                    <p class="text-slate-500 leading-relaxed">
                        El Administrador del estudio puede actualizar o restablecer tu contraseña directamente desde la sección de <em>Equipo y Usuarios</em>.
                    </p>
                </div>

                {{-- Distintivo Inferior --}}
                <div class="pt-2 text-center text-xs text-slate-400 flex items-center justify-center gap-1.5">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <span>Conexión cifrada &middot; tus datos fiscales están protegidos</span>
                </div>

            </div>
        </div>

    </div>

</body>
</html>
