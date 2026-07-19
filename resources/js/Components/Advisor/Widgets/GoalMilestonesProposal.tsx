import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Flag, Check } from 'lucide-react';
import { formatCurrencyNoDecimals, formatDateLong } from '@/lib/formatters';
import { MilestoneAllocationBar } from '@/Components/Advisor/Widgets/MilestoneAllocationBar';
import type { GoalMilestonesProposalWidget, GoalData } from '@/Components/Advisor/types';

/**
 * Do the goal's current milestones already match the proposed set (same values
 * and dates, same count)? If so the proposal was applied before, so we render
 * the applied state instead of re-offering it after a refresh. The advisor's
 * `label` maps to the stored `notes`, so labels aren't compared — only the
 * amounts and dates that define a milestone.
 */
function alreadyApplied(data: GoalMilestonesProposalWidget['data'], goal: GoalData | null | undefined): boolean {
    if (!goal || goal.milestones.length !== data.milestones.length || data.milestones.length === 0) return false;
    return data.milestones.every((m, i) =>
        m.target_value === goal.milestones[i]?.target_value && m.target_date === goal.milestones[i]?.target_date,
    );
}

/**
 * Confirmation card for AI-proposed intermediate milestones. propose_goal_milestones
 * only proposes; applying POSTs to /advisor/goal/milestones, which replaces the
 * goal's milestones (leaving core and composition intact). The advisor's `label`
 * is sent as the stored `notes`.
 */
export function GoalMilestonesProposal({ data, goal }: { data: GoalMilestonesProposalWidget['data']; goal?: GoalData | null }) {
    const [state, setState] = useState<'idle' | 'saving' | 'applied' | 'dismissed'>(
        alreadyApplied(data, goal) ? 'applied' : 'idle',
    );

    if (data.milestones.length === 0) return null;

    const apply = () => {
        setState('saving');
        router.post('/advisor/goal/milestones', {
            milestones: data.milestones.map((m) => ({
                notes: m.label,
                action: m.action,
                rationale: m.rationale,
                target_value: m.target_value,
                target_date: m.target_date,
                allocation: m.allocation ?? [],
            })),
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
                    <Flag className="h-4 w-4 text-primary" />
                    Tappe intermedie proposte
                </CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3 space-y-3">
                <ol className="space-y-2.5 text-xs">
                    {data.milestones.map((m, i) => (
                        <li key={i} className="space-y-1 border-l-2 border-border pl-2.5">
                            <div className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-2">
                                <span className="font-medium">{m.label ?? `Tappa ${i + 1}`}</span>
                                <span className="text-muted-foreground sm:text-right">
                                    {formatCurrencyNoDecimals(m.target_value)} · {formatDateLong(m.target_date)}
                                </span>
                            </div>
                            {m.action && (
                                <p><span className="font-medium text-foreground">Azione: </span><span className="text-muted-foreground">{m.action}</span></p>
                            )}
                            {m.rationale && (
                                <p><span className="font-medium text-foreground">Perché: </span><span className="text-muted-foreground">{m.rationale}</span></p>
                            )}
                            {(m.allocation?.length ?? 0) > 0 && (
                                <div className="space-y-1 pt-0.5">
                                    <span className="font-medium text-foreground">Allocazione target</span>
                                    <MilestoneAllocationBar segments={m.allocation ?? []} />
                                </div>
                            )}
                        </li>
                    ))}
                </ol>

                {state === 'applied' ? (
                    <div className="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        <Check className="h-4 w-4" />
                        Tappe salvate.
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
                        Applicando sostituisci le tappe intermedie attuali. Nulla viene salvato finché non premi «Applica».
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
