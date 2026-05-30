import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
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
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Pencil, Trash2, Plus, Download, Upload, RefreshCw, Layers, Database, Settings as SettingsIcon } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { formatCurrency } from '@/lib/formatters';
import type { Category } from '@/types/models';

const MACRO_CATEGORIES = ['Liquidità', 'ETF', 'Cripto', 'Fondo Pensione'] as const;

interface PriceEntry {
    ticker: string;
    price: number;
    currency: string;
    fetched_at: string;
}

interface Props {
    categories: (Category & { assets_count: number })[];
    prices: PriceEntry[];
}

type CategoryForm = {
    name: string;
    color: string;
    icon: string;
    macro_category: string;
};

function ImportCsvDialog({ open, onClose }: { open: boolean; onClose: () => void }) {
    const { data, setData, post, processing, errors, reset } = useForm<{ file: File | null }>({ file: null });

    const handleClose = () => {
        reset();
        onClose();
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/import/csv', { forceFormData: true, onSuccess: () => { reset(); onClose(); } });
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && handleClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Importa da CSV</DialogTitle>
                </DialogHeader>
                <form onSubmit={handleSubmit} className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Carica un file CSV nel formato richiesto.{' '}
                        <a href="/import/csv/template" download className="underline hover:text-foreground">
                            Scarica template
                        </a>{' '}
                        per vedere il formato corretto.
                    </p>
                    <div className="space-y-1">
                        <Label>File CSV</Label>
                        <Input
                            type="file"
                            accept=".csv,text/csv"
                            className="text-sm"
                            onChange={(e) => setData('file', e.target.files?.[0] ?? null)}
                        />
                        {errors.file && <p className="text-xs text-destructive">{errors.file}</p>}
                    </div>
                    <div className="rounded-md border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground space-y-1">
                        <p className="font-medium text-foreground">Formato atteso (separatore <code>;</code>):</p>
                        <p><code>data;categoria;nome_asset;valore;note</code></p>
                        <p><code>2026-01-01;ETF;Gold SGLD;1243.00;</code></p>
                        <p>Se un asset con stessa data e nome esiste già, viene aggiornato.</p>
                    </div>
                    <DialogFooter>
                        <Button type="button" variant="outline" onClick={handleClose}>
                            Annulla
                        </Button>
                        <Button type="submit" disabled={processing || !data.file}>
                            {processing ? 'Importando...' : 'Importa'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function CategoryDialog({
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
        icon:           editCategory?.icon ?? '',
        macro_category: editCategory?.macro_category ?? '',
    });

    useEffect(() => {
        if (open) {
            setData({
                name:           editCategory?.name ?? '',
                color:          editCategory?.color ?? '#6366f1',
                icon:           editCategory?.icon ?? '',
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
                        {errors.color && <p className="text-xs text-destructive">{errors.color}</p>}
                    </div>
                    <div className="space-y-1">
                        <Label>Icona (emoji opzionale)</Label>
                        <Input
                            value={data.icon}
                            onChange={(e) => setData('icon', e.target.value)}
                            placeholder="💰"
                            maxLength={10}
                        />
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

function DeleteCategoryButton({ category }: { category: Category & { assets_count: number } }) {
    const { delete: destroy, processing } = useForm({});

    const handleDelete = () => {
        if (category.assets_count > 0) {
            alert(`Non puoi eliminare "${category.name}": ha ${category.assets_count} asset associati.`);
            return;
        }
        if (confirm(`Eliminare la categoria "${category.name}"?`)) {
            destroy(`/categories/${category.id}`);
        }
    };

    return (
        <Button
            variant="ghost"
            size="icon"
            className="h-7 w-7 text-muted-foreground hover:text-destructive"
            onClick={handleDelete}
            disabled={processing || category.assets_count > 0}
            title={category.assets_count > 0 ? 'Categoria in uso' : 'Elimina'}
        >
            <Trash2 className="w-3.5 h-3.5" />
        </Button>
    );
}

export default function Settings({ categories, prices }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editCategory, setEditCategory] = useState<Category | null>(null);
    const refreshForm = useForm({});
    const backupForm = useForm({});

    const lastPriceUpdate = prices.length > 0
        ? new Date(Math.max(...prices.map((p) => new Date(p.fetched_at).getTime())))
        : null;

    return (
        <>
            <Head title="Impostazioni" />
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full">
                <PageHeader icon={SettingsIcon} title="Impostazioni" />

                {/* Categories */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <CardTitle className="text-base">Categorie</CardTitle>
                        <Button
                            size="sm"
                            onClick={() => {
                                setEditCategory(null);
                                setDialogOpen(true);
                            }}
                        >
                            <Plus className="w-4 h-4 mr-1" />
                            Nuova
                        </Button>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="divide-y divide-border">
                            {categories.map((cat) => (
                                <div key={cat.id} className="flex items-center gap-3 px-4 py-2.5 hover:bg-muted/30 transition-colors">
                                    <div className="w-3 h-3 rounded-full flex-shrink-0" style={{ backgroundColor: cat.color }} />
                                    <span className="text-sm font-medium flex-1 flex items-center gap-1.5">
                                        {cat.icon && <span>{cat.icon}</span>}
                                        {cat.name}
                                    </span>
                                    {cat.macro_category && (
                                        <span className="flex items-center gap-1 text-xs text-muted-foreground">
                                            <Layers className="w-3 h-3" />
                                            {cat.macro_category}
                                        </span>
                                    )}
                                    <span className="text-xs text-muted-foreground w-16 text-right">
                                        {cat.assets_count} asset
                                    </span>
                                    <div className="flex gap-1 flex-shrink-0">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-7 w-7 text-muted-foreground hover:text-foreground"
                                            onClick={() => {
                                                setEditCategory(cat);
                                                setDialogOpen(true);
                                            }}
                                        >
                                            <Pencil className="w-3.5 h-3.5" />
                                        </Button>
                                        <DeleteCategoryButton category={cat} />
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Prices */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle className="text-base">Prezzi asset live</CardTitle>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Aggiornati automaticamente ogni giorno alle 06:00
                                {lastPriceUpdate && ` · ultimo aggiornamento ${lastPriceUpdate.toLocaleString('it-IT')}`}
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => refreshForm.post('/prices/refresh')}
                            disabled={refreshForm.processing}
                        >
                            <RefreshCw className={`w-4 h-4 mr-1 ${refreshForm.processing ? 'animate-spin' : ''}`} />
                            Aggiorna ora
                        </Button>
                    </CardHeader>
                    <CardContent className="p-0">
                        {prices.length === 0 ? (
                            <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                Nessun prezzo disponibile. Aggiungi asset con un ticker e clicca &quot;Aggiorna ora&quot;.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Ticker</TableHead>
                                        <TableHead className="text-right">Prezzo</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {prices.map((p) => (
                                        <TableRow key={p.ticker}>
                                            <TableCell className="font-mono font-medium">{p.ticker}</TableCell>
                                            <TableCell className="text-right font-mono">
                                                {formatCurrency(p.price)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Import / Export */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <CardTitle className="text-base">Dati</CardTitle>
                        <div className="flex items-center gap-2">
                            <Button variant="outline" size="sm" onClick={() => setImportOpen(true)}>
                                <Upload className="w-4 h-4 mr-2" />
                                Importa CSV
                            </Button>
                            <a href="/export/csv" download>
                                <Button variant="outline" size="sm">
                                    <Download className="w-4 h-4 mr-2" />
                                    Esporta CSV
                                </Button>
                            </a>
                        </div>
                    </CardHeader>
                </Card>

                {/* Backup */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <div>
                            <CardTitle className="text-base">Backup database</CardTitle>
                            <p className="text-xs text-muted-foreground mt-0.5">
                                Snapshot atomico verso il cloud. Backup automatico ogni notte alle 03:00.
                            </p>
                        </div>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => backupForm.post('/backup', { preserveScroll: true })}
                            disabled={backupForm.processing}
                        >
                            <Database className={`w-4 h-4 mr-1 ${backupForm.processing ? 'animate-pulse' : ''}`} />
                            Backup ora
                        </Button>
                    </CardHeader>
                </Card>
            </div>

            <CategoryDialog
                open={dialogOpen}
                onClose={() => {
                    setDialogOpen(false);
                    setEditCategory(null);
                }}
                editCategory={editCategory}
            />
            <ImportCsvDialog open={importOpen} onClose={() => setImportOpen(false)} />
        </>
    );
}

Settings.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
