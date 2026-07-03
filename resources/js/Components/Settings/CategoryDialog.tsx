import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogFooter,
} from '@/Components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import type { Category } from '@/types/models';
import type { CategoryForm } from '@/Components/Settings/types';

const MACRO_CATEGORIES = ['Liquidità', 'ETF', 'Cripto', 'Fondo Pensione'] as const;

// Well-separated hues so categories stay distinguishable in charts.
const CATEGORY_PALETTE = [
    '#6366f1', // indigo
    '#0ce708', // green
    '#f7931a', // orange
    '#d4af37', // gold
    '#ef4444', // red
    '#06b6d4', // cyan
    '#a855f7', // purple
    '#ec4899', // pink
    '#64748b', // slate
    '#fcfcfc', // white
] as const;

export function CategoryDialog({
    open,
    onClose,
    editCategory,
}: {
    open: boolean;
    onClose: () => void;
    editCategory?: Category | null;
}) {
    const isEdit = !!editCategory;
    const { data, setData, post, put, processing, errors, reset } = useForm<CategoryForm>({
        name:           editCategory?.name ?? '',
        color:          editCategory?.color ?? '#6366f1',
        macro_category: editCategory?.macro_category ?? '',
    });

    useEffect(() => {
        if (open) {
            setData({
                name:           editCategory?.name ?? '',
                color:          editCategory?.color ?? '#6366f1',
                macro_category: editCategory?.macro_category ?? '',
            });
        }
    }, [open, editCategory, setData]);

    const handleClose = () => {
        reset();
        onClose();
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = { onSuccess: () => { reset(); onClose(); } };
        if (isEdit) {
            put(`/categories/${editCategory!.id}`, opts);
        } else {
            post('/categories', opts);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
            <DialogContent className="sm:max-w-sm">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Modifica categoria' : 'Nuova categoria'}</DialogTitle>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1">
                        <Label>Nome</Label>
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="es. Obbligazioni"
                        />
                        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                    </div>
                    <div className="space-y-1">
                        <Label>Colore</Label>
                        <div className="flex items-center gap-2">
                            <input
                                type="color"
                                value={data.color}
                                onChange={(e) => setData('color', e.target.value)}
                                className="h-9 w-16 cursor-pointer rounded border border-input"
                            />
                            <Input
                                value={data.color}
                                onChange={(e) => setData('color', e.target.value)}
                                placeholder="#6366f1"
                                className="font-mono"
                            />
                        </div>
                        <div className="flex flex-wrap gap-1.5 pt-1">
                            {CATEGORY_PALETTE.map((c) => (
                                <button
                                    key={c}
                                    type="button"
                                    onClick={() => setData('color', c)}
                                    className={`w-6 h-6 rounded-full border transition-transform hover:scale-110 ${data.color.toLowerCase() === c.toLowerCase() ? 'border-foreground ring-2 ring-foreground/30' : 'border-border'}`}
                                    style={{ backgroundColor: c }}
                                    aria-label={`Colore ${c}`}
                                />
                            ))}
                        </div>
                        {errors.color && <p className="text-xs text-destructive">{errors.color}</p>}
                    </div>
                    <div className="space-y-1">
                        <Label>Macro-categoria</Label>
                        <Select
                            value={data.macro_category}
                            onValueChange={(v) => setData('macro_category', v === '__none__' ? '' : v)}
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Nessuna" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="__none__">Nessuna</SelectItem>
                                {MACRO_CATEGORIES.map((mc) => (
                                    <SelectItem key={mc} value={mc}>{mc}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={handleClose}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {isEdit ? 'Salva' : 'Crea'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
