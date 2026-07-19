import { useForm, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Sparkles } from 'lucide-react';
import { Money } from '@/Components/ui/Money';
import { OptionalHint } from '@/Components/ui/OptionalHint';
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

    const buildMilestones = (): MilestoneFormItem[] =>
        goal?.milestones.map((m) => ({
            notes: m.notes ?? '',
            action: m.action ?? '',
            rationale: m.rationale ?? '',
            target_value: String(m.target_value),
            target_date: m.target_date,
            allocation: (m.allocation ?? []).map((a) => ({
                category_id: String(a.category_id ?? ''),
                percentage: String(a.percentage),
            })),
        })) ?? [];

    const { data, setData, post, put, processing, errors, reset } = useForm<GoalFormData>({
        name: goal?.name ?? '',
        description: goal?.description ?? '',
        target_value: goal ? String(goal.target_value) : '',
        target_date: goal?.target_date ?? '',
        milestones: buildMilestones(),
    });

    useEffect(() => {
        if (open) {
            setData({
                name: goal?.name ?? '',
                description: goal?.description ?? '',
                target_value: goal ? String(goal.target_value) : '',
                target_date: goal?.target_date ?? '',
                milestones: buildMilestones(),
            });
        }
    // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, goal]);

    const updateMilestone = (idx: number, field: string, value: string) => {
        const updated = [...(data.milestones as MilestoneFormItem[])];
        updated[idx] = { ...updated[idx], [field]: value };
        setData('milestones', updated);
    };

    const updateMilestoneAllocation = (idx: number, allocation: AllocationFormItem[]) => {
        const updated = [...(data.milestones as MilestoneFormItem[])];
        updated[idx] = { ...updated[idx], allocation };
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
                    <DialogDescription className="sr-only">Definisci patrimonio obiettivo, allocazione target e milestone.</DialogDescription>
                </DialogHeader>
                {/* Single column: milestones now carry label, action, rationale
                    and their own allocation, so they need the full width — a
                    narrow side column would cramp them. Base fields stack on top,
                    milestones below. */}
                <form onSubmit={submit} className="flex flex-col gap-6">
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
                        <div className="flex gap-4 items-start">
                            <div className="space-y-1 flex-1">
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
                            <div className="space-y-1 flex-shrink-0">
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
                                    className="font-mono w-32"
                                />
                            </div>
                        </div>

                    </div>

                    {/* Milestones — each carries its own target allocation (the glide-path) */}
                    <div className="space-y-4">
                        <div className="rounded-md border border-border p-4">
                            <MilestonesSection
                                items={data.milestones as MilestoneFormItem[]}
                                categories={categories}
                                onAdd={() => setData('milestones', [...(data.milestones as MilestoneFormItem[]), { notes: '', action: '', rationale: '', target_value: '', target_date: '', allocation: [] }])}
                                onUpdate={updateMilestone}
                                onUpdateAllocation={updateMilestoneAllocation}
                                onRemove={(idx) => setData('milestones', (data.milestones as MilestoneFormItem[]).filter((_, i) => i !== idx))}
                            />
                        </div>
                    </div>

                    <DialogFooter className="sm:justify-between">
                        <Button asChild type="button" variant="ghost" size="sm" className="gap-1.5">
                            <Link href={`/advisor?ask=${encodeURIComponent(isEdit ? 'Aiutami a ridefinire il mio obiettivo e a rivedere le milestone.' : 'Aiutami a definire il mio obiettivo finanziario da zero.')}`}>
                                <Sparkles className="h-4 w-4" />
                                Ridefinisci con l’AI
                            </Link>
                        </Button>
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" onClick={onClose}>Annulla</Button>
                            <Button type="submit" disabled={processing}>
                                {isEdit ? 'Salva modifiche' : 'Crea obiettivo'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
