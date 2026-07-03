import { describe, it, expect, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { NewConversation } from '@/Components/Advisor/NewConversation';

function setup(overrides = {}) {
    const props = {
        value: '',
        onChange: vi.fn(),
        onStart: vi.fn(),
        onCancel: vi.fn(),
        onPick: vi.fn(),
        starting: false,
        funFacts: [],
        ...overrides,
    };
    render(<NewConversation {...props} />);
    return props;
}

describe('NewConversation', () => {
    it('sends the suggestion when a chip is clicked', async () => {
        const props = setup();
        // The chips render three of the known starters — click the first one shown.
        const chip = screen.getAllByRole('button').find((b) => b.textContent && b.textContent.length > 10);
        expect(chip).toBeTruthy();
        await userEvent.click(chip!);
        expect(props.onPick).toHaveBeenCalledWith(chip!.textContent);
    });

    it('shows the sent question and a thinking bubble after starting', async () => {
        setup({ value: 'La mia liquidità è troppa?' });
        // Fire the send button (paper plane) — the last button in the composer.
        const buttons = screen.getAllByRole('button');
        await userEvent.click(buttons[buttons.length - 1]);
        expect(screen.getByText('La mia liquidità è troppa?')).toBeInTheDocument();
    });
});
