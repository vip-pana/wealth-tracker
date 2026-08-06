import { useState } from 'react';
import { Money } from '@/Components/ui/Money';
import { MilestoneAllocationDetail } from '@/Components/Goal/MilestoneAllocationDetail';
import { cn } from '@/lib/utils';
import { CheckCircle2, Circle, ChevronLeft, ChevronRight, Flag } from 'lucide-react';
import type { GoalMilestone } from '@/types/models';

type Segment = { category: string; percentage: number; color?: string; cap_amount?: number | null };

export interface MilestoneEntry {
    milestone: GoalMilestone;
    achieved: boolean;
    segments: Segment[];
}

/**
 * The goal's milestones, one at a time: a clickable timeline of the whole path
 * on top, the selected step's detail below. It opens on the next step still to
 * reach and it never advances on its own — only the arrows or a timeline click
 * move it, so reading a step is never interrupted.
 *
 * Replaces a stack of accordions whose expanded bodies made the card grow
 * without bound.
 *
 * A section, not a card: it renders inside the goal card, below the progress
 * bar, so the goal reads as one object — where it's going and the steps there.
 */
export function MilestoneCarousel({
    milestones,
    initialIndex,
}: {
    milestones: MilestoneEntry[];
    initialIndex: number;
}) {
    const total = milestones.length;
    const [idx, setIdx] = useState(Math.min(Math.max(initialIndex, 0), Math.max(total - 1, 0)));

    if (total === 0) {
        return null;
    }

    function step(direction: -1 | 1) {
        setIdx((i) => (i + direction + total) % total);
    }

    const safeIdx = Math.min(idx, total - 1);
    const active = milestones[safeIdx];
    const previous = safeIdx > 0 ? milestones[safeIdx - 1] : null;
    const { milestone, segments } = active;
    const hasDetail = Boolean(milestone.notes || milestone.action || milestone.rationale || segments.length > 0);

    // Generous space around the rule: it reads as two parts of one card rather
    // than a cramped stack, and it takes up the slack the stretched card leaves
    // at the bottom.
    return (
        <div className="space-y-3 mt-10 pt-8 border-t border-border">
                <div className="flex flex-row items-center justify-between gap-2">
                    <h3 className="text-sm font-semibold flex items-center gap-2 min-w-0">
                        <Flag className="w-4 h-4 shrink-0" />
                        Milestone
                    </h3>
                {total > 1 && (
                    <div className="flex items-center gap-1 shrink-0">
                        <button
                            type="button"
                            onClick={() => step(-1)}
                            title="Milestone precedente"
                            aria-label="Milestone precedente"
                            className="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:text-foreground hover:bg-muted/60"
                        >
                            <ChevronLeft className="w-4 h-4" />
                        </button>
                        <span className="text-xs text-muted-foreground tabular-nums" aria-hidden="true">
                            {idx + 1}/{total}
                        </span>
                        <button
                            type="button"
                            onClick={() => step(1)}
                            title="Milestone successiva"
                            aria-label="Milestone successiva"
                            className="inline-flex items-center justify-center rounded-md p-2 text-muted-foreground hover:text-foreground hover:bg-muted/60"
                        >
                            <ChevronRight className="w-4 h-4" />
                        </button>
                    </div>
                )}
                </div>

                {/* The whole path stays visible: scrolls sideways rather than
                    wrapping, so a long glide-path can't stretch the card. */}
                {total > 1 && (
                    <div className="flex items-center gap-1.5 overflow-x-auto pb-1">
                        {milestones.map((entry, i) => (
                            <button
                                key={entry.milestone.id}
                                type="button"
                                onClick={() => setIdx(i)}
                                aria-current={i === idx}
                                aria-label={`Milestone ${entry.milestone.target_date.slice(0, 4)}`}
                                className={cn(
                                    'shrink-0 inline-flex items-center gap-1.5 rounded-lg border px-2 py-1 text-xs transition-colors',
                                    i === idx
                                        ? 'border-primary/50 bg-primary/10 text-foreground'
                                        : 'border-border text-muted-foreground hover:bg-muted/60',
                                )}
                            >
                                {entry.achieved ? (
                                    <CheckCircle2 className="w-3.5 h-3.5 text-green-500 shrink-0" />
                                ) : (
                                    <Circle className="w-3.5 h-3.5 shrink-0" />
                                )}
                                <span className={cn('tabular-nums', entry.achieved && 'line-through')}>
                                    <Money value={entry.milestone.target_value} variant="no-decimals" />
                                </span>
                                <span className="opacity-70">{entry.milestone.target_date.slice(0, 4)}</span>
                            </button>
                        ))}
                    </div>
                )}

                {/* min-h keeps the card from jumping between a rich step and a
                    bare one. key + fade so the swap reads as a change. */}
                <div key={milestone.id} className="min-h-56 animate-fade-in">
                    <div className="flex items-center gap-2 mb-2">
                        {active.achieved ? (
                            <CheckCircle2 className="w-4 h-4 text-green-500 shrink-0" />
                        ) : (
                            <Circle className="w-4 h-4 text-muted-foreground shrink-0" />
                        )}
                        <span className={cn('text-sm font-semibold', active.achieved && 'line-through text-muted-foreground')}>
                            <Money value={milestone.target_value} variant="no-decimals" />
                            <span className="ml-2 text-xs font-normal text-muted-foreground">{milestone.target_date.slice(0, 4)}</span>
                        </span>
                    </div>

                    {hasDetail ? (
                        <div className="space-y-1.5 sm:pl-6">
                            {milestone.notes && (
                                <p className="text-xs whitespace-pre-wrap"><span className="font-medium">Etichetta: </span><span className="text-muted-foreground">{milestone.notes}</span></p>
                            )}
                            {milestone.action && (
                                <p className="text-xs whitespace-pre-wrap"><span className="font-medium">Azione: </span><span className="text-muted-foreground">{milestone.action}</span></p>
                            )}
                            {milestone.rationale && (
                                <p className="text-xs whitespace-pre-wrap"><span className="font-medium">Perché: </span><span className="text-muted-foreground">{milestone.rationale}</span></p>
                            )}
                            {segments.length > 0 && (
                                <div className="pt-3">
                                    {/* A block, not an inline span: the label needs
                                        real space above the bars. */}
                                    <p className="text-xs font-medium mb-3">
                                        Allocazione target
                                        <span className="ml-1 font-normal text-muted-foreground">
                                            · <Money value={milestone.target_value} variant="no-decimals" />
                                        </span>
                                    </p>
                                    {/* The previous step is the baseline for the
                                        delta — the glide path reads as movement. */}
                                    <MilestoneAllocationDetail
                                        segments={segments}
                                        targetValue={milestone.target_value}
                                        previous={previous?.segments ?? null}
                                        previousTargetValue={previous?.milestone.target_value ?? null}
                                    />
                                </div>
                            )}
                        </div>
                    ) : (
                        <p className="text-xs text-muted-foreground pl-6">Nessun dettaglio per questa tappa.</p>
                    )}
                </div>
        </div>
    );
}
