<div
    x-data="{
        abierto: false,
        alternar() {
            this.abierto = !this.abierto;
            if (this.abierto) {
                this.$dispatch('topbar-panel', { panel: 'config' });
                this.$nextTick(() => this.ubicar());
            }
        },
        ubicar() {
            if (window.ubicarMenuFlotante && this.$refs.panel && this.$refs.boton) {
                window.ubicarMenuFlotante(this.$refs.panel, this.$refs.boton);
            }
        }
    }"
    class="relative shrink-0"
    @keydown.escape.window="abierto = false"
    @topbar-panel.window="if ($event.detail.panel !== 'config') abierto = false"
>
    <button
        type="button"
        x-ref="boton"
        data-config-boton
        @click="alternar()"
        :class="abierto ? 'bg-gray-100 text-brand-700 ring-2 ring-brand-500/20' : 'text-gray-600 hover:text-gray-900 hover:bg-gray-100'"
        class="flex h-8 w-8 items-center justify-center rounded-lg border border-gray-200/80 bg-white shadow-2xs transition cursor-pointer"
        title="Configuración"
        aria-label="Configuración"
        :aria-expanded="abierto ? 'true' : 'false'"
    >
        <svg class="h-4 w-4 text-brand-600" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        </svg>
    </button>

    <template x-teleport="body">
        <div
            x-ref="panel"
            x-show="abierto"
            x-cloak
            data-menu-flotante
            data-abrir="abajo"
            data-separacion="8"
            @click.outside="if (!$event.target.closest('[data-config-boton]') && !$el._clicInicioDentro) abierto = false"
            role="menu"
            aria-label="Configuración"
            class="fixed z-50 w-64 space-y-1 rounded-2xl border border-white/10 bg-sidebar p-2 text-xs shadow-2xl"
            style="display: none;"
        >
            <a href="{{ route('cronogramas.index') }}"
               @click="abierto = false"
               role="menuitem"
               class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition {{ request()->routeIs('cronogramas.*') ? 'bg-white/10 font-bold text-white' : 'text-white/80 hover:bg-sidebar-hover hover:text-white' }}">
                <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-emerald-500/30 bg-emerald-500/20 text-emerald-400">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="font-bold leading-tight">Cronograma SUNAT</p>
                    <p class="text-[10px] leading-tight text-white/55">Calendario de vencimientos</p>
                </div>
            </a>

            @if (auth()->user()?->isAdmin())
                <a href="{{ route('usuarios.index') }}"
                   @click="abierto = false"
                   role="menuitem"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition {{ request()->routeIs('usuarios.*') ? 'bg-white/10 font-bold text-white' : 'text-white/80 hover:bg-sidebar-hover hover:text-white' }}">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-purple-500/30 bg-purple-500/20 text-purple-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold leading-tight">Equipo y Usuarios</p>
                        <p class="text-[10px] leading-tight text-white/55">Personal y asignación de clientes</p>
                    </div>
                </a>

                <a href="{{ route('ajustes.codigos') }}"
                   @click="abierto = false"
                   role="menuitem"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition {{ request()->routeIs('ajustes.codigos') ? 'bg-white/10 font-bold text-white' : 'text-white/80 hover:bg-sidebar-hover hover:text-white' }}">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-amber-500/30 bg-amber-500/20 text-amber-300">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25H12" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold leading-tight">Códigos SUNAT</p>
                        <p class="text-[10px] leading-tight text-white/55">Tributos y medios de pago</p>
                    </div>
                </a>

                <a href="{{ route('ajustes.index') }}"
                   @click="abierto = false"
                   role="menuitem"
                   class="flex items-center gap-3 rounded-xl px-3 py-2.5 transition {{ request()->routeIs('ajustes.index') ? 'bg-white/10 font-bold text-white' : 'text-white/80 hover:bg-sidebar-hover hover:text-white' }}">
                    <div class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg border border-sky-500/30 bg-sky-500/20 text-sky-400">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </div>
                    <div class="min-w-0">
                        <p class="font-bold leading-tight">Ajustes del Estudio</p>
                        <p class="text-[10px] leading-tight text-white/55">Logo, RUC, RPA SUNAT y WhatsApp</p>
                    </div>
                </a>
            @endif
        </div>
    </template>
</div>
