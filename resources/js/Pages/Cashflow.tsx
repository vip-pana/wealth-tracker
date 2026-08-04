import { useCallback, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { SegmentedToggle } from '@/Components/ui/SegmentedToggle';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Money } from '@/Components/ui/Money';
import PositionsCard from '@/Components/Data/PositionsCard';
import { currentMonth, formatDateLong, formatMonthLong, stepMonth } from '@/lib/formatters';
import { bankFreshness } from '@/lib/metrics';
import { cn } from '@/lib/utils';
import { ArrowLeftRight, ChevronLeft, ChevronRight, EyeOff, Wallet, Info, Shield, RefreshCw } from 'lucide-react';
import type { PositionReturns } from '@/types/analytics';

type FlowType = 'income' | 'expense' | 'transfer';

interface Account {
    id: number;
    label: string;
    last_sync_at: string | null;
    last_sync_status: string | null;
    last_sync_error: string | null;
}

interface Transaction {
    id: number;
    account_id: number;
    date: string;
    amount: number;
    description: string;
    flow_type: FlowType | null;
    excluded: boolean;
    is_manual: boolean;
    is_salary: boolean;
}

interface Props {
    accounts: Account[];
    transactions: Transaction[];
    month: string;
    availableMonths: string[];
    monthlySalary: number | null;
    emergencyFund: { buffer: number; targetMonths: number | null; monthlyExpense: number | null };
    // Whole-history, not scoped to the selected month.
    positionReturns: PositionReturns | null;
}

const FLOW_OPTIONS: { value: FlowType; label: string }[] = [
    { value: 'income', label: 'Entrata' },
    { value: 'expense', label: 'Uscita' },
    { value: 'transfer', label: 'Giroconto' },
];

function StatCard({ label, value, tone, hint, help }: { label: string; value: number; tone?: string; hint?: string; help?: string }) {
    return (
        <Card>
            <CardContent className="p-4">
                <div className="flex items-center gap-1 mb-1">
                    <p className="text-xs text-muted-foreground">{label}</p>
                    {help && (
                        <span
                            tabIndex={0}
                            title={help}
                            className="inline-flex cursor-help text-muted-foreground/70 hover:text-foreground"
                            aria-label={help}
                        >
                            <Info className="w-3 h-3" />
                        </span>
                    )}
                </div>
                <p className={cn('text-xl font-bold', tone)}>
                    <Money value={value} />
                </p>
                {hint && <p className="text-[11px] text-muted-foreground mt-0.5">{hint}</p>}
            </CardContent>
        </Card>
    );
}

type Edit = { flow_type: FlowType; excluded: boolean };

export default function Cashflow({ accounts, transactions, month, availableMonths, monthlySalary, emergencyFund, positionReturns }: Props) {
    const [accountFilter, setAccountFilter] = useState<'all' | number>('all');
    const [typeFilter, setTypeFilter] = useState<'all' | FlowType | 'excluded'>('all');
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    // Local, unsaved edits keyed by transaction id. The list re-renders from
    // these without a round-trip, so filters and scroll survive until "Salva".
    const [edits, setEdits] = useState<Map<number, Edit>>(new Map());
    const [saving, setSaving] = useState(false);
    const [targetMonths, setTargetMonths] = useState(emergencyFund.targetMonths ?? 6);
    const [savingTarget, setSavingTarget] = useState(false);
    const [syncing, setSyncing] = useState(false);
    const [loadingMonth, setLoadingMonth] = useState(false);

    // Pull the same import the scheduler runs at 06:05. It is idempotent, so an
    // on-demand run between scheduled ones can only add rows.
    function syncTransactions() {
        setSyncing(true);
        router.post('/cashflow/sync', {}, { preserveScroll: true, onFinish: () => setSyncing(false) });
    }

    // Only the month's rows are refetched: preserveState keeps the filters, the
    // expanded rows and any staged edit alive across a month change, and the
    // whole-history figures don't depend on the month anyway.
    function handleMonthChange(newMonth: string) {
        if (newMonth === month || newMonth > currentMonth()) return;
        setLoadingMonth(true);
        router.get(
            '/cashflow',
            { month: newMonth },
            {
                only: ['transactions', 'month'],
                preserveState: true,
                preserveScroll: true,
                onFinish: () => setLoadingMonth(false),
            },
        );
    }

    // There is nothing to show past the current month: transactions can only
    // exist up to today. The current month is always offered even before its
    // first transaction lands, so you can always get back to it.
    const atCurrentMonth = month >= currentMonth();
    const selectableMonths = useMemo(() => {
        const now = currentMonth();
        const past = availableMonths.filter((m) => m <= now);
        return past.includes(now) ? past : [now, ...past];
    }, [availableMonths]);

    function navigateMonth(direction: 'prev' | 'next') {
        handleMonthChange(stepMonth(month, direction));
    }

    function toggleExpanded(id: number) {
        setExpanded((prev) => {
            const next = new Set(prev);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return next;
        });
    }

    // The effective (possibly edited) value shown for a row.
    const effective = useCallback(
        (t: Transaction): Edit => {
            const edit = edits.get(t.id);
            return {
                flow_type: edit?.flow_type ?? t.flow_type ?? 'expense',
                excluded: edit?.excluded ?? t.excluded,
            };
        },
        [edits],
    );

    function stage(t: Transaction, patch: Partial<Edit>) {
        setEdits((prev) => {
            const next = new Map(prev);
            const current = effective(t);
            const merged: Edit = { ...current, ...patch };
            // Drop the edit entirely if it matches the saved row again.
            if (merged.flow_type === (t.flow_type ?? 'expense') && merged.excluded === t.excluded) {
                next.delete(t.id);
            } else {
                next.set(t.id, merged);
            }
            return next;
        });
    }

    function saveAll() {
        if (edits.size === 0) return;
        const changes = Array.from(edits.entries()).map(([id, e]) => ({ id, ...e }));
        setSaving(true);
        router.patch(
            '/cashflow',
            { changes },
            {
                preserveScroll: true,
                onSuccess: () => setEdits(new Map()),
                onFinish: () => setSaving(false),
            },
        );
    }

    const accountLabel = useMemo(
        () => Object.fromEntries(accounts.map((a) => [a.id, a.label])),
        [accounts],
    );

    // Rows of the shown month in the active account, regardless of the type
    // filter — the summary cards read from this set, so they follow the account
    // but not the type filter (which only narrows the list below).
    const inRange = useMemo(
        () =>
            transactions.filter((t) => accountFilter === 'all' || t.account_id === accountFilter),
        [transactions, accountFilter],
    );

    // Month totals. Transfers are internal by nature and excluded rows are the
    // user's opt-outs, so neither counts.
    const summary = useMemo(() => {
        let income = 0;
        let expense = 0;
        for (const t of inRange) {
            const eff = effective(t);
            if (eff.excluded || eff.flow_type === 'transfer') continue;
            if (eff.flow_type === 'income') income += t.amount;
            else expense += t.amount;
        }
        return { income, expense, net: income + expense };
    }, [inRange, effective]);

    // Emergency-fund coverage: how many months of expenses the buffer covers,
    // and progress toward the configured target. The burn rate is the
    // whole-history average from the server, already a positive magnitude.
    const monthlyBurn = emergencyFund.monthlyExpense ?? 0;
    const monthsCovered = monthlyBurn > 0 ? emergencyFund.buffer / monthlyBurn : 0;
    const targetAmount = monthlyBurn * targetMonths;
    const coveragePct = targetAmount > 0 ? Math.min(100, (emergencyFund.buffer / targetAmount) * 100) : 0;
    const shortfall = Math.max(0, targetAmount - emergencyFund.buffer);
    const targetDirty = targetMonths !== (emergencyFund.targetMonths ?? 6);

    function saveTarget() {
        setSavingTarget(true);
        router.patch(
            '/cashflow/emergency-fund',
            { target_months: targetMonths },
            { preserveScroll: true, preserveState: true, onFinish: () => setSavingTarget(false) },
        );
    }

    // The list narrows the in-range rows by the type filter. Filter on the
    // effective values so a row keeps matching even after an unsaved change.
    const filtered = useMemo(
        () =>
            inRange.filter((t) => {
                if (typeFilter === 'all') return true;
                const eff = effective(t);
                if (typeFilter === 'excluded') return eff.excluded;
                return eff.flow_type === typeFilter;
            }),
        [inRange, typeFilter, effective],
    );

    const byDay = useMemo(() => {
        const groups: Record<string, Transaction[]> = {};
        for (const t of filtered) (groups[t.date] ??= []).push(t);
        return Object.entries(groups).sort((a, b) => (a[0] < b[0] ? 1 : -1));
    }, [filtered]);

    return (
        <>
            <Head title="Entrate e Uscite" />
            <div className="p-4 pb-24 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={ArrowLeftRight}
                    title="Entrate e Uscite"
                    subtitle="Rivedi le transazioni bancarie: correggi il tipo o escludi le voci straordinarie. Le tue scelte non vengono sovrascritte agli aggiornamenti."
                    actions={
                        <Button size="sm" variant="outline" onClick={syncTransactions} disabled={syncing || accounts.length === 0}>
                            <RefreshCw className={cn('w-4 h-4 mr-1', syncing && 'animate-spin')} />
                            {syncing ? 'Sincronizzo…' : 'Sincronizza'}
                        </Button>
                    }
                />

                {accounts.length === 0 ? (
                    <Card>
                        <CardContent className="py-3 px-4 text-sm text-muted-foreground">
                            Nessun conto bancario collegato. Collegane uno dalle Impostazioni per importare le transazioni.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-muted-foreground px-1">
                        {accounts.map((a) => {
                            const freshness = bankFreshness(a.last_sync_at);
                            const failed = a.last_sync_status === 'failed';
                            return (
                                <span key={a.id} className="inline-flex items-center gap-1.5">
                                    <span className="font-medium text-foreground/80">{a.label}</span>
                                    <span
                                        className={cn(failed ? 'text-destructive' : freshness.stale && 'text-amber-500')}
                                        title={a.last_sync_error ?? undefined}
                                    >
                                        {failed ? (a.last_sync_error ?? 'sincronizzazione non riuscita') : freshness.label}
                                    </span>
                                </span>
                            );
                        })}
                    </div>
                )}

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <StatCard
                        label="Stipendio medio / mese"
                        value={monthlySalary ?? 0}
                        tone="text-green-500"
                        hint={monthlySalary === null ? 'Nessuno stipendio rilevato' : 'Su tutto lo storico'}
                        help="Somma degli accrediti riconosciuti come stipendio su tutto lo storico, divisa per il numero di mesi in cui è arrivato uno stipendio (non per tutti i mesi). Così un mese senza stipendio, o il mese corrente non ancora accreditato, non abbassa la media. Non dipende dal mese mostrato."
                    />
                    <StatCard
                        label="Spese medie / mese"
                        value={-monthlyBurn}
                        tone="text-red-500"
                        hint={emergencyFund.monthlyExpense === null ? 'Nessuna uscita rilevata' : 'Su tutto lo storico'}
                        help="Totale delle uscite su tutto lo storico (esclusi i giroconti interni e le voci che hai marcato «escludi»), diviso per i mesi coperti. Non dipende dal mese mostrato."
                    />
                    <StatCard label="Uscite del mese" value={summary.expense} tone="text-red-500" />
                    <StatCard label="Netto del mese" value={summary.net} tone={summary.net >= 0 ? 'text-green-500' : 'text-red-500'} />
                </div>

                {/* Emergency fund */}
                <Card>
                    <CardContent className="p-4 space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-1.5">
                                <Shield className="w-4 h-4 text-primary" />
                                <h2 className="text-sm font-semibold">Fondo di emergenza</h2>
                                <span
                                    tabIndex={0}
                                    className="inline-flex cursor-help text-muted-foreground/70 hover:text-foreground"
                                    title="Il fondo è il valore delle categorie non-investibili (liquidità parcheggiata). I mesi coperti = fondo ÷ spesa media mensile calcolata su TUTTO lo storico bancario, così la copertura non cambia al cambio del mese mostrato."
                                    aria-label="Come funziona il fondo di emergenza"
                                >
                                    <Info className="w-3 h-3" />
                                </span>
                            </div>
                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                Obiettivo
                                <input
                                    type="number"
                                    min={1}
                                    max={60}
                                    value={targetMonths}
                                    onChange={(e) => setTargetMonths(Math.max(1, Math.min(60, Number(e.target.value) || 1)))}
                                    className="w-14 rounded-lg border border-border bg-background px-2 py-1 text-xs text-foreground"
                                />
                                mesi
                                {targetDirty && (
                                    <Button size="sm" onClick={saveTarget} disabled={savingTarget}>
                                        {savingTarget ? '…' : 'Salva'}
                                    </Button>
                                )}
                            </label>
                        </div>

                        {monthlyBurn === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                Servono transazioni di spesa per stimare la copertura del fondo.
                            </p>
                        ) : (
                            <>
                                <div className="grid grid-cols-2 sm:grid-cols-4 gap-4">
                                    <div>
                                        <p className="text-xs text-muted-foreground mb-0.5">Fondo attuale</p>
                                        <p className="text-lg font-bold"><Money value={emergencyFund.buffer} /></p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground mb-0.5">Mesi coperti</p>
                                        <p className={cn('text-lg font-bold', monthsCovered >= targetMonths ? 'text-green-500' : 'text-amber-500')}>
                                            {monthsCovered.toFixed(1)}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground mb-0.5">Obiettivo ({targetMonths} mesi)</p>
                                        <p className="text-lg font-bold"><Money value={targetAmount} /></p>
                                    </div>
                                    <div>
                                        <p className="text-xs text-muted-foreground mb-0.5">{shortfall > 0 ? 'Manca' : 'Raggiunto'}</p>
                                        <p className={cn('text-lg font-bold', shortfall > 0 ? 'text-amber-500' : 'text-green-500')}>
                                            {shortfall > 0 ? <Money value={shortfall} /> : '✓'}
                                        </p>
                                    </div>
                                </div>

                                <div className="h-2 w-full rounded-full bg-muted overflow-hidden">
                                    <div
                                        className={cn('h-full rounded-full transition-all', coveragePct >= 100 ? 'bg-green-500' : 'bg-amber-500')}
                                        style={{ width: `${coveragePct}%` }}
                                    />
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* Filters */}
                <div className="flex flex-wrap items-center gap-2 text-sm">
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
                    <div className="flex items-center gap-1 ml-auto">
                        {loadingMonth && <RefreshCw className="w-3.5 h-3.5 mr-1 animate-spin text-muted-foreground" />}
                        <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => navigateMonth('prev')} disabled={loadingMonth}>
                            <ChevronLeft className="w-4 h-4" />
                        </Button>
                        <Select value={month} onValueChange={handleMonthChange} disabled={loadingMonth}>
                            <SelectTrigger className="h-7 w-36">
                                <SelectValue>{formatMonthLong(month)}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                {selectableMonths.map((m) => (
                                    <SelectItem key={m} value={m}>
                                        {formatMonthLong(m)}
                                    </SelectItem>
                                ))}
                                {!selectableMonths.includes(month) && (
                                    <SelectItem value={month}>{formatMonthLong(month)}</SelectItem>
                                )}
                            </SelectContent>
                        </Select>
                        <Button
                            variant="outline"
                            size="icon"
                            className="h-7 w-7"
                            onClick={() => navigateMonth('next')}
                            disabled={loadingMonth || atCurrentMonth}
                            title={atCurrentMonth ? 'Il mese corrente è il più recente disponibile' : undefined}
                        >
                            <ChevronRight className="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                {byDay.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground text-sm">
                            Nessuna transazione in {formatMonthLong(month)} per questo filtro.
                        </CardContent>
                    </Card>
                ) : (
                    <div className="space-y-4">
                        {byDay.map(([date, items]) => (
                            <div key={date}>
                                <h3 className="text-xs font-semibold text-muted-foreground uppercase tracking-wide mb-1.5 px-1">
                                    {formatDateLong(date)}
                                </h3>
                                <Card>
                                    <CardContent className="p-0 divide-y divide-border">
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
                                                        onChange={(v) => stage(t, { flow_type: v })}
                                                        options={FLOW_OPTIONS}
                                                    />
                                                    <button
                                                        type="button"
                                                        onClick={() => stage(t, { excluded: !eff.excluded })}
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
                                    </CardContent>
                                </Card>
                            </div>
                        ))}
                    </div>
                )}

                <PositionsCard returns={positionReturns} />
            </div>

            {edits.size > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 backdrop-blur-sm supports-backdrop-filter:bg-background/80">
                    <div className="max-w-[1400px] mx-auto w-full flex items-center justify-between gap-3 p-3">
                        <span className="text-sm text-muted-foreground">
                            {edits.size} {edits.size === 1 ? 'modifica non salvata' : 'modifiche non salvate'}
                        </span>
                        <div className="flex items-center gap-2">
                            <Button variant="ghost" size="sm" onClick={() => setEdits(new Map())} disabled={saving}>
                                Annulla
                            </Button>
                            <Button size="sm" onClick={saveAll} disabled={saving}>
                                {saving ? 'Salvataggio…' : 'Salva modifiche'}
                            </Button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}

Cashflow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
