import { Link, usePage } from '@inertiajs/react';
import { LayoutDashboard, PlusSquare, BarChart2, Settings, Target, TrendingUp, X, ChevronLeft, ChevronRight, PiggyBank, Sun, Moon, Menu } from 'lucide-react';
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
    onNavigate,
}: {
    href: string;
    label: string;
    icon: React.ElementType;
    active: boolean;
    collapsed: boolean;
    onNavigate?: () => void;
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
                onClick={onNavigate}
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
    const [mobileOpen, setMobileOpen] = useState(false);
    const [theme, setTheme] = useState<'dark' | 'light'>(() =>
        localStorage.getItem('theme') === 'light' ? 'light' : 'dark',
    );

    useEffect(() => {
        document.documentElement.classList.toggle('dark', theme === 'dark');
        localStorage.setItem('theme', theme);
    }, [theme]);

    // Only honor the collapsed width on desktop; the mobile drawer always shows labels.
    const [isDesktop, setIsDesktop] = useState(true);
    useEffect(() => {
        const mq = window.matchMedia('(min-width: 1024px)');
        const update = () => setIsDesktop(mq.matches);
        update();
        mq.addEventListener('change', update);
        return () => mq.removeEventListener('change', update);
    }, []);
    const showCollapsed = isDesktop && collapsed;

    const isActive = (href: string) => {
        if (href === '/') return url === '/';
        return url.startsWith(href);
    };

    return (
        <div className="flex h-screen overflow-hidden bg-background">
            {/* Mobile top bar */}
            <div className="lg:hidden fixed top-0 inset-x-0 z-30 h-[56px] flex items-center gap-2 px-4 border-b border-border bg-background">
                <button
                    onClick={() => setMobileOpen(true)}
                    className="p-2 -ml-2 rounded-md text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors"
                    aria-label="Apri menu"
                >
                    <Menu className="w-5 h-5" />
                </button>
                <TrendingUp className="w-5 h-5 text-primary flex-shrink-0" />
                <span className="font-bold text-base text-foreground">Wealth Tracker</span>
            </div>

            {/* Mobile overlay */}
            {mobileOpen && (
                <div
                    className="lg:hidden fixed inset-0 z-40 bg-black/50"
                    onClick={() => setMobileOpen(false)}
                />
            )}

            {/* Sidebar */}
            <aside
                className={cn(
                    'border-r border-border flex flex-col overflow-x-visible bg-background',
                    // Desktop: in-flow, collapsible width
                    'lg:relative lg:flex-shrink-0 lg:translate-x-0 lg:transition-all lg:duration-300 lg:ease-in-out',
                    collapsed ? 'lg:w-[60px]' : 'lg:w-56',
                    // Mobile: off-canvas drawer
                    'fixed inset-y-0 left-0 z-50 w-56 transition-transform duration-300 ease-in-out',
                    mobileOpen ? 'translate-x-0' : '-translate-x-full lg:translate-x-0',
                )}
            >
                {/* Logo */}
                <div className={cn(
                    'flex items-center border-b border-border h-[60px] overflow-hidden',
                    showCollapsed ? 'justify-center px-0' : 'gap-2 px-4',
                )}>
                    <TrendingUp className="w-5 h-5 text-primary flex-shrink-0" />
                    {!showCollapsed && (
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
                            collapsed={showCollapsed}
                            onNavigate={() => setMobileOpen(false)}
                        />
                    ))}
                </nav>

                {/* Toggle button */}
                <div className="px-2 py-3 border-t border-border space-y-1">
                    <button
                        onClick={() => setTheme((t) => (t === 'dark' ? 'light' : 'dark'))}
                        className={cn(
                            'flex items-center gap-2 w-full px-2 py-2 rounded-md text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors',
                            showCollapsed ? 'justify-center' : '',
                        )}
                        title={theme === 'dark' ? 'Tema chiaro' : 'Tema scuro'}
                    >
                        {theme === 'dark' ? (
                            <Sun className="w-4 h-4 flex-shrink-0" />
                        ) : (
                            <Moon className="w-4 h-4 flex-shrink-0" />
                        )}
                        {!showCollapsed && <span>{theme === 'dark' ? 'Tema chiaro' : 'Tema scuro'}</span>}
                    </button>
                    <button
                        onClick={() => setCollapsed((c) => { localStorage.setItem('sidebar-collapsed', String(!c)); return !c; })}
                        className={cn(
                            'hidden lg:flex items-center gap-2 w-full px-2 py-2 rounded-md text-xs text-muted-foreground hover:bg-accent hover:text-accent-foreground transition-colors',
                            showCollapsed ? 'justify-center' : '',
                        )}
                    >
                        {showCollapsed ? (
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
            <main className="flex-1 overflow-y-auto pt-[56px] lg:pt-0">
                {children}
            </main>

            <FlashMessage />
        </div>
    );
}
