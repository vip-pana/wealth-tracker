interface Option<T extends string> {
    value: T;
    label: string;
}

interface Props<T extends string> {
    options: Option<T>[];
    value: T;
    onChange: (value: T) => void;
    size?: 'sm' | 'xs';
}

export function SegmentedToggle<T extends string>({ options, value, onChange, size = 'sm' }: Props<T>) {
    const textSize = size === 'xs' ? 'text-xs' : 'text-sm';
    return (
        <div className={`flex items-center rounded-lg border border-border overflow-hidden ${textSize}`}>
            {options.map((option) => (
                <button
                    key={option.value}
                    onClick={() => onChange(option.value)}
                    className={`px-3 py-1.5 ${value === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
