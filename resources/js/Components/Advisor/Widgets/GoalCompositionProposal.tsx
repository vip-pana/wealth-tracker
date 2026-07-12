import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { PieChart, Check, AlertTriangle } from 'lucide-react';
import type { GoalCompositionProposalWidget, GoalData, MacroCategory } from '@/Components/Advisor/types';

/**
 * Does the goal's current macro composition already match these buckets (same
 * categories and percentages)? If so the proposal was applied before, so we
 * render the applied state after a refresh.
 */
function alreadyApplied(data: GoalCompositionProposalWidget['data'], goal: GoalData | null | undefined): boolean {
    if (!goal || goal.macro_allocations.length !== data.buckets.length || data.buckets.length === 0) return false;
    const current = new Map(goal.macro_allocations.map((a) => [a.macro_category, a.percentage]));
    return data.buckets.every((b) => current.get(b.macro_category) === b.percentage);
}

/**
 * Confirmation card for an AI-SUGGESTED target composition. Unlike the other
 * proposals, the percentages are editable: the advisor only suggests buckets and
 * a rationale, and the user decides the final numbers here before applying.
 * Applying POSTs the user-edited values to /advisor/goal/composition, which
 * replaces the goal's target composition (leaving core and milestones intact).
 * A live total is shown and a warning appears when it isn't 100% — but the user
 * can still apply (a deliberate, soft guard, matching the manual goal form).
 */
export function GoalCompositionProposal({ data, goal }: { data: GoalCompositionProposalWidget['data']; goal?: GoalData | null }) {
    const [rows, setRows] = useState<{ macro_category: MacroCategory; percentage: number }[]>(
        data.buckets.map((b) => ({ ...b })),
    );
    const [state, setState] = useState<'idle' | 'saving' | 'applied' | 'dismissed'>(
        alreadyApplied(data, goal) ? 'applied' : 'idle',
    );

    if (data.buckets.length === 0) return null;

    const total = Math.round(rows.reduce((sum, r) => sum + (Number.isFinite(r.percentage) ? r.percentage : 0), 0) * 10) / 10;
    const off100 = Math.abs(total - 100) > 0.05;

    const setPct = (i: number, raw: string) => {
        const value = raw === '' ? 0 : parseFloat(raw);
        setRows((prev) => prev.map((r, j) => (j === i ? { ...r, percentage: Number.isFinite(value) ? value : 0 } : r)));
    };

    const apply = () => {
        setState('saving');
        router.post('/advisor/goal/composition', {
            macro_allocations: rows.map((r) => ({ macro_category: r.macro_category, percentage: r.percentage })),
        }, {
            preserveScroll: true,
            preserveState: true,
            only: ['goal'],
            onSuccess: () => setState('applied'),
            onError: () => setState('idle'),
        });
    };

    return (
        <Card className="mt-3">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="flex items-center gap-1.5 text-sm">
                    <PieChart className="h-4 w-4 text-primary" />
                    Composizione target suggerita
                </CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3 space-y-3">
                {data.rationale && <p className="text-xs text-muted-foreground">{data.rationale}</p>}

                <div className="space-y-1.5">
                    {rows.map((row, i) => (
                        <div key={row.macro_category} className="flex items-center justify-between gap-2 text-xs">
                            <span className="font-medium">{row.macro_category}</span>
                            <div className="flex items-center gap-1">
                                <Input
                                    type="number"
                                    inputMode="decimal"
                                    min={0}
                                    max={100}
                                    value={Number.isFinite(row.percentage) ? row.percentage : ''}
                                    onChange={(e) => setPct(i, e.target.value)}
                                    disabled={state === 'saving' || state === 'applied'}
                                    className="h-7 w-20 text-right"
                                    aria-label={`Percentuale ${row.macro_category}`}
                                />
                                <span className="text-muted-foreground">%</span>
                            </div>
                        </div>
                    ))}
                </div>

                <div className={`flex items-center gap-1.5 text-xs ${off100 ? 'text-amber-600 dark:text-amber-400' : 'text-muted-foreground'}`}>
                    {off100 && <AlertTriangle className="h-3.5 w-3.5" />}
                    Totale: {total}%{off100 ? ' — di solito una composizione somma al 100%.' : ''}
                </div>

                {state === 'applied' ? (
                    <div className="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        <Check className="h-4 w-4" />
                        Composizione salvata.
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
                        È un suggerimento: modifica le percentuali come preferisci. Applicando sostituisci l&apos;allocazione target attuale.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
