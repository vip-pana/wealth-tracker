import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { SessionSummary } from '@/Components/Advisor/types';

// SessionRow navigates with router.visit and stamps an internal-nav flag; mock
// both so the test focuses on the inline-rename commit logic.
const routerVisit = vi.fn();
const markInternalNavigation = vi.fn();
vi.mock('@inertiajs/react', () => ({ router: { visit: (...a: unknown[]) => routerVisit(...a) } }));
vi.mock('@/Components/Advisor/enterAnimation', () => ({
    markInternalNavigation: () => markInternalNavigation(),
}));

import { SessionRow } from '@/Components/Advisor/SessionRow';

function session(over: Partial<SessionSummary> = {}): SessionSummary {
    return {
        id: 1,
        kind: 'chat',
        title: 'Chat iniziale',
        status: 'done',
        generating: false,
        unread: false,
        created_at: null,
        ...over,
    };
}

beforeEach(() => {
    routerVisit.mockReset();
    markInternalNavigation.mockReset();
});

async function enterEditMode() {
    await userEvent.click(screen.getByRole('button', { name: 'Rinomina sessione' }));
    return screen.getByRole('textbox');
}

describe('SessionRow — navigation', () => {
    it('visits the session on click when not active', async () => {
        render(<SessionRow s={session({ id: 5 })} activeId={null} onRename={vi.fn()} />);
        await userEvent.click(screen.getByText('Chat iniziale'));
        expect(markInternalNavigation).toHaveBeenCalled();
        expect(routerVisit).toHaveBeenCalledWith('/advisor/5');
    });

    it('does not navigate when the row is already active', async () => {
        render(<SessionRow s={session({ id: 5 })} activeId={5} onRename={vi.fn()} />);
        await userEvent.click(screen.getByText('Chat iniziale'));
        expect(routerVisit).not.toHaveBeenCalled();
    });
});

describe('SessionRow — inline rename', () => {
    it('commits a changed, non-empty title on Enter', async () => {
        const onRename = vi.fn();
        render(<SessionRow s={session({ id: 3, title: 'Vecchio' })} activeId={null} onRename={onRename} />);
        const input = await enterEditMode();
        await userEvent.clear(input);
        await userEvent.type(input, 'Nuovo nome{Enter}');
        expect(onRename).toHaveBeenCalledWith(3, 'Nuovo nome');
    });

    it('does not rename when the title is unchanged', async () => {
        const onRename = vi.fn();
        render(<SessionRow s={session({ title: 'Uguale' })} activeId={null} onRename={onRename} />);
        const input = await enterEditMode();
        await userEvent.type(input, '{Enter}');
        expect(onRename).not.toHaveBeenCalled();
    });

    it('does not rename when the trimmed title is empty', async () => {
        const onRename = vi.fn();
        render(<SessionRow s={session({ title: 'Qualcosa' })} activeId={null} onRename={onRename} />);
        const input = await enterEditMode();
        await userEvent.clear(input);
        await userEvent.type(input, '   {Enter}');
        expect(onRename).not.toHaveBeenCalled();
    });

    it('discards the edit on Escape without renaming', async () => {
        const onRename = vi.fn();
        render(<SessionRow s={session({ title: 'Originale' })} activeId={null} onRename={onRename} />);
        const input = await enterEditMode();
        await userEvent.clear(input);
        await userEvent.type(input, 'scartato{Escape}');
        expect(onRename).not.toHaveBeenCalled();
        // Back to display mode.
        expect(screen.getByRole('button', { name: 'Rinomina sessione' })).toBeInTheDocument();
    });
});
