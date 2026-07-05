import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { UserCog, Check } from 'lucide-react';
import type { ProfileProposalWidget } from '@/Components/Advisor/types';

const HORIZON_LABELS: Record<string, string> = { short: 'Breve (< 3 anni)', medium: 'Medio (3-10 anni)', long: 'Lungo (10+ anni)' };
const RISK_LABELS: Record<string, string> = { low: 'Bassa', medium: 'Media', high: 'Alta' };

/**
 * Confirmation card for an AI-proposed investor-profile change. The AI only
 * PROPOSES (via the propose_profile_update tool); nothing is written until the
 * user clicks Applica, which POSTs to the existing /advisor/profile endpoint
 * (same validation as the manual dialog). This keeps the "AI proposes, the user
 * applies" boundary — the write is a deliberate, reversible user action.
 */
export function ProfileProposal({ data }: { data: ProfileProposalWidget['data'] }) {
    const [state, setState] = useState<'idle' | 'saving' | 'applied' | 'dismissed'>('idle');

    const rows: { label: string; value: string }[] = [];
    if (data.horizon) rows.push({ label: 'Orizzonte', value: HORIZON_LABELS[data.horizon] ?? data.horizon });
    if (data.risk_tolerance) rows.push({ label: 'Tolleranza al rischio', value: RISK_LABELS[data.risk_tolerance] ?? data.risk_tolerance });
    if (data.objective) rows.push({ label: 'Obiettivo', value: data.objective });
    if (data.target_allocation) rows.push({ label: 'Allocazione target', value: data.target_allocation });

    if (rows.length === 0) return null;

    const apply = () => {
        setState('saving');
        router.post('/advisor/profile', { ...data }, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => setState('applied'),
            onError: () => setState('idle'),
        });
    };

    return (
        <Card className="mt-3">
            <CardHeader className="pb-1 pt-3 px-3">
                <CardTitle className="flex items-center gap-1.5 text-sm">
                    <UserCog className="h-4 w-4 text-primary" />
                    Proposta per il tuo profilo
                </CardTitle>
            </CardHeader>
            <CardContent className="px-3 pb-3 space-y-3">
                <dl className="space-y-1.5 text-xs">
                    {rows.map((row) => (
                        <div key={row.label} className="flex flex-col gap-0.5 sm:flex-row sm:justify-between sm:gap-2">
                            <dt className="text-muted-foreground">{row.label}</dt>
                            <dd className="font-medium sm:text-right">{row.value}</dd>
                        </div>
                    ))}
                </dl>

                {state === 'applied' ? (
                    <div className="flex items-center gap-1.5 text-xs font-medium text-emerald-600 dark:text-emerald-400">
                        <Check className="h-4 w-4" />
                        Profilo aggiornato.
                    </div>
                ) : state === 'dismissed' ? (
                    <p className="text-xs text-muted-foreground">Proposta annullata.</p>
                ) : (
                    <div className="flex gap-2">
                        <Button size="sm" onClick={apply} disabled={state === 'saving'}>
                            {state === 'saving' ? 'Salvataggio…' : 'Applica'}
                        </Button>
                        <Button size="sm" variant="ghost" onClick={() => setState('dismissed')} disabled={state === 'saving'}>
                            Annulla
                        </Button>
                    </div>
                )}
                <p className="text-xs text-muted-foreground">
                    Nulla viene salvato finché non premi «Applica». Puoi sempre modificarlo dal tuo profilo.
                </p>
            </CardContent>
        </Card>
    );
}
