import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Markdown } from '@/Components/ui/Markdown';
import { useToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { ProfileDialog, ProfileSummary, type InvestorProfile } from '@/Components/Advisor/ProfileDialog';
import {
    Sparkles, AlertTriangle, Loader2, MessageSquarePlus, Trash2, FileText, MessageCircle, Send, UserCog,
} from 'lucide-react';

type Status = 'pending' | 'done' | 'failed';
type Kind = 'report' | 'chat' | string;

interface SessionSummary {
    id: number;
    kind: Kind;
    title: string | null;
    status: Status;
    created_at: string | null;
}

interface Message {
    id: number;
    role: 'assistant' | 'user';
    content: string;
    created_at: string | null;
}

interface ActiveSession {
    id: number;
    kind: Kind;
    title: string | null;
    status: Status;
    error: string | null;
    messages: Message[];
}

interface Props {
    configured: boolean;
    profile: InvestorProfile | null;
    goalObjective: string | null;
    sessions: SessionSummary[];
    activeSession: ActiveSession | null;
}

function KindIcon({ kind, className }: { kind: Kind; className?: string }) {
    const Icon = kind === 'report' ? FileText : MessageCircle;
    return <Icon className={className} />;
}

function SessionList({
    sessions,
    activeId,
    onGenerate,
    onNewChat,
    generating,
}: {
    sessions: SessionSummary[];
    activeId: number | null;
    onGenerate: () => void;
    onNewChat: () => void;
    generating: boolean;
}) {
    return (
        <div className="flex flex-col gap-2">
            <div className="flex gap-2">
                <Button size="sm" className="flex-1" onClick={onGenerate} disabled={generating}>
                    {generating ? <Loader2 className="w-4 h-4 mr-1 animate-spin" /> : <Sparkles className="w-4 h-4 mr-1" />}
                    Genera analisi
                </Button>
                <Button size="sm" variant="outline" onClick={onNewChat} title="Nuova conversazione">
                    <MessageSquarePlus className="w-4 h-4" />
                </Button>
            </div>

            {sessions.length === 0 ? (
                <p className="px-1 py-4 text-center text-xs text-muted-foreground">
                    Nessuna sessione. Genera un&apos;analisi o avvia una chat.
                </p>
            ) : (
                <div className="flex flex-col gap-1">
                    {sessions.map((s) => (
                        <button
                            key={s.id}
                            onClick={() => router.visit(`/advisor/${s.id}`)}
                            className={cn(
                                'flex items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors',
                                s.id === activeId ? 'bg-primary/10 text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                            )}
                        >
                            <KindIcon kind={s.kind} className="w-3.5 h-3.5 flex-shrink-0" />
                            <span className="flex-1 min-w-0 truncate">{s.title ?? 'Sessione'}</span>
                            {s.status === 'pending' && <Loader2 className="w-3 h-3 flex-shrink-0 animate-spin" />}
                            {s.status === 'failed' && <AlertTriangle className="w-3 h-3 flex-shrink-0 text-amber-500" />}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function MessageBubble({ message }: { message: Message }) {
    if (message.role === 'user') {
        return (
            <div className="flex justify-end">
                <div className="max-w-[85%] rounded-lg bg-primary px-3 py-2 text-sm text-primary-foreground whitespace-pre-wrap">
                    {message.content}
                </div>
            </div>
        );
    }
    return (
        <div className="flex items-start gap-2">
            <div className="mt-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                <Sparkles className="h-3.5 w-3.5 text-primary" />
            </div>
            <div className="min-w-0 flex-1">
                <Markdown content={message.content} />
            </div>
        </div>
    );
}

function Conversation({
    session,
    configured,
    onSent,
}: {
    session: ActiveSession;
    configured: boolean;
    onSent: () => void;
}) {
    const [messages, setMessages] = useState<Message[]>(session.messages);
    const [status, setStatus] = useState<Status>(session.status);
    const [error, setError] = useState<string | null>(session.error);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const pushToast = useToast();
    const bottomRef = useRef<HTMLDivElement>(null);
    const prevStatus = useRef<Status>(session.status);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, sending]);

    // Poll while a report session's opening analysis is generating.
    useEffect(() => {
        if (status !== 'pending') return;
        let cancelled = false;
        let timer: ReturnType<typeof setTimeout> | undefined;
        const tick = async () => {
            try {
                const { data } = await axios.get<{ status: Status; error: string | null; messages: Message[] }>(`/advisor/${session.id}/status`);
                if (cancelled) return;
                setStatus(data.status);
                setMessages(data.messages);
                setError(data.error);
                if (data.status === 'pending') timer = setTimeout(tick, 2500);
            } catch {
                // transient; stop quietly
            }
        };
        void tick();
        return () => { cancelled = true; if (timer) clearTimeout(timer); };
    }, [status, session.id]);

    // Toast on a real pending→done/failed transition for the report.
    useEffect(() => {
        const from = prevStatus.current;
        prevStatus.current = status;
        if (from === 'pending' && status === 'done') pushToast('Analisi completata.', 'success');
        else if (from === 'pending' && status === 'failed') pushToast('Generazione non riuscita.', 'error');
    }, [status, pushToast]);

    const send = async () => {
        const text = input.trim();
        if (text === '' || sending) return;
        setInput('');
        setSending(true);
        const optimisticId = -Date.now();
        setMessages((m) => [...m, { id: optimisticId, role: 'user', content: text, created_at: null }]);
        try {
            const { data } = await axios.post<{ message: Message }>(`/advisor/${session.id}/message`, { message: text });
            setMessages((m) => [...m, data.message]);
            onSent();
        } catch (e) {
            const msg = axios.isAxiosError(e) && typeof e.response?.data?.error === 'string'
                ? e.response.data.error
                : 'Il consulente non ha risposto. Riprova.';
            pushToast(msg, 'error');
            setMessages((m) => m.filter((x) => x.id !== optimisticId));
            setInput(text);
        } finally {
            setSending(false);
        }
    };

    const pending = status === 'pending';

    return (
        <Card className="flex flex-col h-[calc(100vh-13rem)]">
            <CardContent className="flex-1 overflow-y-auto p-4 space-y-4">
                {pending && messages.length === 0 && (
                    <div className="flex items-center gap-2 text-sm text-muted-foreground">
                        <Loader2 className="w-4 h-4 animate-spin" />
                        Analisi in corso… il modello locale può impiegare qualche decina di secondi.
                    </div>
                )}
                {status === 'failed' && (
                    <div className="flex items-start gap-2 text-sm text-red-500">
                        <AlertTriangle className="w-4 h-4 flex-shrink-0 mt-0.5" />
                        <span>{error ?? 'Generazione non riuscita.'}</span>
                    </div>
                )}
                {messages.map((m) => <MessageBubble key={m.id} message={m} />)}
                {sending && (
                    <div className="flex items-center gap-2 text-xs text-muted-foreground">
                        <Loader2 className="w-3.5 h-3.5 animate-spin" />
                        Il consulente sta scrivendo…
                    </div>
                )}
                <div ref={bottomRef} />
            </CardContent>

            {configured && !pending && (
                <div className="border-t border-border p-3">
                    <div className="flex items-end gap-2">
                        <textarea
                            value={input}
                            onChange={(e) => setInput(e.target.value)}
                            onKeyDown={(e) => {
                                if (e.key === 'Enter' && !e.shiftKey) {
                                    e.preventDefault();
                                    void send();
                                }
                            }}
                            placeholder="Chiedi al tuo consulente… (es. la mia liquidità è troppa?)"
                            rows={1}
                            className="flex-1 resize-none rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                        />
                        <Button size="icon" onClick={() => void send()} disabled={sending || input.trim() === ''}>
                            <Send className="w-4 h-4" />
                        </Button>
                    </div>
                </div>
            )}
        </Card>
    );
}

export default function Advisor({ configured, profile, goalObjective, sessions, activeSession }: Props) {
    const [profileOpen, setProfileOpen] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [chatMode, setChatMode] = useState(false);
    const [firstChat, setFirstChat] = useState('');
    const [startingChat, setStartingChat] = useState(false);
    const pushToast = useToast();

    const generate = async () => {
        setGenerating(true);
        try {
            const { data } = await axios.post<{ session_id: number }>('/advisor/generate');
            router.visit(`/advisor/${data.session_id}`);
        } catch (e) {
            const msg = axios.isAxiosError(e) && typeof e.response?.data?.error === 'string'
                ? e.response.data.error
                : 'Generazione non riuscita.';
            pushToast(msg, 'error');
            setGenerating(false);
        }
    };

    const startChat = async () => {
        const text = firstChat.trim();
        if (text === '' || startingChat) return;
        setStartingChat(true);
        try {
            const { data } = await axios.post<{ session_id: number }>('/advisor/chat', { message: text });
            router.visit(`/advisor/${data.session_id}`);
        } catch (e) {
            const msg = axios.isAxiosError(e) && typeof e.response?.data?.error === 'string'
                ? e.response.data.error
                : 'Il consulente non ha risposto. Riprova.';
            pushToast(msg, 'error');
            setStartingChat(false);
        }
    };

    const deleteSession = (id: number) => {
        if (!confirm('Eliminare questa sessione e la sua conversazione?')) return;
        router.delete(`/advisor/${id}`, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Consulente AI" />
            <div className="p-4 space-y-4 max-w-[1400px] mx-auto w-full animate-page-enter">
                <PageHeader
                    icon={Sparkles}
                    title="Consulente AI"
                    subtitle="Genera un'analisi o parla col tuo consulente — le sessioni restano salvate"
                    actions={
                        <Button variant="outline" size="sm" onClick={() => setProfileOpen(true)}>
                            <UserCog className="w-4 h-4 mr-1" />
                            Profilo
                        </Button>
                    }
                />

                {!configured ? (
                    <Card>
                        <CardContent className="py-8 text-center space-y-2">
                            <AlertTriangle className="w-8 h-8 text-amber-500 mx-auto" />
                            <p className="text-sm text-muted-foreground max-w-md mx-auto">
                                Il consulente AI non è configurato. Imposta un modello locale (Ollama) tramite <code>OLLAMA_MODEL</code> per usarlo.
                            </p>
                        </CardContent>
                    </Card>
                ) : (
                    <div className="grid grid-cols-1 lg:grid-cols-[260px_1fr] gap-4 items-start">
                        <div className="space-y-3">
                            <ProfileSummary profile={profile} onEdit={() => setProfileOpen(true)} />
                            <SessionList
                                sessions={sessions}
                                activeId={activeSession?.id ?? null}
                                onGenerate={generate}
                                onNewChat={() => setChatMode(true)}
                                generating={generating}
                            />
                        </div>

                        <div>
                            {activeSession ? (
                                <div className="space-y-2">
                                    <div className="flex items-center justify-between">
                                        <h2 className="text-sm font-medium truncate flex items-center gap-2">
                                            <KindIcon kind={activeSession.kind} className="w-4 h-4 text-primary" />
                                            {activeSession.title ?? 'Sessione'}
                                        </h2>
                                        <Button
                                            variant="ghost" size="icon"
                                            className="h-7 w-7 text-muted-foreground hover:text-destructive"
                                            onClick={() => deleteSession(activeSession.id)}
                                            title="Elimina sessione"
                                        >
                                            <Trash2 className="w-4 h-4" />
                                        </Button>
                                    </div>
                                    <Conversation
                                        key={activeSession.id}
                                        session={activeSession}
                                        configured={configured}
                                        onSent={() => router.reload({ only: ['sessions'] })}
                                    />
                                </div>
                            ) : chatMode ? (
                                <Card>
                                    <CardContent className="p-4 space-y-3">
                                        <p className="text-sm font-medium">Nuova conversazione</p>
                                        <p className="text-xs text-muted-foreground">
                                            Chiedi quello che vuoi sul tuo portafoglio, una decisione, la tua strategia o l&apos;obiettivo.
                                        </p>
                                        <textarea
                                            value={firstChat}
                                            onChange={(e) => setFirstChat(e.target.value)}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); void startChat(); }
                                            }}
                                            placeholder="es. Ho troppa liquidità ferma? Mi conviene aumentare il PAC?"
                                            rows={3}
                                            autoFocus
                                            className="w-full resize-y rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                                        />
                                        <div className="flex justify-end gap-2">
                                            <Button variant="outline" size="sm" onClick={() => { setChatMode(false); setFirstChat(''); }}>
                                                Annulla
                                            </Button>
                                            <Button size="sm" onClick={() => void startChat()} disabled={startingChat || firstChat.trim() === ''}>
                                                {startingChat ? <Loader2 className="w-4 h-4 mr-1 animate-spin" /> : <Send className="w-4 h-4 mr-1" />}
                                                Invia
                                            </Button>
                                        </div>
                                    </CardContent>
                                </Card>
                            ) : (
                                <Card>
                                    <CardContent className="py-12 text-center space-y-2">
                                        <Sparkles className="w-8 h-8 text-primary/60 mx-auto" />
                                        <p className="text-sm text-muted-foreground max-w-sm mx-auto">
                                            Genera un&apos;analisi del tuo portafoglio o avvia una conversazione. Le sessioni restano salvate qui a sinistra.
                                        </p>
                                    </CardContent>
                                </Card>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <ProfileDialog
                open={profileOpen}
                onClose={() => setProfileOpen(false)}
                profile={profile}
                goalObjective={goalObjective}
            />
        </>
    );
}

Advisor.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
