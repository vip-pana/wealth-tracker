import { useState } from 'react';
import { Button } from '@/Components/ui/button';
import { Sparkles } from 'lucide-react';
import type { ProposalOfferWidget } from '@/Components/Advisor/types';

const LABEL: Record<ProposalOfferWidget['data']['kind'], string> = {
    profile: 'Genera la proposta di profilo',
    goal: 'Genera la proposta di obiettivo',
};

/**
 * A button the advisor shows when the interview is complete, instead of asking
 * in words whether to propose. The click — not a parsed "yes" — is the consent:
 * it POSTs to /advisor/{session}/propose/{kind} (via onPropose), which generates
 * the actual proposal card in a fresh assistant turn. The model kept re-asking
 * for confirmation on its own, so this makes the trigger deterministic and the
 * card generation reliable. Disables itself once clicked so a double-tap can't
 * fire two generations.
 */
export function ProposalOffer({ data, onPropose }: { data: ProposalOfferWidget['data']; onPropose?: (kind: 'profile' | 'goal') => void }) {
    const [clicked, setClicked] = useState(false);

    if (!onPropose) return null;

    return (
        <div className="mt-3">
            <Button
                size="sm"
                disabled={clicked}
                onClick={() => { setClicked(true); onPropose(data.kind); }}
            >
                <Sparkles className="h-4 w-4 mr-1.5" />
                {LABEL[data.kind]}
            </Button>
        </div>
    );
}
