import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Badge } from '@/Components/ui/badge';
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
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Download, Filter, X } from 'lucide-react';
import { formatCurrency, formatMonthLong } from '@/lib/formatters';
import type { Asset, Category } from '@/types/models';

interface Filters {
    category_id: number | null;
    date_from: string | null;
    date_to: string | null;
}

interface Props {
    assets: Asset[];
    categories: Pick<Category, 'id' | 'name' | 'color'>[];
    filters: Filters;
}

export default function Analysis({ assets, categories, filters }: Props) {
    const [localFilters, setLocalFilters] = useState<Filters>(filters);

    const applyFilters = () => {
        const params: Record<string, string> = {};
        if (localFilters.category_id) params.category_id = String(localFilters.category_id);
        if (localFilters.date_from) params.date_from = localFilters.date_from;
        if (localFilters.date_to) params.date_to = localFilters.date_to;
        router.get('/analysis', params, { preserveState: true });
    };

    const clearFilters = () => {
        setLocalFilters({ category_id: null, date_from: null, date_to: null });
        router.get('/analysis', {}, { preserveState: false });
    };

    const hasFilters =
        filters.category_id || filters.date_from || filters.date_to;

    const total = assets.reduce((s, a) => s + a.value, 0);

    // Build CSV export URL
    const exportParams = new URLSearchParams();
    if (filters.category_id) exportParams.set('category_id', String(filters.category_id));
    if (filters.date_from) exportParams.set('date_from', filters.date_from);
    if (filters.date_to) exportParams.set('date_to', filters.date_to);
    const exportUrl = `/export/csv${exportParams.toString() ? '?' + exportParams : ''}`;

    return (
        <>
            <Head title="Analisi" />
            <div className="p-6 space-y-6">
                <div className="flex items-start justify-between">
                    <div>
                        <h1 className="text-2xl font-bold">Analisi</h1>
                        <p className="text-sm text-muted-foreground">
                            {assets.length} asset trovati{hasFilters ? ' (filtrati)' : ''}
                        </p>
                    </div>
                    <a href={exportUrl} download>
                        <Button variant="outline" size="sm">
                            <Download className="w-4 h-4 mr-2" />
                            Esporta CSV
                        </Button>
                    </a>
                </div>

                {/* Filters */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm flex items-center gap-2">
                            <Filter className="w-4 h-4" />
                            Filtri
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="flex flex-wrap gap-4 items-end">
                            <div className="space-y-1">
                                <Label className="text-xs">Categoria</Label>
                                <Select
                                    value={localFilters.category_id?.toString() ?? 'all'}
                                    onValueChange={(v) =>
                                        setLocalFilters((f) => ({
                                            ...f,
                                            category_id: v === 'all' ? null : Number(v),
                                        }))
                                    }
                                >
                                    <SelectTrigger className="w-40">
                                        <SelectValue placeholder="Tutte" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Tutte</SelectItem>
                                        {categories.map((c) => (
                                            <SelectItem key={c.id} value={c.id.toString()}>
                                                {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label className="text-xs">Da</Label>
                                <Input
                                    type="date"
                                    className="w-40"
                                    value={localFilters.date_from ?? ''}
                                    onChange={(e) =>
                                        setLocalFilters((f) => ({
                                            ...f,
                                            date_from: e.target.value || null,
                                        }))
                                    }
                                />
                            </div>

                            <div className="space-y-1">
                                <Label className="text-xs">A</Label>
                                <Input
                                    type="date"
                                    className="w-40"
                                    value={localFilters.date_to ?? ''}
                                    onChange={(e) =>
                                        setLocalFilters((f) => ({
                                            ...f,
                                            date_to: e.target.value || null,
                                        }))
                                    }
                                />
                            </div>

                            <Button size="sm" onClick={applyFilters}>
                                Applica
                            </Button>
                            {hasFilters && (
                                <Button size="sm" variant="ghost" onClick={clearFilters}>
                                    <X className="w-4 h-4 mr-1" />
                                    Rimuovi filtri
                                </Button>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* Data table */}
                <Card>
                    <CardContent className="p-0">
                        {assets.length === 0 ? (
                            <div className="py-12 text-center text-muted-foreground text-sm">
                                Nessun asset trovato con i filtri selezionati.
                            </div>
                        ) : (
                            <>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>Mese</TableHead>
                                            <TableHead>Asset</TableHead>
                                            <TableHead>Categoria</TableHead>
                                            <TableHead className="text-right">Valore</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {assets.map((asset) => (
                                            <TableRow key={asset.id}>
                                                <TableCell className="text-sm text-muted-foreground whitespace-nowrap">
                                                    {formatMonthLong(asset.date)}
                                                </TableCell>
                                                <TableCell>
                                                    <div>
                                                        <p className="font-medium">{asset.name}</p>
                                                        {asset.notes && (
                                                            <p className="text-xs text-muted-foreground">
                                                                {asset.notes}
                                                            </p>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <Badge
                                                        variant="outline"
                                                        style={{
                                                            borderColor: asset.category.color,
                                                            color: asset.category.color,
                                                        }}
                                                    >
                                                        {asset.category.icon && (
                                                            <span className="mr-1">{asset.category.icon}</span>
                                                        )}
                                                        {asset.category.name}
                                                    </Badge>
                                                </TableCell>
                                                <TableCell className="text-right font-mono">
                                                    {formatCurrency(asset.value)}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                <div className="flex justify-between items-center px-4 py-3 border-t border-border bg-muted/40">
                                    <span className="text-sm text-muted-foreground">
                                        {assets.length} righe
                                    </span>
                                    <span className="font-bold">{formatCurrency(total)}</span>
                                </div>
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </>
    );
}

Analysis.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
