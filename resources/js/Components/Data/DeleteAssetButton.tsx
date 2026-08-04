import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Landmark, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import type { Asset } from '@/types/models';

interface Props {
    asset: Asset;
    /**
     * 'icon' is the bare trash button used in a table row; 'button' is the
     * labelled variant used inside the edit dialog, where an unlabelled icon
     * next to Save/Cancel would read as ambiguous.
     */
    variant?: 'icon' | 'button';
    /** Called once the deletion succeeds — e.g. to close the surrounding dialog. */
    onDeleted?: () => void;
}

/**
 * Deletes an asset from the current month, behind a confirmation dialog that
 * spells out the scope (this month only) and warns when the asset is bank-linked
 * and will simply be recreated by the next sync.
 */
export default function DeleteAssetButton({ asset, variant = 'icon', onDeleted }: Props) {
    const [open, setOpen] = useState(false);
    const { delete: destroy, processing } = useForm({});

    return (
        <>
            {variant === 'icon' ? (
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-accent"
                    onClick={() => setOpen(true)}
                >
                    <Trash2 className="w-4 h-4" />
                </Button>
            ) : (
                <Button
                    type="button"
                    variant="ghost"
                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                    onClick={() => setOpen(true)}
                >
                    <Trash2 className="w-4 h-4 mr-1" />
                    Elimina
                </Button>
            )}

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Elimina asset</DialogTitle>
                        <DialogDescription className="sr-only">Conferma la rimozione di questo asset dal mese corrente.</DialogDescription>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Stai per rimuovere <span className="font-medium text-foreground">{asset.name}</span> dal mese corrente. Gli altri mesi non vengono modificati.
                    </p>
                    {asset.bank_linked && (
                        <p className="flex items-start gap-1.5 text-sm text-amber-500">
                            <Landmark className="w-4 h-4 shrink-0 mt-0.5" />
                            <span>Questo asset è collegato a un conto bancario: verrà ricreato al prossimo aggiornamento dei saldi. Per rimuoverlo davvero, scollega prima il conto in Impostazioni → Conti bancari.</span>
                        </p>
                    )}
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setOpen(false)} disabled={processing}>
                            Annulla
                        </Button>
                        <Button
                            variant="destructive"
                            disabled={processing}
                            onClick={() => destroy(`/assets/${asset.id}`, {
                                onSuccess: () => {
                                    setOpen(false);
                                    onDeleted?.();
                                },
                            })}
                        >
                            Elimina
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
