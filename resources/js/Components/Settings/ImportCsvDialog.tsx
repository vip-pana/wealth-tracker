import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/Components/ui/dialog';

export function ImportCsvDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({ file: null });

    const handleClose = () => {
        reset();
        onClose();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/import/csv', { forceFormData: true, onSuccess: () => { reset(); onClose(); } });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Importa da CSV</DialogTitle>
                    <DialogDescription className="sr-only">Carica un file CSV per importare asset in blocco.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Carica un file CSV nel formato richiesto.{' '}
                        <a href="/import/csv/template" download className="underline hover:text-foreground">
                            Scarica template
                        </a>{' '}
                        per vedere il formato corretto.
                    </p>
                    <div className="space-y-1">
                        <Label>File CSV</Label>
                        <Input
                            type="file"
                            accept=".csv,text/csv"
                            className="text-sm"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                        />
                        {errors.file && <p className="text-xs text-destructive">{errors.file}</p>}
                    </div>
                    <div className="rounded-md border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground space-y-1">
                        <p className="font-medium text-foreground">Formato atteso (separatore <code>;</code>):</p>
                        <p><code>data;categoria;nome_asset;valore;note</code></p>
                        <p><code>2026-01-01;ETF;Gold SGLD;1243.00;</code></p>
                        <p>Se un asset con stessa data e nome esiste già, viene aggiornato.</p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={handleClose}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={processing || !data.file}>
                            {processing ? 'Importando...' : 'Importa'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
