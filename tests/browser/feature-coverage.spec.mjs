import { test, expect } from '@playwright/test';

const password = 'change-this-local-password';

async function login(page, email, destination) {
  await page.goto('/login');
  await page.getByLabel('Email').fill(email);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: /Masuk/ }).click();
  await page.waitForURL('**' + destination);
}

test('owner feature pages and report exports are reachable', async ({ page }) => {
  await login(page, 'owner@kedaisenja.test', '/owner/menu');

  const pages = [
    ['/owner/dashboard', 'Ringkasan kedai'],
    ['/owner/menu', 'Kelola menu'],
    ['/owner/tables', 'Kelola meja'],
    ['/owner/settings', 'Pengaturan kedai'],
    ['/owner/reports?from=2026-01-01&to=2026-12-31', 'Laporan transaksi'],
  ];

  for (const [url, heading] of pages) {
    await page.goto(url);
    await page.waitForLoadState('networkidle');
    await expect(page.getByRole('heading', { name: heading })).toBeVisible();
  }

  const csv = await page.request.get('/owner/reports/csv?from=2026-01-01&to=2026-12-31&status=paid');
  expect(csv.status()).toBe(200);
  expect(csv.headers()['content-type']).toContain('text/csv');

  const pdf = await page.request.get('/owner/reports/pdf?from=2026-01-01&to=2026-12-31&status=paid');
  expect(pdf.status()).toBe(200);
  expect(pdf.headers()['content-type']).toContain('application/pdf');
});

test('cashier menu search, category filter, and table selector work', async ({ page }) => {
  await login(page, 'kasir@kedaisenja.test', '/cashier/orders');
  await expect(page.getByRole('heading', { name: 'Racik pesanan' })).toBeVisible();

  const search = page.getByLabel('Cari menu');
  await search.fill('Kopi Susu Senja');
  await expect(page.getByText('Kopi Susu Senja')).toBeVisible();
  await search.fill('menu-tidak-ada');
  await expect(page.getByText('Menu tidak ditemukan.')).toBeVisible();
  await search.fill('');

  const category = page.getByRole('button', { name: 'Minuman' });
  if (await category.count()) {
    await category.click();
    await expect(category).toHaveAttribute('aria-pressed', 'true');
  }

  await page.getByRole('button', { name: /Ganti meja/ }).click();
  await expect(page.getByRole('dialog', { name: 'Pilih meja' })).toBeVisible();
  await page.getByRole('button', { name: 'Kembali' }).click();
  await expect(page.getByRole('dialog', { name: 'Pilih meja' })).toBeHidden();
});

test('cashier is denied every owner page', async ({ page }) => {
  await login(page, 'kasir@kedaisenja.test', '/cashier/orders');
  for (const path of ['/owner/dashboard', '/owner/menu', '/owner/tables', '/owner/settings', '/owner/reports']) {
    const response = await page.request.get(path);
    expect(response.status(), path).toBe(403);
  }
});


test('owner and cashier can logout', async ({ page }) => {
  await login(page, 'owner@kedaisenja.test', '/owner/menu');
  await page.getByRole('button', { name: 'Keluar' }).click();
  await page.waitForURL('**/login');
  await expect(page.getByRole('heading', { name: 'Selamat datang kembali' })).toBeVisible();

  await login(page, 'kasir@kedaisenja.test', '/cashier/orders');
  await page.getByRole('button', { name: 'Keluar' }).click();
  await page.waitForURL('**/login');
});

test('cashier can open table selector from navigation', async ({ page }) => {
  await login(page, 'kasir@kedaisenja.test', '/cashier/orders');
  await page.getByRole('button', { name: 'Meja', exact: true }).click();
  await expect(page.getByRole('dialog', { name: 'Pilih meja' })).toBeVisible();
});


test('owner can upload menu photo', async ({ page }) => {
  await login(page, 'owner@kedaisenja.test', '/owner/menu');
  const upload = page.getByText('Upload foto menu').first();
  await expect(upload).toBeVisible();
  const input = page.locator('input[type=file]').first();
  await input.setInputFiles({ name: 'menu.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z', 'base64') });
  await expect(page.getByText('Kopi Susu Senja').first()).toBeVisible();
});


test('cashier can upload menu photo', async ({ page }) => {
  await login(page, 'kasir@kedaisenja.test', '/cashier/orders');
  const input = page.locator('input[type=file]').first();
  await expect(input).toBeVisible();
  await input.setInputFiles({ name: 'kasir-menu.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('/9j/4AAQSkZJRgABAQAAAQABAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/ASf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/ASf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z', 'base64') });
  await expect(page.getByText('Foto tersimpan.')).toBeVisible();
});
