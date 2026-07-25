import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Label } from '@/Components/ui/label';
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
import { UserCog, Sparkles } from 'lucide-react';

export interface InvestorProfile {
    name: string | null;
    birth_date: string | null;
    horizon: string | null;
    risk_tolerance: string | null;
    notes: string | null;
    memory: string | null;
}

const HORIZON_LABELS: Record<string, string> = { short: 'Breve', medium: 'Medio', long: 'Lungo' };
const RISK_LABELS: Record<string, string> = { low: 'Bassa', medium: 'Media', high: 'Alta' };

export function ProfileDialog({
    open,
    onClose,
    profile,
    onDefineWithAi,
}: {
    open: boolean;
    onClose: () => void;
    profile: InvestorProfile | null;
    onDefineWithAi: () => void;
}) {
    const form = useForm({
        name: profile?.name ?? '',
        birth_date: profile?.birth_date ?? '',
        horizon: profile?.horizon ?? '',
        risk_tolerance: profile?.risk_tolerance ?? '',
        memory: profile?.memory ?? '',
    });

    // useForm seeds its data only once, so when the profile prop changes after a
    // save elsewhere (e.g. the AI's profile-proposal card applies an update via a
    // partial reload), re-sync the fields — otherwise the dialog keeps showing
    // the stale values it was first mounted with. Depend on the prop values, not
    // on setData, to avoid the effect re-running every render.
    const { setData } = form;
    useEffect(() => {
        setData({
            name: profile?.name ?? '',
            birth_date: profile?.birth_date ?? '',
            horizon: profile?.horizon ?? '',
            risk_tolerance: profile?.risk_tolerance ?? '',
            memory: profile?.memory ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [profile?.name, profile?.birth_date, profile?.horizon, profile?.risk_tolerance, profile?.memory]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/advisor/profile', { preserveScroll: true, onSuccess: onClose });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Il tuo profilo investitore</DialogTitle>
                    <DialogDescription className="sr-only">Imposta orizzonte e tolleranza al rischio per personalizzare l&apos;analisi del consulente.</DialogDescription>
                </DialogHeader>
                <p className="text-xs text-muted-foreground">
                    Questo contesto rende l&apos;analisi tua, non generica. L&apos;obiettivo e l&apos;allocazione target si definiscono nella sezione Obiettivo.
                </p>
                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label className="text-xs">Nome</Label>
                            <Input
                                type="text"
                                value={form.data.name}
                                onChange={(e) => form.setData('name', e.target.value)}
                                placeholder="es. Mario"
                            />
                        </div>
                        <div className="space-y-1">
                            <Label className="text-xs">Data di nascita</Label>
                            <Input
                                type="date"
                                value={form.data.birth_date}
                                onChange={(e) => form.setData('birth_date', e.target.value)}
                            />
                        </div>
                    </div>
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
                        <Label className="text-xs">Reddito netto mensile</Label>
                        <p className="text-xs text-muted-foreground">
                            Calcolato automaticamente dalle transazioni bancarie (media degli stipendi) nella sezione Entrate e Uscite. Non si imposta più a mano.
                        </p>
                    </div>
                    <div className="space-y-1">
                        <Label className="text-xs">Cose da ricordare</Label>
                        <textarea
                            value={form.data.memory}
                            onChange={(e) => form.setData('memory', e.target.value)}
                            placeholder="Preferenze e fatti durevoli che il consulente terrà a mente (es. preferisco ETF ad accumulo, non voglio obbligazioni)"
                            rows={2}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                    </div>
                    {profile?.notes && (
                        <div className="space-y-1">
                            <Label className="text-xs">Note sul profilo di rischio</Label>
                            <p className="rounded-md border border-input bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                                {profile.notes}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Sintesi scritta dal consulente AI durante l’intervista di profilazione.
                            </p>
                        </div>
                    )}
                    <DialogFooter className="sm:justify-between">
                        <Button type="button" variant="ghost" size="sm" className="gap-1.5" onClick={onDefineWithAi} disabled={form.processing}>
                            <Sparkles className="h-4 w-4" />
                            Definisci con l’AI
                        </Button>
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" onClick={onClose} disabled={form.processing}>
                                Annulla
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Salvataggio…' : 'Salva profilo'}
                            </Button>
                        </div>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

export function ProfileSummary({ profile, onEdit }: { profile: InvestorProfile | null; onEdit: () => void }) {
    const parts: string[] = [];
    if (profile?.name) parts.push(profile.name);
    if (profile?.horizon) parts.push(`Orizzonte: ${HORIZON_LABELS[profile.horizon] ?? profile.horizon}`);
    if (profile?.risk_tolerance) parts.push(`Rischio: ${RISK_LABELS[profile.risk_tolerance] ?? profile.risk_tolerance}`);

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
