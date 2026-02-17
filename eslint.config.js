import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'
import vueConfigTypeScript from '@vue/eslint-config-typescript'
import vueConfigPrettier from '@vue/eslint-config-prettier'
import globals from 'globals'

export default [
    // Base ESLint recommended rules
    js.configs.recommended,

    // Vue.js essential rules
    ...vue.configs['flat/essential'],

    // TypeScript rules
    ...tseslint.configs.recommended,

    // Vue + TypeScript configuration
    ...vueConfigTypeScript(),

    // Prettier integration (should be last)
    vueConfigPrettier,

    {
        files: ['**/*.{js,mjs,cjs,vue,ts}'],
        languageOptions: {
            ecmaVersion: 2022,
            sourceType: 'module',
            globals: {
                ...globals.browser,
                ...globals.node,
                process: 'readonly'
            }
        },
        rules: {
            // Vue-specific rules
            'vue/block-lang': 'off',
            'vue/multi-word-component-names': 'off',
            'vue/no-v-html': 'off',
            'vue/require-default-prop': 'off',
            'vue/require-explicit-emits': 'error',
            'vue/component-definition-name-casing': ['error', 'PascalCase'],
            'vue/component-name-in-template-casing': ['error', 'PascalCase'],

            // General rules
            'no-empty': 'off',
            'no-console': 'off',
            'no-debugger': 'off',
            'no-unused-vars': 'off',

            // TypeScript rules
            '@typescript-eslint/no-unused-vars': 'off',
            '@typescript-eslint/no-explicit-any': 'off',
            '@typescript-eslint/explicit-function-return-type': 'off',
            '@typescript-eslint/explicit-module-boundary-types': 'off'
        }
    },

    // TypeScript-specific overrides
    {
        files: ['**/*.ts', '**/*.tsx'],
        rules: {
            'no-unused-vars': 'off', // Turn off base rule for TS files
            '@typescript-eslint/no-unused-vars': 'error'
        }
    },

    // Vue SFC script setup specific rules
    {
        files: ['**/*.vue'],
        languageOptions: {
            parserOptions: {
                parser: '@typescript-eslint/parser'
            }
        },
        rules: {
            '@typescript-eslint/no-unused-vars': 'off' // Vue compiler handles this
        }
    },

    // Ignore patterns
    {
        ignores: ['node_modules/**', 'dist/**', 'build/**', 'public/**', '*.min.js']
    }
]
