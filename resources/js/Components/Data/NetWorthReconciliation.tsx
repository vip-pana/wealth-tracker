import { Money } from '@/Components/ui/Money';
import { formatMonthLong } from '@/lib/formatters';

export interface Reconciliation {
    total: number;
    currentMonthTotal: number;
    carriedForwardTotal: number;
    carriedForward: { category: string; value: number; asOf: string }[];
}

export function NetWorthReconciliation({ reconciliation }: { reconciliation: Reconciliation }) {
    if (reconciliation.carriedForward.length === 0) {
        return null;
    }

    return (
        <div className="rounded-md border border-border bg-muted/40 p-2 text-xs space-y-1">
            <p className="text-muted-foreground">
                Il patrimonio include valori non aggiornati questo mese, riportati dall&apos;ultima rilevazione:
            </p>
            <div className="flex items-center justify-between">
                <span className="text-muted-foreground">Valori del mese corrente</span>
                <Money value={reconciliation.currentMonthTotal} />
            </div>
            {reconciliation.carriedForward.map((item) => (
                <div key={item.category} className="flex items-center justify-between">
                    <span className="text-muted-foreground">
                        {item.category} · da {formatMonthLong(item.asOf)}
                    </span>
                    <Money value={item.value} />
                </div>
            ))}
            <div className="flex items-center justify-between border-t border-border pt-1 font-medium">
                <span>Totale patrimonio</span>
                <Money value={reconciliation.total} />
            </div>
        </div>
    );
}
