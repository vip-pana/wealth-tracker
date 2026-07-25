import { BarChart3 } from 'lucide-react';

interface Props {
    message: string;
}

export function ChartEmptyState({ message }: Props) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 text-center" style={{ height: 200 }}>
            <BarChart3 className="w-8 h-8 text-muted-foreground/50" />
            <p className="text-xs text-muted-foreground max-w-[16rem]">{message}</p>
        </div>
    );
}
