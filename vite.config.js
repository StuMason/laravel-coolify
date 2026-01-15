import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

/** @type {import('vite').UserConfig} */
export default {
    plugins: [vue()],
    build: {
        outDir: 'dist',
        emptyOutDir: true,
        rollupOptions: {
            input: {
                app: resolve(__dirname, 'resources/js/app.js'),
            },
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: 'chunks/[name]-[hash].js',
                assetFileNames: '[name].[ext]',
            },
        },
    },
    css: {
        postcss: resolve(__dirname, 'postcss.config.js'),
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
};
