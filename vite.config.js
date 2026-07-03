import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import { viteStaticCopy } from 'vite-plugin-static-copy';

// This is a Laravel package, not a Laravel app: assets are served through a
// custom route (voyager_asset() in src/Helpers/helpers.php), not Laravel's
// @vite()/manifest helpers, so there's no need for laravel-vite-plugin here.
// publishable/assets/** is committed to the repo and consumed via
// vendor:publish, so output filenames must stay fixed (no content hashing)
// and match the existing publishable/assets/{css,js} layout exactly.
export default defineConfig(({ mode }) => ({
    resolve: {
        alias: {
            // Vue's package.json "exports" map points the bare `vue` import
            // at the runtime-only build by default (no template compiler).
            // The admin menu and other root apps are mounted with no
            // render/template (see resources/assets/js/voyager-vue.js),
            // relying on Vue compiling the mount target's innerHTML as an
            // in-DOM template — that silently no-ops on the runtime-only
            // build (dev logs a warning, production just renders nothing).
            vue: 'vue/dist/vue.esm-bundler.js',
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                // app.scss imports plugin stylesheets via bare "node_modules/..."
                // paths (matching how Laravel Mix's sass-loader resolved them).
                loadPaths: ['.'],
            },
        },
    },
    plugins: [
        vue(),
        viteStaticCopy({
            targets: [
                { src: 'node_modules/tinymce/skins/*', dest: 'js/skins' },
                { src: 'resources/assets/js/skins/*', dest: 'js/skins' },
                { src: 'node_modules/tinymce/themes/silver', dest: 'js/themes' },
                { src: 'node_modules/tinymce/models/dom', dest: 'js/models' },
                { src: 'node_modules/tinymce/icons/default', dest: 'js/icons' },
                { src: 'node_modules/ace-builds/src-noconflict/*', dest: 'js/ace/libs' },
            ],
        }),
    ],
    build: {
        outDir: 'publishable/assets',
        emptyOutDir: false,
        cssCodeSplit: false,
        minify: mode === 'production',
        sourcemap: mode !== 'production',
        rollupOptions: {
            input: {
                app: 'resources/assets/js/app.js',
            },
            output: {
                entryFileNames: 'js/app.js',
                chunkFileNames: 'js/[name].js',
                assetFileNames: (assetInfo) => {
                    if (assetInfo.names?.some((n) => n.endsWith('.css'))) {
                        return 'css/app.css';
                    }
                    return 'js/[name][extname]';
                },
            },
        },
    },
}));
