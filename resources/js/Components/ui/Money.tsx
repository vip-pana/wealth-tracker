import { formatCurrency, formatCurrencyNoDecimals, formatCurrencyCompact } from '@/lib/formatters';
import { useValuesHidden } from '@/lib/privacy';

type Variant = 'default' | 'no-decimals' | 'compact';

const FORMATTERS: Record<Variant, (v: number) => string> = {
    'default': formatCurrency,
    'no-decimals': formatCurrencyNoDecimals,
    'compact': formatCurrencyCompact,
};

/**
 * A monetary amount in EUR. When the privacy toggle is on (provided via
 * PrivacyContext from AppLayout), the amount keeps its real text but gets the
 * `.money-hidden` class, which applies a light blur (see app.css) — readable
 * as "an amount is here" without leaking the figure; hover to peek. Use this
 * instead of formatCurrency directly wherever a euro figure is shown.
 */
export function Money({ value, variant = 'default', className }: { value: number; variant?: Variant; className?: string }) {
    const hidden = useValuesHidden();
    const cls = [hidden ? 'money-hidden' : '', className].filter(Boolean).join(' ');

    return <span className={cls || undefined}>{FORMATTERS[variant](value)}</span>;
}
