import { render, cleanup } from '@testing-library/react';
import { afterEach, describe, expect, it } from 'vitest';
import { Markdown } from './Markdown';

afterEach(cleanup);

describe('Markdown', () => {
    it('renders a GitHub-style pipe table as a real <table>', () => {
        const content = [
            '| Categoria | Attuale | Target |',
            '|-----------|---------|--------|',
            '| Azioni | 46.29% | 50% |',
            '| Bitcoin | 30.42% | 25% |',
        ].join('\n');

        const { container } = render(<Markdown content={content} />);

        const table = container.querySelector('table');
        expect(table).not.toBeNull();
        expect(container.querySelectorAll('thead th')).toHaveLength(3);
        // Two body rows, not the separator.
        expect(container.querySelectorAll('tbody tr')).toHaveLength(2);
        expect(table?.textContent).toContain('Azioni');
        expect(table?.textContent).toContain('30.42%');
        // The separator row must never leak into the output as text.
        expect(container.textContent).not.toContain('---');
    });

    it('renders bold inside table cells', () => {
        const content = ['| A | B |', '|---|---|', '| x | **+5.42%** |'].join('\n');

        const { container } = render(<Markdown content={content} />);

        const strong = container.querySelector('td strong');
        expect(strong?.textContent).toBe('+5.42%');
    });

    it('leaves a lone pipe line (no separator) as a paragraph, not a table', () => {
        const { container } = render(<Markdown content={'| non è | una tabella |'} />);

        expect(container.querySelector('table')).toBeNull();
        expect(container.textContent).toContain('| non è | una tabella |');
    });

    it('still renders headings, bold and lists around a table', () => {
        const content = [
            '## Titolo',
            'Testo con **grassetto**.',
            '- primo',
            '- secondo',
            '',
            '| A | B |',
            '|---|---|',
            '| 1 | 2 |',
        ].join('\n');

        const { container } = render(<Markdown content={content} />);

        expect(container.querySelector('h2')?.textContent).toBe('Titolo');
        expect(container.querySelector('p strong')?.textContent).toBe('grassetto');
        expect(container.querySelectorAll('ul li')).toHaveLength(2);
        expect(container.querySelector('table')).not.toBeNull();
    });
});
