import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, PlusSquare, BarChart2, Settings, Target, TrendingUp, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useEffect, useRef, useState } from 'react';
import type { SharedProps } from '@/types/index.d';

const navItems = [
    { href: '/',         label: 'Dashboard',   icon: LayoutDashboard },
    { href: '/input',    label: 'Input Dati',   icon: PlusSquare },
    { href: '/analysis', label: 'Analisi',      icon: BarChart2 },
    { href: '/goal',     label: 'Obiettivo',    icon: Target },
    { href: '/settings', label: 'Impostazioni', icon: Settings },
];

function FlashMessage() {
    const { flash } = usePage<{ flash: SharedProps['flash'] }>().props;
    const [toasts, setToasts] = useState<{ id: number; message: string; type: 'success' | 'error' }[]>([]);
    const counterRef = useRef(0);

    useEffect(() => {
        const msg = flash.success ?? flash.error ?? null;
        if (!msg) return;
        const id = ++counterRef.current;
        const type = flash.success ? 'success' : 'error';
        setToasts((prev) => [...prev, { id, message: msg, type }]);
        const t = setTimeout(() => {
            setToasts((prev) => prev.filter((toast) => toast.id !== id));
        }, type === 'success' ? 3000 : 4000);
        return () => clearTimeout(t);
    }, [flash]);

    return (
        <div className="fixed top-4 right-4 z-50 flex flex-col gap-2">
            {toasts.map((toast) => (
                <div
                    key={toast.id}
                    className={cn(
                        'flex items-center gap-3 rounded-md px-4 py-3 text-sm font-medium shadow-lg',
                        toast.type === 'success'
                            ? 'bg-green-500 text-white'
                            : 'bg-destructive text-destructive-foreground',
                    )}
                >
                    <span>{toast.message}</span>
                    <button
                        onClick={() => setToasts((prev) => prev.filter((t) => t.id !== toast.id))}
                        className="ml-auto opacity-70 hover:opacity-100"
                        aria-label="Chiudi"
                    >
                        <X className="w-3.5 h-3.5" />
                    </button>
                </div>
            ))}
        </div>
    );
}

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { url } = usePage();

    useEffect(() => {
        document.documentElement.classList.add('dark');
    }, []);

    // Determine active nav link (match prefix)
    const isActive = (href: string) => {
        if (href === '/') return url === '/';
        return url.startsWith(href);
    };

    return (
        <div className="flex h-screen overflow-hidden bg-background">
            {/* Sidebar */}
            <aside className="w-64 flex-shrink-0 border-r border-border flex flex-col">
                {/* Logo */}
                <div className="flex items-center gap-2 px-6 py-5 border-b border-border">
                    <TrendingUp className="w-6 h-6 text-primary" />
                    <span className="font-bold text-lg text-foreground">Wealth Tracker</span>
                </div>

                {/* Navigation */}
                <nav className="flex-1 px-3 py-4 space-y-1 overflow-y-auto">
                    {navItems.map(({ href, label, icon: Icon }) => (
                        <Link
                            key={href}
                            href={href}
                            className={cn(
                                'flex items-center gap-3 px-3 py-2 rounded-md text-sm font-medium transition-colors',
                                isActive(href)
                                    ? 'bg-primary text-primary-foreground'
                                    : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )}
                        >
                            <Icon className="w-4 h-4 flex-shrink-0" />
                            {label}
                        </Link>
                    ))}
                </nav>


            </aside>

            {/* Main content */}
            <main className="flex-1 overflow-y-auto">
                {children}
            </main>

            <FlashMessage />
        </div>
    );
}
