import { formatCurrency, formatCurrencyNoDecimals, formatCurrencyCompact } from '@/lib/formatters';

type Variant = 'default' | 'no-decimals' | 'compact';

const FORMATTERS: Record<Variant, (v: number) => string> = {
    'default': formatCurrency,
    'no-decimals': formatCurrencyNoDecimals,
    'compact': formatCurrencyCompact,
};

/**
 * A monetary amount in EUR. Wrapping every amount in this component lets the
 * "nascondi valori" toggle blur all of them at once: under the
 * `.values-hidden` root class, `.money-value` is blurred via CSS — no
 * per-amount state wiring. Use this instead of calling formatCurrency directly
 * wherever a euro figure is shown to the user.
 */
export function Money({ value, variant = 'default', className }: { value: number; variant?: Variant; className?: string }) {
    return (
        <span className={className ? `money-value ${className}` : 'money-value'}>
            {FORMATTERS[variant](value)}
        </span>
    );
}
