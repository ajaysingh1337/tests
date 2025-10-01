import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import path from 'path';
import fs from 'fs';

import react from '@vitejs/plugin-react';
export default defineConfig({
    build: {
        chunkSizeWarningLimit: 100000000,
        rollupOptions: {
            // external: ['@mui/material/utils']
        }
    },
    optimizeDeps: {
        include: ['@mui/material','@mui/icons-material']
    },
    esbuild: {
        loader: 'jsx',
        include: /reactjs\/.*\.js$/,
        exclude: [],
    },
    plugins: [
    laravel({
        input: [
            'resources/js/app.js',
            'reactjs/main.js'
        ],
        ssr: 'resources/js/ssr.js',
        refresh: [
            'resources/js/**',
            'resources/routes/**',
            'routes/**',
            'resources/views/**',
            'reactjs/**'
        ],
    }),
    vue({
        template: {
            transformAssetUrls: {
                base: null,
                includeAbsolute: false,
            },
        },
    }),
    //react({ jsxRuntime: 'automatic' }), 
    react({
      include: ['**/*.jsx', '**/*.tsx', '**/*.js'], // add .js here
      jsxRuntime: 'automatic',
    })
],
        resolve: {
        alias: [{
            find: '@/',
            replacement:'/resources/js'
        },
        
        {
            find: '~bootstrap',
            replacement : path.resolve(__dirname, 'node_modules/bootstrap'),
        },
        {
            find: '~bootstrap-icons',
            replacement : path.resolve(__dirname, 'node_modules/bootstrap-icons'),
        },
         {
            find: '~select2',
            replacement : path.resolve(__dirname, 'node_modules/select2'),
        },
         {
            find: '~vue3-carousel',
            replacement : path.resolve(__dirname, 'node_modules/vue3-carousel'),
        },
        {
        find: 'reactComponents',
            replacement: path.resolve(__dirname, 'reactjs/components'),
        },
        {
            find: 'webrtc-client',
            replacement: path.resolve(__dirname, 'reactjs/assets/webrtc-client'),
        },
        
    ],
    },
    ssr: {
        noExternal: ['@inertiajs/server'],
    },
   
});
