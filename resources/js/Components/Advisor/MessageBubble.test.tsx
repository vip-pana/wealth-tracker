import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { Message } from '@/Components/Advisor/types';

// ThinkingWithFacts spins timers/rng; stub it to a marker so the "thinking"
// branch is observable without driving timers here. It echoes the label so we
// can assert the live tool activity is passed through.
vi.mock('@/Components/Advisor/ThinkingWithFacts', () => ({
    ThinkingWithFacts: ({ label }: { label?: string }) => <div data-testid="thinking">{label}</div>,
}));

// The widgets pull in Recharts; stub the dispatcher to a marker that reports how
// many widgets it received, so we test MessageBubble's wiring in isolation.
vi.mock('@/Components/Advisor/AdvisorWidgets', () => ({
    AdvisorWidgets: ({ widgets }: { widgets: unknown[] }) => (
        <div data-testid="widgets">{widgets.length}</div>
    ),
}));

import { MessageBubble } from '@/Components/Advisor/MessageBubble';

function msg(over: Partial<Message> = {}): Message {
    return { id: 1, role: 'assistant', content: '', status: 'done', created_at: null, ...over };
}

describe('MessageBubble', () => {
    it('renders a user message verbatim', () => {
        render(<MessageBubble message={msg({ role: 'user', content: 'La mia domanda' })} funFacts={[]} />);
        expect(screen.getByText('La mia domanda')).toBeInTheDocument();
    });

    it('renders assistant markdown content when done', () => {
        render(<MessageBubble message={msg({ role: 'assistant', content: 'Risposta', status: 'done' })} funFacts={[]} />);
        expect(screen.getByText('Risposta')).toBeInTheDocument();
        expect(screen.queryByTestId('thinking')).not.toBeInTheDocument();
    });

    it('shows the thinking state while pending', () => {
        render(<MessageBubble message={msg({ role: 'assistant', content: '', status: 'pending' })} funFacts={[]} />);
        expect(screen.getByTestId('thinking')).toBeInTheDocument();
    });

    it('treats an undefined status with empty content as thinking', () => {
        render(<MessageBubble message={msg({ role: 'assistant', content: '', status: undefined })} funFacts={[]} />);
        expect(screen.getByTestId('thinking')).toBeInTheDocument();
    });

    it('surfaces the live tool activity while thinking', () => {
        render(<MessageBubble message={msg({ role: 'assistant', content: '', status: 'pending', tool_activity: 'Sto controllando la tua posizione Bitcoin…' })} funFacts={[]} />);
        expect(screen.getByTestId('thinking')).toHaveTextContent('Sto controllando la tua posizione Bitcoin…');
    });

    it('renders widgets under the prose when the reply carries them', () => {
        render(
            <MessageBubble
                message={msg({
                    role: 'assistant',
                    content: 'Con 600€ ci metti circa 30 anni.',
                    status: 'done',
                    widgets: [
                        {
                            type: 'pac_simulator',
                            data: {
                                current_net_worth: 35516,
                                target_value: 1000000,
                                monthly_amount: 600,
                                annual_return: 0.07,
                                annual_return_source: 'profilo di rischio alto',
                                low_confidence: true,
                            },
                        },
                    ],
                })}
                funFacts={[]}
            />,
        );
        expect(screen.getByText('Con 600€ ci metti circa 30 anni.')).toBeInTheDocument();
        expect(screen.getByTestId('widgets')).toHaveTextContent('1');
    });

    it('renders no widgets container when there are none', () => {
        render(<MessageBubble message={msg({ role: 'assistant', content: 'Risposta', status: 'done' })} funFacts={[]} />);
        expect(screen.queryByTestId('widgets')).not.toBeInTheDocument();
    });

    it('shows the error text when failed', () => {
        render(<MessageBubble message={msg({ role: 'assistant', status: 'failed', error: 'Modello non raggiungibile' })} funFacts={[]} />);
        expect(screen.getByText('Modello non raggiungibile')).toBeInTheDocument();
    });

    it('falls back to a default error message when failed without an error', () => {
        render(<MessageBubble message={msg({ role: 'assistant', status: 'failed', error: null })} funFacts={[]} />);
        expect(screen.getByText(/Il consulente non ha risposto/)).toBeInTheDocument();
    });

    it('makes the failed message clickable to retry and calls onRetry with it', async () => {
        const user = userEvent.setup();
        const onRetry = vi.fn();
        const failed = msg({ role: 'assistant', status: 'failed', error: 'Errore' });
        render(<MessageBubble message={failed} funFacts={[]} onRetry={onRetry} />);

        await user.click(screen.getByRole('button', { name: /Errore/ }));

        expect(onRetry).toHaveBeenCalledTimes(1);
        expect(onRetry).toHaveBeenCalledWith(failed);
    });

    it('shows no retry affordance when onRetry is not provided', () => {
        render(<MessageBubble message={msg({ role: 'assistant', status: 'failed', error: 'Errore' })} funFacts={[]} />);
        expect(screen.queryByRole('button')).not.toBeInTheDocument();
    });
});
