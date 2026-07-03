import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { SegmentedToggle } from '@/Components/ui/SegmentedToggle';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Target, Pencil, Trash2, CalendarClock, TrendingUp } from 'lucide-react';
import { Money } from '@/Components/ui/Money';
import { Tooltip, TooltipTrigger, TooltipContent, TooltipProvider } from '@/Components/ui/tooltip';
import { monthsUntil, requiredMonthlyGrowth, requiredAnnualGrowth, formatPct, pctOfTotal, allocationDeviation } from '@/lib/goalMath';
import { SmallDonut } from '@/Components/Goal/SmallDonut';
import { MilestoneAccordionItem } from '@/Components/Goal/MilestoneAccordionItem';
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
    onDelete,
}: {
    goal: Goal;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    currentNetWorth: number | null;
    currentAllocation: CurrentAllocationItem[];
    currentMacroAllocation: CurrentMacroAllocationItem[];
    today: string;
    onEdit: () => void;
    onDelete: () => void;
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

    return (
        <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
            <Head title={`Obiettivo — ${goal.name}`} />

            <PageHeader
                icon={Target}
                title={goal.name}
                subtitle={(goal.description || goal.target_date) ? (
                    <>
                        {goal.description && (
                            <p className="whitespace-pre-wrap">{goal.description}</p>
                        )}
                        {goal.target_date && (
                            <p className="text-xs mt-1 flex items-center gap-1">
                                <CalendarClock className="w-3.5 h-3.5" />
                                Anno target: {goal.target_date.slice(0, 4)}
                                {months !== null && months > 0 && (
                                    <span className="ml-1">({months} mesi rimanenti)</span>
                                )}
                            </p>
                        )}
                    </>
                ) : undefined}
                actions={
                    <>
                        <Button variant="outline" size="sm" onClick={onEdit}>
                            <Pencil className="w-4 h-4 mr-1" />
                            Modifica
                        </Button>
                        <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-destructive" onClick={onDelete}>
                            <Trash2 className="w-4 h-4" />
                        </Button>
                    </>
                }
            />

            {/* Progress + Milestones side by side */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4 items-start">
            <Card>
                <CardHeader className="pb-3">
                    <CardTitle className="text-base flex items-center gap-2">
                        <TrendingUp className="w-4 h-4" />
                        Progresso verso l&apos;obiettivo
                    </CardTitle>
                </CardHeader>
                <CardContent className="space-y-4">
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
                </CardContent>
            </Card>

            {sortedMilestones.length > 0 && (() => {
                const nextIdx = sortedMilestones.findIndex((m) => current < m.target_value && m.target_date > today);
                return (
                    <Card>
                        <CardHeader className="pb-3">
                            <CardTitle className="text-base">Milestone</CardTitle>
                        </CardHeader>
                        <CardContent className="p-0">
                            <div className="divide-y divide-border">
                                {sortedMilestones.map((m, idx) => {
                                    const achieved = current >= m.target_value || m.target_date <= today;
                                    const defaultOpen = idx === nextIdx || (nextIdx === -1 && idx === sortedMilestones.length - 1);
                                    return <MilestoneAccordionItem key={m.id} milestone={m} achieved={achieved} defaultOpen={defaultOpen} />;
                                })}
                            </div>
                        </CardContent>
                    </Card>
                );
            })()}
            </div>

            {/* Allocation comparison */}
            {(goal.categoryAllocations.length > 0 || goal.macroAllocations.length > 0) && (
                <Card>
                    <CardHeader className="pb-3">
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
                    <CardContent className="p-3">
                        {currentNetWorth === null ? (
                            <p className="text-sm text-muted-foreground text-center py-4">
                                Nessuno snapshot disponibile. Crea uno snapshot per vedere il confronto.
                            </p>
                        ) : (
                            <div className="flex flex-col lg:flex-row gap-6">
                                {/* Donuts */}
                                <div className="flex justify-center gap-4 sm:gap-10 lg:flex-shrink-0">
                                    <SmallDonut data={currentDonut} title="Attuale" />
                                    <SmallDonut data={targetDonut} title="Target" />
                                </div>

                                {/* Deviation table — uses the shared Table
                                    primitives with compact overrides (the
                                    default h-12/p-4 spacing is too airy for
                                    this dense comparison). */}
                                <div className="flex-1 self-center">
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
                                                            <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: d.color }} />
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
    );
}
