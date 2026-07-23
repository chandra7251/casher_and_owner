import { chromium } from 'playwright';

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
await page.goto('http://127.0.0.1:8000/login');
await page.waitForLoadState('networkidle');
await page.getByLabel('Email').fill('kasir@kedaisenja.test');
await page.getByLabel('Password').fill('change-this-local-password');
await page.getByRole('button', { name: 'Masuk' }).click();
await page.waitForURL('**/cashier/orders');
await page.locator('button').filter({ hasText: 'Large' }).filter({ hasText: '22.000' }).click();
await page.getByRole('button', { name: 'Proses Pembayaran' }).click();
if (!await page.getByText('Konfirmasi pembayaran').isVisible()) throw new Error('payment modal missing');
if (!await page.getByText(/Selesaikan dalam \d+ detik/).isVisible()) throw new Error('countdown missing');
await page.getByLabel('Jumlah diterima').fill('25000');
await page.getByRole('button', { name: 'Bayar', exact: true }).click();
await page.getByText('Pembayaran berhasil.').waitFor();
console.log('browser smoke: PASS');
await browser.close();
