import { Head } from '@inertiajs/react';
import OwnerShell from '../../Components/OwnerShell';

const money = (value) => new Intl.NumberFormat('id-ID', { style: 'currency', currency: 'IDR', maximumFractionDigits: 0 }).format(value);

export default function Dashboard({ data }) {
    return <><Head title="Dashboard Owner" /><OwnerShell title="Ringkasan kedai" description="Pantau pendapatan, ritme order, dan stok yang perlu ditangani hari ini.">
        <section className="grid gap-4 sm:grid-cols-3">
            <article className="cafe-stat bg-espresso text-white"><p className="text-sm text-cream/65">Pendapatan paid</p><strong className="mt-3 block font-display text-3xl">{money(data.paid_revenue)}</strong><p className="mt-6 text-xs text-cream/55">Akumulasi transaksi berhasil</p></article>
            <article className="cafe-stat border border-line bg-white text-espresso"><p className="text-sm text-muted">Order paid</p><strong className="mt-3 block font-display text-3xl">{data.paid_orders}</strong><p className="mt-6 text-xs text-muted">Transaksi selesai</p></article>
            <article className="cafe-stat border border-line bg-terracotta-soft text-espresso"><p className="text-sm text-muted">Order expired</p><strong className="mt-3 block font-display text-3xl text-danger">{data.expired_orders}</strong><p className="mt-6 text-xs text-muted">Perlu dipantau untuk pola pembayaran</p></article>
        </section>
        <section className="cafe-surface mt-6 p-6 sm:p-7"><div className="flex items-end justify-between gap-4"><div><p className="cafe-kicker">Inventory watch</p><h2 className="mt-2 font-display text-2xl font-bold text-espresso">Stok perlu dicek</h2><p className="mt-1 text-sm text-muted">Prioritas restock untuk menjaga ritme pelayanan.</p></div><span className="rounded-full bg-terracotta-soft px-3 py-1 text-xs font-bold text-espresso">{data.low_stock.length} item</span></div>{data.low_stock.length === 0 ? <div className="mt-6 rounded-2xl bg-cream p-5 text-muted">Semua stok aman. Belum ada item melewati batas minimum.</div> : <div className="mt-6 grid gap-3 md:grid-cols-2">{data.low_stock.map((row) => <div key={row.id} className="flex items-center justify-between gap-4 rounded-2xl border border-line bg-cream/60 p-4"><span><strong className="block text-espresso">{row.menu_item?.name ?? 'Menu'}</strong><span className="text-sm text-muted">{row.size} · batas {row.low_stock_threshold}</span></span><span className="shrink-0 rounded-full bg-red-100 px-3 py-1 text-sm font-bold text-danger">{row.on_hand} tersisa</span></div>)}</div>}</section>
    </OwnerShell></>;
}
