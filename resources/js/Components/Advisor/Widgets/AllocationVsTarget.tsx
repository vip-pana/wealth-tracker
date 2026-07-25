import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import type { AllocationVsTargetWidget } from '@/Components/Advisor/types';

/**
 * Current-vs-target allocation, one row per category with two bars (current and
 * target) and the deviation in percentage points. Makes "am I in line with my
 * plan?" readable at a glance. Percentages come from PHP.
 */
export function AllocationVsTarget({ data }: { data: AllocationVsTargetWidget['data'] }) {
    const max = Math.max(1, ...data.rows.flatMap((r) => [r.current_pct, r.target_pct]));

    return (
        <Card className="mt-3">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="text-sm">Allocazione: attuale vs obiettivo</CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3 space-y-3">
                {data.rows.map((row) => {
                    const delta = row.current_pct - row.target_pct;
                    return (
                        <div key={row.name} className="text-xs">
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">{row.name}</span>
                                <span className="font-mono">
                                    {row.current_pct.toFixed(1)}% / {row.target_pct.toFixed(1)}%
                                    <span
                                        className={`ml-1 ${
                                            Math.abs(delta) < 0.05
                                                ? 'text-muted-foreground'
                                                : delta > 0
                                                  ? 'text-amber-600 dark:text-amber-400'
                                                  : 'text-sky-600 dark:text-sky-400'
                                        }`}
                                    >
                                        ({delta >= 0 ? '+' : '−'}
                                        {Math.abs(delta).toFixed(1)})
                                    </span>
                                </span>
                            </div>
                            <div className="mt-1 space-y-0.5">
                                <Bar pct={row.current_pct} max={max} className="bg-primary" />
                                <Bar pct={row.target_pct} max={max} className="bg-muted-foreground/40" />
                            </div>
                        </div>
                    );
                })}
                <p className="text-xs text-muted-foreground">
                    Barra piena: attuale · barra chiara: obiettivo. Il numero fra parentesi è lo scostamento in punti.
                </p>
            </CardContent>
        </Card>
    );
}

function Bar({ pct, max, className }: { pct: number; max: number; className: string }) {
    return (
        <div className="h-2 w-full overflow-hidden rounded-full bg-muted">
            <div className={`h-full rounded-full ${className}`} style={{ width: `${Math.min(100, (pct / max) * 100)}%` }} />
        </div>
    );
}
