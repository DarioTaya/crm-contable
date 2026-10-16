/**
 * Acceso rápido de empresas en la barra superior.
 * La cartera llega por la API (no se incrusta en cada página) y los portales
 * reutilizan el mismo inicio de sesión que la ficha del cliente.
 */
export function registrarSelectorEmpresa(Alpine) {
    Alpine.data('selectorEmpresa', (config = {}) => ({
        buscarUrl: config.buscarUrl,
        seleccionarUrl: config.seleccionarUrl,
        fijarUrl: config.fijarUrl,
        empresa: config.empresa,
        contextoClienteId: config.contextoClienteId ?? null,
        rutaActual: config.rutaActual ?? null,
        abierto: false,
        consulta: '',
        empresas: [],
        hayMas: false,
        cargando: false,
        errorLista: '',
        indiceActivo: 0,
        debounce: null,
        abort: null,
        menuSunat: { abierto: false, url: '', nombre: '', ruc: '' },
        hud: {
            abierto: false,
            portal: 'SUNAT',
            clienteNombre: '',
            clienteRuc: '',
            progreso: 0,
            faseTexto: '',
            icono: '🚀',
            nombreNavegador: 'Google Chrome',
            completado: false,
            error: null,
            animTimer: null,
        },

        alternar() {
            if (this.abierto) {
                this.cerrar();
                return;
            }

            this.abierto = true;
            this.menuSunat.abierto = false;
            this.$nextTick(() => {
                this.ubicar(this.$refs.panel, this.$refs.disparador);
                this.$refs.buscador?.focus();
            });
            this.cargar();
        },

        cerrar() {
            this.abierto = false;
            this.menuSunat.abierto = false;
            this.consulta = '';
            this.errorLista = '';
        },

        cerrarDesdeFuera(event, panel) {
            if (panel?._clicInicioDentro) {
                return;
            }
            if (this.$refs.disparador?.contains(event.target)) {
                return;
            }
            if (this.$refs.panelSunat?.contains(event.target)) {
                return;
            }
            this.cerrar();
        },

        cerrarSunatDesdeFuera(event, panel) {
            if (!this.menuSunat.abierto) {
                return;
            }
            if (panel?._clicInicioDentro) {
                return;
            }
            if (event.target?.closest?.('[data-accion-sunat]')) {
                return;
            }
            this.menuSunat.abierto = false;
        },

        alEscape(event) {
            if (this.hud.abierto) {
                if (this.hud.error) {
                    event?.preventDefault();
                    this.hud.abierto = false;
                }
                return;
            }
            if (!this.abierto) {
                return;
            }
            event?.preventDefault();
            if (this.menuSunat.abierto) {
                this.menuSunat.abierto = false;
                return;
            }
            if (this.consulta.trim() !== '') {
                this.consulta = '';
                this.cargar();
                return;
            }
            this.cerrar();
        },

        alEscribir() {
            clearTimeout(this.debounce);
            this.debounce = setTimeout(() => this.cargar(), 220);
        },

        alTeclado(event) {
            if (event.key === 'ArrowDown') {
                event.preventDefault();
                this.mover(1);
            } else if (event.key === 'ArrowUp') {
                event.preventDefault();
                this.mover(-1);
            } else if (event.key === 'Enter') {
                event.preventDefault();
                const item = this.empresas[this.indiceActivo];
                if (item) {
                    this.elegir(item);
                }
            }
        },

        mover(delta) {
            if (this.empresas.length === 0) {
                return;
            }
            const maximo = this.empresas.length - 1;
            this.indiceActivo = Math.min(maximo, Math.max(0, this.indiceActivo + delta));
            this.$nextTick(() => {
                this.$refs.lista?.querySelector('[data-opcion-activa="1"]')?.scrollIntoView({ block: 'nearest' });
            });
        },

        async cargar() {
            const controlador = new AbortController();
            this.abort?.abort();
            this.abort = controlador;
            this.cargando = true;
            this.errorLista = '';

            try {
                const url = new URL(this.buscarUrl, window.location.origin);
                const termino = this.consulta.trim().slice(0, 80);
                if (termino !== '') {
                    url.searchParams.set('q', termino);
                }

                const respuesta = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    signal: controlador.signal,
                });

                if (!respuesta.ok) {
                    throw new Error('carga');
                }

                const data = await respuesta.json();
                this.empresas = Array.isArray(data.empresas) ? data.empresas : [];
                this.hayMas = Boolean(data.hay_mas);
                this.indiceActivo = 0;
            } catch (error) {
                if (error?.name === 'AbortError') {
                    return;
                }
                this.empresas = [];
                this.hayMas = false;
                this.errorLista = 'No se pudo cargar la cartera. Inténtalo de nuevo.';
            } finally {
                if (!controlador.signal.aborted) {
                    this.cargando = false;
                }
            }
        },

        async alternarFijada(item) {
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const respuesta = await fetch(this.fijarUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ cliente_id: item.id }),
                });
                const data = await respuesta.json().catch(() => ({}));

                if (respuesta.status === 422) {
                    this.errorLista = data.errors?.cliente_id?.[0]
                        || 'Ya tienes 5 empresas fijas. Quita una para fijar otra.';
                    return;
                }

                if (!respuesta.ok) {
                    this.errorLista = respuesta.status === 404
                        ? 'Esa empresa ya no está en tu cartera.'
                        : 'No se pudo fijar la empresa.';
                    return;
                }

                this.errorLista = '';
                item.fijada = Boolean(data.fijada);

                if (this.consulta.trim() === '') {
                    await this.cargar();
                }
            } catch {
                this.errorLista = 'No se pudo fijar la empresa.';
            }
        },

        paginaDeOtraEmpresa(item) {
            if (this.contextoClienteId === null || this.contextoClienteId === '') {
                return false;
            }

            return Number(this.contextoClienteId) !== Number(item.id);
        },

        async elegir(item) {
            if (item?.carpeta) {
                window.location.assign(item.carpeta);
                return;
            }

            const ok = await this.recordar(item);
            if (!ok) {
                return;
            }

            this.empresa = {
                id: item.id,
                razon_social: item.razon_social,
                ruc: item.ruc,
                url: item.carpeta,
            };
            this.cerrar();
        },

        async recordar(item) {
            try {
                const token = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
                const respuesta = await fetch(this.seleccionarUrl, {
                    method: 'POST',
                    headers: {
                        Accept: 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ cliente_id: item.id }),
                });

                if (respuesta.ok) {
                    return true;
                }

                this.errorLista = respuesta.status === 404
                    ? 'Esa empresa ya no está en tu cartera.'
                    : 'No se pudo seleccionar la empresa.';
            } catch {
                this.errorLista = 'No se pudo seleccionar la empresa.';
            }

            return false;
        },

        abrirSunat(event, item) {
            const mismo = this.menuSunat.abierto && this.menuSunat.url === item.sunat;
            if (mismo) {
                this.menuSunat.abierto = false;
                return;
            }

            if (!this.paginaDeOtraEmpresa(item)) {
                this.empresa = {
                    id: item.id,
                    razon_social: item.razon_social,
                    ruc: item.ruc,
                    url: item.carpeta,
                };
                this.recordar(item);
            }

            this.menuSunat.url = item.sunat;
            this.menuSunat.nombre = item.razon_social;
            this.menuSunat.ruc = item.ruc;
            this.menuSunat.abierto = true;
            const boton = event.currentTarget;
            this.$nextTick(() => this.ubicar(this.$refs.panelSunat, boton));
        },

        elegirAccesoSunat(codigo) {
            const separador = this.menuSunat.url.includes('?') ? '&' : '?';
            const url = this.menuSunat.url + separador + 'destino=' + encodeURIComponent(codigo);
            const nombre = this.menuSunat.nombre;
            const ruc = this.menuSunat.ruc;
            this.menuSunat.abierto = false;
            this.abierto = false;
            this.lanzarAcceso(url, 'SUNAT', nombre, ruc);
        },

        lanzarSunafil(item) {
            if (!this.paginaDeOtraEmpresa(item)) {
                this.empresa = {
                    id: item.id,
                    razon_social: item.razon_social,
                    ruc: item.ruc,
                    url: item.carpeta,
                };
                this.recordar(item);
            }
            this.cerrar();
            this.lanzarAcceso(item.sunafil, 'SUNAFIL', item.razon_social, item.ruc);
        },

        init() {
            this._alRecuperarFoco = () => {
                if (this.hud.completado) {
                    this.hud.abierto = false;
                    this.menuSunat.abierto = false;
                }
            };
            window.addEventListener('focus', this._alRecuperarFoco);
            document.addEventListener('visibilitychange', this._alRecuperarFoco);
        },

        async lanzarAcceso(url, portal, nombre, ruc) {
            if (this.hud.animTimer) {
                clearInterval(this.hud.animTimer);
            }

            this.abierto = false;
            this.menuSunat.abierto = false;
            this.hud.portal = portal;
            this.hud.clienteNombre = nombre;
            this.hud.clienteRuc = ruc;
            this.hud.icono = portal === 'SUNAT' ? '🚀' : '👷';
            this.hud.progreso = 22;
            this.hud.faseTexto = portal === 'SUNAT'
                ? 'Conectando con SUNAT SOL...'
                : 'Conectando con Casilla SUNAFIL...';
            this.hud.completado = false;
            this.hud.error = null;
            this.hud.abierto = true;

            let step = 0;
            this.hud.animTimer = setInterval(() => {
                step++;
                if (step === 1 && this.hud.progreso < 48) {
                    this.hud.progreso = 48;
                    this.hud.faseTexto = 'Autenticando credenciales Clave SOL...';
                } else if (step === 2 && this.hud.progreso < 74) {
                    this.hud.progreso = 74;
                    this.hud.faseTexto = portal === 'SUNAT'
                        ? 'Abriendo servicio en SUNAT...'
                        : 'Abriendo Casilla Electrónica...';
                } else if (step >= 3 && this.hud.progreso < 94) {
                    this.hud.progreso = Math.min(94, this.hud.progreso + 5);
                    this.hud.faseTexto = 'Listo, abriendo navegador...';
                }
            }, 180);

            try {
                const respuesta = await fetch(url, {
                    headers: {
                        Accept: 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                clearInterval(this.hud.animTimer);
                const data = await respuesta.json();

                if (respuesta.ok && data.success) {
                    this.hud.nombreNavegador = data.browser || 'Google Chrome';
                    this.hud.progreso = 100;
                    this.hud.completado = true;
                    this.hud.faseTexto = '¡Sesión lista!';
                    this.hud.abierto = false;
                    this.menuSunat.abierto = false;
                } else {
                    this.hud.error = data.message || 'No se pudo iniciar la sesión automática.';
                    this.hud.progreso = 0;
                }
            } catch {
                clearInterval(this.hud.animTimer);
                this.hud.error = 'Ocurrió un error al contactar al servidor.';
                this.hud.progreso = 0;
            }
        },

        ubicar(panel, ancla) {
            if (window.ubicarMenuFlotante && panel && ancla) {
                window.ubicarMenuFlotante(panel, ancla);
            }
        },

        destroy() {
            clearTimeout(this.debounce);
            this.abort?.abort();
            if (this.hud.animTimer) {
                clearInterval(this.hud.animTimer);
            }
            if (this._alRecuperarFoco) {
                window.removeEventListener('focus', this._alRecuperarFoco);
                document.removeEventListener('visibilitychange', this._alRecuperarFoco);
            }
        },
    }));
}
