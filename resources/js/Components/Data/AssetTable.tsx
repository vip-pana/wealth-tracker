import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Trash2, ChevronDown, ChevronRight, Landmark, CandlestickChart, AlertTriangle, ReceiptText } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/Components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { priceFreshness, bankFreshness, brokerFreshness } from '@/lib/metrics';
import { cn } from '@/lib/utils';
import TransactionsDialog from '@/Components/Data/TransactionsDialog';
import { Money } from '@/Components/ui/Money';
import type { Asset, AssetPriceInfo } from '@/types/models';

interface Props {
    assets: Asset[];
    onEdit: (asset: Asset) => void;
    prices: Record<string, AssetPriceInfo>;
}

function DeleteButton({ asset }: { asset: Asset }) {
    const [open, setOpen] = useState(false);
    const { delete: destroy, processing } = useForm({});

    return (
        <>
            <Button
                variant="ghost"
                size="icon"
                className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-accent"
                onClick={() => setOpen(true)}
            >
                <Trash2 className="w-4 h-4" />
            </Button>

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
                            onClick={() => destroy(`/assets/${asset.id}`, { onSuccess: () => setOpen(false) })}
                        >
                            Elimina
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

function CategoryGroup({ assets, onEdit, onViewTransactions, prices }: { assets: Asset[]; onEdit: (a: Asset) => void; onViewTransactions: (a: Asset) => void; prices: Record<string, AssetPriceInfo> }) {
    const [open, setOpen] = useState(true);
    const cat = assets[0].category;
    const total = assets.reduce((sum, a) => sum + a.value, 0);

    return (
        <>
            <TableRow
                className="cursor-pointer hover:bg-muted/50 bg-muted/20"
                onClick={() => setOpen((o) => !o)}
            >
                <TableCell colSpan={4} className="py-2 px-4">
                    <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                            {open ? <ChevronDown className="w-3.5 h-3.5 text-muted-foreground" /> : <ChevronRight className="w-3.5 h-3.5 text-muted-foreground" />}
                            <span
                                className="w-2 h-2 rounded-full shrink-0"
                                style={{ backgroundColor: cat.color }}
                            />
                            <span className="text-sm font-medium">
                                {cat.name}
                            </span>
                            <span className="text-xs text-muted-foreground">({assets.length})</span>
                        </div>
                        <Money value={total} className="text-sm font-mono font-semibold" />
                    </div>
                </TableCell>
            </TableRow>
            {open && assets.map((asset) => {
                const freshness = asset.ticker ? priceFreshness(prices[asset.ticker]?.fetched_at) : null;
                // Drive the bank badge off the live link state (same signal as the
                // edit-modal lock), and the freshness line off the last sync time.
                const bankSync = asset.bank_linked ? bankFreshness(asset.synced_at) : null;
                // A broker-synced holding (e.g. Scalable) surfaces its own freshness
                // so a stalled sync (expired session) shows as stale.
                const brokerSync = asset.sync_source === 'broker'
                    ? brokerFreshness(asset.synced_at)
                    : null;
                return (
                <TableRow key={asset.id}>
                    <TableCell className="pl-10">
                        <div>
                            <div className="flex items-center gap-2">
                                <p className="font-medium">{asset.name}</p>
                                {asset.ticker && (
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs font-mono',
                                            freshness?.stale
                                                ? 'bg-muted text-muted-foreground'
                                                : 'bg-blue-500/10 text-blue-400',
                                        )}
                                        title={freshness?.label}
                                    >
                                        <span
                                            className={cn(
                                                'w-1.5 h-1.5 rounded-full',
                                                freshness?.stale ? 'bg-muted-foreground' : 'bg-blue-400 animate-pulse',
                                            )}
                                        />
                                        {asset.ticker}
                                    </span>
                                )}
                                {bankSync && (
                                    <span
                                        className="inline-flex items-center gap-1 rounded-full bg-emerald-500/10 px-1.5 py-0.5 text-xs text-emerald-400"
                                        title="Saldo sincronizzato dal conto bancario collegato"
                                    >
                                        <Landmark className="w-3 h-3" aria-hidden />
                                        Banca
                                    </span>
                                )}
                                {brokerSync && (
                                    <span
                                        className={cn(
                                            'inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-xs',
                                            brokerSync.stale ? 'bg-amber-500/10 text-amber-500' : 'bg-indigo-500/10 text-indigo-400',
                                        )}
                                        title={brokerSync.stale
                                            ? 'Sincronizzazione Scalable ferma: il proxy potrebbe essere spento o la sessione scaduta'
                                            : 'Valore sincronizzato dal broker Scalable'}
                                    >
                                        {brokerSync.stale
                                            ? <AlertTriangle className="w-3 h-3" aria-hidden />
                                            : <CandlestickChart className="w-3 h-3" aria-hidden />}
                                        {brokerSync.stale ? 'Scalable · non aggiornato' : 'Scalable'}
                                    </span>
                                )}
                            </div>
                            {asset.ticker && asset.quantity !== null && (
                                <p className="text-xs text-muted-foreground">
                                    {asset.quantity} unità
                                    {asset.price !== null && (
                                        <> · <Money value={asset.price} />/unità</>
                                    )}
                                    {freshness && (
                                        <> · <span className={cn(freshness.stale && 'text-amber-500')}>{freshness.label}</span></>
                                    )}
                                    {asset.wallet_address && (
                                        <> · <span className="font-mono" title={asset.wallet_address}>{asset.wallet_address.slice(0, 8)}…{asset.wallet_address.slice(-6)}</span></>
                                    )}
                                </p>
                            )}
                            {bankSync && (
                                <p className="text-xs text-muted-foreground">
                                    Saldo da banca · <span className={cn(bankSync.stale && 'text-amber-500')}>{bankSync.label}</span>
                                </p>
                            )}
                            {brokerSync && (
                                <p className="text-xs text-muted-foreground">
                                    Valore da Scalable · <span className={cn(brokerSync.stale && 'text-amber-500')}>{brokerSync.label}</span>
                                    {brokerSync.stale && <span className="text-amber-500"> · sincronizzazione ferma</span>}
                                </p>
                            )}
                            {asset.notes && !asset.ticker && (
                                <p className="text-xs text-muted-foreground">{asset.notes}</p>
                            )}
                        </div>
                    </TableCell>
                    <TableCell className="text-right font-mono">
                        <Money value={asset.value} />
                    </TableCell>
                    <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
                            {asset.transaction_managed && (
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-accent"
                                    title="Vedi transazioni"
                                    onClick={() => onViewTransactions(asset)}
                                >
                                    <ReceiptText className="w-4 h-4" />
                                </Button>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-accent"
                                onClick={() => onEdit(asset)}
                            >
                                <Pencil className="w-4 h-4" />
                            </Button>
                            <DeleteButton asset={asset} />
                        </div>
                    </TableCell>
                </TableRow>
                );
            })}
        </>
    );
}

export default function AssetTable({ assets, onEdit, prices }: Props) {
    const [txAsset, setTxAsset] = useState<Asset | null>(null);

    if (assets.length === 0) {
        return (
            <div className="py-12 text-center text-muted-foreground text-sm">
                Nessun asset per questo mese. Aggiungine uno con il pulsante sopra.
            </div>
        );
    }

    const total = assets.reduce((sum, a) => sum + a.value, 0);

    // Group by category_id preserving sort order
    const groups = new Map<number, Asset[]>();
    for (const asset of assets) {
        if (!groups.has(asset.category_id)) groups.set(asset.category_id, []);
        groups.get(asset.category_id)!.push(asset);
    }

    return (
        <div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Asset</TableHead>
                        <TableHead className="text-right">Valore</TableHead>
                        <TableHead className="text-right w-20">Azioni</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {[...groups.entries()].map(([categoryId, groupAssets]) => (
                        <CategoryGroup key={categoryId} assets={groupAssets} onEdit={onEdit} onViewTransactions={setTxAsset} prices={prices} />
                    ))}
                </TableBody>
            </Table>

            <div className="flex justify-between items-center px-4 py-3 border-t border-border bg-muted/40 rounded-b-md">
                <span className="text-sm font-medium text-muted-foreground">Totale mese</span>
                <Money value={total} className="font-bold text-base font-mono" />
            </div>

            <TransactionsDialog asset={txAsset} onClose={() => setTxAsset(null)} />
        </div>
    );
}
