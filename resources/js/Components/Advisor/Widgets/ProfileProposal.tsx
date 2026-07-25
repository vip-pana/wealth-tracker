import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { UserCog, Check } from 'lucide-react';
import type { ProfileProposalWidget } from '@/Components/Advisor/types';
import { type InvestorProfile } from '@/Components/Advisor/ProfileDialog';

const HORIZON_LABELS: Record<string, string> = { short: 'Breve (< 3 anni)', medium: 'Medio (3-10 anni)', long: 'Lungo (10+ anni)' };
const RISK_LABELS: Record<string, string> = { low: 'Bassa', medium: 'Media', high: 'Alta' };

/**
 * Is every proposed field already the current profile value? If so the proposal
 * was applied before (its local state is gone after a refresh), so we show it as
 * already applied instead of a fresh, clickable card. Only the fields the AI
 * proposed are compared — an unrelated field on the profile doesn't matter.
 */
function alreadyApplied(data: ProfileProposalWidget['data'], profile: InvestorProfile | null | undefined): boolean {
    if (!profile) return false;
    const keys = Object.keys(data) as (keyof ProfileProposalWidget['data'])[];
    if (keys.length === 0) return false;

    return keys.every((k) => (data[k] ?? null) === (profile[k] ?? null));
}

/**
 * Confirmation card for an AI-proposed investor-profile change. The AI only
 * PROPOSES (via the propose_profile_update tool); nothing is written until the
 * user clicks Applica, which POSTs to the existing /advisor/profile endpoint
 * (same validation as the manual dialog). This keeps the "AI proposes, the user
 * applies" boundary — the write is a deliberate, reversible user action.
 *
 * The applied/dismissed state is local (useState), so a page refresh loses it.
 * To avoid re-offering a proposal the user already applied, we compare the
 * proposed values against the current profile at mount: an exact match means it
 * was applied, so we render the applied state and hide the button.
 */
export function ProfileProposal({ data, profile }: { data: ProfileProposalWidget['data']; profile?: InvestorProfile | null }) {
    const [state, setState] = useState<'idle' | 'saving' | 'applied' | 'dismissed'>(
        alreadyApplied(data, profile) ? 'applied' : 'idle',
    );

    const rows: { label: string; value: string }[] = [];
    if (data.name) rows.push({ label: 'Nome', value: data.name });
    if (data.birth_date) rows.push({ label: 'Data di nascita', value: data.birth_date });
    if (data.horizon) rows.push({ label: 'Orizzonte', value: HORIZON_LABELS[data.horizon] ?? data.horizon });
    if (data.risk_tolerance) rows.push({ label: 'Tolleranza al rischio', value: RISK_LABELS[data.risk_tolerance] ?? data.risk_tolerance });
    if (data.notes) rows.push({ label: 'Note', value: data.notes });
    if (data.memory) rows.push({ label: 'Da ricordare', value: data.memory });

    if (rows.length === 0) return null;

    const apply = () => {
        setState('saving');
        // preserveState keeps the open chat (and this card's local state) from
        // remounting; only: ['profile'] still refreshes the profile prop so the
        // profile dialog reflects the change immediately after the write.
        router.post('/advisor/profile', { ...data }, {
            preserveScroll: true,
            preserveState: true,
            only: ['profile'],
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
                {(state === 'idle' || state === 'saving') && (
                    <p className="text-xs text-muted-foreground">
                        Nulla viene salvato finché non premi «Applica». Puoi sempre modificarlo dal tuo profilo.
                    </p>
                )}
            </CardContent>
        </Card>
    );
}
