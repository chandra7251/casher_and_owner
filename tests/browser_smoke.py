from playwright.sync_api import sync_playwright

BASE = 'http://127.0.0.1:8000'

with sync_playwright() as playwright:
    browser = playwright.chromium.launch(headless=True)
    page = browser.new_page()
    page.goto(f'{BASE}/login')
    page.wait_for_load_state('networkidle')
    page.get_by_label('Email').fill('kasir@kedaisenja.test')
    page.get_by_label('Password').fill('change-this-local-password')
    page.get_by_role('button', name='Masuk').click()
    page.wait_for_url(f'{BASE}/cashier/orders')
    page.get_by_role('button', name='Large Rp22.000').click()
    page.get_by_role('button', name='Proses Pembayaran').click()
    assert page.get_by_text('Konfirmasi pembayaran').is_visible()
    assert page.get_by_text('Selesaikan dalam 60 detik.').is_visible()
    page.get_by_role('button', name='Tunai').click()
    page.get_by_label('Jumlah diterima').fill('25000')
    page.get_by_role('button', name='Bayar').click()
    page.wait_for_timeout(500)
    assert page.get_by_text('Pembayaran berhasil.').is_visible()
    browser.close()

print('browser smoke: PASS')
