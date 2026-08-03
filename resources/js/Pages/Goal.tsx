import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { GoalFormDialog } from '@/Components/Goal/GoalFormDialog';
import { GoalProgress } from '@/Components/Goal/GoalProgress';
import { EmptyGoal } from '@/Components/Goal/EmptyGoal';
import { ProfileCard } from '@/Components/Goal/ProfileCard';
import { ProfileDialog, type InvestorProfile } from '@/Components/Advisor/ProfileDialog';
import type { CurrentAllocationItem, CurrentMacroAllocationItem } from '@/Components/Goal/types';
import type { Category } from '@/types/models';
import type { Goal } from '@/types/models';

interface Props {
    goal: Goal | null;
    profile: InvestorProfile | null;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    currentNetWorth: number | null;
    currentAllocation: CurrentAllocationItem[];
    currentMacroAllocation: CurrentMacroAllocationItem[];
    today: string;
}

export default function GoalPage({ goal, profile, categories, currentNetWorth, currentAllocation, currentMacroAllocation, today }: Props) {
    const [formOpen, setFormOpen] = useState(false);
    const [profileOpen, setProfileOpen] = useState(false);
    const { delete: destroy, processing: deleting } = useForm({});

    const handleDelete = () => {
        if (!goal) return;
        if (confirm(`Eliminare l'obiettivo "${goal.name}"?`)) {
            destroy(`/goal/${goal.id}`);
        }
    };

    return (
        <>
            {!goal && <Head title="Obiettivo" />}
            <div className="h-full">
                {goal ? (
                    <GoalProgress
                        goal={goal}
                        categories={categories}
                        currentNetWorth={currentNetWorth}
                        currentAllocation={currentAllocation}
                        currentMacroAllocation={currentMacroAllocation}
                        today={today}
                        onEdit={() => setFormOpen(true)}
                        profileCard={<ProfileCard profile={profile} onEdit={() => setProfileOpen(true)} />}
                    />
                ) : (
                    // No goal yet: the profile still belongs here, so it sits
                    // above the empty state rather than being unreachable until
                    // a goal exists.
                    <div className="p-4 space-y-4 max-w-350 mx-auto w-full animate-page-enter">
                        <ProfileCard profile={profile} onEdit={() => setProfileOpen(true)} />
                        <EmptyGoal onCreate={() => setFormOpen(true)} />
                    </div>
                )}
            </div>

            <GoalFormDialog
                open={formOpen}
                onClose={() => setFormOpen(false)}
                categories={categories}
                goal={goal}
                onDelete={handleDelete}
            />

            <ProfileDialog
                open={profileOpen}
                onClose={() => setProfileOpen(false)}
                profile={profile}
                // The advisor owns the conversational path, so "define with AI"
                // hands off to it with the question prefilled (the ?ask= param
                // the Advisor page already reads) rather than duplicating a chat
                // launcher here.
                onDefineWithAi={() => {
                    setProfileOpen(false);
                    router.visit(`/advisor?ask=${encodeURIComponent('Aiutami a definire il mio profilo di rischio')}`);
                }}
            />

            {deleting && (
                <div className="fixed inset-0 bg-background/50 flex items-center justify-center z-50">
                    <p className="text-sm text-muted-foreground">Eliminazione in corso…</p>
                </div>
            )}
        </>
    );
}

GoalPage.layout = (page: React.ReactNode) => <AppLayout>{page}</AppLayout>;
