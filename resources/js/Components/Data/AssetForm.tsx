import { useEffect, useState } from 'react';
import { router, useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/Components/ui/dialog';
import { formatCurrency } from '@/lib/formatters';
import type { Asset, AssetPriceInfo, Category } from '@/types/models';

type Mode = 'manual' | 'ticker';

interface Props {
    open: boolean;
    onClose: () => void;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'icon'>[];
    month: string;
    editAsset?: Asset | null;
    prices: Record<string, AssetPriceInfo>;
}

export default function AssetForm({ open, onClose, categories, month, editAsset, prices }: Props) {
    const isEdit = !!editAsset;

    const initialMode = (): Mode =>
        editAsset?.ticker ? 'ticker' : 'manual';

    const [mode, setMode] = useState<Mode>(initialMode);
    const [showWallet, setShowWallet] = useState(!!editAsset?.wallet_address);

    const { data, setData, post, put, processing, errors, reset } = useForm({
        category_id:    editAsset?.category_id?.toString() ?? '',
        name:           editAsset?.name ?? '',
        ticker:         editAsset?.ticker ?? '',
        wallet_address: editAsset?.wallet_address ?? '',
        quantity:       editAsset?.quantity?.toString() ?? '',
        value:          editAsset?.value?.toString() ?? '',
        date:           editAsset?.date ?? month,
        notes:          editAsset?.notes ?? '',
    });

    useEffect(() => {
        if (!open) return;
        setData({
            category_id:    editAsset?.category_id?.toString() ?? '',
            name:           editAsset?.name ?? '',
            ticker:         editAsset?.ticker ?? '',
            wallet_address: editAsset?.wallet_address ?? '',
            quantity:       editAsset?.quantity?.toString() ?? '',
            value:          editAsset?.value?.toString() ?? '',
            date:           editAsset?.date ?? month,
            notes:          editAsset?.notes ?? '',
        });
        setMode(editAsset?.ticker ? 'ticker' : 'manual');
        setShowWallet(!!editAsset?.wallet_address);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, editAsset?.id, month]);

    const switchMode = (m: Mode) => {
        setMode(m);
        if (m === 'manual') {
            setData('ticker', '');
            setData('wallet_address', '');
            setData('quantity', '');
            setShowWallet(false);
        } else {
            setData('value', '');
        }
    };

    const currentPrice = data.ticker.trim() !== ''
        ? prices[data.ticker.trim().toUpperCase()] ?? prices[data.ticker.trim()]
        : null;
    const computedValue =
        currentPrice && currentPrice.price !== null && data.quantity && !isNaN(parseFloat(data.quantity))
            ? parseFloat(data.quantity) * currentPrice.price
            : null;

    const handleClose = () => {
        reset();
        onClose();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const hasTicker = data.ticker.trim() !== '';
        const opts = {
            onSuccess: () => {
                reset();
                onClose();
                if (hasTicker) {
                    router.post('/prices/refresh', {}, { preserveScroll: true });
                }
            },
        };
        if (isEdit) {
            put(`/assets/${editAsset!.id}`, opts);
        } else {
            post('/assets', opts);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Modifica Asset' : 'Aggiungi Asset'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
                    {/* Mode segmented control */}
                    <div className="flex items-center rounded-lg border border-border overflow-hidden text-sm">
                        <button
                            type="button"
                            onClick={() => switchMode('manual')}
                            className={`flex-1 py-1.5 text-center transition-colors ${mode === 'manual' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Valore manuale
                        </button>
                        <button
                            type="button"
                            onClick={() => switchMode('ticker')}
                            className={`flex-1 py-1.5 text-center transition-colors ${mode === 'ticker' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                        >
                            Ticker + quantità
                        </button>
                    </div>

                    {/* Category */}
                    <div className="space-y-1">
                        <Label>Categoria</Label>
                        <Select
                            value={data.category_id}
                            onValueChange={(v) => setData('category_id', v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Seleziona categoria" />
                            </SelectTrigger>
                            <SelectContent>
                                {categories.map((cat) => (
                                    <SelectItem key={cat.id} value={cat.id.toString()}>
                                        <span className="flex items-center gap-2">
                                            {cat.icon && <span>{cat.icon}</span>}
                                            {cat.name}
                                        </span>
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {errors.category_id && (
                            <p className="text-xs text-destructive">{errors.category_id}</p>
                        )}
                    </div>

                    {/* Name */}
                    <div className="space-y-1">
                        <Label>Nome asset</Label>
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="es. Conto ING, Bitcoin, VWCE"
                        />
                        {errors.name && (
                            <p className="text-xs text-destructive">{errors.name}</p>
                        )}
                    </div>

                    {mode === 'manual' ? (
                        /* Manual: value only */
                        <div className="space-y-1">
                            <Label>Valore (€)</Label>
                            <Input
                                type="text"
                                inputMode="decimal"
                                value={data.value}
                                onChange={(e) => setData('value', e.target.value)}
                                placeholder="0.00"
                            />
                            {errors.value && (
                                <p className="text-xs text-destructive">{errors.value}</p>
                            )}
                        </div>
                    ) : (
                        /* Ticker mode */
                        <>
                            <div className={`grid gap-3 ${showWallet ? '' : 'grid-cols-2'}`}>
                                <div className="space-y-1">
                                    <Label>Ticker</Label>
                                    <Input
                                        value={data.ticker}
                                        onChange={(e) => setData('ticker', e.target.value.toUpperCase())}
                                        placeholder="es. BTC, SWDA.MI"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        Crypto: simbolo (es. <strong>BTC</strong>). ETF: Yahoo Finance (es. <strong>SWDA.MI</strong>).
                                    </p>
                                    {errors.ticker && (
                                        <p className="text-xs text-destructive">{errors.ticker}</p>
                                    )}
                                </div>
                                {!showWallet && (
                                    <div className="space-y-1">
                                        <Label>Quantità</Label>
                                        <Input
                                            type="text"
                                            inputMode="decimal"
                                            value={data.quantity}
                                            onChange={(e) => setData('quantity', e.target.value)}
                                            placeholder="es. 0.5"
                                        />
                                        {errors.quantity && (
                                            <p className="text-xs text-destructive">{errors.quantity}</p>
                                        )}
                                    </div>
                                )}
                            </div>

                            <label className="flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    type="checkbox"
                                    checked={showWallet}
                                    onChange={(e) => {
                                        setShowWallet(e.target.checked);
                                        if (!e.target.checked) {
                                            setData('wallet_address', '');
                                        } else {
                                            setData('quantity', '');
                                        }
                                    }}
                                    className="rounded border-border"
                                />
                                <span className="text-sm">Traccia da indirizzo wallet on-chain</span>
                            </label>

                            {showWallet && (
                                <div className="space-y-1">
                                    <Input
                                        value={data.wallet_address}
                                        onChange={(e) => setData('wallet_address', e.target.value)}
                                        placeholder="es. bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh"
                                        className="font-mono text-xs"
                                    />
                                    <p className="text-xs text-muted-foreground">
                                        La quantità viene aggiornata automaticamente dal saldo on-chain ad ogni fetch dei prezzi. La quantità manuale viene ignorata.
                                    </p>
                                    {errors.wallet_address && (
                                        <p className="text-xs text-destructive">{errors.wallet_address}</p>
                                    )}
                                </div>
                            )}

                            {/* Live price box */}
                            <div className="rounded-md border border-border bg-muted/40 px-3 py-2 space-y-1 text-sm">
                                {currentPrice ? (
                                    <>
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Prezzo corrente</span>
                                            <span className="font-mono">
                                                {currentPrice.price !== null ? formatCurrency(currentPrice.price) : 'non disponibile'}
                                            </span>
                                        </div>
                                        {computedValue !== null && (
                                            <div className="flex justify-between">
                                                <span className="text-muted-foreground">Valore calcolato</span>
                                                <span className="font-mono font-semibold">{formatCurrency(computedValue)}</span>
                                            </div>
                                        )}
                                        {currentPrice.fetched_at && (
                                            <p className="text-xs text-muted-foreground">
                                                Aggiornato: {new Date(currentPrice.fetched_at).toLocaleString('it-IT')}
                                            </p>
                                        )}
                                    </>
                                ) : (
                                    <p className="text-muted-foreground text-xs">
                                        {data.ticker.trim()
                                            ? <>Nessun prezzo disponibile per <strong>{data.ticker}</strong>. Aggiorna i prezzi dalle impostazioni.</>
                                            : 'Inserisci un ticker per vedere il prezzo live.'}
                                    </p>
                                )}
                            </div>
                        </>
                    )}

                    {/* Notes */}
                    <div className="space-y-1">
                        <Label>
                            Note{' '}
                            <span className="text-muted-foreground font-normal">(opzionale)</span>
                        </Label>
                        <Input
                            value={data.notes ?? ''}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder="Note facoltative..."
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={handleClose}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Salvando...' : isEdit ? 'Salva modifiche' : 'Aggiungi'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
