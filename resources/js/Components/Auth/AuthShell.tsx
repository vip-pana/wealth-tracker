import { Head } from '@inertiajs/react';
import { useEffect } from 'react';

interface Props {
    title: string;
    heading: string;
    subheading: string;
    children: React.ReactNode;
}

// The one screen shown before the app exists. It has no AppLayout — no sidebar,
// no notification bell, nothing that would query data the visitor cannot see
// yet — so it carries its own theme handling.
export default function AuthShell({ title, heading, subheading, children }: Props) {
    // AppLayout owns the theme everywhere else; here the class has to be applied
    // directly. Same key and same default as AppLayout — dark unless 'light' was
    // explicitly stored — so the login screen never flips theme on sign-in.
    useEffect(() => {
        document.documentElement.classList.toggle('dark', localStorage.getItem('theme') !== 'light');
    }, []);

    return (
        <>
            <Head title={title} />
            <div className="min-h-screen flex items-center justify-center bg-background px-4 py-12">
                <div className="w-full max-w-sm">
                    <div className="mb-8 text-center">
                        <h1 className="text-2xl font-semibold tracking-tight text-foreground">{heading}</h1>
                        <p className="mt-2 text-sm text-muted-foreground">{subheading}</p>
                    </div>

                    <div className="rounded-lg border border-border bg-card p-6 shadow-sm">
                        {children}
                    </div>

                    <p className="mt-6 text-center text-xs text-muted-foreground">
                        Wealth Tracker
                    </p>
                </div>
            </div>
        </>
    );
}
