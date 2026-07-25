import { describe, it, expect } from 'vitest';
import { render, screen } from '@testing-library/react';
import PortfolioInsights from '@/Components/Dashboard/PortfolioInsights';
import type { PortfolioMetrics } from '@/types/analytics';

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

describe('PortfolioInsights — visibility', () => {
    it('renders nothing when there is no data', () => {
        const { container } = render(
            <PortfolioInsights metrics={{ hasData: false }} positionReturns={null} />,
        );
        expect(container.firstChild).toBeNull();
    });
});

describe('PortfolioInsights — concentration hint buckets', () => {
    it('flags a very concentrated portfolio at HHI >= 5000', () => {
        render(<PortfolioInsights metrics={metrics({ concentration: { hhi: 5000, top_category: 'ETF', top_share_pct: 80 } })} positionReturns={null} />);
        expect(screen.getByText('Portafoglio molto concentrato su una sola voce.')).toBeInTheDocument();
    });

    it('reports moderate concentration between 3000 and 5000', () => {
        render(<PortfolioInsights metrics={metrics({ concentration: { hhi: 3000, top_category: 'ETF', top_share_pct: 55 } })} positionReturns={null} />);
        expect(screen.getByText('Concentrazione moderata.')).toBeInTheDocument();
    });

    it('reports a well-distributed portfolio below 3000', () => {
        render(<PortfolioInsights metrics={metrics({ concentration: { hhi: 2000, top_category: 'ETF', top_share_pct: 40 } })} positionReturns={null} />);
        expect(screen.getByText('Patrimonio ben distribuito.')).toBeInTheDocument();
    });
});

describe('PortfolioInsights — liquidity hint', () => {
    it('warns when idle cash is >= 30%', () => {
        render(<PortfolioInsights metrics={metrics({ liquidity: { value: 40000, share_pct: 30 } })} positionReturns={null} />);
        expect(screen.getByText(/Quota di liquidità alta/)).toBeInTheDocument();
    });

    it('gives no warning below 30%', () => {
        render(<PortfolioInsights metrics={metrics({ liquidity: { value: 10000, share_pct: 29.9 } })} positionReturns={null} />);
        expect(screen.queryByText(/Quota di liquidità alta/)).not.toBeInTheDocument();
    });
});

describe('PortfolioInsights — allocation drift', () => {
    it('shows the top drift when its magnitude is >= 1 pp', () => {
        render(
            <PortfolioInsights
                metrics={metrics({ allocationDrift: [{ name: 'ETF', share_pct: 45, share_pct_then: 40, delta_pp: 5 }] })}
                positionReturns={null}
            />,
        );
        expect(screen.getByText(/ETF \+5\.0 punti/)).toBeInTheDocument();
    });

    it('hides drift below 1 pp', () => {
        render(
            <PortfolioInsights
                metrics={metrics({ allocationDrift: [{ name: 'ETF', share_pct: 40.5, share_pct_then: 40, delta_pp: 0.5 }] })}
                positionReturns={null}
            />,
        );
        expect(screen.queryByText('Spostamento maggiore:')).not.toBeInTheDocument();
    });
});

describe('PortfolioInsights — goal line', () => {
    it('reports a reached goal', () => {
        render(<PortfolioInsights metrics={metrics({ goalEta: { reached: true, target_value: 100000 } })} positionReturns={null} />);
        expect(screen.getByText('Obiettivo già raggiunto.')).toBeInTheDocument();
    });

    it('marks a projected date as on track', () => {
        render(
            <PortfolioInsights
                metrics={metrics({ goalEta: { reached: false, target_value: 500000, projected_date: '2045-01-01', on_track: true } })}
                positionReturns={null}
            />,
        );
        expect(screen.getByText(/in linea con la data obiettivo/)).toBeInTheDocument();
    });

    it('marks a projected date as past the target', () => {
        render(
            <PortfolioInsights
                metrics={metrics({ goalEta: { reached: false, target_value: 500000, projected_date: '2050-01-01', on_track: false } })}
                positionReturns={null}
            />,
        );
        expect(screen.getByText(/oltre la data obiettivo/)).toBeInTheDocument();
    });

    it('reports no growth toward the goal when there is no projected date', () => {
        render(
            <PortfolioInsights
                metrics={metrics({ goalEta: { reached: false, target_value: 500000 } })}
                positionReturns={null}
            />,
        );
        expect(screen.getByText(/non è in crescita verso l'obiettivo/)).toBeInTheDocument();
    });

    it('adds a low-confidence hint when flagged', () => {
        render(
            <PortfolioInsights
                metrics={metrics({ goalEta: { reached: false, target_value: 500000, projected_date: '2045-01-01', on_track: true, low_confidence: true } })}
                positionReturns={null}
            />,
        );
        expect(screen.getByText(/Stima poco affidabile/)).toBeInTheDocument();
    });
});
