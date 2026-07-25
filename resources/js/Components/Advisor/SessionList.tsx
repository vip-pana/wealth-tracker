import { Button } from '@/Components/ui/button';
import { Sparkles, Loader2, MessageSquarePlus } from 'lucide-react';
import { type SessionSummary } from '@/Components/Advisor/types';
import { SessionRow } from '@/Components/Advisor/SessionRow';

export function SessionList({
    sessions,
    activeId,
    onGenerate,
    onNewChat,
    onOpen,
    onRename,
    generating,
}: {
    sessions: SessionSummary[];
    activeId: number | null;
    onGenerate: () => void;
    onNewChat: () => void;
    onOpen?: () => void;
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
                <div className="flex flex-col gap-1 overflow-y-auto max-h-[calc(100dvh-8rem)]">
                    {sessions.map((s) => (
                        <SessionRow key={s.id} s={s} activeId={activeId} onRename={onRename} onOpen={onOpen} />
                    ))}
                </div>
            )}
        </div>
    );
}
