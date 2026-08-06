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
    // A five-option toggle is wider than a phone, and the options must stay
    // legible, so the group scrolls rather than compressing or widening the
    // page. min-w-0 lets it shrink inside a flex parent at all.
    return (
        <div className={`flex min-w-0 max-w-full items-center overflow-x-auto rounded-lg border border-border ${textSize}`}>
            {options.map((option) => (
                <button
                    key={option.value}
                    onClick={() => onChange(option.value)}
                    className={`shrink-0 whitespace-nowrap px-3 py-1.5 ${value === option.value ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground'}`}
                >
                    {option.label}
                </button>
            ))}
        </div>
    );
}
