import { describe, it, expect, vi, afterEach } from 'vitest';
import { render, screen, act, fireEvent } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import PortfolioInsights, { buildInsights, ROTATE_MS } from '@/Components/Dashboard/PortfolioInsights';
import type { PortfolioMetrics, PositionReturns } from '@/types/analytics';

// A baseline "has data" metrics object; each test overrides the slice under test.
function metrics(over: Partial<Extract<PortfolioMetrics, { hasData: true }>> = {}): PortfolioMetrics {
    return {
        hasData: true,
        monthsTracked: 12,
        totalNetWorth: 100000,
        allocation: [],
        allocationDrift: [],
        concentration: { hhi: 2000, top_category: 'ETF', top_share_pct: 40 },
        liquidity: { value: 10000, share_pct: 10 },
        volatility: { monthly_stddev_pct: null, best_month_pct: null, worst_month_pct: null },
        goalEta: null,
        ...over,
    };
}

const returns: PositionReturns = {
    positions: [],
    aggregate: { cost_basis: 2000, current_value: 2400, unrealised_pnl: 400, unrealised_pnl_pct: 20, realised_pnl: 0 },
};

/** The insight of a given key, or undefined when it wasn't built. */
function insight(over: Partial<Extract<PortfolioMetrics, { hasData: true }>>, key: string) {
    return buildInsights(metrics(over), null).find((i) => i.key === key);
}

afterEach(() => {
    vi.useRealTimers();
});

describe('buildInsights — visibility', () => {
    it('builds nothing when there is no data', () => {
        expect(buildInsights({ hasData: false }, null)).toEqual([]);
    });

    it('always includes concentration and liquidity', () => {
        expect(buildInsights(metrics(), null).map((i) => i.key)).toEqual(['concentration', 'liquidity']);
    });

    it('leads with the investment return when there are positions', () => {
        expect(buildInsights(metrics(), returns)[0].key).toBe('returns');
    });
});

describe('buildInsights — concentration hint buckets', () => {
    it('flags a very concentrated portfolio at HHI >= 5000', () => {
        expect(insight({ concentration: { hhi: 5000, top_category: 'ETF', top_share_pct: 80 } }, 'concentration')?.hint)
            .toBe('Portafoglio molto concentrato su una sola voce.');
    });

    it('reports moderate concentration between 3000 and 5000', () => {
        expect(insight({ concentration: { hhi: 3000, top_category: 'ETF', top_share_pct: 55 } }, 'concentration')?.hint)
            .toBe('Concentrazione moderata.');
    });

    it('reports a well-distributed portfolio below 3000', () => {
        expect(insight({ concentration: { hhi: 2000, top_category: 'ETF', top_share_pct: 40 } }, 'concentration')?.hint)
            .toBe('Patrimonio ben distribuito.');
    });
});

describe('buildInsights — liquidity hint', () => {
    it('warns when idle cash is >= 30%', () => {
        expect(insight({ liquidity: { value: 40000, share_pct: 30 } }, 'liquidity')?.hint)
            .toMatch(/Quota di liquidità alta/);
    });

    it('gives no warning below 30%', () => {
        expect(insight({ liquidity: { value: 10000, share_pct: 29.9 } }, 'liquidity')?.hint).toBeUndefined();
    });
});

describe('buildInsights — allocation drift', () => {
    it('shows the top drift when its magnitude is >= 1 pp', () => {
        expect(insight({ allocationDrift: [{ name: 'ETF', share_pct: 45, share_pct_then: 40, delta_pp: 5 }] }, 'drift')?.value)
            .toBe('ETF +5.0 punti');
    });

    it('hides drift below 1 pp', () => {
        expect(insight({ allocationDrift: [{ name: 'ETF', share_pct: 40.5, share_pct_then: 40, delta_pp: 0.5 }] }, 'drift'))
            .toBeUndefined();
    });
});

describe('buildInsights — volatility', () => {
    it('is skipped without a stddev', () => {
        expect(insight({}, 'volatility')).toBeUndefined();
    });

    it('reports the swing with its best and worst month', () => {
        const built = insight({ volatility: { monthly_stddev_pct: 2.1, best_month_pct: 6, worst_month_pct: -3.5 } }, 'volatility');
        expect(built?.value).toBe('±2.10%');
        expect(built?.hint).toMatch(/Migliore .*peggiore/);
    });
});

describe('buildInsights — goal line', () => {
    it('reports a reached goal', () => {
        expect(insight({ goalEta: { reached: true, target_value: 100000 } }, 'goal')?.value)
            .toBe('Obiettivo già raggiunto.');
    });

    it('marks a projected date as on track', () => {
        expect(insight({ goalEta: { reached: false, target_value: 500000, projected_date: '2045-01-01', on_track: true } }, 'goal')?.value)
            .toMatch(/in linea con la data obiettivo/);
    });

    it('marks a projected date as past the target', () => {
        expect(insight({ goalEta: { reached: false, target_value: 500000, projected_date: '2050-01-01', on_track: false } }, 'goal')?.value)
            .toMatch(/oltre la data obiettivo/);
    });

    it('reports no growth toward the goal when there is no projected date', () => {
        expect(insight({ goalEta: { reached: false, target_value: 500000 } }, 'goal')?.value)
            .toMatch(/non è in crescita verso l'obiettivo/);
    });

    it('adds a low-confidence hint when flagged', () => {
        expect(insight({ goalEta: { reached: false, target_value: 500000, projected_date: '2045-01-01', on_track: true, low_confidence: true } }, 'goal')?.hint)
            .toMatch(/Stima poco affidabile/);
    });
});

describe('PortfolioInsights — carousel', () => {
    it('renders nothing when there is no data', () => {
        const { container } = render(<PortfolioInsights metrics={{ hasData: false }} positionReturns={null} />);
        expect(container.firstChild).toBeNull();
    });

    it('shows one insight at a time, not the whole list', () => {
        render(<PortfolioInsights metrics={metrics()} positionReturns={null} />);
        expect(screen.getByText('Concentrazione:')).toBeInTheDocument();
        expect(screen.queryByText('Liquidità ferma:')).not.toBeInTheDocument();
        expect(screen.getByText('1/2')).toBeInTheDocument();
    });

    it('shows the controls when there is more than one insight', () => {
        render(<PortfolioInsights metrics={metrics()} positionReturns={null} />);
        expect(screen.getByLabelText('Insight successivo')).toBeInTheDocument();
        expect(screen.getByLabelText('Insight precedente')).toBeInTheDocument();
    });

    it('advances on its own after the rotation interval', () => {
        vi.useFakeTimers();
        render(<PortfolioInsights metrics={metrics()} positionReturns={null} />);

        expect(screen.getByText('Concentrazione:')).toBeInTheDocument();
        act(() => vi.advanceTimersByTime(ROTATE_MS));
        expect(screen.getByText('Liquidità ferma:')).toBeInTheDocument();
        expect(screen.getByText('2/2')).toBeInTheDocument();
    });

    it('wraps around at the end', () => {
        vi.useFakeTimers();
        render(<PortfolioInsights metrics={metrics()} positionReturns={null} />);

        act(() => vi.advanceTimersByTime(ROTATE_MS * 2));
        expect(screen.getByText('Concentrazione:')).toBeInTheDocument();
    });

    it('steps forward by hand', async () => {
        const user = userEvent.setup();
        render(<PortfolioInsights metrics={metrics()} positionReturns={null} />);

        await user.click(screen.getByLabelText('Insight successivo'));
        expect(screen.getByText('Liquidità ferma:')).toBeInTheDocument();
    });

    it('steps backward by hand, wrapping to the last insight', async () => {
        const user = userEvent.setup();
        render(<PortfolioInsights metrics={metrics()} positionReturns={null} />);

        await user.click(screen.getByLabelText('Insight precedente'));
        expect(screen.getByText('Liquidità ferma:')).toBeInTheDocument();
        expect(screen.getByText('2/2')).toBeInTheDocument();
    });

    it('stops rotating once stepped by hand', () => {
        vi.useFakeTimers();
        render(<PortfolioInsights metrics={metrics()} positionReturns={null} />);

        // fireEvent, not userEvent: the latter deadlocks against fake timers.
        fireEvent.click(screen.getByLabelText('Insight successivo'));
        expect(screen.getByText('Liquidità ferma:')).toBeInTheDocument();

        act(() => vi.advanceTimersByTime(ROTATE_MS * 3));
        // Still on the hand-picked one: the timer was cancelled.
        expect(screen.getByText('Liquidità ferma:')).toBeInTheDocument();
    });

    it('navigates with the arrows only — no dot row to spend height on', () => {
        render(<PortfolioInsights metrics={metrics()} positionReturns={null} />);
        expect(screen.queryByLabelText('Liquidità ferma')).not.toBeInTheDocument();
    });

    it('pauses and resumes with the pause button', () => {
        vi.useFakeTimers();
        render(<PortfolioInsights metrics={metrics()} positionReturns={null} />);

        fireEvent.click(screen.getByLabelText('Metti in pausa la rotazione'));
        act(() => vi.advanceTimersByTime(ROTATE_MS * 2));
        expect(screen.getByText('Concentrazione:')).toBeInTheDocument();

        fireEvent.click(screen.getByLabelText('Riprendi la rotazione'));
        act(() => vi.advanceTimersByTime(ROTATE_MS));
        expect(screen.getByText('Liquidità ferma:')).toBeInTheDocument();
    });
});
