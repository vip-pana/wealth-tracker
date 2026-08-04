import { useCallback, useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/Components/ui/select';
import { Money } from '@/Components/ui/Money';
import MonthlyFlowChart from '@/Components/Charts/MonthlyFlowChart';
import { TransactionsReviewDialog } from '@/Components/Cashflow/TransactionsReviewDialog';
import { currentMonth, formatMonthLong, stepMonth } from '@/lib/formatters';
import { bankFreshness } from '@/lib/metrics';
import { cn } from '@/lib/utils';
import { ArrowLeftRight, ChevronLeft, ChevronRight, Info, Shield, RefreshCw, ListChecks } from 'lucide-react';
import type { MonthlyFlowPoint } from '@/types/analytics';
import type { Account, Edit, Transaction } from '@/types/cashflow';

interface Props {
    accounts: Account[];
    transactions: Transaction[];
    // How many of the shown month's rows are still unreviewed.
    pendingReview: number;
    month: string;
    availableMonths: string[];
    monthlySalary: number | null;
    emergencyFund: { buffer: number; targetMonths: number | null; monthlyExpense: number | null };
    // Whole history, so the shown month can be read against the others.
    monthlyFlow: MonthlyFlowPoint[];
}

// A help affordance for the figures whose derivation isn't obvious from the
// label alone (which average, over what period).
function Help({ text }: { text: string }) {
    return (
        <span
            tabIndex={0}
            title={text}
            className="inline-flex shrink-0 cursor-help text-muted-foreground/70 hover:text-foreground"
            aria-label={text}
        >
            <Info className="w-3 h-3" />
        </span>
    );
}

// One of the three whole-history figures the page exists to show.
function MetricCard({ label, value, tone, hint, help }: { label: string; value: number; tone?: string; hint?: string; help?: string }) {
    return (
        <Card>
            <CardContent className="p-3">
                <p className="flex items-center gap-1 text-xs text-muted-foreground">
                    <span className="truncate">{label}</span>
                    {help && <Help text={help} />}
                </p>
                <p className={cn('text-xl font-bold tabular-nums', tone)}>
                    <Money value={value} />
                </p>
                {hint && <p className="text-[11px] text-muted-foreground mt-0.5">{hint}</p>}
            </CardContent>
        </Card>
    );
}

export default function Cashflow({ accounts, transactions, pendingReview, month, availableMonths, monthlySalary, emergencyFund, monthlyFlow }: Props) {
    // Local, unsaved edits keyed by transaction id. They live here rather than
    // in the dialog so closing it doesn't throw the work away.
    const [edits, setEdits] = useState<Map<number, Edit>>(new Map());
    const [saving, setSaving] = useState(false);
    const [reviewOpen, setReviewOpen] = useState(false);
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

    // Only the month's rows are refetched: preserveState keeps any staged edit
    // alive across a month change, and the whole-history figures don't depend on
    // the month anyway.
    function handleMonthChange(newMonth: string) {
        if (newMonth === month || newMonth > currentMonth()) return;
        setLoadingMonth(true);
        router.get(
            '/cashflow',
            { month: newMonth },
            {
                only: ['transactions', 'pendingReview', 'month'],
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

    const stage = useCallback((t: Transaction, patch: Partial<Edit>) => {
        setEdits((prev) => {
            const next = new Map(prev);
            const current: Edit = {
                flow_type: prev.get(t.id)?.flow_type ?? t.flow_type ?? 'expense',
                excluded: prev.get(t.id)?.excluded ?? t.excluded,
            };
            const merged: Edit = { ...current, ...patch };
            // Drop the edit entirely if it matches the saved row again.
            if (merged.flow_type === (t.flow_type ?? 'expense') && merged.excluded === t.excluded) {
                next.delete(t.id);
            } else {
                next.set(t.id, merged);
            }
            return next;
        });
    }, []);

    // Saving is both "apply my corrections" and "I have been through this
    // month": agreeing with the classifier changes nothing, so a review-only
    // save carries no changes at all. The month goes along and the server marks
    // its pending rows, so nothing an active filter was hiding is left behind.
    function saveAll() {
        if (edits.size === 0 && pendingReview === 0) return;
        const changes = Array.from(edits.entries()).map(([id, e]) => ({ id, ...e }));
        setSaving(true);
        router.patch(
            '/cashflow',
            { changes, month },
            {
                preserveScroll: true,
                // The rows' reviewed flag changed server-side, so they have to
                // come back rather than be patched locally.
                only: ['transactions', 'pendingReview', 'monthlyFlow', 'flash'],
                onSuccess: () => {
                    setEdits(new Map());
                    setReviewOpen(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    }

    // Month totals, computed here rather than server-side because they have to
    // reflect the staged edits. Transfers are internal by nature and excluded
    // rows are the user's opt-outs, so neither counts.
    const summary = useMemo(() => {
        let income = 0;
        let expense = 0;
        for (const t of transactions) {
            const eff = effective(t);
            if (eff.excluded || eff.flow_type === 'transfer') continue;
            if (eff.flow_type === 'income') income += t.amount;
            else expense += t.amount;
        }
        return { income, expense, net: income + expense };
    }, [transactions, effective]);

    // Emergency-fund coverage. The burn rate is the whole-history average from
    // the server, already a positive magnitude.
    const monthlyBurn = emergencyFund.monthlyExpense ?? 0;
    const monthsCovered = monthlyBurn > 0 ? emergencyFund.buffer / monthlyBurn : 0;
    const targetAmount = monthlyBurn * targetMonths;
    const coveragePct = targetAmount > 0 ? Math.min(100, (emergencyFund.buffer / targetAmount) * 100) : 0;
    const shortfall = Math.max(0, targetAmount - emergencyFund.buffer);
    const targetDirty = targetMonths !== (emergencyFund.targetMonths ?? 6);

    // What you put aside in an average month, from the two whole-history figures
    // shown above — stable, unlike a single month's net.
    const monthlySaving = (monthlySalary ?? 0) - monthlyBurn;
    // Only meaningful while you're still short and actually saving; a
    // non-positive rate never gets there, and the copy says so instead.
    const monthsToTarget = shortfall > 0 && monthlySaving > 0
        ? Math.ceil(shortfall / monthlySaving)
        : null;

    function saveTarget() {
        setSavingTarget(true);
        router.patch(
            '/cashflow/emergency-fund',
            { target_months: targetMonths },
            { preserveScroll: true, preserveState: true, onFinish: () => setSavingTarget(false) },
        );
    }

    return (
        <>
            <Head title="Entrate e Uscite" />
            <div className="flex flex-col shrink-0 p-4 gap-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={ArrowLeftRight}
                    title="Entrate e Uscite"
                    subtitle="Quanto entra, quanto esce e quanto resta — dai movimenti dei tuoi conti."
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

                {/* The three stable figures: what the page is for. */}
                <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                    <MetricCard
                        label="Stipendio medio / mese"
                        value={monthlySalary ?? 0}
                        tone="text-green-500"
                        hint={monthlySalary === null ? 'Nessuno stipendio rilevato' : 'Su tutto lo storico'}
                        help="Somma degli accrediti riconosciuti come stipendio su tutto lo storico, divisa per il numero di mesi in cui è arrivato uno stipendio (non per tutti i mesi). Così un mese senza stipendio, o il mese corrente non ancora accreditato, non abbassa la media."
                    />
                    <MetricCard
                        label="Spese medie / mese"
                        value={-monthlyBurn}
                        tone="text-red-500"
                        hint={emergencyFund.monthlyExpense === null ? 'Nessuna uscita rilevata' : 'Su tutto lo storico'}
                        help="Totale delle uscite su tutto lo storico (esclusi i giroconti interni e le voci che hai marcato «escludi»), diviso per i mesi coperti."
                    />
                    <MetricCard
                        label="Risparmio medio / mese"
                        value={monthlySaving}
                        tone={monthlySaving >= 0 ? 'text-green-500' : 'text-red-500'}
                        hint="Stipendio medio − spese medie"
                        help="Quanto resta in un mese medio. È la base della stima su quando raggiungerai il fondo di emergenza."
                    />
                </div>

                {/* The emergency fund: the page's headline. */}
                <Card className="border-primary/30">
                    <CardContent className="p-4 space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex items-center gap-1.5">
                                <Shield className="w-4 h-4 text-primary" />
                                <h2 className="text-sm font-semibold">Fondo di emergenza</h2>
                                <Help text="Il fondo è il valore delle categorie non-investibili (liquidità parcheggiata). I mesi coperti = fondo ÷ spesa media mensile calcolata su TUTTO lo storico bancario, così la copertura non cambia al cambio del mese mostrato." />
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
                                {/* Coverage in months is the number that answers
                                    "am I safe?", so it leads. */}
                                <div className="flex flex-wrap items-baseline gap-x-2">
                                    <span className={cn('text-3xl font-bold tabular-nums', monthsCovered >= targetMonths ? 'text-green-500' : 'text-amber-500')}>
                                        {monthsCovered.toFixed(1)}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        di {targetMonths} mesi coperti
                                    </span>
                                </div>

                                <div className="h-2 w-full rounded-full bg-muted overflow-hidden">
                                    <div
                                        className={cn('h-full rounded-full transition-all', coveragePct >= 100 ? 'bg-green-500' : 'bg-amber-500')}
                                        style={{ width: `${coveragePct}%` }}
                                    />
                                </div>

                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                                    <span className="font-semibold tabular-nums"><Money value={emergencyFund.buffer} /></span>
                                    <span className="text-muted-foreground">di</span>
                                    <span className="tabular-nums"><Money value={targetAmount} /></span>
                                    {shortfall > 0 ? (
                                        <span className="text-amber-500">
                                            — mancano <Money value={shortfall} />
                                        </span>
                                    ) : (
                                        <span className="text-green-500">— obiettivo raggiunto ✓</span>
                                    )}
                                </div>

                                {shortfall > 0 && (
                                    <p className="text-xs text-muted-foreground">
                                        {monthsToTarget !== null ? (
                                            <>
                                                Al ritmo attuale di <Money value={monthlySaving} /> al mese:{' '}
                                                <strong className="text-foreground">
                                                    ~{monthsToTarget} {monthsToTarget === 1 ? 'mese' : 'mesi'}
                                                </strong>
                                            </>
                                        ) : (
                                            'Al ritmo attuale non lo raggiungi: le spese medie superano lo stipendio medio.'
                                        )}
                                    </p>
                                )}
                            </>
                        )}
                    </CardContent>
                </Card>

                {/* Trend, so a month can be read as normal or not. */}
                <div className="h-64">
                    <MonthlyFlowChart
                        data={monthlyFlow}
                        note="Entrate meno uscite per mese, sui movimenti salvati. Giroconti ed escluse non contano."
                    />
                </div>

                {/* The shown month, and the way into the rows behind it. */}
                <Card>
                    <CardContent className="p-3 space-y-3">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <p className="text-xs font-medium text-muted-foreground">Il mese</p>
                            <div className="flex items-center gap-1">
                                {loadingMonth && <RefreshCw className="w-3.5 h-3.5 mr-1 animate-spin text-muted-foreground" />}
                                <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => navigateMonth('prev')} disabled={loadingMonth}>
                                    <ChevronLeft className="w-4 h-4" />
                                </Button>
                                <Select value={month} onValueChange={handleMonthChange} disabled={loadingMonth}>
                                    <SelectTrigger className="h-7 w-44 px-2 whitespace-nowrap">
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

                        <div className="flex flex-wrap items-end justify-between gap-4">
                            <div className="flex flex-wrap items-baseline gap-x-6 gap-y-2">
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Netto</p>
                                    <p className={cn('text-2xl font-bold tabular-nums', summary.net >= 0 ? 'text-green-500' : 'text-red-500')}>
                                        <Money value={summary.net} />
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Entrate</p>
                                    <p className="text-base font-semibold tabular-nums text-green-500">
                                        <Money value={summary.income} />
                                    </p>
                                </div>
                                <div>
                                    <p className="text-[11px] uppercase tracking-wide text-muted-foreground">Uscite</p>
                                    <p className="text-base font-semibold tabular-nums text-red-500">
                                        <Money value={summary.expense} />
                                    </p>
                                </div>
                            </div>

                            {/* Leads with what's left to do; once the month is
                                worked through it goes quiet and just offers the
                                rows for consultation. */}
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => setReviewOpen(true)}
                                disabled={transactions.length === 0}
                                title={transactions.length === 0 ? 'Nessuna transazione in questo mese' : undefined}
                            >
                                <ListChecks className="w-4 h-4 mr-1" />
                                {pendingReview > 0
                                    ? `Da rivedere (${pendingReview})`
                                    : `Transazioni (${transactions.length})`}
                                {pendingReview > 0 && (
                                    <span className="ml-1.5 h-2 w-2 shrink-0 rounded-full bg-amber-500" aria-hidden />
                                )}
                                {/* Staged edits are easy to forget once the dialog
                                    is closed, so the way back in says so. */}
                                {edits.size > 0 && (
                                    <span className="ml-1.5 text-amber-500">· {edits.size} da salvare</span>
                                )}
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </div>

            {/* Only mounted while open, and keyed on the month: each opening
                starts fresh on the pending queue rather than on wherever the
                last session left the filters. */}
            {reviewOpen && (
            <TransactionsReviewDialog
                key={month}
                open
                onClose={() => setReviewOpen(false)}
                transactions={transactions}
                accounts={accounts}
                month={month}
                edits={edits}
                effective={effective}
                onStage={stage}
                onDiscard={() => setEdits(new Map())}
                onSave={saveAll}
                saving={saving}
            />
            )}
        </>
    );
}

Cashflow.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
