import { defineConfig, loadEnv } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const host = env.VITE_HOST || 'localhost';
    const port = Number(env.VITE_PORT || 5183);

    return {
        plugins: [
            laravel({
                input: ['resources/css/app.css', 'resources/js/app.js'],
                refresh: true,
            }),
        ],
        server: {
            host,
            port,
            strictPort: true,
            hmr: {
                host: env.VITE_HMR_HOST || host,
                port,
            },
        },
    };
});
