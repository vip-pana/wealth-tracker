export interface SharedProps {
    flash: {
        success?: string | null;
        error?: string | null;
    };
}

declare module '@inertiajs/core' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface PageProps extends SharedProps {}
}
