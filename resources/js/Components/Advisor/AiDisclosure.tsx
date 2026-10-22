// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

import { Info } from 'lucide-react';
import { cn } from '@/lib/utils';

/**
 * The two statements the app owes the user about the advisor, rendered by the
 * app itself.
 *
 * The first is the Art. 50(1) AI Act disclosure that the user is interacting
 * with an AI system. It used to be a line in the boundary clauses asking the
 * model to declare itself on its first reply; the WEA-10 review measured that
 * at 2/10 against the tuned private prompt, which is what a legal disclosure
 * looks like when it depends on the model cooperating. Here it is markup: it
 * shows before the model has produced a single token, and it still shows when
 * the model is down, refusing, or ignoring its prompt.
 *
 * The second is the MiFID disclaimer — informational tool, not investment
 * advice, go to someone on the OCF register for a personal recommendation.
 * Layer 3 already appends this to a reply it had to cut (AdvisorOutputFilter::
 * FOOTER), but that only covers replies that tripped the filter; the standing
 * notice is what covers the ones that did not.
 *
 * `where` picks the wording, not the obligation:
 * - `advisor`  — the advisor surface itself, where the user is talking to the model.
 * - `derived`  — a page showing text the advisor wrote earlier (the saved
 *                milestones on the Goal page). No live model there, so the AI
 *                statement is in the past tense, and this is the only notice
 *                those milestones ever get: they are shown outside the chat,
 *                with none of its framing around them.
 */
export function AiDisclosure({ where = 'advisor', className }: { where?: 'advisor' | 'derived'; className?: string }) {
    return (
        <p
            className={cn('flex items-start gap-1.5 text-xs text-muted-foreground', className)}
            data-testid="ai-disclosure"
        >
            <Info className="w-3.5 h-3.5 mt-0.5 shrink-0" aria-hidden="true" />
            <span>
                {where === 'advisor'
                    ? 'Stai interagendo con un sistema di intelligenza artificiale. '
                    : 'Queste note sono state scritte da un sistema di intelligenza artificiale. '}
                È uno strumento informativo ed educativo: non è consulenza finanziaria e può
                contenere errori. Per una raccomandazione personalizzata rivolgiti a un consulente
                abilitato (in Italia, iscritto all&apos;albo OCF).
            </span>
        </p>
    );
}
