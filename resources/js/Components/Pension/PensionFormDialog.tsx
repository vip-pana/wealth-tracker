import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { OptionalHint } from '@/Components/ui/OptionalHint';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { ChevronDown } from 'lucide-react';
import { PensionCategory, PensionEntry } from '@/Components/Pension/types';

interface PensionFormData {
    category_id: string;
    name: string;
    value: string;
    year: string;
    notes: string;
}

function emptyForm(defaults: { categoryId?: number; year?: number }): PensionFormData {
    return {
        category_id: defaults.categoryId ? String(defaults.categoryId) : '',
        name: '',
        value: '',
        year: defaults.year ? String(defaults.year) : '',
        notes: '',
    };
}

export function PensionFormDialog({
    open,
    onClose,
    categories,
    availableYears,
    entry,
}: {
    open: boolean;
    onClose: () => void;
    categories: PensionCategory[];
    availableYears: number[];
    entry: PensionEntry | null;
}) {
    const isEdit = entry !== null;
    // availableYears is server-built (current year first), so trust it rather
    // than reading the browser clock.
    const defaultYear = availableYears[0];
    const defaultCategoryId = categories[0]?.id;

    const { data, setData, post, put, processing, errors, reset } = useForm<PensionFormData>(
        emptyForm({ categoryId: defaultCategoryId, year: defaultYear })
    );

    useEffect(() => {
        if (!open) return;
        if (entry) {
            setData({
                category_id: String(entry.category_id),
                name: entry.name,
                value: String(entry.value),
                year: String(entry.year),
                notes: entry.notes ?? '',
            });
        } else {
            setData(emptyForm({ categoryId: defaultCategoryId, year: defaultYear }));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, entry]);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const opts = {
            onSuccess: () => {
                reset();
                onClose();
            },
        };
        if (isEdit && entry) {
            put(`/pension/${entry.id}`, opts);
        } else {
            post('/pension', opts);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>{isEdit ? 'Modifica valore annuale' : 'Aggiungi valore annuale'}</DialogTitle>
                    <DialogDescription className="sr-only">Registra il valore di un fondo pensione per un dato anno.</DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-1">
                        <Label>Fondo</Label>
                        <div className="relative">
                            <select
                                value={data.category_id}
                                onChange={(e) => setData('category_id', e.target.value)}
                                className="w-full h-9 appearance-none rounded-md border border-input bg-background pl-3 pr-8 text-sm"
                            >
                                <option value="">Seleziona fondo</option>
                                {categories.map((c) => (
                                    <option key={c.id} value={c.id}>{c.name}</option>
                                ))}
                            </select>
                            <ChevronDown className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground" />
                        </div>
                        {errors.category_id && <p className="text-xs text-destructive">{errors.category_id}</p>}
                    </div>

                    <div className="space-y-1">
                        <Label>Etichetta</Label>
                        <Input
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            placeholder="es. Report 2026"
                        />
                        {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                    </div>

                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label>Anno del report</Label>
                            <div className="relative">
                                <select
                                    value={data.year}
                                    onChange={(e) => setData('year', e.target.value)}
                                    className="w-full h-9 appearance-none rounded-md border border-input bg-background pl-3 pr-8 text-sm font-mono"
                                >
                                    {availableYears.map((y) => (
                                        <option key={y} value={y}>{y}</option>
                                    ))}
                                </select>
                                <ChevronDown className="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-muted-foreground" />
                            </div>
                            {errors.year && <p className="text-xs text-destructive">{errors.year}</p>}
                        </div>
                        <div className="space-y-1">
                            <Label>Valore (€)</Label>
                            <Input
                                type="text"
                                inputMode="decimal"
                                value={data.value}
                                onChange={(e) => setData('value', e.target.value)}
                                placeholder="15000"
                                className="font-mono"
                            />
                            {errors.value && <p className="text-xs text-destructive">{errors.value}</p>}
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label>Note <OptionalHint /></Label>
                        <textarea
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                            placeholder="Note dal report annuale..."
                            rows={3}
                            className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm resize-y placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                    </div>

                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={onClose}>Annulla</Button>
                        <Button type="submit" disabled={processing}>
                            {isEdit ? 'Salva' : 'Aggiungi'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
