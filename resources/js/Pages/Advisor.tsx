import { useState } from 'react';
import axios from 'axios';
import { Head, router } from '@inertiajs/react';
import AppLayout from '@/Components/Layout/AppLayout';
import { PageHeader } from '@/Components/Layout/PageHeader';
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { useToast } from '@/lib/toast';
import { cn } from '@/lib/utils';
import { ProfileDialog, type InvestorProfile } from '@/Components/Advisor/ProfileDialog';
import { type SessionSummary, type ActiveSession } from '@/Components/Advisor/types';
import { KindIcon } from '@/Components/Advisor/KindIcon';
import { TypewriterText, markSessionForTitleAnimation } from '@/Components/Advisor/TypewriterText';
import { SessionList } from '@/Components/Advisor/SessionList';
import { Conversation } from '@/Components/Advisor/Conversation';
import { NewConversation } from '@/Components/Advisor/NewConversation';
import { markInternalNavigation, claimEnterAnimation } from '@/Components/Advisor/enterAnimation';
import { Sparkles, AlertTriangle, Trash2, UserCog } from 'lucide-react';

interface Props {
    configured: boolean;
    profile: InvestorProfile | null;
    goalObjective: string | null;
    sessions: SessionSummary[];
    activeSession: ActiveSession | null;
    funFacts: string[];
}

export default function Advisor({ configured, profile, goalObjective, sessions, activeSession, funFacts }: Props) {
    const [profileOpen, setProfileOpen] = useState(false);
    const [generating, setGenerating] = useState(false);
    const [chatMode, setChatMode] = useState(false);
    const [firstChat, setFirstChat] = useState('');
    const [startingChat, setStartingChat] = useState(false);
    const [animateEnter] = useState(claimEnterAnimation);
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
                                <div key={activeSession.id} className="flex flex-col h-full min-h-0 gap-2">
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
                            ) : (
                                // No open session (or the user hit "new chat"):
                                // land on a ready-to-type new conversation rather
                                // than a passive empty state.
                                <div className="flex flex-col h-full min-h-0">
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
                goalObjective={goalObjective}
            />
        </>
    );
}

Advisor.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
