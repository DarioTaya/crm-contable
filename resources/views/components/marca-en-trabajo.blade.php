@props(['clienteId'])

<button
    type="button"
    data-marca-en-trabajo="{{ (int) $clienteId }}"
    @click.stop="$store.enTrabajo.alternar({{ (int) $clienteId }})"
    :aria-pressed="Number($store.enTrabajo.id) === {{ (int) $clienteId }} ? 'true' : 'false'"
    :title="Number($store.enTrabajo.id) === {{ (int) $clienteId }} ? 'Quitar la marca. Sigue aquí hasta que tú la quites.' : 'Marcar: estoy trabajando en esta empresa'"
    class="inline-flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-gray-300 transition hover:bg-gray-100 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500/40"
    :class="Number($store.enTrabajo.id) === {{ (int) $clienteId }} ? '' : 'hover:text-gray-500'"
>
    <span class="sr-only" x-text="Number($store.enTrabajo.id) === {{ (int) $clienteId }} ? 'Empresa en la que estás trabajando' : 'Marcar empresa en trabajo'"></span>
    <span
        class="block h-2.5 w-2.5 rounded-full border border-current"
        :class="Number($store.enTrabajo.id) === {{ (int) $clienteId }} ? 'border-brand-600 bg-brand-600' : 'bg-transparent'"
    ></span>
</button>
