import { Separator } from '@/Components/ui/separator';
import { cn } from '@/lib/utils';

export function SectionHeading({
    icon: Icon,
    title,
    subtitle,
    className,
}: {
    icon: React.ElementType;
    title: string;
    subtitle: string;
    className?: string;
}) {
    return (
        <div className={cn('px-1 pt-2', className)}>
            <div className="flex items-center gap-2">
                <Icon className="w-4 h-4 text-primary flex-shrink-0" />
                <h2 className="text-sm font-semibold tracking-tight">{title}</h2>
            </div>
            <p className="text-xs text-muted-foreground mt-0.5">{subtitle}</p>
            <Separator className="mt-2" />
        </div>
    );
}
