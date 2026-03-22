import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Badge } from '@/Components/ui/badge';
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
import { Pencil, Trash2, Plus, Download } from 'lucide-react';
import type { Category } from '@/types/models';

interface Props {
    categories: (Category & { assets_count: number })[];
}

type CategoryForm = {
    name: string;
    color: string;
    icon: string;
};

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
        name:  editCategory?.name ?? '',
        color: editCategory?.color ?? '#6366f1',
        icon:  editCategory?.icon ?? '',
    });

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
            className="h-8 w-8 text-muted-foreground hover:text-destructive"
            onClick={handleDelete}
            disabled={processing || category.assets_count > 0}
            title={category.assets_count > 0 ? 'Categoria in uso' : 'Elimina'}
        >
            <Trash2 className="w-4 h-4" />
        </Button>
    );
}

export default function Settings({ categories }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [editCategory, setEditCategory] = useState<Category | null>(null);

    return (
        <>
            <Head title="Impostazioni" />
            <div className="p-6 space-y-6">
                <h1 className="text-2xl font-bold">Impostazioni</h1>

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
                            Nuova categoria
                        </Button>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Categoria</TableHead>
                                    <TableHead>Colore</TableHead>
                                    <TableHead>Asset</TableHead>
                                    <TableHead className="w-20 text-right">Azioni</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {categories.map((cat) => (
                                    <TableRow key={cat.id}>
                                        <TableCell>
                                            <span className="flex items-center gap-2 font-medium">
                                                {cat.icon && <span>{cat.icon}</span>}
                                                {cat.name}
                                            </span>
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <div
                                                    className="w-5 h-5 rounded-full border border-border"
                                                    style={{ backgroundColor: cat.color }}
                                                />
                                                <span className="font-mono text-xs text-muted-foreground">
                                                    {cat.color}
                                                </span>
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="secondary">{cat.assets_count}</Badge>
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8"
                                                    onClick={() => {
                                                        setEditCategory(cat);
                                                        setDialogOpen(true);
                                                    }}
                                                >
                                                    <Pencil className="w-4 h-4" />
                                                </Button>
                                                <DeleteCategoryButton category={cat} />
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Export */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Backup & Export</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-3">
                        <div className="flex items-center justify-between p-3 rounded-md border border-border">
                            <div>
                                <p className="text-sm font-medium">Esporta tutti i dati (CSV)</p>
                                <p className="text-xs text-muted-foreground">
                                    Scarica tutti gli asset in formato CSV (compatibile con Excel)
                                </p>
                            </div>
                            <a href="/export/csv" download>
                                <Button variant="outline" size="sm">
                                    <Download className="w-4 h-4 mr-2" />
                                    Download CSV
                                </Button>
                            </a>
                        </div>
                    </CardContent>
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
        </>
    );
}

Settings.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
