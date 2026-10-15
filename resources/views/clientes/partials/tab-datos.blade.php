        {{-- ════════════════════ PESTAÑA 1: DATOS Y ACCESOS ════════════════════ --}}
        <div x-show="tab === 'datos'" x-cloak class="mt-6 space-y-6">
            {{-- Datos Fiscales --}}
            <div class="rounded-xl border border-gray-200 bg-white shadow-xs">
                <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                    <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                        <svg class="h-4 w-4 text-brand-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3H21m-3.75 3H21" />
                        </svg>
                        Datos Fiscales
                    </h3>
                    <div class="flex items-center gap-2">
                        {{-- Botón Sincronizar SUNAT en Vivo --}}
                        <button
                            type="button"
                            @click="sincronizarEstadoSunat()"
                            :disabled="sincronizandoSunat"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 shadow-xs transition hover:bg-emerald-100 hover:text-emerald-800 hover:border-emerald-400 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 active:scale-95 disabled:opacity-60"
                        >
                            <svg class="h-3.5 w-3.5 text-emerald-600" :class="{ 'animate-spin': sincronizandoSunat }" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                            </svg>
                            <span x-text="sincronizandoSunat ? 'Sincronizando...' : 'Actualizar Estado SUNAT'"></span>
                        </button>

                        <button
                            type="button"
                            @click="modalEstadoEstudio = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-semibold text-indigo-700 shadow-xs transition hover:bg-indigo-100 hover:text-indigo-800 hover:border-indigo-300 focus:outline-none focus:ring-2 focus:ring-indigo-500/20 active:scale-95"
                            title="Gestionar estado del cliente en el estudio contable"
                        >
                            <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                            </svg>
                            Estado en Estudio
                        </button>

                        <button
                            type="button"
                            @click="modalEditarDatos = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs transition hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/20 active:scale-95"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Editar Datos Fiscales
                        </button>
                    </div>
                </div>
                {{-- Contenido Unificado dentro de la misma caja sin sub-cuadros --}}
                <div class="p-6 space-y-6">
                    {{-- Grid 1: Datos Base --}}
                    <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">RUC</dt>
                            <dd class="mt-1 font-mono text-sm text-gray-900">{{ $cliente->ruc }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Razón Social</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900" x-text="sunatInfo.razonSocial || '{{ $cliente->razon_social }}'">{{ $cliente->razon_social }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Régimen Tributario</dt>
                            <dd class="mt-1 text-sm font-medium text-gray-900" title="{{ $cliente->regimen_tributario?->nombreOficial() }}">{{ $cliente->regimen_tributario?->etiquetaCompleta() ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Correo</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $cliente->correo ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Teléfono</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $cliente->telefono ?? '—' }}</dd>
                        </div>
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Registrado en CRM</dt>
                            <dd class="mt-1 text-sm text-gray-900">{{ $cliente->created_at->isoFormat('D MMM YYYY') }}</dd>
                        </div>
                    </div>

                    {{-- Separador fino continuo --}}
                    <div class="border-t border-gray-100 pt-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                            <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <span class="text-base">🏛️</span>
                                Situación en el Padrón Oficial SUNAT
                            </h4>
                            <div class="text-[11px] text-gray-500 flex items-center gap-1.5 font-medium">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                <span>Última verificación SUNAT:</span>
                                <span class="font-semibold text-gray-700" x-text="sunatInfo.sincronizado || '{{ $cliente->fecha_sincronizacion_sunat ? $cliente->fecha_sincronizacion_sunat->isoFormat('D MMM YYYY, HH:mm') : 'No registrado' }}'">
                                    {{ $cliente->fecha_sincronizacion_sunat ? $cliente->fecha_sincronizacion_sunat->isoFormat('D MMM YYYY, HH:mm') : 'No registrado' }}
                                </span>
                            </div>
                        </div>

                        {{-- Grid 2: Datos del Padrón integrados limpiamente --}}
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Estado del Contribuyente</dt>
                                <dd class="mt-1.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                          :class="(sunatInfo.estado || '{{ $cliente->estado_contribuyente }}') === 'ACTIVO' ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                          x-text="sunatInfo.estado || '{{ $cliente->estado_contribuyente ?? '—' }}'">
                                        {{ $cliente->estado_contribuyente ?? '—' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Condición de Domicilio</dt>
                                <dd class="mt-1.5">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                          :class="(sunatInfo.condicion || '{{ $cliente->condicion_domicilio }}') === 'HABIDO' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                          x-text="sunatInfo.condicion || '{{ $cliente->condicion_domicilio ?? '—' }}'">
                                        {{ $cliente->condicion_domicilio ?? '—' }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Tipo de Contribuyente</dt>
                                <dd class="mt-1 text-sm font-medium text-gray-900"
                                    x-text="sunatInfo.tipo || '{{ $cliente->tipo_contribuyente ?? (str_starts_with($cliente->ruc, '20') ? 'Persona Jurídica (Empresa / Sociedad)' : 'Persona Natural con Negocio') }}'">
                                    {{ $cliente->tipo_contribuyente ?? (str_starts_with($cliente->ruc, '20') ? 'Persona Jurídica (Empresa / Sociedad)' : 'Persona Natural con Negocio') }}
                                </dd>
                            </div>
                            <div class="sm:col-span-2">
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Dirección Fiscal Registrada</dt>
                                <dd class="mt-1 text-sm text-gray-900 leading-relaxed"
                                    x-text="sunatInfo.direccion || '{{ $cliente->direccion_fiscal ?? '—' }}'">
                                    {{ $cliente->direccion_fiscal ?? '—' }}
                                </dd>
                            </div>
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Ubicación / Ubigeo</dt>
                                <dd class="mt-1 text-sm text-gray-900"
                                    x-text="sunatInfo.ubicacion || '{{ ($cliente->distrito ? $cliente->distrito . ', ' : '') . ($cliente->provincia ? $cliente->provincia . ' — ' : '') . ($cliente->departamento ?? '') . ($cliente->ubigeo ? ' (Ubigeo: ' . $cliente->ubigeo . ')' : '') ?: '—' }}'">
                                    {{ ($cliente->distrito ? $cliente->distrito . ', ' : '') . ($cliente->provincia ? $cliente->provincia . ' — ' : '') . ($cliente->departamento ?? '') . ($cliente->ubigeo ? ' (Ubigeo: ' . $cliente->ubigeo . ')' : '') ?: '—' }}
                                </dd>
                            </div>
                        </div>
                    </div>

                    {{-- Separador fino continuo: Control Interno del Estudio Contable --}}
                    <div class="border-t border-gray-100 pt-6">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-4">
                            <h4 class="text-sm font-semibold text-gray-900 flex items-center gap-2">
                                <span class="text-base">📋</span>
                                Control de Estado en el Estudio Contable
                            </h4>
                            <button type="button"
                                    @click="modalEstadoEstudio = true"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-brand-600 hover:text-brand-700 hover:underline">
                                <span>Cambiar Estado de Estudio</span>
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                </svg>
                            </button>
                        </div>

                        {{-- Grid 3: Datos de Control del Estudio --}}
                        <div class="grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Estado en Estudio</dt>
                                <dd class="mt-1.5">
                                    @if ($cliente->isActivo())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                                            Activo en Cartera
                                        </span>
                                    @elseif ($cliente->isSuspendido())
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                            Suspendido Temporalmente
                                        </span>
                                    @else
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-200 text-slate-800">
                                            <span class="h-1.5 w-1.5 rounded-full bg-slate-500"></span>
                                            Retirado / Baja de Estudio
                                        </span>
                                    @endif
                                </dd>
                                <p class="mt-1 text-[11px] text-gray-400">
                                    @if ($cliente->isActivo())
                                        Obligaciones mensuales activas en Control Tributario y Balances.
                                    @elseif ($cliente->isSuspendido())
                                        Servicio pausado temporalmente por honorarios o trámites pendientes.
                                    @else
                                        Finalizó servicio. A partir del mes de corte no genera pendientes.
                                    @endif
                                </p>
                            </div>

                            @if ($cliente->isRetirado())
                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Fecha y Mes de Retiro</dt>
                                    <dd class="mt-1 text-sm font-semibold text-gray-900">
                                        {{ $cliente->fecha_baja ? $cliente->fecha_baja->translatedFormat('d \d\e F, Y') : ($cliente->mes_baja ?? 'No registrada') }}
                                        @if ($cliente->mes_baja)
                                            <span class="ml-1 text-xs font-normal text-gray-500">
                                                (Corte: {{ \Carbon\Carbon::createFromFormat('Y-m', $cliente->mes_baja)->translatedFormat('M Y') }})
                                            </span>
                                        @endif
                                    </dd>
                                    <p class="mt-1 text-[11px] text-gray-400">Desde este periodo ya no genera pendientes ni alertas.</p>
                                </div>

                                <div>
                                    <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Motivo del Retiro</dt>
                                    <dd class="mt-1 text-sm font-medium text-gray-900">
                                        @php
                                            $motivos = [
                                                'cambio_contador'   => 'Cambio de Contador / Estudio Contable',
                                                'cese_actividades'  => 'Cierre / Cese de Actividades',
                                                'transferencia'     => 'Transferencia / Venta de Empresa',
                                                'decision_cliente'  => 'Decisión del Cliente',
                                                'otro'              => 'Otro motivo',
                                            ];
                                        @endphp
                                        {{ $motivos[$cliente->motivo_baja] ?? ($cliente->motivo_baja ?: 'No especificado') }}
                                    </dd>
                                </div>

                                @if ($cliente->notas_estado)
                                    <div class="sm:col-span-2 lg:col-span-3">
                                        <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Observaciones del Retiro</dt>
                                        <dd class="mt-1 rounded-lg border border-gray-100 bg-gray-50 p-3 text-xs text-gray-700 leading-relaxed">
                                            {{ $cliente->notas_estado }}
                                        </dd>
                                    </div>
                                @endif
                            @elseif ($cliente->isSuspendido() && $cliente->notas_estado)
                                <div class="sm:col-span-2">
                                    <dt class="text-xs font-medium uppercase tracking-wider text-gray-400">Detalle de la Suspensión</dt>
                                    <dd class="mt-1 rounded-lg border border-amber-100 bg-amber-50/40 p-3 text-xs text-amber-900 leading-relaxed">
                                        {{ $cliente->notas_estado }}
                                    </dd>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            {{-- MODAL: EDITAR DATOS FISCALES (Únicamente correo, régimen tributario y teléfono) --}}
            <div x-show="modalEditarDatos"
                 x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 aria-labelledby="modal-datos-fiscales-title"
                 role="dialog"
                 aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="modalEditarDatos"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
                         @click="modalEditarDatos = false"></div>

                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="modalEditarDatos"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="relative z-10 inline-block w-full max-w-lg overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl sm:my-8 sm:align-middle">
                        
                        {{-- Header del Modal --}}
                        <div class="border-b border-gray-100 bg-gray-50/70 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-100 text-brand-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                    </svg>
                                </div>
                                <div>
                                    <h3 class="text-base font-bold text-gray-900" id="modal-datos-fiscales-title">Editar Datos Fiscales</h3>
                                    <p class="text-xs text-gray-500">{{ $cliente->razon_social }}</p>
                                </div>
                            </div>
                            <button type="button" @click="modalEditarDatos = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Formulario --}}
                        <form method="POST" action="{{ route('clientes.datos-fiscales.update', $cliente) }}" class="p-6 space-y-4">
                            @csrf
                            @method('PUT')

                            {{-- RUC y Razón Social (Solo Lectura) --}}
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 rounded-xl bg-gray-50 p-3.5 border border-gray-200">
                                <div>
                                    <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500">RUC</label>
                                    <input type="text" value="{{ $cliente->ruc }}" disabled class="mt-1 block w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-1.5 font-mono text-xs text-gray-600 cursor-not-allowed select-none">
                                    <span class="text-[10px] text-gray-400">🔒 Identificador inmutable</span>
                                </div>
                                <div>
                                    <label class="block text-[11px] font-bold uppercase tracking-wider text-gray-500">Razón Social</label>
                                    <input type="text" value="{{ $cliente->razon_social }}" disabled class="mt-1 block w-full rounded-lg border border-gray-200 bg-gray-100 px-3 py-1.5 text-xs text-gray-600 cursor-not-allowed select-none truncate">
                                    <span class="text-[10px] text-gray-400">🔒 Denominación legal</span>
                                </div>
                            </div>

                            {{-- Régimen Tributario (Editable) --}}
                            <div>
                                <label for="modal_regimen_tributario" class="block text-xs font-semibold text-gray-700 mb-1">Régimen Tributario <span class="text-red-500">*</span></label>
                                <select id="modal_regimen_tributario"
                                        name="regimen_tributario"
                                        required
                                        class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @foreach (\App\Enums\RegimenTributario::cases() as $reg)
                                        <option value="{{ $reg->value }}" @selected(old('regimen_tributario', $cliente->regimen_tributario?->value) === $reg->value)>
                                            {{ $reg->etiquetaCompleta() }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-[11px] text-gray-500">Se sincroniza automáticamente con el módulo de Control de Impuestos.</p>
                            </div>

                            {{-- Correo Electrónico (Editable) --}}
                            <div>
                                <label for="modal_correo" class="block text-xs font-semibold text-gray-700 mb-1">Correo Electrónico</label>
                                <input type="email"
                                       id="modal_correo"
                                       name="correo"
                                       value="{{ old('correo', $cliente->correo) }}"
                                       placeholder="ejemplo@empresa.pe"
                                       class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                            </div>

                            {{-- Teléfono (Editable) --}}
                            <div>
                                <label for="modal_telefono" class="block text-xs font-semibold text-gray-700 mb-1">Teléfono</label>
                                <input type="text"
                                       id="modal_telefono"
                                       name="telefono"
                                       value="{{ old('telefono', $cliente->telefono) }}"
                                       placeholder="987 654 321"
                                       class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                            </div>

                            {{-- Dirección Fiscal (Editable) --}}
                            <div>
                                <label for="modal_direccion_fiscal" class="block text-xs font-semibold text-gray-700 mb-1">Dirección Fiscal</label>
                                <input type="text"
                                       id="modal_direccion_fiscal"
                                       name="direccion_fiscal"
                                       value="{{ old('direccion_fiscal', $cliente->direccion_fiscal) }}"
                                       placeholder="Ej: CALLE 4 MZA. G LOTE 1 URB. LA LAGUNA"
                                       class="block w-full rounded-lg border border-gray-300 px-3.5 py-2.5 text-sm text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                            </div>

                            {{-- Ubicación (Editable) --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2">
                                <div>
                                    <label for="modal_ubigeo" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Ubigeo</label>
                                    <input type="text"
                                           id="modal_ubigeo"
                                           name="ubigeo"
                                           value="{{ old('ubigeo', $cliente->ubigeo) }}"
                                           placeholder="040409"
                                           class="block w-full rounded-lg border border-gray-300 px-2.5 py-1.5 font-mono text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                </div>
                                <div>
                                    <label for="modal_distrito" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Distrito</label>
                                    <input type="text"
                                           id="modal_distrito"
                                           name="distrito"
                                           value="{{ old('distrito', $cliente->distrito) }}"
                                           placeholder="ORCOPAMPA"
                                           class="block w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                </div>
                                <div>
                                    <label for="modal_provincia" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Provincia</label>
                                    <input type="text"
                                           id="modal_provincia"
                                           name="provincia"
                                           value="{{ old('provincia', $cliente->provincia) }}"
                                           placeholder="CASTILLA"
                                           class="block w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                </div>
                                <div>
                                    <label for="modal_departamento" class="block text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-1">Departamento</label>
                                    <input type="text"
                                           id="modal_departamento"
                                           name="departamento"
                                           value="{{ old('departamento', $cliente->departamento) }}"
                                           placeholder="AREQUIPA"
                                           class="block w-full rounded-lg border border-gray-300 px-2.5 py-1.5 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                </div>
                            </div>

                            {{-- Buen Contribuyente SUNAT (Editable) --}}
                            <div class="rounded-xl border border-emerald-200 bg-emerald-50/40 p-3.5">
                                <label class="flex items-start gap-3 cursor-pointer">
                                    <input type="checkbox"
                                           name="es_buen_contribuyente"
                                           value="1"
                                           {{ old('es_buen_contribuyente', $cliente->es_buen_contribuyente) ? 'checked' : '' }}
                                           class="mt-0.5 h-4 w-4 rounded border-gray-300 text-emerald-600 focus:ring-emerald-500">
                                    <div>
                                        <span class="text-xs font-bold text-gray-900">⭐ Buen Contribuyente / UESP (SUNAT)</span>
                                        <p class="text-[11px] text-gray-600 mt-0.5">
                                            Aplica la fecha extendida especial del Cronograma SUNAT en PDT 621 y SIRE para Buenos Contribuyentes y Unidades Ejecutoras del Sector Público.
                                        </p>
                                    </div>
                                </label>
                            </div>

                            {{-- Botones de Acción --}}
                            <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button"
                                        @click="modalEditarDatos = false"
                                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-brand-600 px-5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500/50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Guardar Cambios
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            {{-- Botones de Acceso Rápido Automatizado (1 Clic) --}}
            @if ($cliente->credencial?->usuario_sol && $cliente->credencial?->clave_sol)
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    {{-- Botón Ingresar a SUNAT --}}
                    <div class="relative">
                    <button type="button"
                       @click.stop="abrirMenuSunat($event, '{{ route('clientes.sunat', $cliente) }}', '{{ addslashes($cliente->razon_social) }}', '{{ $cliente->ruc }}')"
                       class="group cursor-pointer flex w-full items-center justify-between gap-3 rounded-xl border-2 border-emerald-200 bg-gradient-to-r from-emerald-500 to-teal-600 px-5 py-3.5 text-left text-white shadow-md transition-all hover:from-emerald-600 hover:to-teal-700 hover:shadow-lg hover:scale-[1.01] active:scale-[0.99]">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">🚀</span>
                            <div>
                                <span class="block text-sm font-bold tracking-wide">Ingresar a SUNAT (1 Clic)</span>
                                <span class="block text-xs font-medium text-emerald-100">Elige el módulo y abre tu navegador</span>
                            </div>
                        </div>
                        <svg class="h-5 w-5 shrink-0 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                    </div>

                    {{-- Botón Ingresar a SUNAFIL --}}
                    <a href="{{ route('clientes.sunafil', $cliente) }}"
                       @click.prevent="lanzarAcceso('{{ route('clientes.sunafil', $cliente) }}', 'SUNAFIL', '{{ addslashes($cliente->razon_social) }}', '{{ $cliente->ruc }}')"
                       class="group flex items-center justify-between gap-3 rounded-xl border-2 border-blue-200 bg-gradient-to-r from-blue-600 to-indigo-600 px-5 py-3.5 text-white shadow-md transition-all hover:from-blue-700 hover:to-indigo-700 hover:shadow-lg hover:scale-[1.01] active:scale-[0.99]">
                        <div class="flex items-center gap-3">
                            <span class="text-2xl">👷</span>
                            <div>
                                <span class="block text-sm font-bold tracking-wide">Ingresar a SUNAFIL (1 Clic)</span>
                                <span class="block text-xs font-medium text-blue-100">Abre Casilla Electrónica automática</span>
                            </div>
                        </div>
                        <svg class="h-5 w-5 shrink-0 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                        </svg>
                    </a>
                </div>
            @endif

            {{-- Credenciales de Acceso --}}
            @if ($cliente->credencial)
                <div class="rounded-xl border border-gray-200 bg-white shadow-xs" x-data="credencialesClipboard()">
                    <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <h3 class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                                <svg class="h-4 w-4 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                </svg>
                                Credenciales de Acceso
                                <span class="ml-2 rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-amber-700">Cifrado AES-256</span>
                            </h3>
                        </div>
                        <button
                            type="button"
                            @click="modalEditarCredenciales = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 shadow-xs transition hover:border-amber-500 hover:bg-amber-50 hover:text-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500/20 active:scale-95"
                        >
                            <svg class="h-3.5 w-3.5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Editar Credenciales
                        </button>
                    </div>
                    <div class="divide-y divide-gray-100">
                        {{-- Clave SOL --}}
                        <div class="px-6 py-5">
                            <h4 class="mb-3 text-xs font-semibold uppercase tracking-wider text-gray-400">Clave SOL (SUNAT)</h4>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                {{-- Usuario SOL --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Usuario</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800">{{ $cliente->credencial->usuario_sol ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->usuario_sol)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->usuario_sol) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar usuario"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                {{-- Clave SOL --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->clave_sol ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->clave_sol)
                                        <button
                                            @click="copiar(claves.clave_sol, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar clave SOL al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- AFPnet --}}
                        <div class="px-6 py-5">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">AFPnet</h4>
                                <a href="{{ $cliente->credencial->enlaceAfp() }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700 hover:underline"
                                   title="Abrir portal oficial de AFPnet en nueva pestaña">
                                    <span>💼 Abrir AFPnet ↗</span>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Usuario</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800">{{ $cliente->credencial->usuario_afp ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->usuario_afp)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->usuario_afp) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar usuario AFP"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->clave_afp ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->clave_afp)
                                        <button
                                            @click="copiar(claves.clave_afp, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar clave AFP al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Detracciones --}}
                        <div class="px-6 py-5">
                            <div class="mb-3 flex items-center justify-between">
                                <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">Detracciones (Banco de la Nación)</h4>
                                <a href="{{ $cliente->credencial->enlaceBn() }}"
                                   target="_blank"
                                   rel="noopener noreferrer"
                                   class="inline-flex items-center gap-1 text-xs font-semibold text-red-600 hover:text-red-700 hover:underline"
                                   title="Abrir Banco de la Nación por Internet en nueva pestaña">
                                    <span>🏦 Abrir BN por Internet ↗</span>
                                    <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg>
                                </a>
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- N° Cuenta Detracciones --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">N° Cuenta Detracciones</p>
                                        <p class="mt-0.5 font-mono text-sm font-semibold text-gray-800">{{ $cliente->credencial->cuenta_detracciones ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->cuenta_detracciones)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->cuenta_detracciones) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar N° de cuenta"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                {{-- DNI Titular --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">DNI Titular</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800">{{ $cliente->credencial->dni_titular ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->dni_titular)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->dni_titular) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar DNI"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                                {{-- Clave --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->clave_detracciones ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->clave_detracciones)
                                        <button
                                            @click="copiar(claves.clave_detracciones, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600"
                                            title="Copiar clave detracciones al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Acceso Adicional 1 (Bancos / Libre - ej. BCP) --}}
                        <div class="px-6 py-5">
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">
                                        {{ $cliente->credencial->banco_1_nombre ?: 'Acceso Adicional 1 (Libre / Bancos)' }}
                                    </h4>
                                    @if ($cliente->credencial->banco_1_nombre)
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">Configurado</span>
                                    @endif
                                </div>
                                @if ($cliente->credencial->enlaceBanco1())
                                    <a href="{{ $cliente->credencial->enlaceBanco1() }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700 hover:underline shrink-0"
                                       title="Abrir el enlace de este acceso en una nueva pestaña">
                                        <span>Abrir {{ $cliente->credencial->banco_1_nombre ?: 'enlace' }} ↗</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </a>
                                @else
                                    <span class="text-[11px] text-gray-400 font-medium">Ej. Telecrédito BCP / Tarjeta y Clave</span>
                                @endif
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- Usuario / N° Tarjeta --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0 flex-1 mr-2">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Usuario / N° Tarjeta</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800 truncate">{{ $cliente->credencial->banco_1_usuario ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->banco_1_usuario)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->banco_1_usuario) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar usuario o tarjeta"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>

                                {{-- Clave / Contraseña --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->banco_1_clave ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->banco_1_clave)
                                        <button
                                            @click="copiar(claves.banco_1_clave, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar clave al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>

                                {{-- Notas / Detalles --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0 flex-1 mr-2">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Notas / Detalles</p>
                                        <p class="mt-0.5 text-xs text-gray-700 truncate" title="{{ $cliente->credencial->banco_1_notas ?? '' }}">
                                            {{ $cliente->credencial->banco_1_notas ?? '—' }}
                                        </p>
                                    </div>
                                    @if ($cliente->credencial->banco_1_notas)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->banco_1_notas) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar notas"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- Acceso Adicional 2 (Bancos / Libre - ej. BBVA) --}}
                        <div class="px-6 py-5">
                            <div class="mb-3 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h4 class="text-xs font-semibold uppercase tracking-wider text-gray-400">
                                        {{ $cliente->credencial->banco_2_nombre ?: 'Acceso Adicional 2 (Libre / Bancos)' }}
                                    </h4>
                                    @if ($cliente->credencial->banco_2_nombre)
                                        <span class="rounded-full bg-blue-50 px-2 py-0.5 text-[10px] font-semibold text-blue-700">Configurado</span>
                                    @endif
                                </div>
                                @if ($cliente->credencial->enlaceBanco2())
                                    <a href="{{ $cliente->credencial->enlaceBanco2() }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="inline-flex items-center gap-1 text-xs font-semibold text-sky-600 hover:text-sky-700 hover:underline shrink-0"
                                       title="Abrir el enlace de este acceso en una nueva pestaña">
                                        <span>Abrir {{ $cliente->credencial->banco_2_nombre ?: 'enlace' }} ↗</span>
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                        </svg>
                                    </a>
                                @else
                                    <span class="text-[11px] text-gray-400 font-medium">Ej. BBVA Net Cash / Tarjeta y Clave</span>
                                @endif
                            </div>
                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                                {{-- Usuario / N° Tarjeta --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0 flex-1 mr-2">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Usuario / N° Tarjeta</p>
                                        <p class="mt-0.5 font-mono text-sm text-gray-800 truncate">{{ $cliente->credencial->banco_2_usuario ?? '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->banco_2_usuario)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->banco_2_usuario) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar usuario o tarjeta"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>

                                {{-- Clave / Contraseña --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div>
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Clave</p>
                                        <p class="mt-0.5 font-mono text-sm tracking-widest text-gray-800">{{ $cliente->credencial->banco_2_clave ? '••••••••' : '—' }}</p>
                                    </div>
                                    @if ($cliente->credencial->banco_2_clave)
                                        <button
                                            @click="copiar(claves.banco_2_clave, $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar clave al portapapeles"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>

                                {{-- Notas / Detalles --}}
                                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-gray-50 px-4 py-3">
                                    <div class="min-w-0 flex-1 mr-2">
                                        <p class="text-[10px] font-medium uppercase tracking-wider text-gray-400">Notas / Detalles</p>
                                        <p class="mt-0.5 text-xs text-gray-700 truncate" title="{{ $cliente->credencial->banco_2_notas ?? '' }}">
                                            {{ $cliente->credencial->banco_2_notas ?? '—' }}
                                        </p>
                                    </div>
                                    @if ($cliente->credencial->banco_2_notas)
                                        <button
                                            @click="copiar('{{ e($cliente->credencial->banco_2_notas) }}', $event)"
                                            type="button"
                                            class="rounded-md p-1.5 text-gray-400 transition hover:bg-gray-200 hover:text-gray-600 shrink-0"
                                            title="Copiar notas"
                                        >
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0 0 13.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 0 1-.75.75H9.75a.75.75 0 0 1-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 0 1-2.25 2.25H6.75A2.25 2.25 0 0 1 4.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 0 1 1.927-.184" />
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @else
                <div class="rounded-xl border border-dashed border-gray-300 bg-white px-6 py-12 text-center">
                    <svg class="mx-auto mb-3 h-10 w-10 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                    </svg>
                    <p class="text-sm font-medium text-gray-500">No se han registrado credenciales para este cliente.</p>
                    <button
                        type="button"
                        @click="modalEditarCredenciales = true"
                        class="mt-4 inline-flex items-center gap-2 rounded-lg bg-amber-600 px-4 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500/40"
                    >
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Registrar Credenciales
                    </button>
                </div>
            @endif

            {{-- MODAL: EDITAR CREDENCIALES DE ACCESO (Todos los accesos + Banco de la Nación Detracciones) --}}
            <div x-show="modalEditarCredenciales"
                 x-cloak
                 class="fixed inset-0 z-50 overflow-y-auto"
                 aria-labelledby="modal-credenciales-title"
                 role="dialog"
                 aria-modal="true">
                <div class="flex min-h-screen items-end justify-center px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                    <div x-show="modalEditarCredenciales"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         class="fixed inset-0 z-0 bg-gray-900/60 backdrop-blur-xs transition-opacity"
                         @click="modalEditarCredenciales = false"></div>

                    <span class="hidden sm:inline-block sm:h-screen sm:align-middle" aria-hidden="true">&#8203;</span>

                    <div x-show="modalEditarCredenciales"
                         x-transition:enter="ease-out duration-300"
                         x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave="ease-in duration-200"
                         x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                         x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                         class="relative z-10 inline-block w-full max-w-xl overflow-hidden rounded-2xl bg-white text-left align-bottom shadow-2xl sm:my-8 sm:align-middle">
                        
                        {{-- Header del Modal --}}
                        <div class="border-b border-gray-100 bg-amber-50/50 px-6 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-100 text-amber-600">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h3 class="text-base font-bold text-gray-900" id="modal-credenciales-title">Credenciales de Acceso</h3>
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">Cifrado AES-256</span>
                                    </div>
                                    <p class="text-xs text-gray-500">{{ $cliente->razon_social }}</p>
                                </div>
                            </div>
                            <button type="button" @click="modalEditarCredenciales = false" class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>

                        {{-- Formulario --}}
                        <form method="POST" action="{{ route('clientes.credenciales.update', $cliente) }}" class="p-6 space-y-5 max-h-[75vh] overflow-y-auto">
                            @csrf
                            @method('PUT')

                            {{-- SECCIÓN 1: CLAVE SOL SUNAT --}}
                            <div class="rounded-xl border border-emerald-100 bg-emerald-50/30 p-4 space-y-3">
                                <h4 class="text-xs font-bold uppercase tracking-wider text-emerald-800 flex items-center gap-1.5">
                                    <span>🚀</span> Clave SOL (SUNAT)
                                </h4>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="modal_usuario_sol" class="block text-xs font-semibold text-gray-700 mb-1">Usuario SOL</label>
                                        <input type="text"
                                               id="modal_usuario_sol"
                                               name="usuario_sol"
                                               value="{{ old('usuario_sol', $cliente->credencial?->usuario_sol) }}"
                                               placeholder="MODDATOS"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_clave_sol" class="block text-xs font-semibold text-gray-700 mb-1">Clave SOL</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_clave_sol"
                                                   name="clave_sol"
                                                   value="{{ old('clave_sol', $cliente->credencial?->clave_sol) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN 2: AFPNET --}}
                            <div class="rounded-xl border border-blue-100 bg-blue-50/30 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-blue-800 flex items-center gap-1.5">
                                        <span>💼</span> AFPnet
                                    </h4>
                                    <a href="{{ $cliente->credencial?->enlaceAfp() ?? \App\Models\Credencial::ENLACE_AFP }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="text-[11px] font-semibold text-sky-600 hover:underline flex items-center gap-1">
                                        AFPnet Oficial ↗
                                    </a>
                                </div>
                                <div>
                                    <label for="modal_url_afp" class="block text-xs font-semibold text-gray-700 mb-1">Enlace</label>
                                    <input type="url"
                                           id="modal_url_afp"
                                           name="url_afp"
                                           value="{{ old('url_afp', $cliente->credencial?->url_afp ?: \App\Models\Credencial::ENLACE_AFP) }}"
                                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('url_afp')
                                        <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="modal_usuario_afp" class="block text-xs font-semibold text-gray-700 mb-1">Usuario AFP</label>
                                        <input type="text"
                                               id="modal_usuario_afp"
                                               name="usuario_afp"
                                               value="{{ old('usuario_afp', $cliente->credencial?->usuario_afp) }}"
                                               placeholder="Usuario AFP"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_clave_afp" class="block text-xs font-semibold text-gray-700 mb-1">Clave AFP</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_clave_afp"
                                                   name="clave_afp"
                                                   value="{{ old('clave_afp', $cliente->credencial?->clave_afp) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN 3: DETRACCIONES (BANCO DE LA NACIÓN) --}}
                            <div class="rounded-xl border border-red-100 bg-red-50/30 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-red-800 flex items-center gap-1.5">
                                        <span>🏦</span> Detracciones (Banco de la Nación)
                                    </h4>
                                    <a href="{{ $cliente->credencial?->enlaceBn() ?? \App\Models\Credencial::ENLACE_BN }}"
                                       target="_blank"
                                       rel="noopener noreferrer"
                                       class="text-[11px] font-semibold text-red-600 hover:underline flex items-center gap-1">
                                        BN por Internet ↗
                                    </a>
                                </div>
                                <div>
                                    <label for="modal_url_bn" class="block text-xs font-semibold text-gray-700 mb-1">Enlace</label>
                                    <input type="url"
                                           id="modal_url_bn"
                                           name="url_bn"
                                           value="{{ old('url_bn', $cliente->credencial?->url_bn ?: \App\Models\Credencial::ENLACE_BN) }}"
                                           class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    @error('url_bn')
                                        <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div>
                                        <label for="modal_dni_titular" class="block text-xs font-semibold text-gray-700 mb-1">DNI Titular</label>
                                        <input type="text"
                                               id="modal_dni_titular"
                                               name="dni_titular"
                                               maxlength="8"
                                               value="{{ old('dni_titular', $cliente->credencial?->dni_titular) }}"
                                               placeholder="8 dígitos"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_clave_detracciones" class="block text-xs font-semibold text-gray-700 mb-1">Clave BN</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_clave_detracciones"
                                                   name="clave_detracciones"
                                                   value="{{ old('clave_detracciones', $cliente->credencial?->clave_detracciones) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div>
                                        <label for="modal_cuenta_detracciones" class="block text-xs font-semibold text-gray-700 mb-1">N° Cuenta Detracciones</label>
                                        <input type="text"
                                               id="modal_cuenta_detracciones"
                                               name="cuenta_detracciones"
                                               maxlength="50"
                                               value="{{ old('cuenta_detracciones', $cliente->credencial?->cuenta_detracciones) }}"
                                               placeholder="00-000-000000"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN 4: ACCESO ADICIONAL 1 (LIBRE / BANCOS - EJ. BCP) --}}
                            <div class="rounded-xl border border-indigo-100 bg-indigo-50/30 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-indigo-900">
                                        Acceso Adicional 1 (Bancos / Libre - Ej. BCP)
                                    </h4>
                                    <div class="flex items-center gap-3">
                                        @if ($cliente->credencial?->enlaceBanco1())
                                            <a href="{{ $cliente->credencial->enlaceBanco1() }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="text-[11px] font-semibold text-sky-600 hover:underline flex items-center gap-1">
                                                {{ $cliente->credencial->banco_1_nombre ?: 'Enlace' }} ↗
                                            </a>
                                        @endif
                                        <span class="text-[10px] text-gray-500 font-medium">Opcional</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_1_nombre" class="block text-xs font-semibold text-gray-700 mb-1">Nombre o Entidad</label>
                                        <input type="text"
                                               id="modal_banco_1_nombre"
                                               name="banco_1_nombre"
                                               value="{{ old('banco_1_nombre', $cliente->credencial?->banco_1_nombre) }}"
                                               placeholder="Ej: Telecrédito BCP"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div>
                                        <label for="modal_banco_1_usuario" class="block text-xs font-semibold text-gray-700 mb-1">Usuario / N° Tarjeta</label>
                                        <input type="text"
                                               id="modal_banco_1_usuario"
                                               name="banco_1_usuario"
                                               value="{{ old('banco_1_usuario', $cliente->credencial?->banco_1_usuario) }}"
                                               placeholder="Ej: 4557 0000 0000 0000"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_banco_1_clave" class="block text-xs font-semibold text-gray-700 mb-1">Clave / Contraseña</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_banco_1_clave"
                                                   name="banco_1_clave"
                                                   value="{{ old('banco_1_clave', $cliente->credencial?->banco_1_clave) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_1_notas" class="block text-xs font-semibold text-gray-700 mb-1">Notas / Detalles Adicionales</label>
                                        <input type="text"
                                               id="modal_banco_1_notas"
                                               name="banco_1_notas"
                                               value="{{ old('banco_1_notas', $cliente->credencial?->banco_1_notas) }}"
                                               placeholder="Ej: Para descarga de movimientos bancarios y extractos"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_1_url" class="block text-xs font-semibold text-gray-700 mb-1">Enlace</label>
                                        <input type="url"
                                               id="modal_banco_1_url"
                                               name="banco_1_url"
                                               value="{{ old('banco_1_url', $cliente->credencial?->banco_1_url) }}"
                                               placeholder="https://"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                        @error('banco_1_url')
                                            <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- SECCIÓN 5: ACCESO ADICIONAL 2 (LIBRE / BANCOS - EJ. BBVA) --}}
                            <div class="rounded-xl border border-sky-100 bg-sky-50/30 p-4 space-y-3">
                                <div class="flex items-center justify-between">
                                    <h4 class="text-xs font-bold uppercase tracking-wider text-sky-900">
                                        Acceso Adicional 2 (Bancos / Libre - Ej. BBVA)
                                    </h4>
                                    <div class="flex items-center gap-3">
                                        @if ($cliente->credencial?->enlaceBanco2())
                                            <a href="{{ $cliente->credencial->enlaceBanco2() }}"
                                               target="_blank"
                                               rel="noopener noreferrer"
                                               class="text-[11px] font-semibold text-sky-600 hover:underline flex items-center gap-1">
                                                {{ $cliente->credencial->banco_2_nombre ?: 'Enlace' }} ↗
                                            </a>
                                        @endif
                                        <span class="text-[10px] text-gray-500 font-medium">Opcional</span>
                                    </div>
                                </div>
                                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_2_nombre" class="block text-xs font-semibold text-gray-700 mb-1">Nombre o Entidad</label>
                                        <input type="text"
                                               id="modal_banco_2_nombre"
                                               name="banco_2_nombre"
                                               value="{{ old('banco_2_nombre', $cliente->credencial?->banco_2_nombre) }}"
                                               placeholder="Ej: BBVA Net Cash"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div>
                                        <label for="modal_banco_2_usuario" class="block text-xs font-semibold text-gray-700 mb-1">Usuario / N° Tarjeta</label>
                                        <input type="text"
                                               id="modal_banco_2_usuario"
                                               name="banco_2_usuario"
                                               value="{{ old('banco_2_usuario', $cliente->credencial?->banco_2_usuario) }}"
                                               placeholder="Ej: BBVA-USER-123"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs font-mono text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div x-data="{ showClave: false }">
                                        <label for="modal_banco_2_clave" class="block text-xs font-semibold text-gray-700 mb-1">Clave / Contraseña</label>
                                        <div class="relative">
                                            <input :type="showClave ? 'text' : 'password'"
                                                   id="modal_banco_2_clave"
                                                   name="banco_2_clave"
                                                   value="{{ old('banco_2_clave', $cliente->credencial?->banco_2_clave) }}"
                                                   placeholder="••••••••"
                                                   class="block w-full rounded-lg border border-gray-300 px-3 py-2 pr-9 text-xs text-gray-900 font-mono shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                            <button type="button"
                                                    @click="showClave = !showClave"
                                                    class="absolute inset-y-0 right-0 flex items-center pr-2.5 text-gray-400 hover:text-gray-600">
                                                <span x-show="!showClave" title="Ver contraseña">👁️</span>
                                                <span x-show="showClave" title="Ocultar">🙈</span>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_2_notas" class="block text-xs font-semibold text-gray-700 mb-1">Notas / Detalles Adicionales</label>
                                        <input type="text"
                                               id="modal_banco_2_notas"
                                               name="banco_2_notas"
                                               value="{{ old('banco_2_notas', $cliente->credencial?->banco_2_notas) }}"
                                               placeholder="Ej: Token digital en celular de administración"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                    </div>
                                    <div class="sm:col-span-2">
                                        <label for="modal_banco_2_url" class="block text-xs font-semibold text-gray-700 mb-1">Enlace</label>
                                        <input type="url"
                                               id="modal_banco_2_url"
                                               name="banco_2_url"
                                               value="{{ old('banco_2_url', $cliente->credencial?->banco_2_url) }}"
                                               placeholder="https://"
                                               class="block w-full rounded-lg border border-gray-300 px-3 py-2 text-xs text-gray-900 shadow-xs focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20">
                                        @error('banco_2_url')
                                            <p class="mt-1 text-[11px] text-red-600">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            {{-- Botones de Acción --}}
                            <div class="mt-6 flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                                <button type="button"
                                        @click="modalEditarCredenciales = false"
                                        class="rounded-lg border border-gray-300 bg-white px-4 py-2 text-xs font-semibold text-gray-700 shadow-xs transition hover:bg-gray-50">
                                    Cancelar
                                </button>
                                <button type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-lg bg-amber-600 px-5 py-2 text-xs font-semibold text-white shadow-xs transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500/50">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Guardar Credenciales
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    {{-- ═══════════════════ PESTAÑA 5: CREDENCIALES API SUNAT ═══════════════════ --}}
    <div x-show="tab === 'datos'" x-cloak class="mt-6 space-y-6">
        {{-- Card Principal --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-xs" x-data="apiCredentialsForm()">
            <div class="border-b border-gray-100 px-6 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 0 1 3 3m3 0a6 6 0 0 1-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1 1 21.75 8.25Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Credenciales API SUNAT</h3>
                        <p class="text-xs text-gray-500">Client ID y Client Secret para integraciones con la API de SUNAT (SIRE, facturación electrónica)</p>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <template x-if="clientIdVisible">
                        <div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-green-50 border border-green-200 px-3 py-1 text-xs font-semibold text-green-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-green-500"></span>
                                Configurado
                            </span>
                            <p class="mt-1 max-w-[16rem] truncate font-mono text-xs text-gray-800" x-text="clientIdVisible" title="Client ID"></p>
                        </div>
                    </template>
                    <template x-if="!clientIdVisible">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-500">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                            Sin configurar
                        </span>
                    </template>
                </div>
            </div>

            <div class="px-6 py-5">
                {{-- Toast de feedback --}}
                <div x-show="toast.mostrar"
                     x-cloak
                     x-transition:enter="transform ease-out duration-300 transition"
                     x-transition:enter-start="translate-y-2 opacity-0"
                     x-transition:enter-end="translate-y-0 opacity-100"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="mb-4 flex items-center gap-3 rounded-lg border px-4 py-3"
                     :class="toast.tipo === 'success'
                         ? 'bg-green-50 border-green-200 text-green-800'
                         : 'bg-red-50 border-red-200 text-red-800'">
                    <template x-if="toast.tipo === 'success'">
                        <svg class="h-4 w-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </template>
                    <template x-if="toast.tipo === 'error'">
                        <svg class="h-4 w-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </template>
                    <p class="text-sm font-medium" x-text="toast.mensaje"></p>
                </div>

                <form @submit.prevent="guardar" autocomplete="off" class="space-y-5">
                    {{-- Campos señuelo: el navegador vuelca aquí el usuario y la
                         clave de la sesión en lugar de tocar Client ID / Secret. --}}
                    <div class="hidden" aria-hidden="true">
                        <input type="text" name="usuario" autocomplete="username" tabindex="-1">
                        <input type="password" name="clave" autocomplete="current-password" tabindex="-1">
                    </div>

                    {{-- Client ID --}}
                    <div>
                        <label for="api_client_id" class="block text-sm font-medium text-gray-700 mb-1">Client ID</label>
                        <input type="text"
                               id="api_client_id"
                               name="sunat_api_client_id"
                               autocomplete="off"
                               autocapitalize="off"
                               spellcheck="false"
                               data-1p-ignore
                               data-lpignore="true"
                               readonly
                               @focus="$el.removeAttribute('readonly')"
                               x-model="form.sunat_client_id"
                               placeholder="Ej: 4b2c8a1e-f3d9-4c7b-a8e5-1234567890ab"
                               class="block w-full rounded-lg border-gray-300 shadow-xs focus:border-brand-500 focus:ring-brand-500 sm:text-sm font-mono" />
                    </div>

                    {{-- Client Secret. Es texto, no password: si el tipo fuera
                         password el navegador lo trata como un inicio de sesión
                         y rellena la clave del estudio. --}}
                    <div>
                        <label for="api_client_secret" class="block text-sm font-medium text-gray-700 mb-1">Client Secret</label>
                        <div class="relative">
                            <input type="text"
                                   id="api_client_secret"
                                   name="sunat_api_client_secret"
                                   autocomplete="off"
                                   autocapitalize="off"
                                   spellcheck="false"
                                   data-1p-ignore
                                   data-lpignore="true"
                                   readonly
                                   @focus="prepararSecreto($el)"
                                   x-model="form.sunat_client_secret"
                                   :style="mostrarSecret ? '' : '-webkit-text-security: disc'"
                                   placeholder="{{ ! empty($cliente->sunat_client_secret) ? '••••••••••••••••' : 'Ingrese el Client Secret de la aplicación registrada' }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-xs focus:border-brand-500 focus:ring-brand-500 sm:text-sm font-mono pr-10" />
                            <button type="button"
                                    @click="alternarSecreto()"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition"
                                    title="Mostrar / ocultar clave">
                                <template x-if="!mostrarSecret">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </template>
                                <template x-if="mostrarSecret">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </template>
                            </button>
                        </div>
                        @if (! empty($cliente->sunat_client_secret))
                            <p class="mt-1 text-[11px] text-gray-500">Deje este campo vacío si no desea modificar el Client Secret configurado.</p>
                        @endif
                    </div>

                    {{-- Botón Guardar --}}
                    <div class="flex items-center justify-between pt-2">
                        <button type="submit"
                                :disabled="guardando"
                                class="inline-flex items-center gap-2 rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-brand-700 focus:outline-none focus:ring-2 focus:ring-brand-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition">
                            <template x-if="!guardando">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </template>
                            <template x-if="guardando">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="guardando ? 'Guardando...' : '{{ ! empty($cliente->sunat_client_id) ? 'Actualizar Credenciales' : 'Guardar Credenciales' }}'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- Card de Ayuda SIRE/SOL --}}
        <div class="rounded-xl border border-amber-200 bg-amber-50/50 shadow-xs">
            <div class="px-6 py-4">
                <div class="flex items-start gap-3">
                    <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-600 mt-0.5">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                    </div>
                    <div>
                        <h4 class="text-sm font-semibold text-amber-900">Cómo obtener credenciales de SIRE / Factoring / GRE</h4>
                        <ol class="mt-2 space-y-1.5 text-xs text-amber-800 list-decimal list-inside">
                            <li>Ingresar al portal <span class="font-semibold">SUNAT SOL</span> con la Clave SOL del contribuyente.</li>
                            <li>Navegar a: <span class="font-mono bg-amber-100 px-1 py-0.5 rounded text-[11px]">Empresas &gt; Credenciales de API SUNAT &gt; Credenciales de API SUNAT &gt; Gestión Credenciales de API SUNAT</span>.</li>
                            <li>Registrar una aplicación. En <span class="font-bold text-amber-950">'Alcance'</span> seleccionar obligatoriamente <span class="font-bold underline text-amber-950">'Desktop'</span> <span class="text-amber-700">(SUNAT bloquea las llamadas API SIRE con Error 401 si se selecciona 'Web')</span>.</li>
                            <li>Marcar obligatoriamente el check: <span class="font-semibold font-mono bg-amber-100 px-1 py-0.5 rounded text-[11px]">'MIGE RCE y RVIE - SIRE /v1/contribuyente/migeigv'</span>.</li>
                            <li>Copiar el <span class="font-semibold">'ID'</span> y la <span class="font-semibold">'CLAVE'</span> y pegarlos en el formulario superior.</li>
                        </ol>
                        <p class="mt-3 text-[11px] text-amber-700 flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
                            </svg>
                            El Client Secret se almacena cifrado con AES-256-CBC en la base de datos.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ═══════════════════ CARD: CREDENCIALES API SUNAT — VALIDEZ DE COMPROBANTES (CPE) ═══════════════════ --}}
        <div class="rounded-xl border border-teal-200 bg-white shadow-xs overflow-hidden" x-data="cpeCredentialsForm()">
            <div class="border-b border-teal-100 bg-teal-50/40 px-6 py-4 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-10 w-10 items-center justify-center rounded-lg bg-teal-100 text-teal-700">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h3 class="text-base font-semibold text-gray-900">Credenciales API SUNAT — Consulta de Validez CPE</h3>
                            <span class="inline-flex items-center rounded-md bg-teal-100 px-2 py-0.5 text-[10px] font-bold text-teal-800 tracking-wide">OFICIAL SUNAT</span>
                        </div>
                        <p class="text-xs text-gray-600">ID y Clave exclusivos para la API de Consulta de Validez integrada (Facturas, Boletas, NC, ND, Guías y condición de Habido).</p>
                    </div>
                </div>
                <div class="text-right shrink-0">
                    <template x-if="clientIdVisible">
                        <div>
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-3 py-1 text-xs font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                Configurado
                            </span>
                            <p class="mt-1 max-w-[16rem] truncate font-mono text-xs text-gray-800" x-text="clientIdVisible" title="Client ID CPE"></p>
                        </div>
                    </template>
                    <template x-if="!clientIdVisible">
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-gray-50 border border-gray-200 px-3 py-1 text-xs font-semibold text-gray-500">
                            <span class="h-1.5 w-1.5 rounded-full bg-gray-400"></span>
                            Sin configurar
                        </span>
                    </template>
                </div>
            </div>

            <div class="px-6 py-5 space-y-5">
                {{-- Ruta de navegación exacta en SUNAT SOL --}}
                <div class="rounded-lg border border-teal-200 bg-teal-50/60 p-3.5 text-xs text-teal-900">
                    <div class="flex items-start gap-2.5">
                        <svg class="h-4 w-4 text-teal-700 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
                        </svg>
                        <div class="space-y-1">
                            <p class="font-semibold text-teal-950">Ruta oficial en el portal SUNAT Operaciones en Línea (SOL):</p>
                            <p class="font-mono bg-white/80 border border-teal-200 rounded px-2 py-1 text-[11px] text-teal-900 select-all font-medium">
                                EMPRESAS &gt; COMPROBANTES DE PAGO &gt; Consulta de Validez de Comprobante de Pago &gt; Consulta de Validez de Comprobante de Pago &gt; Credenciales de API SUNAT
                            </p>
                            <p class="text-[11px] text-teal-800">
                                Registre una aplicación (Nombre y URL) para obtener el <strong>Id</strong> y la <strong>Clave</strong> exclusivos de este servicio web.
                            </p>
                        </div>
                    </div>
                </div>

                {{-- Toast de feedback --}}
                <div x-show="toast.mostrar"
                     x-cloak
                     x-transition
                     class="flex items-center gap-3 rounded-lg border px-4 py-3"
                     :class="toast.tipo === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'">
                    <template x-if="toast.tipo === 'success'">
                        <svg class="h-4 w-4 text-green-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </template>
                    <template x-if="toast.tipo === 'error'">
                        <svg class="h-4 w-4 text-red-500 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </template>
                    <p class="text-sm font-medium" x-text="toast.mensaje"></p>
                </div>

                {{-- Resultado del Test de Conexión en Vivo --}}
                <div x-show="testResultado !== null" x-cloak class="rounded-lg border p-3.5 text-xs transition"
                     :class="testResultado?.success ? 'bg-emerald-50 border-emerald-300 text-emerald-900' : 'bg-rose-50 border-rose-300 text-rose-900'">
                    <div class="flex items-start gap-2.5">
                        <template x-if="testResultado?.success">
                            <svg class="h-4 w-4 text-emerald-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </template>
                        <template x-if="!testResultado?.success">
                            <svg class="h-4 w-4 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        </template>
                        <div class="flex-1 min-w-0">
                            <p class="font-semibold text-sm" x-text="testResultado?.message"></p>
                            <template x-if="testResultado?.latencia_ms">
                                <p class="text-[11px] mt-0.5 opacity-80" x-text="'Tiempo de respuesta: ' + testResultado.latencia_ms + ' ms'"></p>
                            </template>
                        </div>
                        <button type="button" @click="testResultado = null" class="text-gray-400 hover:text-gray-600 font-bold px-1">&times;</button>
                    </div>
                </div>

                <form @submit.prevent="guardar" autocomplete="off" class="space-y-4">
                    {{-- Señuelos anti autofill --}}
                    <div class="hidden" aria-hidden="true">
                        <input type="text" name="usuario_cpe_fake" tabindex="-1">
                        <input type="password" name="clave_cpe_fake" tabindex="-1">
                    </div>

                    {{-- Client ID CPE --}}
                    <div>
                        <label for="cpe_client_id" class="block text-sm font-medium text-gray-700 mb-1">Id de Aplicación (Validez CPE)</label>
                        <input type="text"
                               id="cpe_client_id"
                               name="sunat_cpe_client_id"
                               autocomplete="off"
                               spellcheck="false"
                               data-1p-ignore
                               data-lpignore="true"
                               readonly
                               @focus="$el.removeAttribute('readonly')"
                               x-model="form.sunat_cpe_client_id"
                               placeholder="Ej: 49b922f1-e6a5-43a9-9fdd-8c03c3323cc2"
                               class="block w-full rounded-lg border-gray-300 shadow-xs focus:border-teal-500 focus:ring-teal-500 sm:text-sm font-mono" />
                    </div>

                    {{-- Client Secret CPE --}}
                    <div>
                        <label for="cpe_client_secret" class="block text-sm font-medium text-gray-700 mb-1">Clave de Aplicación (Validez CPE)</label>
                        <div class="relative">
                            <input type="text"
                                   id="cpe_client_secret"
                                   name="sunat_cpe_client_secret"
                                   autocomplete="off"
                                   spellcheck="false"
                                   data-1p-ignore
                                   data-lpignore="true"
                                   readonly
                                   @focus="prepararSecreto($el)"
                                   x-model="form.sunat_cpe_client_secret"
                                   :style="mostrarSecret ? '' : '-webkit-text-security: disc'"
                                   placeholder="{{ ! empty($cliente->sunat_cpe_client_secret) ? '••••••••••••••••' : 'Ej: hpj9ALEkYYhajchPQzu3GA==' }}"
                                   class="block w-full rounded-lg border-gray-300 shadow-xs focus:border-teal-500 focus:ring-teal-500 sm:text-sm font-mono pr-10" />
                            <button type="button"
                                    @click="alternarSecreto()"
                                    class="absolute inset-y-0 right-0 flex items-center pr-3 text-gray-400 hover:text-gray-600 transition"
                                    title="Mostrar / ocultar clave">
                                <template x-if="!mostrarSecret">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    </svg>
                                </template>
                                <template x-if="mostrarSecret">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                                    </svg>
                                </template>
                            </button>
                        </div>
                    </div>

                    {{-- Botones de Acción --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2">
                        <button type="submit"
                                :disabled="guardando"
                                class="inline-flex items-center gap-2 rounded-lg bg-teal-600 px-4 py-2.5 text-sm font-semibold text-white shadow-xs hover:bg-teal-700 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition">
                            <template x-if="!guardando">
                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                </svg>
                            </template>
                            <template x-if="guardando">
                                <svg class="h-4 w-4 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="guardando ? 'Guardando...' : 'Guardar Credenciales CPE'"></span>
                        </button>

                        <button type="button"
                                @click="probarConexion()"
                                :disabled="probando || !form.sunat_cpe_client_id"
                                class="inline-flex items-center gap-2 rounded-lg border border-teal-300 bg-teal-50 px-3.5 py-2.5 text-sm font-semibold text-teal-800 shadow-2xs hover:bg-teal-100 focus:outline-none focus:ring-2 focus:ring-teal-500 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed transition cursor-pointer">
                            <template x-if="!probando">
                                <svg class="h-4 w-4 text-teal-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.288 15.038a5.25 5.25 0 0 1 7.424 0M5.106 11.856c3.807-3.808 9.98-3.808 13.788 0M1.924 8.674c5.565-5.565 14.587-5.565 20.152 0M12.53 18.22l-.53.53-.53-.53a.75.75 0 0 1 1.06 0Z" />
                                </svg>
                            </template>
                            <template x-if="probando">
                                <svg class="h-4 w-4 animate-spin text-teal-600" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </template>
                            <span x-text="probando ? 'Probando conexión con SUNAT...' : 'Probar Conexión con SUNAT'"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    @include('clientes.partials.menu-accesos-sunat')
    </div>
