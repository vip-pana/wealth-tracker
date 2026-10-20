<?php

// SPDX-FileCopyrightText: 2026 Vincenzo Panacciulli
// SPDX-License-Identifier: AGPL-3.0-only

declare(strict_types=1);

namespace App\Advisor;

use App\Models\Asset;
use App\Models\Category;
use Illuminate\Support\Facades\Log;

/**
 * Layer 3 of the advisor firewall: a deterministic check on what the model
 * actually produced, run before a reply is persisted and again before it is
 * shown. Layers 1 and 2 instruct the model; this one does not trust it.
 *
 * The rule is co-occurrence: a segment is removed when it carries an operative
 * directive (buy / sell / reduce / "you should", a suitability judgement) AND a
 * reference to a specific financial instrument — either one the user actually
 * holds (matched against their own asset names, tickers, ISINs and categories)
 * or one they do not (an ISIN- or ticker-shaped token). Talking about asset
 * *classes* is untouched: generic advice is allowed under ESMA35-43-3861
 * ¶48-50, and gutting it would gut the product.
 *
 * Over-blocking is the intended direction of error. See
 * docs/ai-advisor-firewall.md for the boundaries of what this can catch.
 */
class AdvisorOutputFilter
{
    /** Replaces a single offending segment, keeping the rest of the reply. */
    public const string REDACTION = '[indicazione operativa rimossa automaticamente]';

    /** Replaces the whole reply when nothing survived the check. */
    public const string REFUSAL = <<<'TEXT'
        Non posso rispondere a questa domanda: indicarti se comprare, vendere o quanto detenere di uno strumento specifico sarebbe consulenza finanziaria, e questo assistente non è autorizzato a fornirla.

        Posso invece mostrarti i dati su cui si basa la decisione e gli aspetti da valutare: chiedimi come stanno i tuoi numeri (rendimento, concentrazione, costi, liquidità, distanza dall'obiettivo) o che cosa conviene verificare prima di decidere.

        Per una raccomandazione personalizzata serve un consulente finanziario abilitato (in Italia, iscritto all'albo OCF).
        TEXT;

    /** Appended once when part of a reply was redacted, so the gap is explained. */
    public const string FOOTER = '_Una parte di questa risposta è stata rimossa automaticamente: conteneva un\'indicazione operativa su uno strumento specifico. Questo assistente è uno strumento informativo e non fornisce consulenza finanziaria; per una raccomandazione personalizzata rivolgiti a un consulente abilitato (albo OCF)._';

    /**
     * An Italian enclitic pronoun, appended to an imperative or an infinitive:
     * "vendine", "riducila", "venderglielo". Without this the directive hides
     * inside the verb — "vendi" is caught, "vendine" is not — and the whole
     * check is one suffix away from being bypassed by ordinary phrasing.
     */
    private const string CLITIC = '(?:(?:me|te|se|ce|ve|glie)?(?:l[oaie]|ne))?';

    /**
     * Directives that are prohibited outright when aimed at an instrument.
     * Stems plus their conjugations, so "vendi", "vendere" and "venderei" all
     * match while nouns that share the stem ("vendita", "liquidità") do not.
     * Infinitives keep their final -e optional, because Italian drops it before
     * a clitic ("vendere" but "venderla").
     *
     * "aprile" is excluded explicitly: it parses as apri + le and is far more
     * often the month.
     */
    private const string HARD_DIRECTIVES = '/\b(?!aprile\b)(?:'
        .'compr(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|acquist(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|vend(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'|ridu(?:ci|rre?|cendo|rrei|rresti|rrebbe|ciamo|cete)'
        .'|alleggeri(?:sci|re?|sco|rei|resti|rebbe|amo)'
        .'|aument(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|increment(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|disinvest(?:i|ire?|endo|irei|iresti|irebbe|iamo)'
        .'|liquid(?:a|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|sostitu(?:isci|ire?|isco|irei|iresti|irebbe|iamo)'
        .'|chiud(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'|apri(?:re?)?'
        .'|ribilanc(?:ia|i|iare?|iando|erei|eresti|erebbe|iamo|iate)'
        .'|spost(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|esci|uscir(?:e)?|entra|entrar(?:e)?'
        .'|raccomand(?:o|iamo|erei|o di)'
        .')'.self::CLITIC.'\b/iu';

    /**
     * Directives that are prohibited only when they introduce an action. The
     * same words introduce the advisor's legitimate output ("dovresti
     * verificare il TER"), which the prompt explicitly asks for, so they are
     * cleared when an epistemic verb follows within a few words.
     */
    private const string SOFT_DIRECTIVES = '/\b(?:'
        .'dovresti|ti conviene|conviene|meglio se|faresti bene a'
        .'|ti consiglio|consiglio di|ti suggerisco|suggerisco di'
        .'|se fossi in te|al posto tuo'
        .')\b(?!(?:\W+\w+){0,3}\W+(?:'
        .'verificar|controllar|chieder|valutar|considerar|capir|saper|confrontar'
        .'|monitorar|legger|informart|documentart|parlar|domandar|osservar|guardar'
        .'|ricordar|tener|aggiornar|rivedere i dati'
        .'))/iu';

    /**
     * Suitability judgements. Prohibited whenever they land on a specific
     * instrument: ESMA ¶65 is explicit that a disclaimer does not undo one.
     */
    private const string SUITABILITY = '/\b(?:'
        .'adatt[oaie] (?:a te|al tuo|alla tua)'
        .'|giust[oaie] per (?:te|il tuo|la tua)'
        .'|(?:in linea|coerente|coerenti) con (?:il tuo|la tua|i tuoi|le tue)'
        .'|su misura per (?:te|il tuo)'
        .'|fa per te'
        .')\b/iu';

    /** A 12-character ISIN: two letters, nine alphanumerics, a check digit. */
    private const string ISIN = '/(?<![\p{L}\p{N}])[A-Z]{2}[A-Z0-9]{9}[0-9](?![\p{L}\p{N}])/u';

    /** An all-caps 3-6 character token — the shape of a ticker. */
    private const string TICKER = '/(?<![\p{L}\p{N}])[A-Z][A-Z0-9]{2,5}(?![\p{L}\p{N}])/u';

    /**
     * All-caps tokens that are never a ticker: domain vocabulary, currencies,
     * and the Italian words the model writes in capitals for emphasis.
     */
    private const array NOT_TICKERS = [
        'ETF', 'PAC', 'TER', 'TFR', 'HHI', 'OCF', 'TUF', 'ESMA', 'ISIN', 'IBAN', 'MIFID',
        'IVA', 'PIL', 'PMI', 'CAGR', 'TAEG', 'TAN', 'TAE', 'ROI', 'PNL', 'MWR', 'TWR', 'IRR',
        'PEPP', 'PIR', 'BCE', 'FED', 'EUR', 'USD', 'GBP', 'CHF', 'JPY', 'YTD', 'API', 'URL',
        'FAQ', 'DCA', 'KID', 'KIID', 'SRRI', 'UCITS', 'OICR', 'SGR', 'SPA', 'SRL', 'UE', 'EU',
        'USA', 'ITA', 'NON', 'SOLO', 'MAI', 'TUTTI', 'TUTTO', 'OGNI', 'NOTA', 'QUALI', 'COSA',
        'QUANTO', 'COME', 'PERCHE', 'SE', 'MA', 'OK', 'NO', 'SI', 'AI',
    ];

    /**
     * Names that denote an asset *class*, not an instrument, however the user
     * spelled their category or asset. Keeping these out of the protected set
     * is what preserves the generic-advice safe harbour: "riduci la liquidità"
     * is allowed, "riduci VWCE" is not.
     */
    private const array GENERIC_CLASS_NAMES = [
        'liquidita', 'liquidity', 'cash', 'contanti', 'conto corrente', 'conti correnti',
        'conto deposito', 'risparmio', 'risparmi', 'azionario', 'azioni', 'equity',
        'obbligazionario', 'obbligazioni', 'bond', 'titoli di stato', 'immobiliare',
        'immobili', 'casa', 'oro', 'materie prime', 'commodities', 'crypto', 'cripto',
        'criptovalute', 'criptovaluta', 'etf', 'fondi', 'fondo', 'fondo pensione',
        'fondi pensione', 'pensione', 'previdenza', 'investimenti', 'investimento',
        'portafoglio', 'emergenza', 'fondo di emergenza', 'fondo emergenza', 'altro',
        'altri', 'varie', 'liquido', 'illiquido',
    ];

    /** Shorter than this, a name matches too much to be usable as a signal. */
    private const int MIN_NAME_LENGTH = 3;

    /** @var non-empty-string|null Memoised across a request; null = nothing to match. */
    private ?string $heldPattern = null;

    private bool $heldPatternBuilt = false;

    /**
     * Screen an assistant reply. Returns it unchanged when clean, with the
     * offending segments replaced when not, or the refusal when nothing
     * survived. Idempotent: re-running it on its own output is a no-op, which
     * is what lets it run both at write time and at read time.
     */
    public function filter(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }

        // Split on sentence and line boundaries, keeping the separators so the
        // reply can be rebuilt with its markdown intact.
        $parts = preg_split('/([.!?;:\n]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return self::REFUSAL;
        }

        $out = [];
        $redacted = 0;
        $kept = 0;
        $previousNamedInstrument = false;

        foreach ($parts as $index => $part) {
            if ($index % 2 === 1 || trim($part) === '') {
                $out[] = $part;

                continue;
            }

            $namesInstrument = $this->mentionsInstrument($part);

            // A directive is judged against the instrument named in its own
            // segment or in the one before it: the model routinely describes a
            // position in one sentence and acts on it in the next ("VWCE è la
            // tua posizione più grande. Ti conviene ridurla.").
            if ($this->isDirective($part) && ($namesInstrument || $previousNamedInstrument)) {
                $out[] = $this->leadingMarkup($part).self::REDACTION;
                $redacted++;
            } else {
                $out[] = $part;
                $kept++;
            }

            $previousNamedInstrument = $namesInstrument;
        }

        if ($redacted === 0) {
            return $text;
        }

        Log::info('Advisor reply filtered', ['segments_redacted' => $redacted, 'segments_kept' => $kept]);

        if ($kept === 0) {
            return self::REFUSAL;
        }

        return rtrim(implode('', $out))."\n\n".self::FOOTER;
    }

    /** Whether a segment carries a directive the boundary clauses prohibit. */
    private function isDirective(string $segment): bool
    {
        return preg_match(self::HARD_DIRECTIVES, $segment) === 1
            || preg_match(self::SOFT_DIRECTIVES, $segment) === 1
            || preg_match(self::SUITABILITY, $segment) === 1;
    }

    /**
     * Whether a segment points at a specific financial instrument: one the user
     * holds, or an ISIN/ticker for one they do not (the clauses forbid
     * suggesting unheld instruments just as firmly).
     */
    private function mentionsInstrument(string $segment): bool
    {
        if (preg_match(self::ISIN, $segment) === 1) {
            return true;
        }

        $pattern = $this->heldPattern();

        if ($pattern !== null && preg_match($pattern, $segment) === 1) {
            return true;
        }

        if (preg_match_all(self::TICKER, $segment, $matches) >= 1) {
            foreach ($matches[0] as $token) {
                if (! in_array($token, self::NOT_TICKERS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * One alternation over everything the user owns that names an instrument:
     * asset names, tickers, ISINs and category names, minus the ones that are
     * really asset classes. Soft-deleted rows are included: a position the user
     * sold is still a named instrument they can be told to buy back. Built once
     * per request — the filter runs on every message of a rendered conversation.
     *
     * @return non-empty-string|null
     */
    private function heldPattern(): ?string
    {
        if ($this->heldPatternBuilt) {
            return $this->heldPattern;
        }

        $this->heldPatternBuilt = true;

        /** @var list<string> $raw */
        $raw = [];

        foreach (Asset::query()->withTrashed()->get(['name', 'ticker', 'isin']) as $asset) {
            $raw[] = $asset->name;

            if ($asset->ticker !== null) {
                $raw[] = $asset->ticker;
            }

            if ($asset->isin !== null) {
                $raw[] = $asset->isin;
            }
        }

        foreach (Category::query()->withTrashed()->get(['name']) as $category) {
            $raw[] = $category->name;
        }

        $names = [];
        foreach ($raw as $name) {
            $name = trim($name);

            if (mb_strlen($name) < self::MIN_NAME_LENGTH || $this->isGenericClassName($name)) {
                continue;
            }

            $names[mb_strtolower($name)] = $name;
        }

        if ($names === []) {
            return null;
        }

        // Longest first, so "VWCE Accumulating" wins over "VWCE" and the match
        // is reported against the most specific name present.
        $sorted = array_values($names);
        usort($sorted, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $alternation = implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '/'),
            $sorted,
        ));

        return $this->heldPattern = '/(?<![\p{L}\p{N}])(?:'.$alternation.')(?![\p{L}\p{N}])/iu';
    }

    /**
     * An asset or category whose name is an asset class rather than a specific
     * instrument. Compared accent- and punctuation-insensitively so "Liquidità"
     * and "liquidita" are the same word.
     */
    private function isGenericClassName(string $name): bool
    {
        $normalised = mb_strtolower(trim($name));
        $normalised = strtr($normalised, [
            'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u',
        ]);
        $normalised = trim((string) preg_replace('/\s+/u', ' ', $normalised));

        return in_array($normalised, self::GENERIC_CLASS_NAMES, true);
    }

    /**
     * The bullet or numbering a segment opens with, so a redacted list item
     * stays a list item instead of collapsing the markdown around it.
     */
    private function leadingMarkup(string $segment): string
    {
        preg_match('/^(\s*(?:[-*+]|\d+\.|#{1,6})?\s*)/u', $segment, $matches);

        return $matches[1] ?? '';
    }
}
