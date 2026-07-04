import { useState } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Loader2, MessageCircle, Send } from 'lucide-react';
import { SUGGESTED_QUESTIONS } from '@/Components/Advisor/types';
import { MessageBubble } from '@/Components/Advisor/MessageBubble';

/**
 * The empty state for a not-yet-started conversation. Deliberately mirrors the
 * Conversation layout — a full-height message area (here holding a centered
 * prompt) over the same bottom input with suggestion chips — so starting a chat
 * feels continuous with reading one, rather than a separate form.
 */
export function NewConversation({
    value,
    onChange,
    onStart,
    onCancel,
    onPick,
    starting,
    funFacts,
}: {
    value: string;
    onChange: (v: string) => void;
    onStart: () => void;
    onCancel: () => void;
    onPick: (q: string) => void;
    starting: boolean;
    funFacts: string[];
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

    // The question being sent, shown optimistically as a user bubble the moment
    // the chat is started (chip or input) — the actual reply arrives on the next
    // page after navigation, but this keeps the just-sent message visible during
    // the wait instead of leaving the sender staring at the empty composer.
    const [pendingText, setPendingText] = useState<string | null>(null);
    const start = () => {
        const text = value.trim();
        if (text === '') return;
        setPendingText(text);
        onStart();
    };
    const pick = (q: string) => {
        setPendingText(q);
        onPick(q);
    };

    if (pendingText !== null) {
        return (
            <Card className="flex flex-col flex-1 min-h-0">
                <CardContent className="flex-1 overflow-y-auto p-4 space-y-4">
                    <MessageBubble message={{ id: -1, role: 'user', content: pendingText, created_at: null }} funFacts={funFacts} />
                    <MessageBubble message={{ id: -2, role: 'assistant', content: '', created_at: null }} funFacts={funFacts} />
                </CardContent>
            </Card>
        );
    }

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
                            onClick={() => pick(q)}
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
                            if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); start(); }
                            else if (e.key === 'Escape') { e.preventDefault(); onCancel(); }
                        }}
                        placeholder="Chiedi al tuo consulente…"
                        rows={1}
                        autoFocus
                        className="min-w-0 flex-1 resize-none rounded-md border border-input bg-background px-3 py-2 text-sm placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                    />
                    <Button size="icon" onClick={start} disabled={starting || value.trim() === ''}>
                        {starting ? <Loader2 className="w-4 h-4 animate-spin" /> : <Send className="w-4 h-4" />}
                    </Button>
                </div>
            </div>
        </Card>
    );
}
