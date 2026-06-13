import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
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
import { Label } from '@/Components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Download, Filter, X, ChevronLeft, ChevronRight, BarChart2 } from 'lucide-react';
import { formatMonthLong } from '@/lib/formatters';
import { Money } from '@/Components/ui/Money';
import type { Asset, Category } from '@/types/models';

interface Filters {
    category_id: number | null;
    date_from: string | null;
    date_to: string | null;
}

interface Props {
    assets: Asset[];
    categories: Pick<Category, 'id' | 'name' | 'color'>[];
    availableMonths: string[];
    filters: Filters;
}

export default function Analysis({ assets, categories, availableMonths, filters }: Props) {
    const [localFilters, setLocalFilters] = useState<Filters>(filters);
    const [page, setPage] = useState(1);
    const [perPage, setPerPage] = useState(10);

    const applyFilters = () => {
        const params: Record<string, string> = {};
        if (localFilters.category_id) params.category_id = String(localFilters.category_id);
        if (localFilters.date_from) params.date_from = localFilters.date_from;
        if (localFilters.date_to) params.date_to = localFilters.date_to;
        router.get('/analysis', params, { preserveState: true });
    };

    const clearFilters = () => {
        setLocalFilters({ category_id: null, date_from: null, date_to: null });
        setPage(1);
        router.get('/analysis', {}, { preserveState: false });
    };

    const hasFilters =
        filters.category_id || filters.date_from || filters.date_to;

    const totalPages = Math.max(1, Math.ceil(assets.length / perPage));
    const safePage = Math.min(page, totalPages);
    const pagedAssets = assets.slice((safePage - 1) * perPage, safePage * perPage);

    // Build CSV export URL
    const exportParams = new URLSearchParams();
    if (filters.category_id) exportParams.set('category_id', String(filters.category_id));
    if (filters.date_from) exportParams.set('date_from', filters.date_from);
    if (filters.date_to) exportParams.set('date_to', filters.date_to);
    const exportUrl = `/export/csv${exportParams.toString() ? '?' + exportParams : ''}`;

    return (
        <>
            <Head title="Analisi" />
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={BarChart2}
                    title="Analisi"
                    subtitle={`${assets.length} asset trovati${hasFilters ? ' (filtrati)' : ''}`}
                    actions={
                        <a href={exportUrl} download>
                            <Button variant="outline" size="sm">
                                <Download className="w-4 h-4 mr-2" />
                                Esporta CSV
                            </Button>
                        </a>
                    }
                />

                {/* Filters */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-sm flex items-center gap-2">
                            <Filter className="w-4 h-4" />
                            Filtri
                        </CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
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
                                    <SelectTrigger className="w-full">
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
                                <Select
                                    value={localFilters.date_from ?? 'all'}
                                    onValueChange={(v) =>
                                        setLocalFilters((f) => ({
                                            ...f,
                                            date_from: v === 'all' ? null : v,
                                        }))
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Tutti" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Tutti</SelectItem>
                                        {availableMonths.map((m) => (
                                            <SelectItem key={m} value={m}>
                                                {formatMonthLong(m)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="space-y-1">
                                <Label className="text-xs">A</Label>
                                <Select
                                    value={localFilters.date_to ?? 'all'}
                                    onValueChange={(v) =>
                                        setLocalFilters((f) => ({
                                            ...f,
                                            date_to: v === 'all' ? null : v,
                                        }))
                                    }
                                >
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Tutti" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">Tutti</SelectItem>
                                        {availableMonths.map((m) => (
                                            <SelectItem key={m} value={m}>
                                                {formatMonthLong(m)}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <div className="flex flex-col-reverse sm:flex-row sm:items-center gap-2 mt-3">
                            <Button size="sm" onClick={applyFilters} className="w-full sm:w-auto">
                                Applica
                            </Button>
                            {hasFilters && (
                                <Button size="sm" variant="ghost" onClick={clearFilters} className="w-full sm:w-auto">
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
                                {/* Desktop: table */}
                                <div className="hidden lg:block">
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
                                            {pagedAssets.map((asset) => (
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
                                                        <Money value={asset.value} />
                                                    </TableCell>
                                                </TableRow>
                                            ))}
                                        </TableBody>
                                    </Table>
                                </div>

                                {/* Mobile: cards */}
                                <div className="lg:hidden divide-y divide-border">
                                    {pagedAssets.map((asset) => (
                                        <div key={asset.id} className="p-4 space-y-2">
                                            <div className="flex items-start justify-between gap-3">
                                                <div className="min-w-0">
                                                    <p className="font-medium truncate">{asset.name}</p>
                                                    <p className="text-xs text-muted-foreground">{formatMonthLong(asset.date)}</p>
                                                </div>
                                                <Money value={asset.value} className="font-mono font-semibold whitespace-nowrap" />
                                            </div>
                                            <div className="flex items-center justify-between gap-2">
                                                <Badge
                                                    variant="outline"
                                                    style={{ borderColor: asset.category.color, color: asset.category.color }}
                                                >
                                                    {asset.category.icon && <span className="mr-1">{asset.category.icon}</span>}
                                                    {asset.category.name}
                                                </Badge>
                                                {asset.notes && (
                                                    <span className="text-xs text-muted-foreground truncate">{asset.notes}</span>
                                                )}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                                <div className="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-t border-border bg-muted/40">
                                    <div className="flex items-center gap-3">
                                        <span className="text-sm text-muted-foreground">
                                            {assets.length} righe · pagina {safePage} di {totalPages}
                                        </span>
                                        <div className="flex items-center gap-1.5">
                                            <span className="text-xs text-muted-foreground">Righe per pagina:</span>
                                            <Select value={String(perPage)} onValueChange={(v) => { setPerPage(Number(v)); setPage(1); }}>
                                                <SelectTrigger className="h-7 w-16 text-xs">
                                                    <SelectValue />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {[10, 25, 50, 100].map((n) => (
                                                        <SelectItem key={n} value={String(n)}>{n}</SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        <Button variant="outline" size="icon" className="h-7 w-7" disabled={safePage <= 1} onClick={() => setPage((p) => p - 1)}>
                                            <ChevronLeft className="w-3.5 h-3.5" />
                                        </Button>
                                        <Button variant="outline" size="icon" className="h-7 w-7" disabled={safePage >= totalPages} onClick={() => setPage((p) => p + 1)}>
                                            <ChevronRight className="w-3.5 h-3.5" />
                                        </Button>
                                    </div>
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
