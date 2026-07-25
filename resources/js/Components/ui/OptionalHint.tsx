import { cn } from '@/lib/utils';

/**
 * The muted "(opzionale)" marker shown inline next to a field label. A single
 * component so the wording and styling stay consistent across every form.
 * Pass `note` for a qualified variant, e.g. `note="%"` → "(opzionale, %)".
 */
export function OptionalHint({ note, className }: { note?: string; className?: string }) {
    return (
        <span className={cn('text-muted-foreground font-normal', className)}>
            ({note ? `opzionale, ${note}` : 'opzionale'})
        </span>
    );
}
