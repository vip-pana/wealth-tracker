import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Target, Check } from 'lucide-react';
import { formatCurrencyNoDecimals, formatDateLong } from '@/lib/formatters';
import type { GoalCoreProposalWidget, GoalData } from '@/Components/Advisor/types';

/**
 * Is every proposed field already the goal's current value? If so the proposal
 * was applied before (its local state is gone after a refresh), so we render it
 * as already applied rather than a fresh, clickable card. Only the proposed
 * fields are compared.
 */
function alreadyApplied(data: GoalCoreProposalWidget['data'], goal: GoalData | null | undefined): boolean {
    if (!goal) return false;
    const checks: boolean[] = [];
    if (data.target_value !== undefined) checks.push(data.target_value === goal.target_value);
    if (data.target_date !== undefined) checks.push(data.target_date === goal.target_date);
    if (data.description !== undefined) checks.push(data.description === goal.description);
    return checks.length > 0 && checks.every(Boolean);
}

/**
 * Confirmation card for an AI-proposed goal core (target amount, date, why). The
 * AI only PROPOSES (via propose_goal_core); nothing is written until the user
 * clicks Applica, which POSTs to /advisor/goal. Mirrors ProfileProposal: the
 * applied/dismissed state is local, so at mount we compare against the current
 * goal to avoid re-offering something already applied.
 */
export function GoalCoreProposal({ data, goal }: { data: GoalCoreProposalWidget['data']; goal?: GoalData | null }) {
    const [state, setState] = useState<'idle' | 'saving' | 'applied' | 'dismissed'>(
        alreadyApplied(data, goal) ? 'applied' : 'idle',
    );

    const rows: { label: string; value: string }[] = [];
    if (data.target_value !== undefined) rows.push({ label: 'Importo target', value: formatCurrencyNoDecimals(data.target_value) });
    if (data.target_date) rows.push({ label: 'Data target', value: formatDateLong(data.target_date) });
    if (data.description) rows.push({ label: 'Descrizione', value: data.description });

    if (rows.length === 0) return null;

    const apply = () => {
        setState('saving');
        router.post('/advisor/goal', { ...data }, {
            preserveScroll: true,
            preserveState: true,
            only: ['goal', 'goalObjective'],
            onSuccess: () => setState('applied'),
            onError: () => setState('idle'),
        });
    };

    return (
        <Card className="mt-3">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="flex items-center gap-1.5 text-sm">
                    <Target className="h-4 w-4 text-primary" />
                    Proposta per il tuo obiettivo
                </CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3 space-y-3">
                <dl className="space-y-1.5 text-xs">
                    {rows.map((row) => (
                        <div key={row.label} className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-2">
                            <dt className="text-muted-foreground">{row.label}</dt>
                            <dd className="font-medium sm:text-right">{row.value}</dd>
                        </div>
                    ))}
                </dl>

                {state === 'applied' ? (
                    <div className="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        <Check className="h-4 w-4" />
                        Obiettivo aggiornato.
                    </div>
                ) : state === 'dismissed' ? (
                    <p className="text-xs text-muted-foreground">Proposta annullata.</p>
                ) : (
                    <div className="flex gap-2">
                        <Button size="sm" onClick={apply} disabled={state === 'saving'}>
                            {state === 'saving' ? 'Salvataggio…' : 'Applica'}
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setState('dismissed')} disabled={state === 'saving'}>
                            Annulla
                        </Button>
                    </div>
                )}
                {(state === 'idle' || state === 'saving') && (
                    <p className="text-xs text-muted-foreground">
                        Nulla viene salvato finché non premi «Applica». Puoi sempre modificarlo dalla sezione Obiettivo.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
