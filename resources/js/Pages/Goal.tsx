import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AppLayout from '@/Components/Layout/AppLayout';
import { GoalFormDialog } from '@/Components/Goal/GoalFormDialog';
import { GoalProgress } from '@/Components/Goal/GoalProgress';
import { EmptyGoal } from '@/Components/Goal/EmptyGoal';
import type { CurrentAllocationItem, CurrentMacroAllocationItem } from '@/Components/Goal/types';
import type { Category } from '@/types/models';
import type { Goal } from '@/types/models';

interface Props {
    goal: Goal | null;
    categories: Pick<Category, 'id' | 'name' | 'color' | 'macro_category'>[];
    currentNetWorth: number | null;
    currentAllocation: CurrentAllocationItem[];
    currentMacroAllocation: CurrentMacroAllocationItem[];
    today: string;
}

export default function GoalPage({ goal, categories, currentNetWorth, currentAllocation, currentMacroAllocation, today }: Props) {
    const [formOpen, setFormOpen] = useState(false);
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
                        onDelete={handleDelete}
                    />
                ) : (
                    <EmptyGoal onCreate={() => setFormOpen(true)} />
                )}
            </div>

            <GoalFormDialog
                open={formOpen}
                onClose={() => setFormOpen(false)}
                categories={categories}
                goal={goal}
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
