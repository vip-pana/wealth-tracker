import { Button } from '@/Components/ui/button';
import { EmptyState } from '@/Components/ui/EmptyState';
import { PiggyBank, Plus } from 'lucide-react';

export function PensionEmptyState({ onCreate, hasCategories }: { onCreate: () => void; hasCategories: boolean }) {
    return (
        <EmptyState
            icon={PiggyBank}
            title="Nessun valore registrato"
            description={hasCategories
                ? 'Inserisci il valore del tuo fondo pensione dal report annuale. Verrà conteggiato nel patrimonio totale ma escluso dai grafici di analisi mensile.'
                : 'Crea una categoria con macro "Fondo Pensione" dalle Impostazioni per iniziare.'}
            action={hasCategories && (
                <Button onClick={onCreate}>
                    <Plus className="w-4 h-4 mr-2" />
                    Aggiungi valore
                </Button>
            )}
        />
    );
}
