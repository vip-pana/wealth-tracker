interface Props {
    icon: React.ElementType;
    title: string;
    subtitle?: React.ReactNode;
    actions?: React.ReactNode;
}

export function PageHeader({ icon: Icon, title, subtitle, actions }: Props) {
    return (
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4 shrink-0">
            <div className="min-w-0">
                <div className="flex items-center gap-2">
                    <Icon className="w-5 h-5 text-primary shrink-0" />
                    <h1 className="text-lg font-bold truncate">{title}</h1>
                </div>
                {subtitle && (
                    <div className="text-sm text-muted-foreground mt-1">{subtitle}</div>
                )}
            </div>
            {/* Actions wrap among themselves rather than pushing the page wider:
                a header can carry two toggles, which do not fit a phone in a row. */}
            {actions && <div className="flex min-w-0 max-w-full shrink-0 flex-wrap items-center gap-2">{actions}</div>}
        </div>
    );
}
