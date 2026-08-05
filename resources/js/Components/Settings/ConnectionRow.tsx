import { useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

export type RowTone = 'ok' | 'warn' | 'error' | 'idle';

const DOT: Record<RowTone, string> = {
    ok: 'bg-emerald-500',
    warn: 'bg-amber-500',
    error: 'bg-destructive',
    idle: 'bg-muted-foreground',
};

const TEXT: Record<RowTone, string> = {
    ok: 'text-emerald-400',
    warn: 'text-amber-500',
    error: 'text-destructive',
    idle: 'text-muted-foreground',
};

// A settings entry that shows only its health when closed and reveals the
// operational detail on click. Rows in a non-ok state open by default: that is
// exactly when the detail is worth the space.
export function ConnectionRow({
    icon: Icon,
    title,
    tone,
    status,
    statusTitle,
    actions,
    defaultOpen,
    children,
}: {
    icon: React.ElementType;
    title: string;
    tone: RowTone;
    status: React.ReactNode;
    statusTitle?: string;
    actions?: React.ReactNode;
    defaultOpen?: boolean;
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState(defaultOpen ?? tone !== 'ok');

    return (
        <div>
            <div className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:gap-4">
                <button
                    type="button"
                    onClick={() => setOpen((o) => !o)}
                    aria-expanded={open}
                    className="flex min-w-0 flex-1 items-center gap-2.5 text-left"
                >
                    <ChevronDown
                        className={cn('w-4 h-4 shrink-0 text-muted-foreground transition-transform', open && 'rotate-180')}
                        aria-hidden
                    />
                    <Icon className="w-4 h-4 shrink-0 text-primary" aria-hidden />
                    <span className="min-w-0">
                        <span className="block text-sm font-medium truncate">{title}</span>
                        <span
                            className={cn('flex items-center gap-1.5 text-xs', TEXT[tone])}
                            title={statusTitle}
                        >
                            <span className={cn('w-1.5 h-1.5 rounded-full shrink-0', DOT[tone])} />
                            {status}
                        </span>
                    </span>
                </button>
                {actions && <div className="flex shrink-0 items-center gap-2">{actions}</div>}
            </div>
            {open && <div className="px-4 pb-4">{children}</div>}
        </div>
    );
}
