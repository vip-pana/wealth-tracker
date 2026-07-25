import { router, usePage } from '@inertiajs/react';
import { Bell, AlertTriangle, CheckCircle2, Info, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';
import type { AppNotification, SharedProps } from '@/types/index.d';

const LEVEL_ICON = {
    success: CheckCircle2,
    warning: AlertTriangle,
    info: Info,
} as const;

const LEVEL_COLOR = {
    success: 'text-emerald-500',
    warning: 'text-amber-500',
    info: 'text-sky-500',
} as const;

function timeAgo(iso: string | null): string {
    if (!iso) return '';
    const diff = Date.now() - new Date(iso).getTime();
    const min = Math.floor(diff / 60000);
    if (min < 1) return 'ora';
    if (min < 60) return `${min} min fa`;
    const h = Math.floor(min / 60);
    if (h < 24) return `${h} h fa`;
    const d = Math.floor(h / 24);
    return `${d} g fa`;
}

export function NotificationBell({
    collapsed = false,
    direction = 'up',
    variant = 'sidebar',
}: {
    collapsed?: boolean;
    direction?: 'up' | 'down';
    variant?: 'sidebar' | 'icon';
}) {
    const { notifications } = usePage<{ notifications: SharedProps['notifications'] }>().props;
    const items = notifications ?? [];
    const count = items.length;

    const [open, setOpen] = useState(false);
    const ref = useRef<HTMLDivElement>(null);

    // Close on outside click / Escape.
    useEffect(() => {
        if (!open) return;
        const onClick = (e: MouseEvent) => {
            if (ref.current && !ref.current.contains(e.target as Node)) setOpen(false);
        };
        const onKey = (e: KeyboardEvent) => {
            if (e.key === 'Escape') setOpen(false);
        };
        document.addEventListener('mousedown', onClick);
        document.addEventListener('keydown', onKey);
        return () => {
            document.removeEventListener('mousedown', onClick);
            document.removeEventListener('keydown', onKey);
        };
    }, [open]);

    const openNotification = (n: AppNotification) => {
        setOpen(false);
        router.post(`/notifications/${n.id}/read`, {}, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (n.action_url) router.visit(n.action_url);
            },
        });
    };

    const markAllRead = () => {
        router.post('/notifications/read-all', {}, { preserveScroll: true, preserveState: true });
    };

    return (
        <div ref={ref} className="relative">
            <button
                onClick={() => setOpen((o) => !o)}
                className={cn(
                    'relative flex items-center transition-colors text-muted-foreground hover:text-accent-foreground',
                    variant === 'sidebar'
                        ? cn('gap-2 w-full px-2 py-2 rounded-md text-xs hover:bg-accent', collapsed ? 'justify-center' : '')
                        : 'p-2 rounded-md hover:bg-accent',
                )}
                title="Notifiche"
                aria-label={count > 0 ? `Notifiche (${count} non lette)` : 'Notifiche'}
            >
                <span className="relative flex-shrink-0">
                    <Bell className={variant === 'icon' ? 'w-5 h-5' : 'w-4 h-4'} />
                    {count > 0 && (
                        <span className="absolute -top-1.5 -right-1.5 min-w-[15px] h-[15px] px-1 rounded-full bg-destructive text-destructive-foreground text-[9px] font-bold flex items-center justify-center">
                            {count > 9 ? '9+' : count}
                        </span>
                    )}
                </span>
                {variant === 'sidebar' && !collapsed && <span>Notifiche</span>}
            </button>

            {open && (
                <div className={cn(
                    'absolute z-50 w-80 max-w-[calc(100vw-2rem)] rounded-md border border-border bg-popover shadow-lg',
                    direction === 'up' ? 'bottom-full mb-2 left-0' : 'top-full mt-2 right-0',
                )}>
                    <div className="flex items-center justify-between px-3 py-2 border-b border-border">
                        <span className="text-sm font-medium">Notifiche</span>
                        {count > 0 && (
                            <button
                                onClick={markAllRead}
                                className="text-xs text-muted-foreground hover:text-foreground transition-colors"
                            >
                                Segna tutte lette
                            </button>
                        )}
                    </div>

                    {count === 0 ? (
                        <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                            Nessuna notifica.
                        </p>
                    ) : (
                        <div className="max-h-96 overflow-y-auto divide-y divide-border">
                            {items.map((n) => {
                                const Icon = LEVEL_ICON[n.level] ?? Info;
                                return (
                                    <div key={n.id} className="flex items-start gap-2 px-3 py-2.5 hover:bg-muted/40 transition-colors">
                                        <Icon className={cn('w-4 h-4 flex-shrink-0 mt-0.5', LEVEL_COLOR[n.level] ?? '')} />
                                        <button
                                            onClick={() => openNotification(n)}
                                            className="flex-1 min-w-0 text-left"
                                        >
                                            <p className="text-sm font-medium leading-snug">{n.title}</p>
                                            {n.body && <p className="text-xs text-muted-foreground mt-0.5">{n.body}</p>}
                                            <p className="text-[11px] text-muted-foreground mt-1">{timeAgo(n.created_at)}</p>
                                        </button>
                                        <button
                                            onClick={() => openNotification(n)}
                                            className="flex-shrink-0 text-muted-foreground hover:text-foreground transition-colors"
                                            aria-label="Segna come letta"
                                            title="Segna come letta"
                                        >
                                            <X className="w-3.5 h-3.5" />
                                        </button>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
