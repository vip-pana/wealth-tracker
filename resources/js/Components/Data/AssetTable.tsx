import { useForm } from '@inertiajs/react';
import { Pencil, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
import { formatCurrency } from '@/lib/formatters';
import type { Asset } from '@/types/models';

interface Props {
    assets: Asset[];
    onEdit: (asset: Asset) => void;
}

function DeleteButton({ assetId }: { assetId: number }) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (confirm('Eliminare questo asset?')) {
            destroy(`/assets/${assetId}`);
        }
    };

    return (
        <Button
            variant="ghost"
            size="icon"
            className="h-8 w-8 text-muted-foreground hover:text-destructive"
            onClick={handleDelete}
            disabled={processing}
        >
            <Trash2 className="w-4 h-4" />
        </Button>
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

    // Group by macro_category; assets without one go under null
    const groups = new Map<string | null, Asset[]>();
    for (const asset of assets) {
        const macro = asset.category.macro_category ?? null;
        if (!groups.has(macro)) groups.set(macro, []);
        groups.get(macro)!.push(asset);
    }

    const renderAssetRow = (asset: Asset) => (
        <TableRow key={asset.id}>
            <TableCell>
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
            <TableCell>
                <Badge
                    variant="outline"
                    style={{ borderColor: asset.category.color }}
                    className="text-foreground"
                >
                    {asset.category.icon && <span className="mr-1">{asset.category.icon}</span>}
                    {asset.category.name}
                </Badge>
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
                    <DeleteButton assetId={asset.id} />
                </div>
            </TableCell>
        </TableRow>
    );

    return (
        <div>
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Asset</TableHead>
                        <TableHead>Categoria</TableHead>
                        <TableHead className="text-right">Valore</TableHead>
                        <TableHead className="text-right w-20">Azioni</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {[...groups.entries()].map(([macro, groupAssets]) => (
                        <>
                            {macro !== null && (
                                <TableRow key={`group-${macro}`} className="bg-muted/30 hover:bg-muted/30">
                                    <TableCell colSpan={4} className="py-1.5 px-4 text-xs font-semibold text-muted-foreground uppercase tracking-wide">
                                        {macro}
                                    </TableCell>
                                </TableRow>
                            )}
                            {groupAssets.map(renderAssetRow)}
                        </>
                    ))}
                </TableBody>
            </Table>

            {/* Total row */}
            <div className="flex justify-between items-center px-4 py-3 border-t border-border bg-muted/40 rounded-b-md">
                <span className="text-sm font-medium text-muted-foreground">Totale mese</span>
                <span className="font-bold text-lg">{formatCurrency(total)}</span>
            </div>
        </div>
    );
}
