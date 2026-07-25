import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { ToastContext } from '@/lib/toast';
import type { ActiveSession, Message } from '@/Components/Advisor/types';

// Conversation talks to the server only through axios; mock it so we drive the
// optimistic-send, rollback, and guard logic without a network. ThinkingWithFacts
// spins timers/rng, so stub it to a static marker.
const axiosGet = vi.fn();
const axiosPost = vi.fn();
vi.mock('axios', () => ({
    default: {
        get: (...a: unknown[]) => axiosGet(...a),
        post: (...a: unknown[]) => axiosPost(...a),
    },
}));
vi.mock('@/Components/Advisor/ThinkingWithFacts', () => ({
    ThinkingWithFacts: () => <div data-testid="thinking" />,
}));

import { Conversation } from '@/Components/Advisor/Conversation';

const pushToast = vi.fn();

function session(over: Partial<ActiveSession> = {}): ActiveSession {
    return {
        id: 1,
        kind: 'chat',
        title: 'Chat',
        status: 'done',
        error: null,
        created_at: null,
        messages: [{ id: 1, role: 'assistant', content: 'Ciao', status: 'done', created_at: null }],
        ...over,
    };
}

function renderConversation(over: Partial<ActiveSession> = {}, onSent = vi.fn()) {
    render(
        <ToastContext.Provider value={pushToast}>
            <Conversation session={session(over)} configured funFacts={[]} profile={null} goal={null} onSent={onSent} />
        </ToastContext.Provider>,
    );
    return { onSent };
}

beforeEach(() => {
    axiosGet.mockReset();
    axiosPost.mockReset();
    pushToast.mockReset();
    // Default: status poll returns a settled session (no further scheduling).
    axiosGet.mockResolvedValue({ data: { status: 'done', error: null, messages: [] } });
});

describe('Conversation — optimistic send', () => {
    it('shows the typed question immediately and posts it', async () => {
        // Never-resolving post keeps the optimistic pair on screen.
        axiosPost.mockReturnValue(new Promise(() => {}));
        renderConversation();

        const box = screen.getByPlaceholderText(/Chiedi al tuo consulente/);
        await userEvent.type(box, 'La mia liquidità è troppa?');
        await userEvent.keyboard('{Enter}');

        expect(screen.getByText('La mia liquidità è troppa?')).toBeInTheDocument();
        expect(axiosPost).toHaveBeenCalledWith('/advisor/1/message', { message: 'La mia liquidità è troppa?' });
    });

    it('replaces the optimistic pair with the server rows on success', async () => {
        const user: Message = { id: 10, role: 'user', content: 'Domanda', status: 'done', created_at: null };
        const assistant: Message = { id: 11, role: 'assistant', content: 'Risposta reale', status: 'done', created_at: null };
        axiosPost.mockResolvedValue({ data: { user, assistant } });
        renderConversation();

        await userEvent.type(screen.getByPlaceholderText(/Chiedi al tuo consulente/), 'Domanda');
        await userEvent.keyboard('{Enter}');

        await waitFor(() => expect(screen.getByText('Risposta reale')).toBeInTheDocument());
    });

    it('rolls back the optimistic pair and toasts on failure', async () => {
        axiosPost.mockRejectedValue(new Error('boom'));
        renderConversation();

        await userEvent.type(screen.getByPlaceholderText(/Chiedi al tuo consulente/), 'Domanda fallita');
        await userEvent.keyboard('{Enter}');

        await waitFor(() =>
            expect(pushToast).toHaveBeenCalledWith('Il consulente non ha risposto. Riprova.', 'error'),
        );
        // The optimistic user bubble (a <div>, not the textarea) is gone…
        const bubble = screen
            .queryAllByText('Domanda fallita')
            .find((el) => el.tagName !== 'TEXTAREA');
        expect(bubble).toBeUndefined();
        // …and the text is restored into the input for a retry.
        expect(screen.getByPlaceholderText(/Chiedi al tuo consulente/)).toHaveValue('Domanda fallita');
    });

    it('ignores an empty send', async () => {
        renderConversation();
        // Enter on an empty box must not fire a request.
        await userEvent.type(screen.getByPlaceholderText(/Chiedi al tuo consulente/), '{Enter}');
        expect(axiosPost).not.toHaveBeenCalled();
    });
});

describe('Conversation — send guard', () => {
    it('blocks a second concurrent send while the first is in flight', async () => {
        // Post never resolves → the first send stays "in flight".
        axiosPost.mockReturnValue(new Promise(() => {}));
        renderConversation();

        const box = screen.getByPlaceholderText(/Chiedi al tuo consulente/);
        await userEvent.type(box, 'Prima');
        await userEvent.keyboard('{Enter}');
        // A chip click (second path) should be blocked by the synchronous ref lock.
        // After the first send the input clears; typing + Enter again is the 2nd attempt.
        await userEvent.type(box, 'Seconda');
        await userEvent.keyboard('{Enter}');

        expect(axiosPost).toHaveBeenCalledTimes(1);
    });
});

describe('Conversation — report status transitions', () => {
    it('toasts and refreshes when a pending report finishes', async () => {
        // First poll returns done → pending→done transition fires the toast.
        axiosGet.mockResolvedValue({ data: { status: 'done', error: null, messages: [] } });
        const { onSent } = renderConversation({ status: 'pending', messages: [] });

        await waitFor(() => expect(pushToast).toHaveBeenCalledWith('Analisi completata.', 'success'));
        expect(onSent).toHaveBeenCalled();
    });

    it('toasts an error when a pending report fails', async () => {
        axiosGet.mockResolvedValue({ data: { status: 'failed', error: 'kaputt', messages: [] } });
        renderConversation({ status: 'pending', messages: [] });

        await waitFor(() => expect(pushToast).toHaveBeenCalledWith('Generazione non riuscita.', 'error'));
    });
});

describe('Conversation — proposal offer fallback', () => {
    // Build an interview with N user turns (the model may never call the offer
    // tool, so the frontend surfaces the button itself after enough turns).
    function interview(openingText: string, userTurns: number): Message[] {
        const msgs: Message[] = [];
        for (let i = 0; i < userTurns; i++) {
            msgs.push({ id: i * 2 + 1, role: 'user', content: i === 0 ? openingText : `risposta ${i}`, status: 'done', created_at: null });
            msgs.push({ id: i * 2 + 2, role: 'assistant', content: `domanda ${i}`, status: 'done', created_at: null });
        }
        return msgs;
    }

    it('does not show the fallback before four user turns', () => {
        renderConversation({ messages: interview('definisci il mio profilo di rischio', 3) });
        expect(screen.queryByRole('button', { name: /Genera la proposta/ })).not.toBeInTheDocument();
    });

    it('shows the profile fallback after four user turns', () => {
        renderConversation({ messages: interview('aiutami col mio profilo di rischio', 4) });
        expect(screen.getByRole('button', { name: /Genera la proposta di profilo/ })).toBeInTheDocument();
    });

    it('shows the goal fallback when the interview is about the objective', () => {
        renderConversation({ messages: interview('vorrei ridefinire il mio obiettivo', 4) });
        expect(screen.getByRole('button', { name: /Genera la proposta di obiettivo/ })).toBeInTheDocument();
    });

    it('offers the goal fallback when the goal signal only appears mid-conversation', () => {
        // The opening line is a plain analysis question; the goal intent surfaces
        // in a later turn. The kind must reflect the WHOLE conversation, not just
        // the opening — this is what mis-offered a profile button before.
        const msgs = interview('come sta andando il portafoglio?', 4);
        msgs[4] = { ...msgs[4], content: 'rivediamo le milestone e la target allocation' };
        renderConversation({ messages: msgs });
        expect(screen.getByRole('button', { name: /Genera la proposta di obiettivo/ })).toBeInTheDocument();
    });

    it('offers the goal fallback even when voice input mangles "milestone"/"allocation"', () => {
        // Real speech-to-text output: "milestone"→"milson"/"mile son",
        // "allocation"→"location". A strict exact-word match defaulted this to a
        // profile offer even though the user was revising milestones.
        const msgs = interview('senti una cosa', 4);
        msgs[2] = { ...msgs[2], content: 'ha senso rivedere le milson per il primo punto della mile son' };
        msgs[6] = { ...msgs[6], content: 'aggiornare la target location' };
        renderConversation({ messages: msgs });
        expect(screen.getByRole('button', { name: /Genera la proposta di obiettivo/ })).toBeInTheDocument();
    });

    it('hides the fallback while the button is the current (last) turn', () => {
        const msgs = interview('definisci il mio profilo di rischio', 4);
        msgs[msgs.length - 1] = {
            ...msgs[msgs.length - 1],
            widgets: [{ type: 'profile_proposal', data: { horizon: 'long' } }],
        };
        renderConversation({ messages: msgs });
        expect(screen.queryByRole('button', { name: /Genera la proposta/ })).not.toBeInTheDocument();
    });

    it('re-surfaces the fallback when the user keeps talking after an offer', () => {
        // The offer widget appeared earlier, then the user added a request (e.g. a
        // liquidity cap). The buried button is stale — a fresh one must appear at
        // the bottom instead of the fallback staying suppressed forever.
        const msgs = interview('vorrei ridefinire le milestone', 4);
        // Attach the offer to an assistant turn that is NOT the last message.
        const lastAssistantIdx = msgs.map((m) => m.role).lastIndexOf('assistant');
        msgs[lastAssistantIdx] = { ...msgs[lastAssistantIdx], widgets: [{ type: 'proposal_offer', data: { kind: 'goal' } }] };
        // A newer user turn follows the offer.
        msgs.push({ id: 999, role: 'user', content: 'aggiungi un tetto alla liquidità di 50k', status: 'done', created_at: null });

        renderConversation({ messages: msgs });
        expect(screen.getByRole('button', { name: /Genera la proposta di obiettivo/ })).toBeInTheDocument();
    });

    it('posts to the propose endpoint when the fallback is clicked', async () => {
        const user = userEvent.setup();
        axiosPost.mockResolvedValue({ data: { assistant: { id: 99, role: 'assistant', content: '', status: 'pending', created_at: null } } });
        renderConversation({ messages: interview('profilo di rischio', 4) });

        await user.click(screen.getByRole('button', { name: /Genera la proposta di profilo/ }));

        expect(axiosPost.mock.calls[0][0]).toBe('/advisor/1/propose/profile');
    });
});

describe('Conversation — retry on a proposal turn', () => {
    it('re-runs the proposal endpoint (not chat retry) for a failed proposal turn', async () => {
        const user = userEvent.setup();
        axiosPost.mockResolvedValue({ data: { assistant: { id: 99, role: 'assistant', content: '', status: 'pending', created_at: null } } });
        // A proposal turn has NO user message right before it: two assistants in a
        // row, the last one failed.
        const msgs: Message[] = [
            { id: 1, role: 'user', content: 'definisci il mio profilo di rischio', status: 'done', created_at: null },
            { id: 2, role: 'assistant', content: 'ok', status: 'done', created_at: null },
            { id: 3, role: 'assistant', content: '', status: 'failed', error: 'boom', created_at: null },
        ];
        renderConversation({ messages: msgs });

        // The failed bubble is a clickable button whose label is the error text.
        await user.click(screen.getByRole('button', { name: /boom/ }));

        expect(axiosPost.mock.calls[0][0]).toBe('/advisor/1/propose/profile');
        // It must NOT hit the chat-retry endpoint.
        expect(axiosPost.mock.calls.every((c) => !String(c[0]).includes('/retry'))).toBe(true);
    });
});
