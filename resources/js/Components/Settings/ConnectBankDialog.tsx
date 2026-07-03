import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Input } from '@/Components/ui/input';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/Components/ui/dialog';
import { Link2, ShieldCheck, AlertTriangle } from 'lucide-react';
import type { BankOption } from '@/Components/Settings/types';

export function ConnectBankDialog({ open, onClose, banks, redirectReady }: { open: boolean; onClose: () => void; banks: BankOption[]; redirectReady: boolean }) {
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
