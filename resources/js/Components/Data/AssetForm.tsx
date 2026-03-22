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
import type { Asset, Category } from '@/types/models';

interface Props {
    open: boolean;
    onClose: () => void;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'icon'>[];
    month: string;
    editAsset?: Asset | null;
}

export default function AssetForm({ open, onClose, categories, month, editAsset }: Props) {
    const isEdit = !!editAsset;

    const { data, setData, post, put, processing, errors, reset } = useForm({
        category_id: editAsset?.category_id?.toString() ?? '',
        name:        editAsset?.name ?? '',
        value:       editAsset?.value?.toString() ?? '',
        date:        editAsset?.date ?? month,
        notes:       editAsset?.notes ?? '',
    });

    useEffect(() => {
        if (open) {
            setData({
                category_id: editAsset?.category_id?.toString() ?? '',
                name:        editAsset?.name ?? '',
                value:       editAsset?.value?.toString() ?? '',
                date:        editAsset?.date ?? month,
                notes:       editAsset?.notes ?? '',
            });
        }
    }, [open, editAsset, month, setData]);

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
                            placeholder="es. Conto ING, BTC, ETF VWCE"
                        />
                        {errors.name && (
                            <p className="text-xs text-destructive">{errors.name}</p>
                        )}
                    </div>

                    {/* Value */}
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

                    {/* Notes */}
                    <div className="space-y-1">
                        <Label>Note (opzionale)</Label>
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
