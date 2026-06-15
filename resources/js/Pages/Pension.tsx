import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Dialog, DialogContent, DialogFooter, DialogHeader, DialogTitle } from '@/Components/ui/dialog';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { EmptyState } from '@/Components/ui/EmptyState';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { PiggyBank, Pencil, Trash2, Plus, ChevronDown, Info } from 'lucide-react';
import { formatCurrencyNoDecimals, formatCurrencyCompact } from '@/lib/formatters';
import { Money } from '@/Components/ui/Money';

interface PensionCategory {
    id: number;
    name: string;
    color: string;
    icon: string | null;
    macro_category: string | null;
}

interface PensionEntry {
    id: number;
    name: string;
    value: number;
    year: number;
    date: string;
    notes: string | null;
    category_id: number;
    category: {
        id: number;
        name: string;
        color: string;
        icon: string | null;
    };
}

interface Props {
    categories: PensionCategory[];
    entries: PensionEntry[];
    availableYears: number[];
    totalCurrent: number;
}

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

function PensionFormDialog({
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
                        <Label>Note (opzionale)</Label>
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

function PensionEmptyState({ onCreate, hasCategories }: { onCreate: () => void; hasCategories: boolean }) {
    return (
        <EmptyState
            icon={PiggyBank}
            title="Nessun valore registrato"
            description={hasCategories
                ? 'Inserisci il valore del tuo fondo pensione dal report annuale. Verrà conteggiato nel patrimonio totale ma escluso dai grafici di analisi mensile.'
                : 'Crea una categoria con macro "Fondo Pensione" dalle Impostazioni per iniziare.'}
            action={hasCategories && (
                <Button onClick={onCreate}>
                    <Plus className="w-4 h-4 mr-2" />
                    Aggiungi valore
                </Button>
            )}
        />
    );
}

export default function PensionPage({ categories, entries, availableYears, totalCurrent }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editingEntry, setEditingEntry] = useState<PensionEntry | null>(null);
    const { delete: destroy } = useForm({});

    const openCreate = () => {
        setEditingEntry(null);
        setFormOpen(true);
    };

    const openEdit = (entry: PensionEntry) => {
        setEditingEntry(entry);
        setFormOpen(true);
    };

    const handleDelete = (entry: PensionEntry) => {
        if (confirm(`Eliminare il valore di ${entry.year} per "${entry.name}"?`)) {
            destroy(`/pension/${entry.id}`);
        }
    };

    if (categories.length === 0 || entries.length === 0) {
        return (
            <>
                <Head title="Fondo Pensione" />
                <PensionEmptyState onCreate={openCreate} hasCategories={categories.length > 0} />
                <PensionFormDialog
                    open={formOpen}
                    onClose={() => setFormOpen(false)}
                    categories={categories}
                    availableYears={availableYears}
                    entry={editingEntry}
                />
            </>
        );
    }

    const chartData = [...entries]
        .sort((a, b) => a.year - b.year)
        .map((e) => ({ year: e.year, value: e.value, name: e.name }));

    return (
        <>
            <Head title="Fondo Pensione" />
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={PiggyBank}
                    title="Fondo Pensione"
                    subtitle={
                        <span className="flex items-center gap-1.5">
                            <Info className="w-3.5 h-3.5" />
                            Asset illiquido: escluso da Dashboard e Analisi, conteggiato nel patrimonio totale.
                        </span>
                    }
                    actions={
                        <Button onClick={openCreate}>
                            <Plus className="w-4 h-4 mr-2" />
                            Aggiungi valore
                        </Button>
                    }
                />

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-4 items-start">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm text-muted-foreground">Valore attuale</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <p className="text-2xl font-bold"><Money value={totalCurrent} variant="no-decimals" /></p>
                            <p className="text-xs text-muted-foreground mt-1">
                                Somma dell&apos;ultimo valore di ogni fondo
                            </p>
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2">
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm">Andamento storico</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {chartData.length < 2 ? (
                                <div className="h-[180px] flex items-center justify-center text-sm text-muted-foreground">
                                    Almeno due valori per vedere l&apos;andamento.
                                </div>
                            ) : (
                                <ResponsiveContainer width="100%" height={180}>
                                    <LineChart data={chartData} margin={{ top: 5, right: 10, left: 0, bottom: 0 }}>
                                        <CartesianGrid strokeDasharray="3 3" stroke="hsl(var(--border))" />
                                        <XAxis
                                            dataKey="year"
                                            stroke="hsl(var(--muted-foreground))"
                                            fontSize={12}
                                        />
                                        <YAxis
                                            stroke="hsl(var(--muted-foreground))"
                                            fontSize={12}
                                            tickFormatter={(v) => formatCurrencyCompact(v as number)}
                                        />
                                        <Tooltip
                                            formatter={(v) => formatCurrencyNoDecimals(v as number)}
                                            contentStyle={{
                                                fontSize: 12,
                                                backgroundColor: 'hsl(var(--card))',
                                                borderColor: 'hsl(var(--border))',
                                                color: 'hsl(var(--card-foreground))',
                                            }}
                                        />
                                        <Line
                                            type="monotone"
                                            dataKey="value"
                                            stroke="hsl(var(--primary))"
                                            strokeWidth={2}
                                            dot={{ r: 4 }}
                                        />
                                    </LineChart>
                                </ResponsiveContainer>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Storico</CardTitle>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table className="min-w-[600px]">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Anno</TableHead>
                                    <TableHead>Fondo</TableHead>
                                    <TableHead>Etichetta</TableHead>
                                    <TableHead className="text-right">Valore</TableHead>
                                    <TableHead>Note</TableHead>
                                    <TableHead className="w-24" />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {entries.map((entry) => (
                                    <TableRow key={entry.id}>
                                        <TableCell className="font-mono">{entry.year}</TableCell>
                                        <TableCell>
                                            <div className="flex items-center gap-2">
                                                <div className="w-2 h-2 rounded-full" style={{ backgroundColor: entry.category.color }} />
                                                <span>{entry.category.name}</span>
                                            </div>
                                        </TableCell>
                                        <TableCell>{entry.name}</TableCell>
                                        <TableCell className="text-right font-mono">
                                            <Money value={entry.value} variant="no-decimals" />
                                        </TableCell>
                                        <TableCell className="text-xs text-muted-foreground max-w-xs truncate">
                                            {entry.notes ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-1 justify-end">
                                                <Button variant="ghost" size="icon" className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-accent" onClick={() => openEdit(entry)}>
                                                    <Pencil className="w-4 h-4" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-accent"
                                                    onClick={() => handleDelete(entry)}
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <PensionFormDialog
                open={formOpen}
                onClose={() => setFormOpen(false)}
                categories={categories}
                availableYears={availableYears}
                entry={editingEntry}
            />
        </>
    );
}

PensionPage.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
