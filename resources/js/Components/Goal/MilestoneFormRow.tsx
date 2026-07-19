import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { ChevronDown, Trash2 } from 'lucide-react';
import { formatCurrencyNoDecimals } from '@/lib/formatters';
import { AllocationSection } from '@/Components/Goal/AllocationSection';
import type { AllocationFormItem, MilestoneFormItem } from '@/Components/Goal/types';
import type { Category } from '@/types/models';

export function MilestoneFormRow({
    item,
    idx,
    isLast,
    categories,
    onUpdate,
    onUpdateAllocation,
    onRemove,
}: {
    item: MilestoneFormItem;
    idx: number;
    isLast: boolean;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    onUpdate: (idx: number, field: string, value: string) => void;
    onUpdateAllocation: (idx: number, allocation: AllocationFormItem[]) => void;
    onRemove: (idx: number) => void;
}) {
    const updateAlloc = (aIdx: number, field: string, value: string) => {
        const updated = item.allocation.map((a, i) => (i === aIdx ? { ...a, [field]: value } : a));
        onUpdateAllocation(idx, updated);
    };

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
                <div className="flex flex-col sm:flex-row gap-2 sm:items-end">
                    <div className="space-y-0.5 flex-1">
                        <label className="text-xs text-muted-foreground">Valore</label>
                        <Input
                            type="text"
                            inputMode="numeric"
                            value={item.target_value ? formatCurrencyNoDecimals(parseInt(item.target_value, 10)) : ''}
                            onChange={(e) => onUpdate(idx, 'target_value', e.target.value.replace(/\D/g, ''))}
                            placeholder="es. 50.000 €"
                            className="font-mono text-sm"
                        />
                    </div>
                    <div className="flex gap-2 items-end">
                        <div className="space-y-0.5 flex-1 sm:flex-none sm:w-28">
                            <label className="text-xs text-muted-foreground">Anno</label>
                            <Input
                                type="text"
                                inputMode="numeric"
                                maxLength={4}
                                value={(item.target_date ?? '').slice(0, 4)}
                                onChange={(e) => onUpdate(idx, 'target_date', e.target.value.replace(/\D/g, '').slice(0, 4))}
                                placeholder="es. 2045"
                                className="font-mono text-sm w-full"
                            />
                        </div>
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="h-9 w-9 text-muted-foreground hover:text-destructive flex-shrink-0"
                            onClick={() => onRemove(idx)}
                        >
                            <Trash2 className="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                {/* Label, action, rationale — each with an explicit heading so
                    they don't blur together. Label is the short name; action is
                    the "what to do here"; rationale is the "why". */}
                <div className="space-y-0.5">
                    <label className="text-xs text-muted-foreground">Etichetta</label>
                    <textarea
                        value={item.notes}
                        onChange={(e) => onUpdate(idx, 'notes', e.target.value)}
                        placeholder="Etichetta breve, es. Metà percorso"
                        rows={1}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </div>
                <div className="space-y-0.5">
                    <label className="text-xs text-muted-foreground">Azione</label>
                    <textarea
                        value={item.action}
                        onChange={(e) => onUpdate(idx, 'action', e.target.value)}
                        placeholder="Cosa fare una volta raggiunta questa tappa"
                        rows={3}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </div>
                <div className="space-y-0.5">
                    <label className="text-xs text-muted-foreground">Perché</label>
                    <textarea
                        value={item.rationale}
                        onChange={(e) => onUpdate(idx, 'rationale', e.target.value)}
                        placeholder="Il motivo di questa azione"
                        rows={3}
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                </div>

                {/* Per-milestone target allocation (the glide-path step) */}
                <div className="rounded-md border border-border/60 p-2.5">
                    <AllocationSection
                        title="Allocazione target a questa tappa"
                        items={item.allocation}
                        onAdd={() => onUpdateAllocation(idx, [...item.allocation, { category_id: '', percentage: '' }])}
                        onUpdate={updateAlloc}
                        onRemove={(aIdx) => onUpdateAllocation(idx, item.allocation.filter((_, i) => i !== aIdx))}
                        renderSelect={(alloc, aIdx) => (
                            <div className="relative">
                                <select
                                    value={(alloc as AllocationFormItem).category_id ?? ''}
                                    onChange={(e) => updateAlloc(aIdx, 'category_id', e.target.value)}
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
        </div>
    );
}
