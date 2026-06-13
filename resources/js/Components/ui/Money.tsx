import { formatCurrency, formatCurrencyNoDecimals, formatCurrencyCompact } from '@/lib/formatters';
import { useValuesHidden } from '@/lib/privacy';

type Variant = 'default' | 'no-decimals' | 'compact';

const FORMATTERS: Record<Variant, (v: number) => string> = {
    'default': formatCurrency,
    'no-decimals': formatCurrencyNoDecimals,
    'compact': formatCurrencyCompact,
};

// Fixed-width mask so the number of dots never hints at the magnitude.
const MASK = '€ ••••••';

/**
 * A monetary amount in EUR. When the privacy toggle is on (provided via
 * PrivacyContext from AppLayout), the figure is replaced by a fixed-width
 * masked placeholder keeping the € sign — so amounts read as hidden without
 * leaking their size. Use this instead of formatCurrency directly wherever a
 * euro figure is shown to the user.
 */
export function Money({ value, variant = 'default', className }: { value: number; variant?: Variant; className?: string }) {
    const hidden = useValuesHidden();
    const cls = className ? `money-value ${className}` : 'money-value';

    if (hidden) {
        return <span className={cls}>{MASK}</span>;
    }

    return <span className={cls}>{FORMATTERS[variant](value)}</span>;
}
