import { useForm, Link } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Sparkles, Trash2 } from 'lucide-react';
import { formatCurrencyNoDecimals } from '@/lib/formatters';
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
    onDelete,
}: {
    open: boolean;
    onClose: () => void;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    goal: Goal | null;
    // Deleting the goal lives here rather than on the page: it's an edit of the
    // goal, and it keeps a destructive action out of one-click reach. Only
    // offered when editing an existing goal.
    onDelete?: () => void;
}) {
    const isEdit = goal !== null;

    // The year fields hold a bare 4-digit year in form state (so the input
    // stays freely editable); a stored AAAA-MM-GG date is reduced to its year on
    // load and expanded back to a full date on submit.
    const toYear = (date: string | null | undefined) => (date ?? '').slice(0, 4);

    const buildMilestones = (): MilestoneFormItem[] =>
        goal?.milestones.map((m) => ({
            notes: m.notes ?? '',
            action: m.action ?? '',
            rationale: m.rationale ?? '',
            target_value: String(m.target_value),
            target_date: toYear(m.target_date),
            allocation: (m.allocation ?? []).map((a) => ({
                category_id: String(a.category_id ?? ''),
                percentage: String(a.percentage),
                cap_amount: a.cap_amount != null ? String(a.cap_amount) : '',
            })),
        })) ?? [];

    const { data, setData, post, put, processing, errors, reset, transform } = useForm<GoalFormData>({
        name: goal?.name ?? '',
        description: goal?.description ?? '',
        target_value: goal ? String(goal.target_value) : '',
        target_date: toYear(goal?.target_date),
        milestones: buildMilestones(),
    });

    useEffect(() => {
        if (open) {
            setData({
                name: goal?.name ?? '',
                description: goal?.description ?? '',
                target_value: goal ? String(goal.target_value) : '',
                target_date: toYear(goal?.target_date),
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
        // The year fields hold a bare 4-digit year while editing (so the input
        // stays freely editable); expand them to a full AAAA-01-01 date, which
        // is what the backend validates and stores.
        const toDate = (year: string) => /^\d{4}$/.test(year) ? `${year}-01-01` : '';
        transform((d) => ({
            ...d,
            target_date: toDate(d.target_date),
            milestones: d.milestones.map((m) => ({ ...m, target_date: toDate(m.target_date) })),
        }));
        const opts = { onSuccess: () => { reset(); onClose(); } };
        if (isEdit) {
            put(`/goal/${goal.id}`, opts);
        } else {
            post('/goal', opts);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-3xl max-h-[90vh] overflow-y-auto">
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
                                className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-1 focus-visible:ring-ring"
                            />
                        </div>
                        <div className="flex flex-col gap-4 sm:flex-row sm:items-start">
                            <div className="space-y-1 flex-1">
                                <Label>Patrimonio obiettivo</Label>
                                <Input
                                    type="text"
                                    inputMode="numeric"
                                    value={data.target_value ? formatCurrencyNoDecimals(parseInt(data.target_value, 10)) : ''}
                                    onChange={(e) => setData('target_value', e.target.value.replace(/\D/g, ''))}
                                    placeholder="es. 500.000 €"
                                    className="font-mono"
                                />
                                {errors.target_value && <p className="text-xs text-destructive">{errors.target_value}</p>}
                            </div>
                            <div className="space-y-1 shrink-0">
                                <Label className="whitespace-nowrap">Anno target <OptionalHint /></Label>
                                <Input
                                    type="text"
                                    inputMode="numeric"
                                    maxLength={4}
                                    value={(data.target_date ?? '').slice(0, 4)}
                                    onChange={(e) => setData('target_date', e.target.value.replace(/\D/g, '').slice(0, 4))}
                                    placeholder="es. 2045"
                                    className="font-mono w-full sm:w-40"
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
                        <div className="flex items-center gap-1">
                            <Button asChild type="button" variant="ghost" size="sm" className="gap-1.5">
                                <Link href={`/advisor?ask=${encodeURIComponent(isEdit ? 'Aiutami a ridefinire il mio obiettivo e a rivedere le milestone.' : 'Aiutami a definire il mio obiettivo finanziario da zero.')}`}>
                                    <Sparkles className="h-4 w-4" />
                                    Ridefinisci con l’AI
                                </Link>
                            </Button>
                            {isEdit && onDelete && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="gap-1.5 text-muted-foreground hover:text-destructive"
                                    onClick={onDelete}
                                >
                                    <Trash2 className="h-4 w-4" />
                                    Elimina
                                </Button>
                            )}
                        </div>
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
