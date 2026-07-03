import { Markdown } from '@/Components/ui/Markdown';
import { Sparkles, AlertTriangle } from 'lucide-react';
import { type Message } from '@/Components/Advisor/types';
import { ThinkingWithFacts } from '@/Components/Advisor/ThinkingWithFacts';

export function MessageBubble({ message, funFacts }: { message: Message; funFacts: string[] }) {
    if (message.role === 'user') {
        return (
            <div className="flex justify-end">
                <div className="max-w-[85%] rounded-lg bg-primary px-3 py-2 text-sm text-primary-foreground whitespace-pre-wrap">
                    {message.content}
                </div>
            </div>
        );
    }
    const failed = message.status === 'failed';
    const thinking = message.status === 'pending' || (message.status === undefined && message.content === '');
    return (
        <div className="flex items-start gap-2">
            <div className="mt-1 flex h-6 w-6 flex-shrink-0 items-center justify-center rounded-full bg-primary/10">
                <Sparkles className="h-3.5 w-3.5 text-primary" />
            </div>
            <div className="min-w-0 flex-1">
                {failed ? (
                    <div className="flex items-start gap-2 text-sm text-red-500">
                        <AlertTriangle className="w-4 h-4 flex-shrink-0 mt-0.5" />
                        <span>{message.error ?? 'Il consulente non ha risposto. Riprova.'}</span>
                    </div>
                ) : thinking ? (
                    <ThinkingWithFacts facts={funFacts} />
                ) : (
                    <Markdown content={message.content} />
                )}
            </div>
        </div>
    );
}
