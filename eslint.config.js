import js from '@eslint/js';
import tseslint from 'typescript-eslint';
import pluginVue from 'eslint-plugin-vue';
import vueParser from 'vue-eslint-parser';
import eslintConfigPrettier from 'eslint-config-prettier';
import globals from 'globals';

export default tseslint.config(
    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],
    {
        languageOptions: {
            globals: {
                ...globals.browser,
            },
        },
    },
    {
        files: ['**/*.vue'],
        languageOptions: {
            parser: vueParser,
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
            },
        },
    },
    {
        files: ['**/*.{ts,vue}'],
        rules: {
            // TypeScript already checks for undefined identifiers (including
            // ambient `declare global` types like Ziggy's `route()`), and
            // core no-undef produces false positives against those in this
            // flat-config setup. This is the fix documented by
            // typescript-eslint: https://typescript-eslint.io/troubleshooting/faqs/eslint/#i-am-using-a-rule-from-eslint-core-and-it-doesnt-work-correctly-with-typescript
            'no-undef': 'off',
        },
    },
    {
        rules: {
            'vue/multi-word-component-names': 'off',
        },
    },
    eslintConfigPrettier,
    {
        ignores: ['vendor/**', 'node_modules/**', 'public/build/**'],
    },
);
