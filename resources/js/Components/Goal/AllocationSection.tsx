import { Label } from '@/Components/ui/label';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Trash2, Plus } from 'lucide-react';
import { allocationSum, formatPct } from '@/lib/goalMath';
import { formatCurrencyNoDecimals } from '@/lib/formatters';
import type { AllocationFormItem } from '@/Components/Goal/types';

export function AllocationSection({
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
                                    type="text"
                                    inputMode="decimal"
                                    value={item.percentage}
                                    onChange={(e) => onUpdate(idx, 'percentage', e.target.value.replace(/[^\d.]/g, ''))}
                                    className="text-right font-mono pr-6"
                                    placeholder="0"
                                />
                                <span className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-sm text-muted-foreground">%</span>
                            </div>
                            <div className="relative w-32">
                                {/* Optional absolute cap: this category stops tracking its
                                    percentage once it would exceed this amount, and the
                                    excess is redistributed to the uncapped categories.
                                    Same formatted numeric input as the milestone target
                                    value (thousands separators) so the amount is readable. */}
                                <Input
                                    type="text"
                                    inputMode="numeric"
                                    value={item.cap_amount ? formatCurrencyNoDecimals(parseInt(item.cap_amount, 10)) : ''}
                                    onChange={(e) => onUpdate(idx, 'cap_amount', e.target.value.replace(/\D/g, ''))}
                                    className="text-right font-mono"
                                    placeholder="tetto"
                                    aria-label="Tetto massimo (facoltativo)"
                                />
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
