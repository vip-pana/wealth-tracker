import { useMemo, useState } from 'react';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { SegmentedToggle } from '@/Components/ui/SegmentedToggle';
import { Money } from '@/Components/ui/Money';
import { formatDateLong, formatMonthLong } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import { EyeOff, Wallet } from 'lucide-react';
import type { Account, Edit, FlowType, Transaction } from '@/types/cashflow';

const FLOW_OPTIONS: { value: FlowType; label: string }[] = [
    { value: 'income', label: 'Entrata' },
    { value: 'expense', label: 'Uscita' },
    { value: 'transfer', label: 'Giroconto' },
];

interface Props {
    open: boolean;
    onClose: () => void;
    transactions: Transaction[];
    accounts: Account[];
    month: string;
    edits: Map<number, Edit>;
    effective: (t: Transaction) => Edit;
    onStage: (t: Transaction, patch: Partial<Edit>) => void;
    onDiscard: () => void;
    onSave: () => void;
    saving: boolean;
}

type ReviewFilter = 'pending' | 'reviewed' | 'all';

/**
 * The review surface, off the page and behind a button: correcting rows is
 * maintenance, not the reason the page exists. It opens on the rows not yet
 * reviewed, so a month is worked through once and stays done. The staged edits
 * live on the page, so closing the dialog keeps them — the opening button says
 * how many.
 */
export function TransactionsReviewDialog({
    open,
    onClose,
    transactions,
    accounts,
    month,
    edits,
    effective,
    onStage,
    onDiscard,
    onSave,
    saving,
}: Props) {
    const [accountFilter, setAccountFilter] = useState<'all' | number>('all');
    const [typeFilter, setTypeFilter] = useState<'all' | FlowType | 'excluded'>('all');
    const [reviewFilter, setReviewFilter] = useState<ReviewFilter>('pending');
    const [expanded, setExpanded] = useState<Set<number>>(new Set());

    const accountLabel = useMemo(
        () => Object.fromEntries(accounts.map((a) => [a.id, a.label])),
        [accounts],
    );

    function toggleExpanded(id: number) {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    // Filter on the effective values so a row keeps matching after an unsaved
    // change instead of vanishing from under the cursor.
    const filtered = useMemo(
        () =>
            transactions.filter((t) => {
                if (reviewFilter === 'pending' && t.reviewed) return false;
                if (reviewFilter === 'reviewed' && !t.reviewed) return false;
                if (accountFilter !== 'all' && t.account_id !== accountFilter) return false;
                if (typeFilter === 'all') return true;
                const eff = effective(t);
                if (typeFilter === 'excluded') return eff.excluded;
                return eff.flow_type === typeFilter;
            }),
        [transactions, reviewFilter, accountFilter, typeFilter, effective],
    );

    // Counted over the whole month, not over `filtered`: saving marks every
    // pending row, including the ones the active filters are hiding, so the
    // button has to promise exactly that.
    const pendingCount = useMemo(
        () => transactions.filter((t) => !t.reviewed).length,
        [transactions],
    );

    const byDay = useMemo(() => {
        const groups: Record<string, Transaction[]> = {};
        for (const t of filtered) (groups[t.date] ??= []).push(t);
        return Object.entries(groups).sort((a, b) => (a[0] < b[0] ? 1 : -1));
    }, [filtered]);

    return (
        <Dialog open={open} onOpenChange={(next) => !next && onClose()}>
            {/* flex, not the default grid, and overflow-hidden: the rows own the
                scrolling so the filters and the save bar stay put. */}
            <DialogContent className="sm:max-w-4xl flex flex-col max-h-[85vh] overflow-hidden p-0 gap-0">
                {/* DialogHeader carries sticky -top-6/-mx-6/-mt-6 offsets tuned
                    for DialogContent's default p-6. This dialog uses p-0 so the
                    rows can scroll edge to edge, which left those negatives
                    pulling the header outside the panel — hence mx-0/mt-0/top-0.
                    pr-12 keeps the title clear of the close button. */}
                <DialogHeader className="static mx-0 mt-0 px-4 pt-4 pb-3 pr-12 shrink-0 text-left">
                    <DialogTitle>Transazioni — {formatMonthLong(month)}</DialogTitle>
                    <DialogDescription>
                        Correggi il tipo o escludi le voci straordinarie. Le tue scelte non vengono
                        sovrascritte agli aggiornamenti.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex flex-wrap items-center gap-2 px-4 pb-3 shrink-0">
                    <SegmentedToggle
                        size="xs"
                        value={reviewFilter}
                        onChange={(v) => setReviewFilter(v as ReviewFilter)}
                        options={[
                            { value: 'pending', label: 'Da rivedere' },
                            { value: 'reviewed', label: 'Riviste' },
                            { value: 'all', label: 'Tutte' },
                        ]}
                    />
                    <SegmentedToggle
                        size="xs"
                        value={accountFilter === 'all' ? 'all' : String(accountFilter)}
                        onChange={(v) => setAccountFilter(v === 'all' ? 'all' : Number(v))}
                        options={[
                            { value: 'all', label: 'Tutti i conti' },
                            ...accounts.map((a) => ({ value: String(a.id), label: a.label })),
                        ]}
                    />
                    <SegmentedToggle
                        size="xs"
                        value={typeFilter}
                        onChange={(v) => setTypeFilter(v as typeof typeFilter)}
                        options={[
                            { value: 'all', label: 'Tutti' },
                            { value: 'income', label: 'Entrate' },
                            { value: 'expense', label: 'Uscite' },
                            { value: 'transfer', label: 'Giroconti' },
                            { value: 'excluded', label: 'Escluse' },
                        ]}
                    />
                </div>

                {byDay.length === 0 ? (
                    <div className="flex-1 flex flex-col items-center justify-center gap-2 py-12 text-center text-sm border-t border-border">
                        {/* "Nothing left to do" and "nothing here" read the same
                            as an empty list but mean opposite things. */}
                        {reviewFilter === 'pending' && pendingCount === 0 && transactions.length > 0 ? (
                            <>
                                <p className="font-medium text-green-500">Tutte riviste ✓</p>
                                <p className="text-muted-foreground">
                                    Nessuna nuova transazione in {formatMonthLong(month)}.
                                </p>
                                <Button variant="outline" size="sm" onClick={() => setReviewFilter('reviewed')}>
                                    Mostra le {transactions.length} riviste
                                </Button>
                            </>
                        ) : (
                            <p className="text-muted-foreground">
                                Nessuna transazione in {formatMonthLong(month)} per questo filtro.
                            </p>
                        )}
                    </div>
                ) : (
                    <div className="flex-1 min-h-0 overflow-y-auto border-t border-border">
                        {byDay.map(([date, items]) => (
                            <div key={date}>
                                <h3 className="sticky top-0 z-10 bg-background px-4 py-1.5 text-xs font-semibold text-muted-foreground uppercase tracking-wide border-b border-border">
                                    {formatDateLong(date)}
                                </h3>
                                <div className="divide-y divide-border">
                                    {items.map((t) => {
                                        const eff = effective(t);
                                        const dirty = edits.has(t.id);
                                        return (
                                        <div
                                            key={t.id}
                                            className={cn(
                                                'flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-3 p-3',
                                                (eff.excluded || eff.flow_type === 'transfer') && 'opacity-60',
                                                dirty && 'bg-primary/5 border-l-2 border-primary',
                                            )}
                                        >
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-start gap-2">
                                                    <button
                                                        type="button"
                                                        onClick={() => toggleExpanded(t.id)}
                                                        title={expanded.has(t.id) ? 'Comprimi' : 'Mostra tutta la descrizione'}
                                                        className={cn(
                                                            'text-sm text-left hover:text-foreground min-w-0',
                                                            expanded.has(t.id) ? 'whitespace-pre-wrap wrap-break-word' : 'truncate',
                                                        )}
                                                    >
                                                        {t.description || '—'}
                                                    </button>
                                                    {t.is_salary && (
                                                        <Badge className="shrink-0 text-[10px] mt-0.5 gap-1 bg-green-600 hover:bg-green-600">
                                                            <Wallet className="w-2.5 h-2.5" />
                                                            stipendio
                                                        </Badge>
                                                    )}
                                                    {t.is_manual && !dirty && (
                                                        <Badge variant="outline" className="shrink-0 text-[10px] mt-0.5">
                                                            manuale
                                                        </Badge>
                                                    )}
                                                    {dirty && (
                                                        <Badge className="shrink-0 text-[10px] mt-0.5">
                                                            modificata
                                                        </Badge>
                                                    )}
                                                </div>
                                                <span className="text-xs text-muted-foreground">{accountLabel[t.account_id] ?? '—'}</span>
                                            </div>

                                            <span
                                                className={cn(
                                                    'font-mono text-sm font-semibold tabular-nums shrink-0 sm:w-28 sm:text-right',
                                                    t.amount < 0 ? 'text-red-500' : 'text-green-500',
                                                )}
                                            >
                                                <Money value={t.amount} />
                                            </span>

                                            <div className="flex items-center gap-2 shrink-0">
                                                <SegmentedToggle
                                                    size="xs"
                                                    value={eff.flow_type}
                                                    onChange={(v) => onStage(t, { flow_type: v })}
                                                    options={FLOW_OPTIONS}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => onStage(t, { excluded: !eff.excluded })}
                                                    title={eff.excluded ? 'Inclusa nei totali' : 'Escludi dai totali'}
                                                    className={cn(
                                                        'inline-flex items-center gap-1 rounded-lg border px-2 py-1.5 text-xs',
                                                        eff.excluded
                                                            ? 'border-amber-500/50 bg-amber-500/10 text-amber-600 dark:text-amber-400'
                                                            : 'border-border text-muted-foreground hover:text-foreground',
                                                    )}
                                                >
                                                    <EyeOff className="w-3.5 h-3.5" />
                                                    {eff.excluded ? 'Esclusa' : 'Escludi'}
                                                </button>
                                            </div>
                                        </div>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3 p-4 border-t border-border shrink-0">
                    <span className="text-sm text-muted-foreground">
                        {edits.size > 0
                            ? `${edits.size} ${edits.size === 1 ? 'modifica non salvata' : 'modifiche non salvate'}`
                            : `${filtered.length} ${filtered.length === 1 ? 'transazione' : 'transazioni'}`}
                    </span>
                    <div className="flex items-center gap-2">
                        {edits.size > 0 && (
                            <Button variant="ghost" size="sm" onClick={onDiscard} disabled={saving}>
                                Annulla
                            </Button>
                        )}
                        {/* Saving with nothing changed is now the main action —
                            agreeing with the classifier is still a review. The
                            label spells out how many rows it will mark, because
                            it covers the ones the filters are hiding too. */}
                        <Button
                            size="sm"
                            onClick={onSave}
                            disabled={saving || (edits.size === 0 && pendingCount === 0)}
                        >
                            {saving
                                ? 'Salvataggio…'
                                : pendingCount > 0
                                    ? `Salva e segna ${pendingCount} come ${pendingCount === 1 ? 'rivista' : 'riviste'}`
                                    : 'Salva modifiche'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
