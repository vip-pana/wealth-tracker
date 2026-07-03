import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import type { Message } from '@/Components/Advisor/types';

// ThinkingWithFacts spins timers/rng; stub it to a marker so the "thinking"
// branch is observable without driving timers here.
vi.mock('@/Components/Advisor/ThinkingWithFacts', () => ({
    ThinkingWithFacts: () => <div data-testid="thinking" />,
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

    it('shows the error text when failed', () => {
        render(<MessageBubble message={msg({ role: 'assistant', status: 'failed', error: 'Modello non raggiungibile' })} funFacts={[]} />);
        expect(screen.getByText('Modello non raggiungibile')).toBeInTheDocument();
    });

    it('falls back to a default error message when failed without an error', () => {
        render(<MessageBubble message={msg({ role: 'assistant', status: 'failed', error: null })} funFacts={[]} />);
        expect(screen.getByText(/Il consulente non ha risposto/)).toBeInTheDocument();
    });
});
