import { describe, it, expect } from 'vitest';
import { render } from '@testing-library/react';
import { Money } from '@/Components/ui/Money';
import { PrivacyContext } from '@/lib/privacy';

const normalize = (s: string) => s.replace(/[\u00a0\u202f]/g, " ");

// Query the single <span> Money renders rather than by text: the euro/number
// separators are locale glyphs and the class (not the figure) is the logic
// under test.
function renderMoney(node: React.ReactElement): HTMLElement {
    const { container } = render(node);
    return container.querySelector('span')!;
}

describe('Money', () => {
    it('renders the formatted amount without a masking class by default', () => {
        const el = renderMoney(<Money value={1234.5} />);
        expect(normalize(el.textContent!)).toContain('1234,50');
        expect(el).not.toHaveClass('money-hidden');
    });

    it('applies the no-decimals variant', () => {
        const el = renderMoney(<Money value={1234.5} variant="no-decimals" />);
        expect(el.textContent).not.toContain(',');
        expect(normalize(el.textContent!)).toContain('1235');
    });

    it('applies the compact variant for thousands', () => {
        const el = renderMoney(<Money value={12300} variant="compact" />);
        expect(el.textContent).toBe('€ 12.3k');
    });

    it('keeps the real figure but adds the blur-sm class when privacy is on', () => {
        const el = renderMoney(
            <PrivacyContext.Provider value={true}>
                <Money value={1234.5} />
            </PrivacyContext.Provider>,
        );
        // The amount text is still in the DOM (blur is visual only via CSS).
        expect(normalize(el.textContent!)).toContain('1234,50');
        expect(el).toHaveClass('money-hidden');
    });

    it('merges a passed className alongside the masking class', () => {
        const el = renderMoney(
            <PrivacyContext.Provider value={true}>
                <Money value={10} className="font-mono" />
            </PrivacyContext.Provider>,
        );
        expect(el).toHaveClass('money-hidden');
        expect(el).toHaveClass('font-mono');
    });
});
