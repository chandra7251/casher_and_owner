import { Head } from '@inertiajs/react';
import { useRef, useState } from 'react';
import OwnerShell from '../../Components/OwnerShell';

const headers = () => ({ 'Content-Type': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' });
const emptyForm = (categoryId = '') => ({ name: '', menu_category_id: categoryId, regular_price: 0, regular_stock: 0, regular_threshold: 0, large_price: 0, large_stock: 0, large_threshold: 0 });

export default function Menu({ items = [], categories = [] }) {
    const [rows, setRows] = useState(items);
    const [categoryRows, setCategoryRows] = useState(categories);
    const [categoryName, setCategoryName] = useState('');
    const [form, setForm] = useState(emptyForm(categories[0]?.id ?? ''));
    const [editingId, setEditingId] = useState(null);
    const [error, setError] = useState('');
    const [busy, setBusy] = useState(false);
    const [photoBusy, setPhotoBusy] = useState(null);

    async function request(url, method, body) {
        const response = await fetch(url, { method, headers: headers(), body: body ? JSON.stringify(body) : undefined });
        const result = await response.json().catch(() => ({}));
        if (!response.ok) throw new Error(result.message ?? 'Perubahan gagal disimpan.');
        return result;
    }

    function setField(key) { return (event) => setForm((current) => ({ ...current, [key]: event.target.value })); }
    function editSize(size, key, value) { setRows((current) => current.map((item) => ({ ...item, sizes: item.sizes.map((entry) => entry.id === size.id ? { ...entry, [key]: Number(value) } : entry) }))); }

    async function saveItem(event) {
        event.preventDefault(); setBusy(true); setError('');
        const payload = { menu_category_id: Number(form.menu_category_id), name: form.name.trim(), is_available: true, sizes: [
            { size: 'Regular', price: Number(form.regular_price), on_hand: Number(form.regular_stock), low_stock_threshold: Number(form.regular_threshold) },
            { size: 'Large', price: Number(form.large_price), on_hand: Number(form.large_stock), low_stock_threshold: Number(form.large_threshold) },
        ] };
        try {
            if (editingId) {
                const result = await request('/owner/menu/items/' + editingId, 'PATCH', { menu_category_id: payload.menu_category_id, name: payload.name, is_available: true });
                setRows((current) => current.map((item) => item.id === editingId ? { ...item, ...result.data } : item));
            } else {
                const result = await request('/owner/menu/items', 'POST', payload);
                const category = categoryRows.find((item) => item.id === payload.menu_category_id);
                setRows((current) => [...current, { ...result.data, category: category?.name }]);
            }
            setForm(emptyForm(categoryRows[0]?.id ?? '')); setEditingId(null);
        } catch (exception) { setError(exception.message); } finally { setBusy(false); }
    }

    function beginEdit(item) {
        const regular = item.sizes.find((size) => size.size === 'Regular') ?? {};
        const large = item.sizes.find((size) => size.size === 'Large') ?? {};
        setForm({ name: item.name, menu_category_id: item.menu_category_id, regular_price: regular.price ?? 0, regular_stock: regular.on_hand ?? 0, regular_threshold: regular.low_stock_threshold ?? 0, large_price: large.price ?? 0, large_stock: large.on_hand ?? 0, large_threshold: large.low_stock_threshold ?? 0 });
        setEditingId(item.id); window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    async function addCategory(event) { event.preventDefault(); if (!categoryName.trim()) return; try { const result = await request('/owner/menu/categories', 'POST', { name: categoryName.trim(), sort_order: categoryRows.length }); setCategoryRows((current) => [...current, result.data]); setCategoryName(''); } catch (exception) { setError(exception.message); } }
    async function removeCategory(category) { try { await request('/owner/menu/categories/' + category.id, 'DELETE'); setCategoryRows((current) => current.filter((item) => item.id !== category.id)); } catch (exception) { setError(exception.message); } }
    async function toggle(item) { try { const result = await request('/owner/menu/items/' + item.id + '/availability', 'PATCH', { is_available: !item.is_available }); setRows((current) => current.map((entry) => entry.id === item.id ? { ...entry, is_available: result.data.is_available } : entry)); } catch (exception) { setError(exception.message); } }

    async function uploadPhoto(item, file) {
        if (!file) return;
        setPhotoBusy(item.id); setError('');
        const body = new FormData(); body.append('photo', file);
        try {
            const response = await fetch('/owner/menu/items/' + item.id + '/photo', { method: 'POST', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '', Accept: 'application/json' }, body });
            const result = await response.json().catch(() => ({}));
            if (!response.ok) throw new Error(result.message ?? 'Foto menu gagal disimpan.');
            setRows((current) => current.map((entry) => entry.id === item.id ? { ...entry, photo_url: result.data.photo_url, photo_path: result.data.photo_path } : entry));
        } catch (exception) { setError(exception.message); } finally { setPhotoBusy(null); }
    }

    async function saveSize(size) { setBusy(true); try { const result = await request('/owner/menu/sizes/' + size.id, 'PATCH', { price: size.price, on_hand: size.on_hand, low_stock_threshold: size.low_stock_threshold }); setRows((current) => current.map((item) => ({ ...item, sizes: item.sizes.map((entry) => entry.id === size.id ? result.data : entry) }))); } catch (exception) { setError(exception.message); } finally { setBusy(false); } }

    return <><Head title="Menu Owner" /><OwnerShell title="Menu & stok" description="Kelola katalog, harga, ketersediaan, dan batas stok dari satu ruang kerja.">
        <header className="mb-7"><p className="font-mono text-xs uppercase tracking-[.18em] text-terracotta">Owner workspace</p><h1 className="mt-2 font-display text-4xl font-bold text-espresso">Kelola menu</h1><p className="mt-2 text-muted">Kelola produk, ukuran, harga, dan stok.</p></header>
        {error && <p role="alert" className="mb-4 rounded-xl bg-red-50 p-3 text-danger">{error}</p>}
        <section className="mb-6 rounded-2xl border border-line bg-white/85 p-5"><div className="flex items-center justify-between gap-3"><div><h2 className="font-display text-2xl font-bold text-espresso">{editingId ? 'Edit produk' : 'Tambah produk'}</h2><p className="mt-1 text-sm text-muted">Regular dan Large wajib diisi.</p></div>{editingId && <button type="button" onClick={() => setEditingId(null)} className="min-h-10 rounded-lg border border-line px-3 text-sm">Batal</button>}</div><form onSubmit={saveItem} className="mt-4 grid gap-3 md:grid-cols-3"><label className="text-sm font-semibold md:col-span-2">Nama produk<input required value={form.name} onChange={setField('name')} className="mt-1 min-h-11 w-full rounded-xl border border-line px-3" /></label><label className="text-sm font-semibold">Kategori<select required value={form.menu_category_id} onChange={setField('menu_category_id')} className="mt-1 min-h-11 w-full rounded-xl border border-line px-3">{categoryRows.map((category) => <option key={category.id} value={category.id}>{category.name}</option>)}</select></label><div className="rounded-xl bg-cream p-3"><strong>Regular</strong><div className="mt-2 grid grid-cols-3 gap-2"><input aria-label="Regular harga baru" type="number" min="0" required value={form.regular_price} onChange={setField('regular_price')} className="min-h-10 w-full rounded-lg border border-line px-2 text-sm" placeholder="Harga" /><input aria-label="Regular stok baru" type="number" min="0" required value={form.regular_stock} onChange={setField('regular_stock')} className="min-h-10 w-full rounded-lg border border-line px-2 text-sm" placeholder="Stok" /><input aria-label="Regular batas baru" type="number" min="0" required value={form.regular_threshold} onChange={setField('regular_threshold')} className="min-h-10 w-full rounded-lg border border-line px-2 text-sm" placeholder="Batas" /></div></div><div className="rounded-xl bg-cream p-3"><strong>Large</strong><div className="mt-2 grid grid-cols-3 gap-2"><input aria-label="Large harga baru" type="number" min="0" required value={form.large_price} onChange={setField('large_price')} className="min-h-10 w-full rounded-lg border border-line px-2 text-sm" placeholder="Harga" /><input aria-label="Large stok baru" type="number" min="0" required value={form.large_stock} onChange={setField('large_stock')} className="min-h-10 w-full rounded-lg border border-line px-2 text-sm" placeholder="Stok" /><input aria-label="Large batas baru" type="number" min="0" required value={form.large_threshold} onChange={setField('large_threshold')} className="min-h-10 w-full rounded-lg border border-line px-2 text-sm" placeholder="Batas" /></div></div><button disabled={busy || !categoryRows.length} className="min-h-11 rounded-xl bg-terracotta px-4 font-bold text-white disabled:opacity-50">{busy ? 'Menyimpan...' : editingId ? 'Simpan edit' : 'Tambah produk'}</button></form></section>
        <section className="mb-6 cafe-surface p-6"><div className="flex flex-wrap items-center justify-between gap-3"><h2 className="font-display text-2xl font-bold text-espresso">Kategori</h2><form onSubmit={addCategory} className="flex gap-2"><input aria-label="Nama kategori baru" value={categoryName} onChange={(event) => setCategoryName(event.target.value)} placeholder="Kategori baru" className="min-h-11 rounded-xl border border-line px-3" /><button className="min-h-11 rounded-xl bg-espresso px-4 font-bold text-white">Tambah</button></form></div><div className="mt-4 flex flex-wrap gap-2">{categoryRows.map((category) => <span key={category.id} className="inline-flex items-center gap-2 rounded-full bg-terracotta-soft px-3 py-2 text-sm font-semibold text-espresso">{category.name}<button type="button" aria-label={'Hapus ' + category.name} onClick={() => removeCategory(category)} className="min-h-6 px-1 text-danger">×</button></span>)}</div></section>
        <div className="grid gap-5 lg:grid-cols-2">{rows.map((item) => <article key={item.id} className="group overflow-hidden rounded-3xl border border-line bg-white shadow-[0_12px_30px_rgba(76,51,31,.07)] transition hover:-translate-y-1 hover:shadow-[0_18px_38px_rgba(76,51,31,.12)]"><div className="flex items-center justify-between gap-3"><div className="flex items-center gap-3">{item.photo_url ? <><img src={item.photo_url} alt={item.name} onError={(event) => { event.currentTarget.hidden = true; event.currentTarget.nextElementSibling.hidden = false; }} className="h-16 w-16 rounded-2xl object-cover" /><div hidden className="grid h-16 w-16 place-items-center rounded-2xl bg-terracotta-soft font-display text-xl font-bold text-espresso">{item.name.slice(0, 1)}</div></> : <div className="grid h-14 w-14 place-items-center rounded-xl bg-terracotta-soft font-display text-xl font-bold text-espresso">{item.name.slice(0, 1)}</div>}<div><p className="text-xs uppercase tracking-widest text-muted">{item.category ?? 'Tanpa kategori'}</p><h2 className="font-display text-2xl font-bold text-espresso">{item.name}</h2></div></div><div className="flex items-center gap-2"><button type="button" onClick={() => beginEdit(item)} className="min-h-10 rounded-lg border border-line px-3 text-xs font-bold">Edit</button><button onClick={() => toggle(item)} className={item.is_available ? 'min-h-11 rounded-xl bg-green-100 px-3 text-sm font-bold text-success' : 'min-h-11 rounded-xl bg-red-100 px-3 text-sm font-bold text-danger'}>{item.is_available ? 'Aktif' : 'Nonaktif'}</button></div></div><label className="mt-4 flex min-h-11 cursor-pointer items-center justify-center rounded-xl border border-dashed border-line px-3 text-sm font-semibold text-muted transition hover:border-terracotta hover:bg-terracotta-soft hover:text-terracotta"><input type="file" accept="image/png,image/jpeg,image/webp" className="sr-only" onChange={(event) => uploadPhoto(item, event.target.files?.[0])} />{photoBusy === item.id ? 'Mengunggah foto...' : 'Upload foto menu'}</label><div className="mt-4 space-y-3">{item.sizes.map((size) => <div key={size.id} className="rounded-2xl border border-line bg-cream/35 p-4"><div className="flex items-center justify-between"><strong>{size.size}</strong><span className="text-sm text-muted">Reserved {size.reserved}</span></div><div className="mt-3 grid grid-cols-3 gap-2 text-sm"><label>Harga<input aria-label={size.size + ' harga'} type="number" value={size.price} onChange={(event) => editSize(size, 'price', event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-line px-2" /></label><label>Stok<input aria-label={size.size + ' stok'} type="number" value={size.on_hand} onChange={(event) => editSize(size, 'on_hand', event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-line px-2" /></label><label>Batas<input aria-label={size.size + ' batas stok'} type="number" value={size.low_stock_threshold} onChange={(event) => editSize(size, 'low_stock_threshold', event.target.value)} className="mt-1 min-h-11 w-full rounded-lg border border-line px-2" /></label></div><button disabled={busy} onClick={() => saveSize(size)} className="mt-3 min-h-11 w-full rounded-xl bg-espresso font-semibold text-white disabled:opacity-50">{busy ? 'Menyimpan...' : 'Simpan'}</button></div>)}</div></article>)}</div>
    </OwnerShell></>;
}
