<!DOCTYPE html>
<html lang="es" class="h-full bg-slate-900">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión — CRM Estudio Contable</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-full bg-slate-950 text-slate-100 flex items-center justify-center p-4 antialiased selection:bg-brand-500 selection:text-white">

    {{-- Fondo decorativo sutil --}}
    <div class="fixed inset-0 overflow-hidden pointer-events-none z-0 opacity-20">
        <div class="absolute -top-[40%] -left-[20%] w-[800px] h-[800px] rounded-full bg-brand-600/30 blur-3xl"></div>
        <div class="absolute -bottom-[30%] -right-[20%] w-[700px] h-[700px] rounded-full bg-indigo-600/20 blur-3xl"></div>
    </div>

    <div class="relative z-10 w-full max-w-md">

        {{-- Encabezado con Logotipo --}}
        <div class="text-center mb-8">
            <div class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-600 text-white font-black text-2xl shadow-xl shadow-brand-500/25 mb-4 ring-4 ring-brand-500/10">
                EC
            </div>
            <h1 class="text-2xl font-black tracking-tight text-white sm:text-3xl">Estudio Contable</h1>
            <p class="text-sm font-medium text-slate-400 mt-1">Plataforma Integral de Gestión & CRM Tributario</p>
        </div>

        {{-- Tarjeta Principal de Inicio de Sesión --}}
        <div class="rounded-2xl border border-slate-800 bg-slate-900/90 p-7 shadow-2xl backdrop-blur-xl sm:p-8">

            <div class="mb-6">
                <h2 class="text-lg font-bold text-white">Iniciar Sesión</h2>
                <p class="text-xs text-slate-400 mt-0.5">Ingrese sus credenciales de acceso asignadas</p>
            </div>

            {{-- Errores de Validación o Autenticación --}}
            @if ($errors->any())
                <div class="mb-5 rounded-xl border border-rose-500/30 bg-rose-500/10 p-4 text-xs text-rose-300">
                    <div class="flex items-start gap-2.5">
                        <svg class="h-4 w-4 shrink-0 text-rose-400 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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

            <form action="{{ route('login.post') }}" method="POST" class="space-y-5" x-data="{ showPass: false }">
                @csrf

                {{-- Campo Correo Electrónico --}}
                <div>
                    <label for="email" class="block text-xs font-semibold uppercase tracking-wider text-slate-300 mb-1.5">
                        Correo Electrónico
                    </label>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
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
                               placeholder="admin@estudio.com"
                               class="w-full rounded-xl border border-slate-700 bg-slate-800/80 py-2.5 pl-10 pr-3.5 text-sm text-white placeholder-slate-500 transition focus:border-brand-500 focus:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                    </div>
                </div>

                {{-- Campo Contraseña --}}
                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label for="password" class="block text-xs font-semibold uppercase tracking-wider text-slate-300">
                            Contraseña
                        </label>
                    </div>
                    <div class="relative">
                        <div class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3.5 text-slate-500">
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
                               class="w-full rounded-xl border border-slate-700 bg-slate-800/80 py-2.5 pl-10 pr-10 text-sm text-white placeholder-slate-500 transition focus:border-brand-500 focus:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                        <button type="button"
                                @click="showPass = !showPass"
                                class="absolute inset-y-0 right-0 flex items-center pr-3 text-slate-500 hover:text-slate-300 focus:outline-none"
                                title="Mostrar / ocultar contraseña">
                            <svg x-show="!showPass" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <svg x-show="showPass" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Recordar Sesión --}}
                <div class="flex items-center justify-between text-xs">
                    <label for="remember" class="flex items-center gap-2 cursor-pointer select-none text-slate-300 hover:text-white transition">
                        <input type="checkbox"
                               id="remember"
                               name="remember"
                               value="1"
                               @checked(old('remember', $rememberDevice ?? false))
                               class="h-4 w-4 rounded border-slate-700 bg-slate-800 text-brand-600 focus:ring-brand-500 focus:ring-offset-slate-900">
                        <span>Recordar sesión en este equipo</span>
                    </label>
                </div>

                {{-- Botón de Acción --}}
                <div class="pt-1">
                    <button type="submit"
                            class="group relative flex w-full items-center justify-center gap-2 rounded-xl bg-brand-600 py-3 px-4 text-sm font-bold text-white shadow-lg shadow-brand-600/30 transition duration-150 hover:bg-brand-500 hover:shadow-brand-500/40 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 focus:ring-offset-slate-900 active:scale-[0.99]">
                        <span>Ingresar al Sistema</span>
                        <svg class="h-4 w-4 transition duration-150 group-hover:translate-x-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </button>
                </div>
            </form>

            {{-- Pie de tarjeta / Roles --}}
            <div class="mt-6 pt-5 border-t border-slate-800/80 flex items-center justify-center gap-4 text-[11px] text-slate-500 font-medium">
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Administradores
                </span>
                <span>•</span>
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                    Asistentes Contables
                </span>
            </div>
        </div>

        {{-- Pie de Página Seguro --}}
        <div class="mt-6 text-center text-xs text-slate-500">
            <p>© {{ date('Y') }} Estudio Contable · Plataforma Multitenant LAN & Cloud</p>
        </div>
    </div>
</body>
</html>
