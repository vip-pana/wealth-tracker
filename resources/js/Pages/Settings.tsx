import { Head, useForm, router } from '@inertiajs/react';
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
import { Pencil, Trash2, Plus, Download, Upload, RefreshCw, Layers, Database, Settings as SettingsIcon, RotateCcw, Landmark, Link2, Unlink, ShieldCheck, AlertTriangle } from 'lucide-react';
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

// Well-separated hues so categories stay distinguishable in charts.
const CATEGORY_PALETTE = [
    '#6366f1', // indigo
    '#0ce708', // green
    '#f7931a', // orange
    '#d4af37', // gold
    '#ef4444', // red
    '#06b6d4', // cyan
    '#a855f7', // purple
    '#ec4899', // pink
    '#64748b', // slate
    '#fcfcfc', // white
] as const;

/** Mask an IBAN to first 4 + last 4 chars (e.g. IT09 ···· 0125); null if empty. */
function maskIban(iban: string | null): string | null {
    if (!iban || iban.length < 8) return iban || null;
    return `${iban.slice(0, 4)} ···· ${iban.slice(-4)}`;
}

interface PriceEntry {
    ticker: string;
    price: number | null;
    currency: string;
    fetched_at: string | null;
    last_status: 'ok' | 'failed' | null;
    last_attempt_at: string | null;
    last_error: string | null;
}

interface TrashedItem {
    type: string;
    label: string;
    deleted_at: string | null;
    restore_url: string;
}

interface BankAccountEntry {
    id: number;
    iban: string | null;
    name: string | null;
    linked_asset_id: number | null;
    linked_name: string | null;
}

interface BankConnectionEntry {
    id: number;
    status: 'active' | 'pending' | 'expired';
    aspsp_name: string;
    aspsp_country: string;
    valid_until: string | null;
    accounts: BankAccountEntry[];
}

interface BankOption {
    name: string;
    country: string;
}

interface LinkableAsset {
    id: number;
    name: string;
}

interface Props {
    categories: (Category & { assets_count: number })[];
    prices: PriceEntry[];
    trashed: TrashedItem[];
    bankConnections: BankConnectionEntry[];
    banks: BankOption[];
    linkableAssets: LinkableAsset[];
    bankRedirectReady: boolean;
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
                        <div className="flex flex-wrap gap-1.5 pt-1">
                            {CATEGORY_PALETTE.map((c) => (
                                <button
                                    key={c}
                                    type="button"
                                    onClick={() => setData('color', c)}
                                    className={`w-6 h-6 rounded-full border transition-transform hover:scale-110 ${data.color.toLowerCase() === c.toLowerCase() ? 'border-foreground ring-2 ring-foreground/30' : 'border-border'}`}
                                    style={{ backgroundColor: c }}
                                    aria-label={`Colore ${c}`}
                                />
                            ))}
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
            className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-accent"
            onClick={handleDelete}
            disabled={processing || category.assets_count > 0}
            title={category.assets_count > 0 ? 'Categoria in uso' : 'Elimina'}
        >
            <Trash2 className="w-4 h-4" />
        </Button>
    );
}

function RestoreButton({ url }: { url: string }) {
    const { post, processing } = useForm({});

    return (
        <Button
            variant="outline"
            size="sm"
            className="h-8 flex-shrink-0"
            onClick={() => post(url, { preserveScroll: true })}
            disabled={processing}
        >
            <RotateCcw className="w-3.5 h-3.5 mr-1" />
            Ripristina
        </Button>
    );
}

function ConnectBankDialog({ open, onClose, banks, redirectReady }: { open: boolean; onClose: () => void; banks: BankOption[]; redirectReady: boolean }) {
    const [query, setQuery] = useState('');
    const { data, setData, post, processing } = useForm({ aspsp_name: '', aspsp_country: 'IT' });

    const filtered = query.trim() === ''
        ? banks.slice(0, 30)
        : banks.filter((b) => b.name.toLowerCase().includes(query.trim().toLowerCase())).slice(0, 30);

    const connect = (bank: BankOption) => {
        setData({ aspsp_name: bank.name, aspsp_country: bank.country });
        // Submitting redirects the browser to the bank's consent page.
        post('/banking/connect');
    };

    return (
        <Dialog open={open} onOpenChange={(o) => !o && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Collega un conto bancario</DialogTitle>
                </DialogHeader>
                <div className="space-y-3">
                    {!redirectReady && (
                        <div className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 px-3 py-2 text-xs text-amber-500">
                            <AlertTriangle className="w-4 h-4 flex-shrink-0 mt-0.5" />
                            <span>
                                Serve un tunnel HTTPS attivo per il consenso. Avvia <code>cloudflared</code> e imposta <code>ENABLE_BANKING_REDIRECT_URL</code> (vedi <code>docs/enable-banking-usage.md</code>), altrimenti il collegamento fallirà.
                            </span>
                        </div>
                    )}
                    <p className="text-sm text-muted-foreground">
                        Scegli la tua banca: ti porteremo sul <strong>sito della banca</strong> per autorizzare l&apos;accesso, poi tornerai qui.
                    </p>
                    <div className="flex items-start gap-2 rounded-md border border-border bg-muted/40 px-3 py-2 text-xs text-muted-foreground">
                        <ShieldCheck className="w-4 h-4 flex-shrink-0 text-emerald-400 mt-0.5" />
                        <span>
                            Solo lettura del saldo: non possiamo muovere denaro. Le credenziali le inserisci sulla banca, non qui. Puoi scollegare quando vuoi.
                        </span>
                    </div>
                    <Input
                        placeholder="Cerca la tua banca…"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        autoFocus
                    />
                    <div className="max-h-72 overflow-y-auto divide-y divide-border rounded-md border border-border">
                        {filtered.length === 0 ? (
                            <p className="px-3 py-4 text-center text-sm text-muted-foreground">Nessuna banca trovata.</p>
                        ) : (
                            filtered.map((bank) => (
                                <button
                                    key={bank.name}
                                    type="button"
                                    onClick={() => connect(bank)}
                                    disabled={processing}
                                    className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-muted/40 transition-colors disabled:opacity-50"
                                >
                                    <span>{bank.name}</span>
                                    <Link2 className="w-3.5 h-3.5 text-muted-foreground" />
                                </button>
                            ))
                        )}
                    </div>
                    {data.aspsp_name && processing && (
                        <p className="text-xs text-muted-foreground">Reindirizzamento a {data.aspsp_name}…</p>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    );
}

function ReconnectButton({ connection }: { connection: BankConnectionEntry }) {
    const { post, processing } = useForm({ aspsp_name: connection.aspsp_name, aspsp_country: connection.aspsp_country });

    return (
        <Button
            variant="outline"
            size="sm"
            className="h-7 text-xs"
            onClick={() => post('/banking/connect')}
            disabled={processing}
        >
            <RotateCcw className="w-3.5 h-3.5 mr-1" />
            Riconnetti
        </Button>
    );
}

function LinkAccountSelect({ account, assets }: { account: BankAccountEntry; assets: LinkableAsset[] }) {
    const link = (value: string) => {
        const assetId = value === '__none__' ? null : value;
        router.post(`/banking/accounts/${account.id}/link`, { asset_id: assetId }, { preserveScroll: true });
    };

    return (
        <Select value={account.linked_asset_id ? String(account.linked_asset_id) : '__none__'} onValueChange={link}>
            <SelectTrigger className="h-8 w-48 text-xs">
                <SelectValue placeholder={account.linked_name ? `${account.linked_name} (questo mese non ancora creato)` : 'Collega a un asset'} />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="__none__">Non collegato</SelectItem>
                {assets.map((a) => (
                    <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                ))}
            </SelectContent>
        </Select>
    );
}

function BankConnectionsCard({ connections, banks, assets, redirectReady }: { connections: BankConnectionEntry[]; banks: BankOption[]; assets: LinkableAsset[]; redirectReady: boolean }) {
    const [connectOpen, setConnectOpen] = useState(false);

    const disconnect = (id: number, name: string) => {
        if (confirm(`Rimuovere il collegamento con ${name}? Gli asset restano, ma non verranno più aggiornati automaticamente.`)) {
            router.delete(`/banking/connections/${id}`, { preserveScroll: true });
        }
    };

    const statusBadge = (c: BankConnectionEntry) => {
        if (c.status === 'active') {
            const until = c.valid_until ? new Date(c.valid_until).toLocaleDateString('it-IT') : null;
            return <span className="text-xs text-green-500">Attivo{until ? ` · scade ${until}` : ''}</span>;
        }
        if (c.status === 'pending') {
            return <span className="text-xs text-amber-500">In attesa di consenso</span>;
        }
        return <span className="text-xs text-destructive">Scaduto</span>;
    };

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 pb-3">
                <div>
                    <CardTitle className="text-base">Conti bancari</CardTitle>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Collega un conto via open banking (sola lettura) per sincronizzarne il saldo.
                    </p>
                </div>
                <Button size="sm" variant="outline" onClick={() => setConnectOpen(true)} disabled={banks.length === 0}>
                    <Landmark className="w-4 h-4 mr-1" />
                    Collega conto
                </Button>
            </CardHeader>
            <CardContent className="p-0">
                {banks.length === 0 && (
                    <p className="px-4 py-3 text-xs text-amber-500">
                        Enable Banking non configurato (chiave/credenziali mancanti).
                    </p>
                )}
                {connections.length === 0 ? (
                    <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                        Nessun conto collegato.
                    </p>
                ) : (
                    <div className="divide-y divide-border">
                        {connections.map((c) => (
                            <div key={c.id} className="px-4 py-3 space-y-2">
                                <div className="flex items-center justify-between">
                                    <div className="flex items-center gap-2">
                                        <span className="text-sm font-medium">{c.aspsp_name}</span>
                                        {statusBadge(c)}
                                    </div>
                                    <div className="flex items-center gap-1">
                                        {c.status === 'expired' && <ReconnectButton connection={c} />}
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="h-8 w-8 text-muted-foreground hover:text-destructive hover:bg-accent"
                                            onClick={() => disconnect(c.id, c.aspsp_name)}
                                            title="Rimuovi collegamento"
                                        >
                                            <Unlink className="w-4 h-4" />
                                        </Button>
                                    </div>
                                </div>
                                {c.accounts.map((acc) => (
                                    <div key={acc.id} className="flex items-center justify-between gap-3 pl-2">
                                        <div className="min-w-0">
                                            <span className="block text-xs font-mono text-foreground truncate">
                                                {maskIban(acc.iban) ?? acc.name ?? `Conto ${acc.id}`}
                                            </span>
                                            {acc.name && acc.iban && (
                                                <span className="block text-xs text-muted-foreground truncate">{acc.name}</span>
                                            )}
                                        </div>
                                        <LinkAccountSelect account={acc} assets={assets} />
                                    </div>
                                ))}
                            </div>
                        ))}
                    </div>
                )}
            </CardContent>
            <ConnectBankDialog open={connectOpen} onClose={() => setConnectOpen(false)} banks={banks} redirectReady={redirectReady} />
        </Card>
    );
}

export default function Settings({ categories, prices, trashed, bankConnections, banks, linkableAssets, bankRedirectReady }: Props) {
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
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader icon={SettingsIcon} title="Impostazioni" />

                {/* Categories */}
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
                                                        <span className="w-1.5 h-1.5 rounded-full bg-green-500" />
                                                        Aggiornato
                                                    </span>
                                                )}
                                            </TableCell>
                                            <TableCell className="text-right font-mono">
                                                {p.price !== null ? formatCurrency(p.price) : '—'}
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

                {/* Import / Export */}
                <Card>
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 pb-3">
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
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 pb-3">
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

                {/* Trash / restore */}
                <Card>
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">Elementi eliminati</CardTitle>
                        <p className="text-xs text-muted-foreground mt-0.5">
                            Asset, categorie e obiettivi eliminati. Puoi ripristinarli da qui.
                        </p>
                    </CardHeader>
                    <CardContent className="p-0">
                        {trashed.length === 0 ? (
                            <p className="px-4 py-6 text-center text-sm text-muted-foreground">
                                Nessun elemento eliminato.
                            </p>
                        ) : (
                            <div className="divide-y divide-border">
                                {trashed.map((item) => (
                                    <div key={`${item.type}-${item.label}-${item.deleted_at}`} className="flex items-center gap-3 px-4 py-2.5 hover:bg-muted/30 transition-colors">
                                        <span className="text-xs text-muted-foreground w-20 flex-shrink-0">{item.type}</span>
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
