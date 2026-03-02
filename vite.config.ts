import { readFileSync } from 'node:fs';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import type { Plugin } from 'vite';
import { defineConfig } from 'vite';

function lucideDirectImports(): Plugin {
    const exportMap = new Map<string, string>();

    return {
        name: 'lucide-direct-imports',
        enforce: 'pre',
        buildStart() {
            if (exportMap.size > 0) return;
            const barrel = readFileSync(
                'node_modules/lucide-vue-next/dist/esm/lucide-vue-next.js',
                'utf-8',
            );
            for (const match of barrel.matchAll(
                /export\s*\{([^}]+)\}\s*from\s*'\.\/icons\/([^']+)'/g,
            )) {
                const file = match[2];
                for (const name of match[1].split(',')) {
                    const cleaned = name.replace(/default\s+as\s+/, '').trim();
                    if (cleaned) exportMap.set(cleaned, file);
                }
            }
        },
        transform(code, id) {
            if (id.includes('node_modules') || !code.includes('lucide-vue-next')) return;

            return code.replace(
                /import\s*\{([^}]+)\}\s*from\s*['"]lucide-vue-next['"]/g,
                (_, imports: string) => {
                    const names = imports.split(',').map((s: string) => s.trim()).filter(Boolean);
                    const isTypeOnly = names.every((n: string) => n.startsWith('type '));

                    if (isTypeOnly) {
                        return `import { ${imports} } from 'lucide-vue-next'`;
                    }

                    return names
                        .map((n: string) => {
                            if (n.startsWith('type ')) {
                                return `import { ${n} } from 'lucide-vue-next'`;
                            }
                            const file = exportMap.get(n);
                            if (file) {
                                return `import ${n} from 'lucide-vue-next/dist/esm/icons/${file}'`;
                            }
                            return `import { ${n} } from 'lucide-vue-next'`;
                        })
                        .join('\n');
                },
            );
        },
    };
}

export default defineConfig({
    plugins: [
        lucideDirectImports(),
        laravel({
            input: ['resources/js/app.ts'],
            ssr: 'resources/js/ssr.ts',
            refresh: true,
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
});
