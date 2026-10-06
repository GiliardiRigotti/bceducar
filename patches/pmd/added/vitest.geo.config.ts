import { defineConfig } from 'vitest/config';
import vue from '@vitejs/plugin-vue';
import path from 'path';
export default defineConfig({ plugins: [vue()], resolve: {alias: [{find: /^leaflet$/, replacement:path.resolve(__dirname, 'tests/geo/leaflet.fixture.ts')},{find:'@',replacement:path.resolve(__dirname,'resources/ts')}]}, test: { environment: 'happy-dom', include: ['tests/geo/**/*.test.ts'], threads: false } });
