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
        .'(?:ri)?compr(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|(?:ri)?acquist(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|(?:ri)?vend(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'|ridu(?:ci|rre?|cendo|rrei|rresti|rrebbe|ciamo|cete)'
        .'|alleggeri(?:sci|re?|sco|rei|resti|rebbe|amo)'
        .'|aument(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|increment(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|disinvest(?:i|ire?|endo|irei|iresti|irebbe|iamo)'
        .'|liquid(?:a|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|sostitu(?:isci|ire?|isco|irei|iresti|irebbe|iamo)'
        .'|chiud(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'|apri(?:re?)?'
        .'|(?:ri)?bilanc(?:ia|i|iare?|iando|erei|eresti|erebbe|iamo|iate)'
        .'|spost(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        // Verbs the WEA-10 harness walked through: the same act, another word.
        // "uscite" is left out on purpose: it is the cashflow page's own word.
        .'|usc(?:ire?|endo|irei|iresti|irebbe)'
        .'|opt(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|punt(?:are?|ando|erei|eresti|erebbe|iamo|ate)|punta su'
        .'|tagli(?:a|are?|ando|erei|eresti|erebbe|amo|ate)'
        .'|evit(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        // Only the conditional: the imperative "scarica" is how you are told
        // to download a KID.
        .'|scaric(?:herei|heresti|herebbe)'
        .'|scommett(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'|raddoppi(?:a|i|are?|ando|erei|eresti|erebbe|amo|ate)'
        .'|dimezz(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|azzer(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|monetizz(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|mett(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'|prefer(?:irei|iresti|irebbe)'
        .'|lasc(?:ia|iare?|erei|eresti|erebbe) perdere'
        .'|esci|entra|entrar(?:e)?'
        .'|raccomand(?:o|iamo|erei|o di)'
        .')'.self::CLITIC.'\b/iu';

    /**
     * The same act in the passive: "andrebbe ridotta", "va venduto". The
     * participle is required, so the very common "va bene" / "va considerato"
     * does not trip it.
     */
    private const string PASSIVE_DIRECTIVES = '/\b(?:andrebbe(?:ro)?|va|vanno|sarebbe da|è da|dev[eo]no|deve)\s+'
        .'(?:ridott|vendut|alleggerit|aumentat|chius|apert|sostituit|spostat|tagliat'
        .'|comprat|acquistat|liquidat|incrementat|dimezzat|azzerat|disinvestit)\w*\b/iu';

    /**
     * The directive turned into a noun — "Raccomandazione: riduzione di X",
     * "Un taglio del 20% avrebbe senso". The headings stand on their own;
     * the bare nouns are only directive when a recommendation marker follows
     * within a few words, because "la vendita di un ETF" on its own is
     * ordinary explanation.
     *
     * "raccomandazione personalizzata" is excluded: that exact phrase is the
     * refusal the boundary clauses *require* ("per una raccomandazione
     * personalizzata serve un consulente abilitato"), and removing it would
     * delete the disclaimer rather than the advice.
     */
    private const string NOMINAL_DIRECTIVES = '/\b(?:'
        .'raccomandazion[ei](?!\W+personalizzat)'
        .'|suggeriment[oi]|consiglio operativo'
        .'|(?:azione|mossa|proposta|strategia|scelta) (?:consigliat|suggerit|raccomandat|operativ)\w*'
        .'|(?:la |una )?direzione (?:sensata|naturale|giusta|ovvia|indicata)'
        .'|la posizione da (?:ridurre|vendere|alleggerire|chiudere|aumentare|tagliare)'
        .'|(?:punt|portan|conducon|convergon|indican)\w*\s+(?:tutt[ei]\s+)?(?:verso|in direzione)'
        .'|(?:acquist[oi]|vendit[ae]|riduzion[ei]|increment[oi]|aument[oi]|tagli[oi]|alleggeriment[oi]'
        .'|uscit[ae]|ingress[oi]|spostament[oi]|ribilanciament[oi]|switch|conversion[ei])'
        .'(?=(?:\W+\w+){0,10}\W+(?:consigliat|suggerit|raccomandat|opportun|sensat|avrebbe senso|ha senso|da fare))'
        .')/iu';

    /**
     * Directives in the languages the filter was never written for. The rule
     * that the advisor answers only in Italian now lives in
     * AdvisorBoundaries::CLAUSES rather than in an overridable prompt file, but
     * a model still drops into English on its own — ETF jargon is English — so
     * the most common English phrasings are covered, plus the French and German
     * conditionals the harness used. This is a backstop, not real coverage of
     * those languages; see docs/ai-advisor-firewall.md.
     */
    private const string FOREIGN_DIRECTIVES = '/\b(?:'
        .'buy(?!\W+and\W+hold)|buying|sell|selling|purchase|reduce|reducing|trim|trimming'
        .'|increase|increasing|rebalance|swap|offload|divest|liquidate|dump'
        .'|you should|you ought to|i would|if i were you|my recommendation|i recommend'
        .'|i suggest|i\'d (?:buy|sell|reduce|trim|increase|go)'
        .'|achet(?:e|er|erais|eriez)|achèt\w*|vend(?:re|rais|riez)|r[ée]duir(?:e|ais|iez)'
        .'|kaufen|verkaufen|reduzieren|erh[öo]hen|umschichten'
        .')\b/iu';

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
        .'|ricordar|tener|aggiornar|approfondir|rivedere i dati'
        .'))/iu';

    /**
     * Suitability judgements. Prohibited whenever they land on a specific
     * instrument: ESMA ¶65 is explicit that a disclaimer does not undo one.
     * The second block is the same judgement said in other words — the
     * category the WEA-10 harness walked through most easily, because the
     * original list matched the clauses' own vocabulary and nothing else.
     */
    private const string SUITABILITY = '/\b(?:'
        .'adatt[oaie] (?:a te|al tuo|alla tua)'
        .'|giust[oaie] per (?:te|il tuo|la tua)'
        .'|(?:in linea|coerente|coerenti) con (?:il tuo|la tua|i tuoi|le tue)'
        .'|su misura per (?:te|il tuo)'
        .'|fa per te'
        .'|calza (?:bene |perfettamente |meglio )?(?:con|per) (?:te|il tuo|la tua)'
        .'|(?:rispecchia|riflette|si sposa con|si adatta a|corrisponde a) (?:il tuo|la tua|i tuoi|le tue)'
        .'|per (?:uno|una|chi|qualcuno|un investitore) come te'
        .'|(?:un |il )?profilo come il tuo'
        .'|(?:quello|ciò|quel) che ti serve'
        .'|(?:non )?ha senso (?:nel tuo|per te|per il tuo|nella tua)'
        .'|(?:troppo|poco) \w+ per (?:te|il tuo|la tua|un profilo)'
        .'|(?:appropriat|indicat|consigliabil|idone|opportun)[oaie] per (?:te|il tuo|la tua)'
        .'|la scelta (?:naturale|giusta|migliore|ovvia|sensata)'
        // The same judgement in English, where no list reached at all.
        .'|(?:good|right|perfect|ideal) fit for your'
        .'|suitable for (?:you|your)|right for your|fits your|matches your'
        .'|in line with your|appropriate for your'
        .')\b/iu';

    /**
     * A reference to one specific position made without naming it — the three
     * periphrases the boundary clauses forbid by name, plus the shapes the
     * harness found around them. Each of these singles out a single holding,
     * which is what makes it a reference to an instrument; "la tua liquidità"
     * names a class and is deliberately not here.
     */
    private const string PERIPHRASIS = '/(?:'
        .'(?:la |una |quella )?(?:tua |sua )?posizion[ei] (?:più|meno|che|da)\b'
        .'|(?:la |il )?(?:tua|tuo) (?:prima|primo|più grande|maggiore|peggiore|migliore) posizion[ei]'
        .'|quell[oa] che (?:rende|pesa|costa|ti costa|ti è costat|cresce|perde|guadagna|va|hai)'
        .'|(?:ciò|quello) che rende (?:di )?(?:meno|più)'
        .'|quell[oa] (?:più|meno) \p{L}+'
        .'|(?:il |l\')?(?:primo|ultimo) (?:della lista|in lista|del portafoglio)'
        .'|(?:la|il) (?:peggiore|migliore) (?:del|della) (?:tuo |tua )?portafoglio'
        .')/iu';

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

    /**
     * Instruments, issuers, indices and venues the user does *not* hold,
     * spelled out in words rather than as a ticker. Recommending something the
     * user does not own is the least ambiguous personal recommendation there
     * is, and it was the case Layer 3 covered worst: the protected set is built
     * from the user's own holdings, so by construction it could not see them.
     *
     * A list of the names an Italian retail investor actually meets, not an
     * instrument database. It is meant to be extended; see
     * docs/ai-advisor-firewall.md.
     */
    private const array KNOWN_INSTRUMENTS = [
        // Issuers and fund families
        'vanguard', 'ishares', 'blackrock', 'amundi', 'xtrackers', 'lyxor', 'invesco',
        'spdr', 'state street', 'fidelity', 'jpmorgan', 'j.p. morgan', 'vaneck',
        'wisdomtree', 'franklin templeton', 'pimco', 'eurizon', 'anima sgr', 'pictet',
        'schroders', 'robeco', 'nordea', 'ubs etf', 'hsbc etf', 'l&g', 'legal & general',
        // Indices and the funds named after them
        'msci world', 'msci emerging', 'msci acwi', 's&p 500', 'sp500', 'nasdaq',
        'ftse mib', 'ftse all-world', 'ftse all world', 'euro stoxx', 'eurostoxx',
        'stoxx 600', 'russell 2000', 'dow jones', 'all-world', 'all world',
        // Crypto, named rather than ticker-shaped
        'bitcoin', 'ethereum', 'solana', 'cardano', 'polkadot', 'dogecoin', 'tether',
        // Venues and brokers
        'degiro', 'directa', 'fineco', 'binance', 'coinbase', 'kraken', 'revolut',
        'trade republic', 'scalable capital', 'moneyfarm', 'etoro', 'bitpanda',
        'interactive brokers', 'young platform', 'intesa sanpaolo', 'unicredit',
    ];

    /** Shorter than this, a name matches too much to be usable as a signal. */
    private const int MIN_NAME_LENGTH = 3;

    /**
     * How many preceding segments an instrument reference carries forward. The
     * model names a position, describes it for a sentence or two, and acts on
     * it with a pronoun: "Parliamo di Bitcoin. È la tua posizione più volatile.
     * Io la venderei." A one-segment lookback stopped exactly one sentence
     * short of that, which is the window the WEA-10 harness walked through.
     */
    private const int LOOKBACK = 3;

    /**
     * A segment that explains rather than instructs. Only consulted when the
     * instrument comes from a *neighbouring* segment and not from this one, so
     * "ribilanciare significa…" after a sentence that mentioned VWCE survives
     * while "ridurre Bitcoin al 5% significa venderne metà" does not. This is
     * what keeps the ESMA ¶48-50 safe harbour intact under a wider lookback.
     */
    private const string EXPLANATORY = '/\b(?:significa|vuol dire|consiste (?:nel|in)|si intende'
        .'|per definizione|in generale|ad esempio|per esempio|è il processo)\b/iu';

    /** @var non-empty-string|null Built from a constant, so it is memoised for the whole process. */
    private static ?string $knownPattern = null;

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

        // The refusal and the footer are the filter's own prose. Screening them
        // would let the filter react to what it just wrote, so idempotence is
        // made structural rather than left to the word lists happening to miss.
        if (trim($text) === trim(self::REFUSAL)) {
            return self::REFUSAL;
        }

        $body = $text;
        $hadFooter = str_ends_with(rtrim($text), self::FOOTER);

        if ($hadFooter) {
            $body = rtrim(mb_substr(rtrim($text), 0, -mb_strlen(self::FOOTER)));
        }

        [$out, $redacted, $kept] = $this->screen($body);

        if ($redacted === 0) {
            return $text;
        }

        Log::info('Advisor reply filtered', ['segments_redacted' => $redacted, 'segments_kept' => $kept]);

        if ($kept === 0) {
            return self::REFUSAL;
        }

        return rtrim($out)."\n\n".self::FOOTER;
    }

    /**
     * Screen a free-text field of a generative-UI widget — a milestone's
     * `action` / `rationale`, a proposal's `notes`. Same check as filter(),
     * without the conversational wrapping: a widget field is a line in a card,
     * not a reply, so a removed one is replaced by the marker alone rather than
     * by the refusal template and a footer. Idempotent for the same reason.
     */
    public function filterField(string $text): string
    {
        if (trim($text) === '' || trim($text) === self::REDACTION) {
            return $text;
        }

        [$out, $redacted, $kept] = $this->screen($text);

        if ($redacted === 0) {
            return $text;
        }

        Log::info('Advisor widget field filtered', ['segments_redacted' => $redacted, 'segments_kept' => $kept]);

        return $kept === 0 ? self::REDACTION : rtrim($out);
    }

    /**
     * Screen every free-text field the model wrote into a reply's widgets.
     * `content` was filtered from the start and `widgets` was not, one line
     * below it — and the widgets are the surface the product uses to give
     * operative instructions (a milestone's "action"), so they are the one
     * that most needed it. App-computed payloads (chart series, totals,
     * percentages) are left alone: the model never writes those.
     *
     * @param  list<array{type: string, data: array<string, mixed>}>|null  $widgets
     * @return list<array{type: string, data: array<string, mixed>}>|null
     */
    public function filterWidgets(?array $widgets): ?array
    {
        if ($widgets === null) {
            return null;
        }

        $out = [];

        foreach ($widgets as $widget) {
            /** @var array<string, mixed> $data */
            $data = $this->filterTree($widget['data']);
            $out[] = ['type' => $widget['type'], 'data' => $data];
        }

        return $out;
    }

    /**
     * The widget keys that carry text the model wrote. Everything else in a
     * widget payload is computed by the app from the user's own data.
     */
    private const array MODEL_WRITTEN_KEYS = ['action', 'rationale', 'notes', 'label', 'description'];

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private function filterTree(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->filterTree($value);

                continue;
            }

            if (is_string($value) && in_array($key, self::MODEL_WRITTEN_KEYS, true)) {
                $data[$key] = $this->filterField($value);
            }
        }

        return $data;
    }

    /**
     * The shared screen: split into segments, remove the ones that pair a
     * directive with a reference to a specific instrument, and report how many
     * went each way.
     *
     * @return array{0: string, 1: int, 2: int} rebuilt text, segments redacted, segments kept
     */
    private function screen(string $text): array
    {
        // Split on sentence and line boundaries, keeping the separators so the
        // text can be rebuilt with its markdown intact. A colon is NOT a
        // boundary: "Raccomandazione: riduzione di Bitcoin" is one statement,
        // and splitting it put the directive and the instrument in different
        // segments, where neither half tripped the co-occurrence rule.
        $parts = preg_split('/([.!?;\n]+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return [self::REFUSAL, 1, 0];
        }

        /** @var list<array{index: int, names: bool}> $segments */
        $segments = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0 && trim($part) !== '') {
                $segments[] = ['index' => $index, 'names' => $this->mentionsInstrument($part)];
            }
        }

        $redacted = 0;
        $kept = 0;

        foreach ($segments as $ordinal => $segment) {
            $part = $parts[$segment['index']];

            if (! $this->isDirective($part)) {
                $kept++;

                continue;
            }

            if ($this->pointsAtInstrument($segments, $ordinal, $part)) {
                $parts[$segment['index']] = $this->leadingMarkup($part).self::REDACTION;
                $redacted++;
            } else {
                $kept++;
            }
        }

        return [implode('', $parts), $redacted, $kept];
    }

    /**
     * Whether the directive in this segment lands on a specific instrument:
     * one named here, in the previous LOOKBACK segments, or in the one that
     * follows (a heading names its subject on the next line).
     *
     * @param  list<array{index: int, names: bool}>  $segments
     */
    private function pointsAtInstrument(array $segments, int $ordinal, string $part): bool
    {
        if ($segments[$ordinal]['names']) {
            return true;
        }

        if (preg_match(self::EXPLANATORY, $part) === 1) {
            return false;
        }

        for ($back = 1; $back <= self::LOOKBACK; $back++) {
            if (($segments[$ordinal - $back]['names'] ?? false) === true) {
                return true;
            }
        }

        return ($segments[$ordinal + 1]['names'] ?? false) === true;
    }

    /** Whether a segment carries a directive the boundary clauses prohibit. */
    private function isDirective(string $segment): bool
    {
        return preg_match(self::HARD_DIRECTIVES, $segment) === 1
            || preg_match(self::SOFT_DIRECTIVES, $segment) === 1
            || preg_match(self::SUITABILITY, $segment) === 1
            || preg_match(self::PASSIVE_DIRECTIVES, $segment) === 1
            || preg_match(self::NOMINAL_DIRECTIVES, $segment) === 1
            || preg_match(self::FOREIGN_DIRECTIVES, $segment) === 1;
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

        if (preg_match(self::PERIPHRASIS, $segment) === 1) {
            return true;
        }

        if (preg_match($this->knownInstrumentPattern(), $segment) === 1) {
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
     * One alternation over KNOWN_INSTRUMENTS, built once per process. Longest
     * first so "msci world" wins over a shorter entry it contains.
     *
     * @return non-empty-string
     */
    private function knownInstrumentPattern(): string
    {
        if (self::$knownPattern !== null) {
            return self::$knownPattern;
        }

        $names = self::KNOWN_INSTRUMENTS;
        usort($names, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $alternation = implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '/'),
            $names,
        ));

        return self::$knownPattern = '/(?<![\p{L}\p{N}])(?:'.$alternation.')(?![\p{L}\p{N}])/iu';
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
