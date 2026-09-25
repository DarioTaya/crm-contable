<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'CRM Contable') — Estudio Contable</title>
    {{-- Aplica el ancho de la barra antes del primer pintado. Sin esto, Alpine
         la anima de cero a abierta en cada carga y la página "se corre". --}}
    <script>
        try {
            if (localStorage.getItem('crm-sidebar') === 'collapsed') {
                document.documentElement.dataset.sidebar = 'collapsed';
            }
        } catch (e) {}

        // Posicionamiento inteligente y sin parpadeo para menús y popovers flotantes
        window.ubicarMenuFlotante = function (panel, ancla, intento) {
            if (!panel || !ancla || panel._reposicionPendiente) {
                return;
            }

            const margen = 16;
            const separacion = 6;
            const rect = ancla.getBoundingClientRect();
            
            // Garantizar que la altura máxima nunca exceda el alto visible de la ventana
            const maxAlto = window.innerHeight - (margen * 2);
            panel.style.maxHeight = maxAlto + 'px';

            // Si el elemento aún no tiene dimensiones en el DOM, esperar un frame sin parpadeo
            const ancho = panel.offsetWidth || 455;
            const alto = panel.offsetHeight || 380;

            if ((!panel.offsetWidth || !panel.offsetHeight) && (intento || 0) < 3) {
                panel.style.visibility = 'hidden';
                requestAnimationFrame(function () {
                    window.ubicarMenuFlotante(panel, ancla, (intento || 0) + 1);
                });
                return;
            }

            const cabeAbajo = window.innerHeight - rect.bottom >= alto + margen;
            const cabeArriba = rect.top >= alto + margen;
            const abrirArriba = !cabeAbajo && cabeArriba;
            
            let top = abrirArriba ? rect.top - alto - separacion : rect.bottom + separacion;
            let left = rect.left;

            // Ajustar horizontalmente si se desborda hacia la derecha
            if (left + ancho > window.innerWidth - margen) {
                left = rect.right - ancho;
            }
            // Asegurar que no quede pegado ni sobrepase el margen derecho de la pantalla
            if (left + ancho > window.innerWidth - margen) {
                left = window.innerWidth - ancho - margen;
            }
            // Asegurar que no quede por fuera de la izquierda
            if (left < margen) {
                left = margen;
            }
            // Asegurar que no quede por fuera arriba o abajo
            if (top < margen) {
                top = margen;
            }
            if (top + alto > window.innerHeight - margen) {
                top = Math.max(margen, window.innerHeight - alto - margen);
            }

            panel.style.top = Math.round(top) + 'px';
            panel.style.left = Math.round(left) + 'px';
            panel.style.visibility = 'visible';
            panel._anclaMenu = ancla;

            // Escuchar cambios de tamaño dinámicos (ej: al activar switches o abrir acordeones) para reajustar posición de inmediato
            if (!panel._menuResizeObserver && typeof ResizeObserver !== 'undefined') {
                panel._menuResizeObserver = new ResizeObserver(function () {
                    if (panel._reposicionPendiente) {
                        return;
                    }
                    if (panel._anclaMenu && panel.style.display !== 'none' && panel.offsetParent !== null) {
                        window.ubicarMenuFlotante(panel, panel._anclaMenu);
                    }
                });
                panel._menuResizeObserver.observe(panel);
            }
        };

        if (!window.__menuFlotanteListo) {
            window.__menuFlotanteListo = true;
            const recolocarMenus = function () {
                document.querySelectorAll('[data-menu-flotante]').forEach(function (panel) {
                    if (panel.style.display === 'none' || !panel._anclaMenu) {
                        return;
                    }
                    window.ubicarMenuFlotante(panel, panel._anclaMenu);
                });
            };
            window.addEventListener('scroll', recolocarMenus, true);
            window.addEventListener('resize', recolocarMenus);
        }
    </script>
    <style>
        @media print {
            .app-sidebar,
            header,
            .no-print,
            [data-menu-flotante] {
                display: none !important;
            }
            body, .app-main, main {
                margin: 0 !important;
                padding: 0 !important;
                background: white !important;
                width: 100% !important;
            }
            .print-only {
                display: block !important;
            }
            @page {
                margin: 1cm;
                size: landscape;
            }
        }
    </style>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="bg-gray-50 text-gray-800 antialiased">
    <div class="flex min-h-screen"
         x-data="{
             sidebarCollapsed: document.documentElement.dataset.sidebar === 'collapsed',
             toggleSidebar() {
                 this.sidebarCollapsed = !this.sidebarCollapsed;
                 document.documentElement.dataset.sidebar = this.sidebarCollapsed ? 'collapsed' : 'open';
                 document.documentElement.classList.add('sidebar-animating');
                 try {
                     localStorage.setItem('crm-sidebar', this.sidebarCollapsed ? 'collapsed' : 'open');
                 } catch (e) {}
             }
         }">

        {{-- ═══════════════════════════════════ SIDEBAR COLAPSABLE ═══════════════════════════════════ --}}
        <aside class="app-sidebar fixed inset-y-0 left-0 z-30 flex flex-col bg-sidebar text-gray-300 select-none shadow-xl">

            {{-- Logo / Marca + Botón Encoger/Expandir --}}
            <div class="flex h-16 items-center border-b border-white/10 px-4"
                 :class="sidebarCollapsed ? 'justify-center' : 'justify-between'">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white font-bold text-sm shadow-md">
                        EC
                    </div>
                    <div x-show="!sidebarCollapsed" class="truncate">
                        <span class="block text-sm font-semibold text-white leading-tight">CRM Contable</span>
                        <span class="block text-xs text-gray-400">Estudio Contable</span>
                    </div>
                </div>

                {{-- Botón Toggle de la Barra Lateral --}}
                <button type="button"
                        @click="toggleSidebar()"
                        :title="sidebarCollapsed ? 'Expandir barra lateral (Mostrar texto)' : 'Encoger barra lateral (Solo iconos)'"
                        class="flex h-8 w-8 items-center justify-center rounded-lg text-gray-400 hover:bg-sidebar-hover hover:text-white transition shrink-0 focus:outline-none"
                        :class="sidebarCollapsed ? 'mt-2 mb-1 text-gray-300 hover:text-white' : ''">
                    <svg x-show="!sidebarCollapsed" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M18.75 19.5l-7.5-7.5 7.5-7.5m-6 15L5.25 12l7.5-7.5" />
                    </svg>
                    <svg x-show="sidebarCollapsed" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M11.25 4.5l7.5 7.5-7.5 7.5m-6-15l7.5 7.5-7.5 7.5" />
                    </svg>
                </button>
            </div>

            {{-- Navegación --}}
            <nav class="mt-4 flex-1 space-y-1.5 px-3 overflow-y-auto overflow-x-hidden">
                <p x-show="!sidebarCollapsed" class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Gestión</p>
                <div x-show="sidebarCollapsed" class="my-2 border-t border-white/10 mx-1"></div>

                <a href="{{ route('dashboard') }}"
                   title="Dashboard"
                   :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('dashboard') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Home / Dashboard --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Dashboard</span>
                </a>

                <a href="{{ route('clientes.index') }}"
                   title="Clientes"
                   :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('clientes.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Users --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Clientes</span>
                </a>

                <a href="{{ route('control.index') }}"
                   title="Control Impuestos"
                   :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('control.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Calendar / Table Matrix --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-3.75h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Control Impuestos</span>
                </a>

                <a href="{{ route('control_deudas.index') }}"
                   title="Control de Deudas"
                   :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('control_deudas.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Banknote / Debt --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Control Deudas</span>
                </a>

                <a href="{{ route('balances.index') }}"
                   title="Control Balances"
                   :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('balances.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Balance Scale --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Control Balances</span>
                </a>

                <a href="{{ route('tipo-cambio.index') }}"
                   title="Tipo de Cambio"
                   :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('tipo-cambio.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Tipo de Cambio</span>
                </a>

                <a href="{{ route('alertas.index') }}"
                   title="Buzón de Alertas"
                   :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                   class="group flex items-center justify-between rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('alertas.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    <div class="flex items-center gap-3 min-w-0" :class="sidebarCollapsed ? 'justify-center' : ''">
                        {{-- Icono: Bell / Inbox --}}
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="truncate">Buzón de Alertas</span>
                    </div>

                    @if (($alertasPendientesCount ?? 0) > 0)
                        <span x-show="!sidebarCollapsed" class="inline-flex items-center justify-center rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-black text-white shadow-xs shrink-0">
                            {{ $alertasPendientesCount }}
                        </span>
                        <span x-show="sidebarCollapsed" class="absolute top-2 right-2.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-sidebar" title="{{ $alertasPendientesCount }} alertas pendientes"></span>
                    @endif
                </a>

                <a href="{{ route('comprobantes.index') }}"
                   title="Bóveda SIRE"
                   :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('comprobantes.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Archive Box / Bóveda Documental --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Bóveda SIRE</span>
                </a>

                <a href="{{ route('cpe.visor') }}"
                   title="Visor XML / Generador CPE"
                   :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                   class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('cpe.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Document Magnifying Glass / Printer --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span x-show="!sidebarCollapsed" class="truncate">Visor XML / CPE</span>
                </a>
            </nav>

            {{-- Sección de Configuración / Sistema separada --}}
            <div class="px-3 pb-2">
                <div class="border-t border-white/10 pt-3">
                    <p x-show="!sidebarCollapsed" class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Configuración</p>
                    <a href="{{ route('cronogramas.index') }}"
                       title="Cronograma SUNAT"
                       :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                       class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar
                              {{ request()->routeIs('cronogramas.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                        <svg class="h-5 w-5 shrink-0 text-gray-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span x-show="!sidebarCollapsed" class="truncate">Cronograma SUNAT</span>
                    </a>
                    @if (auth()->user()?->isAdmin())
                        <a href="{{ route('usuarios.index') }}"
                           title="Equipo y Usuarios"
                           :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                           class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar mt-1
                                  {{ request()->routeIs('usuarios.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0 text-gray-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" class="truncate">Equipo y Usuarios</span>
                        </a>

                        <a href="{{ route('ajustes.index') }}"
                           title="Ajustes del Sistema"
                           :class="sidebarCollapsed ? 'justify-center !px-2' : ''"
                           class="group flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm font-medium transition-sidebar mt-1
                                  {{ request()->routeIs('ajustes.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                            <svg class="h-5 w-5 shrink-0 text-gray-400 group-hover:text-white transition-colors" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            </svg>
                            <span x-show="!sidebarCollapsed" class="truncate">Ajustes del Sistema</span>
                        </a>
                    @endif
                </div>
            </div>

            {{-- Footer sidebar con Perfil y Logout --}}
            <div class="border-t border-white/10 p-3 flex flex-col gap-2">
                @if (auth()->check())
                    <div class="flex items-center gap-2.5" :class="sidebarCollapsed ? 'justify-center' : ''">
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg font-bold text-xs text-white shadow-xs
                                    {{ auth()->user()->isAdmin() ? 'bg-purple-600' : 'bg-brand-600' }}">
                            {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                        </div>
                        <div x-show="!sidebarCollapsed" class="min-w-0 flex-1">
                            <p class="truncate text-xs font-semibold text-white leading-tight">{{ auth()->user()->name }}</p>
                            <p class="truncate text-[10px] {{ auth()->user()->isAdmin() ? 'text-purple-300' : 'text-blue-300' }}">
                                {{ auth()->user()->isAdmin() ? '👑 Administrador' : '👷 Asistente' }}
                            </p>
                        </div>
                        <form x-show="!sidebarCollapsed" action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit"
                                    title="Cerrar Sesión"
                                    class="flex h-7 w-7 items-center justify-center rounded-lg text-gray-400 hover:bg-rose-500/20 hover:text-rose-400 transition">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                                </svg>
                            </button>
                        </form>
                    </div>
                @endif
                <div class="flex items-center justify-between text-gray-500 text-[10px] px-1" x-show="!sidebarCollapsed">
                    <span class="truncate">{{ auth()->user()?->estudio?->nombre ?? 'Estudio Contable' }}</span>
                    <span>v1.0</span>
                </div>
            </div>
        </aside>

        {{-- ═══════════════════════════════ CONTENIDO PRINCIPAL ═══════════════════════════════ --}}
        <main class="app-main flex-1 min-w-0">
            {{-- Contenido dinámico --}}
            <div class="p-8">
                @if (session('success'))
                    <x-ui.alert variant="success" timeout="4500" class="mb-6">{{ session('success') }}</x-ui.alert>
                @endif

                @if (session('error'))
                    <x-ui.alert variant="danger" timeout="6000" class="mb-6">{{ session('error') }}</x-ui.alert>
                @endif

                @yield('content')
            </div>
        </main>
    </div>
</body>
</html>
