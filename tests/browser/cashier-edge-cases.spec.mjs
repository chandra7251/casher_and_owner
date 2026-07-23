import { test, expect } from '@playwright/test';

async function login(page, email, password) {
  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/cashier/orders');
}

test('expired payment shows kedaluwarsa message in UI when server rejects as expired', async ({ page }) => {
  await login(page, 'kasir@kedaisenja.test', 'change-this-local-password');

  // Intercept payment API to simulate server-side expiry (422 with expired message).
  await page.route('**/cashier/orders/*/payment', async (route) => {
    if (route.request().method() === 'POST') {
      await route.fulfill({
        status: 422,
        contentType: 'application/json',
        body: JSON.stringify({ message: 'Pesanan sudah kedaluwarsa atau tidak dapat dibayar.' }),
      });
    } else {
      await route.continue();
    }
  });

  await page.locator('button').filter({ hasText: 'Large' }).filter({ hasText: '22.000' }).click();
  await page.getByRole('button', { name: 'Proses Pembayaran' }).click();
  await expect(page.getByText('Konfirmasi pembayaran')).toBeVisible();
  await page.getByLabel('Jumlah diterima').fill('25000');
  await page.getByRole('button', { name: 'Bayar', exact: true }).click();

  await expect(page.getByText('Pesanan sudah kedaluwarsa atau tidak dapat dibayar.')).toBeVisible();
});

test('UI countdown timer counts down from 60', async ({ page }) => {
  await login(page, 'kasir@kedaisenja.test', 'change-this-local-password');

  await page.locator('button').filter({ hasText: 'Large' }).filter({ hasText: '22.000' }).click();
  await page.getByRole('button', { name: 'Proses Pembayaran' }).click();
  await expect(page.getByText(/Selesaikan dalam 60 detik/)).toBeVisible();
  // After 2 real seconds the counter should have decremented.
  await page.waitForTimeout(2500);
  await expect(page.getByText(/Selesaikan dalam 5[0-9] detik/)).toBeVisible();
});

test('server payment rejection shows error message to cashier', async ({ page }) => {
  await login(page, 'kasir@kedaisenja.test', 'change-this-local-password');

  // Intercept order creation to force server 422 (simulate expired at server).
  await page.route('**/cashier/orders', async (route) => {
    if (route.request().method() === 'POST') {
      await route.fulfill({ status: 422, contentType: 'application/json', body: JSON.stringify({ message: 'Stok tidak mencukupi.' }) });
    } else {
      await route.continue();
    }
  });

  await page.locator('button').filter({ hasText: 'Large' }).filter({ hasText: '22.000' }).click();
  await page.getByRole('button', { name: 'Proses Pembayaran' }).click();
  await page.getByLabel('Jumlah diterima').fill('25000');
  await page.getByRole('button', { name: 'Bayar', exact: true }).click();

  await expect(page.getByText('Stok tidak mencukupi.')).toBeVisible();
});
