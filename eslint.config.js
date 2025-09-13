import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import globals from "globals";
import tseslint from 'typescript-eslint'

export default [
  // Base configs
  js.configs.recommended,
  
  // Vue-specific configuration
  {
    files: ['**/*.vue'],
    ...vue.configs['flat/essential'][0], // Get the Vue config
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
      }
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      'no-console': 'off',
      'vue/no-v-html': 'warn',
      'vue/require-default-prop': 'off',
      'vue/require-explicit-emits': 'error',
      'no-debugger': 'off',
      'no-unused-vars': 'warn'
    }
  },
  
  // TypeScript files only
  {
    files: ['resources/**/*.ts'],
    ...tseslint.configs.recommended[0],
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
      }
    },
    rules: {
      'no-console': 'off',
      'no-debugger': 'off',
      '@typescript-eslint/no-unused-vars': 'warn'
    }
  },
  
  // JavaScript files
  {
    files: ['resources/**/*.js'],
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
      }
    },
    rules: {
      'no-console': 'off',
      'no-debugger': 'off',
      'no-unused-vars': 'warn'
    }
  }
]
