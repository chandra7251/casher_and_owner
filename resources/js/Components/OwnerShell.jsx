import { Link } from '@inertiajs/react';

const nav = [
    ['/owner/dashboard', 'Ringkasan', 'home'],
    ['/owner/menu', 'Menu & stok', 'menu'],
    ['/owner/tables', 'Meja', 'table'],
    ['/owner/reports', 'Laporan', 'report'],
    ['/owner/settings', 'Pengaturan', 'settings'],
];

function csrf() { return document.querySelector('meta[name="csrf-token"]')?.content ?? ''; }

function NavIcon({ name }) {
    const paths = {
        home: 'M3 10.5 12 3l9 7.5M5 9v10h14V9M9 19v-6h6v6',
        menu: 'M4 6h16M4 12h16M4 18h16',
        table: 'M4 5h16v14H4zM4 10h16M10 5v14',
        report: 'M5 19V5h14v14M8 16v-4M12 16V8M16 16v-6',
        settings: 'M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7ZM19 12a7 7 0 0 0-.1-1l2-1.5-2-3.5-2.4 1a7 7 0 0 0-1.7-1L14.5 3h-5l-.3 3a7 7 0 0 0-1.7 1l-2.4-1-2 3.5L5.1 11a7 7 0 0 0 0 2l-2 1.5 2 3.5 2.4-1a7 7 0 0 0 1.7-1l.3 3h5l.3-3a7 7 0 0 0 1.7-1l2.4 1 2.4 1 2-3.5-2-1.5c.1-.3.1-.7.1-1Z',
    };
    return <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.8" className="h-5 w-5"><path d={paths[name]} strokeLinecap="round" strokeLinejoin="round" /></svg>;
}

function LogoutButton() {
    return <form method="post" action="/logout" className="mt-5"><input type="hidden" name="_token" value={csrf()} /><button type="submit" className="flex min-h-11 w-full items-center justify-center rounded-xl border border-white/15 px-4 text-sm font-semibold text-cream/80 transition hover:border-terracotta-soft hover:bg-white/10 hover:text-white">Keluar dari workspace</button></form>;
}

export default function OwnerShell({ title, eyebrow = 'Owner workspace', description, children }) {
    return <div className="min-h-screen bg-cream text-ink lg:flex">
        <aside className="border-b border-line/80 bg-espresso text-cream lg:sticky lg:top-0 lg:flex lg:h-screen lg:w-72 lg:flex-col lg:border-b-0 lg:border-r lg:border-white/10">
            <div className="flex items-center justify-between px-5 py-5 lg:block lg:px-7 lg:py-8">
                <div><p className="font-mono text-[11px] uppercase tracking-[.24em] text-terracotta-soft">Kedai Senja</p><p className="mt-2 font-display text-3xl font-bold tracking-tight">Back office</p><p className="mt-2 max-w-[12rem] text-xs leading-5 text-cream/50">Ruang tenang untuk keputusan kedai.</p></div>
                <span className="rounded-full border border-white/15 px-3 py-1 text-[11px] text-cream/70">Owner</span>
            </div>
            <nav aria-label="Navigasi owner" className="flex gap-2 overflow-x-auto px-4 pb-4 lg:block lg:space-y-2 lg:px-4">
                {nav.map(([href, label, icon]) => { const active = window.location.pathname === href; return <Link key={href} href={href} aria-current={active ? 'page' : undefined} className={'group flex min-h-12 min-w-max items-center gap-3 rounded-2xl px-4 text-sm font-semibold transition hover:bg-white/10 hover:text-white focus:outline-none focus:ring-2 focus:ring-terracotta-soft ' + (active ? 'bg-terracotta text-white shadow-lg shadow-terracotta/20' : 'text-cream/70')}><NavIcon name={icon} /><span>{label}</span>{active && <span className="ml-auto hidden h-1.5 w-1.5 rounded-full bg-cream lg:block" />}</Link>; })}
            </nav>
            <div className="mt-4 px-4 pb-4 lg:mt-auto lg:px-7 lg:pb-7"><div className="cafe-note-dark"><p className="text-xs uppercase tracking-widest text-cream/50">Hari ini</p><p className="mt-2 text-sm leading-6 text-cream/75">Atur menu, stok, meja, dan laporan dari satu tempat.</p></div><LogoutButton /></div>
        </aside>
        <main className="min-w-0 flex-1"><div className="mx-auto max-w-[1440px] px-5 py-7 sm:px-8 lg:px-12 lg:py-10"><header className="mb-8"><div className="flex items-start justify-between gap-5"><div><p className="font-mono text-[11px] uppercase tracking-[.22em] text-terracotta">{eyebrow}</p><h1 className="mt-3 font-display text-4xl font-bold tracking-tight text-espresso sm:text-5xl">{title}</h1>{description && <p className="mt-3 max-w-2xl text-base leading-7 text-muted">{description}</p>}</div><div className="hidden shrink-0 items-center gap-2 rounded-full border border-line bg-white px-4 py-2 text-xs font-semibold text-muted shadow-sm md:flex"><span className="h-2 w-2 rounded-full bg-success" />Kedai Senja · live</div></div><div className="mt-7 h-px bg-line/80" /></header>{children}</div></main>
    </div>;
}
