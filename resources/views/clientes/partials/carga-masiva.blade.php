@php
    $cargaMasivaClientes = [
        'plantillaUrl' => route('clientes.carga_masiva.plantilla'),
        'leerUrl' => route('clientes.carga_masiva.leer'),
        'consultarUrl' => route('clientes.carga_masiva.consultar'),
        'registrarUrl' => route('clientes.carga_masiva.registrar'),
        'enlaceAfp' => \App\Models\Credencial::ENLACE_AFP,
        'enlaceBn' => \App\Models\Credencial::ENLACE_BN,
    ];
@endphp

<script>
    function cargaMasivaClientes(config) {
        return {
            config,
            abierto: false,
            paso: 'preparar',
            urlAfp: config.enlaceAfp,
            urlBn: config.enlaceBn,
            archivo: null,
            nombreArchivo: '',
            pegado: '',
            mensaje: '',
            filas: [],
            progreso: { hecho: 0, total: 0, texto: '' },
            abortar: false,
            control: null,

            abrir() {
                this.reiniciar();
                this.abierto = true;
                this.$nextTick(() => this.$refs.titulo?.focus());
            },

            reiniciar() {
                this.paso = 'preparar';
                this.urlAfp = this.config.enlaceAfp;
                this.urlBn = this.config.enlaceBn;
                this.archivo = null;
                this.nombreArchivo = '';
                this.pegado = '';
                this.mensaje = '';
                this.filas = [];
                this.progreso = { hecho: 0, total: 0, texto: '' };
                this.abortar = false;
                this.control = null;
                if (this.$refs.archivo) {
                    this.$refs.archivo.value = '';
                }
            },

            cerrar() {
                if (this.paso === 'revisando' || this.paso === 'registrando') {
                    return;
                }
                const recargar = this.filas.some((fila) => fila.registrada);
                this.abierto = false;
                if (recargar) {
                    window.location.reload();
                }
            },

            detener() {
                this.abortar = true;
                if (this.control) {
                    this.control.abort();
                }
            },

            urlValida(valor) {
                const url = (valor || '').trim();
                if (url === '') {
                    return true;
                }
                try {
                    const parsed = new URL(url);
                    return parsed.protocol === 'http:' || parsed.protocol === 'https:';
                } catch (error) {
                    return false;
                }
            },

            porcentaje() {
                if (!this.progreso.total) {
                    return 0;
                }
                return Math.round((this.progreso.hecho / this.progreso.total) * 100);
            },

            listasParaRegistrar() {
                return this.filas.filter((fila) => fila.incluir && fila.estado === 'lista' && !fila.registrada);
            },

            hayReintentables() {
                return this.filas.some((fila) => fila.reintentable && !fila.registrada);
            },

            registradas() {
                return this.filas.filter((fila) => fila.registrada).length;
            },

            ubicacion(fila) {
                if (!fila.padron) {
                    return '';
                }
                return [fila.padron.distrito, fila.padron.provincia, fila.padron.departamento].filter(Boolean).join(' · ');
            },

            elegirArchivo(evento) {
                const archivo = evento.target.files ? evento.target.files[0] : null;
                this.archivo = archivo;
                this.nombreArchivo = archivo ? archivo.name : '';
                this.mensaje = '';
            },

            quitarArchivo() {
                this.archivo = null;
                this.nombreArchivo = '';
                if (this.$refs.archivo) {
                    this.$refs.archivo.value = '';
                }
            },

            volver() {
                if (this.filas.some((fila) => fila.registrada) || this.paso === 'revisando' || this.paso === 'registrando') {
                    return;
                }
                this.paso = 'preparar';
                this.filas = [];
                this.mensaje = '';
                this.progreso = { hecho: 0, total: 0, texto: '' };
            },

            async revisar() {
                this.mensaje = '';
                if (!this.urlValida(this.urlAfp)) {
                    this.mensaje = 'El enlace de AFPnet debe comenzar con http:// o https://.';
                    return;
                }
                if (!this.urlValida(this.urlBn)) {
                    this.mensaje = 'El enlace de detracciones debe comenzar con http:// o https://.';
                    return;
                }
                if (!this.archivo && this.pegado.trim() === '') {
                    this.mensaje = 'Suba la plantilla o pegue las filas desde Excel.';
                    return;
                }

                this.paso = 'revisando';
                this.abortar = false;
                this.filas = [];
                this.progreso = { hecho: 0, total: 0, texto: 'Leyendo la plantilla…' };
                this.control = new AbortController();

                const datos = new FormData();
                if (this.archivo) {
                    datos.append('archivo', this.archivo);
                } else {
                    datos.append('contenido', this.pegado);
                }

                let respuesta;
                try {
                    respuesta = await this.enviarForm(this.config.leerUrl, datos);
                } catch (error) {
                    this.paso = 'preparar';
                    this.mensaje = this.abortar ? 'Lectura detenida.' : 'No se pudo leer la plantilla.';
                    return;
                }

                if (!respuesta.ok) {
                    this.paso = 'preparar';
                    this.mensaje = respuesta.mensaje;
                    return;
                }

                const filas = respuesta.datos.filas || [];
                if (filas.length === 0) {
                    this.paso = 'preparar';
                    const aviso = (respuesta.datos.errores || [])[0];
                    this.mensaje = aviso ? aviso.mensaje : 'La plantilla no tiene clientes para cargar.';
                    return;
                }

                this.filas = filas.map((fila) => ({
                    fila: fila.fila,
                    ruc: fila.ruc || '',
                    regimen: fila.regimen || '',
                    correo: fila.correo || '',
                    telefono: fila.telefono || '',
                    usuario_sol: fila.usuario_sol || '',
                    clave_sol: fila.clave_sol || '',
                    sunat_client_id: fila.sunat_client_id || '',
                    sunat_client_secret: fila.sunat_client_secret || '',
                    errores: fila.errores || [],
                    estado: (fila.errores || []).length ? 'observada' : 'pendiente',
                    mensaje: (fila.errores || []).join(' '),
                    reintentable: false,
                    incluir: false,
                    padron: null,
                    registrada: false,
                    numero: null,
                }));

                const pendientes = this.filas.filter((fila) => fila.estado === 'pendiente');
                if (pendientes.length === 0) {
                    this.paso = 'revision';
                    this.mensaje = 'Ninguna fila pasó la revisión. Corrija la plantilla: no se registró ningún cliente.';
                    return;
                }

                await this.consultarLista(pendientes);
            },

            async consultarLista(pendientes) {
                this.control = new AbortController();
                this.progreso = { hecho: 0, total: pendientes.length, texto: '' };
                for (let indice = 0; indice < pendientes.length; indice++) {
                    if (this.abortar) {
                        break;
                    }
                    const fila = pendientes[indice];
                    this.progreso.hecho = indice + 1;
                    this.progreso.texto = 'Validando ' + this.progreso.hecho + ' de ' + pendientes.length + ' · RUC ' + fila.ruc;
                    await this.consultarUna(fila);
                    const siguiente = pendientes[indice + 1];
                    if (this.abortar || !siguiente) {
                        continue;
                    }
                    await this.pausaAntesDelSiguiente(fila, siguiente);
                }
                this.paso = 'revision';
                if (this.abortar) {
                    this.mensaje = 'Consulta detenida. Puede reintentar las filas que faltan.';
                } else {
                    this.mensaje = '';
                }
            },

            async pausaAntesDelSiguiente(fila, siguiente) {
                if (this.abortar) {
                    return;
                }
                if (fila.reintentable) {
                    this.progreso.texto = 'RUC ' + fila.ruc + ' con observación temporal. Pausando 800 ms antes de ' + siguiente.ruc + '…';
                    await this.esperar(800);
                    return;
                }
                const razon = fila.padron && fila.padron.razon_social ? (' · ' + fila.padron.razon_social) : '';
                this.progreso.texto = 'RUC ' + fila.ruc + ' verificado' + razon + '. Pasando al siguiente…';
                await this.esperar(300);
            },

            esperar(milisegundos) {
                return new Promise((resolver) => {
                    window.setTimeout(resolver, milisegundos);
                });
            },

            async consultarUna(fila) {
                fila.estado = 'consultando';
                fila.mensaje = 'Consultando en SUNAT…';
                this.$nextTick(() => {
                    const el = document.getElementById('fila-cm-' + fila.fila);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
                    }
                });
                try {
                    const respuesta = await this.enviarJson(this.config.consultarUrl, { ruc: fila.ruc });
                    if (this.abortar) {
                        fila.estado = 'observada';
                        fila.reintentable = true;
                        fila.incluir = false;
                        fila.mensaje = 'Consulta detenida. Puede reintentarla.';
                        return;
                    }
                    const datos = respuesta.datos || {};
                    fila.mensaje = datos.mensaje || respuesta.mensaje;
                    fila.reintentable = !!datos.reintentable;
                    const padron = {
                        razon_social: datos.razon_social || '',
                        estado_sunat: datos.estado_sunat || '',
                        condicion_sunat: datos.condicion_sunat || '',
                        tipo_persona: datos.tipo_persona || '',
                        direccion_sunat: datos.direccion_sunat || '',
                        ubigeo: datos.ubigeo || '',
                        distrito: datos.distrito || '',
                        provincia: datos.provincia || '',
                        departamento: datos.departamento || '',
                    };
                    if (datos.lista) {
                        fila.estado = 'lista';
                        fila.incluir = true;
                        fila.padron = padron;
                        return;
                    }
                    fila.estado = 'observada';
                    fila.incluir = false;
                    if (padron.razon_social) {
                        fila.padron = padron;
                    }
                } catch (error) {
                    if (fila.estado === 'lista' || fila.registrada) {
                        return;
                    }
                    fila.estado = 'observada';
                    fila.incluir = false;
                    fila.reintentable = true;
                    fila.mensaje = this.abortar ? 'Consulta detenida. Puede reintentarla.' : 'No se pudo consultar SUNAT. Puede reintentar esta fila.';
                }
            },

            async reintentar() {
                const pendientes = this.filas.filter((fila) => fila.reintentable && !fila.registrada);
                if (pendientes.length === 0) {
                    return;
                }
                pendientes.forEach((fila) => {
                    fila.estado = 'pendiente';
                    fila.mensaje = '';
                    fila.reintentable = false;
                });
                this.paso = 'revisando';
                this.abortar = false;
                this.mensaje = '';
                await this.consultarLista(pendientes);
            },

            async registrar() {
                const elegidas = this.listasParaRegistrar();
                if (elegidas.length === 0 || this.paso === 'registrando') {
                    this.mensaje = elegidas.length === 0 ? 'No hay clientes listos para registrar.' : this.mensaje;
                    return;
                }
                if (!this.urlValida(this.urlAfp) || !this.urlValida(this.urlBn)) {
                    this.mensaje = 'Revise los enlaces de AFPnet y detracciones antes de registrar.';
                    return;
                }

                this.paso = 'registrando';
                this.abortar = false;
                this.mensaje = '';
                this.control = new AbortController();
                this.progreso = { hecho: 0, total: elegidas.length, texto: '' };

                for (const fila of elegidas) {
                    if (this.abortar) {
                        break;
                    }
                    this.progreso.hecho += 1;
                    this.progreso.texto = 'Registrando ' + this.progreso.hecho + ' de ' + elegidas.length + ' · RUC ' + fila.ruc;
                    let respuesta;
                    try {
                        respuesta = await this.enviarJson(this.config.registrarUrl, {
                            ruc: fila.ruc,
                            regimen: fila.regimen,
                            correo: fila.correo,
                            telefono: fila.telefono,
                            usuario_sol: fila.usuario_sol,
                            clave_sol: fila.clave_sol,
                            sunat_client_id: fila.sunat_client_id,
                            sunat_client_secret: fila.sunat_client_secret,
                            url_afp: (this.urlAfp || '').trim(),
                            url_bn: (this.urlBn || '').trim(),
                        });
                    } catch (error) {
                        fila.estado = 'observada';
                        fila.incluir = false;
                        fila.reintentable = !this.abortar;
                        fila.mensaje = this.abortar ? 'Registro detenido.' : 'No se pudo registrar este cliente. Puede reintentar la fila.';
                        if (this.abortar) {
                            break;
                        }
                        continue;
                    }

                    if (respuesta.ok && respuesta.datos.registrado) {
                        fila.registrada = true;
                        fila.estado = 'registrada';
                        fila.incluir = false;
                        fila.reintentable = false;
                        fila.numero = respuesta.datos.cliente ? respuesta.datos.cliente.numero : null;
                        fila.mensaje = respuesta.datos.mensaje || 'Registrado.';
                        if (respuesta.datos.cliente && respuesta.datos.cliente.razon_social) {
                            fila.padron = fila.padron || {};
                            fila.padron.razon_social = respuesta.datos.cliente.razon_social;
                        }
                        continue;
                    }

                    fila.estado = 'observada';
                    fila.incluir = false;
                    fila.reintentable = !!respuesta.datos.reintentable;
                    fila.mensaje = respuesta.mensaje;
                    if (respuesta.datos.motivo === 'enlace') {
                        this.mensaje = respuesta.mensaje;
                        break;
                    }
                }

                const registradas = this.registradas();
                const observadas = this.filas.filter((fila) => fila.estado === 'observada').length;
                if (this.abortar) {
                    this.paso = 'revision';
                    this.mensaje = 'Carga detenida. Los clientes que alcanzaron a registrarse ya están en la cartera.';
                    return;
                }
                if (registradas > 0 && observadas === 0) {
                    this.paso = 'listo';
                    this.mensaje = '';
                    return;
                }
                this.paso = 'revision';
                this.mensaje = registradas > 0
                    ? 'Se registraron ' + registradas + ' clientes. Las filas en rojo no entraron.'
                    : 'No se registró ningún cliente.';
            },

            async enviarJson(url, cuerpo) {
                const token = document.querySelector('meta[name="csrf-token"]');
                const respuesta = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    },
                    body: JSON.stringify(cuerpo),
                    signal: this.control ? this.control.signal : undefined,
                });
                const datos = await respuesta.json().catch(() => ({}));
                return { ok: respuesta.ok, datos, mensaje: this.primerMensaje(datos) };
            },

            async enviarForm(url, datos) {
                const token = document.querySelector('meta[name="csrf-token"]');
                const respuesta = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': token ? token.getAttribute('content') : '',
                    },
                    body: datos,
                    signal: this.control ? this.control.signal : undefined,
                });
                const json = await respuesta.json().catch(() => ({}));
                return { ok: respuesta.ok, datos: json, mensaje: this.primerMensaje(json) };
            },

            primerMensaje(datos) {
                if (datos.errors) {
                    const primero = Object.values(datos.errors).flat()[0];
                    if (primero) {
                        return primero;
                    }
                }
                return datos.mensaje || datos.message || 'No se pudo completar la operación.';
            },
        };
    }
</script>

<div x-data="cargaMasivaClientes(@js($cargaMasivaClientes))"
     x-on:abrir-carga-masiva-clientes.window="abrir()"
     x-on:keydown.escape.window="cerrar()"
     x-cloak>
    <div x-show="abierto"
         class="fixed inset-0 z-[70] flex items-start justify-center overflow-y-auto p-4 sm:p-6"
         role="dialog"
         aria-modal="true"
         aria-labelledby="carga-masiva-clientes-titulo">
        <div x-show="abierto" x-transition.opacity class="fixed inset-0 bg-slate-900/50" @click="cerrar()" aria-hidden="true"></div>

        <div class="relative z-10 my-6 w-full max-w-5xl rounded-overlay bg-surface shadow-overlay">
            <div class="flex items-start justify-between gap-4 border-b border-hairline px-6 py-4">
                <div>
                    <h2 id="carga-masiva-clientes-titulo" tabindex="-1" x-ref="titulo" class="text-base font-semibold text-neutral-950 outline-none">Carga masiva de clientes</h2>
                    <p class="mt-1 text-sm text-neutral-600">El CRM consulta SUNAT fila por fila y asigna el número como en el alta manual. Lo que ya está en la cartera no se toca.</p>
                </div>
                <button type="button" @click="cerrar()" class="rounded-control p-1.5 text-neutral-500 hover:bg-neutral-100 hover:text-neutral-800" aria-label="Cerrar">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="space-y-5 px-6 py-5">
                <div x-show="paso === 'preparar'" class="space-y-5">
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="block text-xs font-semibold text-neutral-700">
                            Enlace AFPnet
                            <input type="url" x-model="urlAfp" class="mt-1 w-full rounded-control border border-hairline px-3 py-2 text-sm text-neutral-900" spellcheck="false">
                        </label>
                        <label class="block text-xs font-semibold text-neutral-700">
                            Enlace de detracciones
                            <input type="url" x-model="urlBn" class="mt-1 w-full rounded-control border border-hairline px-3 py-2 text-sm text-neutral-900" spellcheck="false">
                        </label>
                    </div>
                    <p class="text-xs leading-relaxed text-neutral-600">Estos enlaces valen para todos los clientes de esta carga y ya traen el portal oficial. Cámbielos solo si la dirección publicada cambia. Usuario AFP, cuenta de detracciones y bancos se completan después, dentro de cada empresa.</p>

                    <div class="flex flex-wrap items-center gap-3">
                        <a href="{{ route('clientes.carga_masiva.plantilla') }}"
                           class="inline-flex items-center gap-1.5 rounded-control border border-brand-200 bg-brand-50 px-3 py-2 text-xs font-semibold text-brand-700 hover:bg-brand-100">
                            Descargar plantilla Excel
                        </a>
                        <p class="text-xs text-neutral-600">RUC, régimen, correo, teléfono, usuario y clave SOL, y credenciales API SUNAT.</p>
                    </div>

                    <details class="rounded-panel border border-hairline bg-neutral-50 px-3 py-2 text-sm text-neutral-700">
                        <summary class="cursor-pointer font-semibold">Cómo llenar la plantilla</summary>
                        <div class="mt-2 space-y-2 text-xs leading-relaxed">
                            <p>Una fila por cliente. El régimen es obligatorio porque el padrón del RUC no lo trae: escriba <span class="font-mono">NRUS</span>, <span class="font-mono">RER</span>, <span class="font-mono">RMT</span> o <span class="font-mono">General</span>.</p>
                            <p>Razón social, tipo de contribuyente, estado, condición de domicilio, dirección, ubigeo, distrito, provincia y departamento los completa el CRM al consultar SUNAT. La lectura es de un RUC a la vez: el siguiente empieza cuando el anterior ya terminó.</p>
                            <p>La clave SOL se guarda cifrada. La comprobación dentro del portal queda para cuando abra cada empresa.</p>
                            <p>Suba el Excel tal como lo descargó, o copie las filas y péguelas abajo. También se acepta CSV con punto y coma o tabulación. La columna RUC ya viene en formato texto.</p>
                        </div>
                    </details>

                    <div class="rounded-panel border border-dashed border-neutral-300 bg-neutral-50 px-4 py-5 text-center">
                        <p class="text-sm font-semibold text-neutral-900">Subir la plantilla</p>
                        <p class="mt-1 text-xs text-neutral-600">Excel (.xlsx) o CSV UTF-8. Puede subir la plantilla tal como la descargó.</p>
                        <label class="mt-3 inline-flex cursor-pointer items-center gap-2 rounded-control border border-hairline bg-white px-3 py-2 text-xs font-semibold text-neutral-800 shadow-xs hover:bg-neutral-100">
                            <input x-ref="archivo" type="file" accept=".xlsx,.csv,.txt,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv,text/plain" @change="elegirArchivo($event)" class="sr-only">
                            Seleccionar archivo
                        </label>
                        <p x-show="nombreArchivo" x-cloak class="mt-3 flex items-center justify-center gap-2 text-xs text-neutral-700">
                            <span class="font-medium" x-text="nombreArchivo"></span>
                            <button type="button" @click="quitarArchivo()" class="font-semibold text-brand-700 hover:text-brand-800">Quitar</button>
                        </p>
                    </div>

                    <label class="block text-xs font-semibold text-neutral-700">
                        O pegue aquí desde Excel
                        <textarea x-model="pegado" :disabled="archivo !== null" rows="5" class="mt-1 w-full rounded-control border border-hairline font-mono text-xs disabled:bg-neutral-100" placeholder="RUC;Régimen;Correo;Teléfono;Usuario SOL;Clave SOL;Client ID API SUNAT;Client Secret API SUNAT"></textarea>
                    </label>
                </div>

                <div x-show="paso !== 'preparar'" x-cloak class="space-y-4">
                    <div x-show="paso === 'revisando' || paso === 'registrando'" class="space-y-2">
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <p class="font-medium text-neutral-800" x-text="progreso.texto || 'Preparando…'"></p>
                            <p class="font-mono text-xs text-neutral-500" x-text="porcentaje() + '%'"></p>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-neutral-100" role="progressbar" :aria-valuenow="porcentaje()" aria-valuemin="0" aria-valuemax="100">
                            <div class="h-2 rounded-full bg-brand-600 transition-all" :style="'width: ' + porcentaje() + '%'"></div>
                        </div>
                        <p class="text-xs text-neutral-500">Se consulta un RUC y se espera a que esa lectura termine antes de empezar el siguiente. Puede detener la carga: lo ya registrado permanece.</p>
                    </div>

                    <div class="overflow-x-auto rounded-panel border border-hairline">
                        <table class="min-w-full text-left text-sm">
                            <thead class="bg-neutral-50 text-xs font-semibold uppercase tracking-wide text-neutral-500">
                                <tr>
                                    <th class="px-3 py-2">Incluir</th>
                                    <th class="px-3 py-2">Fila</th>
                                    <th class="px-3 py-2">RUC</th>
                                    <th class="px-3 py-2">Padrón SUNAT</th>
                                    <th class="px-3 py-2">Régimen</th>
                                    <th class="px-3 py-2">Resultado</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-hairline">
                                <template x-for="fila in filas" :key="fila.fila + '-' + fila.ruc">
                                    <tr :id="'fila-cm-' + fila.fila"
                                        :class="fila.registrada ? 'bg-emerald-50/60' : (fila.estado === 'consultando' ? 'bg-sky-50 ring-1 ring-inset ring-sky-300' : (fila.estado === 'observada' ? 'bg-rose-50/70' : ''))"
                                        class="transition-colors duration-150">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" class="rounded border-hairline text-brand-600"
                                                   :checked="fila.incluir"
                                                   :disabled="fila.estado !== 'lista' || paso === 'registrando' || paso === 'revisando'"
                                                   @change="fila.incluir = $event.target.checked">
                                        </td>
                                        <td class="px-3 py-2 font-mono text-xs text-neutral-500" x-text="fila.fila"></td>
                                        <td class="px-3 py-2 font-mono text-xs font-semibold text-neutral-800" x-text="fila.ruc || '—'"></td>
                                        <td class="px-3 py-2">
                                            <p class="font-medium text-neutral-900" x-text="fila.padron && fila.padron.razon_social ? fila.padron.razon_social : '—'"></p>
                                            <p class="mt-0.5 text-xs text-neutral-500" x-show="fila.padron && fila.padron.tipo_persona" x-text="fila.padron.tipo_persona"></p>
                                            <p class="text-xs text-neutral-500" x-show="fila.padron && fila.padron.direccion_sunat" x-text="fila.padron.direccion_sunat"></p>
                                            <p class="text-xs text-neutral-500" x-show="ubicacion(fila)" x-text="(fila.padron.ubigeo ? fila.padron.ubigeo + ' · ' : '') + ubicacion(fila)"></p>
                                        </td>
                                        <td class="px-3 py-2 text-xs font-semibold text-neutral-700" x-text="fila.regimen || '—'"></td>
                                        <td class="px-3 py-2 text-xs">
                                            <div class="flex items-center gap-1.5 font-medium"
                                                 :class="fila.registrada ? 'text-emerald-700' : (fila.estado === 'observada' ? 'text-rose-700' : (fila.estado === 'consultando' ? 'text-sky-700' : 'text-neutral-700'))">
                                                <template x-if="fila.estado === 'consultando'">
                                                    <svg class="h-3.5 w-3.5 animate-spin text-sky-600 shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path>
                                                    </svg>
                                                </template>
                                                <template x-if="fila.estado === 'lista' && !fila.registrada">
                                                    <svg class="h-3.5 w-3.5 text-emerald-600 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                                        <path fill-rule="evenodd" d="M16.704 4.153a.75.75 0 01.143 1.052l-8 10.5a.75.75 0 01-1.127.075l-4.5-4.5a.75.75 0 011.06-1.06l3.894 3.893 7.48-9.817a.75.75 0 011.05-.143z" clip-rule="evenodd" />
                                                    </svg>
                                                </template>
                                                <span x-text="fila.registrada ? ('Registrado · N° ' + fila.numero) : (fila.estado === 'consultando' ? 'Consultando en SUNAT…' : (fila.mensaje || 'En espera'))"></span>
                                            </div>
                                            <p class="mt-0.5 text-neutral-500" x-show="fila.padron && (fila.padron.estado_sunat || fila.padron.condicion_sunat)"
                                               x-text="[fila.padron.estado_sunat, fila.padron.condicion_sunat].filter(Boolean).join(' · ')"></p>
                                            <p class="mt-0.5 text-neutral-400" x-show="fila.usuario_sol || fila.sunat_client_id">
                                                <span x-show="fila.usuario_sol">SOL</span>
                                                <span x-show="fila.usuario_sol && fila.sunat_client_id"> · </span>
                                                <span x-show="fila.sunat_client_id">API SUNAT</span>
                                            </p>
                                        </td>
                                    </tr>
                                </template>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div x-show="paso === 'listo'" x-cloak class="rounded-panel border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    <p class="font-semibold" x-text="'Se registraron ' + registradas() + ' clientes.'"></p>
                    <p class="mt-1 text-xs">El número de cada uno es el primer correlativo libre del estudio, igual que si los hubiera creado uno por uno.</p>
                </div>

                <p x-show="mensaje" x-cloak class="rounded-panel border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900" x-text="mensaje"></p>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-hairline px-6 py-4">
                <button type="button" x-show="paso === 'revision' && registradas() === 0" @click="volver()" class="text-xs font-semibold text-neutral-600 hover:text-neutral-900">Volver a la plantilla</button>
                <span x-show="paso !== 'revision'"></span>
                <div class="flex flex-wrap items-center gap-2">
                    <button type="button" x-show="paso === 'revisando' || paso === 'registrando'" @click="detener()"
                            class="rounded-control border border-hairline bg-white px-3 py-2 text-xs font-semibold text-neutral-800 hover:bg-neutral-50">
                        Detener
                    </button>
                    <button type="button" x-show="paso === 'revision' && hayReintentables()" @click="reintentar()"
                            class="rounded-control border border-hairline bg-white px-3 py-2 text-xs font-semibold text-neutral-800 hover:bg-neutral-50">
                        Reintentar consultas
                    </button>
                    <button type="button" x-show="paso === 'preparar'" @click="revisar()"
                            class="rounded-control bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700">
                        Revisar con SUNAT
                    </button>
                    <button type="button" x-show="paso === 'revision'" @click="registrar()" :disabled="listasParaRegistrar().length === 0"
                            class="rounded-control bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700 disabled:cursor-not-allowed disabled:opacity-50">
                        <span x-text="'Registrar ' + listasParaRegistrar().length + (listasParaRegistrar().length === 1 ? ' cliente' : ' clientes')"></span>
                    </button>
                    <button type="button" x-show="paso === 'listo'" @click="cerrar()"
                            class="rounded-control bg-brand-600 px-4 py-2 text-xs font-semibold text-white hover:bg-brand-700">
                        Cerrar y ver la cartera
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
