import { useState, useEffect, useRef, useMemo } from 'react';
import axios from 'axios';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Markdown } from '@/Components/ui/Markdown';
import { useToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { ProfileDialog, type InvestorProfile } from '@/Components/Advisor/ProfileDialog';
import {
    Sparkles, AlertTriangle, Loader2, MessageSquarePlus, Trash2, FileText, MessageCircle, Send, UserCog, Pencil, Check, X, ChevronDown,
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
    funFacts: string[];
}

// Pool of conversation starters; 3 are drawn per session. Phrased as things to
// understand/evaluate (never "buy X"), matching the advisor's ethical boundary.
const SUGGESTED_QUESTIONS = [
    'La mia liquidità ferma è troppa?',
    'Quanto sono concentrato e dovrei preoccuparmi?',
    'Come sta andando davvero il mio rendimento?',
    'Il mio portafoglio è coerente col mio profilo di rischio?',
    'Quanto incidono i costi di gestione sul lungo periodo?',
    'Sono in linea con il mio obiettivo?',
    'Il mio PAC è abbastanza per raggiungere l’obiettivo?',
    'Quali sono i rischi principali del mio portafoglio?',
    'Cosa dovrei controllare questo mese?',
    'La mia esposizione a Bitcoin è troppo alta?',
];

/** Pick `count` distinct questions, varied by the session id so they're stable per session. */
function pickQuestions(seed: number, count: number): string[] {
    const pool = [...SUGGESTED_QUESTIONS];
    const out: string[] = [];
    let s = seed + 1;
    while (out.length < count && pool.length > 0) {
        s = (s * 1103515245 + 12345) & 0x7fffffff; // deterministic LCG, varies by seed
        out.push(pool.splice(s % pool.length, 1)[0]);
    }
    return out;
}

function KindIcon({ kind, className }: { kind: Kind; className?: string }) {
    const Icon = kind === 'report' ? FileText : MessageCircle;
    return <Icon className={className} />;
}

// The title typewriter plays only for a session the user just created. On
// creation we stamp its id here (survives the `router.visit` navigation, unlike
// module state, and a normal page refresh doesn't set it) and TypewriterText
// consumes the stamp — so a refresh, a rename, or reopening an old session all
// render the title plainly.
const PENDING_TITLE_ANIM_KEY = 'advisor:animate-title-id';

function markSessionForTitleAnimation(id: number): void {
    try {
        sessionStorage.setItem(PENDING_TITLE_ANIM_KEY, String(id));
    } catch {
        // sessionStorage unavailable (private mode / SSR) — skip the animation.
    }
}

// Ids resolved to "animate" this page-load. Both title spots (header + list
// row) share the same id, so we resolve the sessionStorage stamp once and let
// every instance agree — otherwise the first to mount would consume the stamp
// and the other would render plainly.
const claimedTitleAnims = new Map<number, boolean>();

function claimTitleAnimation(id: number): boolean {
    const cached = claimedTitleAnims.get(id);
    if (cached !== undefined) return cached;

    let claimed = false;
    try {
        if (sessionStorage.getItem(PENDING_TITLE_ANIM_KEY) === String(id)) {
            sessionStorage.removeItem(PENDING_TITLE_ANIM_KEY);
            claimed = true;
        }
    } catch {
        // ignore
    }
    claimedTitleAnims.set(id, claimed);
    return claimed;
}

/**
 * Types a title out character by character, but only for the freshly created
 * session (claimed once from sessionStorage). Every other case — refresh,
 * rename, reopening an old session — renders the title plainly.
 */
function TypewriterText({ id, text, className }: { id: number; text: string; className?: string }) {
    // Claim once at mount: the first render for the just-created id animates,
    // and the claim is cleared so a later refresh won't replay it.
    const [shouldAnimate] = useState(() => claimTitleAnimation(id));
    const [shown, setShown] = useState(shouldAnimate ? '' : text);

    useEffect(() => {
        if (!shouldAnimate) {
            setShown(text);
            return;
        }
        let i = 0;
        const timer = setInterval(() => {
            i += 1;
            setShown(text.slice(0, i));
            if (i >= text.length) clearInterval(timer);
        }, 32);
        return () => clearInterval(timer);
        // Keyed on id only: a title that changes mid-life (rename) must not
        // retrigger the typewriter.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [id]);

    return (
        <span className={className}>
            {shown}
            {shouldAnimate && shown.length < text.length && (
                <span className="inline-block w-[1px] animate-pulse">|</span>
            )}
        </span>
    );
}

function SessionRow({ s, activeId, onRename }: { s: SessionSummary; activeId: number | null; onRename: (id: number, title: string) => void }) {
    const isActive = s.id === activeId;
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(s.title ?? '');

    const startEditing = () => { setDraft(s.title ?? ''); setEditing(true); };
    const commit = () => {
        const next = draft.trim();
        if (next !== '' && next !== (s.title ?? '')) onRename(s.id, next);
        setEditing(false);
    };

    if (editing) {
        return (
            <div className="flex items-center gap-1 rounded-md bg-primary/10 px-2 py-1.5">
                <KindIcon kind={s.kind} className="w-3.5 h-3.5 flex-shrink-0 text-muted-foreground" />
                <input
                    value={draft}
                    autoFocus
                    maxLength={120}
                    onChange={(e) => setDraft(e.target.value)}
                    onKeyDown={(e) => {
                        if (e.key === 'Enter') { e.preventDefault(); commit(); }
                        else if (e.key === 'Escape') { e.preventDefault(); setEditing(false); }
                    }}
                    onBlur={commit}
                    className="flex-1 min-w-0 bg-transparent text-sm focus:outline-none"
                />
                <button type="button" className="flex-shrink-0 text-muted-foreground hover:text-foreground" title="Salva" onMouseDown={(e) => { e.preventDefault(); commit(); }}>
                    <Check className="w-3.5 h-3.5" />
                </button>
                <button type="button" className="flex-shrink-0 text-muted-foreground hover:text-foreground" title="Annulla" onMouseDown={(e) => { e.preventDefault(); setEditing(false); }}>
                    <X className="w-3.5 h-3.5" />
                </button>
            </div>
        );
    }

    return (
        <div
            onClick={() => { if (!isActive) router.visit(`/advisor/${s.id}`); }}
            className={cn(
                'group flex items-center gap-2 rounded-md px-2 py-2 text-left text-sm transition-colors cursor-pointer',
                isActive ? 'bg-primary/10 text-foreground' : 'text-muted-foreground hover:bg-accent hover:text-accent-foreground',
            )}
        >
            <KindIcon kind={s.kind} className="w-3.5 h-3.5 flex-shrink-0" />
            <TypewriterText id={s.id} text={s.title ?? 'Sessione'} className="flex-1 min-w-0 truncate" />
            {s.status === 'pending' && <Loader2 className="w-3 h-3 flex-shrink-0 animate-spin" />}
            {s.status === 'failed' && <AlertTriangle className="w-3 h-3 flex-shrink-0 text-amber-500" />}
            <button
                type="button"
                className="flex-shrink-0 text-muted-foreground opacity-0 transition-opacity hover:text-foreground group-hover:opacity-100"
                title="Rinomina sessione"
                onClick={(e) => { e.stopPropagation(); startEditing(); }}
            >
                <Pencil className="w-3.5 h-3.5" />
            </button>
        </div>
    );
}

function SessionList({
    sessions,
    activeId,
    onGenerate,
    onNewChat,
    onRename,
    generating,
}: {
    sessions: SessionSummary[];
    activeId: number | null;
    onGenerate: () => void;
    onNewChat: () => void;
    onRename: (id: number, title: string) => void;
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
                        <SessionRow key={s.id} s={s} activeId={activeId} onRename={onRename} />
                    ))}
                </div>
            )}
        </div>
    );
}

function MessageBubble({ message, funFacts }: { message: Message; funFacts: string[] }) {
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
                {message.content === '' ? (
                    <ThinkingWithFacts facts={funFacts} />
                ) : (
                    <Markdown content={message.content} />
                )}
            </div>
        </div>
    );
}

/**
 * The "thinking" state shown while the model works — for both a chat reply and
 * the opening report. Typing dots plus a single rotating insight about the
 * user's own data, so a slow answer never feels like dead air. `revealDelay`
 * holds the insight back briefly for chat (its stream often starts fast, so a
 * flash of fact would be noise); the report reveals it right away since its
 * wait is always long.
 */
function ThinkingWithFacts({ facts, revealDelay = 2000, label }: { facts: string[]; revealDelay?: number; label?: string }) {
    const [ordered] = useState(() => {
        const a = [...facts];
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a;
    });
    const [show, setShow] = useState(revealDelay === 0);
    const [idx, setIdx] = useState(0);

    useEffect(() => {
        if (ordered.length === 0 || revealDelay === 0) return;
        const reveal = setTimeout(() => setShow(true), revealDelay);
        return () => clearTimeout(reveal);
    }, [ordered.length, revealDelay]);

    useEffect(() => {
        if (!show || ordered.length < 2) return;
        const t = setInterval(() => setIdx((i) => (i + 1) % ordered.length), 5000);
        return () => clearInterval(t);
    }, [show, ordered.length]);

    return (
        <div className="flex flex-col gap-2">
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
                <TypingDots />
                {label && <span>{label}</span>}
            </div>
            {show && ordered.length > 0 && (
                <p key={idx} className="text-xs italic text-muted-foreground/80 animate-fade-in">{ordered[idx]}</p>
            )}
        </div>
    );
}

function TypingDots() {
    return (
        <span className="inline-flex items-center gap-1">
            <span className="h-1.5 w-1.5 rounded-full bg-current animate-bounce [animation-delay:-0.3s]" />
            <span className="h-1.5 w-1.5 rounded-full bg-current animate-bounce [animation-delay:-0.15s]" />
            <span className="h-1.5 w-1.5 rounded-full bg-current animate-bounce" />
        </span>
    );
}

function Conversation({
    session,
    configured,
    funFacts,
    onSent,
}: {
    session: ActiveSession;
    configured: boolean;
    funFacts: string[];
    onSent: () => void;
}) {
    const [messages, setMessages] = useState<Message[]>(session.messages);
    const [status, setStatus] = useState<Status>(session.status);
    const [error, setError] = useState<string | null>(session.error);
    const [input, setInput] = useState('');
    const [sending, setSending] = useState(false);
    const pushToast = useToast();
    const bottomRef = useRef<HTMLDivElement>(null);
    const scrollRef = useRef<HTMLDivElement>(null);
    const prevStatus = useRef<Status>(session.status);
    // Whether the view is pinned to the bottom. Auto-scroll on new content only
    // while pinned, so a user who scrolls up to read old messages isn't yanked
    // back down every time a stream chunk arrives.
    const [atBottom, setAtBottom] = useState(true);
    // Synchronous lock: setSending is async, so two near-simultaneous sends
    // (chip + Enter) could both pass the state guard and hit the single-request
    // local model concurrently, which returns an empty reply. This blocks the
    // second one immediately.
    const sendingRef = useRef(false);

    // 3 starters, stable for this session (don't reshuffle on every keystroke).
    const suggestions = useMemo(() => pickQuestions(session.id, 3), [session.id]);

    const scrollToBottom = (behavior: ScrollBehavior = 'smooth') => {
        bottomRef.current?.scrollIntoView({ behavior });
    };

    const handleScroll = () => {
        const el = scrollRef.current;
        if (!el) return;
        // A small threshold so "almost at the bottom" still counts as pinned.
        setAtBottom(el.scrollHeight - el.scrollTop - el.clientHeight < 80);
    };

    // Follow new content only while pinned to the bottom.
    useEffect(() => {
        if (atBottom) scrollToBottom();
    }, [messages, sending, atBottom]);

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

    // Toast on a real pending→done/failed transition for the report, and
    // refresh the session list so its row stops showing the pending spinner.
    useEffect(() => {
        const from = prevStatus.current;
        prevStatus.current = status;
        if (from === 'pending' && status === 'done') {
            pushToast('Analisi completata.', 'success');
            onSent();
        } else if (from === 'pending' && status === 'failed') {
            pushToast('Generazione non riuscita.', 'error');
            onSent();
        }
    }, [status, pushToast, onSent]);

    const send = async (raw?: string) => {
        const text = (raw ?? input).trim();
        if (text === '' || sendingRef.current) return;
        sendingRef.current = true;
        setInput('');
        setSending(true);
        const userId = -Date.now();
        const assistantId = userId - 1;
        // Show the user turn and an empty assistant bubble that fills as the
        // stream arrives.
        setMessages((m) => [
            ...m,
            { id: userId, role: 'user', content: text, created_at: null },
            { id: assistantId, role: 'assistant', content: '', created_at: null },
        ]);
        try {
            const xsrf = decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');
            const res = await fetch(`/advisor/${session.id}/message`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrf,
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: JSON.stringify({ message: text }),
            });
            if (!res.ok || !res.body) throw new Error(String(res.status));

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            for (;;) {
                const { value, done } = await reader.read();
                if (done) break;
                const chunk = decoder.decode(value, { stream: true });
                setMessages((m) => m.map((msg) => msg.id === assistantId ? { ...msg, content: msg.content + chunk } : msg));
            }
            onSent();
        } catch {
            pushToast('Il consulente non ha risposto. Riprova.', 'error');
            // Roll back the optimistic pair.
            setMessages((m) => m.filter((x) => x.id !== userId && x.id !== assistantId));
            setInput(text);
        } finally {
            sendingRef.current = false;
            setSending(false);
        }
    };

    const pending = status === 'pending';

    return (
        <Card className="flex flex-col flex-1 min-h-0">
            <div className="relative flex-1 min-h-0">
                <CardContent ref={scrollRef} onScroll={handleScroll} className="h-full overflow-y-auto p-4 space-y-4">
                    {pending && messages.length === 0 && (
                        <ThinkingWithFacts facts={funFacts} revealDelay={0} label="Sto analizzando il tuo portafoglio…" />
                    )}
                    {status === 'failed' && (
                        <div className="flex items-start gap-2 text-sm text-red-500">
                            <AlertTriangle className="w-4 h-4 flex-shrink-0 mt-0.5" />
                            <span>{error ?? 'Generazione non riuscita.'}</span>
                        </div>
                    )}
                    {messages.map((m) => <MessageBubble key={m.id} message={m} funFacts={funFacts} />)}
                    <div ref={bottomRef} />
                </CardContent>

                {!atBottom && (
                    <button
                        type="button"
                        onClick={() => scrollToBottom()}
                        className="absolute bottom-3 left-1/2 -translate-x-1/2 flex items-center gap-1 rounded-full border border-border bg-background/95 px-3 py-1.5 text-xs font-medium text-muted-foreground shadow-md backdrop-blur transition-colors hover:text-foreground animate-fade-in"
                        title="Vai in fondo"
                    >
                        <ChevronDown className="h-3.5 w-3.5" />
                        Vai in fondo
                    </button>
                )}
            </div>

            {configured && !pending && (
                <div className="border-t border-border p-3 space-y-2">
                    {!sending && messages.length > 0 && (
                        <div className="flex flex-wrap gap-1.5">
                            {suggestions.map((q) => (
                                <button
                                    key={q}
                                    type="button"
                                    onClick={() => void send(q)}
                                    className="rounded-full border border-border bg-muted/40 px-3 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
                                >
                                    {q}
                                </button>
                            ))}
                        </div>
                    )}
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

/**
 * The empty state for a not-yet-started conversation. Deliberately mirrors the
 * Conversation layout — a full-height message area (here holding a centered
 * prompt) over the same bottom input with suggestion chips — so starting a chat
 * feels continuous with reading one, rather than a separate form.
 */
function NewConversation({
    value,
    onChange,
    onStart,
    onCancel,
    onPick,
    starting,
}: {
    value: string;
    onChange: (v: string) => void;
    onStart: () => void;
    onCancel: () => void;
    onPick: (q: string) => void;
    starting: boolean;
}) {
    // A fresh set of starters each time the composer opens (no session id yet).
    const [suggestions] = useState(() => {
        const a = [...SUGGESTED_QUESTIONS];
        for (let i = a.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [a[i], a[j]] = [a[j], a[i]];
        }
        return a.slice(0, 3);
    });

    return (
        <Card className="flex flex-col flex-1 min-h-0">
            <div className="flex flex-1 min-h-0 flex-col items-center justify-center gap-2 overflow-y-auto p-4 text-center">
                <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                    <MessageCircle className="h-5 w-5 text-primary" />
                </div>
                <p className="text-sm font-medium">Nuova conversazione</p>
                <p className="max-w-sm text-xs text-muted-foreground">
                    Chiedi quello che vuoi sul tuo portafoglio, una decisione, la tua strategia o l&apos;obiettivo.
                </p>
            </div>

            <div className="border-t border-border p-3 space-y-2">
                <div className="flex flex-wrap gap-1.5">
                    {suggestions.map((q) => (
                        <button
                            key={q}
                            type="button"
                            disabled={starting}
                            onClick={() => onPick(q)}
                            className="rounded-full border border-border bg-muted/40 px-3 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground disabled:opacity-50"
                        >
                            {q}
                        </button>
                    ))}
                </div>
                <div className="flex items-end gap-2">
                    <textarea
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); onStart(); }
                            else if (e.key === 'Escape') { e.preventDefault(); onCancel(); }
                        }}
                        placeholder="Chiedi al tuo consulente… (es. la mia liquidità è troppa?)"
                        rows={1}
                        autoFocus
                        className="flex-1 resize-none rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <Button size="icon" onClick={onStart} disabled={starting || value.trim() === ''}>
                        {starting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
                    </Button>
                </div>
            </div>
        </Card>
    );
}

export default function Advisor({ configured, profile, goalObjective, sessions, activeSession, funFacts }: Props) {
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
            markSessionForTitleAnimation(data.session_id);
            router.visit(`/advisor/${data.session_id}`);
        } catch (e) {
            const msg = axios.isAxiosError(e) && typeof e.response?.data?.error === 'string'
                ? e.response.data.error
                : 'Generazione non riuscita.';
            pushToast(msg, 'error');
            setGenerating(false);
        }
    };

    const startChat = async (raw?: string) => {
        const text = (raw ?? firstChat).trim();
        if (text === '' || startingChat) return;
        setStartingChat(true);
        try {
            const { data } = await axios.post<{ session_id: number }>('/advisor/chat', { message: text });
            markSessionForTitleAnimation(data.session_id);
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

    const renameSession = (id: number, title: string) => {
        router.patch(`/advisor/${id}`, { title }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Consulente AI" />
            <div className="flex flex-col h-full p-4 gap-4 max-w-[1400px] mx-auto w-full">
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
                    <div className="grid flex-1 min-h-0 grid-cols-1 lg:grid-cols-[260px_1fr] gap-4 items-start">
                        <div className="space-y-3">
                            <SessionList
                                sessions={sessions}
                                activeId={activeSession?.id ?? null}
                                onGenerate={generate}
                                onNewChat={() => setChatMode(true)}
                                onRename={renameSession}
                                generating={generating}
                            />
                        </div>

                        <div className="h-full min-h-0">
                            {activeSession && !chatMode ? (
                                <div key={activeSession.id} className="flex flex-col h-full min-h-0 gap-2 animate-page-enter">
                                    <div className="flex items-center justify-between flex-shrink-0">
                                        <h2 className="text-sm font-medium truncate flex items-center gap-2">
                                            <KindIcon kind={activeSession.kind} className="w-4 h-4 text-primary" />
                                            <TypewriterText id={activeSession.id} text={activeSession.title ?? 'Sessione'} className="truncate" />
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
                                        funFacts={funFacts}
                                        onSent={() => router.reload({ only: ['sessions'] })}
                                    />
                                </div>
                            ) : chatMode ? (
                                <div className="flex flex-col h-full min-h-0">
                                    <NewConversation
                                        value={firstChat}
                                        onChange={setFirstChat}
                                        onStart={() => void startChat()}
                                        onPick={(q) => void startChat(q)}
                                        onCancel={() => { setChatMode(false); setFirstChat(''); }}
                                        starting={startingChat}
                                    />
                                </div>
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
