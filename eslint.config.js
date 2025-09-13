import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import globals from "globals";
import tseslint from 'typescript-eslint'

export default [
  js.configs.recommended,
  ...vue.configs['flat/essential'],
  ...tseslint.configs.recommended,
  {
    files: ['resources/**/*.{js,vue,ts}'],
    languageOptions: {
      ecmaVersion: 2022,
      sourceType: 'module',
      globals: {
        ...globals.browser,
        ...globals.node,
        process: 'readonly',
        import: 'readonly',
        route: 'readonly',
        axios: 'readonly'
      },
      parserOptions: {
        ecmaFeatures: {
          jsx: true
        }
      }
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      'no-console': 'off',
      'vue/no-v-html': 'warn',
      'vue/require-default-prop': 'off',
      'vue/require-explicit-emits': 'error',
      'no-debugger': 'off',
      'no-unused-vars': 'warn',
      '@typescript-eslint/no-unused-vars': 'warn'
    }
  },
  // Specific overrides for TypeScript files to avoid conflicts
  {
    files: ['resources/**/*.ts'],
    rules: {
      'no-unused-vars': 'off', // Turn off base rule for TS files
      '@typescript-eslint/no-unused-vars': 'warn'
    }
  },
  // Ignore TypeScript rules for plain JS and Vue files
  {
    files: ['resources/**/*.{js,vue}'],
    rules: {
      '@typescript-eslint/no-unused-vars': 'off',
      '@typescript-eslint/no-explicit-any': 'off',
      '@typescript-eslint/no-require-imports': 'off'
    }
  }
]