import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Pencil, Plus, Download, Upload, RefreshCw, Layers, Database, Settings as SettingsIcon, LineChart, Trash2, FileSpreadsheet, Link2, UserCog } from 'lucide-react';
import { Money } from '@/Components/ui/Money';
import type { Category } from '@/types/models';
import { ImportCsvDialog } from '@/Components/Settings/ImportCsvDialog';
import { CategoryDialog } from '@/Components/Settings/CategoryDialog';
import { DeleteCategoryButton } from '@/Components/Settings/DeleteCategoryButton';
import { RestoreButton } from '@/Components/Settings/RestoreButton';
import { BankConnectionsCard } from '@/Components/Settings/BankConnectionsCard';
import { ScalableConnectionCard } from '@/Components/Settings/ScalableConnectionCard';
import { ConnectionRow } from '@/Components/Settings/ConnectionRow';
import { SectionHeading } from '@/Components/Settings/SectionHeading';
import { AccountCard } from '@/Components/Settings/AccountCard';
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
    accountEmail: string | null;
}

export default function Settings({ categories, prices, trashed, bankConnections, banks, linkableAssets, bankRedirectReady, scalable, transactionAssets, accountEmail }: Props) {
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
    const failedPrices = prices.filter((p) => p.last_status === 'failed').length;

    const priceSummary = prices.length === 0
        ? { tone: 'idle' as const, label: 'Nessun prezzo disponibile' }
        : failedPrices > 0
            ? { tone: 'warn' as const, label: `${failedPrices} su ${prices.length} non aggiornati` }
            : {
                tone: 'ok' as const,
                label: `${prices.length} aggiornati${lastPriceUpdate ? ` · ${lastPriceUpdate.toLocaleDateString('it-IT')}` : ''}`,
            };

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

                <Card>
                    <CardContent className="p-0 divide-y divide-border">
                        <ConnectionRow
                            icon={LineChart}
                            title="Prezzi asset live"
                            tone={priceSummary.tone}
                            status={priceSummary.label}
                            actions={
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => refreshForm.post('/prices/refresh')}
                                    disabled={refreshForm.processing}
                                >
                                    <RefreshCw className={`w-4 h-4 mr-1 ${refreshForm.processing ? 'animate-spin' : ''}`} />
                                    Aggiorna ora
                                </Button>
                            }
                        >
                            <p className="text-xs text-muted-foreground">
                                Aggiornati automaticamente ogni giorno alle 06:00
                                {lastPriceUpdate && ` · ultimo aggiornamento ${lastPriceUpdate.toLocaleString('it-IT')}`}
                            </p>
                            {prices.length === 0 ? (
                                <p className="py-4 text-sm text-muted-foreground">
                                    Aggiungi asset con un ticker e clicca &quot;Aggiorna ora&quot;.
                                </p>
                            ) : (
                                <div className="mt-3 rounded-md border border-border overflow-x-auto">
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
                                                                <span className="w-1.5 h-1.5 shrink-0 rounded-full bg-amber-500" />
                                                                {/* The full wording is the widest cell in this
                                                                    table; on a phone the dot and the tooltip
                                                                    carry it. */}
                                                                <span className="hidden sm:inline">Ultimo aggiornamento fallito</span>
                                                                <span className="sm:hidden">Fallito</span>
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
                                </div>
                            )}
                        </ConnectionRow>

                        <BankConnectionsCard connections={bankConnections} banks={banks} assets={linkableAssets} redirectReady={bankRedirectReady} />

                        <ScalableConnectionCard state={scalable} transactionAssets={transactionAssets} />
                    </CardContent>
                </Card>

                {/* ── Dati: import/export, backup, ripristino ── */}
                <SectionHeading
                    icon={Database}
                    title="Dati"
                    subtitle="Import/export, backup del database e ripristino."
                    className="pt-4"
                />

                <Card>
                    <CardContent className="p-0 divide-y divide-border">
                        <ConnectionRow
                            icon={FileSpreadsheet}
                            title="Import / Export"
                            tone="idle"
                            status="Carica o scarica i tuoi dati in formato CSV"
                            defaultOpen={false}
                            actions={
                                <>
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
                                </>
                            }
                        >
                            <p className="text-xs text-muted-foreground">
                                L&apos;export contiene tutti gli asset con data, categoria, valore e note. L&apos;import accetta lo stesso formato: scarica il modello dalla finestra di importazione.
                            </p>
                        </ConnectionRow>

                        <ConnectionRow
                            icon={Database}
                            title="Backup database"
                            tone="idle"
                            status="Automatico ogni notte alle 03:00"
                            defaultOpen={false}
                            actions={
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => backupForm.post('/backup', { preserveScroll: true })}
                                    disabled={backupForm.processing}
                                >
                                    <Database className={`w-4 h-4 mr-1 ${backupForm.processing ? 'animate-pulse' : ''}`} />
                                    Backup ora
                                </Button>
                            }
                        >
                            <p className="text-xs text-muted-foreground">
                                Snapshot atomico del database verso la cartella sincronizzata sul cloud. Sicuro anche mentre l&apos;app scrive; viene eseguito anche a ogni salvataggio di snapshot.
                            </p>
                        </ConnectionRow>

                        {/* Trash — only worth its space when something is in it. */}
                        {trashed.length > 0 && (
                            <ConnectionRow
                                icon={Trash2}
                                title="Elementi eliminati"
                                tone="idle"
                                status={`${trashed.length} element${trashed.length === 1 ? 'o' : 'i'} ripristinabil${trashed.length === 1 ? 'e' : 'i'}`}
                                defaultOpen={false}
                            >
                                <div className="divide-y divide-border rounded-md border border-border">
                                    {trashed.map((item) => (
                                        <div key={`${item.type}-${item.label}-${item.deleted_at}`} className="flex items-center gap-3 px-3 py-2.5 hover:bg-muted/30 transition-colors">
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
                            </ConnectionRow>
                        )}
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
                    <CardContent className="p-0 divide-y divide-border">
                        <ConnectionRow
                            icon={Layers}
                            title="Categorie"
                            tone="idle"
                            status={`${categories.length} categori${categories.length === 1 ? 'a' : 'e'} · ${categories.reduce((n, c) => n + c.assets_count, 0)} asset`}
                            defaultOpen={false}
                            actions={
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
                            }
                        >
                            <div className="divide-y divide-border rounded-md border border-border">
                                {categories.map((cat) => (
                                    <div key={cat.id} className="flex items-center gap-3 px-3 py-2.5 hover:bg-muted/30 transition-colors">
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
                        </ConnectionRow>
                    </CardContent>
                </Card>

                {/* ── Account: credenziali di accesso ── */}
                {accountEmail && (
                    <>
                        <SectionHeading
                            icon={UserCog}
                            title="Account"
                            subtitle="Le credenziali con cui accedi all'app."
                            className="pt-4"
                        />

                        <Card>
                            <CardContent className="p-0 divide-y divide-border">
                                <AccountCard email={accountEmail} />
                            </CardContent>
                        </Card>
                    </>
                )}
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
