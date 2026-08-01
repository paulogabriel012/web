import { expect, test } from '@playwright/test';

test('guest visiting the app is redirected to login', async ({ page }) => {
  await page.goto('/');

  await expect(page).toHaveURL(/login/);
});
