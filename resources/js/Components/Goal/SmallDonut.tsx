import { PieChart, Pie, Cell, ResponsiveContainer } from 'recharts';

export function SmallDonut({ data, title }: { data: { name: string; value: number; color: string }[]; title: string }) {
    const visible = data.filter((d) => d.value > 0);
    return (
        <div className="space-y-1 min-w-0 flex-1 max-w-[14rem] lg:flex-none lg:w-56">
            <p className="text-xs font-medium text-muted-foreground text-center">{title}</p>
            <div className="pointer-events-none">
                <ResponsiveContainer width="100%" height={170}>
                    <PieChart>
                        <Pie
                            data={visible}
                            cx="50%"
                            cy="50%"
                            innerRadius={46}
                            outerRadius={68}
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
