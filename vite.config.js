import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import statamic from '@statamic/cms/vite-plugin';

export default defineConfig({
    // Addons are published below /vendor/{package}/build. Relative URLs keep
    // Leaflet's images attached to the bundle regardless of the host path.
    base: './',
    plugins: [
        statamic(),
        laravel({
            input: ['resources/js/cp.js'],
            hotFile: 'public/hot',
            buildDirectory: 'build',
            refresh: false,
        }),
    ],
});
