import type { PageProps as InertiaPageProps } from '@inertiajs/core';

export interface SharedProps {
    flash: {
        success?: string | null;
        error?: string | null;
    };
}

declare module '@inertiajs/core' {
    interface PageProps extends SharedProps {}
}
