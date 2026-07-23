import { Head } from '@inertiajs/react';
import { useState } from 'react';

const money = (v) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(v);
const today = () => new Date().toISOString().slice(0, 10);
const monthStart = () => { const d = new Date(); d.setDate(1); return d.toISOString().slice(0, 10); };

export default function Reports({ orders = [], meta = {}, filters = {} }) {
    const [from, setFrom] = useState(filters.from ?? monthStart());
    const [to, setTo] = useState(filters.to ?? today());
    const [status, setStatus] = useState(filters.status ?? 'paid');
    const [rows, setRows] = useState(orders);
    const [summary, setSummary] = useState(meta);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    async function load() {
        setLoading(true); setError('');
        const response = await fetch(`/owner/reports?from=${from}&to=${to}&status=${status}`, { headers: { Accept: 'application/json' } });
        setLoading(false);
        if (!response.ok) { setError('Gagal memuat laporan.'); return; }
        const body = await response.json();
        setRows(body.data); setSummary(body.meta);
    }

    const csvUrl = `/owner/reports/csv?from=${from}&to=${to}&status=${status}`;

    return <><Head title="Laporan" /><main className="min-h-screen bg-cream p-5 text-ink sm:p-8">
        <header className="mb-7"><p className="text-sm font-semibold text-terracotta">Owner</p><h1 className="font-display text-3xl font-bold text-espresso">Laporan transaksi</h1></header>
        <section className="mb-6 flex flex-wrap items-end gap-3">
            <label className="block text-sm font-semibold">Dari<input type="date" value={from} onChange={(e) => setFrom(e.target.value)} className="mt-1 block min-h-11 rounded-xl border border-line px-3" /></label>
            <label className="block text-sm font-semibold">Sampai<input type="date" value={to} onChange={(e) => setTo(e.target.value)} className="mt-1 block min-h-11 rounded-xl border border-line px-3" /></label>
            <label className="block text-sm font-semibold">Status<select value={status} onChange={(e) => setStatus(e.target.value)} className="mt-1 block min-h-11 rounded-xl border border-line px-3"><option value="paid">Paid</option><option value="expired">Expired</option></select></label>
            <button onClick={load} disabled={loading} className="min-h-11 rounded-xl bg-espresso px-5 font-bold text-white disabled:opacity-50">{loading ? 'Memuat...' : 'Tampilkan'}</button>
            <a href={csvUrl} download className="min-h-11 flex items-center rounded-xl border border-line px-5 text-sm font-semibold">Unduh CSV</a>
        </section>
        {error && <p className="mb-4 text-sm text-danger">{error}</p>}
        {summary.total_orders !== undefined && <div className="mb-5 flex gap-4">
            <article className="rounded-2xl bg-espresso px-5 py-4 text-white"><p className="text-xs text-cream/70">Total order</p><strong className="mt-1 block font-display text-2xl">{summary.total_orders}</strong></article>
            <article className="rounded-2xl border border-line bg-white px-5 py-4"><p className="text-xs text-muted">Total revenue</p><strong className="mt-1 block font-display text-2xl text-espresso">{money(summary.total_revenue)}</strong></article>
        </div>}
        <div className="overflow-x-auto rounded-2xl border border-line bg-white">
            <table className="w-full text-sm"><thead className="border-b border-line"><tr>{['Nomor', 'Meja', 'Total', 'Metode', 'Waktu'].map((h) => <th key={h} className="px-4 py-3 text-left font-semibold text-muted">{h}</th>)}</tr></thead>
            <tbody>{rows.length === 0 ? <tr><td colSpan={5} className="px-4 py-8 text-center text-muted">Tidak ada data.</td></tr> : rows.map((o) => <tr key={o.id} className="border-b border-line last:border-0"><td className="px-4 py-3 font-mono text-xs">{o.number}</td><td className="px-4 py-3">{o.table?.name ?? '-'}</td><td className="px-4 py-3 font-semibold">{money(o.total)}</td><td className="px-4 py-3">{o.payment?.method ?? '-'}</td><td className="px-4 py-3 text-muted">{o.payment?.paid_at ? new Date(o.payment.paid_at).toLocaleString('id-ID') : '-'}</td></tr>)}</tbody>
            </table>
        </div>
    </main></>;
}
