import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Pencil, Plus, Download, Upload, RefreshCw, Layers, Database, Settings as SettingsIcon, Link2 } from 'lucide-react';
import { Money } from '@/Components/ui/Money';
import type { Category } from '@/types/models';
import { ImportCsvDialog } from '@/Components/Settings/ImportCsvDialog';
import { CategoryDialog } from '@/Components/Settings/CategoryDialog';
import { DeleteCategoryButton } from '@/Components/Settings/DeleteCategoryButton';
import { RestoreButton } from '@/Components/Settings/RestoreButton';
import { BankConnectionsCard } from '@/Components/Settings/BankConnectionsCard';
import { ScalableConnectionCard } from '@/Components/Settings/ScalableConnectionCard';
import { SectionHeading } from '@/Components/Settings/SectionHeading';
import type {
    PriceEntry,
    TrashedItem,
    BankConnectionEntry,
    BankOption,
    LinkableAsset,
    ScalableState,
    TransactionAsset,
} from '@/Components/Settings/types';

interface Props {
    categories: (Category & { assets_count: number })[];
    prices: PriceEntry[];
    trashed: TrashedItem[];
    bankConnections: BankConnectionEntry[];
    banks: BankOption[];
    linkableAssets: LinkableAsset[];
    bankRedirectReady: boolean;
    scalable: ScalableState;
    transactionAssets: TransactionAsset[];
}

export default function Settings({ categories, prices, trashed, bankConnections, banks, linkableAssets, bankRedirectReady, scalable, transactionAssets }: Props) {
    const [dialogOpen, setDialogOpen] = useState(false);
    const [importOpen, setImportOpen] = useState(false);
    const [editCategory, setEditCategory] = useState<Category | null>(null);
    const refreshForm = useForm({});
    const backupForm = useForm({});

    const fetchedTimes = prices
        .map((p) => p.fetched_at)
        .filter((d): d is string => d !== null)
        .map((d) => new Date(d).getTime());
    const lastPriceUpdate = fetchedTimes.length > 0 ? new Date(Math.max(...fetchedTimes)) : null;

    return (
        <>
            <Head title="Impostazioni" />
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter shrink-0">
                <PageHeader icon={SettingsIcon} title="Impostazioni" />

                {/* ── Connessioni: integrazioni e dati live ── */}
                <SectionHeading
                    icon={Link2}
                    title="Connessioni"
                    subtitle="Integrazioni e dati live: prezzi, banca, broker."
                />

                {/* Prices */}
                <Card>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 pb-3">
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
                                        <TableHead>Stato</TableHead>
                                        <TableHead className="text-right">Costo (TER)</TableHead>
                                        <TableHead className="text-right">Prezzo</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {prices.map((p) => (
                                        <TableRow key={p.ticker}>
                                            <TableCell className="font-mono font-medium">{p.ticker}</TableCell>
                                            <TableCell>
                                                {p.last_status === 'failed' ? (
                                                    <span
                                                        className="inline-flex items-center gap-1.5 text-xs text-amber-500"
                                                        title={p.last_error ?? undefined}
                                                    >
                                                        <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                                                        Ultimo aggiornamento fallito
                                                    </span>
                                                ) : (
                                                    <span className="inline-flex items-center gap-1.5 text-xs text-muted-foreground">
                                                        <span className="w-1.5 h-1.5 rounded-full bg-emerald-500" />
                                                        Aggiornato
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-muted-foreground">
                                                {p.expense_ratio !== null
                                                    ? `${p.expense_ratio.toLocaleString('it-IT', { maximumFractionDigits: 2 })}%`
                                                    : '—'}
                                            </TableCell>
                                            <TableCell className="text-right font-mono">
                                                {p.price !== null ? <Money value={p.price} /> : '—'}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                {/* Bank connections (open banking) */}
                <BankConnectionsCard connections={bankConnections} banks={banks} assets={linkableAssets} redirectReady={bankRedirectReady} />

                {/* Scalable broker sync (stopgap), with the transaction-driven
                    assets it produces as a subordinate block. */}
                <ScalableConnectionCard state={scalable} transactionAssets={transactionAssets} />

                {/* ── Dati: import/export, backup, ripristino ── */}
                <SectionHeading
                    icon={Database}
                    title="Dati"
                    subtitle="Import/export, backup del database e ripristino."
                    className="pt-4"
                />

                <Card>
                    <CardContent className="p-0 divide-y divide-border">
                        {/* Import / Export */}
                        <div className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0">
                            <div className="min-w-0">
                                <p className="text-sm font-medium">Import / Export</p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Carica o scarica i tuoi dati in formato CSV.
                                </p>
                            </div>
                            <div className="flex items-center gap-2 shrink-0">
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
                        </div>

                        {/* Backup */}
                        <div className="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0">
                            <div className="min-w-0">
                                <p className="text-sm font-medium">Backup database</p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Snapshot atomico verso il cloud. Backup automatico ogni notte alle 03:00.
                                </p>
                            </div>
                            <Button
                                size="sm"
                                variant="outline"
                                className="shrink-0"
                                onClick={() => backupForm.post('/backup', { preserveScroll: true })}
                                disabled={backupForm.processing}
                            >
                                <Database className={`w-4 h-4 mr-1 ${backupForm.processing ? 'animate-pulse' : ''}`} />
                                Backup ora
                            </Button>
                        </div>

                        {/* Trash / restore */}
                        <div>
                            <div className="px-4 py-3">
                                <p className="text-sm font-medium">Elementi eliminati</p>
                                <p className="text-xs text-muted-foreground mt-0.5">
                                    Asset, categorie e obiettivi eliminati. Puoi ripristinarli da qui.
                                </p>
                            </div>
                            {trashed.length === 0 ? (
                                <p className="px-4 pb-4 text-sm text-muted-foreground">
                                    Nessun elemento eliminato.
                                </p>
                            ) : (
                                <div className="border-t border-border divide-y divide-border">
                                    {trashed.map((item) => (
                                        <div key={`${item.type}-${item.label}-${item.deleted_at}`} className="flex items-center gap-3 px-4 py-2.5 hover:bg-muted/30 transition-colors">
                                            <span className="text-xs text-muted-foreground w-20 shrink-0">{item.type}</span>
                                            <span className="text-sm font-medium flex-1 truncate">{item.label}</span>
                                            {item.deleted_at && (
                                                <span className="text-xs text-muted-foreground hidden sm:block">
                                                    {new Date(item.deleted_at).toLocaleDateString('it-IT')}
                                                </span>
                                            )}
                                            <RestoreButton url={item.restore_url} />
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>
                    </CardContent>
                </Card>

                {/* ── Categorie: tassonomia degli asset ── */}
                <SectionHeading
                    icon={Layers}
                    title="Categorie"
                    subtitle="La tassonomia con cui classifichi gli asset."
                    className="pt-4"
                />

                <Card>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 pb-3">
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
                                    <div className="w-3 h-3 rounded-full shrink-0" style={{ backgroundColor: cat.color }} />
                                    <span className="text-sm font-medium flex-1 flex items-center gap-1.5">
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
                                    <div className="flex gap-1 shrink-0">
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 text-muted-foreground hover:text-foreground hover:bg-accent"
                                            onClick={() => {
                                                setEditCategory(cat);
                                                setDialogOpen(true);
                                            }}
                                        >
                                            <Pencil className="w-4 h-4" />
                                        </Button>
                                        <DeleteCategoryButton category={cat} />
                                    </div>
                                </div>
                            ))}
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
            <ImportCsvDialog open={importOpen} onClose={() => setImportOpen(false)} />
        </>
    );
}

Settings.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
