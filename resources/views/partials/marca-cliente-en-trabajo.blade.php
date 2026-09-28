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

        Alpine.store('enTrabajo', {
            id: Number(localStorage.getItem(clave)) || null,

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
