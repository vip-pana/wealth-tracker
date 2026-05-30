import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import AssetForm from '@/Components/Data/AssetForm';
import AssetTable from '@/Components/Data/AssetTable';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Plus, ChevronLeft, ChevronRight, Copy, Camera, PlusSquare } from 'lucide-react';
import { formatMonthLong, formatDateLong, formatCurrency, today } from '@/lib/formatters';
import type { Asset, AssetPriceInfo, Category } from '@/types/models';

interface Props {
    assets: Asset[];
    categories: Pick<Category, 'id' | 'name' | 'color' | 'icon'>[];
    month: string;
    availableMonths: string[];
    snapshotState: 'missing' | 'stale' | 'current';
    lastSnapshotDate: string | null;
    currentNetWorth: number;
    prices: Record<string, AssetPriceInfo>;
}

export default function InputData({ assets, categories, month, availableMonths, snapshotState, lastSnapshotDate, currentNetWorth, prices }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editAsset, setEditAsset] = useState<Asset | null>(null);
    const [savingSnapshot, setSavingSnapshot] = useState(false);
    const [copyOpen, setCopyOpen] = useState(false);
    const copyForm = useForm({ source_date: '' });

    const handleSaveSnapshot = () => {
        if (assets.length === 0) {
            alert('Aggiungi almeno un asset prima di salvare lo snapshot.');
            return;
        }
        const date = today();
        if (confirm(`Salvare lo snapshot di oggi (${formatDateLong(date)})?`)) {
            setSavingSnapshot(true);
            router.post('/snapshots', { date }, {
                onFinish: () => setSavingSnapshot(false),
            });
        }
    };

    const handleMonthChange = (newMonth: string) => {
        router.get('/input', { month: newMonth }, { preserveState: false });
    };

    const navigateMonth = (direction: 'prev' | 'next') => {
        const [year, mon] = month.split('-').map(Number);
        const date = new Date(year, mon - 1 + (direction === 'next' ? 1 : -1), 1);
        const newMonth = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-01`;
        handleMonthChange(newMonth);
    };

    const handleCopy = () => {
        copyForm.post(`/assets/copy-from-month?month=${month}`, {
            onSuccess: () => setCopyOpen(false),
        });
    };

    const total = assets.reduce((sum, a) => sum + a.value, 0);

    // Group by category for summary
    const byCat: Record<string, { name: string; color: string; icon: string | null; total: number }> = {};
    for (const a of assets) {
        if (!byCat[a.category_id]) {
            byCat[a.category_id] = {
                name:  a.category.name,
                color: a.category.color,
                icon:  a.category.icon,
                total: 0,
            };
        }
        byCat[a.category_id].total += a.value;
    }

    return (
        <>
            <Head title="Input Dati" />
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader icon={PlusSquare} title="Input Dati" />

                {/* Header: two cards — month values editor vs snapshot */}
                <div className="grid gap-3 md:grid-cols-2">
                    {/* Card: month values */}
                    <Card>
                        <CardContent className="p-3 space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-xs font-medium text-muted-foreground">Valori del mese</p>
                                <div className="flex items-center gap-1">
                                    <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => navigateMonth('prev')}>
                                        <ChevronLeft className="w-4 h-4" />
                                    </Button>
                                    <Select value={month} onValueChange={handleMonthChange}>
                                        <SelectTrigger className="h-7 w-36">
                                            <SelectValue>{formatMonthLong(month)}</SelectValue>
                                        </SelectTrigger>
                                        <SelectContent>
                                            {availableMonths.map((m) => (
                                                <SelectItem key={m} value={m}>
                                                    {formatMonthLong(m)}
                                                </SelectItem>
                                            ))}
                                            {!availableMonths.includes(month) && (
                                                <SelectItem value={month}>{formatMonthLong(month)}</SelectItem>
                                            )}
                                        </SelectContent>
                                    </Select>
                                    <Button variant="outline" size="icon" className="h-7 w-7" onClick={() => navigateMonth('next')}>
                                        <ChevronRight className="w-4 h-4" />
                                    </Button>
                                </div>
                            </div>
                            <p className="text-2xl font-bold">{formatCurrency(total)}</p>
                            <p className="text-xs text-muted-foreground">
                                Aggiorna qui il valore corrente di ogni asset. Questi numeri non finiscono nei grafici finché non scatti uno snapshot.
                            </p>
                        </CardContent>
                    </Card>

                    {/* Card: snapshot */}
                    <Card>
                        <CardContent className="p-3 space-y-2">
                            <div className="flex items-center justify-between gap-2">
                                <p className="text-xs font-medium text-muted-foreground flex items-center gap-1">
                                    <Camera className="w-3.5 h-3.5" /> Snapshot patrimonio
                                </p>
                                {snapshotState === 'current' && (
                                    <span className="inline-flex items-center rounded-full bg-green-500 px-2 py-0.5 text-xs font-medium text-white">
                                        Aggiornato
                                    </span>
                                )}
                                {snapshotState === 'stale' && (
                                    <span className="inline-flex items-center rounded-full bg-yellow-500 px-2 py-0.5 text-xs font-medium text-white">
                                        Da aggiornare
                                    </span>
                                )}
                            </div>
                            <div>
                                <p className="text-2xl font-bold">{formatCurrency(currentNetWorth)}</p>
                                <p className="text-xs text-muted-foreground">
                                    Patrimonio attuale ·{' '}
                                    {lastSnapshotDate
                                        ? <>ultimo snapshot {formatDateLong(lastSnapshotDate)}</>
                                        : 'nessuno snapshot ancora'}
                                </p>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Congela il valore attuale di tutti gli asset in un punto del grafico. Fanne uno quando vuoi registrare lo stato di oggi.
                            </p>
                            <Button
                                size="sm"
                                onClick={handleSaveSnapshot}
                                disabled={savingSnapshot || assets.length === 0}
                                className="bg-amber-500 hover:bg-amber-600 text-white"
                            >
                                <Camera className="w-4 h-4 mr-1" />
                                {savingSnapshot ? 'Salvando...' : `Fotografa adesso (${formatDateLong(today())})`}
                            </Button>
                        </CardContent>
                    </Card>
                </div>

                {/* Per-category breakdown */}
                {assets.length > 0 && (
                    <div className="grid gap-3" style={{ gridTemplateColumns: 'repeat(auto-fit, minmax(180px, 1fr))' }}>
                        {Object.values(byCat).map((cat) => (
                            <Card key={cat.name}>
                                <CardContent className="p-3">
                                    <p className="text-xs text-muted-foreground flex items-center gap-1">
                                        {cat.icon && <span>{cat.icon}</span>}
                                        {cat.name}
                                    </p>
                                    <p
                                        className="text-base font-bold"
                                        style={{ color: cat.color }}
                                    >
                                        {formatCurrency(cat.total)}
                                    </p>
                                </CardContent>
                            </Card>
                        ))}
                    </div>
                )}

                {/* Main asset card */}
                <Card>
                    <CardHeader className="flex flex-row items-center justify-between pb-3">
                        <CardTitle className="text-base">
                            Asset — {formatMonthLong(month)}
                        </CardTitle>
                        <div className="flex items-center gap-2">
                            <Button
                                variant="default"
                                size="sm"
                                onClick={() => {
                                    setEditAsset(null);
                                    setFormOpen(true);
                                }}
                            >
                                <Plus className="w-4 h-4 mr-1" />
                                Aggiungi
                            </Button>
                            {assets.length === 0 && availableMonths.filter((m) => m !== month).length > 0 && (
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => {
                                        copyForm.setData('source_date', '');
                                        setCopyOpen(true);
                                    }}
                                >
                                    <Copy className="w-4 h-4 mr-1" />
                                    Copia da mese
                                </Button>
                            )}
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <AssetTable
                            assets={assets}
                            onEdit={(a) => {
                                setEditAsset(a);
                                setFormOpen(true);
                            }}
                        />
                    </CardContent>
                </Card>
            </div>

            <AssetForm
                open={formOpen}
                onClose={() => {
                    setFormOpen(false);
                    setEditAsset(null);
                }}
                categories={categories}
                month={month}
                editAsset={editAsset}
                prices={prices}
            />

            <Dialog open={copyOpen} onOpenChange={setCopyOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Copia asset da un altro mese</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Gli asset del mese selezionato verranno copiati in{' '}
                        <strong>{formatMonthLong(month)}</strong> con gli stessi nomi e valori.
                    </p>
                    <Select
                        value={copyForm.data.source_date}
                        onValueChange={(v) => copyForm.setData('source_date', v)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Seleziona mese sorgente" />
                        </SelectTrigger>
                        <SelectContent position="popper" side="bottom" avoidCollisions={false}>
                            {availableMonths
                                .filter((m) => m !== month)
                                .map((m) => (
                                    <SelectItem key={m} value={m}>
                                        {formatMonthLong(m)}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setCopyOpen(false)}>
                            Annulla
                        </Button>
                        <Button
                            onClick={handleCopy}
                            disabled={!copyForm.data.source_date || copyForm.processing}
                        >
                            Copia
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

        </>
    );
}

InputData.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
