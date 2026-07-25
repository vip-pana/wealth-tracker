interface Props {
    icon: React.ElementType;
    title: string;
    description: React.ReactNode;
    action?: React.ReactNode;
}

/**
 * Full-height centered empty state: a circled icon, a heading, a muted line
 * of explanatory text and an optional action. Shared across pages (Dashboard,
 * Goal, Pension) so the "nothing here yet" screen looks identical everywhere.
 */
export function EmptyState({ icon: Icon, title, description, action }: Props) {
    return (
        <div className="flex flex-col items-center justify-center h-full gap-4 text-center p-8">
            <div className="rounded-full bg-muted p-6">
                <Icon className="w-12 h-12 text-muted-foreground" />
            </div>
            <h2 className="text-xl font-semibold">{title}</h2>
            <p className="text-muted-foreground max-w-md">{description}</p>
            {action}
        </div>
    );
}
