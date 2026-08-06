import React from 'react';

/**
 * Minimal markdown renderer for the advisor report — no dependency, since the
 * model only emits a small subset: # headings (any level), **bold**, "- "
 * lists, and GitHub-style pipe tables. Renders that subset to styled elements;
 * anything else passes as text.
 *
 * The advisor is steered (in its system prompt) to render tabular data through
 * a widget rather than draw a table in prose, but a local model still slips a
 * `| a | b |` table into the text now and then. Parsing it here means that when
 * it does, the reader sees a clean table instead of a wall of raw pipes and
 * dashes — a graceful degrade, not the primary path.
 */

/** A line is a table row when it starts and ends with a pipe (ignoring spaces). */
function isTableRow(line: string): boolean {
    const t = line.trim();
    return t.startsWith('|') && t.endsWith('|') && t.length > 1;
}

/** The `|---|:--:|` separator under a table header (dashes, colons, pipes only). */
function isTableSeparator(line: string): boolean {
    return isTableRow(line) && /^\s*\|(\s*:?-+:?\s*\|)+\s*$/.test(line);
}

/** Split a `| a | b |` row into its cell texts, dropping the outer empties. */
function splitTableCells(line: string): string[] {
    const t = line.trim();
    return t
        .slice(1, -1)
        .split('|')
        .map((c) => c.trim());
}

// Heading styling by level; h4+ collapse to the smallest style.
const HEADING_CLASS: Record<number, string> = {
    1: 'text-lg font-bold mt-4 mb-2',
    2: 'text-base font-semibold mt-4 mb-1.5',
    3: 'text-sm font-semibold mt-4 mb-1',
    4: 'text-sm font-semibold mt-3 mb-1',
};

function renderInline(text: string, keyPrefix: string): React.ReactNode[] {
    // Split on **bold** spans, keeping the delimiters' content.
    return text.split(/(\*\*[^*]+\*\*)/g).map((part, i) => {
        if (part.startsWith('**') && part.endsWith('**')) {
            return <strong key={`${keyPrefix}-${i}`}>{part.slice(2, -2)}</strong>;
        }
        return <React.Fragment key={`${keyPrefix}-${i}`}>{part}</React.Fragment>;
    });
}

export function Markdown({ content }: { content: string }) {
    const lines = content.split('\n');
    const blocks: React.ReactNode[] = [];
    let list: string[] = [];

    const flushList = () => {
        if (list.length === 0) return;
        const items = list;
        blocks.push(
            <ul key={`ul-${blocks.length}`} className="list-disc pl-5 space-y-1 my-2">
                {items.map((item, i) => (
                    <li key={i}>{renderInline(item, `li-${blocks.length}-${i}`)}</li>
                ))}
            </ul>,
        );
        list = [];
    };

    const flushTable = (header: string[], rows: string[][], key: number) => {
        blocks.push(
            <div key={`tbl-wrap-${key}`} className="my-2 overflow-x-auto">
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b border-border">
                            {header.map((cell, i) => (
                                <th key={i} className="px-2 py-1 text-left font-semibold">
                                    {renderInline(cell, `th-${key}-${i}`)}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row, r) => (
                            <tr key={r} className="border-b border-border/50 last:border-0">
                                {header.map((_, c) => (
                                    <td key={c} className="px-2 py-1 align-top">
                                        {renderInline(row[c] ?? '', `td-${key}-${r}-${c}`)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>,
        );
    };

    for (let idx = 0; idx < lines.length; idx++) {
        const line = lines[idx].trimEnd();
        const heading = /^(#{1,6})\s+(.*)$/.exec(line);

        // A table starts with a header row immediately followed by a `|---|`
        // separator; consume every contiguous body row after it.
        if (isTableRow(line) && idx + 1 < lines.length && isTableSeparator(lines[idx + 1])) {
            flushList();
            const header = splitTableCells(line);
            const rows: string[][] = [];
            let j = idx + 2;
            while (j < lines.length && isTableRow(lines[j]) && !isTableSeparator(lines[j])) {
                rows.push(splitTableCells(lines[j]));
                j++;
            }
            flushTable(header, rows, idx);
            idx = j - 1;
        } else if (heading) {
            flushList();
            const level = Math.min(heading[1].length, 4);
            const Tag = (`h${Math.min(heading[1].length, 6)}`) as keyof React.JSX.IntrinsicElements;
            blocks.push(
                <Tag key={idx} className={HEADING_CLASS[level]}>
                    {renderInline(heading[2], `h-${idx}`)}
                </Tag>,
            );
        } else if (/^[-*]\s+/.test(line)) {
            list.push(line.replace(/^[-*]\s+/, ''));
        } else if (line.trim() === '') {
            flushList();
        } else {
            flushList();
            blocks.push(<p key={idx} className="my-2 leading-relaxed">{renderInline(line, `p-${idx}`)}</p>);
        }
    }
    flushList();

    // This is model output, so a long unbroken token (a URL, an ISIN list) can
    // arrive at any width and nothing else constrains it — break-words keeps it
    // inside the bubble instead of widening the page.
    return <div className="text-sm wrap-break-word">{blocks}</div>;
}
