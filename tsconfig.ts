{
  "compilerOptions": {
    "target": "ES2020",
    "useDefineForClassFields": true,
    "lib": ["ES2020", "DOM", "DOM.Iterable"],
    "module": "ESNext",
    "skipLibCheck": true,

    /* Bundler mode */
    "moduleResolution": "bundler",
    "allowImportingTsExtensions": true,
    "resolveJsonModule": true,
    "isolatedModules": true,
    "noEmit": true,
    "jsx": "preserve",

    /* Linting */
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noFallthroughCasesInSwitch": true,

    /* Vue specific */
    "allowJs": true,
    "checkJs": false,
    "baseUrl": ".",
    "paths": {
      "@/*": ["resources/js/*"]
    }
  },
  "include": [
    "resources/js/**/*.ts",
    "resources/js/**/*.tsx",
    "resources/js/**/*.vue",
    "resources/js/**/*.js"
  ],
  "exclude": [
    "node_modules",
    "dist",
    "public/build"
  ]
}
