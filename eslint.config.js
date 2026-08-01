import js from '@eslint/js';
import prettier from 'eslint-config-prettier/flat';
import globals from 'globals';
import reactHooks from 'eslint-plugin-react-hooks';
import reactPlugin from 'eslint-plugin-react';
import tseslint from 'typescript-eslint';

/** @type {import('eslint').Linter.Config[]} */
export default [
  {
    ignores: ['.agents', 'vendor', 'node_modules', 'public', 'bootstrap/ssr', 'tailwind.config.js'],
  },
  js.configs.recommended,
  ...tseslint.configs.recommended,
  reactPlugin.configs.flat.recommended,
  reactHooks.configs.flat.recommended,
  {
    settings: {
      react: { version: 'detect' },
    },
  },
  {
    files: ['**/*.{js,jsx,ts,tsx}'],
    languageOptions: {
      globals: {
        ...globals.browser,
      },
    },
  },
  {
    rules: {
      // React 19 + Vite uses the automatic JSX runtime; importing React is not required.
      'react/react-in-jsx-scope': 'off',
      '@typescript-eslint/no-unused-vars': [
        'error',
        {
          argsIgnorePattern: '^_',
          varsIgnorePattern: '^_',
          destructuredArrayIgnorePattern: '^_',
        },
      ],
      '@typescript-eslint/consistent-type-imports': 'off',
      complexity: ['warn', { max: 15 }],
      'max-lines': 'off',
      'max-lines-per-function': ['warn', { max: 200, skipBlankLines: true, skipComments: true }],
      'max-depth': ['warn', { max: 4 }],
      'max-params': ['warn', { max: 4 }],
      'prefer-const': 'error',
      'no-return-await': 'warn',
      'no-dupe-else-if': 'error',
      'no-cond-assign': ['error', 'always'],
      'default-case': 'warn',
      'no-extra-boolean-cast': 'error',
      eqeqeq: ['error', 'always'],
    },
  },
  {
    // Node.js execution context: tooling config files and CLI scripts
    // (ESLint's default env is browser, so these need Node globals).
    files: ['**/*.cjs', '**/*.mjs', 'scripts/**/*.js', 'eslint.config.js'],
    languageOptions: {
      globals: {
        ...globals.node,
      },
    },
  },
  prettier,
];
