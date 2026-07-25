<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;

class ComputeAdvisorFunFacts extends Action
{
    public function __construct(
        private readonly BuildAdvisorContext $buildContext,
    ) {}

    /**
     * Short, true one-liners about the user's own data, shown while a report
     * generates so the wait feels like a tour of the portfolio rather than dead
     * time. Built from the same pre-computed context the advisor reasons over,
     * so nothing here is invented. Facts with no data are simply skipped.
     *
     * @return list<string>
     */
    public function run(): array
    {
        $ctx = $this->buildContext->run();

        /** @var array<string, mixed> $portfolio */
        $portfolio = is_array($ctx['portfolio'] ?? null) ? $ctx['portfolio'] : [];

        if (($portfolio['hasData'] ?? false) !== true) {
            return [];
        }

        $facts = [];

        $facts[] = $this->netWorth($portfolio);
        $facts[] = $this->topCategory($portfolio);
        $facts[] = $this->liquidity($portfolio);
        $facts[] = $this->months($portfolio);
        $facts[] = $this->drift($portfolio);
        $facts[] = $this->trueReturn($ctx['positionReturns'] ?? null);
        $facts[] = $this->bestPosition($ctx['positionReturns'] ?? null);
        $facts[] = $this->contribution($ctx['contribution'] ?? null);
        $facts[] = $this->costs($ctx['costs'] ?? null);

        return array_values(array_filter($facts, fn (?string $f): bool => $f !== null));
    }

    /**
     * @param  array<string, mixed>  $portfolio
     */
    private function netWorth(array $portfolio): ?string
    {
        $total = $portfolio['totalNetWorth'] ?? null;
        if (! is_numeric($total)) {
            return null;
        }

        return 'Oggi il tuo patrimonio tracciato vale '.$this->euro((float) $total).': è la base da cui parte ogni ragionamento sulla tua strategia.';
    }

    /**
     * @param  array<string, mixed>  $portfolio
     */
    private function topCategory(array $portfolio): ?string
    {
        $c = $portfolio['concentration'] ?? null;
        if (! is_array($c) || ! isset($c['top_category'], $c['top_share_pct']) || ! is_string($c['top_category']) || ! is_numeric($c['top_share_pct'])) {
            return null;
        }

        $share = (float) $c['top_share_pct'];
        $read = $share >= 40.0
            ? 'una fetta importante, che concentra buona parte del tuo rischio qui'
            : 'il baricentro attuale del tuo portafoglio';

        return $c['top_category'].' pesa per il '.$this->pct($share).': '.$read.'.';
    }

    /**
     * @param  array<string, mixed>  $portfolio
     */
    private function liquidity(array $portfolio): ?string
    {
        $l = $portfolio['liquidity'] ?? null;
        if (! is_array($l) || ! isset($l['share_pct']) || ! is_numeric($l['share_pct']) || (float) $l['share_pct'] <= 0.0) {
            return null;
        }

        $share = (float) $l['share_pct'];
        $read = $share >= 20.0
            ? 'una riserva ampia, comoda ma che l\'inflazione erode se resta ferma'
            : 'un cuscinetto che ti tiene pronto senza dover disinvestire';

        return 'Il '.$this->pct($share).' del tuo patrimonio è liquido: '.$read.'.';
    }

    /**
     * @param  array<string, mixed>  $portfolio
     */
    private function months(array $portfolio): ?string
    {
        $m = $portfolio['monthsTracked'] ?? null;
        if (! is_int($m) || $m < 1) {
            return null;
        }

        return $m < 4
            ? 'Hai '.$m.($m === 1 ? ' mese' : ' mesi').' di storico: pochi punti, quindi leggo le tendenze con prudenza.'
            : 'Con '.$m.' mesi di storico posso iniziare a leggere le tue tendenze, non solo la foto di oggi.';
    }

    /**
     * @param  array<string, mixed>  $portfolio
     */
    private function drift(array $portfolio): ?string
    {
        $drift = $portfolio['allocationDrift'] ?? null;
        if (! is_array($drift) || $drift === []) {
            return null;
        }

        /** @var array<string, mixed> $top */
        $top = $drift[0];
        $rawDelta = $top['delta_pp'] ?? null;
        if (! is_numeric($rawDelta)) {
            return null;
        }
        $delta = (float) $rawDelta;
        if (abs($delta) < 1.0) {
            return null;
        }

        $name = isset($top['name']) && is_string($top['name']) ? $top['name'] : 'una categoria';
        $verb = $delta > 0 ? 'salita' : 'scesa';
        $read = $delta > 0
            ? 'il tuo portafoglio si sta spostando verso questa asse'
            : 'stai alleggerendo il peso di questa asse';

        return 'Da quando tracci, la quota di '.$name.' è '.$verb.' di '.$this->pp(abs($delta)).': '.$read.'.';
    }

    private function trueReturn(mixed $returns): ?string
    {
        if (! is_array($returns) || ! is_array($returns['aggregate'] ?? null)) {
            return null;
        }

        $pct = $returns['aggregate']['unrealised_pnl_pct'] ?? null;
        if (! is_numeric($pct)) {
            return null;
        }

        $value = (float) $pct;
        $sign = $value >= 0 ? '+' : '';
        $read = $value >= 0
            ? 'quello che i tuoi soldi hanno reso davvero, al netto di quanto ci hai versato'
            : 'la lettura al netto dei versamenti, che racconta il mercato più della cifra grezza';

        return 'Il rendimento vero delle tue posizioni è '.$sign.$this->pct($value).': '.$read.'.';
    }

    private function bestPosition(mixed $returns): ?string
    {
        if (! is_array($returns) || ! is_array($returns['positions'] ?? null) || $returns['positions'] === []) {
            return null;
        }

        /** @var list<array<string, mixed>> $positions */
        $positions = $returns['positions'];
        $bestPct = null;
        $bestName = null;
        foreach ($positions as $p) {
            $pct = $p['unrealised_pnl_pct'] ?? null;
            if (! is_numeric($pct)) {
                continue;
            }
            if ($bestPct === null || (float) $pct > $bestPct) {
                $bestPct = (float) $pct;
                $bestName = isset($p['name']) && is_string($p['name']) ? $p['name'] : 'una posizione';
            }
        }

        if ($bestPct === null) {
            return null;
        }

        $sign = $bestPct >= 0 ? '+' : '';

        return 'A trainare oggi è '.$bestName.' ('.$sign.$this->pct($bestPct).'): la posizione che sta contribuendo di più al tuo rendimento.';
    }

    private function contribution(mixed $contribution): ?string
    {
        if (! is_array($contribution) || ! isset($contribution['monthly_avg']) || ! is_numeric($contribution['monthly_avg'])) {
            return null;
        }

        return 'Col tuo PAC metti da parte in media '.$this->euro((float) $contribution['monthly_avg']).' al mese: è la costanza che, nel tempo, conta più del singolo colpo di mercato.';
    }

    private function costs(mixed $costs): ?string
    {
        if (! is_array($costs) || ! isset($costs['annual_cost']) || ! is_numeric($costs['annual_cost'])) {
            return null;
        }

        return 'I tuoi ETF ti costano circa '.$this->euro((float) $costs['annual_cost']).' l\'anno di gestione: piccolo oggi, ma è un peso che si accumula sul lungo periodo.';
    }

    private function euro(float $value): string
    {
        return number_format($value, 0, ',', '.').'€';
    }

    private function pct(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',').'%';
    }

    private function pp(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1, ',', '.'), '0'), ',').' punti percentuali';
    }
}
