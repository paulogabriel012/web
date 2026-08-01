// =============================================================================
// Browser web — dependency-cruiser (frontend architecture rules)
// =============================================================================
// Enforces the folder structure documented in AGENTS.md.
//
// Run:   pnpm exec depcruise resources/js --config .dependency-cruiser.cjs
// =============================================================================

/** @type {import('dependency-cruiser').IConfiguration} */
module.exports = {
  forbidden: [
    {
      name: 'no-circular',
      severity: 'error',
      comment: 'Circular dependencies make code hard to reason about and break tree-shaking.',
      from: {},
      to: { circular: true },
    },
    {
      name: 'no-orphans',
      severity: 'warn',
      comment: 'Module is not used by anyone — likely dead code.',
      from: {
        orphan: true,
        pathNot: [
          String.raw`(^|/)\.[^/]+\.(js|cjs|mjs|ts|cts|mts|json)$`,
          String.raw`\.d\.ts$`,
          String.raw`(^|/)tsconfig\.json$`,
          String.raw`(^|/)(package|package-lock)\.json$`,
          'resources/js/app.tsx',
          'resources/js/ssr.tsx',
          'resources/js/routes/',
          'resources/js/pages/',
          'resources/js/components/ui/', // shadcn generated
          'resources/js/wayfinder/', // Wayfinder generated
        ],
      },
      to: {},
    },
    {
      name: 'no-deprecated-core',
      severity: 'error',
      from: {},
      to: { dependencyTypes: ['deprecated'] },
    },
    {
      name: 'not-to-unresolvable',
      severity: 'error',
      from: {},
      to: { couldNotResolve: true },
    },
    {
      name: 'no-duplicate-dep-types',
      severity: 'warn',
      from: {},
      to: {
        moreThanOneDependencyType: true,
        dependencyTypesNot: ['type-only'],
      },
    },
    // --- Layer rules ------------------------------------------------------
    {
      name: 'shared-must-not-import-features',
      severity: 'error',
      comment: 'shared/ is the common library — it cannot know about feature modules.',
      from: { path: '^resources/js/shared' },
      to: { path: '^resources/js/(features|pages|layout)' },
    },
    {
      name: 'features-cannot-cross-talk',
      severity: 'error',
      comment:
        'features/checkout cannot import from features/campaigns. If you need cross-feature code, promote it to shared/.',
      from: { path: '^resources/js/features/([^/]+)/' },
      to: {
        path: '^resources/js/features/([^/]+)/',
        pathNot: '^resources/js/features/$1/',
      },
    },
    {
      name: 'layout-cannot-import-features',
      severity: 'error',
      comment: 'layout/ is presentational chrome — it must not know about any feature.',
      from: { path: '^resources/js/layout' },
      to: { path: '^resources/js/features' },
    },
    {
      name: 'pages-only-import-their-module',
      severity: 'warn',
      comment: 'pages/{module}/ should only consume features/{module}/ and shared/, not other features/.',
      from: {
        path: '^resources/js/pages/([^/]+)/',
      },
      to: {
        path: '^resources/js/features/([^/]+)/',
        pathNot: '^resources/js/features/$1/',
      },
    },
    {
      name: 'no-dev-deps-in-prod-code',
      severity: 'error',
      comment: 'devDependencies should never be imported from production source code.',
      from: {
        path: '^resources/js',
        pathNot: String.raw`\.(test|spec)\.(ts|tsx)$`,
      },
      to: { dependencyTypes: ['npm-dev'] },
    },
  ],
  options: {
    doNotFollow: { path: 'node_modules' },
    exclude: {
      path: [
        'resources/js/components/ui/', // shadcn generated
        'resources/js/wayfinder/', // Wayfinder generated
      ],
    },
    tsPreCompilationDeps: true,
    tsConfig: { fileName: 'tsconfig.json' },
    enhancedResolveOptions: {
      exportsFields: ['exports'],
      conditionNames: ['import', 'require', 'node', 'default', 'types'],
      mainFields: ['module', 'main', 'types', 'typings'],
    },
    reporterOptions: {
      text: { highlightFocused: true },
    },
  },
};
