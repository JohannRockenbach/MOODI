import preset from '../../../../vendor/filament/filament/tailwind.config.preset'
import path from 'node:path'

// En Tailwind v3, las rutas de `content` se resuelven relativo al directorio
// de este archivo (resources/css/filament/admin/). Por eso usamos rutas
// absolutas desde la raíz del proyecto.
const root = path.resolve(__dirname, '../../../../')

export default {
    presets: [preset],
    content: [
        path.join(root, 'app/Filament/**/*.php'),
        path.join(root, 'resources/views/filament/**/*.blade.php'),
        path.join(root, 'resources/views/**/*.blade.php'),
        path.join(root, 'vendor/filament/**/*.blade.php'),
    ],
}