import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Tables({ tables }) {
    const [rows, setRows] = useState(tables);
    const [error, setError] = useState('');
    async function save(table) {
        setError('');
        const response = await fetch(`/owner/tables/${table.id}`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' }, body: JSON.stringify({ name: table.name, status: table.status }) });
        if (!response.ok) { setError('Meja gagal disimpan. Nama meja harus unik.'); return; }
        const result = await response.json();
        setRows((items) => items.map((item) => item.id === table.id ? result.data : item));
    }
    return <><Head title="Meja Owner" /><main className="min-h-screen bg-cream p-5 text-ink sm:p-8"><header className="mb-7"><p className="text-sm font-semibold text-terracotta">Owner</p><h1 className="font-display text-3xl font-bold text-espresso">Kelola meja</h1><p className="mt-2 text-muted">Nama dan status meja kasir.</p></header>{error && <p className="mb-4 rounded-xl bg-red-50 p-3 text-danger">{error}</p>}<section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">{rows.map((table) => <article key={table.id} className="rounded-2xl border border-line bg-white p-4"><label className="text-sm font-semibold">Nama meja<input aria-label={`Nama ${table.name}`} value={table.name} onChange={(event) => setRows((items) => items.map((item) => item.id === table.id ? { ...item, name: event.target.value } : item))} className="mt-2 min-h-11 w-full rounded-xl border border-line px-3" /></label><label className="mt-3 flex items-center justify-between text-sm">Status<select aria-label={`Status ${table.name}`} value={table.status} onChange={(event) => setRows((items) => items.map((item) => item.id === table.id ? { ...item, status: event.target.value } : item))} className="min-h-11 rounded-xl border border-line px-3"><option value="available">Tersedia</option><option value="occupied">Terisi</option></select></label><button onClick={() => save(table)} className="mt-4 min-h-11 w-full rounded-xl bg-espresso font-bold text-white">Simpan meja</button></article>)}</section></main></>;
}
