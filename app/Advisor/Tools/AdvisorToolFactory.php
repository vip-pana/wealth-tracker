<?php

declare(strict_types=1);

namespace App\Advisor\Tools;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Models\Snapshot;
use Illuminate\Support\Carbon;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Tool as PrismTool;

/**
 * Builds the tools the advisor model can call to pull fresh, targeted data on
 * demand instead of receiving the whole portfolio up front. Each tool wraps
 * data the app already computes (via BuildAdvisorContext, which reuses the
 * dashboard's own numbers so the advisor never diverges from what the user
 * sees) and returns it as already-annotated text — signs spelled out, unreliable
 * fields labelled — because a local model will otherwise misread a raw number
 * (e.g. flip the sign of a P&L).
 */
class AdvisorToolFactory
{
    public function __construct(
        private readonly BuildAdvisorContext $buildContext,
    ) {}

    /**
     * @return list<PrismTool>
     */
    public function make(): array
    {
        return [
            $this->getPosition(),
            $this->getPortfolioSummary(),
            $this->simulatePac(),
            $this->netWorthBetween(),
        ];
    }

    /**
     * Detail of a single transaction-managed position, looked up by (partial)
     * name. Returns the annotated figures ComputePositionReturns derived from
     * the imported transactions.
     */
    private function getPosition(): PrismTool
    {
        return Tool::as('get_position')
            ->for('Dettagli di una singola posizione di investimento gestita da transazioni (ETF, crypto): quote possedute, prezzo medio di carico, valore attuale, guadagno/perdita e rendimento reale. Usalo quando la domanda riguarda un singolo strumento.')
            ->withStringParameter('name', 'Nome (anche parziale) della posizione, es. Bitcoin, ACWI, Oro')
            ->using(function (string $name): string {
                /** @var array{positions: list<array{id: int, name: string, shares: float, average_cost: float, cost_basis: float, current_value: float|null, unrealised_pnl: float|null, unrealised_pnl_pct: float|null, realised_pnl: float}>}|null $returns */
                $returns = $this->buildContext->run()['positionReturns'] ?? null;

                if ($returns === null || $returns['positions'] === []) {
                    return 'Nessuna posizione gestita da transazioni è disponibile.';
                }

                $needle = mb_strtolower(trim($name));
                $match = null;
                foreach ($returns['positions'] as $position) {
                    if (str_contains(mb_strtolower($position['name']), $needle)) {
                        $match = $position;
                        break;
                    }
                }

                if ($match === null) {
                    $available = implode(', ', array_map(fn (array $p): string => $p['name'], $returns['positions']));

                    return "Nessuna posizione trovata per «{$name}». Posizioni disponibili: {$available}.";
                }

                return $this->describePosition($match);
            });
    }

    /**
     * Whole-portfolio snapshot: net worth, allocation, concentration and idle
     * liquidity. Takes no arguments — the model calls it for the big picture.
     */
    private function getPortfolioSummary(): PrismTool
    {
        return Tool::as('get_portfolio_summary')
            ->for('Riassunto complessivo del portafoglio: patrimonio netto totale, allocazione per categoria in percentuale, concentrazione e liquidità ferma. Usalo per domande generali sullo stato del portafoglio.')
            ->using(function (): string {
                $portfolio = $this->portfolio();

                if ($portfolio === null) {
                    return 'Non ci sono ancora dati di portafoglio sufficienti.';
                }

                return $this->describePortfolio($portfolio);
            });
    }

    /**
     * What-if on the monthly PAC: how a different monthly contribution changes
     * the projected time to reach the goal. Built on the trend gain the metrics
     * already computed, so it stays consistent with the dashboard forecast and
     * inherits its low-confidence honesty.
     */
    private function simulatePac(): PrismTool
    {
        return Tool::as('simulate_pac')
            ->for('Simula l\'effetto di un diverso importo mensile del piano di accumulo (PAC) sul tempo stimato per raggiungere l\'obiettivo. Usalo quando l\'utente chiede cosa succede se cambia il versamento mensile.')
            ->withNumberParameter('monthly_amount', 'Nuovo importo mensile in euro, es. 600')
            ->using(function (int|float $monthly_amount): string {
                $context = $this->buildContext->run();
                $portfolio = $this->portfolio($context);

                if ($portfolio === null) {
                    return 'Non ci sono ancora dati sufficienti per simulare il PAC.';
                }

                return $this->describePacSimulation($portfolio, $this->expectedAnnualReturn($context), (float) $monthly_amount);
            });
    }

    /**
     * Net-worth change between two dates, read from the monthly snapshots. Answers
     * "how did I do compared to N months ago?".
     */
    private function netWorthBetween(): PrismTool
    {
        return Tool::as('net_worth_between')
            ->for('Confronta il patrimonio netto tra due date, restituendo i valori e la variazione. Usalo per domande su come è andato il patrimonio in un periodo (es. rispetto a 3 mesi fa).')
            ->withStringParameter('from', 'Data iniziale in formato AAAA-MM-GG')
            ->withStringParameter('to', 'Data finale in formato AAAA-MM-GG')
            ->using(function (string $from, string $to): string {
                return $this->describeNetWorthBetween($from, $to);
            });
    }

    /**
     * The computed portfolio metrics, or null when there isn't enough data. The
     * shape mirrors ComputePortfolioMetrics::run()'s non-empty return. Accepts
     * an already-built context so a caller that also needs other slices doesn't
     * recompute it.
     *
     * @param  array<string, mixed>|null  $context
     * @return array{monthsTracked: int, totalNetWorth: float, allocation: list<array{name: string, value: float, share_pct: float}>, concentration: array{hhi: float, top_category: string, top_share_pct: float}, liquidity: array{value: float, share_pct: float}, goalEta: array<string, mixed>|null}|null
     */
    private function portfolio(?array $context = null): ?array
    {
        $context ??= $this->buildContext->run();

        /** @var array<string, mixed> $portfolio */
        $portfolio = $context['portfolio'];

        if (($portfolio['hasData'] ?? false) !== true) {
            return null;
        }

        /** @var array{monthsTracked: int, totalNetWorth: float, allocation: list<array{name: string, value: float, share_pct: float}>, concentration: array{hhi: float, top_category: string, top_share_pct: float}, liquidity: array{value: float, share_pct: float}, goalEta: array<string, mixed>|null} $portfolio */
        return $portfolio;
    }

    /**
     * The expected annual real return used to compound the PAC simulation,
     * derived from the user's stated risk tolerance (a long-horizon goal is
     * driven by compounding, which a linear projection ignores entirely). These
     * are deliberately conservative planning assumptions, not a forecast; the
     * simulation labels them as such.
     *
     * @param  array<string, mixed>  $context
     * @return array{rate: float, source: string}
     */
    private function expectedAnnualReturn(array $context): array
    {
        $profile = $context['investorProfile'] ?? null;
        $risk = is_array($profile) && is_string($profile['risk_tolerance'] ?? null)
            ? $profile['risk_tolerance']
            : null;

        return match ($risk) {
            'low' => ['rate' => 0.03, 'source' => 'profilo di rischio basso'],
            'high' => ['rate' => 0.07, 'source' => 'profilo di rischio alto'],
            'medium' => ['rate' => 0.05, 'source' => 'profilo di rischio medio'],
            default => ['rate' => 0.05, 'source' => 'ipotesi prudente predefinita (profilo non indicato)'],
        };
    }

    /**
     * @param  array{name: string, shares: float, average_cost: float, cost_basis: float, current_value: float|null, unrealised_pnl: float|null, unrealised_pnl_pct: float|null, realised_pnl: float}  $p
     */
    private function describePosition(array $p): string
    {
        $lines = [
            "Posizione: {$p['name']}",
            'Quote possedute: '.$this->num($p['shares'], 6),
            'Prezzo medio di carico: '.$this->eur($p['average_cost']),
            'Costo totale investito: '.$this->eur($p['cost_basis']),
        ];

        if ($p['current_value'] !== null) {
            $lines[] = 'Valore attuale: '.$this->eur($p['current_value']);
            $lines[] = 'Guadagno/perdita non realizzato: '.$this->signedEur($p['unrealised_pnl'] ?? 0.0)
                .($p['unrealised_pnl_pct'] !== null ? ' ('.$this->signedPct($p['unrealised_pnl_pct']).')' : '');
        } else {
            $lines[] = 'Valore attuale: non disponibile (prezzo di mercato mancante).';
        }

        if ($p['realised_pnl'] != 0.0) {
            $lines[] = 'Guadagno/perdita realizzato (da vendite): '.$this->signedEur($p['realised_pnl']);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{monthsTracked: int, totalNetWorth: float, allocation: list<array{name: string, value: float, share_pct: float}>, concentration: array{hhi: float, top_category: string, top_share_pct: float}, liquidity: array{value: float, share_pct: float}, goalEta: array<string, mixed>|null}  $portfolio
     */
    private function describePortfolio(array $portfolio): string
    {
        $allocation = $portfolio['allocation'];
        $concentration = $portfolio['concentration'];
        $liquidity = $portfolio['liquidity'];

        $lines = [
            'Patrimonio netto totale: '.$this->eur($portfolio['totalNetWorth']),
            'Mesi tracciati: '.$portfolio['monthsTracked'],
            'Allocazione per categoria:',
        ];

        foreach ($allocation as $slice) {
            $lines[] = "  - {$slice['name']}: ".$this->eur($slice['value']).' ('.$this->num($slice['share_pct'], 1).'%)';
        }

        $lines[] = "Categoria più pesante: {$concentration['top_category']} (".$this->num($concentration['top_share_pct'], 1).'%)';
        $lines[] = 'Liquidità ferma: '.$this->eur($liquidity['value']).' ('.$this->num($liquidity['share_pct'], 1).'%)';

        return implode("\n", $lines);
    }

    /**
     * @param  array{monthsTracked: int, totalNetWorth: float, allocation: list<array{name: string, value: float, share_pct: float}>, concentration: array{hhi: float, top_category: string, top_share_pct: float}, liquidity: array{value: float, share_pct: float}, goalEta: array<string, mixed>|null}  $portfolio
     * @param  array{rate: float, source: string}  $expectedReturn
     */
    private function describePacSimulation(array $portfolio, array $expectedReturn, float $monthlyAmount): string
    {
        $goalEta = $portfolio['goalEta'];

        if ($goalEta === null || ! isset($goalEta['target_value'])) {
            return 'Non c\'è un obiettivo impostato, quindi non posso stimare l\'effetto del PAC.';
        }

        if (($goalEta['reached'] ?? false) === true) {
            return 'L\'obiettivo è già stato raggiunto.';
        }

        $target = is_numeric($goalEta['target_value']) ? (float) $goalEta['target_value'] : 0.0;
        $current = $portfolio['totalNetWorth'];
        $annual = $expectedReturn['rate'];
        $monthlyRate = (1.0 + $annual) ** (1.0 / 12.0) - 1.0;

        // Compound projection: the current net worth grows at the assumed rate
        // while the monthly PAC is added and itself compounds. A linear
        // "remaining / monthly" ignores compounding and yields absurd century
        // ETAs on long-horizon goals, so we iterate month by month until the
        // future value reaches the target (capped so an unreachable goal stops).
        $maxMonths = 1200; // 100 years — a hard stop for the search
        $balance = $current;
        $months = 0;
        while ($balance < $target && $months < $maxMonths) {
            $balance = $balance * (1.0 + $monthlyRate) + $monthlyAmount;
            $months++;
        }

        $lines = [
            'Simulazione con versamento mensile di '.$this->eur($monthlyAmount).':',
            'Patrimonio attuale '.$this->eur($current).', obiettivo '.$this->eur($target).'.',
            'Ipotesi di rendimento annuo: '.$this->num($annual * 100, 1).'% ('.$expectedReturn['source'].') — è un\'assunzione di pianificazione, NON una previsione di mercato.',
        ];

        if ($months >= $maxMonths) {
            $lines[] = 'Con questi versamenti e questa ipotesi di rendimento, l\'obiettivo non viene raggiunto entro un orizzonte ragionevole (oltre 100 anni). Il target è probabilmente troppo ambizioso rispetto al versamento: vale la pena rivedere obiettivo, importo o orizzonte.';

            return implode("\n", $lines);
        }

        $lines[] = 'Stima (con capitalizzazione composta): circa '.$months.' mesi, ovvero '.$this->num($months / 12, 1).' anni, per raggiungere l\'obiettivo.';

        if (($goalEta['low_confidence'] ?? false) === true) {
            $lines[] = 'ATTENZIONE: pochi mesi di dati tracciati. La stima dipende molto dall\'ipotesi di rendimento: trattala come ordine di grandezza, non come previsione.';
        }

        return implode("\n", $lines);
    }

    private function describeNetWorthBetween(string $from, string $to): string
    {
        try {
            $fromDate = Carbon::createFromFormat('Y-m-d', $from)?->startOfDay();
            $toDate = Carbon::createFromFormat('Y-m-d', $to)?->endOfDay();
        } catch (\Throwable) {
            return 'Date non valide. Usa il formato AAAA-MM-GG.';
        }

        if ($fromDate === null || $toDate === null) {
            return 'Date non valide. Usa il formato AAAA-MM-GG.';
        }

        $start = $this->snapshotNear($fromDate);
        $end = $this->snapshotNear($toDate);

        if ($start === null || $end === null) {
            return 'Non ci sono snapshot registrati per il periodo richiesto.';
        }

        $delta = (float) $end->total_value - (float) $start->total_value;
        $pct = (float) $start->total_value > 0.0
            ? $delta / (float) $start->total_value * 100
            : null;

        $lines = [
            'Patrimonio al '.$start->date->format('Y-m-d').': '.$this->eur((float) $start->total_value),
            'Patrimonio al '.$end->date->format('Y-m-d').': '.$this->eur((float) $end->total_value),
            'Variazione: '.$this->signedEur($delta).($pct !== null ? ' ('.$this->signedPct($pct).')' : ''),
        ];

        return implode("\n", $lines);
    }

    private function snapshotNear(Carbon $date): ?Snapshot
    {
        return Snapshot::query()
            ->where('date', '<=', $date)
            ->orderByDesc('date')
            ->first()
            ?? Snapshot::query()->orderBy('date')->first();
    }

    private function eur(float $value): string
    {
        return number_format($value, 2, ',', '.').'€';
    }

    private function signedEur(float $value): string
    {
        $sign = $value >= 0.0 ? '+' : '−';
        $word = $value >= 0.0 ? 'guadagno' : 'perdita';

        return $sign.number_format(abs($value), 2, ',', '.').'€ ('.$word.')';
    }

    private function signedPct(float $value): string
    {
        return ($value >= 0.0 ? '+' : '−').number_format(abs($value), 2, ',', '.').'%';
    }

    private function num(float $value, int $decimals): string
    {
        return rtrim(rtrim(number_format($value, $decimals, ',', '.'), '0'), ',');
    }
}
