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
import { Pencil, Trash2, Plus, Download, Upload, RefreshCw, Layers, Database, Settings as SettingsIcon, RotateCcw, Landmark, Link2, Unlink, ShieldCheck, AlertTriangle, CandlestickChart, ExternalLink, Copy, Check, X } from 'lucide-react';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Separator } from '@/Components/ui/separator';
import { Money } from '@/Components/ui/Money';
import { bankFreshness, brokerFreshness } from '@/lib/metrics';
import { cn } from '@/lib/utils';
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
    expense_ratio: number | null;
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
    synced_at: string | null;
    last_sync_status: 'ok' | 'failed' | null;
    last_sync_error: string | null;
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

interface ScalableLoginState {
    status: 'idle' | 'pending' | 'url_issued' | 'complete' | 'failed';
    url: string | null;
    user_code: string | null;
    error: string | null;
    started_at: string | null;
}

interface ScalableState {
    configured: boolean;
    cli_logged_in: boolean | null;
    last_sync_status: 'ok' | 'failed' | null;
    last_sync_error: string | null;
    last_sync_at: string | null;
    login: ScalableLoginState;
}

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

interface TransactionAsset {
    id: number;
    name: string;
    quantity: number | null;
    transactions_count: number;
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
    const [connecting, setConnecting] = useState<string | null>(null);

    const filtered = query.trim() === ''
        ? banks.slice(0, 30)
        : banks.filter((b) => b.name.toLowerCase().includes(query.trim().toLowerCase())).slice(0, 30);

    const connect = (bank: BankOption) => {
        // Post the chosen bank directly. (Using useForm + setData here would
        // submit stale data on the first click — setData is async.)
        setConnecting(bank.name);
        router.post('/banking/connect', { aspsp_name: bank.name, aspsp_country: bank.country });
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
                                    disabled={connecting !== null}
                                    className="flex w-full items-center justify-between px-3 py-2 text-left text-sm hover:bg-muted/40 transition-colors disabled:opacity-50"
                                >
                                    <span>{bank.name}</span>
                                    <Link2 className="w-3.5 h-3.5 text-muted-foreground" />
                                </button>
                            ))
                        )}
                    </div>
                    {connecting && (
                        <p className="text-xs text-muted-foreground">Reindirizzamento a {connecting}…</p>
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
    const [processing, setProcessing] = useState(false);

    const link = (value: string) => {
        if (value.startsWith('__pending__')) return;
        setProcessing(true);
        router.post(
            `/banking/accounts/${account.id}/link`,
            { asset_id: value === '__none__' ? null : value },
            { preserveScroll: true, onFinish: () => setProcessing(false) },
        );
    };

    // The account is linked but this month's row doesn't exist yet (created on
    // the next sync), so there is no concrete asset id to select — show that as
    // the current value rather than collapsing to "Non collegato".
    const pendingThisMonth = account.linked_name !== null && account.linked_asset_id === null;
    const pendingValue = `__pending__${account.id}`;
    const currentValue = account.linked_asset_id
        ? String(account.linked_asset_id)
        : pendingThisMonth ? pendingValue : '__none__';

    return (
        <Select value={currentValue} onValueChange={link} disabled={processing}>
            <SelectTrigger className="h-8 w-48 text-xs">
                <SelectValue placeholder="Collega a un asset" />
            </SelectTrigger>
            <SelectContent>
                <SelectItem value="__none__">Non collegato</SelectItem>
                {pendingThisMonth && (
                    <SelectItem value={pendingValue} disabled>
                        {account.linked_name} (sync in attesa)
                    </SelectItem>
                )}
                {assets.length === 0 ? (
                    <div className="px-2 py-1.5 text-xs text-muted-foreground">
                        Nessun asset di liquidità questo mese — creane uno in Input.
                    </div>
                ) : (
                    assets.map((a) => (
                        <SelectItem key={a.id} value={String(a.id)}>{a.name}</SelectItem>
                    ))
                )}
            </SelectContent>
        </Select>
    );
}

function BankConnectionsCard({ connections, banks, assets, redirectReady }: { connections: BankConnectionEntry[]; banks: BankOption[]; assets: LinkableAsset[]; redirectReady: boolean }) {
    const [connectOpen, setConnectOpen] = useState(false);
    const [disconnecting, setDisconnecting] = useState<number | null>(null);

    const disconnect = (id: number, name: string) => {
        if (confirm(`Rimuovere il collegamento con ${name}?\n\nGli asset restano, ma vengono persi anche i collegamenti agli asset: dovrai rifarli a mano.\n\nSe vuoi solo rinnovare un consenso scaduto, usa "Riconnetti" invece di rimuovere — mantiene i collegamenti.`)) {
            setDisconnecting(id);
            router.delete(`/banking/connections/${id}`, { preserveScroll: true, onFinish: () => setDisconnecting(null) });
        }
    };

    const statusBadge = (c: BankConnectionEntry) => {
        const until = c.valid_until ? new Date(c.valid_until).toLocaleDateString('it-IT') : null;
        if (c.status === 'active') {
            return <span className="text-xs text-emerald-400">Attivo{until ? ` · scade ${until}` : ''}</span>;
        }
        if (c.status === 'pending') {
            return <span className="text-xs text-amber-500">In attesa di consenso</span>;
        }
        return <span className="text-xs text-destructive">Scaduto{until ? ` il ${until}` : ''}</span>;
    };

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 pb-3">
                <div>
                    <CardTitle className="text-base">Conti bancari</CardTitle>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Collega un conto via open banking (sola lettura) per sincronizzarne il saldo. I saldi si aggiornano con &laquo;Aggiorna ora&raquo; insieme ai prezzi.
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
                                            disabled={disconnecting === c.id}
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
                                            {acc.linked_name && (
                                                acc.last_sync_status === 'failed' ? (
                                                    <span className="block text-xs text-destructive" title={acc.last_sync_error ?? undefined}>
                                                        Ultimo sync fallito
                                                    </span>
                                                ) : (() => {
                                                    const f = bankFreshness(acc.synced_at);
                                                    return (
                                                        <span className={cn('block text-xs', f.stale ? 'text-amber-500' : 'text-muted-foreground')}>
                                                            Saldo {f.label}
                                                        </span>
                                                    );
                                                })()
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

function ScalableConnectionCard({ state, transactionAssets }: { state: ScalableState; transactionAssets: TransactionAsset[] }) {
    const refresh = useForm({});
    const login = useForm({});
    const logout = useForm({});
    const freshness = brokerFreshness(state.last_sync_at);
    const failed = state.last_sync_status === 'failed';
    const needsLogin = state.cli_logged_in === false;

    const cancel = useForm({});
    const [loginFlow, setLoginFlow] = useState<ScalableLoginState>(state.login);
    const [timedOut, setTimedOut] = useState(false);
    const inProgress = !timedOut && (loginFlow.status === 'pending' || loginFlow.status === 'url_issued');
    const [copied, setCopied] = useState(false);

    // Poll the CLI login status while a login is in flight; stop on a terminal
    // state. On completion, refresh the page props so the badge turns live.
    // A device code expires after a few minutes, so cap the wait: if no
    // confirmation lands, stop polling and surface a retry instead of spinning
    // forever on an orphaned "In attesa di conferma…".
    useEffect(() => {
        if (!inProgress) {
            return;
        }
        const controller = new AbortController();
        const id = setInterval(() => {
            void fetch('/scalable/cli/login/status', { signal: controller.signal, headers: { Accept: 'application/json' } })
                .then((r) => r.json())
                .then((next: ScalableLoginState) => {
                    setLoginFlow(next);
                    if (next.status === 'complete') {
                        router.reload({ only: ['scalable'] });
                    }
                })
                .catch(() => undefined);
        }, 2000);
        const giveUp = setTimeout(() => setTimedOut(true), 5 * 60 * 1000);
        return () => {
            clearInterval(id);
            clearTimeout(giveUp);
            controller.abort();
        };
    }, [inProgress]);

    const startCliLogin = () => {
        setTimedOut(false);
        setLoginFlow({ status: 'pending', url: null, user_code: null, error: null, started_at: null });
        login.post('/scalable/cli/login', { preserveScroll: true });
    };

    const cancelCliLogin = () => {
        setTimedOut(false);
        setLoginFlow({ status: 'idle', url: null, user_code: null, error: null, started_at: null });
        cancel.post('/scalable/cli/login/cancel', { preserveScroll: true });
    };

    const copyCode = (code: string) => {
        void navigator.clipboard.writeText(code).then(() => {
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        });
    };

    return (
        <Card>
            <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between sm:gap-0 pb-3">
                <div>
                    <CardTitle className="text-base flex items-center gap-1.5">
                        <CandlestickChart className="w-4 h-4 text-indigo-400" aria-hidden />
                        Scalable Capital
                    </CardTitle>
                    <p className="text-xs text-muted-foreground mt-0.5">
                        Sincronizza saldi e posizioni dal broker (sola lettura) tramite la CLI ufficiale Scalable. Si aggiorna ogni giorno alle 06:00 insieme ai prezzi.
                    </p>
                </div>
                {state.configured && (
                    <div className="flex items-center gap-2">
                        {needsLogin && !inProgress && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={startCliLogin}
                                disabled={login.processing}
                                title="Avvia il login: apri il link mostrato e inserisci il codice + 2FA"
                            >
                                <Link2 className={`w-4 h-4 mr-1 ${login.processing ? 'animate-pulse' : ''}`} />
                                {login.processing ? 'Avvio…' : 'Collega / Riconnetti'}
                            </Button>
                        )}
                        {state.cli_logged_in === true && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => refresh.post('/scalable/refresh', { preserveScroll: true })}
                                disabled={refresh.processing}
                            >
                                <RefreshCw className={`w-4 h-4 mr-1 ${refresh.processing ? 'animate-spin' : ''}`} />
                                Sincronizza ora
                            </Button>
                        )}
                        {state.cli_logged_in === true && (
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => logout.post('/scalable/cli/logout', { preserveScroll: true })}
                                disabled={logout.processing}
                                title="Rimuove la sessione Scalable salvata dalla CLI"
                            >
                                <Unlink className={`w-4 h-4 mr-1 ${logout.processing ? 'animate-pulse' : ''}`} />
                                Scollega
                            </Button>
                        )}
                    </div>
                )}
            </CardHeader>
            <CardContent className="space-y-2">
                {!state.configured ? (
                    <p className="text-xs text-amber-500">
                        Sincronizzazione Scalable non configurata (abilita SCALABLE_CLI_ENABLED).
                    </p>
                ) : (
                    <>
                        <div className="flex items-center gap-1.5 text-sm">
                            {state.cli_logged_in === false ? (
                                <span className="inline-flex items-center gap-1.5 text-amber-500">
                                    <span className="w-1.5 h-1.5 rounded-full bg-amber-500" />
                                    Sessione scaduta, riconnetti
                                </span>
                            ) : failed ? (
                                <span className="inline-flex items-center gap-1.5 text-destructive" title={state.last_sync_error ?? undefined}>
                                    <span className="w-1.5 h-1.5 rounded-full bg-destructive" />
                                    Ultimo sync fallito
                                </span>
                            ) : state.last_sync_at === null ? (
                                <span className="inline-flex items-center gap-1.5 text-muted-foreground">
                                    <span className="w-1.5 h-1.5 rounded-full bg-muted-foreground" />
                                    Mai sincronizzato
                                </span>
                            ) : (
                                <span className={cn('inline-flex items-center gap-1.5', freshness.stale ? 'text-amber-500' : 'text-emerald-400')}>
                                    <span className={cn('w-1.5 h-1.5 rounded-full', freshness.stale ? 'bg-amber-500' : 'bg-emerald-500')} />
                                    {freshness.stale ? 'Sincronizzazione ferma' : 'Connesso'} · {freshness.label}
                                </span>
                            )}
                        </div>

                        {inProgress && (
                            <div className="rounded-md border border-border bg-muted/30 p-3 space-y-2">
                                {loginFlow.status === 'url_issued' && loginFlow.url ? (
                                    <>
                                        <a
                                            href={loginFlow.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="inline-flex items-center gap-1.5 text-sm text-indigo-400 hover:underline"
                                        >
                                            <ExternalLink className="w-3.5 h-3.5" aria-hidden />
                                            Apri Scalable per confermare
                                        </a>
                                        {loginFlow.user_code && (
                                            <div className="flex items-center gap-2">
                                                <span className="text-xs text-muted-foreground">Codice:</span>
                                                <code className="rounded bg-background px-2 py-0.5 font-mono text-sm tracking-wider">{loginFlow.user_code}</code>
                                                <button
                                                    type="button"
                                                    onClick={() => copyCode(loginFlow.user_code as string)}
                                                    className="text-muted-foreground hover:text-foreground"
                                                    title="Copia il codice"
                                                >
                                                    {copied ? <Check className="w-3.5 h-3.5 text-emerald-400" aria-hidden /> : <Copy className="w-3.5 h-3.5" aria-hidden />}
                                                </button>
                                            </div>
                                        )}
                                        <div className="flex items-center justify-between gap-2">
                                            <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                                <RefreshCw className="w-3 h-3 animate-spin" aria-hidden />
                                                In attesa di conferma…
                                            </p>
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                onClick={cancelCliLogin}
                                                disabled={cancel.processing}
                                                title="Annulla il login in corso"
                                                className="h-7 px-2 text-xs text-muted-foreground hover:text-foreground"
                                            >
                                                <X className="w-3.5 h-3.5 mr-1" aria-hidden />
                                                Annulla
                                            </Button>
                                        </div>
                                    </>
                                ) : (
                                    <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                        <RefreshCw className="w-3 h-3 animate-spin" aria-hidden />
                                        Avvio del login in corso…
                                    </p>
                                )}
                            </div>
                        )}

                        {timedOut && (
                            <p className="flex items-start gap-1.5 text-xs text-amber-500">
                                <AlertTriangle className="w-3.5 h-3.5 flex-shrink-0 mt-0.5" aria-hidden />
                                <span>Codice scaduto o login non confermato. Clicca &laquo;Collega / Riconnetti&raquo; per riprovare.</span>
                            </p>
                        )}

                        {loginFlow.status === 'failed' && (
                            <p className="flex items-start gap-1.5 text-xs text-destructive">
                                <AlertTriangle className="w-3.5 h-3.5 flex-shrink-0 mt-0.5" aria-hidden />
                                <span>{loginFlow.error ?? 'Login non riuscito.'} Riprova, oppure da terminale: <code className="font-mono">docker exec -it wealth-tracker-app-1 sc login</code></span>
                            </p>
                        )}

                        {!inProgress && state.cli_logged_in === false && loginFlow.status !== 'failed' && (
                            <p className="flex items-start gap-1.5 text-xs text-muted-foreground">
                                <AlertTriangle className="w-3.5 h-3.5 flex-shrink-0 mt-0.5 text-amber-500" aria-hidden />
                                <span>
                                    Clicca &laquo;Collega / Riconnetti&raquo;: comparirà un link da aprire nel browser e un codice da inserire (con 2FA). Poi la sincronizzazione automatica riprende da sola.
                                </span>
                            </p>
                        )}
                    </>
                )}

                <TransactionAssetsBlock assets={transactionAssets} />
            </CardContent>
        </Card>
    );
}

function SectionHeading({
    icon: Icon,
    title,
    subtitle,
    className,
}: {
    icon: React.ElementType;
    title: string;
    subtitle: string;
    className?: string;
}) {
    return (
        <div className={cn('px-1 pt-2', className)}>
            <div className="flex items-center gap-2">
                <Icon className="w-4 h-4 text-primary flex-shrink-0" />
                <h2 className="text-sm font-semibold tracking-tight">{title}</h2>
            </div>
            <p className="text-xs text-muted-foreground mt-0.5">{subtitle}</p>
            <Separator className="mt-2" />
        </div>
    );
}

// Rendered as a subordinate block inside the broker card, not a peer card:
// these assets are the *result* of imported transactions (today from Scalable,
// but source-agnostic). Returns null when there are none.
function TransactionAssetsBlock({ assets }: { assets: TransactionAsset[] }) {
    const unlink = useForm({});

    if (assets.length === 0) {
        return null;
    }

    const handleUnlink = (asset: TransactionAsset) => {
        if (!confirm(`Scollegare "${asset.name}" dalle transazioni? Le ${asset.transactions_count} transazioni importate verranno rimosse e la quantità (${asset.quantity ?? 0}) tornerà modificabile a mano.`)) {
            return;
        }
        unlink.delete(`/assets/${asset.id}/transactions`, { preserveScroll: true });
    };

    return (
        <div className="mt-1 rounded-md border border-border bg-muted/30 p-3 space-y-2">
            <div className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                <CandlestickChart className="w-3.5 h-3.5" />
                Asset con quantità da transazioni
            </div>
            <p className="text-xs text-muted-foreground">
                La quantità di questi asset è calcolata dalle transazioni importate e non è modificabile a mano. Scollega per rimuovere le transazioni e riprendere il controllo manuale — l&apos;ultima quantità calcolata resta.
            </p>
            <div className="divide-y divide-border rounded-md border border-border bg-background">
                {assets.map((asset) => (
                    <div key={asset.id} className="flex items-center justify-between gap-3 px-3 py-2">
                        <div className="min-w-0">
                            <p className="text-sm font-medium truncate">{asset.name}</p>
                            <p className="text-xs text-muted-foreground">
                                {asset.quantity ?? 0} quote · {asset.transactions_count} transazioni
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            disabled={unlink.processing}
                            onClick={() => handleUnlink(asset)}
                        >
                            <Unlink className="w-4 h-4 mr-1" />
                            Scollega
                        </Button>
                    </div>
                ))}
            </div>
        </div>
    );
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
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
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
                            <div className="flex items-center gap-2 flex-shrink-0">
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
                                className="flex-shrink-0"
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
