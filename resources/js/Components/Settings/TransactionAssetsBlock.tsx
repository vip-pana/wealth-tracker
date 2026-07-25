import { useForm } from '@inertiajs/react';
import { Button } from '@/Components/ui/button';
import { CandlestickChart, Unlink } from 'lucide-react';
import type { TransactionAsset } from '@/Components/Settings/types';

// Rendered as a subordinate block inside the broker card, not a peer card:
// these assets are the *result* of imported transactions (today from Scalable,
// but source-agnostic). Returns null when there are none.
export function TransactionAssetsBlock({ assets }: { assets: TransactionAsset[] }) {
    const unlink = useForm({});

    if (assets.length === 0) {
        return null;
    }

    const handleUnlink = (asset: TransactionAsset) => {
        if (!confirm(`Scollegare "${asset.name}" dalle transazioni? Le ${asset.transactions_count} transazioni importate verranno rimosse e la quantità (${asset.quantity ?? 0}) tornerà modificabile a mano.`)) {
            return;
        }
        unlink.delete(`/assets/${asset.id}/transactions`, { preserveScroll: true });
    };

    return (
        <div className="mt-1 rounded-md border border-border bg-muted/30 p-3 space-y-2">
            <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <CandlestickChart className="w-3.5 h-3.5" />
                Asset con quantità da transazioni
            </div>
            <p className="text-xs text-muted-foreground">
                La quantità di questi asset è calcolata dalle transazioni importate e non è modificabile a mano. Scollega per rimuovere le transazioni e riprendere il controllo manuale — l&apos;ultima quantità calcolata resta.
            </p>
            <div className="divide-y divide-border rounded-md border border-border bg-background">
                {assets.map((asset) => (
                    <div key={asset.id} className="flex items-center justify-between gap-3 px-3 py-2">
                        <div className="min-w-0">
                            <p className="text-sm font-medium truncate">{asset.name}</p>
                            <p className="text-xs text-muted-foreground">
                                {asset.quantity ?? 0} quote · {asset.transactions_count} transazioni
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={unlink.processing}
                            onClick={() => handleUnlink(asset)}
                        >
                            <Unlink className="w-4 h-4 mr-1" />
                            Scollega
                        </Button>
                    </div>
                ))}
            </div>
        </div>
    );
}
