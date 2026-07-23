import { Head } from '@inertiajs/react';
import { useState } from 'react';

export default function Settings({ settings }) {
    const [form, setForm] = useState({ name: settings.name ?? '', address: settings.address ?? '', phone: settings.phone ?? '', thank_you_message: settings.thank_you_message ?? '' });
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState('');

    function set(key) { return (event) => { setSaved(false); setForm((prev) => ({ ...prev, [key]: event.target.value })); }; }

    async function submit(event) {
        event.preventDefault();
        setError(''); setSaved(false);
        const response = await fetch('/owner/settings', { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' }, body: JSON.stringify(form) });
        if (!response.ok) { setError('Gagal menyimpan pengaturan.'); return; }
        setSaved(true);
    }

    return <><Head title="Pengaturan Kedai" /><main className="min-h-screen bg-cream p-5 text-ink sm:p-8"><header className="mb-7"><p className="text-sm font-semibold text-terracotta">Owner</p><h1 className="font-display text-3xl font-bold text-espresso">Pengaturan kedai</h1><p className="mt-2 text-muted">Nama, alamat, dan pesan struk ditampilkan ke kasir dan struk.</p></header><form onSubmit={submit} className="max-w-lg space-y-5"><label className="block text-sm font-semibold">Nama kedai<input required value={form.name} onChange={set('name')} className="mt-2 min-h-11 w-full rounded-xl border border-line px-3" /></label><label className="block text-sm font-semibold">Alamat<input value={form.address} onChange={set('address')} className="mt-2 min-h-11 w-full rounded-xl border border-line px-3" /></label><label className="block text-sm font-semibold">Nomor telepon<input value={form.phone} onChange={set('phone')} className="mt-2 min-h-11 w-full rounded-xl border border-line px-3" /></label><label className="block text-sm font-semibold">Pesan terima kasih (struk)<textarea value={form.thank_you_message} onChange={set('thank_you_message')} rows={3} className="mt-2 w-full rounded-xl border border-line px-3 py-2" /></label>{error && <p className="text-sm text-danger">{error}</p>}{saved && <p className="text-sm text-success">Tersimpan.</p>}<button className="min-h-11 rounded-xl bg-espresso px-6 font-bold text-white">Simpan pengaturan</button></form></main></>;
}
