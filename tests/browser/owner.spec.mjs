import { test, expect } from '@playwright/test';

test('owner can open menu management', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('owner@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/owner/menu');
  await expect(page.getByRole('heading', { name: 'Kelola menu' })).toBeVisible();
  await expect(page.getByText('Kopi Susu Senja')).toBeVisible();
});

test('owner can open dashboard', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('owner@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/owner/menu');
  await page.goto('/owner/dashboard');
  await expect(page.getByRole('heading', { name: 'Ringkasan kedai' })).toBeVisible();
  await expect(page.getByText('Pendapatan paid')).toBeVisible();
});

test('owner can open table management', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('owner@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/owner/menu');
  await page.goto('/owner/tables');
  await expect(page.getByRole('heading', { name: 'Kelola meja' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Simpan meja' }).first()).toBeVisible();
});

test('owner can update cafe settings', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('owner@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/owner/menu');
  await page.goto('/owner/settings');
  await expect(page.getByRole('heading', { name: 'Pengaturan kedai' })).toBeVisible();
  const input = page.getByLabel('Nama kedai');
  await input.fill('Kedai Senja Updated');
  await page.getByRole('button', { name: 'Simpan pengaturan' }).click();
  await expect(page.getByText('Tersimpan.')).toBeVisible();
});

test('owner can open reports page', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('owner@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/owner/menu');
  await page.goto(`/owner/reports?from=2026-01-01&to=2026-12-31`);
  await expect(page.getByRole('heading', { name: 'Laporan transaksi' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Unduh CSV' })).toBeVisible();
});

test('owner can edit price and stock from menu page', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('owner@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/owner/menu');
  await page.getByLabel('Regular harga').first().fill('19000');
  await page.getByLabel('Regular stok').first().fill('40');
  await page.getByLabel('Regular batas stok').first().fill('6');
  await page.getByRole('button', { name: 'Simpan' }).first().click();
  await expect(page.getByLabel('Regular harga').first()).toHaveValue('19000');
  await expect(page.getByLabel('Regular stok').first()).toHaveValue('40');
});

test('cashier cannot open menu management', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('kasir@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/cashier/orders');
  const response = await page.goto('/owner/menu');
  expect(response.status()).toBe(403);
});

test('owner reports page shows pagination controls', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('owner@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/owner/menu');
  await page.goto('/owner/reports?from=2026-01-01&to=2026-12-31');
  await expect(page.getByRole('heading', { name: 'Laporan transaksi' })).toBeVisible();
  // Pagination controls must always be rendered
  await expect(page.getByRole('button', { name: 'Sebelumnya' })).toBeVisible();
  await expect(page.getByRole('button', { name: 'Berikutnya' })).toBeVisible();
  // On page 1 with zero or one page of results, Sebelumnya is disabled
  await expect(page.getByRole('button', { name: 'Sebelumnya' })).toBeDisabled();
});

test('owner reports pagination preserves filters on load', async ({ page }) => {
  await page.goto('/login');
  await page.getByLabel('Email').fill('owner@kedaisenja.test');
  await page.getByLabel('Password').fill('change-this-local-password');
  await page.getByRole('button', { name: 'Masuk' }).click();
  await page.waitForURL('**/owner/menu');
  await page.goto('/owner/reports?from=2026-01-01&to=2026-12-31');
  await expect(page.getByRole('heading', { name: 'Laporan transaksi' })).toBeVisible();
  await page.getByRole('button', { name: 'Tampilkan' }).click();
  // After Tampilkan, filter controls still show correct values
  await expect(page.getByLabel('Dari')).toHaveValue('2026-01-01');
  await expect(page.getByLabel('Sampai')).toHaveValue('2026-12-31');
});
