import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { bunny } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/admin.css', 'resources/js/admin.js', 'resources/js/builder.ts'],
            refresh: true,
            fonts: [
                bunny('Nunito', {
                    weights: [400, 600, 700, 800],
                }),
            ],
        }),
        // ページビルダーのエディタ(resources/js/builder)だけが Vue を使う
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    test: {
        // ページビルダーのエディタの、画面から切り離した処理のテスト(npm test)
        include: ['resources/js/**/*.test.ts'],
        environment: 'node',
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
