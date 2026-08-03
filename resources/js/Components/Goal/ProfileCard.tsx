import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { UserCog, Pencil } from 'lucide-react';
import type { InvestorProfile } from '@/Components/Advisor/ProfileDialog';

const HORIZON_LABELS: Record<string, string> = { short: 'Breve (< 3 anni)', medium: 'Medio (3-10 anni)', long: 'Lungo (10+ anni)' };
const RISK_LABELS: Record<string, string> = { low: 'Bassa', medium: 'Media', high: 'Alta' };

/**
 * The investor profile shown beside the goal. The two describe one thing — who
 * the user is and where they're heading — so they live on the same page instead
 * of the profile hiding behind a button in the advisor. Editing opens the same
 * ProfileDialog the advisor used, so there is one form and one write path.
 *
 * The horizon is read-only here: it is derived from the goal's target date.
 */
export function ProfileCard({ profile, onEdit }: { profile: InvestorProfile | null; onEdit: () => void }) {
    const age = profile?.birth_date ? ageFrom(profile.birth_date) : null;

    const fields: { label: string; value: string | null }[] = [
        { label: 'Nome', value: profile?.name ?? null },
        { label: 'Età', value: age !== null ? `${age} anni` : null },
        { label: 'Orizzonte', value: profile?.horizon ? HORIZON_LABELS[profile.horizon] ?? profile.horizon : null },
        { label: 'Tolleranza al rischio', value: profile?.risk_tolerance ? RISK_LABELS[profile.risk_tolerance] ?? profile.risk_tolerance : null },
    ];

    const filled = fields.filter((f) => f.value !== null).length;

    return (
        <Card>
            {/* Same CardHeader/CardTitle shape as the goal card beside it, so the
                two headings match in size and baseline. */}
            <CardHeader className="pb-3 flex flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle className="text-base flex items-center gap-2 min-w-0">
                    <UserCog className="w-4 h-4 text-primary shrink-0" />
                    <span className="truncate">Il tuo profilo</span>
                </CardTitle>
                <Button variant="outline" size="sm" className="shrink-0" onClick={onEdit}>
                    <Pencil className="w-4 h-4 mr-1" />
                    {filled > 0 ? 'Modifica' : 'Compila'}
                </Button>
            </CardHeader>
            <CardContent className="space-y-3">
                {filled === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Profilo non compilato. Compilalo per rendere l&apos;analisi del consulente AI più mirata.
                    </p>
                ) : (
                    <div className="grid grid-cols-2 gap-4">
                        {fields.map((f) => (
                            <div key={f.label}>
                                <p className="text-xs text-muted-foreground mb-0.5">{f.label}</p>
                                <p className="text-sm font-medium">{f.value ?? '—'}</p>
                            </div>
                        ))}
                    </div>
                )}

                {profile?.notes && (
                    <div className="pt-2 border-t border-border">
                        <p className="text-xs text-muted-foreground mb-0.5">Note sul profilo di rischio</p>
                        <p className="text-xs text-muted-foreground whitespace-pre-wrap">{profile.notes}</p>
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

// Whole years elapsed since the birth date, mirroring the age the advisor
// context derives server-side so the two never disagree by a year.
function ageFrom(birthDate: string): number | null {
    const born = new Date(birthDate);
    if (Number.isNaN(born.getTime())) return null;

    const now = new Date();
    let age = now.getFullYear() - born.getFullYear();
    const monthDelta = now.getMonth() - born.getMonth();
    if (monthDelta < 0 || (monthDelta === 0 && now.getDate() < born.getDate())) age--;

    return age;
}
