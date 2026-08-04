import { Money } from '@/Components/ui/Money';
import { DELTA_EPSILON } from '@/lib/metrics';
import { cn } from '@/lib/utils';

interface Props {
    /** A delta from monthDelta()/categoryDelta(); null when incomparable. */
    change: { delta: number; pct: number | null } | null;
    className?: string;
}

/**
 * A month-over-month change, rendered the same way everywhere it appears: the
 * asset row, the per-category cards, and the table footer all read from the
 * same numbers, so they must also look alike.
 *
 * Three states: no comparable previous value (a dash), an unchanged value
 * ("invariato" — information, not absence, so it is not coloured as a gain),
 * and a real move (signed, coloured, with the percentage when there is one).
 */
export function DeltaAmount({ change, className }: Props) {
    if (change === null) {
        return (
            <span className={cn('text-muted-foreground', className)} title="Nessun valore nel mese precedente">
                —
            </span>
        );
    }

    // Compared against half a cent rather than exact zero: a quantity-held
    // asset re-priced identically lands on a float a hair off zero, which would
    // otherwise render "+0,00 €".
    if (Math.abs(change.delta) < DELTA_EPSILON) {
        return <span className={cn('text-muted-foreground', className)}>invariato</span>;
    }

    return (
        <span className={cn(change.delta > 0 ? 'text-green-500' : 'text-red-500', className)}>
            {change.delta > 0 && '+'}
            <Money value={change.delta} />
            {change.pct !== null && (
                <span className="text-xs">
                    {' '}({change.pct > 0 && '+'}{change.pct.toFixed(1)}%)
                </span>
            )}
        </span>
    );
}
