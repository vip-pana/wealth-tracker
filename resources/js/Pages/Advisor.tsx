import { useState } from 'react';
import axios from 'axios';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { useToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { formatChatTimestamp } from '@/lib/formatters';
import { ProfileDialog, type InvestorProfile } from '@/Components/Advisor/ProfileDialog';
import { type SessionSummary, type ActiveSession, type GoalData } from '@/Components/Advisor/types';
import { KindIcon } from '@/Components/Advisor/KindIcon';
import { TypewriterText, markSessionForTitleAnimation } from '@/Components/Advisor/TypewriterText';
import { SessionList } from '@/Components/Advisor/SessionList';
import { Conversation } from '@/Components/Advisor/Conversation';
import { NewConversation } from '@/Components/Advisor/NewConversation';
import { markInternalNavigation, claimEnterAnimation } from '@/Components/Advisor/enterAnimation';
import { Sparkles, AlertTriangle, Trash2, UserCog, ChevronLeft } from 'lucide-react';

interface Props {
    configured: boolean;
    profile: InvestorProfile | null;
    goal: GoalData | null;
    sessions: SessionSummary[];
    activeSession: ActiveSession | null;
    funFacts: string[];
}

export default function Advisor({ configured, profile, goal, sessions, activeSession, funFacts }: Props) {
    // A `?ask=` query param (e.g. the "Ridefinisci con l'AI" button on the Goal
    // page) opens a fresh composer prefilled with that question, so the user
    // lands ready to send — or to tweak — rather than on the session list.
    const prefill = typeof window !== 'undefined'
        ? new URLSearchParams(window.location.search).get('ask') ?? ''
        : '';

    const [profileOpen, setProfileOpen] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [chatMode, setChatMode] = useState(prefill !== '');
    const [firstChat, setFirstChat] = useState(prefill);
    const [startingChat, setStartingChat] = useState(false);
    const [animateEnter] = useState(claimEnterAnimation);
    // On phones/tablets the list and the conversation don't fit side by side, so
    // we show one at a time (messaging-app pattern). Land on the conversation
    // when a session is open, else on the list; a back button toggles to 'list'.
    // Above `lg` both panes show and this state is ignored.
    const [mobileView, setMobileView] = useState<'list' | 'chat'>(activeSession || chatMode ? 'chat' : 'list');
    const pushToast = useToast();

    const generate = async () => {
        setGenerating(true);
        try {
            const { data } = await axios.post<{ session_id: number }>('/advisor/generate');
            markSessionForTitleAnimation(data.session_id);
            markInternalNavigation();
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
            markInternalNavigation();
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
        markInternalNavigation();
        router.delete(`/advisor/${id}`, { preserveScroll: true });
    };

    const renameSession = (id: number, title: string) => {
        router.patch(`/advisor/${id}`, { title }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Consulente AI" />
            <div className={cn('flex flex-col h-full p-4 gap-4 max-w-[1400px] mx-auto w-full', animateEnter && 'animate-page-enter')}>
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
                        <div className={cn('min-w-0', mobileView === 'chat' && 'hidden lg:block')}>
                            <SessionList
                                sessions={sessions}
                                activeId={activeSession?.id ?? null}
                                onGenerate={generate}
                                onNewChat={() => { setChatMode(true); setMobileView('chat'); }}
                                onOpen={() => { setChatMode(false); setMobileView('chat'); }}
                                onRename={renameSession}
                                generating={generating}
                            />
                        </div>

                        <div className={cn('h-full min-w-0 min-h-0', mobileView === 'list' && 'hidden lg:block')}>
                            {activeSession && !chatMode ? (
                                <div key={activeSession.id} className="flex flex-col h-full min-h-0 gap-2">
                                    <div className="flex items-center gap-2 flex-shrink-0">
                                        <Button
                                            variant="ghost" size="icon"
                                            className="h-7 w-7 flex-shrink-0 text-muted-foreground lg:hidden"
                                            onClick={() => setMobileView('list')}
                                            title="Torna alle sessioni"
                                        >
                                            <ChevronLeft className="w-4 h-4" />
                                        </Button>
                                        <h2 className="min-w-0 flex-1 text-sm font-medium truncate flex items-center gap-2">
                                            <KindIcon kind={activeSession.kind} className="w-4 h-4 flex-shrink-0 text-primary" />
                                            <TypewriterText id={activeSession.id} text={activeSession.title ?? 'Sessione'} className="truncate" />
                                            {activeSession.created_at && (
                                                <span className="flex-shrink-0 text-xs font-normal text-muted-foreground">
                                                    {formatChatTimestamp(activeSession.created_at)}
                                                </span>
                                            )}
                                        </h2>
                                        <Button
                                            variant="ghost" size="icon"
                                            className="h-7 w-7 flex-shrink-0 text-muted-foreground hover:text-destructive"
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
                                        profile={profile}
                                        goal={goal}
                                        onSent={() => router.reload({ only: ['sessions'] })}
                                    />
                                </div>
                            ) : (
                                // No open session (or the user hit "new chat"):
                                // land on a ready-to-type new conversation rather
                                // than a passive empty state.
                                <div className="flex flex-col h-full min-h-0 gap-2">
                                    <Button
                                        variant="ghost" size="sm"
                                        className="self-start h-7 px-2 text-muted-foreground lg:hidden"
                                        onClick={() => setMobileView('list')}
                                    >
                                        <ChevronLeft className="w-4 h-4 mr-1" />
                                        Sessioni
                                    </Button>
                                    <NewConversation
                                        value={firstChat}
                                        onChange={setFirstChat}
                                        onStart={() => void startChat()}
                                        onPick={(q) => void startChat(q)}
                                        onCancel={() => { setChatMode(false); setFirstChat(''); }}
                                        starting={startingChat}
                                        funFacts={funFacts}
                                    />
                                </div>
                            )}
                        </div>
                    </div>
                )}
            </div>

            <ProfileDialog
                open={profileOpen}
                onClose={() => setProfileOpen(false)}
                profile={profile}
                onDefineWithAi={() => {
                    setProfileOpen(false);
                    void startChat('Aiutami a definire il mio profilo di rischio');
                }}
            />
        </>
    );
}

Advisor.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
