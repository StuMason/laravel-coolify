import vue from '@vitejs/plugin-vue';
import { resolve } from 'path';

/** @type {import('vite').UserConfig} */
export default {
    plugins: [vue()],
    build: {
        outDir: 'dist',
        assetsDir: '',
        rollupOptions: {
            input: [
                'resources/js/app.js',
                'resources/css/app.css',
            ],
            output: {
                entryFileNames: '[name].js',
                chunkFileNames: '[name].js',
                assetFileNames: '[name].[ext]',
            },
        },
    },
    resolve: {
        alias: {
            '@': resolve(__dirname, 'resources/js'),
        },
    },
};
