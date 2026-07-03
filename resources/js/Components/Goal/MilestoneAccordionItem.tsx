import { useState } from 'react';
import { CheckCircle2, Circle, ChevronDown, ChevronRight } from 'lucide-react';
import { Money } from '@/Components/ui/Money';

export function MilestoneAccordionItem({
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
                    <Money value={milestone.target_value} variant="no-decimals" />
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
