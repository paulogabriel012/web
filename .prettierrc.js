import * as organizeImports from 'prettier-plugin-organize-imports';
import * as tailwindcss from 'prettier-plugin-tailwindcss';

/**
 * Mirrors the project's shared Prettier config.
 * Kept local so this repo stays independent of any sibling checkout in CI.
 * PHP/Blade are handled by Pint (ignored).
 *
 * Plugin order: organize-imports before tailwind; tailwind must be last.
 *
 * @type {import('prettier').Config}
 */
export default {
  semi: true,
  singleQuote: true,
  trailingComma: 'all',
  printWidth: 100,
  tabWidth: 2,
  useTabs: false,
  arrowParens: 'always',
  bracketSpacing: true,
  endOfLine: 'lf',
  plugins: [organizeImports, tailwindcss],
  tailwindFunctions: ['clsx', 'cn', 'cva', 'tw'],
  overrides: [
    { files: '*.yml', options: { tabWidth: 2 } },
    { files: '*.yaml', options: { tabWidth: 2 } },
    { files: '*.md', options: { proseWrap: 'preserve' } },
    {
      files: '**/*.{js,jsx,ts,tsx,css}',
      options: {
        tailwindStylesheet: './resources/css/app.css',
      },
    },
  ],
};
