import { Head } from '@inertiajs/react';
import { useState } from 'react';

const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

export default function Menu({ items }) {
    const [rows, setRows] = useState(items);
    const [error, setError] = useState('');
    async function saveSize(size, values) {
        setError('');
        const response = await fetch(`/owner/menu/sizes/${size.id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' }, body: JSON.stringify(values) });
        if (!response.ok) { setError('Perubahan gagal disimpan.'); return; }
        const result = await response.json();
        setRows((current) => current.map((item) => ({ ...item, sizes: item.sizes.map((entry) => entry.id === size.id ? result.data : entry) })));
    }
    async function toggle(item) {
        const response = await fetch(`/owner/menu/items/${item.id}/availability`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' }, body: JSON.stringify({ is_available: !item.is_available }) });
        if (!response.ok) { setError('Perubahan ketersediaan gagal.'); return; }
        const result = await response.json();
        setRows((current) => current.map((entry) => entry.id === item.id ? { ...entry, is_available: result.data.is_available } : entry));
    }
    return <><Head title="Menu Owner" /><main className="min-h-screen bg-cream p-5 text-ink sm:p-8"><header className="mb-6"><p className="text-sm font-semibold text-terracotta">Owner</p><h1 className="font-display text-3xl font-bold text-espresso">Kelola menu</h1></header>{error && <p className="mb-4 rounded-xl bg-red-50 p-3 text-danger">{error}</p>}<div className="grid gap-4 lg:grid-cols-2">{rows.map((item) => <article key={item.id} className="rounded-2xl border border-line bg-white p-5"><div className="flex items-center justify-between gap-3"><h2 className="font-display text-xl font-bold text-espresso">{item.name}</h2><button onClick={() => toggle(item)} className={`min-h-11 rounded-xl px-3 text-sm font-bold ${item.is_available ? 'bg-green-100 text-success' : 'bg-red-100 text-danger'}`}>{item.is_available ? 'Aktif' : 'Nonaktif'}</button></div><div className="mt-4 space-y-3">{item.sizes.map((size) => <div key={size.id} className="rounded-xl border border-line p-3"><div className="flex items-center justify-between"><strong>{size.size}</strong><label className="text-sm">Harga<input aria-label={`${size.size} harga`} type="number" value={size.price} onChange={(event) => setRows((current) => current.map((entry) => ({ ...entry, sizes: entry.sizes.map((row) => row.id === size.id ? { ...row, price: Number(event.target.value) } : row) })))} className="ml-2 min-h-11 w-28 rounded-lg border border-line px-2" /></label></div><div className="mt-2 flex items-center justify-between text-sm text-muted"><label>Stok<input aria-label={`${size.size} stok`} type="number" value={size.on_hand} onChange={(event) => setRows((current) => current.map((entry) => ({ ...entry, sizes: entry.sizes.map((row) => row.id === size.id ? { ...row, on_hand: Number(event.target.value) } : row) })))} className="ml-2 min-h-11 w-20 rounded-lg border border-line px-2 text-ink" /></label><label>Batas<input aria-label={`${size.size} batas stok`} type="number" value={size.low_stock_threshold} onChange={(event) => setRows((current) => current.map((entry) => ({ ...entry, sizes: entry.sizes.map((row) => row.id === size.id ? { ...row, low_stock_threshold: Number(event.target.value) } : row) })))} className="ml-2 min-h-11 w-16 rounded-lg border border-line px-2 text-ink" /></label><span>Reserved {size.reserved}</span><button onClick={() => saveSize(size, { price: size.price, on_hand: size.on_hand, low_stock_threshold: size.low_stock_threshold })} className="min-h-11 rounded-lg bg-espresso px-3 font-semibold text-white">Simpan</button></div></div>)}</div></article>)}</div></main></>;
}
