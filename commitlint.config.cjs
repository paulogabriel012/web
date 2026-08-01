/** @type {import('@commitlint/types').UserConfig} */
module.exports = {
  extends: ['@commitlint/config-conventional'],
  rules: {
    'type-enum': [
      2,
      'always',
      ['feat', 'fix', 'refactor', 'perf', 'test', 'docs', 'chore', 'ci', 'build', 'style', 'revert'],
    ],
    'scope-enum': [
      2,
      'always',
      [
        'web',
        'auth',
        'billing',
        'plans',
        'quotas',
        'profile',
        'dashboard',
        'api',
        'contracts',
        'settings',
        'ci',
        'deps',
        'docs',
    ],
    ],
    'subject-case': [2, 'always', 'lower-case'],
    'subject-empty': [2, 'never'],
    'subject-full-stop': [2, 'never', '.'],
    'header-max-length': [2, 'always', 100],
    'body-max-line-length': [2, 'always', 120],
  },
};
