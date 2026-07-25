import { Label } from '@/Components/ui/label';
import { OptionalHint } from '@/Components/ui/OptionalHint';
import { Button } from '@/Components/ui/button';
import { Plus } from 'lucide-react';
import { MilestoneFormRow } from '@/Components/Goal/MilestoneFormRow';
import type { AllocationFormItem, MilestoneFormItem } from '@/Components/Goal/types';
import type { Category } from '@/types/models';

export function MilestonesSection({
    items,
    categories,
    onAdd,
    onUpdate,
    onUpdateAllocation,
    onRemove,
}: {
    items: MilestoneFormItem[];
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    onAdd: () => void;
    onUpdate: (idx: number, field: string, value: string) => void;
    onUpdateAllocation: (idx: number, allocation: AllocationFormItem[]) => void;
    onRemove: (idx: number) => void;
}) {
    return (
        <div className="space-y-2">
            <Label>Milestone intermedie <OptionalHint /></Label>
            <div className="space-y-0">
                {items.map((item, idx) => (
                    <MilestoneFormRow
                        key={idx}
                        item={item}
                        idx={idx}
                        isLast={idx === items.length - 1}
                        categories={categories}
                        onUpdate={onUpdate}
                        onUpdateAllocation={onUpdateAllocation}
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
