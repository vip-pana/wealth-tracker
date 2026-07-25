import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import type { AppNotification } from '@/types/index.d';

// NotificationBell reads its list from usePage().props and acts via router.post/
// router.visit. Mock both so we can drive the badge, the open/close behaviour,
// the read-then-navigate flow, and the relative-time labels.
const routerPost = vi.fn();
const routerVisit = vi.fn();
let pageProps: { notifications: AppNotification[] } = { notifications: [] };

vi.mock('@inertiajs/react', () => ({
    usePage: () => ({ props: pageProps }),
    router: {
        post: (...a: unknown[]) => routerPost(...a),
        visit: (...a: unknown[]) => routerVisit(...a),
    },
}));

import { NotificationBell } from '@/Components/Layout/NotificationBell';

function notif(over: Partial<AppNotification> = {}): AppNotification {
    return {
        id: 1,
        type: 'advisor_report',
        level: 'info',
        title: 'Analisi pronta',
        body: null,
        action_url: null,
        created_at: null,
        ...over,
    };
}

const iso = (msAgo: number) => new Date(Date.now() - msAgo).toISOString();

beforeEach(() => {
    routerPost.mockReset();
    routerVisit.mockReset();
    pageProps = { notifications: [] };
});

describe('NotificationBell — unread badge', () => {
    it('shows no badge when there are no notifications', () => {
        render(<NotificationBell />);
        expect(screen.queryByText('9+')).not.toBeInTheDocument();
        // aria-label reflects the empty state.
        expect(screen.getByRole('button', { name: 'Notifiche' })).toBeInTheDocument();
    });

    it('shows the exact count up to 9', () => {
        pageProps = { notifications: [notif({ id: 1 }), notif({ id: 2 }), notif({ id: 3 })] };
        render(<NotificationBell />);
        expect(screen.getByText('3')).toBeInTheDocument();
    });

    it('caps the badge at "9+" beyond nine', () => {
        pageProps = { notifications: Array.from({ length: 12 }, (_, i) => notif({ id: i + 1 })) };
        render(<NotificationBell />);
        expect(screen.getByText('9+')).toBeInTheDocument();
    });
});

describe('NotificationBell — panel open/close', () => {
    it('opens the panel on click and lists the notifications', async () => {
        pageProps = { notifications: [notif({ title: 'Report pronto' })] };
        render(<NotificationBell />);
        await userEvent.click(screen.getByRole('button', { name: /Notifiche/ }));
        expect(screen.getByText('Report pronto')).toBeInTheDocument();
    });

    it('closes the panel on Escape', async () => {
        pageProps = { notifications: [notif({ title: 'Report pronto' })] };
        render(<NotificationBell />);
        await userEvent.click(screen.getByRole('button', { name: /Notifiche/ }));
        expect(screen.getByText('Report pronto')).toBeInTheDocument();
        await userEvent.keyboard('{Escape}');
        expect(screen.queryByText('Report pronto')).not.toBeInTheDocument();
    });
});

describe('NotificationBell — read then navigate', () => {
    it('marks a notification read and follows its action_url', async () => {
        pageProps = { notifications: [notif({ id: 42, title: 'Vai qui', action_url: '/advisor/7' })] };
        render(<NotificationBell />);
        await userEvent.click(screen.getByRole('button', { name: /Notifiche/ }));
        await userEvent.click(screen.getByText('Vai qui'));

        expect(routerPost).toHaveBeenCalledWith('/notifications/42/read', {}, expect.any(Object));
        // The visit happens in the post's onSuccess callback; invoke it to assert.
        const opts = routerPost.mock.calls[0][2] as { onSuccess: () => void };
        opts.onSuccess();
        expect(routerVisit).toHaveBeenCalledWith('/advisor/7');
    });

    it('marks all as read', async () => {
        pageProps = { notifications: [notif({ id: 1 })] };
        render(<NotificationBell />);
        await userEvent.click(screen.getByRole('button', { name: /Notifiche/ }));
        await userEvent.click(screen.getByText('Segna tutte lette'));
        expect(routerPost).toHaveBeenCalledWith('/notifications/read-all', {}, expect.any(Object));
    });
});

describe('NotificationBell — relative time labels', () => {
    it('labels a fresh notification "ora", then minutes / hours / days', async () => {
        pageProps = {
            notifications: [
                notif({ id: 1, title: 'A', created_at: iso(10 * 1000) }),
                notif({ id: 2, title: 'B', created_at: iso(5 * 60_000) }),
                notif({ id: 3, title: 'C', created_at: iso(3 * 60 * 60_000) }),
                notif({ id: 4, title: 'D', created_at: iso(2 * 24 * 60 * 60_000) }),
            ],
        };
        render(<NotificationBell />);
        await userEvent.click(screen.getByRole('button', { name: /Notifiche/ }));
        expect(screen.getByText('ora')).toBeInTheDocument();
        expect(screen.getByText('5 min fa')).toBeInTheDocument();
        expect(screen.getByText('3 h fa')).toBeInTheDocument();
        expect(screen.getByText('2 g fa')).toBeInTheDocument();
    });
});
