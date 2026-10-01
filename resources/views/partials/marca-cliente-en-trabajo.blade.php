{{-- Marca personal de la empresa en curso. Vive en este navegador, por usuario,
     y solo se quita cuando el mismo contador vuelve a pulsar el punto. --}}
<style>
    tr.marca-en-trabajo > td {
        box-shadow: inset 0 1px 0 #dbeafe, inset 0 -1px 0 #dbeafe;
    }
    tr.marca-en-trabajo > td:first-child {
        background-color: #eff6ff !important;
        box-shadow: inset 3px 0 0 #2563eb, inset 0 1px 0 #dbeafe, inset 0 -1px 0 #dbeafe, 2px 0 5px -2px rgba(0, 0, 0, 0.12) !important;
    }
</style>
<script>
    document.addEventListener('alpine:init', () => {
        const clave = @js('crm-cliente-en-trabajo-'.auth()->id());
        const idsEnMatriz = @js(array_values(array_map('intval', $idsEnMatriz ?? [])));
        let id = Number(localStorage.getItem(clave)) || null;
        const params = new URLSearchParams(window.location.search);
        const solicitada = Number(params.get('empresa'));

        if (solicitada && idsEnMatriz.includes(solicitada)) {
            id = solicitada;
            localStorage.setItem(clave, String(id));
            params.delete('empresa');
            const consulta = params.toString();
            history.replaceState(null, '', window.location.pathname + (consulta ? '?' + consulta : '') + window.location.hash);
        }

        Alpine.store('enTrabajo', {
            id,

            alternar(clienteId) {
                clienteId = Number(clienteId);
                this.id = this.id === clienteId ? null : clienteId;

                if (this.id) {
                    localStorage.setItem(clave, String(this.id));
                } else {
                    localStorage.removeItem(clave);
                }
            },
        });
    });
</script>
