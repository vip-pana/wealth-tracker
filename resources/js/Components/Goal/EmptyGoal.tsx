// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

import { Button } from '@/Components/ui/button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Target, Plus } from 'lucide-react';

export function EmptyGoal({ onCreate }: { onCreate: () => void }) {
    return (
        <EmptyState
            icon={Target}
            title="Nessun obiettivo"
            description="Definisci il tuo obiettivo finanziario: valore target, composizione del portafoglio e milestone intermedie."
            action={
                <Button onClick={onCreate}>
                    <Plus className="w-4 h-4 mr-2" />
                    Crea obiettivo
                </Button>
            }
        />
    );
}
