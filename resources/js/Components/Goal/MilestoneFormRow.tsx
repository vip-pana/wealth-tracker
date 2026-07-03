import { useState } from 'react';
import { Input } from '@/Components/ui/input';
import { Button } from '@/Components/ui/button';
import { Trash2 } from 'lucide-react';
import { Money } from '@/Components/ui/Money';
import type { MilestoneFormItem } from '@/Components/Goal/types';

export function MilestoneFormRow({
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
                            <Money
                                value={parseFloat(item.target_value)}
                                variant="no-decimals"
                                className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-xs text-muted-foreground font-mono"
                            />
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
