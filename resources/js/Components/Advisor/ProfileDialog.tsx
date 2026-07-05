import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
import { OptionalHint } from '@/Components/ui/OptionalHint';
import { Input } from '@/Components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/Components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { UserCog } from 'lucide-react';

export interface InvestorProfile {
    horizon: string | null;
    risk_tolerance: string | null;
    objective: string | null;
    target_allocation: string | null;
}

const HORIZON_LABELS: Record<string, string> = { short: 'Breve', medium: 'Medio', long: 'Lungo' };
const RISK_LABELS: Record<string, string> = { low: 'Bassa', medium: 'Media', high: 'Alta' };

export function ProfileDialog({
    open,
    onClose,
    profile,
    goalObjective,
}: {
    open: boolean;
    onClose: () => void;
    profile: InvestorProfile | null;
    goalObjective: string | null;
}) {
    const form = useForm({
        horizon: profile?.horizon ?? '',
        risk_tolerance: profile?.risk_tolerance ?? '',
        objective: profile?.objective ?? '',
        target_allocation: profile?.target_allocation ?? '',
    });

    // useForm seeds its data only once, so when the profile prop changes after a
    // save elsewhere (e.g. the AI's profile-proposal card applies an update via a
    // partial reload), re-sync the fields — otherwise the dialog keeps showing
    // the stale values it was first mounted with. Depend on the prop values, not
    // on setData, to avoid the effect re-running every render.
    const { setData } = form;
    useEffect(() => {
        setData({
            horizon: profile?.horizon ?? '',
            risk_tolerance: profile?.risk_tolerance ?? '',
            objective: profile?.objective ?? '',
            target_allocation: profile?.target_allocation ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [profile?.horizon, profile?.risk_tolerance, profile?.objective, profile?.target_allocation]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/advisor/profile', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Il tuo profilo investitore</DialogTitle>
                    <DialogDescription className="sr-only">Imposta orizzonte, tolleranza al rischio e obiettivo per personalizzare l&apos;analisi del consulente.</DialogDescription>
                </DialogHeader>
                <p className="text-xs text-muted-foreground">
                    Questo contesto rende l&apos;analisi tua, non generica. Obiettivo e allocazione sono opzionali: se vuoti, il consulente usa quelli della sezione Obiettivo.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label className="text-xs">Orizzonte temporale</Label>
                            <Select value={form.data.horizon} onValueChange={(v) => form.setData('horizon', v)}>
                                <SelectTrigger><SelectValue placeholder="Seleziona" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="short">Breve (&lt; 3 anni)</SelectItem>
                                    <SelectItem value="medium">Medio (3-10 anni)</SelectItem>
                                    <SelectItem value="long">Lungo (10+ anni)</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">Tolleranza al rischio</Label>
                            <Select value={form.data.risk_tolerance} onValueChange={(v) => form.setData('risk_tolerance', v)}>
                                <SelectTrigger><SelectValue placeholder="Seleziona" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="low">Bassa</SelectItem>
                                    <SelectItem value="medium">Media</SelectItem>
                                    <SelectItem value="high">Alta</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Obiettivo principale <OptionalHint /></Label>
                        <Input
                            value={form.data.objective}
                            onChange={(e) => form.setData('objective', e.target.value)}
                            placeholder={goalObjective ? `Da Obiettivo: ${goalObjective}` : 'es. indipendenza finanziaria, pensione, casa'}
                        />
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Allocazione target <OptionalHint /></Label>
                        <textarea
                            value={form.data.target_allocation}
                            onChange={(e) => form.setData('target_allocation', e.target.value)}
                            placeholder="Se vuoto, usa le percentuali della sezione Obiettivo. Es: 60% azioni, 20% obbligazioni, 20% liquidità"
                            rows={2}
                            className="flex w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                        />
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose} disabled={form.processing}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Salvataggio…' : 'Salva profilo'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ProfileSummary({ profile, onEdit }: { profile: InvestorProfile | null; onEdit: () => void }) {
    const parts: string[] = [];
    if (profile?.horizon) parts.push(`Orizzonte: ${HORIZON_LABELS[profile.horizon] ?? profile.horizon}`);
    if (profile?.risk_tolerance) parts.push(`Rischio: ${RISK_LABELS[profile.risk_tolerance] ?? profile.risk_tolerance}`);
    if (profile?.objective) parts.push(`Obiettivo: ${profile.objective}`);

    return (
        <div className="flex items-center justify-between gap-3 rounded-md border border-border bg-muted/30 px-3 py-2">
            <div className="flex items-center gap-2 min-w-0 text-xs text-muted-foreground">
                <UserCog className="w-3.5 h-3.5 flex-shrink-0" />
                <span className="truncate">
                    {parts.length > 0 ? parts.join(' · ') : 'Profilo non compilato — l’analisi sarà più mirata se lo imposti.'}
                </span>
            </div>
            <Button variant="ghost" size="sm" className="flex-shrink-0 h-7 text-xs" onClick={onEdit}>
                {parts.length > 0 ? 'Modifica' : 'Compila'}
            </Button>
        </div>
    );
}
