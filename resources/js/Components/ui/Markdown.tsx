import React from 'react';

/**
 * Minimal markdown renderer for the advisor report — no dependency, since the
 * model only emits a small subset: ## / ### headings, **bold**, and "- " lists.
 * Renders that subset to styled elements; anything else passes through as text.
 */

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

    lines.forEach((raw, idx) => {
        const line = raw.trimEnd();

        if (line.startsWith('### ')) {
            flushList();
            blocks.push(<h3 key={idx} className="text-sm font-semibold mt-4 mb-1">{renderInline(line.slice(4), `h3-${idx}`)}</h3>);
        } else if (line.startsWith('## ')) {
            flushList();
            blocks.push(<h2 key={idx} className="text-base font-semibold mt-4 mb-1.5">{renderInline(line.slice(3), `h2-${idx}`)}</h2>);
        } else if (/^[-*]\s+/.test(line)) {
            list.push(line.replace(/^[-*]\s+/, ''));
        } else if (line.trim() === '') {
            flushList();
        } else {
            flushList();
            blocks.push(<p key={idx} className="my-2 leading-relaxed">{renderInline(line, `p-${idx}`)}</p>);
        }
    });
    flushList();

    return <div className="text-sm">{blocks}</div>;
}
