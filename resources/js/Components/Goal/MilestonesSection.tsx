import { Label } from '@/Components/ui/label';
import { OptionalHint } from '@/Components/ui/OptionalHint';
import { Button } from '@/Components/ui/button';
import { Plus } from 'lucide-react';
import { MilestoneFormRow } from '@/Components/Goal/MilestoneFormRow';
import type { MilestoneFormItem } from '@/Components/Goal/types';

export function MilestonesSection({
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
            <Label>Milestone intermedie <OptionalHint /></Label>
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
