<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;

class RenderAdvisorContext extends Action
{
    /**
     * Turn the structured advisor context into an annotated Italian briefing.
     *
     * Local models reason far better over natural-language labels than over a
     * raw JSON payload with `low_confidence: true` / `null` fields, which they
     * misread (e.g. taking a noisy goal-projection figure as "you're losing
     * money"). So here we lead with the TRUE return, spell out which figures
     * are unreliable, and omit the misleading ones — the model never sees a
     * number it would draw the wrong conclusion from.
     *
     * @param  array<string, mixed>  $context
     */
    public function run(array $context): string
    {
        $lines = [];

        /** @var array<string, mixed> $portfolio */
        $portfolio = is_array($context['portfolio'] ?? null) ? $context['portfolio'] : [];

        if (($portfolio['hasData'] ?? false) !== true) {
            return 'Non ci sono ancora dati di portafoglio sufficienti per un\'analisi.';
        }

        $lines[] = $this->returnsSection($context['positionReturns'] ?? null);
        $lines[] = $this->allocationSection($portfolio);
        $lines[] = $this->bufferSection($context['emergencyFund'] ?? null);
        $lines[] = $this->liquiditySection($portfolio);
        $lines[] = $this->volatilitySection($portfolio);
        $lines[] = $this->costsSection($context['costs'] ?? null);
        $lines[] = $this->contributionSection($context['contribution'] ?? null);
        $lines[] = $this->objectiveSection($context['goal'] ?? null);
        $lines[] = $this->goalSection($portfolio);
        $lines[] = $this->profileSection($context['investorProfile'] ?? null);

        return implode("\n\n", array_filter($lines));
    }

    private function returnsSection(mixed $returns): string
    {
        if (! is_array($returns) || ! is_array($returns['aggregate'] ?? null)) {
            return '';
        }

        /** @var array<string, mixed> $agg */
        $agg = $returns['aggregate'];
        $pct = $agg['unrealised_pnl_pct'];

        $out = "RENDIMENTO REALE DEGLI INVESTIMENTI (il dato più affidabile, al netto di quanto versato):\n";
        $out .= '- Versato: '.$this->eur($agg['cost_basis']).', valore attuale: '.$this->eur($agg['current_value']).'.';

        if (is_numeric($pct)) {
            $out .= "\n- Guadagno/perdita non realizzato: ".$this->eur($agg['unrealised_pnl']).' ('.$this->pct($pct).').';
        }
        if (is_numeric($agg['realised_pnl'] ?? null) && (float) $agg['realised_pnl'] !== 0.0) {
            $out .= "\n- Già realizzato da vendite: ".$this->eur($agg['realised_pnl']).'.';
        }

        if (is_array($returns['positions'] ?? null)) {
            foreach ($returns['positions'] as $p) {
                if (is_array($p) && is_numeric($p['unrealised_pnl_pct'] ?? null)) {
                    $out .= "\n- ".$this->s($p['name'] ?? '').': '.$this->pct($p['unrealised_pnl_pct']).' (valore '.$this->eur($p['current_value'] ?? null).').';
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $portfolio
     */
    private function allocationSection(array $portfolio): string
    {
        if (! is_array($portfolio['allocation'] ?? null)) {
            return '';
        }

        $out = 'ALLOCAZIONE ATTUALE:';
        foreach ($portfolio['allocation'] as $slice) {
            if (is_array($slice)) {
                $out .= "\n- ".$this->s($slice['name'] ?? '').': '.$this->pct($slice['share_pct'] ?? null).' ('.$this->eur($slice['value'] ?? null).').';
            }
        }

        if (is_array($portfolio['concentration'] ?? null)) {
            /** @var array<string, mixed> $c */
            $c = $portfolio['concentration'];
            $hhi = is_numeric($c['hhi'] ?? null) ? (float) $c['hhi'] : 0.0;
            $level = $hhi >= 5000 ? 'molto concentrato' : ($hhi >= 3000 ? 'concentrazione moderata' : 'ben distribuito');
            $out .= "\nConcentrazione: ".$level.' (voce maggiore: '.$this->s($c['top_category'] ?? '').' al '.$this->pct($c['top_share_pct'] ?? null).').';
        }

        return $out;
    }

    private function bufferSection(mixed $fund): string
    {
        if (! is_array($fund)) {
            return '';
        }

        $buffer = $fund['buffer'] ?? null;
        if (! is_numeric($buffer) || (float) $buffer <= 0.0) {
            return '';
        }

        $out = 'FONDO DI EMERGENZA: '.$this->eur($buffer).' tenuti come cuscinetto (categorie marcate non investibili). '
            .'Questo importo NON è incluso nelle metriche di investimento qui sopra (allocazione, rendimento, obiettivo): è liquidità ferma volutamente fuori dal portafoglio investito.';

        $monthsCovered = $fund['monthsCovered'] ?? null;
        $targetMonths = $fund['targetMonths'] ?? null;

        // Coverage is only computable once expenses have been observed. Without
        // it, report the amount alone (no invented months).
        if (is_numeric($monthsCovered) && is_numeric($targetMonths)) {
            $covered = (float) $monthsCovered;
            $target = (int) $targetMonths;
            $shortfall = $fund['shortfall'] ?? null;
            $monthlyExpense = $fund['monthlyExpense'] ?? null;

            $out .= "\nCopertura: circa ".$this->months($covered).' di spese coperte, su un obiettivo di '.$target.' mesi';
            if (is_numeric($monthlyExpense)) {
                $out .= ' (spesa media osservata '.$this->eur($monthlyExpense).'/mese).';
            } else {
                $out .= '.';
            }

            if ($covered + 0.05 < $target && is_numeric($shortfall) && (float) $shortfall > 0.0) {
                $out .= "\nIl fondo è SOTTO l'obiettivo: mancano circa ".$this->eur($shortfall).'. '
                    .'Segnalalo e suggerisci di dare priorità al completamento del fondo di emergenza prima di aumentare gli investimenti; è la base di sicurezza su cui poggia la capacità di rischio.';
            } else {
                $out .= "\nIl fondo COPRE l'obiettivo: confermalo brevemente come punto di forza e non trattarlo come denaro da investire.";
            }
        } else {
            $out .= ' Tienine conto quando valuti la capacità di rischio, ma non trattarlo come denaro da investire. (La copertura in mesi non è calcolabile finché non ci sono transazioni di spesa osservate.)';
        }

        return $out;
    }

    private function months(float $n): string
    {
        return rtrim(rtrim(number_format($n, 1, ',', '.'), '0'), ',').' mesi';
    }

    /**
     * @param  array<string, mixed>  $portfolio
     */
    private function liquiditySection(array $portfolio): string
    {
        if (! is_array($portfolio['liquidity'] ?? null)) {
            return '';
        }

        /** @var array<string, mixed> $l */
        $l = $portfolio['liquidity'];

        return 'LIQUIDITÀ FERMA: '.$this->eur($l['value']).' ('.$this->pct($l['share_pct']).' del totale).';
    }

    /**
     * @param  array<string, mixed>  $portfolio
     */
    private function volatilitySection(array $portfolio): string
    {
        if (! is_array($portfolio['volatility'] ?? null)) {
            return '';
        }

        /** @var array<string, mixed> $v */
        $v = $portfolio['volatility'];

        if (! is_numeric($v['monthly_stddev_pct'] ?? null)) {
            return 'VOLATILITÀ: non ancora calcolabile (servono più mesi di dati). Non trarne conclusioni.';
        }

        return 'VOLATILITÀ mensile: ±'.$this->pct($v['monthly_stddev_pct'])
            .' (miglior mese '.$this->pct($v['best_month_pct']).', peggiore '.$this->pct($v['worst_month_pct']).').';
    }

    private function costsSection(mixed $costs): string
    {
        if (! is_array($costs)) {
            return 'COSTI DI GESTIONE: nessun TER inserito sugli asset. Non puoi valutare il peso dei costi: invita l\'utente a inserire il TER degli strumenti.';
        }

        $out = 'COSTI DI GESTIONE (TER): costo medio ponderato '.$this->pct($costs['weighted_ter_pct'] ?? null)
            .' all\'anno, pari a circa '.$this->eur($costs['annual_cost'] ?? null).'/anno';
        $out .= ' sui '.$this->eur($costs['covered_value'] ?? null).' di asset con TER indicato.';

        return $out.' (Il TER non è inserito su tutti gli asset: il dato copre solo quelli indicati.)';
    }

    private function contributionSection(mixed $contribution): string
    {
        if (! is_array($contribution)) {
            return '';
        }

        $months = is_numeric($contribution['months'] ?? null) ? (int) $contribution['months'] : 0;

        return 'CONTRIBUTO MENSILE (PAC): in media '.$this->eur($contribution['monthly_avg'] ?? null)
            .' al mese versati tramite piano di accumulo (media degli ultimi '.$months.' mesi, calcolata dalle transazioni).';
    }

    /**
     * The objective the user has ALREADY defined in the Goal section — always
     * shown from the structured Goal data (name, target, year, target
     * allocation, how much is left). The interview must never re-ask for a
     * target the user has set; it confirms or refines what's here.
     */
    private function objectiveSection(mixed $goal): string
    {
        if (! is_array($goal)) {
            return '';
        }

        $out = 'OBIETTIVO ATTUALE (già definito dall\'utente — NON richiedere di nuovo questi dati, al massimo aiutalo a confermarli o modificarli):';
        $out .= "\n- Nome: ".$this->s($goal['name'] ?? '');

        if (is_string($goal['description'] ?? null) && $goal['description'] !== '') {
            $out .= "\n- Descrizione: ".$this->userText($goal['description']);
        }

        if (is_numeric($goal['target_value'] ?? null)) {
            $year = is_string($goal['target_year'] ?? null) ? ' entro il '.$goal['target_year'] : '';
            $out .= "\n- Target: ".$this->eur($goal['target_value']).$year.'.';
        }

        if (is_numeric($goal['current_value'] ?? null)) {
            $left = is_numeric($goal['remaining'] ?? null) ? ' (mancano '.$this->eur($goal['remaining']).')' : '';
            $out .= "\n- Valore attuale: ".$this->eur($goal['current_value']).$left.'.';
        }

        if (is_string($goal['target_allocation'] ?? null) && $goal['target_allocation'] !== '') {
            $out .= "\n- Allocazione target: ".$goal['target_allocation'].'.';
        }

        return $out.$this->milestonesLines($goal['milestones'] ?? null);
    }

    private function milestonesLines(mixed $milestones): string
    {
        if (! is_array($milestones) || $milestones === []) {
            return "\n- Milestone: nessuna tappa intermedia configurata.";
        }

        $out = "\n- Milestone già configurate (tappe intermedie verso l'obiettivo):";
        foreach ($milestones as $m) {
            if (! is_array($m)) {
                continue;
            }
            $label = is_string($m['label'] ?? null) && $m['label'] !== '' ? ' — '.$this->userText($m['label']) : '';
            $year = is_string($m['year'] ?? null) ? ' entro il '.$m['year'] : '';
            $out .= "\n  · ".$this->eur($m['value'] ?? null).$year.$label;
            if (is_string($m['allocation'] ?? null) && $m['allocation'] !== '') {
                $out .= ' (allocazione target: '.$m['allocation'].')';
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $portfolio
     */
    private function goalSection(array $portfolio): string
    {
        if (! is_array($portfolio['goalEta'] ?? null)) {
            return '';
        }

        /** @var array<string, mixed> $g */
        $g = $portfolio['goalEta'];

        if (($g['reached'] ?? false) === true) {
            return 'PROIEZIONE OBIETTIVO: già raggiunto.';
        }

        // The projection is the noisy part. When it's low-confidence, say so in
        // words and DO NOT surface the misleading monthly figure at all.
        if (($g['low_confidence'] ?? false) === true) {
            return 'PROIEZIONE OBIETTIVO: non affidabile — troppo pochi mesi di dati per stimare quando sarà raggiunto. NON interpretare questo come una perdita: il rendimento reale è quello indicato sopra.';
        }

        if (is_numeric($g['months_to_goal'] ?? null)) {
            $track = ($g['on_track'] ?? null) === true ? 'in linea con la data obiettivo' : 'oltre la data obiettivo';

            return 'PROIEZIONE OBIETTIVO: al ritmo attuale circa '.$g['months_to_goal'].' mesi ('.$track.').';
        }

        return '';
    }

    private function profileSection(mixed $profile): string
    {
        if (! is_array($profile)) {
            return "PROFILO INVESTITORE: non compilato. Non assumere orizzonte o tolleranza al rischio: invita l'utente a definirlo per un'analisi più mirata.";
        }

        $out = 'PROFILO INVESTITORE (già noto — NON richiedere questi dati, al massimo chiedi conferma se rilevante):';

        $name = $profile['name'] ?? null;
        if (is_string($name) && $name !== '') {
            $out .= "\n- Nome: ".$name.' (rivolgiti a lui/lei per nome, con naturalezza).';
        }

        if (is_numeric($profile['age'] ?? null)) {
            $out .= "\n- Età: ".(int) $profile['age'].' anni.';
        }

        $out .= "\n- Orizzonte: ".$this->labelOr($profile['horizon'] ?? null, ['short' => 'breve', 'medium' => 'medio', 'long' => 'lungo']);
        $out .= "\n- Tolleranza al rischio: ".$this->labelOr($profile['risk_tolerance'] ?? null, ['low' => 'bassa', 'medium' => 'media', 'high' => 'alta']);

        if (is_numeric($profile['net_monthly_income'] ?? null)) {
            $out .= "\n- Reddito netto mensile: ".$this->eur($profile['net_monthly_income']).' al mese, osservato dalle transazioni bancarie (media degli stipendi), non dichiarato a mano.';
        }

        $notes = $profile['notes'] ?? null;
        if (is_string($notes) && $notes !== '') {
            $out .= "\n- Note sul profilo di rischio: ".$notes;
        }

        $memory = $profile['memory'] ?? null;
        if (is_string($memory) && $memory !== '') {
            $out .= "\n- Cose da ricordare su di lui/lei: ".$memory;
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $map
     */
    private function labelOr(mixed $value, array $map): string
    {
        return is_string($value) && isset($map[$value]) ? $map[$value] : 'non indicato';
    }

    private function s(mixed $value): string
    {
        return is_string($value) ? $this->userText($value) : '';
    }

    /**
     * Render a user-controlled string (asset/category/goal name, profile text)
     * as inert data inside the briefing. Newlines and control characters are
     * collapsed so a crafted value can't open a fake new line/section, and the
     * value is wrapped in guillemets as an explicit "this is data" delimiter
     * the system prompt is told to respect. Defense against prompt injection;
     * matters most once input is multi-user or the provider becomes cloud.
     */
    private function userText(string $value): string
    {
        $clean = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value) ?? $value;
        $clean = trim($clean);

        return '«'.$clean.'»';
    }

    private function eur(mixed $value): string
    {
        return is_numeric($value) ? number_format((float) $value, 0, ',', '.').'€' : 'n/d';
    }

    private function pct(mixed $value): string
    {
        if (! is_numeric($value)) {
            return 'n/d';
        }

        $v = (float) $value;

        return ($v > 0 ? '+' : '').rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.').'%';
    }
}
