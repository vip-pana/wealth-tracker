import { forwardRef, useLayoutEffect, useRef, type TextareaHTMLAttributes } from 'react';

/**
 * A textarea that grows with its content, from one row up to `maxRows`, then
 * scrolls. Used by the advisor chat inputs, which start as a single line but
 * should expand as the user writes a longer message instead of trapping the
 * text in a tiny box.
 *
 * It measures the natural scrollHeight after each render (so it also shrinks
 * back when the value is cleared on send) and caps the height at maxRows lines
 * derived from the computed line-height. `resize-none` stays: the height is
 * driven by content, not a manual drag handle.
 */
export const AutoTextarea = forwardRef<
    HTMLTextAreaElement,
    TextareaHTMLAttributes<HTMLTextAreaElement> & { maxRows?: number }
>(function AutoTextarea({ maxRows = 15, value, style, ...props }, forwardedRef) {
    const innerRef = useRef<HTMLTextAreaElement | null>(null);

    const setRef = (node: HTMLTextAreaElement | null) => {
        innerRef.current = node;
        if (typeof forwardedRef === 'function') forwardedRef(node);
        else if (forwardedRef) forwardedRef.current = node;
    };

    useLayoutEffect(() => {
        const el = innerRef.current;
        if (!el) return;

        // Reset first so scrollHeight reflects the content, not the previous
        // (possibly taller) height, letting the box shrink as well as grow.
        el.style.height = 'auto';

        const styles = window.getComputedStyle(el);
        const lineHeight = parseFloat(styles.lineHeight) || 20;
        const paddingY = parseFloat(styles.paddingTop) + parseFloat(styles.paddingBottom);
        const borderY = parseFloat(styles.borderTopWidth) + parseFloat(styles.borderBottomWidth);
        const maxHeight = lineHeight * maxRows + paddingY + borderY;

        const next = Math.min(el.scrollHeight, maxHeight);
        el.style.height = `${next}px`;
        el.style.overflowY = el.scrollHeight > maxHeight ? 'auto' : 'hidden';
    }, [value, maxRows]);

    return <textarea ref={setRef} value={value} style={style} {...props} />;
});
