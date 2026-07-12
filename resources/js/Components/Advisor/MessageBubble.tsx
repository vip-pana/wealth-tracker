import { Markdown } from '@/Components/ui/Markdown';
import { Sparkles, AlertTriangle, RotateCw } from 'lucide-react';
import { type Message, type GoalData } from '@/Components/Advisor/types';
import { ThinkingWithFacts } from '@/Components/Advisor/ThinkingWithFacts';
import { AdvisorWidgets } from '@/Components/Advisor/AdvisorWidgets';
import { type InvestorProfile } from '@/Components/Advisor/ProfileDialog';

export function MessageBubble({
    message,
    funFacts,
    profile,
    goal,
    onRetry,
    onPropose,
}: {
    message: Message;
    funFacts: string[];
    profile?: InvestorProfile | null;
    goal?: GoalData | null;
    onRetry?: (message: Message) => void;
    onPropose?: (kind: 'profile' | 'goal') => void;
}) {
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
                        {onRetry ? (
                            <button
                                type="button"
                                onClick={() => onRetry(message)}
                                className="inline-flex items-center gap-1.5 text-left text-red-500 transition-colors hover:text-red-600"
                            >
                                <span className="underline">
                                    {message.error ?? 'Il consulente non ha risposto.'}
                                </span>
                                <RotateCw className="h-3.5 w-3.5 flex-shrink-0" />
                            </button>
                        ) : (
                            <span>{message.error ?? 'Il consulente non ha risposto.'}</span>
                        )}
                    </div>
                ) : thinking ? (
                    <ThinkingWithFacts facts={funFacts} label={message.tool_activity ?? undefined} />
                ) : (
                    <>
                        <Markdown content={message.content} />
                        {message.widgets && message.widgets.length > 0 && (
                            <AdvisorWidgets widgets={message.widgets} profile={profile} goal={goal} onPropose={onPropose} />
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
