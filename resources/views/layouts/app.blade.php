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

        // Posicionamiento inteligente y sin parpadeo para menús y popovers flotantes.
        // El reencuadre por cambio de alto no puede depender de offsetParent: en un
        // position:fixed ese valor es null, y el aviso del ResizeObserver se quedaba
        // sin aplicar hasta el siguiente movimiento del mouse.
        window.ubicarMenuFlotante = function (panel, ancla, intento) {
            if (!panel || !ancla || panel._reposicionPendiente) {
                return;
            }

            const soloEncuadre = panel._soloEncuadre === true;
            const margen = 16;
            const separacion = panel.dataset.separacion ? parseInt(panel.dataset.separacion, 10) : 10;
            const rect = ancla.getBoundingClientRect();
            let maxAlto = window.innerHeight - (margen * 2);

            // El acceso rápido siempre cae debajo del disparador. Si el panel
            // usara todo el alto de la ventana, el reencuadre lo subiría y
            // taparía el selector.
            if (panel.dataset.abrir === 'abajo') {
                maxAlto = Math.max(160, window.innerHeight - rect.bottom - separacion - margen);
            }

            if (!soloEncuadre) {
                const maxCss = maxAlto + 'px';
                if (panel.style.maxHeight !== maxCss) {
                    panel.style.maxHeight = maxCss;
                }
            }

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
            const abrirArriba = panel.dataset.abrir === 'abajo' ? false : (!cabeAbajo && cabeArriba);

            let top = abrirArriba ? rect.top - alto - separacion : rect.bottom + separacion;
            let left = rect.left;

            if (panel.dataset.alineacion === 'centro') {
                left = rect.left + (rect.width / 2) - (ancho / 2);
            }

            if (left + ancho > window.innerWidth - margen) {
                left = rect.right - ancho;
            }
            if (left + ancho > window.innerWidth - margen) {
                left = window.innerWidth - ancho - margen;
            }
            if (left < margen) {
                left = margen;
            }
            if (top < margen) {
                top = margen;
            }
            if (top + alto > window.innerHeight - margen) {
                top = Math.max(margen, window.innerHeight - alto - margen);
            }

            const topCss = Math.round(top) + 'px';
            const leftCss = Math.round(left) + 'px';
            if (panel.style.top !== topCss) {
                panel.style.top = topCss;
            }
            if (panel.style.left !== leftCss) {
                panel.style.left = leftCss;
            }
            if (panel.style.visibility !== 'visible') {
                panel.style.visibility = 'visible';
            }
            panel._anclaMenu = ancla;

            if (!panel._menuResizeObserver && typeof ResizeObserver !== 'undefined') {
                panel._menuResizeObserver = new ResizeObserver(function () {
                    if (panel._reposicionPendiente || panel._soloEncuadre || !panel._anclaMenu) {
                        return;
                    }
                    if (panel.style.display === 'none' || panel.getClientRects().length === 0) {
                        return;
                    }
                    panel._soloEncuadre = true;
                    window.ubicarMenuFlotante(panel, panel._anclaMenu);
                    panel._soloEncuadre = false;
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

            // Un arrastre que empieza dentro y suelta afuera no es un clic fuera.
            // El cierre de @click.outside solo procede si el botón se presionó afuera.
            document.addEventListener('mousedown', function (event) {
                const paneles = document.querySelectorAll('[data-menu-flotante]');
                for (let i = 0; i < paneles.length; i++) {
                    const panel = paneles[i];
                    if (panel._x_isShown === false || panel.style.display === 'none') {
                        panel._clicInicioDentro = false;
                        continue;
                    }
                    panel._clicInicioDentro = panel.contains(event.target);
                }
            }, true);
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
             sidebarHovered: false,
             hoverTimer: null,

             get isExpanded() {
                 return !this.sidebarCollapsed || this.sidebarHovered;
             },

             onMouseEnter() {
                 if (!this.sidebarCollapsed) return;
                 clearTimeout(this.hoverTimer);
                 this.hoverTimer = setTimeout(() => {
                     this.sidebarHovered = true;
                 }, 60);
             },

             onMouseLeave() {
                 if (!this.sidebarCollapsed) return;
                 clearTimeout(this.hoverTimer);
                 this.hoverTimer = setTimeout(() => {
                     this.sidebarHovered = false;
                 }, 140);
             },

             toggleSidebar() {
                 clearTimeout(this.hoverTimer);
                 this.sidebarHovered = false;
                 this.sidebarCollapsed = !this.sidebarCollapsed;
                 document.documentElement.dataset.sidebar = this.sidebarCollapsed ? 'collapsed' : 'open';
                 document.documentElement.classList.add('sidebar-animating');
                 try {
                     localStorage.setItem('crm-sidebar', this.sidebarCollapsed ? 'collapsed' : 'open');
                 } catch (e) {}
                 setTimeout(() => {
                     document.documentElement.classList.remove('sidebar-animating');
                 }, 250);
             },

             handleSidebarClick(event) {
                 const link = event.target.closest('a');
                 if (link && this.sidebarCollapsed) {
                     // Si el menú estaba encogido y el usuario hizo clic en cualquier enlace,
                     // la navegación debe llevarlo a la página con el menú permanentemente abierto.
                     this.sidebarCollapsed = false;
                     this.sidebarHovered = false;
                     document.documentElement.dataset.sidebar = 'open';
                     try {
                         localStorage.setItem('crm-sidebar', 'open');
                     } catch (e) {}
                 }
             }
         }">

        {{-- ═══════════════════════════════════ SIDEBAR COLAPSABLE ═══════════════════════════════════ --}}
        <aside class="app-sidebar fixed inset-y-0 left-0 z-30 flex flex-col bg-sidebar text-gray-300 select-none shadow-xl"
               :class="{ 'is-hover-expanded': sidebarCollapsed && sidebarHovered }"
               @mouseenter="onMouseEnter()"
               @mouseleave="onMouseLeave()"
               @click="handleSidebarClick($event)">

            @php
                $currentEstudio = auth()->user()?->estudio;
                $estudioLogoUrl = $currentEstudio?->logoUrl();
                $estudioIsotipoUrl = $currentEstudio?->isotipoUrl();
                $estudioNombre = $currentEstudio ? $currentEstudio->nombreParaMostrar() : 'CRM Contable';
                $estudioSubtitulo = $currentEstudio?->slogan ?: ($currentEstudio?->nombre_comercial ? $currentEstudio->nombre : 'Estudio Contable');
                $estudioDisplay = $currentEstudio?->sidebar_display ?? 'logo_nombre';
                $estudioIniciales = $currentEstudio ? $currentEstudio->iniciales() : 'EC';
            @endphp

            {{-- Header de la Barra Lateral: Botón Hamburguesa (a la izquierda, alineado con los iconos) + Logo / Marca --}}
            <div class="sidebar-header flex h-16 items-center border-b border-white/10 px-3.5 gap-2">
                {{-- Botón Toggle de la Barra Lateral (Menú Hamburguesa sutil de estilo CRM) --}}
                <button type="button"
                        @click.stop="toggleSidebar()"
                        :title="sidebarCollapsed ? 'Fijar menú abierto' : 'Encoger barra lateral'"
                        data-sidebar-expanded-only
                        class="sidebar-hamburger flex h-8 w-8 items-center justify-center rounded-lg text-slate-400 hover:text-white hover:bg-white/10 transition-colors opacity-75 hover:opacity-100 shrink-0 focus:outline-none focus:ring-1 focus:ring-brand-500/50"
                        aria-label="Alternar menú lateral">
                    {{-- Icono de Menú Hamburguesa elegante y sutil (3 barras) --}}
                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                    </svg>
                </button>

                <a href="{{ route('dashboard') }}"
                   title="{{ $estudioNombre }} - Ir al Dashboard"
                   class="group flex items-center min-w-0 flex-1 hover:opacity-90 transition rounded-lg p-1 focus:outline-none focus:ring-2 focus:ring-brand-500/50">

                    {{-- Vista 1: Barra Lateral Extendida (Muestra el Logotipo Completo o Nombre) --}}
                    <div data-sidebar-expanded-only class="flex items-center gap-2.5 min-w-0">
                        @if ($estudioDisplay !== 'nombre_solo' && $estudioLogoUrl)
                            <div class="flex h-7 max-h-7 items-center">
                                <img src="{{ $estudioLogoUrl }}" alt="{{ $estudioNombre }}" class="h-6.5 max-h-7 w-auto max-w-[130px] object-contain">
                            </div>
                        @else
                            <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white font-black text-xs shadow-md tracking-wider">
                                {{ $estudioIniciales }}
                            </div>
                            <div class="truncate min-w-0 text-left">
                                <span class="block text-sm font-bold text-white leading-tight truncate group-hover:text-brand-300 transition">
                                    {{ $estudioNombre }}
                                </span>
                                <span class="block text-[11px] text-gray-400 truncate">
                                    {{ \Illuminate\Support\Str::limit($estudioSubtitulo, 22) }}
                                </span>
                            </div>
                        @endif
                    </div>

                    {{-- Vista 2: Barra Lateral Contraída / Encogida (Muestra únicamente el Isotipo o Monograma centrado) --}}
                    <div data-sidebar-collapsed-only class="flex items-center justify-center">
                        @if ($estudioDisplay !== 'nombre_solo' && $estudioIsotipoUrl)
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-white/10 p-1 shadow-md overflow-hidden border border-white/15">
                                <img src="{{ $estudioIsotipoUrl }}" alt="{{ $estudioNombre }}" class="h-full w-full object-contain rounded">
                            </div>
                        @else
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-brand-600 text-white font-black text-sm shadow-md tracking-wider">
                                {{ $estudioIniciales }}
                            </div>
                        @endif
                    </div>
                </a>
            </div>

            {{-- Navegación --}}
            <nav class="mt-4 flex-1 space-y-1.5 px-2.5 overflow-y-auto overflow-x-hidden custom-scrollbar">
                <p data-sidebar-expanded-only class="mb-2 px-3 text-[11px] font-semibold uppercase tracking-wider text-gray-500">Gestión</p>
                <div data-sidebar-collapsed-only class="my-2 border-t border-white/10 mx-1"></div>

                <a href="{{ route('dashboard') }}"
                   title="Dashboard"
                   class="sidebar-nav-item group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('dashboard') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Home / Dashboard --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                    </svg>
                    <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Dashboard</span>
                </a>

                <a href="{{ route('clientes.index') }}"
                   title="Clientes"
                   class="sidebar-nav-item group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('clientes.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Users --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                    </svg>
                    <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Clientes</span>
                </a>

                <a href="{{ route('control.index') }}"
                   title="Control Impuestos"
                   class="sidebar-nav-item group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('control.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Calendar / Table Matrix --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5m-9-3.75h.008v.008H12v-.008ZM12 15h.008v.008H12V15Zm0 2.25h.008v.008H12v-.008ZM9.75 15h.008v.008H9.75V15Zm0 2.25h.008v.008H9.75v-.008ZM7.5 15h.008v.008H7.5V15Zm0 2.25h.008v.008H7.5v-.008Zm6.75-4.5h.008v.008h-.008v-.008Zm0 2.25h.008v.008h-.008V15Zm0 2.25h.008v.008h-.008v-.008Zm2.25-4.5h.008v.008H16.5v-.008Zm0 2.25h.008v.008H16.5V15Z" />
                    </svg>
                    <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Control Impuestos</span>
                </a>

                <a href="{{ route('control_deudas.index') }}"
                   title="Control de Deudas"
                   class="sidebar-nav-item group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('control_deudas.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Banknote / Debt --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                    </svg>
                    <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Control Deudas</span>
                </a>

                <a href="{{ route('balances.index') }}"
                   title="Control Balances"
                   class="sidebar-nav-item group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('balances.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Balance Scale --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 3v17.25m0 0c-1.472 0-2.882.265-4.185.75M12 20.25c1.472 0 2.882.265 4.185.75M18.75 4.97A48.416 48.416 0 0 0 12 4.5c-2.291 0-4.545.16-6.75.47m13.5 0c1.01.143 2.01.317 3 .52m-3-.52 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.988 5.988 0 0 1-2.031.352 5.988 5.988 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L18.75 4.971Zm-16.5.52c.99-.203 1.99-.377 3-.52m0 0 2.62 10.726c.122.499-.106 1.028-.589 1.202a5.989 5.989 0 0 1-2.031.352 5.989 5.989 0 0 1-2.031-.352c-.483-.174-.711-.703-.59-1.202L5.25 4.971Z" />
                    </svg>
                    <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Control Balances</span>
                </a>

                <a href="{{ route('tipo-cambio.index') }}"
                   title="Tipo de Cambio"
                   class="sidebar-nav-item group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('tipo-cambio.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-3-2.818.879.659c1.171.879 3.07.879 4.242 0 1.172-.879 1.172-2.303 0-3.182C13.536 12.219 12.768 12 12 12c-.725 0-1.45-.22-2.003-.659-1.106-.879-1.106-2.303 0-3.182s2.9-.879 4.006 0l.415.33M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Tipo de Cambio</span>
                </a>

                <a href="{{ route('alertas.index') }}"
                   title="Buzón de Alertas"
                   class="sidebar-nav-item group flex items-center justify-between rounded-lg px-2.5 py-2 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('alertas.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    <div class="flex items-center gap-2.5 min-w-0 sidebar-nav-inner">
                        {{-- Icono: Bell / Inbox --}}
                        <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
                        </svg>
                        <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Buzón de Alertas</span>
                    </div>

                    @if (($alertasPendientesCount ?? 0) > 0)
                        <span data-sidebar-expanded-only class="inline-flex items-center justify-center rounded-full bg-rose-500 px-2 py-0.5 text-[10px] font-black text-white shadow-xs shrink-0 ml-1.5">
                            {{ $alertasPendientesCount }}
                        </span>
                        <span data-sidebar-collapsed-only class="absolute top-2 right-2.5 h-2.5 w-2.5 rounded-full bg-rose-500 ring-2 ring-sidebar" title="{{ $alertasPendientesCount }} alertas pendientes"></span>
                    @endif
                </a>

                <a href="{{ route('comprobantes.index') }}"
                   title="Bóveda SIRE"
                   class="sidebar-nav-item group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('comprobantes.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Archive Box / Bóveda Documental --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m20.25 7.5-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
                    </svg>
                    <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Bóveda SIRE</span>
                </a>

                <a href="{{ route('cpe.visor') }}"
                   title="Visor XML / Generador CPE"
                   class="sidebar-nav-item group flex items-center gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium transition-sidebar relative
                          {{ request()->routeIs('cpe.*') ? 'bg-brand-600/20 text-white font-semibold' : 'text-gray-400 hover:bg-sidebar-hover hover:text-white' }}">
                    {{-- Icono: Document Magnifying Glass / Printer --}}
                    <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Visor XML / CPE</span>
                </a>
            </nav>

            <div class="px-2.5 pb-2">
                <div class="border-t border-white/10 pt-2.5">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit"
                                title="Cerrar sesión"
                                class="sidebar-nav-item group flex w-full items-center justify-start gap-2.5 rounded-lg px-2.5 py-2 text-sm font-medium text-gray-400 transition-colors hover:bg-sidebar-hover hover:text-rose-300 cursor-pointer select-none">
                            <svg class="h-5 w-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3 0 3-3m0 0-3-3m3 3H9" />
                            </svg>
                            <span data-sidebar-expanded-only class="truncate whitespace-nowrap">Cerrar sesión</span>
                        </button>
                    </form>
                </div>
            </div>

        </aside>

        {{-- ═══════════════════════════════ CONTENIDO PRINCIPAL ═══════════════════════════════ --}}
        <main class="app-main flex-1 min-w-0 flex flex-col">
            {{-- Barra delgada de acceso rápido con espaciado respirable, selector central, consultas rápidas y avatar de usuario --}}
            <header
                class="app-topbar sticky top-0 z-[24] flex h-12 shrink-0 items-center border-b border-gray-200 bg-white px-2.5 sm:px-4"
                data-selector-empresa
                data-empresa-id="{{ $empresaActiva?->id ?? '' }}"
            >
                <div class="w-full flex items-center justify-between gap-2 min-w-0">
                    {{-- Lado Izquierdo: Spacer para centrado balanceado --}}
                    <div class="hidden sm:flex items-center min-w-0 flex-1 justify-start"></div>

                    {{-- Centro: Selector de Empresa (REGLA ESTRICTA: Sin modificar clases internas del selector) --}}
                    <div class="flex shrink-0 items-center justify-center">
                        <x-navegacion.selector-empresa :empresa="$empresaActiva" />
                    </div>

                    {{-- Lado Derecho: Consultas Rápidas, cronograma, configuración y foto --}}
                    <div class="flex items-center justify-end gap-2.5 sm:gap-3 flex-1 min-w-0">
                        {{-- Consultas Rápidas --}}
                        <div x-data="{ abierto: false }"
                             class="relative"
                             @click.outside="abierto = false"
                             @keydown.escape.window="abierto = false"
                             @topbar-panel.window="if ($event.detail.panel !== 'consultas') abierto = false">
                            <button type="button"
                                    @click="abierto = !abierto; if (abierto) { $dispatch('topbar-panel', { panel: 'consultas' }) }"
                                    :class="abierto ? 'bg-gray-100 text-brand-600 ring-2 ring-brand-500/20' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'"
                                    class="flex h-8 items-center gap-1.5 rounded-lg px-2.5 text-xs font-semibold transition border border-gray-200/80 bg-white shadow-2xs cursor-pointer select-none"
                                    title="Consultas rápidas (Enlaces oficiales)"
                                    aria-label="Consultas rápidas"
                                    :aria-expanded="abierto ? 'true' : 'false'">
                                {{-- Icono de Consultas Rápidas --}}
                                <svg class="h-4 w-4 text-brand-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                </svg>
                                <span class="hidden md:inline font-medium text-gray-700 text-[11px]">Consultas Rápidas</span>
                                <svg class="h-3 w-3 text-gray-400 transition-transform" :class="abierto ? 'rotate-180' : ''" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                </svg>
                            </button>

                            {{-- Menú Flotante Dropdown --}}
                            <div x-show="abierto"
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0 translate-y-1 scale-95"
                                 x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave="transition ease-in duration-100"
                                 x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                 x-transition:leave-end="opacity-0 translate-y-1 scale-95"
                                 class="absolute right-0 mt-1.5 w-64 rounded-md border border-gray-200 bg-white p-2 shadow-lg z-50">

                                <div class="px-2 py-1 mb-1 border-b border-gray-100">
                                    <span class="text-[10px] font-bold uppercase tracking-wider text-gray-400">Enlaces oficiales</span>
                                </div>

                                <div class="max-h-80 overflow-y-auto space-y-0.5 custom-scrollbar">
                                    @forelse ($quickLinks ?? [] as $link)
                                        <a href="{{ $link->url }}"
                                           target="_blank"
                                           rel="noopener noreferrer"
                                           @click="abierto = false"
                                           class="flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-xs text-gray-700 hover:bg-gray-50 transition group">
                                            <svg class="h-3.5 w-3.5 text-gray-400 group-hover:text-brand-600 shrink-0 transition" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                            </svg>
                                            <span class="truncate font-medium text-gray-700 group-hover:text-gray-900">{{ $link->label }}</span>
                                        </a>
                                    @empty
                                        <div class="px-2.5 py-3 text-center text-xs text-gray-400">
                                            No hay enlaces configurados
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        </div>

                        <x-navegacion.cronograma-barra :cronograma="$cronogramaBarra" />

                        <x-navegacion.menu-configuracion />

                        {{-- Cuenta: solo la foto en la barra. El detalle y el cierre de sesión van en el panel. --}}
                        @if (auth()->check())
                            @php
                                $usuarioActual = auth()->user();
                                $esAdmin = $usuarioActual->isAdmin();
                                $fotoUrl = $usuarioActual->fotoPerfilUrl();
                                $rolCuenta = $esAdmin ? 'Administrador' : 'Asistente';
                            @endphp

                            <div x-data="{ abierto: false }"
                                 class="relative shrink-0"
                                 @click.outside="abierto = false"
                                 @keydown.escape.window="abierto = false"
                                 @topbar-panel.window="if ($event.detail.panel !== 'cuenta') abierto = false">
                                <button type="button"
                                        @click="abierto = !abierto; if (abierto) { $dispatch('topbar-panel', { panel: 'cuenta' }) }"
                                        class="relative flex h-8 w-8 items-center justify-center rounded-full cursor-pointer"
                                        title="{{ $usuarioActual->name }}"
                                        aria-label="Cuenta de {{ $usuarioActual->name }}"
                                        :aria-expanded="abierto ? 'true' : 'false'">
                                    @if ($fotoUrl)
                                        <img src="{{ $fotoUrl }}"
                                             alt=""
                                             class="h-8 w-8 rounded-full object-cover ring-2 {{ $esAdmin ? 'ring-gold-500' : 'ring-blue-500' }}">
                                    @else
                                        <span class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-bold text-white ring-2 {{ $esAdmin ? 'ring-gold-500 bg-gold-600' : 'ring-blue-500 bg-blue-600' }}">
                                            {{ $usuarioActual->iniciales() }}
                                        </span>
                                    @endif

                                    <span class="absolute -bottom-0.5 -right-0.5 flex h-3.5 w-3.5 items-center justify-center rounded-full text-[8px] leading-none ring-2 ring-white {{ $esAdmin ? 'bg-gold-600' : 'bg-blue-600' }}"
                                          title="{{ $rolCuenta }}">
                                        {{ $esAdmin ? '👑' : '💼' }}
                                    </span>
                                </button>

                                <div x-show="abierto"
                                     x-cloak
                                     x-transition:enter="transition ease-out duration-150"
                                     x-transition:enter-start="opacity-0 translate-y-1"
                                     x-transition:enter-end="opacity-100 translate-y-0"
                                     x-transition:leave="transition ease-in duration-100"
                                     x-transition:leave-start="opacity-100 translate-y-0"
                                     x-transition:leave-end="opacity-0 translate-y-1"
                                     class="absolute right-0 z-50 mt-2 w-72 rounded-xl border border-gray-200 bg-white shadow-xl">
                                    <div class="flex items-center gap-3 px-3.5 py-3">
                                        @if ($fotoUrl)
                                            <img src="{{ $fotoUrl }}" alt="" class="h-11 w-11 shrink-0 rounded-full object-cover ring-2 {{ $esAdmin ? 'ring-gold-500' : 'ring-blue-500' }}">
                                        @else
                                            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full text-sm font-bold text-white ring-2 {{ $esAdmin ? 'ring-gold-500 bg-gold-600' : 'ring-blue-500 bg-blue-600' }}">
                                                {{ $usuarioActual->iniciales() }}
                                            </span>
                                        @endif
                                        <div class="min-w-0">
                                            <p class="truncate text-sm font-semibold text-gray-900">{{ $usuarioActual->name }}</p>
                                            <p class="mt-0.5 text-[11px] font-semibold {{ $esAdmin ? 'text-gold-600' : 'text-blue-600' }}">{{ $rolCuenta }}</p>
                                        </div>
                                    </div>

                                    <dl class="space-y-1.5 border-t border-gray-100 px-3.5 py-2.5 text-[11px]">
                                        <div class="flex gap-3">
                                            <dt class="w-16 shrink-0 text-gray-400">Correo</dt>
                                            <dd class="min-w-0 truncate text-gray-700">{{ $usuarioActual->email }}</dd>
                                        </div>
                                        @if (filled($usuarioActual->cargo))
                                            <div class="flex gap-3">
                                                <dt class="w-16 shrink-0 text-gray-400">Cargo</dt>
                                                <dd class="min-w-0 truncate text-gray-700">{{ $usuarioActual->cargo }}</dd>
                                            </div>
                                        @endif
                                        @if (filled($usuarioActual->telefono))
                                            <div class="flex gap-3">
                                                <dt class="w-16 shrink-0 text-gray-400">Teléfono</dt>
                                                <dd class="min-w-0 truncate text-gray-700">{{ $usuarioActual->telefono }}</dd>
                                            </div>
                                        @endif
                                        <div class="flex gap-3">
                                            <dt class="w-16 shrink-0 text-gray-400">Estudio</dt>
                                            <dd class="min-w-0 truncate text-gray-700">{{ $estudioNombre }}</dd>
                                        </div>
                                        @if ($usuarioActual->esCumpleanosHoy())
                                            <div class="pt-0.5 text-gray-600">Hoy es su cumpleaños.</div>
                                        @endif
                                    </dl>

                                    <div class="border-t border-gray-100 p-1.5">
                                        <a href="{{ route('perfil.edit') }}"
                                           @click="abierto = false"
                                           class="flex items-center gap-2 rounded-lg px-2.5 py-2 text-xs font-medium text-gray-700 hover:bg-gray-50">
                                            Mi perfil
                                        </a>
                                        <form action="{{ route('logout') }}" method="POST">
                                            @csrf
                                            <button type="submit"
                                                    title="Cerrar Sesión"
                                                    class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-xs font-medium text-gray-700 hover:bg-rose-50 hover:text-rose-700 cursor-pointer">
                                                Cerrar sesión
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>
                </div>
            </header>

            {{-- Contenido dinámico --}}
            <div class="px-8 pt-4 pb-8">
                @if (session('success'))
                    <x-ui.alert variant="success" timeout="4500" class="mb-6">{{ session('success') }}</x-ui.alert>
                @endif

                @if (session('error'))
                    <x-ui.alert variant="danger" timeout="6000" class="mb-6">{{ session('error') }}</x-ui.alert>
                @endif

                @yield('content')
            </div>

            {{-- Footer sutil de aplicación: visible únicamente en la página de Ajustes del Sistema --}}
            @if (request()->routeIs('ajustes.*'))
                <footer class="mt-auto px-8 py-3 text-xs text-gray-400 border-t border-gray-200/50 flex items-center justify-between no-print">
                    <span class="truncate">{{ $estudioNombre }} &copy; {{ date('Y') }}</span>
                    <span class="font-mono text-[10px] text-gray-400 bg-gray-100 px-2 py-0.5 rounded">v1.0</span>
                </footer>
            @endif
        </main>
    </div>

    {{-- Experiencia interactiva y festiva de cumpleaños --}}
    @if (auth()->check() && auth()->user()->esCumpleanosHoy())
        <div x-data="{
                 abierto: !sessionStorage.getItem('crm_cumpleanos_visto_{{ auth()->id() }}_{{ date('Ymd') }}'),
                 lanzarConfeti() {
                     if (typeof window.confetti === 'function') {
                         window.confetti({ particleCount: 90, spread: 70, origin: { y: 0.6 } });
                         setTimeout(() => {
                             window.confetti({ particleCount: 50, angle: 60, spread: 55, origin: { x: 0 } });
                             window.confetti({ particleCount: 50, angle: 120, spread: 55, origin: { x: 1 } });
                         }, 250);
                     }
                 },
                 cerrar() {
                     this.abierto = false;
                     sessionStorage.setItem('crm_cumpleanos_visto_{{ auth()->id() }}_{{ date('Ymd') }}', 'true');
                 }
             }"
             x-init="if (abierto) { setTimeout(() => lanzarConfeti(), 500); }"
             x-show="abierto"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/60 backdrop-blur-xs transition-opacity duration-300">

            <div @click.outside="cerrar()"
                 class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white p-7 text-center shadow-2xl border border-amber-200">

                {{-- Resplandores decorativos festivos --}}
                <div class="pointer-events-none absolute -top-8 -right-8 h-32 w-32 rounded-full bg-amber-400/20 blur-2xl"></div>
                <div class="pointer-events-none absolute -bottom-8 -left-8 h-32 w-32 rounded-full bg-rose-400/20 blur-2xl"></div>

                {{-- Emoji / Icono animado --}}
                <div class="mx-auto mb-4 flex h-20 w-20 items-center justify-center rounded-2xl bg-gradient-to-tr from-amber-400 via-orange-400 to-rose-500 shadow-lg shadow-amber-500/30 animate-bounce">
                    <span class="text-4xl select-none">🎂</span>
                </div>

                <div class="space-y-2">
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-xs font-black uppercase tracking-wider text-amber-800 border border-amber-300">
                        🎉 ¡Día Especial en el CRM! 🎉
                    </span>
                    <h3 class="text-2xl font-black text-gray-900 tracking-tight">
                        ¡Feliz Cumpleaños, {{ auth()->user()->name }}!
                    </h3>
                    <p class="text-xs text-gray-600 leading-relaxed max-w-sm mx-auto">
                        De parte de todo el equipo de <strong>{{ $estudioNombre }}</strong>, te deseamos un día extraordinario lleno de éxitos, alegría y momentos inolvidables.
                    </p>
                </div>

                <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-2.5">
                    <button type="button"
                            @click="lanzarConfeti()"
                            class="w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-amber-500 to-orange-500 px-5 py-2.5 text-xs font-bold text-white shadow-md shadow-orange-500/25 hover:from-amber-600 hover:to-orange-600 transition active:scale-95 cursor-pointer">
                        <span>🎊 ¡Lanzar más Confeti!</span>
                    </button>
                    <button type="button"
                            @click="cerrar()"
                            class="w-full sm:w-auto inline-flex items-center justify-center rounded-xl border border-gray-300 bg-white px-5 py-2.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 transition active:scale-95 cursor-pointer">
                        <span>¡Muchas Gracias! 😊</span>
                    </button>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.4/dist/confetti.browser.min.js"></script>
    @endif
</body>
</html>
