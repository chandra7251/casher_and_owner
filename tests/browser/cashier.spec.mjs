import { test, expect } from '@playwright/test';

test('cashier can pay cash order', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('kasir@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/cashier/orders');
  await page.locator('button').filter({ hasText: 'Large' }).filter({ hasText: '22.000' }).click();
  await page.getByRole('button', { name: 'Proses Pembayaran' }).click();
  await expect(page.getByText('Konfirmasi pembayaran')).toBeVisible();
  await expect(page.getByText(/Selesaikan dalam \d+ detik/)).toBeVisible();
  await page.getByLabel('Jumlah diterima').fill('25000');
  await page.getByRole('button', { name: 'Bayar', exact: true }).click();
  await expect(page.getByText('Pembayaran berhasil.')).toBeVisible();
});
