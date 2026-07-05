<?php

declare(strict_types=1);

namespace App\Advisor\Tools;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Models\Category;
use App\Models\Goal;
use App\Models\GoalCategoryAllocation;
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
        private readonly AdvisorToolActivityReporter $activity,
        private readonly AdvisorWidgetCollector $widgets,
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
            $this->allocationVsTarget(),
            $this->listPositions(),
            $this->simulateGoal(),
            $this->proposeProfileUpdate(),
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
            ->for('Dettagli di una singola posizione o categoria di investimento, per nome (anche parziale). Per gli strumenti gestiti da transazioni (ETF, crypto importate) dà quote, prezzo medio di carico, valore, guadagno/perdita e rendimento reale; per le altre voci del portafoglio (es. Bitcoin, Oro, Liquidità) dà almeno valore attuale e peso. Usalo quando la domanda riguarda un singolo strumento o categoria.')
            ->withStringParameter('name', 'Nome (anche parziale) della posizione o categoria, es. Bitcoin, ACWI, Oro')
            ->using(function (string $name): string {
                $this->activity->report('Sto controllando la tua posizione '.trim($name).'…');
                $context = $this->buildContext->run();
                $needle = mb_strtolower(trim($name));

                /** @var array{positions: list<array{id: int, name: string, shares: float, average_cost: float, cost_basis: float, current_value: float|null, unrealised_pnl: float|null, unrealised_pnl_pct: float|null, realised_pnl: float}>}|null $returns */
                $returns = $context['positionReturns'] ?? null;

                if ($returns !== null) {
                    foreach ($returns['positions'] as $position) {
                        if (str_contains(mb_strtolower($position['name']), $needle)) {
                            $this->emitPositionWidget($position);

                            return $this->describePosition($position);
                        }
                    }
                }

                // Not a transaction-managed position: fall back to the portfolio
                // allocation, which covers categories like Bitcoin/Oro/Liquidità
                // that have a current value and weight but no cost-basis detail.
                $portfolio = $this->portfolio($context);
                if ($portfolio !== null) {
                    foreach ($portfolio['allocation'] as $slice) {
                        if (str_contains(mb_strtolower($slice['name']), $needle)) {
                            $this->emitCategoryWidget($slice);

                            return "Posizione: {$slice['name']}\n"
                                .'Valore attuale: '.$this->eur($slice['value']).' ('.$this->num($slice['share_pct'], 1).'% del portafoglio)'."\n"
                                .'Questa voce non è gestita da transazioni: non ho il prezzo medio di carico né il rendimento reale, solo il valore attuale.';
                        }
                    }
                }

                $names = [];
                foreach (($returns['positions'] ?? []) as $p) {
                    $names[] = $p['name'];
                }
                foreach (($portfolio['allocation'] ?? []) as $slice) {
                    $names[] = $slice['name'];
                }
                $available = $names === [] ? 'nessuna' : implode(', ', array_unique($names));

                return "Nessuna posizione trovata per «{$name}». Voci disponibili: {$available}.";
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
                $this->activity->report('Sto riepilogando il tuo portafoglio…');
                $portfolio = $this->portfolio();

                if ($portfolio === null) {
                    return 'Non ci sono ancora dati di portafoglio sufficienti.';
                }

                $this->emitAllocationWidget($portfolio);

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
                $this->activity->report('Sto simulando un versamento mensile di '.$this->eur((float) $monthly_amount).'…');
                $context = $this->buildContext->run();
                $portfolio = $this->portfolio($context);

                if ($portfolio === null) {
                    return 'Non ci sono ancora dati sufficienti per simulare il PAC.';
                }

                $expectedReturn = $this->expectedAnnualReturn($context);
                $this->emitPacWidget($portfolio, $expectedReturn, (float) $monthly_amount);

                return $this->describePacSimulation($portfolio, $expectedReturn, (float) $monthly_amount);
            });
    }

    /**
     * Net-worth change between two dates, read from the monthly snapshots. Answers
     * "how did I do compared to N months ago?".
     */
    private function netWorthBetween(): PrismTool
    {
        // Give the model concrete date anchors: without today's date and the
        // tracked range it turns "due mesi fa" into a guess and often sends the
        // same date for from and to (a zero-length period that draws no line).
        // make() runs on every request and must not assume a migrated DB, so the
        // range lookup degrades to no range if the snapshots table isn't there.
        $today = Carbon::now()->format('Y-m-d');
        $range = $this->snapshotRangeHint();

        return Tool::as('net_worth_between')
            ->for("Confronta il patrimonio netto tra due date, restituendo i valori e la variazione, e disegna l'andamento nel periodo. Usalo per domande su come è andato il patrimonio in un periodo (es. rispetto a 3 mesi fa). Oggi è {$today}.{$range}")
            ->withStringParameter('from', "Data iniziale in formato AAAA-MM-GG. Deve essere una data PASSATA (es. per «due mesi fa» sottrai 2 mesi da oggi, {$today}). DEVE essere diversa e precedente a «to», altrimenti il periodo è vuoto.")
            ->withStringParameter('to', "Data finale in formato AAAA-MM-GG. Di solito oggi ({$today}) per «fino ad adesso».")
            ->using(function (string $from, string $to): string {
                $this->activity->report('Sto confrontando il patrimonio nel periodo indicato…');

                return $this->describeNetWorthBetween($from, $to);
            });
    }

    /**
     * Current allocation against the Goal's target allocation, per category.
     * Answers "am I in line with my plan?" — a comparison the user's structured
     * GoalCategoryAllocation makes possible.
     */
    private function allocationVsTarget(): PrismTool
    {
        return Tool::as('allocation_vs_target')
            ->for('Confronta l\'allocazione ATTUALE del portafoglio con l\'allocazione OBIETTIVO impostata (target per categoria). Usalo per domande su quanto il portafoglio è in linea col piano/strategia, o dove ribilanciare.')
            ->using(function (): string {
                $this->activity->report('Sto confrontando la tua allocazione con l\'obiettivo…');

                return $this->describeAllocationVsTarget();
            });
    }

    /**
     * All positions with cost/value/return in one shot. Answers "how are my
     * investments doing?" across the whole book — a table the eye reads faster
     * than the model narrates, and where per-position signs stay correct.
     */
    private function listPositions(): PrismTool
    {
        return Tool::as('list_positions')
            ->for('Elenca TUTTE le posizioni gestite da transazioni con quote, prezzo medio di carico, valore attuale e guadagno/perdita. Usalo per domande sull\'andamento complessivo degli investimenti o per confrontare le posizioni tra loro.')
            ->using(function (): string {
                $this->activity->report('Sto raccogliendo i rendimenti di tutte le posizioni…');

                return $this->describePositionsTable();
            });
    }

    /**
     * What-if on the goal itself: given a target amount and date, the monthly
     * contribution needed to reach it. The inverse of simulate_pac (which fixes
     * the PAC and finds the time); here the time is fixed and we solve for PAC.
     */
    private function simulateGoal(): PrismTool
    {
        $today = Carbon::now()->format('Y-m-d');

        return Tool::as('simulate_goal')
            ->for("Simula un obiettivo: dato un importo target e una data, calcola il versamento mensile (PAC) necessario per raggiungerlo. Usalo quando l'utente ragiona su un obiettivo diverso o vuole capire quanto versare. Oggi è {$today}.")
            ->withNumberParameter('target_value', 'Importo obiettivo in euro, es. 500000')
            ->withStringParameter('target_date', "Data obiettivo in formato AAAA-MM-GG, futura (dopo {$today})")
            ->using(function (int|float $target_value, string $target_date): string {
                $this->activity->report('Sto simulando l\'obiettivo…');

                return $this->describeGoalSimulation((float) $target_value, $target_date);
            });
    }

    /**
     * Propose an investor-profile change. Does NOT write: it emits a
     * profile_proposal widget the user confirms with a click (the write happens
     * through the existing /advisor/profile endpoint and its validation). This
     * keeps the "AI proposes, the user applies" boundary — the model prefills,
     * the user still pulls the trigger. Every field is optional so the model can
     * propose only what the conversation actually settled.
     */
    private function proposeProfileUpdate(): PrismTool
    {
        return Tool::as('propose_profile_update')
            ->for('Proponi una modifica al profilo investitore dell\'utente (orizzonte, tolleranza al rischio, obiettivo, allocazione target, note) quando la conversazione ha chiarito uno o più di questi elementi. NON salva: mostra all\'utente una proposta che lui conferma con un click. Compila SOLO i campi realmente emersi dalla conversazione; lascia vuoti gli altri. Dopo averlo chiamato, spiega a parole cosa hai proposto e invita l\'utente a confermare.')
            ->withStringParameter('horizon', 'Orizzonte temporale: uno tra "short" (breve, <3 anni), "medium" (medio, 3-10 anni), "long" (lungo, 10+ anni). Ometti se non emerso.', required: false)
            ->withStringParameter('risk_tolerance', 'Tolleranza al rischio: uno tra "low" (bassa), "medium" (media), "high" (alta). Ometti se non emerso.', required: false)
            ->withStringParameter('objective', 'Obiettivo di investimento, testo libero (max 500 caratteri). Ometti se non emerso.', required: false)
            ->withStringParameter('target_allocation', 'Allocazione target desiderata, testo libero (es. "60% azioni, 30% obbligazioni, 10% liquidità", max 500). Ometti se non emerso.', required: false)
            ->withStringParameter('notes', 'Sintesi del ragionamento sul profilo di rischio (max 1000 caratteri): capacità di rischio (orizzonte, stabilità del reddito, cuscinetto di liquidità), tolleranza emotiva (reazione a un forte calo), e contesto rilevante. Compilalo quando hai condotto un\'intervista di profilazione, così il "perché" resta salvato.', required: false)
            ->using(function (?string $horizon = null, ?string $risk_tolerance = null, ?string $objective = null, ?string $target_allocation = null, ?string $notes = null): string {
                $this->activity->report('Sto preparando una proposta per il tuo profilo…');

                return $this->describeProfileProposal($horizon, $risk_tolerance, $objective, $target_allocation, $notes);
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

    /**
     * Emit the interactive PAC-simulator widget. The frontend re-runs the same
     * compound projection as describePacSimulation() (mirrored in lib/pacMath.ts,
     * tested on both sides), so the user can drag the monthly amount and expected
     * return and watch the ETA update without another model round-trip. Skipped
     * when there's no reachable goal, so the widget only appears when the text
     * has a meaningful estimate to accompany.
     *
     * @param  array{monthsTracked: int, totalNetWorth: float, allocation: list<array{name: string, value: float, share_pct: float}>, concentration: array{hhi: float, top_category: string, top_share_pct: float}, liquidity: array{value: float, share_pct: float}, goalEta: array<string, mixed>|null}  $portfolio
     * @param  array{rate: float, source: string}  $expectedReturn
     */
    private function emitPacWidget(array $portfolio, array $expectedReturn, float $monthlyAmount): void
    {
        $goalEta = $portfolio['goalEta'];

        if ($goalEta === null || ! isset($goalEta['target_value']) || ($goalEta['reached'] ?? false) === true) {
            return;
        }

        $this->widgets->add('pac_simulator', [
            'current_net_worth' => $portfolio['totalNetWorth'],
            'target_value' => is_numeric($goalEta['target_value']) ? (float) $goalEta['target_value'] : 0.0,
            'monthly_amount' => $monthlyAmount,
            'annual_return' => $expectedReturn['rate'],
            'annual_return_source' => $expectedReturn['source'],
            'low_confidence' => ($goalEta['low_confidence'] ?? false) === true,
        ]);
    }

    /**
     * Emit the static position-detail card for a transaction-managed position.
     * The frontend renders shares, average cost, value and a coloured P&L; all
     * figures are the ones ComputePositionReturns already derived.
     *
     * @param  array{id: int, name: string, shares: float, average_cost: float, cost_basis: float, current_value: float|null, unrealised_pnl: float|null, unrealised_pnl_pct: float|null, realised_pnl: float}  $p
     */
    private function emitPositionWidget(array $p): void
    {
        $this->widgets->add('position_card', [
            'name' => $p['name'],
            'managed' => true,
            'shares' => $p['shares'],
            'average_cost' => $p['average_cost'],
            'cost_basis' => $p['cost_basis'],
            'current_value' => $p['current_value'],
            'unrealised_pnl' => $p['unrealised_pnl'],
            'unrealised_pnl_pct' => $p['unrealised_pnl_pct'],
            'realised_pnl' => $p['realised_pnl'],
        ]);
    }

    /**
     * Emit the position card for a non-transaction-managed category (Bitcoin,
     * Oro, Liquidità): only the current value and portfolio weight are known.
     *
     * @param  array{name: string, value: float, share_pct: float}  $slice
     */
    private function emitCategoryWidget(array $slice): void
    {
        $this->widgets->add('position_card', [
            'name' => $slice['name'],
            'managed' => false,
            'current_value' => $slice['value'],
            'share_pct' => $slice['share_pct'],
        ]);
    }

    /**
     * Emit the allocation donut. The metrics allocation carries name/value/%
     * but not the category colour, so we look colours up by name here (the
     * dashboard donut uses the same category colours) and default any unmatched
     * slice to a neutral grey.
     *
     * @param  array{allocation: list<array{name: string, value: float, share_pct: float}>, concentration: array{top_category: string, top_share_pct: float}}  $portfolio
     */
    private function emitAllocationWidget(array $portfolio): void
    {
        /** @var array<string, string> $colours */
        $colours = Category::query()->pluck('color', 'name')->all();

        $slices = [];
        foreach ($portfolio['allocation'] as $slice) {
            $slices[] = [
                'name' => $slice['name'],
                'value' => $slice['value'],
                'share_pct' => $slice['share_pct'],
                'color' => $colours[$slice['name']] ?? '#94a3b8',
            ];
        }

        $this->widgets->add('allocation_donut', [
            'slices' => $slices,
            'top_category' => $portfolio['concentration']['top_category'],
            'top_share_pct' => $portfolio['concentration']['top_share_pct'],
        ]);
    }

    /**
     * Emit the net-worth line for the requested window: the full run of monthly
     * snapshots between the two resolved endpoints, so the frontend draws a real
     * curve (not just the two ends) and highlights the period.
     */
    private function emitNetWorthWidget(Snapshot $start, Snapshot $end): void
    {
        $points = Snapshot::query()
            ->whereBetween('date', [$start->date->format('Y-m-d'), $end->date->format('Y-m-d')])
            ->orderBy('date')
            ->get(['date', 'total_value'])
            ->map(fn (Snapshot $s): array => [
                'date' => $s->date->format('Y-m-d'),
                'total_value' => round((float) $s->total_value, 2),
            ])
            ->all();

        if (count($points) < 2) {
            return;
        }

        $this->widgets->add('networth_line', [
            'points' => $points,
            'from' => $start->date->format('Y-m-d'),
            'to' => $end->date->format('Y-m-d'),
        ]);
    }

    /**
     * Build the current-vs-target allocation comparison, emit its widget and
     * return the annotated text. Target percentages come from the Goal's
     * category allocations; current percentages from the metrics.
     */
    private function describeAllocationVsTarget(): string
    {
        $portfolio = $this->portfolio();

        if ($portfolio === null) {
            return 'Non ci sono ancora dati di portafoglio sufficienti.';
        }

        $goal = Goal::query()->with('categoryAllocations.category')->first();
        $targets = [];
        if ($goal instanceof Goal) {
            foreach ($goal->categoryAllocations as $a) {
                $label = $a->category_id !== null
                    ? ($a->category->name ?? 'Sconosciuta')
                    : ($a->macro_category ?? 'Sconosciuta');
                $targets[$label] = round((float) $a->percentage, 2);
            }
        }

        if ($targets === []) {
            return 'Non hai impostato un\'allocazione obiettivo nella sezione Obiettivo, quindi non posso confrontare con un target.';
        }

        $current = [];
        foreach ($portfolio['allocation'] as $slice) {
            $current[$slice['name']] = $slice['share_pct'];
        }

        // Union of category names across current and target, so a category that
        // is only in one of the two still shows (as 0 on the missing side).
        $names = array_values(array_unique([...array_keys($current), ...array_keys($targets)]));

        $rows = [];
        foreach ($names as $name) {
            $rows[] = [
                'name' => $name,
                'current_pct' => $current[$name] ?? 0.0,
                'target_pct' => $targets[$name] ?? 0.0,
            ];
        }

        $this->widgets->add('allocation_vs_target', ['rows' => $rows]);

        $lines = ['Allocazione attuale vs obiettivo:'];
        foreach ($rows as $row) {
            $delta = $row['current_pct'] - $row['target_pct'];
            $lines[] = "  - {$row['name']}: attuale ".$this->num($row['current_pct'], 1).'%, obiettivo '
                .$this->num($row['target_pct'], 1).'% ('.$this->signedPct($delta).')';
        }

        return implode("\n", $lines);
    }

    /**
     * Emit and describe the full positions table (transaction-managed only).
     */
    private function describePositionsTable(): string
    {
        $context = $this->buildContext->run();

        /** @var array{positions: list<array{id: int, name: string, shares: float, average_cost: float, cost_basis: float, current_value: float|null, unrealised_pnl: float|null, unrealised_pnl_pct: float|null, realised_pnl: float}>}|null $returns */
        $returns = $context['positionReturns'] ?? null;

        if ($returns === null || $returns['positions'] === []) {
            return 'Non hai posizioni gestite da transazioni (nessun rendimento reale da confrontare).';
        }

        $rows = [];
        foreach ($returns['positions'] as $p) {
            $rows[] = [
                'name' => $p['name'],
                'shares' => $p['shares'],
                'average_cost' => $p['average_cost'],
                'current_value' => $p['current_value'],
                'unrealised_pnl' => $p['unrealised_pnl'],
                'unrealised_pnl_pct' => $p['unrealised_pnl_pct'],
            ];
        }

        $this->widgets->add('positions_table', ['rows' => $rows]);

        $lines = ['Rendimenti per posizione:'];
        foreach ($returns['positions'] as $p) {
            $pnl = $p['unrealised_pnl'] !== null
                ? $this->signedEur($p['unrealised_pnl']).($p['unrealised_pnl_pct'] !== null ? ' ('.$this->signedPct($p['unrealised_pnl_pct']).')' : '')
                : 'valore non disponibile';
            $lines[] = "  - {$p['name']}: ".$pnl;
        }

        return implode("\n", $lines);
    }

    /**
     * Simulate a goal: given a target amount and date, the monthly PAC needed.
     * Solves the compound annuity for the payment (the inverse of simulate_pac),
     * using the same expected-return assumption. Emits an interactive widget so
     * the user can drag target and date and watch the required PAC update.
     */
    private function describeGoalSimulation(float $targetValue, string $targetDate): string
    {
        $context = $this->buildContext->run();
        $portfolio = $this->portfolio($context);

        if ($portfolio === null) {
            return 'Non ci sono ancora dati sufficienti per simulare l\'obiettivo.';
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $targetDate)?->endOfDay();
        } catch (\Throwable) {
            return 'Data non valida. Usa il formato AAAA-MM-GG.';
        }

        if ($date === null || $date->isPast()) {
            return 'La data obiettivo deve essere futura.';
        }

        $months = (int) ceil(Carbon::now()->diffInMonths($date, absolute: true));
        if ($months < 1) {
            return 'La data obiettivo è troppo vicina per un piano mensile.';
        }

        $expectedReturn = $this->expectedAnnualReturn($context);
        $current = $portfolio['totalNetWorth'];
        $required = $this->requiredMonthlyContribution($current, $targetValue, $months, $expectedReturn['rate']);

        $this->widgets->add('goal_simulator', [
            'current_net_worth' => $current,
            'target_value' => $targetValue,
            'target_date' => $date->format('Y-m-d'),
            'months' => $months,
            'annual_return' => $expectedReturn['rate'],
            'annual_return_source' => $expectedReturn['source'],
            'required_monthly' => $required,
        ]);

        $lines = [
            'Simulazione obiettivo '.$this->eur($targetValue).' entro il '.$date->format('Y-m-d').' ('.$months.' mesi):',
            'Patrimonio attuale '.$this->eur($current).'.',
            'Ipotesi di rendimento annuo: '.$this->num($expectedReturn['rate'] * 100, 1).'% ('.$expectedReturn['source'].') — assunzione di pianificazione, NON una previsione.',
        ];

        if ($required <= 0.0) {
            $lines[] = 'Con il solo patrimonio attuale e questo rendimento raggiungi già l\'obiettivo entro la data: nessun versamento aggiuntivo necessario.';
        } else {
            $lines[] = 'Versamento mensile necessario: circa '.$this->eur($required).'.';
        }

        return implode("\n", $lines);
    }

    /**
     * The monthly PAC needed to grow $current to $target over $months at a
     * given annual rate. Closed-form annuity: PMT = (FV - PV·(1+i)^n) / s, where
     * s is the future-value factor of a €1/month annuity. Returns 0 when the
     * current balance alone already reaches the target.
     */
    private function requiredMonthlyContribution(float $current, float $target, int $months, float $annualReturn): float
    {
        $i = (1.0 + $annualReturn) ** (1.0 / 12.0) - 1.0;
        $growth = (1.0 + $i) ** $months;
        $futureOfCurrent = $current * $growth;

        if ($futureOfCurrent >= $target) {
            return 0.0;
        }

        // Future-value factor of a €1/month ordinary annuity; guard i≈0.
        $annuityFactor = $i > 1e-9 ? ($growth - 1.0) / $i : $months;

        return ($target - $futureOfCurrent) / $annuityFactor;
    }

    /**
     * Validate the proposed profile fields, emit the confirmation widget and
     * return text describing what was proposed. Enum fields are validated
     * against the same values as StoreInvestorProfileRequest; an out-of-range
     * enum is dropped (not proposed) so the model can't push an invalid value.
     * Returns without a widget when nothing valid was proposed.
     */
    private function describeProfileProposal(?string $horizon, ?string $riskTolerance, ?string $objective, ?string $targetAllocation, ?string $notes = null): string
    {
        $horizon = in_array($horizon, ['short', 'medium', 'long'], true) ? $horizon : null;
        $riskTolerance = in_array($riskTolerance, ['low', 'medium', 'high'], true) ? $riskTolerance : null;
        $objective = $objective !== null && trim($objective) !== '' ? mb_substr(trim($objective), 0, 500) : null;
        $targetAllocation = $targetAllocation !== null && trim($targetAllocation) !== '' ? mb_substr(trim($targetAllocation), 0, 500) : null;
        $notes = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 1000) : null;

        $proposed = array_filter([
            'horizon' => $horizon,
            'risk_tolerance' => $riskTolerance,
            'objective' => $objective,
            'target_allocation' => $targetAllocation,
            'notes' => $notes,
        ], fn ($v): bool => $v !== null);

        if ($proposed === []) {
            return 'Non ho abbastanza elementi per proporre una modifica al profilo. Chiedi all\'utente orizzonte, tolleranza al rischio e obiettivo.';
        }

        // Deterministic consent gate: the advisor must not propose on its own
        // initiative. Unless the user just agreed (ContinueChat sets this), emit
        // no widget and steer the model to ask first. This holds regardless of
        // how insistently the model tries to propose.
        if (! $this->widgets->isProfileProposalAllowed()) {
            return 'NON proporre ancora. Non mostrare nessuna card di proposta. Prima CHIEDI esplicitamente all\'utente se vuole che aggiorni il suo profilo con queste conclusioni, oppure se preferisce continuare l\'analisi. Attendi il suo consenso.';
        }

        $this->widgets->add('profile_proposal', $proposed);

        $horizonLabels = ['short' => 'breve', 'medium' => 'medio', 'long' => 'lungo'];
        $riskLabels = ['low' => 'bassa', 'medium' => 'media', 'high' => 'alta'];

        $lines = ['Proposta di profilo (da confermare):'];
        if ($horizon !== null) {
            $lines[] = '  - Orizzonte: '.$horizonLabels[$horizon];
        }
        if ($riskTolerance !== null) {
            $lines[] = '  - Tolleranza al rischio: '.$riskLabels[$riskTolerance];
        }
        if ($objective !== null) {
            $lines[] = '  - Obiettivo: '.$objective;
        }
        if ($targetAllocation !== null) {
            $lines[] = '  - Allocazione target: '.$targetAllocation;
        }
        if ($notes !== null) {
            $lines[] = '  - Note: '.$notes;
        }
        $lines[] = 'La proposta è mostrata all\'utente con un pulsante per confermare: non è ancora salvata.';

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

        if (! $start instanceof Snapshot || ! $end instanceof Snapshot) {
            return 'Non ci sono snapshot registrati per il periodo richiesto.';
        }

        $delta = (float) $end->total_value - (float) $start->total_value;
        $pct = (float) $start->total_value > 0.0
            ? $delta / (float) $start->total_value * 100
            : null;

        $this->emitNetWorthWidget($start, $end);

        $lines = [
            'Patrimonio al '.$start->date->format('Y-m-d').': '.$this->eur((float) $start->total_value),
            'Patrimonio al '.$end->date->format('Y-m-d').': '.$this->eur((float) $end->total_value),
            'Variazione: '.$this->signedEur($delta).($pct !== null ? ' ('.$this->signedPct($pct).')' : ''),
        ];

        return implode("\n", $lines);
    }

    /**
     * A " Dati disponibili dal … al …." hint for the net_worth_between tool
     * description, or '' when there are no snapshots. Built at tool-construction
     * time, so it must tolerate a DB without the snapshots table (tests that
     * don't migrate) — any query error degrades to no hint rather than throwing.
     */
    private function snapshotRangeHint(): string
    {
        try {
            $first = Snapshot::query()->orderBy('date')->value('date');
            $last = Snapshot::query()->orderByDesc('date')->value('date');
        } catch (\Throwable) {
            return '';
        }

        return $first instanceof Carbon && $last instanceof Carbon
            ? " Dati disponibili dal {$first->format('Y-m-d')} al {$last->format('Y-m-d')}."
            : '';
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
