import { useState, useEffect, useRef, useMemo } from 'react';
import axios from 'axios';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { useToast } from '@/lib/toast';
import { AlertTriangle, Send, ChevronDown } from 'lucide-react';
import { type Status, type Message, type ActiveSession, type GoalData, pickQuestions } from '@/Components/Advisor/types';
import { MessageBubble } from '@/Components/Advisor/MessageBubble';
import { ThinkingWithFacts } from '@/Components/Advisor/ThinkingWithFacts';
import { type InvestorProfile } from '@/Components/Advisor/ProfileDialog';

export function Conversation({
    session,
    configured,
    funFacts,
    profile,
    goal,
    onSent,
}: {
    session: ActiveSession;
    configured: boolean;
    funFacts: string[];
    profile: InvestorProfile | null;
    goal: GoalData | null;
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

    // The assistant reply is generated in a background job; the request just
    // enqueues it. Show the sent question and an empty pending bubble, then let
    // the poll effect below fill it in — so the user can navigate away while the
    // local model works.
    const send = async (raw?: string) => {
        const text = (raw ?? input).trim();
        if (text === '' || sendingRef.current) return;
        sendingRef.current = true;
        setInput('');
        setSending(true);
        // Optimistic pair; replaced by the server's real rows on response.
        const tempUserId = -Date.now();
        const tempAssistantId = tempUserId - 1;
        setMessages((m) => [
            ...m,
            { id: tempUserId, role: 'user', content: text, status: 'done', created_at: null },
            { id: tempAssistantId, role: 'assistant', content: '', status: 'pending', created_at: null },
        ]);
        try {
            const { data } = await axios.post<{ user: Message; assistant: Message }>(
                `/advisor/${session.id}/message`,
                { message: text },
            );
            setMessages((m) => [
                ...m.filter((x) => x.id !== tempUserId && x.id !== tempAssistantId),
                data.user,
                data.assistant,
            ]);
        } catch {
            pushToast('Il consulente non ha risposto. Riprova.', 'error');
            setMessages((m) => m.filter((x) => x.id !== tempUserId && x.id !== tempAssistantId));
            setInput(text);
        } finally {
            sendingRef.current = false;
            setSending(false);
        }
    };

    // Retry a failed assistant reply in place: ask the server to regenerate it,
    // then flip it back to pending so the poll effect below resumes and fills it.
    const retry = async (failed: Message) => {
        if (sendingRef.current) return;
        setMessages((m) => m.map((x) => (x.id === failed.id ? { ...x, status: 'pending', error: null, content: '' } : x)));
        try {
            await axios.post(`/advisor/${session.id}/message/${failed.id}/retry`);
        } catch {
            pushToast('Non è stato possibile riprovare. Riprova più tardi.', 'error');
            setMessages((m) => m.map((x) => (x.id === failed.id ? { ...x, status: 'failed' } : x)));
        }
    };

    // Generate a proposal card on demand: the user clicked the "genera la
    // proposta" button the advisor offered. Adds NO user turn — only a pending
    // assistant turn the server creates and the poll below fills. The click is
    // the consent, so the backend opens the gate and forces the propose tool.
    const propose = async (kind: 'profile' | 'goal') => {
        if (sendingRef.current) return;
        const tempAssistantId = -Date.now();
        setMessages((m) => [...m, { id: tempAssistantId, role: 'assistant', content: '', status: 'pending', created_at: null }]);
        try {
            const { data } = await axios.post<{ assistant: Message }>(`/advisor/${session.id}/propose/${kind}`);
            setMessages((m) => [...m.filter((x) => x.id !== tempAssistantId), data.assistant]);
        } catch {
            pushToast('Non è stato possibile generare la proposta. Riprova.', 'error');
            setMessages((m) => m.filter((x) => x.id !== tempAssistantId));
        }
    };

    // A chat reply is being generated when the last message is a pending
    // assistant turn. Poll the session until it resolves (mirrors the report
    // poll, but keyed on the message rather than the session status).
    const lastMessage = messages[messages.length - 1];
    const awaitingReply = lastMessage?.role === 'assistant' && lastMessage?.status === 'pending' && lastMessage.id > 0;

    useEffect(() => {
        if (!awaitingReply) return;
        let cancelled = false;
        let timer: ReturnType<typeof setTimeout> | undefined;
        const tick = async () => {
            try {
                const { data } = await axios.get<{ messages: Message[] }>(`/advisor/${session.id}/status`);
                if (cancelled) return;
                setMessages(data.messages);
                const last = data.messages[data.messages.length - 1];
                if (last?.role === 'assistant' && last?.status === 'pending') {
                    timer = setTimeout(tick, 2000);
                } else {
                    if (last?.status === 'failed') pushToast(last.error ?? 'Il consulente non ha risposto.', 'error');
                    onSent();
                }
            } catch {
                // transient; retry
                timer = setTimeout(tick, 2500);
            }
        };
        timer = setTimeout(tick, 1500);
        return () => { cancelled = true; if (timer) clearTimeout(timer); };
    }, [awaitingReply, session.id, pushToast, onSent]);

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
                    {messages.map((m, i) => <MessageBubble key={m.id} message={m} funFacts={funFacts} profile={profile} goal={goal} onRetry={i === messages.length - 1 ? retry : undefined} onPropose={propose} />)}
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
                        <div className="flex gap-1.5 overflow-x-auto pb-1 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden">
                            {suggestions.map((q) => (
                                <button
                                    key={q}
                                    type="button"
                                    onClick={() => void send(q)}
                                    className="shrink-0 whitespace-nowrap rounded-full border border-border bg-muted/40 px-3 py-1 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-accent-foreground"
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
                            placeholder="Chiedi al tuo consulente…"
                            rows={1}
                            className="min-w-0 flex-1 resize-none rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
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
