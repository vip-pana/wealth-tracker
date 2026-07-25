interface Props {
    icon: React.ElementType;
    title: string;
    subtitle?: React.ReactNode;
    actions?: React.ReactNode;
}

export function PageHeader({ icon: Icon, title, subtitle, actions }: Props) {
    return (
        <div className="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 sm:gap-4">
            <div className="min-w-0">
                <div className="flex items-center gap-2">
                    <Icon className="w-5 h-5 text-primary shrink-0" />
                    <h1 className="text-lg font-bold truncate">{title}</h1>
                </div>
                {subtitle && (
                    <div className="text-sm text-muted-foreground mt-1">{subtitle}</div>
                )}
            </div>
            {actions && <div className="flex items-center gap-2 shrink-0">{actions}</div>}
        </div>
    );
}
