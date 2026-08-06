import { BarChart3 } from 'lucide-react';

interface Props {
    message: string;
}

export function ChartEmptyState({ message }: Props) {
    return (
        <div className="flex h-full min-h-50 flex-col items-center justify-center gap-2 text-center">
            <BarChart3 className="w-8 h-8 text-muted-foreground/50" />
            <p className="text-xs text-muted-foreground max-w-[16rem]">{message}</p>
        </div>
    );
}
