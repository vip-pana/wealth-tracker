import { useCallback, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { SegmentedToggle } from '@/Components/ui/SegmentedToggle';
import { Money } from '@/Components/ui/Money';
import { formatDateLong } from '@/lib/formatters';
import { cn } from '@/lib/utils';
import { ArrowLeftRight, EyeOff, Wallet, Info, Shield } from 'lucide-react';

type FlowType = 'income' | 'expense' | 'transfer';

interface Account {
    id: number;
    label: string;
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
    emergencyFund: { buffer: number; targetMonths: number | null };
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

// Count of calendar months spanned by a date range, min 1 — so a partial month
// still divides by 1, not 0.
function monthsBetween(from: string, to: string): number {
    const [fy, fm] = from.split('-').map(Number);
    const [ty, tm] = to.split('-').map(Number);
    return Math.max(1, (ty - fy) * 12 + (tm - fm) + 1);
}

type Edit = { flow_type: FlowType; excluded: boolean };

export default function Cashflow({ accounts, transactions, emergencyFund }: Props) {
    const [accountFilter, setAccountFilter] = useState<'all' | number>('all');
    const [typeFilter, setTypeFilter] = useState<'all' | FlowType | 'excluded'>('all');
    const [dateFrom, setDateFrom] = useState('');
    const [dateTo, setDateTo] = useState('');
    const [expanded, setExpanded] = useState<Set<number>>(new Set());
    // Local, unsaved edits keyed by transaction id. The list re-renders from
    // these without a round-trip, so filters and scroll survive until "Salva".
    const [edits, setEdits] = useState<Map<number, Edit>>(new Map());
    const [saving, setSaving] = useState(false);
    const [targetMonths, setTargetMonths] = useState(emergencyFund.targetMonths ?? 6);
    const [savingTarget, setSavingTarget] = useState(false);

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

    // Rows in the active account + date range, regardless of the type filter.
    // Both the summary cards and the monthly averages read from this set, so
    // they reflect the date filter but not the type filter (which only narrows
    // the list below).
    const inRange = useMemo(
        () =>
            transactions.filter((t) => {
                if (accountFilter !== 'all' && t.account_id !== accountFilter) return false;
                if (dateFrom && t.date < dateFrom) return false;
                if (dateTo && t.date > dateTo) return false;
                return true;
            }),
        [transactions, accountFilter, dateFrom, dateTo],
    );

    // Summary + monthly averages over the in-range rows. Transfers are internal
    // by nature and excluded rows are the user's opt-outs, so neither counts.
    const summary = useMemo(() => {
        let income = 0;
        let expense = 0;
        let salary = 0;
        let minDate = '';
        let maxDate = '';
        // Months (YYYY-MM) that actually received a salary — the salary average
        // divides by these, not by the whole range, so a month with no salary
        // (or a partial current month) doesn't drag the average down.
        const salaryMonths = new Set<string>();
        for (const t of inRange) {
            if (t.date < minDate || minDate === '') minDate = t.date;
            if (t.date > maxDate) maxDate = t.date;
            const eff = effective(t);
            if (eff.excluded || eff.flow_type === 'transfer') continue;
            if (eff.flow_type === 'income') {
                income += t.amount;
                if (t.is_salary) {
                    salary += t.amount;
                    salaryMonths.add(t.date.slice(0, 7));
                }
            } else {
                expense += t.amount;
            }
        }
        const months = minDate ? monthsBetween(dateFrom || minDate, dateTo || maxDate) : 1;
        return {
            income,
            expense,
            net: income + expense,
            monthlyExpense: expense / months,
            monthlySalary: salaryMonths.size > 0 ? salary / salaryMonths.size : 0,
            salaryMonths: salaryMonths.size,
            months,
        };
    }, [inRange, effective, dateFrom, dateTo]);

    // Average monthly expense over ALL history (every account, ignoring the
    // date filter), so the emergency-fund coverage is a stable figure that
    // doesn't shift as you filter the list. Uses effective values so unsaved
    // edits still count.
    const wholeHistoryMonthlyExpense = useMemo(() => {
        let expense = 0;
        let minDate = '';
        let maxDate = '';
        for (const t of transactions) {
            if (t.date < minDate || minDate === '') minDate = t.date;
            if (t.date > maxDate) maxDate = t.date;
            const eff = effective(t);
            if (eff.excluded || eff.flow_type !== 'expense') continue;
            expense += t.amount;
        }
        const months = minDate ? monthsBetween(minDate, maxDate) : 1;
        return expense / months;
    }, [transactions, effective]);

    // Emergency-fund coverage: how many months of expenses the buffer covers,
    // and progress toward the configured target. monthlyExpense is negative
    // (outflows), so take its magnitude.
    const monthlyBurn = Math.abs(wholeHistoryMonthlyExpense);
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
            <div className="p-4 pb-24 space-y-4 max-w-[1100px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={ArrowLeftRight}
                    title="Entrate e Uscite"
                    subtitle="Rivedi le transazioni bancarie: correggi il tipo o escludi le voci straordinarie. Le tue scelte non vengono sovrascritte agli aggiornamenti."
                />

                <div className="grid grid-cols-2 lg:grid-cols-4 gap-3">
                    <StatCard
                        label="Stipendio medio / mese"
                        value={summary.monthlySalary}
                        tone="text-green-500"
                        hint={summary.salaryMonths > 0 ? `Media su ${summary.salaryMonths} ${summary.salaryMonths === 1 ? 'mese' : 'mesi'} con stipendio` : 'Nessuno stipendio nel periodo'}
                        help="Somma degli accrediti riconosciuti come stipendio, divisa per il numero di mesi in cui è arrivato uno stipendio (non per tutti i mesi del periodo). Così un mese senza stipendio, o il mese corrente non ancora accreditato, non abbassa la media."
                    />
                    <StatCard
                        label="Spese medie / mese"
                        value={summary.monthlyExpense}
                        tone="text-red-500"
                        hint={`Su ${summary.months} ${summary.months === 1 ? 'mese' : 'mesi'}`}
                        help="Totale delle uscite del periodo (esclusi i giroconti interni e le voci che hai marcato «escludi»), diviso per i mesi coperti dal periodo."
                    />
                    <StatCard label="Uscite (periodo)" value={summary.expense} tone="text-red-500" />
                    <StatCard label="Netto (periodo)" value={summary.net} tone={summary.net >= 0 ? 'text-green-500' : 'text-red-500'} />
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
                                    title="Il fondo è il valore delle categorie non-investibili (liquidità parcheggiata). I mesi coperti = fondo ÷ spesa media mensile calcolata su TUTTO lo storico bancario (non sul filtro data), così la copertura è un numero stabile."
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
                    <label className="flex items-center gap-1.5 text-muted-foreground">
                        <span className="text-xs">Da</span>
                        <input
                            type="date"
                            value={dateFrom}
                            max={dateTo || undefined}
                            onChange={(e) => setDateFrom(e.target.value)}
                            className="rounded-lg border border-border bg-background px-2 py-1 text-xs text-foreground"
                        />
                    </label>
                    <label className="flex items-center gap-1.5 text-muted-foreground">
                        <span className="text-xs">A</span>
                        <input
                            type="date"
                            value={dateTo}
                            min={dateFrom || undefined}
                            onChange={(e) => setDateTo(e.target.value)}
                            className="rounded-lg border border-border bg-background px-2 py-1 text-xs text-foreground"
                        />
                    </label>
                    {(dateFrom || dateTo) && (
                        <button
                            type="button"
                            onClick={() => {
                                setDateFrom('');
                                setDateTo('');
                            }}
                            className="text-xs text-muted-foreground hover:text-foreground underline"
                        >
                            Azzera date
                        </button>
                    )}
                </div>

                {byDay.length === 0 ? (
                    <Card>
                        <CardContent className="py-12 text-center text-muted-foreground text-sm">
                            Nessuna transazione per questo filtro.
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
                                                                expanded.has(t.id) ? 'whitespace-pre-wrap break-words' : 'truncate',
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
            </div>

            {edits.size > 0 && (
                <div className="fixed inset-x-0 bottom-0 z-40 border-t border-border bg-background/95 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                    <div className="max-w-[1100px] mx-auto w-full flex items-center justify-between gap-3 p-3">
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
