import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

const money = (value, currency = 'IDR') => new Intl.NumberFormat('id-ID', { style: 'currency', currency, maximumFractionDigits: 0 }).format(value);


export default function Workspace({ cafe, user, categories, products, tables, failedPrintJobs = [] }) {
    const [category, setCategory] = useState('Semua');
    const [query, setQuery] = useState('');
    const [table, setTable] = useState(tables.find((item) => item.status === 'available') ?? tables[0]);
    const [cart, setCart] = useState([]);
    const [paymentOpen, setPaymentOpen] = useState(false);
    const [paymentMethod, setPaymentMethod] = useState('cash');
    const [receivedAmount, setReceivedAmount] = useState('');
    const [qrisValidated, setQrisValidated] = useState(false);
    const [paymentError, setPaymentError] = useState('');
    const [paymentResult, setPaymentResult] = useState(null);
    const [secondsLeft, setSecondsLeft] = useState(60);
    const [tableOpen, setTableOpen] = useState(false);
    const [paymentExpired, setPaymentExpired] = useState(false);
    const [tableSaving, setTableSaving] = useState(false);
    const [submitting, setSubmitting] = useState(false);
    const [photoBusy, setPhotoBusy] = useState(null);
    const [photoError, setPhotoError] = useState('');
    const [photoSaved, setPhotoSaved] = useState(false);
    const tableDialogRef = useRef(null);
    const paymentDialogRef = useRef(null);

    useEffect(() => {
        if (secondsLeft === 0 && paymentOpen) setPaymentExpired(true);
    }, [secondsLeft, paymentOpen]);

    useEffect(() => {
        if (!paymentOpen) return undefined;
        setSecondsLeft(60);
        const timer = window.setInterval(() => setSecondsLeft((value) => Math.max(0, value - 1)), 1000);
        return () => window.clearInterval(timer);
    }, [paymentOpen]);

    // Focus trap: move focus into modal when it opens.
    useEffect(() => { if (tableOpen) tableDialogRef.current?.focus(); }, [tableOpen]);
    useEffect(() => { if (paymentOpen) paymentDialogRef.current?.focus(); }, [paymentOpen]);

    // Close modals on Escape.
    useEffect(() => {
        const handler = (e) => {
            if (e.key !== 'Escape') return;
            if (tableOpen) { setTableOpen(false); }
            if (paymentOpen) { setPaymentOpen(false); setPaymentExpired(false); }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [tableOpen, paymentOpen]);

    const visibleProducts = products.filter((product) => (category === 'Semua' || product.categoryId === categories.find((item) => item.name === category)?.id) && product.name.toLowerCase().includes(query.toLowerCase()));
    const total = cart.reduce((sum, item) => sum + item.unitPrice * item.quantity, 0);

    function add(product, size = 'Regular') {
        const unitPrice = product.sizes[size];
        setCart((items) => {
            const existing = items.find((item) => item.productId === product.id && item.size === size);
            if (existing) return items.map((item) => item === existing ? { ...item, quantity: item.quantity + 1 } : item);
            return [...items, { productId: product.id, name: product.name, size, unitPrice, quantity: 1 }];
        });
    }

    async function markTableAvailable() {
        if (!table || table.activeOrders) return;
        setTableSaving(true);
        try {
            const response = await fetch(`/cashier/tables/${table.id}/status`, { method: 'PATCH', headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=\"csrf-token\"]')?.content ?? '', Accept: 'application/json' }, body: JSON.stringify({ status: 'available' }) });
            if (!response.ok) throw new Error('Status meja gagal diubah.');
            setTable((current) => ({ ...current, status: 'available' }));
        } catch (error) { setPaymentError(error.message); } finally { setTableSaving(false); }
    }


    async function uploadPhoto(product, file) {
        if (!file) return;
        setPhotoBusy(product.id); setPhotoError(''); setPhotoSaved(false);
        const body = new FormData(); body.append('photo', file);
        try {
            const response = await fetch('/cashier/menu/items/' + product.id + '/photo', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' }, body });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message ?? 'Foto menu gagal disimpan.');
            product.photoUrl = result.data.photo_url;
            setPhotoSaved(true);
        } catch (error) { setPhotoError(error.message); } finally { setPhotoBusy(null); }
    }

    function change(item, amount) {
        setCart((items) => items.flatMap((entry) => entry === item ? (entry.quantity + amount > 0 ? [{ ...entry, quantity: entry.quantity + amount }] : []) : [entry]));
    }

    async function submitPayment(event) {
        event.preventDefault();
        if (submitting) return;
        setPaymentError('');
        setSubmitting(true);
        const headers = { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' };
        try {
            const orderResponse = await fetch('/cashier/orders', { method: 'POST', headers, body: JSON.stringify({ table_id: table?.id, items: cart.map((item) => ({ product_id: item.productId, size: item.size, quantity: item.quantity })) }) });
            const order = await orderResponse.json().catch(() => ({}));
            if (!orderResponse.ok) { setPaymentError(order.message ?? 'Pesanan gagal dibuat.'); return; }
            const response = await fetch(`/cashier/orders/${order.data.id}/payment`, { method: 'POST', headers, body: JSON.stringify({ method: paymentMethod, received_amount: Number(receivedAmount), validated: qrisValidated, idempotency_key: crypto.randomUUID() }) });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) { setPaymentError(result.message ?? 'Pembayaran gagal.'); return; }
            setPaymentResult(result.data); setPaymentOpen(false); setCart([]); setReceivedAmount(''); setQrisValidated(false);
        } catch (error) {
            setPaymentError('Koneksi gagal. Periksa jaringan lalu coba lagi.');
        } finally {
            setSubmitting(false);
        }
    }

    const [clock, setClock] = useState(() => new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
    useEffect(() => {
        const t = setInterval(() => setClock(new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' })), 30000);
        return () => clearInterval(t);
    }, []);


    return <>
        <Head title="Workspace Kasir" />
        <div className="min-h-screen bg-cream text-ink lg:flex">
            <aside className="hidden w-64 shrink-0 border-r border-line bg-white p-6 lg:flex lg:flex-col">
                <div className="mb-10">{cafe.logoUrl && <img src={cafe.logoUrl} alt="Logo kedai" className="mb-4 h-14 w-auto max-w-[180px] object-contain" />}<div className="font-display text-2xl font-bold text-espresso">{cafe.name}</div><div className="mt-1 text-sm text-muted">POS operasional</div></div>
                <nav aria-label="Navigasi kasir" className="space-y-2 text-sm font-semibold">
                    <a className="flex items-center gap-3 rounded-xl bg-terracotta-soft px-4 py-3 text-espresso" aria-current="page"><span aria-hidden="true" className="text-lg leading-none">▦</span> Pesanan</a>
                    <button type="button" onClick={() => setTableOpen(true)} className="flex min-h-11 w-full items-center gap-3 rounded-xl px-4 py-3 text-left text-muted transition hover:bg-cream hover:text-espresso"><span aria-hidden="true" className="text-lg leading-none">▦</span> Meja</button>
                </nav>
                <div className="mt-auto border-t border-line pt-5 text-sm text-muted"><div className="font-semibold text-ink">{user.name}</div><div>Kasir</div><form method="post" action="/logout" className="mt-4"><input type="hidden" name="_token" value={document.querySelector('meta[name=\"csrf-token\"]')?.content ?? ''} /><button type="submit" className="min-h-11 w-full rounded-xl border border-line px-4 text-sm font-semibold text-ink transition hover:border-terracotta hover:text-terracotta">Keluar</button></form></div>
            </aside>
            <main className="min-w-0 flex-1 p-4 sm:p-6 lg:p-8">
                {failedPrintJobs.length > 0 && <section role="alert" className="mb-5 rounded-2xl border border-danger/30 bg-red-50 p-4 text-danger"><div className="flex items-center justify-between gap-3"><div><strong>Printer perlu dicek</strong><p className="mt-1 text-sm">{failedPrintJobs.length} struk gagal dicetak.</p></div><div className="space-y-2 text-right">{failedPrintJobs.map((job) => <button key={job.id} onClick={async () => { const response = await fetch(`/cashier/print-jobs/${job.id}/retry`, { method: "POST", headers: { "X-CSRF-TOKEN": document.querySelector("meta[name=\"csrf-token\"]")?.content ?? "", Accept: "application/json" } }); if (response.ok) window.location.reload(); }} className="ml-2 min-h-9 rounded-lg bg-danger px-3 text-xs font-bold text-white">Ulangi {job.order?.number ?? "struk"}</button>)}</div></div></section>}
                <header className="mb-6 flex items-center justify-between"><div><p className="font-mono text-xs font-medium uppercase tracking-[.18em] text-terracotta">{cafe.name} · {clock}</p><h1 className="mt-2 font-display text-4xl font-bold text-espresso">Racik pesanan</h1><p className="mt-1 text-sm text-muted">Pilih menu, ukuran, lalu antar ke meja.</p></div><div className="rounded-full bg-white px-4 py-2 text-sm font-semibold shadow-sm" aria-hidden="true">● Terhubung</div></header>
                <div className="mb-5 flex flex-col gap-3 sm:flex-row">
                    <div role="group" aria-label="Filter kategori" className="flex gap-2 overflow-x-auto">
                        <button onClick={() => setCategory('Semua')} className={`min-h-11 whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold ${category === 'Semua' ? 'bg-espresso text-white' : 'bg-white text-muted'}`} aria-pressed={category === 'Semua'}>Semua</button>
                        {categories.map((item) => <button key={item.id} onClick={() => setCategory(item.name)} className={`min-h-11 whitespace-nowrap rounded-full px-4 py-2 text-sm font-semibold ${category === item.name ? 'bg-espresso text-white' : 'bg-white text-muted'}`} aria-pressed={category === item.name}>{item.name}</button>)}
                    </div>
                    <label className="flex min-h-11 flex-1 items-center gap-2 rounded-xl border border-line bg-white px-4 text-muted">
                        <span aria-hidden="true" className="text-lg leading-none">⌕</span>
                        <input aria-label="Cari menu" value={query} onChange={(event) => setQuery(event.target.value)} className="w-full border-0 bg-transparent outline-none" placeholder="Cari menu" />
                    </label>
                </div>
                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                    <section aria-label="Daftar menu" className="grid grid-cols-2 gap-4 md:grid-cols-3">
                        {visibleProducts.length === 0 ? <div role="status" className="col-span-full rounded-2xl border border-dashed border-line bg-white p-8 text-center"><p className="font-display text-xl font-bold text-espresso">Menu tidak ditemukan.</p><p className="mt-2 text-sm text-muted">Coba kata kunci atau kategori lain.</p></div> : visibleProducts.map((product) => <article key={product.id} className="group overflow-hidden rounded-3xl border border-line bg-white shadow-[0_12px_30px_rgba(76,51,31,.08)] transition duration-200 hover:-translate-y-1 hover:shadow-[0_18px_38px_rgba(76,51,31,.14)]"><div className="relative m-2 mb-0 flex h-32 items-end overflow-hidden rounded-2xl bg-terracotta-soft">{product.photoUrl ? <><img src={product.photoUrl} alt={product.name} onError={(event) => { event.currentTarget.hidden = true; event.currentTarget.nextElementSibling.hidden = false; }} loading="lazy" className="h-full w-full object-cover transition duration-300 group-hover:scale-105" /><span hidden className="p-4 font-display text-2xl font-bold text-espresso" aria-hidden="true">{product.name.slice(0, 1)}</span></> : <span className="p-4 font-display text-2xl font-bold text-espresso" aria-hidden="true">{product.name.slice(0, 1)}</span>}</div><div className="p-4 pt-3"><div className="flex min-h-14 items-start justify-between gap-2"><div><h2 className="font-display text-lg font-bold leading-tight text-espresso">{product.name}</h2><p className="mt-1 text-xs text-muted">Sisa {product.stock}</p></div><span className="shrink-0 rounded-full bg-green-50 px-2 py-1 text-[11px] font-bold text-success">Tersedia</span></div><label className="mb-3 flex min-h-11 cursor-pointer items-center justify-center rounded-xl border border-dashed border-line px-2 text-xs font-semibold text-muted transition hover:border-terracotta hover:bg-terracotta-soft hover:text-terracotta"><input type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(event) => uploadPhoto(product, event.target.files?.[0])} />{photoBusy === product.id ? 'Mengunggah...' : 'Upload foto'}</label><div className="grid grid-cols-2 gap-2">{Object.entries(product.sizes).map(([size, price]) => <button key={size} onClick={() => add(product, size)} aria-label={`Tambah ${product.name} ${size} — ${money(price, cafe.currency)}`} className="min-h-11 rounded-xl border border-line bg-cream/40 p-2 text-left transition hover:border-terracotta hover:bg-terracotta-soft"><span className="block text-xs text-muted">{size}</span><strong className="block text-sm text-espresso">{money(price)}</strong></button>)}</div></div></article>)}
                    </section>
                    <aside aria-label="Keranjang pesanan" className="h-fit rounded-2xl bg-espresso p-5 text-white xl:sticky xl:top-6">
                        <div className="flex items-center justify-between"><div><p className="text-sm text-white/60">Meja</p><button onClick={() => setTableOpen(true)} aria-label={`Ganti meja, sekarang: ${table?.name}`} className="mt-1 text-xl font-bold underline decoration-terracotta underline-offset-4">{table?.name}</button></div><div className="flex items-center gap-2"><span className="rounded-full bg-white/10 px-3 py-1 text-xs">Order baru</span>{table?.status === 'occupied' && !table?.activeOrders && <button type="button" disabled={tableSaving} onClick={markTableAvailable} className="rounded-full bg-white/10 px-3 py-1 text-xs underline">{tableSaving ? 'Menyimpan...' : 'Meja kosong'}</button>}</div></div>
                        <div className="my-6 space-y-4">{cart.map((item) => <div key={`${item.productId}-${item.size}`} className="border-b border-white/10 pb-4"><div className="flex justify-between gap-3"><div><p className="font-semibold">{item.name}</p><p className="text-sm text-white/60">{item.size} · {money(item.unitPrice, cafe.currency)} / unit</p></div><strong>{money(item.unitPrice * item.quantity, cafe.currency)}</strong></div><div className="mt-3 flex items-center gap-3"><button onClick={() => change(item, -1)} aria-label={`Kurangi ${item.name} ${item.size}`} className="min-h-11 w-11 rounded-lg bg-white/10">−</button><span aria-live="polite" aria-atomic="true">{item.quantity}</span><button onClick={() => change(item, 1)} aria-label={`Tambah ${item.name} ${item.size}`} className="min-h-11 w-11 rounded-lg bg-white/10">+</button></div></div>)}</div>
                        <div className="flex justify-between border-t border-white/20 pt-4 text-lg"><span>Total</span><strong>{money(total, cafe.currency)}</strong></div>
                        <button disabled={!cart.length || !table} onClick={() => { setPaymentError(''); setPaymentOpen(true); }} className="mt-5 w-full min-h-11 rounded-xl bg-terracotta px-4 py-3 font-bold text-white shadow-lg shadow-terracotta/20 disabled:cursor-not-allowed disabled:opacity-40">Proses Pembayaran</button>
                        {paymentResult && <p role="status" className="mt-4 rounded-xl bg-green-500/20 p-3 text-sm">Pembayaran berhasil. Kembalian: {money(paymentResult.change, cafe.currency)}</p>}
                    </aside>
                </div>{photoError && <p role="alert" className="mt-4 text-sm text-danger">{photoError}</p>}{photoSaved && <p role="status" className="mt-4 text-sm text-success">Foto tersimpan.</p>}
            </main>
            {tableOpen && <div role="dialog" aria-modal="true" aria-labelledby="dialog-table-title" className="fixed inset-0 z-10 grid place-items-center bg-black/40 p-4"><div ref={tableDialogRef} tabIndex={-1} className="w-full max-w-md rounded-2xl bg-white p-6 text-ink shadow-2xl outline-none"><h2 id="dialog-table-title" className="font-display text-2xl font-bold text-espresso">Pilih meja</h2><div className="mt-5 grid grid-cols-2 gap-3">{tables.map((item) => <button key={item.id} onClick={() => { setTable(item); setTableOpen(false); }} className={`min-h-14 rounded-xl border p-3 text-left ${item.status === 'occupied' ? 'border-warning bg-orange-50' : 'border-line'}`}><strong>{item.name}</strong><span className="mt-1 block text-xs text-muted">{item.activeOrders ? `${item.activeOrders} order aktif` : 'Tersedia'}</span></button>)}</div><button onClick={() => setTableOpen(false)} className="mt-5 min-h-11 w-full rounded-xl border border-line">Kembali</button></div></div>}
            {paymentOpen && <div role="dialog" aria-modal="true" aria-labelledby="dialog-payment-title" className="fixed inset-0 z-10 grid place-items-center bg-black/40 p-4"><form ref={paymentDialogRef} tabIndex={-1} onSubmit={submitPayment} className="w-full max-w-md rounded-2xl bg-white p-6 text-ink shadow-2xl outline-none"><h2 id="dialog-payment-title" className="font-display text-2xl font-bold text-espresso">Konfirmasi pembayaran</h2><p className="mt-1 text-sm text-muted">Total server: {money(total)}</p><p aria-live="assertive" className={`mt-3 text-sm font-semibold ${paymentExpired || secondsLeft < 10 ? 'text-danger' : 'text-terracotta'}`}>{paymentExpired ? 'Pembayaran kedaluwarsa.' : `Selesaikan dalam ${secondsLeft} detik.`}</p>{!paymentExpired && <><div className="mt-5 grid grid-cols-2 gap-2">{['cash', 'qris_manual'].map((method) => <button type="button" key={method} onClick={() => setPaymentMethod(method)} aria-pressed={paymentMethod === method} className={`min-h-11 rounded-xl border px-3 text-sm font-semibold ${paymentMethod === method ? 'border-espresso bg-espresso text-white' : 'border-line'}`}>{method === 'cash' ? 'Tunai' : 'QRIS manual'}</button>)}</div><label className="mt-5 block text-sm font-semibold">Jumlah diterima<input required type="number" min={total} value={receivedAmount} onChange={(event) => setReceivedAmount(event.target.value)} className="mt-2 min-h-11 w-full rounded-xl border border-line px-3" /></label>{paymentMethod === 'qris_manual' && <label className="mt-4 flex gap-2 text-sm"><input type="checkbox" checked={qrisValidated} onChange={(event) => setQrisValidated(event.target.checked)} /> Saya sudah melihat pembayaran QRIS pada rekening.</label>}{paymentError && <p role="alert" className="mt-4 text-sm text-danger">{paymentError}</p>}</>}<div className="mt-6 flex gap-2"><button type="button" onClick={() => { setPaymentOpen(false); setPaymentExpired(false); }} className="min-h-11 flex-1 rounded-xl border border-line">Tutup</button>{!paymentExpired && <button disabled={submitting} className="min-h-11 flex-1 rounded-xl bg-espresso font-bold text-white disabled:opacity-50">{submitting ? 'Memproses...' : 'Bayar'}</button>}</div></form></div>}
        </div>
    </>;
}
