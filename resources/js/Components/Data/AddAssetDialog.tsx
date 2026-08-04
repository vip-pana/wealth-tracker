import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Money } from '@/Components/ui/Money';
import { formatMonthLong } from '@/lib/formatters';
import { PencilLine, Copy, ChevronLeft } from 'lucide-react';

export interface CopyableAsset {
    id: number;
    name: string;
    category_id: number;
    category: string;
    color: string;
    value: number;
}

interface Props {
    open: boolean;
    onClose: () => void;
    /** Opens the manual asset form instead of copying. */
    onManual: () => void;
    month: string;
    /** The month the copyable assets come from; null on the first tracked month. */
    previousMonth: string | null;
    /** Assets held last month that have no row in this one. */
    copyableAssets: CopyableAsset[];
}

/** Null while the user is still choosing how to add. */
type Mode = 'copy' | null;

/** One row of the vertical "how do you want to add?" list. */
function ActionChoice({ icon: Icon, title, description, onClick, disabled }: {
    icon: React.ElementType;
    title: string;
    description: string;
    onClick: () => void;
    disabled?: boolean;
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className="flex w-full items-start gap-3 rounded-lg border p-3 text-left transition-colors hover:bg-muted/50 disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent"
        >
            <Icon className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" />
            <span className="min-w-0">
                <span className="block text-sm font-medium">{title}</span>
                <span className="block text-xs text-muted-foreground">{description}</span>
            </span>
        </button>
    );
}

/**
 * Entry point for adding assets: either fill the form by hand, or carry forward
 * assets the previous month had and this one is missing.
 *
 * Copying is only offered when there is something to copy — on the first
 * tracked month, or once every asset has been carried over, the dialog is a
 * plain "add by hand" prompt rather than a mode picker with a dead end.
 */
export default function AddAssetDialog({ open, onClose, onManual, month, previousMonth, copyableAssets }: Props) {
    const canCopy = previousMonth !== null && copyableAssets.length > 0;
    const [mode, setMode] = useState<Mode>(null);
    const { data, setData, post, processing } = useForm<{ source_date: string; asset_ids: number[] }>({
        source_date: previousMonth ?? '',
        asset_ids: [],
    });
    const selected = data.asset_ids;

    // Closing drops back to the choice list, so reopening never lands mid-flow
    // on a copy step with a stale selection.
    const close = () => {
        setMode(null);
        setData('asset_ids', []);
        onClose();
    };

    const toggle = (id: number) => {
        setData('asset_ids', selected.includes(id) ? selected.filter((i) => i !== id) : [...selected, id]);
    };

    const allSelected = selected.length === copyableAssets.length && copyableAssets.length > 0;

    const handleCopy = () => {
        if (previousMonth === null || selected.length === 0) return;

        post(`/assets/copy-from-month?month=${month}`, {
            preserveScroll: true,
            onSuccess: close,
        });
    };

    const startManual = () => {
        close();
        onManual();
    };

    return (
        <Dialog open={open} onOpenChange={(next) => { if (!next) close(); }}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Aggiungi asset a {formatMonthLong(month)}</DialogTitle>
                    <DialogDescription className="sr-only">
                        Scegli se inserire un asset a mano o copiarne uno dal mese precedente.
                    </DialogDescription>
                </DialogHeader>

                {mode === null ? (
                    <div className="space-y-2">
                        <ActionChoice
                            icon={PencilLine}
                            title="Inserisci a mano"
                            description="Compila nome, categoria e valore di un nuovo asset."
                            onClick={startManual}
                        />
                        <ActionChoice
                            icon={Copy}
                            title={previousMonth !== null ? `Copia da ${formatMonthLong(previousMonth)}` : 'Copia dal mese precedente'}
                            description={
                                previousMonth === null
                                    ? 'Questo è il primo mese tracciato: non c’è nulla da copiare.'
                                    : canCopy
                                        ? `${copyableAssets.length} ${copyableAssets.length === 1 ? 'asset non è ancora' : 'asset non sono ancora'} in ${formatMonthLong(month)}.`
                                        : `Tutti gli asset di ${formatMonthLong(previousMonth)} sono già in questo mese.`
                            }
                            disabled={!canCopy}
                            onClick={() => setMode('copy')}
                        />
                    </div>
                ) : (
                    <>
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm text-muted-foreground">
                                Non ancora in {formatMonthLong(month)}:
                            </p>
                            {copyableAssets.length > 1 && (
                                <button
                                    type="button"
                                    className="text-xs text-primary hover:underline"
                                    onClick={() => setData('asset_ids', allSelected ? [] : copyableAssets.map((a) => a.id))}
                                >
                                    {allSelected ? 'Deseleziona tutti' : 'Seleziona tutti'}
                                </button>
                            )}
                        </div>

                        <div className="max-h-64 space-y-1 overflow-y-auto">
                            {copyableAssets.map((asset) => (
                                <label
                                    key={asset.id}
                                    className="flex cursor-pointer items-center gap-2 rounded-md border p-2 hover:bg-muted/50"
                                >
                                    <input
                                        type="checkbox"
                                        checked={selected.includes(asset.id)}
                                        onChange={() => toggle(asset.id)}
                                        className="h-4 w-4 shrink-0"
                                    />
                                    <span
                                        className="h-2 w-2 shrink-0 rounded-full"
                                        style={{ backgroundColor: asset.color }}
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span className="block truncate text-sm font-medium">{asset.name}</span>
                                        <span className="block text-xs text-muted-foreground">{asset.category}</span>
                                    </span>
                                    <span className="shrink-0 font-mono text-sm">
                                        <Money value={asset.value} />
                                    </span>
                                </label>
                            ))}
                        </div>

                        <p className="text-xs text-muted-foreground">
                            Valori e quantità vengono copiati dal mese precedente. Correggili dalla
                            tabella se sono cambiati.
                        </p>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setMode(null)} disabled={processing}>
                                <ChevronLeft className="w-4 h-4 mr-1" />
                                Indietro
                            </Button>
                            <Button onClick={handleCopy} disabled={selected.length === 0 || processing}>
                                <Copy className="w-4 h-4 mr-1" />
                                {processing
                                    ? 'Copia in corso…'
                                    : `Copia ${selected.length > 0 ? selected.length : ''}`.trim()}
                            </Button>
                        </DialogFooter>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}
