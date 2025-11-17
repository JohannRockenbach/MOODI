<div>
    <script>
        document.addEventListener('livewire:initialized', () => {
            // Escuchar cambios en los SelectColumn de estado
            Livewire.on('order-status-updated', (data) => {
                console.log('✅ Estado actualizado, refrescando tabla...');
                
                // Refrescar el componente de Livewire después de 500ms
                setTimeout(() => {
                    Livewire.dispatch('$refresh');
                }, 500);
            });
        });
    </script>
</div>
