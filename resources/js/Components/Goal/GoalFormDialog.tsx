import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { ChevronDown } from 'lucide-react';
import { Money } from '@/Components/ui/Money';
import { OptionalHint } from '@/Components/ui/OptionalHint';
import { AllocationSection } from '@/Components/Goal/AllocationSection';
import { MilestonesSection } from '@/Components/Goal/MilestonesSection';
import type { AllocationFormItem, MilestoneFormItem, GoalFormData } from '@/Components/Goal/types';
import type { Category } from '@/types/models';
import type { Goal } from '@/types/models';

export function GoalFormDialog({
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
            <DialogContent className="sm:max-w-5xl max-h-[90vh] overflow-y-auto">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Modifica obiettivo' : 'Crea il tuo obiettivo'}</DialogTitle>
                    <DialogDescription className="sr-only">Definisci patrimonio obiettivo, allocazione target e milestone.</DialogDescription>
                </DialogHeader>
                {/* Two columns on wide screens: the base fields on the left, the
                    longer allocation and milestone lists on the right, so the
                    dialog reads across rather than scrolling down. Collapses to a
                    single column on small screens. */}
                <form onSubmit={submit} className="grid grid-cols-1 lg:grid-cols-2 gap-6 items-start">
                    {/* Base info */}
                    <div className="space-y-4">
                        <div className="space-y-1">
                            <Label>Nome obiettivo</Label>
                            <Input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="es. FIRE, Pensione, Prima casa"
                            />
                            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Descrizione <OptionalHint /></Label>
                            <textarea
                                value={data.description}
                                onChange={(e) => setData('description', e.target.value)}
                                placeholder="Breve descrizione del tuo obiettivo"
                                rows={3}
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div className="grid grid-cols-2 gap-4">
                            <div className="space-y-1">
                                <Label>Patrimonio obiettivo</Label>
                                <div className="relative">
                                    <Input
                                        type="text"
                                        inputMode="decimal"
                                        value={data.target_value}
                                        onChange={(e) => setData('target_value', e.target.value)}
                                        placeholder="es. 500000"
                                        className="font-mono pr-24"
                                    />
                                    <span className="absolute right-3 top-1/2 -translate-y-1/2 text-sm text-muted-foreground font-mono pointer-events-none">
                                        {data.target_value && !isNaN(parseFloat(data.target_value))
                                            ? <Money value={parseFloat(data.target_value)} variant="no-decimals" />
                                            : ''}
                                    </span>
                                </div>
                                {errors.target_value && <p className="text-xs text-destructive">{errors.target_value}</p>}
                            </div>
                            <div className="space-y-1">
                                <Label className="whitespace-nowrap">Anno target <OptionalHint /></Label>
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
                    </div>

                    {/* Milestones */}
                    <div className="space-y-4">
                        <div className="rounded-md border border-border p-4">
                            <MilestonesSection
                                items={data.milestones as MilestoneFormItem[]}
                                onAdd={() => setData('milestones', [...(data.milestones as MilestoneFormItem[]), { notes: '', target_value: '', target_date: '' }])}
                                onUpdate={updateMilestone}
                                onRemove={(idx) => setData('milestones', (data.milestones as MilestoneFormItem[]).filter((_, i) => i !== idx))}
                            />
                        </div>
                    </div>

                    <DialogFooter className="lg:col-span-2">
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
