import { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
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
        if (open) {
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
        }
    }, [open, editAsset, month, setData]);

    const hasLiveTicker = data.ticker.trim() !== '';
    const hasWalletAddress = data.wallet_address.trim() !== '';
    const currentPrice = hasLiveTicker ? prices[data.ticker.trim().toUpperCase()] ?? prices[data.ticker.trim()] : null;
    const computedValue =
        currentPrice && data.quantity && !isNaN(parseFloat(data.quantity))
            ? parseFloat(data.quantity) * currentPrice.price
            : null;

    const handleClose = () => {
        reset();
        onClose();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();

        const opts = {
            onSuccess: () => {
                reset();
                onClose();
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
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Modifica Asset' : 'Aggiungi Asset'}</DialogTitle>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-4">
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

                    {/* Ticker + Quantity */}
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label>
                                Ticker{' '}
                                <span className="text-muted-foreground font-normal">(opzionale)</span>
                            </Label>
                            <Input
                                value={data.ticker}
                                onChange={(e) => setData('ticker', e.target.value.toUpperCase())}
                                placeholder="es. BTC, SWDA.MI"
                            />
                            <p className="text-xs text-muted-foreground">
                                Per crypto usa il simbolo (es. <strong>BTC</strong>). Per ETF/azioni usa il simbolo Yahoo Finance (es. <strong>SWDA.MI</strong>, <strong>IUSQ.DE</strong>).
                            </p>
                            {errors.ticker && (
                                <p className="text-xs text-destructive">{errors.ticker}</p>
                            )}
                        </div>
                        <div className="space-y-1">
                            <Label>Quantità</Label>
                            <Input
                                type="text"
                                inputMode="decimal"
                                value={data.quantity}
                                onChange={(e) => setData('quantity', e.target.value)}
                                placeholder="es. 0.5"
                                disabled={!hasLiveTicker || hasWalletAddress}
                                readOnly={hasWalletAddress}
                                title={hasWalletAddress ? 'Aggiornata automaticamente dal wallet' : undefined}
                            />
                            {errors.quantity && (
                                <p className="text-xs text-destructive">{errors.quantity}</p>
                            )}
                        </div>
                    </div>

                    {/* Wallet address */}
                    {hasLiveTicker && (
                        <div className="space-y-1">
                            <Label>
                                Indirizzo wallet{' '}
                                <span className="text-muted-foreground font-normal">(opzionale)</span>
                            </Label>
                            <Input
                                value={data.wallet_address}
                                onChange={(e) => setData('wallet_address', e.target.value)}
                                placeholder="es. bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh"
                                className="font-mono text-xs"
                            />
                            {hasWalletAddress && (
                                <p className="text-xs text-muted-foreground">
                                    La quantità verrà aggiornata automaticamente dal saldo on-chain ad ogni fetch.
                                </p>
                            )}
                            {errors.wallet_address && (
                                <p className="text-xs text-destructive">{errors.wallet_address}</p>
                            )}
                        </div>
                    )}

                    {/* Live price info OR manual value */}
                    {hasLiveTicker ? (
                        <div className="rounded-md border border-border bg-muted/40 px-3 py-2 space-y-1 text-sm">
                            {currentPrice ? (
                                <>
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Prezzo corrente</span>
                                        <span className="font-mono">{formatCurrency(currentPrice.price)}</span>
                                    </div>
                                    {computedValue !== null && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Valore calcolato</span>
                                            <span className="font-mono font-semibold">{formatCurrency(computedValue)}</span>
                                        </div>
                                    )}
                                    <p className="text-xs text-muted-foreground">
                                        Aggiornato: {new Date(currentPrice.fetched_at).toLocaleString('it-IT')}
                                    </p>
                                </>
                            ) : (
                                <p className="text-muted-foreground text-xs">
                                    Nessun prezzo disponibile per <strong>{data.ticker}</strong>.
                                    Aggiorna i prezzi dalle impostazioni o attendi il fetch giornaliero.
                                </p>
                            )}
                        </div>
                    ) : (
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
