import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { SegmentedToggle } from '@/Components/ui/SegmentedToggle';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Target, Pencil, CalendarClock, TrendingUp } from 'lucide-react';
import { Money } from '@/Components/ui/Money';
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from '@/Components/ui/tooltip';
import { monthsUntil, requiredMonthlyGrowth, requiredAnnualGrowth, formatPct, pctOfTotal, allocationDeviation } from '@/lib/goalMath';
import { SmallDonut } from '@/Components/Goal/SmallDonut';
import { MilestoneCarousel } from '@/Components/Goal/MilestoneCarousel';
import { MACRO_COLORS } from '@/Components/Goal/types';
import type { CurrentAllocationItem, CurrentMacroAllocationItem } from '@/Components/Goal/types';
import type { Category } from '@/types/models';
import type { Goal } from '@/types/models';

export function GoalProgress({
    goal,
    categories,
    currentNetWorth,
    currentAllocation,
    currentMacroAllocation,
    today,
    onEdit,
    profileCard,
}: {
    goal: Goal;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    currentNetWorth: number | null;
    currentAllocation: CurrentAllocationItem[];
    currentMacroAllocation: CurrentMacroAllocationItem[];
    today: string;
    onEdit: () => void;
    // The investor-profile card, rendered inside this page's container so it
    // shares the layout. Passed in rather than imported so this component stays
    // about the goal itself.
    profileCard?: React.ReactNode;
}) {
    const [allocTab, setAllocTab] = useState<'category' | 'macro'>('category');

    const current = currentNetWorth ?? 0;
    const target = goal.target_value;
    const progressPct = target > 0 ? Math.min((current / target) * 100, 100) : 0;
    const remaining = Math.max(target - current, 0);

    const months = goal.target_date ? monthsUntil(goal.target_date) : null;
    const annualGrowth = months !== null && current > 0 ? requiredAnnualGrowth(current, target, months) : null;
    const monthlyGrowth = months !== null && current > 0 ? requiredMonthlyGrowth(current, target, months) : null;

    // Allocation comparison — category
    const totalCurrent = currentAllocation.reduce((s, a) => s + a.value, 0);
    const categoryDeviations = goal.categoryAllocations.map((alloc) => {
        const cat = categories.find((c) => c.id === alloc.category_id);
        const currentVal = currentAllocation.find((a) => a.category_id === alloc.category_id)?.value ?? 0;
        const currentPct = pctOfTotal(currentVal, totalCurrent);
        const delta = allocationDeviation(currentVal, totalCurrent, alloc.percentage);
        return { name: cat?.name ?? '?', color: cat?.color ?? '#94a3b8', currentPct, targetPct: alloc.percentage, delta };
    });

    const currentDonutCat = categoryDeviations.map((d) => ({ name: d.name, value: d.currentPct, color: d.color }));
    const targetDonutCat = categoryDeviations.map((d) => ({ name: d.name, value: d.targetPct, color: d.color }));

    // Allocation comparison — macro
    const totalCurrentMacro = currentMacroAllocation.reduce((s, a) => s + a.value, 0);
    const macroDeviations = goal.macroAllocations.map((alloc) => {
        const currentVal = currentMacroAllocation.find((a) => a.macro_category === alloc.macro_category)?.value ?? 0;
        const currentPct = pctOfTotal(currentVal, totalCurrentMacro);
        const delta = allocationDeviation(currentVal, totalCurrentMacro, alloc.percentage);
        const color = MACRO_COLORS[alloc.macro_category ?? ''] ?? '#94a3b8';
        return { name: alloc.macro_category ?? '', color, currentPct, targetPct: alloc.percentage, delta };
    });

    const currentDonutMacro = macroDeviations.map((d) => ({ name: d.name, value: d.currentPct, color: d.color }));
    const targetDonutMacro = macroDeviations.map((d) => ({ name: d.name, value: d.targetPct, color: d.color }));

    const deviations = allocTab === 'category' ? categoryDeviations : macroDeviations;
    const currentDonut = allocTab === 'category' ? currentDonutCat : currentDonutMacro;
    const targetDonut = allocTab === 'category' ? targetDonutCat : targetDonutMacro;

    // Milestones sorted
    const sortedMilestones = [...goal.milestones].sort((a, b) => a.target_date.localeCompare(b.target_date));

    // From lg up the page is a full-height column: the header takes what it
    // needs and the two-column grid absorbs the rest, so the page fits the
    // viewport and the long content (allocation table, milestone steps) scrolls
    // inside its own card. Below lg the columns stack and the page scrolls as
    // before.
    return (
        <div className="p-4 gap-4 max-w-[1400px] mx-auto w-full animate-page-enter flex flex-col shrink-0 lg:flex-1 lg:shrink lg:min-h-0">
            <Head title={`Obiettivo — ${goal.name}`} />

            {/* The page is "Obiettivo"; the goal's own identity (name,
                description, target year) and its actions belong to the card that
                tracks it — editing opens the dialog, which is also where the
                goal can be deleted. */}
            <PageHeader icon={Target} title="Obiettivo" />

            {/* Two columns: the left stacks who the user is and how the mix
                compares, the right is the goal itself — where it's going and the
                milestones on the way, as one object. No items-start, so the goal
                card runs down to the same height as the left column. Below lg it
                all stacks. */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 lg:flex-1 lg:min-h-0">
                <div className="flex flex-col gap-4 lg:min-h-0">
                {profileCard}
                {/* Allocation comparison */}
                {(goal.categoryAllocations.length > 0 || goal.macroAllocations.length > 0) && (
                    <Card className="flex flex-col overflow-hidden lg:flex-1 lg:min-h-0">
                        <CardHeader className="pb-3 shrink-0">
                            <div className="flex items-center justify-between">
                                <CardTitle className="text-base">Composizione: attuale vs target</CardTitle>
                                <SegmentedToggle
                                    size="xs"
                                    options={[
                                        ...(goal.categoryAllocations.length > 0 ? [{ value: 'category' as const, label: 'Categorie' }] : []),
                                        ...(goal.macroAllocations.length > 0 ? [{ value: 'macro' as const, label: 'Macro' }] : []),
                                    ]}
                                    value={allocTab}
                                    onChange={setAllocTab}
                                />
                            </div>
                        </CardHeader>
                        <CardContent className="lg:flex-1 lg:min-h-0 lg:overflow-y-auto">
                            {currentNetWorth === null ? (
                                <p className="text-sm text-muted-foreground text-center py-4">
                                    Nessuno snapshot disponibile. Crea uno snapshot per vedere il confronto.
                                </p>
                            ) : (
                                <div className="flex flex-col gap-4">
                                    {/* Donuts above the table: in a half-width
                                        column there isn't room to sit them side
                                        by side. */}
                                    <div className="flex justify-center gap-4 sm:gap-10">
                                        <SmallDonut data={currentDonut} title="Attuale" />
                                        <SmallDonut data={targetDonut} title="Target" />
                                    </div>

                                    {/* Deviation table — uses the shared Table
                                        primitives with compact overrides (the
                                        default h-12/p-4 spacing is too airy for
                                        this dense comparison). */}
                                    <div className="flex-1">
                                        <Table className="text-xs">
                                            <TableHeader>
                                                <TableRow className="hover:bg-transparent">
                                                    <TableHead className="h-auto pb-2 px-0 text-xs">Categoria</TableHead>
                                                    <TableHead className="h-auto pb-2 px-4 text-xs text-right">Attuale</TableHead>
                                                    <TableHead className="h-auto pb-2 px-4 text-xs text-right">Target</TableHead>
                                                    <TableHead className="h-auto pb-2 pl-4 pr-0 text-xs text-right">Delta</TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {deviations.map((d) => (
                                                    <TableRow key={d.name} className="border-0 hover:bg-transparent">
                                                        <TableCell className="py-1 px-0">
                                                            <div className="flex items-center gap-2">
                                                                <div className="w-2 h-2 rounded-full shrink-0" style={{ backgroundColor: d.color }} />
                                                                <span className="font-medium text-sm">{d.name}</span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-right font-mono text-muted-foreground py-1 px-4">{formatPct(d.currentPct)}</TableCell>
                                                        <TableCell className="text-right font-mono text-muted-foreground py-1 px-4">{formatPct(d.targetPct)}</TableCell>
                                                        <TableCell className="text-right py-1 pl-4 pr-0">
                                                            <Badge
                                                                variant="outline"
                                                                className={`font-mono text-xs ${
                                                                    Math.abs(d.delta) < 1
                                                                        ? 'text-green-500 border-green-500/30'
                                                                        : d.delta > 0
                                                                        ? 'text-blue-400 border-blue-400/30'
                                                                        : 'text-orange-400 border-orange-400/30'
                                                                }`}
                                                            >
                                                                {d.delta > 0 ? '+' : ''}{formatPct(d.delta)}
                                                            </Badge>
                                                        </TableCell>
                                                    </TableRow>
                                                ))}
                                            </TableBody>
                                        </Table>
                                    </div>
                                </div>
                            )}
                        </CardContent>
                    </Card>
                )}
                </div>

                <Card className="flex flex-col h-full overflow-hidden lg:min-h-0">
                    <CardHeader className="pb-3 space-y-1 shrink-0">
                        <div className="flex flex-row items-center justify-between gap-2">
                            <CardTitle className="text-base flex items-center gap-2 min-w-0">
                                <TrendingUp className="w-4 h-4 shrink-0" />
                                <span className="truncate">{goal.name}</span>
                            </CardTitle>
                            <Button variant="outline" size="sm" className="shrink-0" onClick={onEdit}>
                                <Pencil className="w-4 h-4 mr-1" />
                                Modifica
                            </Button>
                        </div>
                        {goal.description && (
                            <p className="text-xs text-muted-foreground whitespace-pre-wrap">{goal.description}</p>
                        )}
                        {goal.target_date && (
                            <p className="text-xs text-muted-foreground flex items-center gap-1">
                                <CalendarClock className="w-3.5 h-3.5 shrink-0" />
                                Anno target: {goal.target_date.slice(0, 4)}
                                {months !== null && months > 0 && (
                                    <span>({months} mesi rimanenti)</span>
                                )}
                            </p>
                        )}
                    </CardHeader>
                    <CardContent className="space-y-4 lg:flex-1 lg:min-h-0 lg:overflow-y-auto">
                        <div className="flex justify-between text-sm">
                            <Money value={current} variant="no-decimals" className="font-medium" />
                            <Money value={target} variant="no-decimals" className="text-muted-foreground" />
                        </div>
                        <TooltipProvider delayDuration={100}>
                            <div className="relative w-full h-3 rounded-full bg-muted">
                                <div
                                    className="h-full rounded-full bg-primary transition-all"
                                    style={{ width: `${progressPct}%` }}
                                />
                                {target > 0 && sortedMilestones.map((m) => {
                                    const pos = Math.min((m.target_value / target) * 100, 100);
                                    const reached = current >= m.target_value;
                                    return (
                                        <Tooltip key={m.id}>
                                            <TooltipTrigger asChild>
                                                <button
                                                    type="button"
                                                    aria-label={`Milestone: ${m.notes ?? ''}`}
                                                    className={`absolute top-1/2 h-4 w-1 -translate-x-1/2 -translate-y-1/2 rounded-full ring-2 ring-background transition-colors ${reached ? 'bg-emerald-500' : 'bg-foreground/40'}`}
                                                    style={{ left: `${pos}%` }}
                                                />
                                            </TooltipTrigger>
                                            <TooltipContent>
                                                <div className="space-y-0.5 text-xs">
                                                    {m.notes && <p className="font-medium">{m.notes}</p>}
                                                    <p><Money value={m.target_value} variant="no-decimals" /> · {m.target_date}</p>
                                                    <p className={reached ? 'text-emerald-500' : 'text-muted-foreground'}>
                                                        {reached ? 'Raggiunta' : <><Money value={Math.max(m.target_value - current, 0)} variant="no-decimals" /> mancanti</>}
                                                    </p>
                                                </div>
                                            </TooltipContent>
                                        </Tooltip>
                                    );
                                })}
                            </div>
                        </TooltipProvider>
                        <div className="flex justify-between text-xs text-muted-foreground">
                            <span>{progressPct.toFixed(1)}% raggiunto</span>
                            <span><Money value={remaining} variant="no-decimals" /> mancanti</span>
                        </div>

                        {/* Growth rate */}
                        {annualGrowth !== null && monthlyGrowth !== null && months !== null && months > 0 && (
                            <div className="pt-2 border-t border-border grid grid-cols-1 sm:grid-cols-2 gap-4">
                                <div>
                                    <p className="text-xs text-muted-foreground">Crescita mensile necessaria</p>
                                    <p className="text-lg font-bold text-primary">{monthlyGrowth.toFixed(2)}%</p>
                                </div>
                                <div>
                                    <p className="text-xs text-muted-foreground">Equivalente annuale</p>
                                    <p className="text-lg font-bold text-primary">{annualGrowth.toFixed(2)}%</p>
                                </div>
                            </div>
                        )}

                {sortedMilestones.length > 0 && (() => {
                    const nextIdx = sortedMilestones.findIndex((m) => current < m.target_value && m.target_date > today);
                    const entries = sortedMilestones.map((m) => ({
                        milestone: m,
                        achieved: current >= m.target_value || m.target_date <= today,
                        // Map the milestone's stored allocation (keyed by category_id)
                        // to the bar's segments (name + colour), so the step shows the
                        // glide-path like the advisor widget does.
                        segments: (m.allocation ?? []).map((a) => {
                            const cat = categories.find((c) => c.id === a.category_id);
                            return {
                                category: cat?.name ?? 'Sconosciuta',
                                percentage: a.percentage,
                                color: cat?.color ?? undefined,
                                cap_amount: a.cap_amount ?? null,
                            };
                        }),
                    }));

                    // Open on the next step still to reach; once they're all reached,
                    // on the last one.
                    return (
                        <MilestoneCarousel
                            milestones={entries}
                            initialIndex={nextIdx === -1 ? sortedMilestones.length - 1 : nextIdx}
                        />
                    );
                })()}
                    </CardContent>
                </Card>
            </div>

        </div>
    );
}
