import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { PieChart, Pie, Cell, Tooltip, ResponsiveContainer } from 'recharts';
import { Target, Pencil, Trash2, Plus, CheckCircle2, Circle, CalendarClock, TrendingUp, ChevronDown, ChevronRight } from 'lucide-react';
import { formatCurrency, formatCurrencyNoDecimals } from '@/lib/formatters';
import type { Category } from '@/types/models';
import type { Goal } from '@/types/models';

const MACRO_CATEGORIES = ['Liquidità', 'ETF', 'Cripto'] as const;
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
    macro_allocations: AllocationFormItem[];
    milestones: MilestoneFormItem[];
};

// ─── Helpers ──────────────────────────────────────────────────────────────────

function allocationSum(items: AllocationFormItem[]): number {
    return items.reduce((s, i) => s + (parseFloat(i.percentage) || 0), 0);
}

function formatDate(dateStr: string): string {
    return new Date(dateStr + 'T00:00:00').toLocaleDateString('it-IT', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });
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
    const sumOk = items.length === 0 || Math.abs(sum - 100) < 0.01;

    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label>{title}</Label>
                {items.length > 0 && (
                    <span className={`text-xs font-mono ${sumOk ? 'text-green-500' : 'text-destructive'}`}>
                        {sum.toFixed(1)}% / 100%
                    </span>
                )}
            </div>
            {items.map((item, idx) => (
                <div key={idx} className="flex gap-2 items-center">
                    <div className="flex-1">{renderSelect(item, idx)}</div>
                    <Input
                        type="number"
                        min={0}
                        max={100}
                        step={0.1}
                        value={item.percentage}
                        onChange={(e) => onUpdate(idx, 'percentage', e.target.value)}
                        className="w-24 text-right font-mono"
                        placeholder="%"
                    />
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
            ))}
            <Button type="button" variant="outline" size="sm" onClick={onAdd} className="w-full">
                <Plus className="w-3.5 h-3.5 mr-1" />
                Aggiungi
            </Button>
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
            {items.map((item, idx) => (
                <div key={idx} className="rounded-md border border-border p-3 space-y-3">
                    <div className="flex items-start justify-between gap-2">
                        <div className="grid grid-cols-2 gap-3 flex-1">
                            <div className="space-y-1">
                                <Label className="text-xs text-muted-foreground">Valore</Label>
                                <Input
                                    type="number"
                                    min={0}
                                    step="any"
                                    value={item.target_value}
                                    onChange={(e) => onUpdate(idx, 'target_value', e.target.value)}
                                    placeholder="es. 50000"
                                    className="font-mono"
                                />
                                {item.target_value && !isNaN(parseFloat(item.target_value)) && (
                                    <p className="text-xs text-muted-foreground font-medium">
                                        {formatCurrency(parseFloat(item.target_value))}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1">
                                <Label className="text-xs text-muted-foreground">Anno</Label>
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
                                    placeholder="es. 2030"
                                    className="font-mono"
                                />
                            </div>
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-8 w-8 mt-6 text-muted-foreground hover:text-destructive flex-shrink-0"
                            onClick={() => onRemove(idx)}
                        >
                            <Trash2 className="w-4 h-4" />
                        </Button>
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs text-muted-foreground">Note (opzionale)</Label>
                        <textarea
                            value={item.notes}
                            onChange={(e) => onUpdate(idx, 'notes', e.target.value)}
                            placeholder="Strategia, azioni da intraprendere, considerazioni..."
                            rows={2}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                    </div>
                </div>
            ))}
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
        macro_allocations: goal?.macroAllocations.map((a) => ({
            macro_category: a.macro_category ?? '',
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
                macro_allocations: goal?.macroAllocations.map((a) => ({
                    macro_category: a.macro_category ?? '',
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

    const updateMacroAlloc = (idx: number, field: string, value: string) => {
        const updated = [...(data.macro_allocations as AllocationFormItem[])];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('macro_allocations', updated);
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
                            <Label>Valore target (€)</Label>
                            <Input
                                type="number"
                                min={0}
                                step={1000}
                                value={data.target_value}
                                onChange={(e) => setData('target_value', e.target.value)}
                                placeholder="es. 500000"
                                className="font-mono"
                            />
                            {data.target_value && !isNaN(parseFloat(data.target_value)) && (
                                <p className="text-xs text-muted-foreground font-medium">
                                    {formatCurrency(parseFloat(data.target_value))}
                                </p>
                            )}
                            {errors.target_value && <p className="text-xs text-destructive">{errors.target_value}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Anno target (opzionale)</Label>
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
                    <AllocationSection
                        title="Target allocation per categoria"
                        items={data.category_allocations as AllocationFormItem[]}
                        onAdd={() => setData('category_allocations', [...(data.category_allocations as AllocationFormItem[]), { category_id: '', percentage: '' }])}
                        onUpdate={updateCategoryAlloc}
                        onRemove={(idx) => setData('category_allocations', (data.category_allocations as AllocationFormItem[]).filter((_, i) => i !== idx))}
                        renderSelect={(item, idx) => (
                            <select
                                value={(item as AllocationFormItem).category_id ?? ''}
                                onChange={(e) => updateCategoryAlloc(idx, 'category_id', e.target.value)}
                                className="w-full h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Seleziona categoria</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                        )}
                    />

                    {/* Macro allocations */}
                    <AllocationSection
                        title="Target allocation per macro-categoria"
                        items={data.macro_allocations as AllocationFormItem[]}
                        onAdd={() => setData('macro_allocations', [...(data.macro_allocations as AllocationFormItem[]), { macro_category: '', percentage: '' }])}
                        onUpdate={updateMacroAlloc}
                        onRemove={(idx) => setData('macro_allocations', (data.macro_allocations as AllocationFormItem[]).filter((_, i) => i !== idx))}
                        renderSelect={(item, idx) => (
                            <select
                                value={(item as AllocationFormItem).macro_category ?? ''}
                                onChange={(e) => updateMacroAlloc(idx, 'macro_category', e.target.value)}
                                className="w-full h-9 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Seleziona macro</option>
                                {MACRO_CATEGORIES.map((mc) => (
                                    <option key={mc} value={mc}>{mc}</option>
                                ))}
                            </select>
                        )}
                    />

                    {/* Milestones */}
                    <MilestonesSection
                        items={data.milestones as MilestoneFormItem[]}
                        onAdd={() => setData('milestones', [...(data.milestones as MilestoneFormItem[]), { notes: '', target_value: '', target_date: '' }])}
                        onUpdate={updateMilestone}
                        onRemove={(idx) => setData('milestones', (data.milestones as MilestoneFormItem[]).filter((_, i) => i !== idx))}
                    />

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
    return (
        <div className="space-y-1 w-56">
            <p className="text-xs font-medium text-muted-foreground text-center">{title}</p>
            <ResponsiveContainer width="100%" height={170}>
                <PieChart>
                    <Pie
                        data={data}
                        cx="50%"
                        cy="50%"
                        innerRadius={46}
                        outerRadius={68}
                        paddingAngle={3}
                        dataKey="value"
                        nameKey="name"
                    >
                        {data.map((entry) => (
                            <Cell key={entry.name} fill={entry.color} />
                        ))}
                    </Pie>
                    <Tooltip
                        formatter={(v) => [`${(v as number).toFixed(1)}%`]}
                        contentStyle={{
                            fontSize: 12,
                            backgroundColor: 'hsl(var(--card))',
                            borderColor: 'hsl(var(--border))',
                            color: 'hsl(var(--card-foreground))',
                        }}
                    />
                </PieChart>
            </ResponsiveContainer>
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
        <div className="p-4 space-y-4">
            <Head title={`Obiettivo — ${goal.name}`} />

            {/* Header */}
            <div className="flex items-start justify-between">
                <div>
                    <div className="flex items-center gap-2">
                        <Target className="w-5 h-5 text-primary" />
                        <h1 className="text-lg font-bold">{goal.name}</h1>
                    </div>
                    {goal.description && (
                        <p className="text-sm text-muted-foreground mt-1 whitespace-pre-wrap">{goal.description}</p>
                    )}
                    {goal.target_date && (
                        <p className="text-xs text-muted-foreground mt-1 flex items-center gap-1">
                            <CalendarClock className="w-3.5 h-3.5" />
                            Anno target: {goal.target_date.slice(0, 4)}
                            {months !== null && months > 0 && (
                                <span className="ml-1">({months} mesi rimanenti)</span>
                            )}
                        </p>
                    )}
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" size="sm" onClick={onEdit}>
                        <Pencil className="w-4 h-4 mr-1" />
                        Modifica
                    </Button>
                    <Button variant="ghost" size="sm" className="text-muted-foreground hover:text-destructive" onClick={onDelete}>
                        <Trash2 className="w-4 h-4" />
                    </Button>
                </div>
            </div>

            {/* Progress + Milestones side by side */}
            <div className="grid grid-cols-1 lg:grid-cols-2 gap-4">
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
                        <div className="pt-2 border-t border-border grid grid-cols-2 gap-4">
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
                            <div className="flex items-center rounded-lg border border-border overflow-hidden text-xs">
                                {goal.categoryAllocations.length > 0 && (
                                    <button
                                        onClick={() => setAllocTab('category')}
                                        className={`px-3 py-1.5 ${allocTab === 'category' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                                    >
                                        Categorie
                                    </button>
                                )}
                                {goal.macroAllocations.length > 0 && (
                                    <button
                                        onClick={() => setAllocTab('macro')}
                                        className={`px-3 py-1.5 ${allocTab === 'macro' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                                    >
                                        Macro
                                    </button>
                                )}
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-3">
                        {currentNetWorth === null ? (
                            <p className="text-sm text-muted-foreground text-center py-4">
                                Nessuno snapshot disponibile. Crea uno snapshot per vedere il confronto.
                            </p>
                        ) : (
                            <div className="flex gap-6">
                                {/* Donuts */}
                                <div className="flex gap-10 flex-shrink-0">
                                    <SmallDonut data={currentDonut} title="Attuale" />
                                    <SmallDonut data={targetDonut} title="Target" />
                                </div>

                                {/* Deviation table */}
                                <div className="flex-1 self-center">
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
                                                    <td className="text-right font-mono text-muted-foreground py-1 px-4">{d.currentPct.toFixed(1)}%</td>
                                                    <td className="text-right font-mono text-muted-foreground py-1 px-4">{d.targetPct.toFixed(1)}%</td>
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
                                                            {d.delta > 0 ? '+' : ''}{d.delta.toFixed(1)}%
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
