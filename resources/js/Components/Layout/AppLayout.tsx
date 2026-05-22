import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, PlusSquare, BarChart2, Settings, Target, TrendingUp, X, ChevronLeft, ChevronRight, PiggyBank } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useEffect, useRef, useState } from 'react';
import { createPortal } from 'react-dom';
import type { SharedProps } from '@/types/index.d';

const navItems = [
    { href: '/',         label: 'Dashboard',    icon: LayoutDashboard },
    { href: '/goal',     label: 'Obiettivo',    icon: Target },
    { href: '/input',    label: 'Input Dati',   icon: PlusSquare },
    { href: '/pension',  label: 'Fondo Pensione', icon: PiggyBank },
    { href: '/analysis', label: 'Analisi',      icon: BarChart2 },
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

function NavItem({
    href,
    label,
    icon: Icon,
    active,
    collapsed,
}: {
    href: string;
    label: string;
    icon: React.ElementType;
    active: boolean;
    collapsed: boolean;
}) {
    const ref = useRef<HTMLDivElement>(null);
    const [tooltip, setTooltip] = useState<{ top: number; left: number } | null>(null);

    const showTooltip = () => {
        if (!collapsed || !ref.current) return;
        const rect = ref.current.getBoundingClientRect();
        setTooltip({ top: rect.top + rect.height / 2, left: rect.right + 8 });
    };

    const hideTooltip = () => setTooltip(null);

    return (
        <div ref={ref} onMouseEnter={showTooltip} onMouseLeave={hideTooltip}>
            <Link
                href={href}
                className={cn(
                    'flex items-center gap-3 px-2 py-2 rounded-md text-sm font-medium transition-colors',
                    collapsed ? 'justify-center' : '',
                    active
                        ? 'bg-primary text-primary-foreground'
                        : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                )}
            >
                <Icon className="w-4 h-4 flex-shrink-0" />
                {!collapsed && <span className="whitespace-nowrap">{label}</span>}
            </Link>
            {tooltip && createPortal(
                <div
                    className="fixed z-50 pointer-events-none"
                    style={{ top: tooltip.top, left: tooltip.left, transform: 'translateY(-50%)' }}
                >
                    <div className="bg-popover text-popover-foreground text-xs font-medium px-2 py-1 rounded-md shadow-md border border-border whitespace-nowrap">
                        {label}
                    </div>
                </div>,
                document.body,
            )}
        </div>
    );
}

export default function AppLayout({ children }: { children: React.ReactNode }) {
    const { url } = usePage();
    const [collapsed, setCollapsed] = useState(() => localStorage.getItem('sidebar-collapsed') === 'true');

    useEffect(() => {
        document.documentElement.classList.add('dark');
    }, []);

    const isActive = (href: string) => {
        if (href === '/') return url === '/';
        return url.startsWith(href);
    };

    return (
        <div className="flex h-screen overflow-hidden bg-background">
            {/* Sidebar */}
            <aside
                className={cn(
                    'relative flex-shrink-0 border-r border-border flex flex-col transition-all duration-300 ease-in-out overflow-x-visible',
                    collapsed ? 'w-[60px]' : 'w-56',
                )}
            >
                {/* Logo */}
                <div className={cn(
                    'flex items-center border-b border-border h-[60px] overflow-hidden',
                    collapsed ? 'justify-center px-0' : 'gap-2 px-4',
                )}>
                    <TrendingUp className="w-5 h-5 text-primary flex-shrink-0" />
                    {!collapsed && (
                        <span className="font-bold text-base text-foreground whitespace-nowrap">Wealth Tracker</span>
                    )}
                </div>

                {/* Navigation */}
                <nav className="flex-1 px-2 py-4 space-y-1 overflow-y-auto overflow-x-hidden">
                    {navItems.map(({ href, label, icon: Icon }) => (
                        <NavItem
                            key={href}
                            href={href}
                            label={label}
                            icon={Icon}
                            active={isActive(href)}
                            collapsed={collapsed}
                        />
                    ))}
                </nav>

                {/* Toggle button */}
                <div className="px-2 py-3 border-t border-border">
                    <button
                        onClick={() => setCollapsed((c) => { localStorage.setItem('sidebar-collapsed', String(!c)); return !c; })}
                        className={cn(
                            'flex items-center gap-2 w-full px-2 py-2 rounded-md text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors',
                            collapsed ? 'justify-center' : '',
                        )}
                    >
                        {collapsed ? (
                            <ChevronRight className="w-4 h-4 flex-shrink-0" />
                        ) : (
                            <>
                                <ChevronLeft className="w-4 h-4 flex-shrink-0" />
                                <span>Comprimi</span>
                            </>
                        )}
                    </button>
                </div>
            </aside>

            {/* Main content */}
            <main className="flex-1 overflow-y-auto">
                {children}
            </main>

            <FlashMessage />
        </div>
    );
}
