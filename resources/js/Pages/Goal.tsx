import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { SegmentedToggle } from '@/Components/ui/SegmentedToggle';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';
import { Target, Pencil, Trash2, Plus, CheckCircle2, Circle, CalendarClock, TrendingUp, ChevronDown, ChevronRight } from 'lucide-react';
import { formatCurrencyNoDecimals } from '@/lib/formatters';
import type { Category } from '@/types/models';
import type { Goal } from '@/types/models';

const MACRO_COLORS: Record<string, string> = {
    'Liquidità': '#60a5fa',
    'ETF': '#34d399',
    'Cripto': '#f59e0b',
};

interface CurrentAllocationItem {
    category_id: number;
    value: number;
}

interface CurrentMacroAllocationItem {
    macro_category: string;
    value: number;
}

interface Props {
    goal: Goal | null;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    currentNetWorth: number | null;
    currentAllocation: CurrentAllocationItem[];
    currentMacroAllocation: CurrentMacroAllocationItem[];
}

// ─── Form types ───────────────────────────────────────────────────────────────

interface AllocationFormItem {
    category_id?: string;
    macro_category?: string;
    percentage: string;
}

interface MilestoneFormItem {
    notes: string;
    target_value: string;
    target_date: string;
}

type GoalFormData = {
    name: string;
    description: string;
    target_value: string;
    target_date: string;
    category_allocations: AllocationFormItem[];
    milestones: MilestoneFormItem[];
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function allocationSum(items: AllocationFormItem[]): number {
    return items.reduce((s, i) => s + (parseFloat(i.percentage) || 0), 0);
}

function formatPct(value: number): string {
    return Number.isInteger(value) ? `${value}%` : `${value.toFixed(1)}%`;
}


function monthsUntil(dateStr: string): number {
    const target = new Date(dateStr + 'T00:00:00');
    const now = new Date();
    return (target.getFullYear() - now.getFullYear()) * 12 + (target.getMonth() - now.getMonth());
}

function requiredMonthlyGrowth(current: number, target: number, months: number): number | null {
    if (months <= 0 || current <= 0) return null;
    return (Math.pow(target / current, 1 / months) - 1) * 100;
}

function requiredAnnualGrowth(current: number, target: number, months: number): number | null {
    const monthly = requiredMonthlyGrowth(current, target, months);
    if (monthly === null) return null;
    return (Math.pow(1 + monthly / 100, 12) - 1) * 100;
}

// ─── Sub-components ───────────────────────────────────────────────────────────

function AllocationSection({
    title,
    items,
    onAdd,
    onUpdate,
    onRemove,
    renderSelect,
}: {
    title: string;
    items: AllocationFormItem[];
    onAdd: () => void;
    onUpdate: (idx: number, field: string, value: string) => void;
    onRemove: (idx: number) => void;
    renderSelect: (item: AllocationFormItem, idx: number) => React.ReactNode;
}) {
    const sum = allocationSum(items);
    const remaining = Math.max(0, 100 - sum);
    const sumOk = items.length === 0 || Math.abs(sum - 100) < 0.01;

    return (
        <div className="space-y-3">
            <Label>{title}</Label>

            {items.map((item, idx) => {
                const pct = parseFloat(item.percentage) || 0;
                return (
                    <div key={idx} className="space-y-1.5">
                        <div className="flex gap-2 items-center">
                            <div className="flex-1">{renderSelect(item, idx)}</div>
                            <div className="relative w-20">
                                <Input
                                    type="number"
                                    min={0}
                                    max={100}
                                    step={0.1}
                                    value={item.percentage}
                                    onChange={(e) => onUpdate(idx, 'percentage', e.target.value)}
                                    className="text-right font-mono pr-6"
                                    placeholder="0"
                                />
                                <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">%</span>
                            </div>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 text-muted-foreground hover:text-destructive"
                                onClick={() => onRemove(idx)}
                            >
                                <Trash2 className="w-4 h-4" />
                            </Button>
                        </div>
                        {pct > 0 && (
                            <div className="h-1 w-full bg-muted rounded-full overflow-hidden">
                                <div
                                    className="h-1 rounded-full bg-primary transition-all"
                                    style={{ width: `${Math.min(100, pct)}%` }}
                                />
                            </div>
                        )}
                    </div>
                );
            })}

            <Button type="button" variant="outline" size="sm" onClick={onAdd} className="w-full">
                <Plus className="w-3.5 h-3.5 mr-1" />
                Aggiungi
            </Button>

            {items.length > 0 && (
                <div className="space-y-1.5 pt-1">
                    <div className="h-1.5 w-full bg-muted rounded-full overflow-hidden">
                        <div
                            className={`h-1.5 rounded-full transition-all ${sumOk ? 'bg-green-500' : sum > 100 ? 'bg-destructive' : 'bg-primary'}`}
                            style={{ width: `${Math.min(100, sum)}%` }}
                        />
                    </div>
                    <div className="flex items-center justify-between text-xs">
                        <span className="text-muted-foreground">
                            {sumOk ? 'Allocazione completa' : `Rimanente: ${formatPct(remaining)}`}
                        </span>
                        <span className={`font-mono ${sumOk ? 'text-green-500' : sum > 100 ? 'text-destructive' : 'text-muted-foreground'}`}>
                            {formatPct(sum)} / 100%
                        </span>
                    </div>
                </div>
            )}
        </div>
    );
}

function MilestoneFormRow({
    item,
    idx,
    isLast,
    onUpdate,
    onRemove,
}: {
    item: MilestoneFormItem;
    idx: number;
    isLast: boolean;
    onUpdate: (idx: number, field: string, value: string) => void;
    onRemove: (idx: number) => void;
}) {
    const [noteOpen, setNoteOpen] = useState(!!item.notes);

    return (
        <div className="flex gap-3">
            {/* Timeline spine */}
            <div className="flex flex-col items-center flex-shrink-0 w-5">
                <div className="w-5 h-5 rounded-full bg-primary/20 border border-primary/40 flex items-center justify-center flex-shrink-0 mt-2">
                    <span className="text-[10px] font-bold text-primary">{idx + 1}</span>
                </div>
                {!isLast && <div className="w-px flex-1 bg-border mt-1" />}
            </div>

            {/* Content */}
            <div className={`flex-1 space-y-2 ${isLast ? '' : 'pb-4'}`}>
                <div className="flex flex-col sm:flex-row gap-2 sm:items-center">
                    <div className="relative flex-1">
                        <Input
                            type="text"
                            inputMode="decimal"
                            value={item.target_value}
                            onChange={(e) => onUpdate(idx, 'target_value', e.target.value)}
                            placeholder="Valore es. 50000"
                            className="font-mono pr-24 text-sm"
                        />
                        {item.target_value && !isNaN(parseFloat(item.target_value)) && (
                            <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-muted-foreground font-mono">
                                {formatCurrencyNoDecimals(parseFloat(item.target_value))}
                            </span>
                        )}
                    </div>
                    <div className="flex gap-2 items-center">
                        <Input
                            type="number"
                            min={2020}
                            max={2100}
                            step={1}
                            value={item.target_date ? item.target_date.slice(0, 4) : ''}
                            onChange={(e) => {
                                const y = e.target.value;
                                onUpdate(idx, 'target_date', y ? `${y}-01-01` : '');
                            }}
                            placeholder="Anno"
                            className="font-mono text-sm flex-1 sm:flex-none sm:w-24"
                        />
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 text-muted-foreground hover:text-destructive flex-shrink-0"
                            onClick={() => onRemove(idx)}
                        >
                            <Trash2 className="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                {noteOpen ? (
                    <textarea
                        value={item.notes}
                        onChange={(e) => onUpdate(idx, 'notes', e.target.value)}
                        placeholder="Note opzionali..."
                        rows={7}
                        autoFocus
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                ) : (
                    <button
                        type="button"
                        onClick={() => setNoteOpen(true)}
                        className="text-xs text-muted-foreground hover:text-foreground transition-colors"
                    >
                        + Aggiungi nota
                    </button>
                )}
            </div>
        </div>
    );
}

function MilestonesSection({
    items,
    onAdd,
    onUpdate,
    onRemove,
}: {
    items: MilestoneFormItem[];
    onAdd: () => void;
    onUpdate: (idx: number, field: string, value: string) => void;
    onRemove: (idx: number) => void;
}) {
    return (
        <div className="space-y-2">
            <Label>Milestone intermedie (opzionale)</Label>
            <div className="space-y-0">
                {items.map((item, idx) => (
                    <MilestoneFormRow
                        key={idx}
                        item={item}
                        idx={idx}
                        isLast={idx === items.length - 1}
                        onUpdate={onUpdate}
                        onRemove={onRemove}
                    />
                ))}
            </div>
            <Button type="button" variant="outline" size="sm" onClick={onAdd} className="w-full">
                <Plus className="w-3.5 h-3.5 mr-1" />
                Aggiungi milestone
            </Button>
        </div>
    );
}

// ─── Goal Form Dialog ─────────────────────────────────────────────────────────

function GoalFormDialog({
    open,
    onClose,
    categories,
    goal,
}: {
    open: boolean;
    onClose: () => void;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    goal: Goal | null;
}) {
    const isEdit = goal !== null;

    const { data, setData, post, put, processing, errors, reset } = useForm<GoalFormData>({
        name: goal?.name ?? '',
        description: goal?.description ?? '',
        target_value: goal ? String(goal.target_value) : '',
        target_date: goal?.target_date ?? '',
        category_allocations: goal?.categoryAllocations.map((a) => ({
            category_id: String(a.category_id ?? ''),
            percentage: String(a.percentage),
        })) ?? [],
        milestones: goal?.milestones.map((m) => ({
            notes: m.notes ?? '',
            target_value: String(m.target_value),
            target_date: m.target_date,
        })) ?? [],
    });

    useEffect(() => {
        if (open) {
            setData({
                name: goal?.name ?? '',
                description: goal?.description ?? '',
                target_value: goal ? String(goal.target_value) : '',
                target_date: goal?.target_date ?? '',
                category_allocations: goal?.categoryAllocations.map((a) => ({
                    category_id: String(a.category_id ?? ''),
                    percentage: String(a.percentage),
                })) ?? [],
                milestones: goal?.milestones.map((m) => ({
                    notes: m.notes ?? '',
                    target_value: String(m.target_value),
                    target_date: m.target_date,
                })) ?? [],
            });
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, goal]);

    const updateCategoryAlloc = (idx: number, field: string, value: string) => {
        const updated = [...(data.category_allocations as AllocationFormItem[])];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('category_allocations', updated);
    };

    const updateMilestone = (idx: number, field: string, value: string) => {
        const updated = [...(data.milestones as MilestoneFormItem[])];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('milestones', updated);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { onSuccess: () => { reset(); onClose(); } };
        if (isEdit) {
            put(`/goal/${goal.id}`, opts);
        } else {
            post('/goal', opts);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-2xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Modifica obiettivo' : 'Crea il tuo obiettivo'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-6">
                    {/* Base info */}
                    <div className="grid grid-cols-2 gap-4">
                        <div className="col-span-2 space-y-1">
                            <Label>Nome obiettivo</Label>
                            <Input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="es. FIRE, Pensione, Prima casa"
                            />
                            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                        </div>
                        <div className="col-span-2 space-y-1">
                            <Label>Descrizione (opzionale)</Label>
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Breve descrizione del tuo obiettivo"
                                rows={3}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label>Patrimonio obiettivo</Label>
                            <div className="relative">
                                <Input
                                    type="text"
                                    inputMode="decimal"
                                    value={data.target_value}
                                    onChange={(e) => setData('target_value', e.target.value)}
                                    placeholder="es. 500000"
                                    className="font-mono pr-32"
                                />
                                <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground font-mono pointer-events-none">
                                    {data.target_value && !isNaN(parseFloat(data.target_value))
                                        ? formatCurrencyNoDecimals(parseFloat(data.target_value))
                                        : ''}
                                </span>
                            </div>
                            {errors.target_value && <p className="text-xs text-destructive">{errors.target_value}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Anno di raggiungimento (opzionale)</Label>
                            <Input
                                type="number"
                                min={2020}
                                max={2100}
                                step={1}
                                value={data.target_date ? data.target_date.slice(0, 4) : ''}
                                onChange={(e) => {
                                    const y = e.target.value;
                                    setData('target_date', y ? `${y}-01-01` : '');
                                }}
                                placeholder="es. 2045"
                                className="font-mono"
                            />
                        </div>
                    </div>

                    {/* Category allocations */}
                    <div className="rounded-md border border-border p-4">
                        <AllocationSection
                            title="Target allocation per categoria"
                            items={data.category_allocations as AllocationFormItem[]}
                            onAdd={() => setData('category_allocations', [...(data.category_allocations as AllocationFormItem[]), { category_id: '', percentage: '' }])}
                            onUpdate={updateCategoryAlloc}
                            onRemove={(idx) => setData('category_allocations', (data.category_allocations as AllocationFormItem[]).filter((_, i) => i !== idx))}
                            renderSelect={(item, idx) => (
                                <div className="relative">
                                    <select
                                        value={(item as AllocationFormItem).category_id ?? ''}
                                        onChange={(e) => updateCategoryAlloc(idx, 'category_id', e.target.value)}
                                        className="w-full h-9 appearance-none rounded-md border border-input bg-background pl-3 pr-8 text-sm"
                                    >
                                        <option value="">Seleziona categoria</option>
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>{c.name}</option>
                                        ))}
                                    </select>
                                    <ChevronDown className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground" />
                                </div>
                            )}
                        />
                    </div>

                    {/* Milestones */}
                    <div className="rounded-md border border-border p-4">
                        <MilestonesSection
                            items={data.milestones as MilestoneFormItem[]}
                            onAdd={() => setData('milestones', [...(data.milestones as MilestoneFormItem[]), { notes: '', target_value: '', target_date: '' }])}
                            onUpdate={updateMilestone}
                            onRemove={(idx) => setData('milestones', (data.milestones as MilestoneFormItem[]).filter((_, i) => i !== idx))}
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annulla</Button>
                        <Button type="submit" disabled={processing}>
                            {isEdit ? 'Salva modifiche' : 'Crea obiettivo'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

// ─── Donut chart (inline, simple) ─────────────────────────────────────────────

function SmallDonut({ data, title }: { data: { name: string; value: number; color: string }[]; title: string }) {
    const visible = data.filter((d) => d.value > 0);
    return (
        <div className="space-y-1 min-w-0 flex-1 max-w-[14rem] lg:flex-none lg:w-56">
            <p className="text-xs font-medium text-muted-foreground text-center">{title}</p>
            <div className="pointer-events-none">
                <ResponsiveContainer width="100%" height={170}>
                    <PieChart>
                        <Pie
                            data={visible}
                            cx="50%"
                            cy="50%"
                            innerRadius={46}
                            outerRadius={68}
                            paddingAngle={3}
                            dataKey="value"
                            nameKey="name"
                        >
                            {visible.map((entry) => (
                                <Cell key={entry.name} fill={entry.color} stroke="hsl(var(--card))" strokeWidth={2} tabIndex={-1} style={{ outline: 'none' }} />
                            ))}
                        </Pie>
                    </PieChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}

function MilestoneAccordionItem({
    milestone,
    achieved,
    defaultOpen,
}: {
    milestone: { id: number; target_value: number; target_date: string; notes: string | null };
    achieved: boolean;
    defaultOpen: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div>
            <button
                className="w-full flex items-center gap-3 px-4 py-3 hover:bg-muted/40 transition-colors text-left"
                onClick={() => setOpen((o) => !o)}
            >
                {achieved ? (
                    <CheckCircle2 className="w-4 h-4 text-green-500 flex-shrink-0" />
                ) : (
                    <Circle className="w-4 h-4 text-muted-foreground flex-shrink-0" />
                )}
                <span className={`flex-1 text-sm font-semibold ${achieved ? 'line-through text-muted-foreground' : ''}`}>
                    {formatCurrencyNoDecimals(milestone.target_value)}
                    <span className="ml-2 text-xs font-normal text-muted-foreground">{milestone.target_date.slice(0, 4)}</span>
                </span>
                {milestone.notes && (
                    open ? <ChevronDown className="w-3.5 h-3.5 text-muted-foreground flex-shrink-0" /> : <ChevronRight className="w-3.5 h-3.5 text-muted-foreground flex-shrink-0" />
                )}
            </button>
            {open && milestone.notes && (
                <div className="px-11 pb-3">
                    <p className="text-xs text-muted-foreground whitespace-pre-wrap">{milestone.notes}</p>
                </div>
            )}
        </div>
    );
}

// ─── Progress view ────────────────────────────────────────────────────────────

function GoalProgress({
    goal,
    categories,
    currentNetWorth,
    currentAllocation,
    currentMacroAllocation,
    onEdit,
    onDelete,
}: {
    goal: Goal;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    currentNetWorth: number | null;
    currentAllocation: CurrentAllocationItem[];
    currentMacroAllocation: CurrentMacroAllocationItem[];
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
        const currentPct = totalCurrent > 0 ? (currentVal / totalCurrent) * 100 : 0;
        const delta = currentPct - alloc.percentage;
        return { name: cat?.name ?? '?', color: cat?.color ?? '#94a3b8', currentPct, targetPct: alloc.percentage, delta };
    });

    const currentDonutCat = categoryDeviations.map((d) => ({ name: d.name, value: d.currentPct, color: d.color }));
    const targetDonutCat = categoryDeviations.map((d) => ({ name: d.name, value: d.targetPct, color: d.color }));

    // Allocation comparison — macro
    const totalCurrentMacro = currentMacroAllocation.reduce((s, a) => s + a.value, 0);
    const macroDeviations = goal.macroAllocations.map((alloc) => {
        const currentVal = currentMacroAllocation.find((a) => a.macro_category === alloc.macro_category)?.value ?? 0;
        const currentPct = totalCurrentMacro > 0 ? (currentVal / totalCurrentMacro) * 100 : 0;
        const delta = currentPct - (alloc.percentage);
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
    const today = new Date().toISOString().slice(0, 10);

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
                        <span className="font-medium">{formatCurrencyNoDecimals(current)}</span>
                        <span className="text-muted-foreground">{formatCurrencyNoDecimals(target)}</span>
                    </div>
                    <div className="w-full h-3 rounded-full bg-muted overflow-hidden">
                        <div
                            className="h-full rounded-full bg-primary transition-all"
                            style={{ width: `${progressPct}%` }}
                        />
                    </div>
                    <div className="flex justify-between text-xs text-muted-foreground">
                        <span>{progressPct.toFixed(1)}% raggiunto</span>
                        <span>{formatCurrencyNoDecimals(remaining)} mancanti</span>
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

                                {/* Deviation table */}
                                <div className="flex-1 self-center overflow-x-auto">
                                    <table className="w-full text-xs">
                                        <thead>
                                            <tr className="text-muted-foreground font-medium">
                                                <th className="text-left pb-2 font-medium">Categoria</th>
                                                <th className="text-right pb-2 font-medium px-4">Attuale</th>
                                                <th className="text-right pb-2 font-medium px-4">Target</th>
                                                <th className="text-right pb-2 font-medium pl-4">Delta</th>
                                            </tr>
                                        </thead>
                                        <tbody className="space-y-1">
                                            {deviations.map((d) => (
                                                <tr key={d.name}>
                                                    <td className="py-1">
                                                        <div className="flex items-center gap-2">
                                                            <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ backgroundColor: d.color }} />
                                                            <span className="font-medium text-sm">{d.name}</span>
                                                        </div>
                                                    </td>
                                                    <td className="text-right font-mono text-muted-foreground py-1 px-4">{formatPct(d.currentPct)}</td>
                                                    <td className="text-right font-mono text-muted-foreground py-1 px-4">{formatPct(d.targetPct)}</td>
                                                    <td className="text-right py-1 pl-4">
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
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>
            )}

        </div>
    );
}

// ─── Empty state ──────────────────────────────────────────────────────────────

function EmptyGoal({ onCreate }: { onCreate: () => void }) {
    return (
        <div className="flex flex-col items-center justify-center h-full gap-4 text-center p-8">
            <div className="rounded-full bg-muted p-6">
                <Target className="w-12 h-12 text-muted-foreground" />
            </div>
            <h2 className="text-xl font-semibold">Nessun obiettivo</h2>
            <p className="text-muted-foreground max-w-sm">
                Definisci il tuo obiettivo finanziario: valore target, composizione del portafoglio e milestone intermedie.
            </p>
            <Button onClick={onCreate}>
                <Plus className="w-4 h-4 mr-2" />
                Crea obiettivo
            </Button>
        </div>
    );
}

// ─── Page ─────────────────────────────────────────────────────────────────────

export default function GoalPage({ goal, categories, currentNetWorth, currentAllocation, currentMacroAllocation }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const { delete: destroy, processing: deleting } = useForm({});

    const handleDelete = () => {
        if (!goal) return;
        if (confirm(`Eliminare l'obiettivo "${goal.name}"?`)) {
            destroy(`/goal/${goal.id}`);
        }
    };

    return (
        <>
            {!goal && <Head title="Obiettivo" />}
            <div className="h-full">
                {goal ? (
                    <GoalProgress
                        goal={goal}
                        categories={categories}
                        currentNetWorth={currentNetWorth}
                        currentAllocation={currentAllocation}
                        currentMacroAllocation={currentMacroAllocation}
                        onEdit={() => setFormOpen(true)}
                        onDelete={handleDelete}
                    />
                ) : (
                    <EmptyGoal onCreate={() => setFormOpen(true)} />
                )}
            </div>

            <GoalFormDialog
                open={formOpen}
                onClose={() => setFormOpen(false)}
                categories={categories}
                goal={goal}
            />

            {deleting && (
                <div className="fixed inset-0 bg-background/50 flex items-center justify-center z-50">
                    <p className="text-sm text-muted-foreground">Eliminazione in corso…</p>
                </div>
            )}
        </>
    );
}

GoalPage.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
