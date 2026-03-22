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
                    {assets.map((asset) => (
                        <TableRow key={asset.id}>
                            <TableCell>
                                <div>
                                    <p className="font-medium">{asset.name}</p>
                                    {asset.notes && (
                                        <p className="text-xs text-muted-foreground">{asset.notes}</p>
                                    )}
                                </div>
                            </TableCell>
                            <TableCell>
                                <Badge
                                    variant="outline"
                                    style={{ borderColor: asset.category.color, color: asset.category.color }}
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
