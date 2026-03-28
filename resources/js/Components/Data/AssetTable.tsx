import { useState } from 'react';
import { useForm } from '@inertiajs/react';
import { Pencil, Trash2, ChevronDown, ChevronRight } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
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
import { formatCurrency } from '@/lib/formatters';
import type { Asset } from '@/types/models';

interface Props {
    assets: Asset[];
    onEdit: (asset: Asset) => void;
}

function DeleteButton({ asset }: { asset: Asset }) {
    const [open, setOpen] = useState(false);
    const { delete: destroy, processing } = useForm({});

    return (
        <>
            <Button
                variant="ghost"
                size="icon"
                className="h-8 w-8 text-muted-foreground hover:text-destructive"
                onClick={() => setOpen(true)}
            >
                <Trash2 className="w-4 h-4" />
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="sm:max-w-sm">
                    <DialogHeader>
                        <DialogTitle>Elimina asset</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Stai per rimuovere <span className="font-medium text-foreground">{asset.name}</span> dal mese corrente. Gli altri mesi non vengono modificati.
                    </p>
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

function CategoryGroup({ assets, onEdit }: { assets: Asset[]; onEdit: (a: Asset) => void }) {
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
                                className="w-2 h-2 rounded-full flex-shrink-0"
                                style={{ backgroundColor: cat.color }}
                            />
                            <span className="text-sm font-medium">
                                {cat.icon && <span className="mr-1">{cat.icon}</span>}
                                {cat.name}
                            </span>
                            <span className="text-xs text-muted-foreground">({assets.length})</span>
                        </div>
                        <span className="text-sm font-mono font-semibold">{formatCurrency(total)}</span>
                    </div>
                </TableCell>
            </TableRow>
            {open && assets.map((asset) => (
                <TableRow key={asset.id}>
                    <TableCell className="pl-10">
                        <div>
                            <div className="flex items-center gap-2">
                                <p className="font-medium">{asset.name}</p>
                                {asset.ticker && (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-blue-500/10 px-1.5 py-0.5 text-xs font-mono text-blue-400">
                                        <span className="w-1.5 h-1.5 rounded-full bg-blue-400 animate-pulse" />
                                        {asset.ticker}
                                    </span>
                                )}
                            </div>
                            {asset.ticker && asset.quantity !== null && (
                                <p className="text-xs text-muted-foreground">
                                    {asset.quantity} unità
                                    {asset.price !== null && (
                                        <> · {formatCurrency(asset.price)}/unità</>
                                    )}
                                    {asset.wallet_address && (
                                        <> · <span className="font-mono" title={asset.wallet_address}>{asset.wallet_address.slice(0, 8)}…{asset.wallet_address.slice(-6)}</span></>
                                    )}
                                </p>
                            )}
                            {asset.notes && !asset.ticker && (
                                <p className="text-xs text-muted-foreground">{asset.notes}</p>
                            )}
                        </div>
                    </TableCell>
                    <TableCell className="text-right font-mono">
                        {formatCurrency(asset.value)}
                    </TableCell>
                    <TableCell className="text-right">
                        <div className="flex justify-end gap-1">
                            <Button
                                variant="ghost"
                                size="icon"
                                className="h-8 w-8 text-muted-foreground hover:text-foreground"
                                onClick={() => onEdit(asset)}
                            >
                                <Pencil className="w-4 h-4" />
                            </Button>
                            <DeleteButton asset={asset} />
                        </div>
                    </TableCell>
                </TableRow>
            ))}
        </>
    );
}

export default function AssetTable({ assets, onEdit }: Props) {
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
                        <CategoryGroup key={categoryId} assets={groupAssets} onEdit={onEdit} />
                    ))}
                </TableBody>
            </Table>

            <div className="flex justify-between items-center px-4 py-3 border-t border-border bg-muted/40 rounded-b-md">
                <span className="text-sm font-medium text-muted-foreground">Totale mese</span>
                <span className="font-bold text-base font-mono">{formatCurrency(total)}</span>
            </div>
        </div>
    );
}
