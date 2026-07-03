import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { LineChart, Line, XAxis, YAxis, Tooltip, ResponsiveContainer, CartesianGrid } from 'recharts';
import { PiggyBank, Pencil, Trash2, Plus, Info } from 'lucide-react';
import { formatCurrencyNoDecimals, formatCurrencyCompact } from '@/lib/formatters';
import { Money } from '@/Components/ui/Money';
import { PensionCategory, PensionEntry } from '@/Components/Pension/types';
import { PensionFormDialog } from '@/Components/Pension/PensionFormDialog';
import { PensionEmptyState } from '@/Components/Pension/PensionEmptyState';

interface Props {
    categories: PensionCategory[];
    entries: PensionEntry[];
    availableYears: number[];
    totalCurrent: number;
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
