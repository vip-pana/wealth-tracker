import { createContext, useContext } from 'react';

/**
 * Whether monetary amounts are hidden. Provided by AppLayout (which owns the
 * persisted toggle) and consumed by the <Money> component to render a masked
 * placeholder instead of the figure. Defaults to false so amounts render
 * normally outside the layout (e.g. in isolation).
 */
export const PrivacyContext = createContext(false);

export function useValuesHidden(): boolean {
    return useContext(PrivacyContext);
}

/** Masked placeholder for a chart Y-axis tick when values are hidden. */
export const MASKED_TICK = '•••';
