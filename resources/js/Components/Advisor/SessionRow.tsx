import { useState } from 'react';
import { router } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import { AlertTriangle, Loader2, Pencil, Check, X } from 'lucide-react';
import { type SessionSummary } from '@/Components/Advisor/types';
import { KindIcon } from '@/Components/Advisor/KindIcon';
import { TypewriterText } from '@/Components/Advisor/TypewriterText';

export function SessionRow({ s, activeId, onRename }: { s: SessionSummary; activeId: number | null; onRename: (id: number, title: string) => void }) {
    const isActive = s.id === activeId;
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(s.title ?? '');

    const startEditing = () => { setDraft(s.title ?? ''); setEditing(true); };
    const commit = () => {
        const next = draft.trim();
        if (next !== '' && next !== (s.title ?? '')) onRename(s.id, next);
        setEditing(false);
    };

    if (editing) {
        return (
            <div className="flex items-center gap-1 rounded-md bg-primary/10 px-2 py-1.5">
                <KindIcon kind={s.kind} className="w-3.5 h-3.5 flex-shrink-0 text-muted-foreground" />
                <input
                    value={draft}
                    autoFocus
                    maxLength={120}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') { e.preventDefault(); commit(); }
                        else if (e.key === 'Escape') { e.preventDefault(); setEditing(false); }
                    }}
                    onBlur={commit}
                    className="flex-1 min-w-0 bg-transparent text-sm focus:outline-none"
                />
                <button type="button" className="flex-shrink-0 text-muted-foreground hover:text-foreground" title="Salva" onMouseDown={(e) => { e.preventDefault(); commit(); }}>
                    <Check className="w-3.5 h-3.5" />
                </button>
                <button type="button" className="flex-shrink-0 text-muted-foreground hover:text-foreground" title="Annulla" onMouseDown={(e) => { e.preventDefault(); setEditing(false); }}>
                    <X className="w-3.5 h-3.5" />
                </button>
            </div>
        );
    }

    return (
        <div
            onClick={() => { if (!isActive) router.visit(`/advisor/${s.id}`); }}
            className={cn(
                'group flex items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors cursor-pointer',
                isActive ? 'bg-primary/10 text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            )}
        >
            <KindIcon kind={s.kind} className="w-3.5 h-3.5 flex-shrink-0" />
            <TypewriterText
                id={s.id}
                text={s.title ?? 'Sessione'}
                className={cn('flex-1 min-w-0 truncate', s.unread && !isActive && 'font-semibold text-foreground')}
            />
            {s.generating
                ? <Loader2 className="w-3 h-3 flex-shrink-0 animate-spin" />
                : s.unread && !isActive
                    ? <span className="h-2 w-2 flex-shrink-0 rounded-full bg-primary" title="Risposta da leggere" />
                    : null}
            {s.status === 'failed' && <AlertTriangle className="w-3 h-3 flex-shrink-0 text-amber-500" />}
            <button
                type="button"
                className="flex-shrink-0 text-muted-foreground opacity-0 transition-opacity hover:text-foreground group-hover:opacity-100"
                title="Rinomina sessione"
                onClick={(e) => { e.stopPropagation(); startEditing(); }}
            >
                <Pencil className="w-3.5 h-3.5" />
            </button>
        </div>
    );
}
