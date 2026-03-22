import { useState } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
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
import { Plus, Save, ChevronLeft, ChevronRight, ArrowRightLeft, Copy } from 'lucide-react';
import { formatMonthLong, formatCurrency } from '@/lib/formatters';
import type { Asset, Category } from '@/types/models';

interface Props {
    assets: Asset[];
    categories: Pick<Category, 'id' | 'name' | 'color' | 'icon'>[];
    month: string;
    availableMonths: string[];
    snapshotState: 'missing' | 'stale' | 'current';
}

export default function InputData({ assets, categories, month, availableMonths, snapshotState }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [editAsset, setEditAsset] = useState<Asset | null>(null);
    const [savingSnapshot, setSavingSnapshot] = useState(false);
    const [moveOpen, setMoveOpen] = useState(false);
    const moveForm = useForm({
        asset_ids: [] as number[],
        target_date: '',
    });

    const [copyOpen, setCopyOpen] = useState(false);
    const copyForm = useForm({ source_date: '' });

    const handleSaveSnapshot = () => {
        if (assets.length === 0) {
            alert('Aggiungi almeno un asset prima di salvare lo snapshot.');
            return;
        }
        if (confirm(`Salvare lo snapshot per ${formatMonthLong(month)}?`)) {
            setSavingSnapshot(true);
            router.post('/snapshots', { month }, {
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

    const moveMonthOptions = (() => {
        const [y, m] = month.split('-').map(Number);
        const options: string[] = [];
        for (let i = -24; i <= -1; i++) {
            const d = new Date(y, m - 1 + i, 1);
            const val = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-01`;
            if (val !== month) options.push(val);
        }
        return options.reverse();
    })();

    const handleCopy = () => {
        copyForm.post(`/assets/copy-from-month?month=${month}`, {
            onSuccess: () => setCopyOpen(false),
        });
    };

    const handleOpenMove = () => {
        moveForm.setData({
            asset_ids: assets.map((a) => a.id),
            target_date: '',
        });
        setMoveOpen(true);
    };

    const handleMove = () => {
        moveForm.post('/assets/bulk-move', {
            onSuccess: () => setMoveOpen(false),
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
            <div className="p-6 space-y-6">
                {/* Header row */}
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold">Input Dati</h1>
                        <p className="text-sm text-muted-foreground">
                            Gestisci gli asset per il mese selezionato
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <Button variant="outline" size="icon" onClick={() => navigateMonth('prev')}>
                            <ChevronLeft className="w-4 h-4" />
                        </Button>

                        <Select value={month} onValueChange={handleMonthChange}>
                            <SelectTrigger className="w-44">
                                <SelectValue>{formatMonthLong(month)}</SelectValue>
                            </SelectTrigger>
                            <SelectContent>
                                {availableMonths.map((m) => (
                                    <SelectItem key={m} value={m}>
                                        {formatMonthLong(m)}
                                    </SelectItem>
                                ))}
                                {/* Always show current month */}
                                {!availableMonths.includes(month) && (
                                    <SelectItem value={month}>{formatMonthLong(month)}</SelectItem>
                                )}
                            </SelectContent>
                        </Select>

                        <Button variant="outline" size="icon" onClick={() => navigateMonth('next')}>
                            <ChevronRight className="w-4 h-4" />
                        </Button>
                    </div>
                </div>

                {/* Summary cards */}
                {assets.length > 0 && (
                    <div className="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <Card>
                            <CardContent className="p-4">
                                <p className="text-xs text-muted-foreground">Totale</p>
                                <p className="text-lg font-bold">{formatCurrency(total)}</p>
                            </CardContent>
                        </Card>
                        {Object.values(byCat).map((cat) => (
                            <Card key={cat.name}>
                                <CardContent className="p-4">
                                    <p className="text-xs text-muted-foreground flex items-center gap-1">
                                        {cat.icon && <span>{cat.icon}</span>}
                                        {cat.name}
                                    </p>
                                    <p
                                        className="text-lg font-bold"
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
                        <div className="flex items-center gap-3">
                            <CardTitle className="text-base">
                                Asset — {formatMonthLong(month)}
                            </CardTitle>
                            {assets.length > 0 && snapshotState === 'missing' && (
                                <span className="inline-flex items-center rounded-full bg-amber-500 px-2 py-0.5 text-xs font-medium text-white">
                                    Snapshot non salvato
                                </span>
                            )}
                            {assets.length > 0 && snapshotState === 'stale' && (
                                <span className="inline-flex items-center rounded-full bg-yellow-500 px-2 py-0.5 text-xs font-medium text-white">
                                    Snapshot non aggiornato
                                </span>
                            )}
                            {assets.length > 0 && snapshotState === 'current' && (
                                <span className="inline-flex items-center rounded-full bg-green-500 px-2 py-0.5 text-xs font-medium text-white">
                                    Snapshot aggiornato
                                </span>
                            )}
                        </div>
                        <div className="flex gap-2">
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
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleOpenMove}
                                disabled={assets.length === 0}
                            >
                                <ArrowRightLeft className="w-4 h-4 mr-1" />
                                Sposta mese
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={handleSaveSnapshot}
                                disabled={savingSnapshot || assets.length === 0}
                            >
                                <Save className="w-4 h-4 mr-1" />
                                Salva snapshot
                            </Button>
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

            <Dialog open={moveOpen} onOpenChange={setMoveOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Sposta asset a un altro mese</DialogTitle>
                    </DialogHeader>
                    <p className="text-sm text-muted-foreground">
                        Verranno spostati tutti i {assets.length} asset di{' '}
                        <strong>{formatMonthLong(month)}</strong> al mese selezionato.
                    </p>
                    <Select
                        value={moveForm.data.target_date}
                        onValueChange={(v) => moveForm.setData('target_date', v)}
                    >
                        <SelectTrigger>
                            <SelectValue placeholder="Seleziona mese di destinazione" />
                        </SelectTrigger>
                        <SelectContent position="popper" side="bottom" avoidCollisions={false}>
                            {moveMonthOptions.map((m) => (
                                <SelectItem key={m} value={m}>
                                    {formatMonthLong(m)}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <DialogFooter>
                        <Button variant="outline" onClick={() => setMoveOpen(false)}>
                            Annulla
                        </Button>
                        <Button
                            onClick={handleMove}
                            disabled={!moveForm.data.target_date || moveForm.processing}
                        >
                            Sposta
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

InputData.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
