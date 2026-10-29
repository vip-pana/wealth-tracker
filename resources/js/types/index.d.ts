// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

export interface AppNotification {
    id: number;
    type: string;
    level: 'info' | 'success' | 'warning';
    title: string;
    body: string | null;
    action_url: string | null;
    created_at: string | null;
}

export interface SharedProps {
    flash: {
        success?: string | null;
        error?: string | null;
        undo?: string | null;
    };
    notifications: AppNotification[];
}

declare module '@inertiajs/core' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface PageProps extends SharedProps {}
}
