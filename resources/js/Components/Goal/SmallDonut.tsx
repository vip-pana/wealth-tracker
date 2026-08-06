import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';
import { useIsMobile } from '@/lib/media';

export function SmallDonut({ data, title }: { data: { name: string; value: number; color: string }[]; title: string }) {
    const visible = data.filter((d) => d.value > 0);
    // Two of these sit side by side, so on a phone each gets ~130px of width —
    // less than the 136px ring, which would clip it.
    const isMobile = useIsMobile();
    return (
        <div className="space-y-1 min-w-0 flex-1 max-w-40 sm:max-w-56 lg:flex-none lg:w-56">
            <p className="text-xs font-medium text-muted-foreground text-center">{title}</p>
            <div className="pointer-events-none">
                <ResponsiveContainer width="100%" height={isMobile ? 130 : 170}>
                    <PieChart>
                        <Pie
                            data={visible}
                            cx="50%"
                            cy="50%"
                            innerRadius={isMobile ? 34 : 46}
                            outerRadius={isMobile ? 52 : 68}
                            paddingAngle={3}
                            dataKey="value"
                            nameKey="name"
                        >
                            {visible.map((entry) => (
                                <Cell key={entry.name} fill={entry.color} stroke="hsl(var(--card))" strokeWidth={2} tabIndex={-1} style={{ outline: 'none' }} />
                            ))}
                        </Pie>
                    </PieChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
