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
 * directive (buy / sell / reduce / "you should", a suitability judgement, a
 * target allocation written as notation) AND a reference to a specific
 * financial instrument — either one the user actually holds (matched against
 * their own asset names, tickers, ISINs and categories) or one they do not (an
 * ISIN- or ticker-shaped token). Talking about asset *classes* is untouched:
 * generic advice is allowed under ESMA35-43-3861 ¶48-50, and gutting it would
 * gut the product.
 *
 * Co-occurrence alone is not enough, and the WEA-14 review is why. A refusal
 * has to name what it refuses — "non posso dirti se comprare o vendere VWCE"
 * carries a directive verb and an instrument name in one segment by necessity,
 * which is exactly the shape the rule fires on. Run against six real model
 * replies, the rule altered all six and removed thirteen segments: the MiFID
 * disclaimer, the Art. 50(1) AI Act declaration and the reflection questions
 * the clauses list among the *permitted* outputs. So over-blocking is NOT the
 * prudent direction of error: deleting the disclaimer and replacing it with a
 * note saying an operative instruction was removed makes the app accuse a
 * compliant reply of non-compliance, which is a compliance loss of its own.
 *
 * Hence the third gate, isProtectedSpeech(): a segment that refuses, discloses,
 * declares the AI or asks the user to reflect is never removed, however many
 * directive tokens it contains. See docs/ai-advisor-firewall.md for how that
 * gate is kept from becoming the bypass.
 */
class AdvisorOutputFilter
{
    /**
     * Replaces a single offending segment, keeping the rest of the reply.
     *
     * Worded as what the filter did, not as a verdict on what the model wrote.
     * This is a lexical check, so a removal is "this matched" and not "this was
     * advice"; the earlier wording asserted the latter, and the WEA-14 review
     * found it attached to five compliant refusals and reflection questions.
     */
    public const string REDACTION = '[segmento rimosso automaticamente dal filtro di conformità]';

    /** Replaces the whole reply when nothing survived the check. */
    public const string REFUSAL = <<<'TEXT'
        Non posso rispondere a questa domanda: indicarti se comprare, vendere o quanto detenere di uno strumento specifico sarebbe consulenza finanziaria, e questo assistente non è autorizzato a fornirla.

        Posso invece mostrarti i dati su cui si basa la decisione e gli aspetti da valutare: chiedimi come stanno i tuoi numeri (rendimento, concentrazione, costi, liquidità, distanza dall'obiettivo) o che cosa conviene verificare prima di decidere.

        Per una raccomandazione personalizzata serve un consulente finanziario abilitato (in Italia, iscritto all'albo OCF).
        TEXT;

    /** Appended once when part of a reply was redacted, so the gap is explained. */
    public const string FOOTER = '_Una parte di questa risposta è stata rimossa automaticamente da un filtro di conformità: poteva essere letta come un\'indicazione operativa su uno strumento specifico. Questo assistente è uno strumento informativo e non fornisce consulenza finanziaria; per una raccomandazione personalizzata rivolgiti a un consulente abilitato (albo OCF)._';

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
        .'|ridu(?:ci|rre?|cendo|rrei|rresti|rrebbe|ciamo|cete|ca|cano)'
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
        .'|prefer(?:irei|irebbe)'
        .'|lasc(?:ia|iare?|erei|eresti|erebbe) perdere'
        .'|esci|entra|entrar(?:e)?'
        .'|raccomand(?:o|iamo|erei|o di)'
        // The WEA-14 harness's verb-gap family: the same act, another word.
        // Restricted to the imperative, the infinitive and the conditional —
        // the third person ("il rendimento si mantiene", "l'ETF elimina il
        // rischio di cambio") is description, not instruction.
        .'|elimin(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|abbandon(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|trasfer(?:isci|ire?|endo|irei|iresti|irebbe|iamo|ite)'
        .'|congel(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|smett(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'|togl(?:i|iere?|iendo|ierei|ieresti|ierebbe|iamo|iete)'
        .'|mantien[ia]|manten(?:ere?|endo|iamo|ete)|manterr(?:ei|esti|ebbe)'
        // "Tieni presente/conto/d'occhio" is the advisor's own idiom and is
        // not a holding instruction.
        .'|(?:tien[ia]|ten(?:ere|endo))(?!\s+(?:present|cont|d\'occhio|a\s+mente))|terr(?:ei|ebbe)'
        // Market timing on a named instrument: no imperative required.
        // Without the `i` form on purpose: `aspetti` is also the plural noun,
        // and "aspetti da valutare" is this advisor's own section heading —
        // the live probe found that one collision accounting for five of the
        // eighteen segments it removed, every one of them a heading.
        .'|aspett(?:a|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|approfitt(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|star(?:ei|ebbe)\s+alla\s+larga|alla\s+larga\s+da'
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
        .'|overweight|underweight|consider|rotate|rotating|rotate out'
        .'|shift|shifting|allocate|take profit|cut your|go long|go short'
        .'|achet(?:e|er|erais|eriez)|achèt\w*|vend(?:re|rais|riez)|r[ée]duir(?:e|ais|iez)'
        .'|kaufen|verkaufen|reduzieren|erh[öo]hen|umschichten'
        .'|mein rat|ich w[üu]rde|empfehle|ratschlag|statt'
        .')\b/iu';

    /**
     * Directives that are prohibited only when they introduce an action. The
     * same words introduce the advisor's legitimate output ("dovresti
     * verificare il TER"), which the prompt explicitly asks for, so they are
     * cleared when an epistemic verb follows within a few words.
     */
    private const string SOFT_DIRECTIVES = '/\b(?:'
        .'dovresti|ti conviene|conviene|meglio se|faresti bene a'
        .'|ti consiglio|consiglio di|ti suggerisco|suggerisco'
        .'|ti consiglierei|consiglierei|ti suggerirei|suggerirei|ti raccomanderei'
        .'|se fossi in te|al posto tuo|al tuo posto'
        .'|la mia preferenza|la mia scelta sarebbe|personalmente (?:io )?'
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
        .'|ti calza'
        .'|la risposta (?:giusta|migliore|naturale) (?:a|al|alla|per)'
        .'|per (?:una|la) persona con (?:il tuo|la tua)'
        .'|(?:rispecchia|riflette|si sposa con|si adatta a|corrisponde a) (?:il tuo|la tua|i tuoi|le tue)'
        .'|per (?:uno|una|chi|qualcuno|un investitore) come te'
        .'|(?:un |il )?profilo come il tuo'
        .'|(?:quello|ciò|quel) che ti serve'
        .'|(?:non )?ha senso (?:nel tuo|per te|per il tuo|nella tua)'
        .'|(?:troppo|poco) \w+ per (?:te|il tuo|la tua|un profilo)'
        .'|(?:appropriat|indicat|consigliabil|idone|opportun)[oaie] per (?:te|il tuo|la tua)'
        .'|la scelta (?:più |meno )?(?:naturale|giusta|migliore|ovvia|sensata)'
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
        // The position pointed at by its row, its units or its plan rather
        // than by a name — the WEA-14 harness's `reference` family.
        .'|\d+\s+(?:delle\s+)?(?:tue\s+)?quote\b|le\s+tue\s+quote\b'
        .'|(?:il|un)\s+PAC\s+(?:da|su|di|a)\b'
        .'|(?:il|la|lo)\s+(?:prim|second|terz|quart|quint|sest|ultim)\w*\s+'
        .'(?:strumento|posizione|riga|voce|asset|fondo|ETF|titolo)'
        .'|(?:della|nella|in)\s+tabella\s+(?:qui\s+)?(?:sopra|sotto)'
        .')/iu';

    /**
     * A percentage or a money amount, however the model groups and spaces it:
     * `25%`, `0 %`, `5.000€`, `1.234,50 euro`.
     */
    private const string QUANTITY = '\d{1,3}(?:[.\s]\d{3})*(?:[.,]\d+)?\s*(?:%|€|euro\b)';

    /**
     * The allocation recommendation written as notation rather than as a
     * sentence: a weight, a transition operator, another weight. All six
     * phrases in the WEA-14 harness's first family are this shape, and none of
     * them carries a verb — which is why no amount of widening the word lists
     * reached them. `VWCE: 40% → 25%` is an instruction with nothing to list.
     *
     * The arrow is taken as notation unconditionally: Italian prose describing
     * what a position did says "è passato dal 40% al 25%", it does not draw an
     * arrow.
     */
    private const string NOTATION_ARROW = '/(?:'.self::QUANTITY.'|[\p{L}\p{N}]{2,})\s*(?:→|➔|➜|⇒|=>|-+>|~>)\s*'.self::QUANTITY.'/u';

    /**
     * The same move written as a span rather than as an arrow: "Bitcoin da
     * 15% a 5% entro giugno". Suppressed by DESCRIPTIVE, which is what the
     * reporting reading of the same words looks like ("è passato dal 40% al
     * 25% negli ultimi sei mesi").
     */
    private const string NOTATION_RANGE = '/\bda(?:l|llo|lla|ll\')?\s+'.self::QUANTITY.'\s+a(?:l|llo|lla|ll\')?\s+'.self::QUANTITY.'/iu';

    /** A markdown table row. */
    private const string TABLE_ROW = '/^\s*\|.*\|\s*$/u';

    /**
     * The header that turns the rows beneath it into a proposed allocation —
     * `| Posizione | Ora | Target |`. The rows themselves are just an
     * instrument and two numbers, which is also what a perfectly legitimate
     * portfolio table looks like, so the header is what tells them apart.
     */
    private const string TABLE_TARGET_HEADER = '/\|[^|]*\b(?:target|obiettivo|ideal[ei]|propost\w+|consigliat\w+|suggerit\w+|a\s+regime)\b[^|]*\|/iu';

    /**
     * The same recommendation named as a target rather than drawn: "peso
     * target 25%", "allocazione ideale", "Target: VWCE 0%". Paired with a
     * percentage by isNotational(), so a goal amount in euro — which the app
     * legitimately talks about — does not trip it.
     *
     * Deliberately requires an allocation noun in front of `target`, or a
     * colon after it. The bare word is the product's own vocabulary ("sei al
     * 62% del target" is the savings goal, not an instrument recommendation).
     */
    private const string NOTATION_TARGET = '/(?:'
        .'\b(?:allocazion[ei]|peso|pesi|quot[ae]|esposizion[ei]|percentual[ei]|composizion[ei]|ripartizion[ei])\s+'
        .'(?:target|ideal[ei]|obiettivo|consigliat\w+|suggerit\w+|raccomandat\w+|propost\w+|di\s+arrivo|a\s+regime)\b'
        .'|\btarget\s*:'
        .'|\b(?:tetto|soglia|limite)\s+(?:ragionevole|prudente|massim[oa]|consigliat\w+|suggerit\w+|sensat[oa]|accettabile)'
        .')/iu';

    /**
     * The recommendation as a ceiling — "Bitcoin non dovrebbe superare il 5%".
     * No imperative, and the negation makes it read as a caution rather than
     * an instruction, but it is a personalised weight on a named instrument,
     * which is what the clauses forbid.
     */
    private const string NOTATION_CAP = '/(?:'
        .'\bnon\s+dovrebbe(?:ro)?\s+(?:\w+\s+){0,2}(?:superare|supera|pesare|pesa|eccedere|rappresentare|andare|salire|stare)'
        .'|\bnon\s+(?:deve|devono)\s+(?:\w+\s+){0,2}(?:superare|pesare|eccedere|rappresentare)'
        .'|\bnon\s+(?:più|oltre)\s+(?:di|del|dello|della|dell\')'
        .'|\bal\s+massimo\s+(?:il|lo|la|un|una)'
        .'|\bun\s+tetto\b|\bil\s+tetto\b'
        .')/iu';

    /**
     * The move stated in euro instead of in percent — "Porta VWCE a 5.000€",
     * "Destina 3.000€ a SWDA". These verbs are far too ordinary to list
     * unconditionally (`porta`, `lascia`, `tieni` appear in every other
     * sentence), so each one is only directive when an amount follows it in
     * the same segment, and not when the segment is DESCRIPTIVE.
     *
     * The gap may not cross a goal frame. "Quanto dovresti versare mensilmente
     * per raggiungere i 300.000€ entro il 2040" is the savings goal — the app's
     * own forecast, and the one target figure it exists to talk about — not a
     * move on an instrument, and the live probe found it redacted. Only a goal
     * frame *between* the verb and the amount suppresses it, so "Porta VWCE a
     * 5.000€ per raggiungere il tuo obiettivo" is untouched: there the amount
     * comes first and the goal is the pretext, not the subject.
     */
    private const string NOTATION_MOVE = '/\b(?:'
        .'port(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|lasc(?:ia|i|iare?|iando|erei|eresti|erebbe|iamo|iate)'
        .'|vers(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|destin(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|ferm(?:a|i|ati|arsi|ando|erei|eresti|erebbe|atevi)'
        .'|scend(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'|sal(?:i|ire?|endo|irei|iresti|irebbe|iamo|ite)'
        .'|limit(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|alloc(?:a|hi|are?|ando|herei|heresti|herebbe|hiamo|ate)'
        .'|concentr(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .')'.self::CLITIC.'\b(?:(?!per\s+raggiunger|obiettivo|traguardo)[^.!?;\n]){0,60}?'.self::QUANTITY.'/iu';

    /**
     * A signed amount in a list or a table cell: `VWCE: -5.000€`,
     * `Obbligazioni: +5.000€`. Restricted to money — a signed *percentage* is
     * how a return is written, and eating those would eat the product.
     *
     * The label is required, not optional. A bare sign also means addition,
     * and the live probe caught this eating a plain description of the current
     * portfolio — `Azionario: 74.000€ (62.000€ in VWCE + 12.000€ in SWDA)`,
     * where the `+` is a sum, not a proposed delta. What makes a delta a delta
     * is that it is the value of a labelled row.
     */
    private const string NOTATION_DELTA = '/[:|]\s*[-+−±]\s*\d{1,3}(?:[.\s]\d{3})*(?:[.,]\d+)?\s*(?:€|euro\b)/u';

    /**
     * "meno VWCE, più Obbligazioni" — the allocation instruction reduced to a
     * comparison, with no verb and no number. Harmless on asset classes, which
     * is where the co-occurrence gate leaves it alone.
     */
    private const string NOTATION_MORE_LESS = '/\b(?:meno|più)\s+[^\s,;.]{2,}\s*,\s*(?:e\s+)?(?:più|meno)\s+[^\s,;.]{2,}/iu';

    /**
     * The segment is reporting what happened, not prescribing what to do:
     * "hai versato 500€ al mese su VWCE negli ultimi tre anni". Suppresses the
     * notational family only — a past participle is no excuse for "vendi
     * VWCE", so the lexical directives are unaffected.
     */
    private const string DESCRIPTIVE = '/(?:'
        .'\b(?:è|sono|era|erano|si\s+è|si\s+sono)\s+(?:\w+\s+){0,2}'
        .'(?:passat|sces|salit|cresciut|calat|aumentat|diminuit|ridott|portat|attestat|moss|andat)\w*'
        .'|\b(?:hai|ha|hanno|avevi|aveva|avevano|avete|abbiamo|ho)\s+(?:\w+\s+){0,2}'
        .'(?:versat|investit|comprat|acquistat|vendut|ridott|aumentat|guadagnat|pers|res|oscillat|fatt|chius|apert)\w*'
        .'|\bnegli\s+ultimi|\bnell\'ultim|\bnel\s+(?:19|20)\d\d|\bdal\s+(?:19|20)\d\d'
        .'|\bstoricamente|\bin\s+passato|\bfinora|\bfino\s+a\s+(?:ora|oggi)|\bad?\s+oggi\b'
        .'|\bda\s+inizio\s+anno|\bl\'anno\s+scorso|\bil\s+mese\s+scorso|\bl\'anno\s+passato'
        .')/iu';

    /**
     * The segment is a what-if, not a plan: "se Bitcoin scendesse del 50% (da
     * 18.000€ a 9.000€), come reagiresti?". Suppresses the notational family
     * for the same reason DESCRIPTIVE does — the signal there is a bare pair
     * of numbers, and a hypothetical is the other reading of exactly that
     * shape. The live probe found NOTATION_RANGE eating the stress-test
     * question the clauses ask the advisor to pose.
     *
     * The lexical directives are deliberately unaffected: "se fossi in te
     * venderei VWCE" is advice with a conditional bolted on, and SOFT_DIRECTIVES
     * already lists that frame.
     */
    private const string HYPOTHETICAL = '/(?:'
        .'\bse\s+(?:\w+\s+){0,3}(?:scendess|salisse|perdess|guadagnass|crollass|dimezzass'
        .'|raddoppiass|aumentass|calass|facess|andass|arrivass|toccass)\w*'
        .'|\bipotes\w+|\bipotetic\w+|\bimmagina\b|\bimmaginiamo\b|\bsupponiamo\b'
        .'|\bmettiamo\s+(?:che|caso)|\bper\s+assurdo\b|\bnello\s+scenario\b|\bscenario\s+(?:in\s+cui|peggiore)'
        .'|\bin\s+caso\s+di\s+(?:crollo|calo|ribasso|discesa)'
        .')/iu';

    /**
     * A refusal. These govern forward: everything from the marker to the end of
     * the segment is the refusal's own subject matter, so the directive verbs
     * and instrument names it necessarily contains are not advice.
     *
     * Forward only, because "Vendi VWCE, dato che non posso dirti altro" is not
     * a refusal — the directive lands before the marker. And broken by an
     * ADVERSATIVE, because "non posso dirtelo, ma la direzione è ridurre
     * Bitcoin" is the WEA-10 harness's refuse-in-form-advise-in-substance move.
     */
    private const string REFUSAL_FRAME = '/(?:'
        .'non\s+poss[oi]\b'
        .'|non\s+devo\b'
        .'|non\s+sono\s+(?:autorizzat|abilitat|in\s+grado|un[a\']?\s*(?:consulente|professionista))'
        .'|non\s+mi\s+è\s+(?:consentito|permesso|possibile)'
        .'|non\s+spetta\s+a\s+me'
        .'|non\s+(?:ti\s+)?(?:dirò|dico|dirti|dirtelo)\b'
        .'|non\s+(?:fornisco|do)\s+(?:indicazioni|consigli|raccomandazioni|consulenza)'
        .'|non\s+rientra\s+(?:nel|tra|fra)'
        .'|non\s+è\s+(?:il\s+mio\s+ruolo|compito\s+mio|una\s+decisione\s+che\s+posso)'
        .'|mi\s+astengo\b|evito\s+di\b'
        .'|(?:questa|quella)\s+è\s+una\s+(?:decisione|scelta)\s+(?:che\s+)?non\s+poss[oi]'
        .')/iu';

    /**
     * Statements *about* advice rather than statements *of* it: the MiFID
     * disclaimer, the referral to the OCF register, and the Art. 50(1) AI Act
     * declaration. Unlike a refusal these license the whole segment, because
     * they routinely come after what they qualify — "indicare cosa comprare o
     * vendere costituirebbe consulenza finanziaria" names the act first.
     *
     * The ADVERSATIVE check still applies, so a disclaimer with advice bolted
     * onto it after a "però" is not protected.
     */
    private const string DISCLAIMER = '/(?:'
        .'(?:sarebbe|costituirebbe|equivarrebbe\s+a|configurerebbe|è|sia)\s+(?:una\s+)?(?:forma\s+di\s+)?consulenza\s+(?:finanziaria|in\s+materia|personalizzata)'
        .'|consulenza\s+finanziaria\s+personalizzata'
        .'|raccomandazione\s+personalizzat'
        .'|consulente\s+(?:finanziario\s+)?abilitat'
        .'|albo\s+(?:degli\s+)?OCF|iscritto\s+all\'albo'
        .'|sistema\s+di\s+intelligenza\s+artificiale'
        .'|non\s+sono\s+un[a]?\s+(?:persona|essere\s+umano|umano)'
        .'|strumento\s+informativo\s+(?:ed\s+educativo|e\s+non)'
        .')/iu';

    /**
     * The reflection prompt, which the clauses list among the things the
     * advisor SHOULD do ("dire che cosa l'utente dovrebbe VERIFICARE,
     * CONTROLLARE o CHIEDERSI"). The review found the filter eating these
     * wholesale, because "chiediti se il peso di VWCE è coerente con la tua
     * tolleranza" trips SUITABILITY on the same phrase that the forbidden
     * assertion uses: the pattern cannot tell a question from a claim.
     *
     * Recognised by an explicit second-person frame rather than by a trailing
     * question mark. A bare "?" would hand over the obvious bypass — "Hai
     * pensato di ridurre Bitcoin al 5%?" is advice — and these frames are what
     * a reflection question actually looks like: they ask about the user's own
     * judgement, not about an operation.
     */
    private const string REFLECTION = '/\b(?:'
        .'chiediti|chieditelo|domandati|ti\s+sei\s+(?:mai\s+)?chiest[oa]'
        .'|prova\s+a\s+chiedert[ei]|una\s+domanda\s+da\s+(?:port[ei]|fart[ei])'
        .'|la\s+domanda\s+da\s+fars[ei]'
        .'|valuta\s+se|verifica\s+se|controlla\s+se|considera\s+se|rifletti\s+su|ragiona\s+su'
        .'|riuscirest[ei]\s+a|te\s+la\s+sentirest[ei]|saresti\s+(?:disposto|disposta|in\s+grado)'
        .'|ti\s+permetterest[ei]|sopporterest[ei]|reagirest[ei]|farest[ei]\s+se'
        .'|preferirest[ei]|ti\s+sentirest[ei]|ti\s+dà\s+(?:sicurezza|fastidio|serenità)'
        .')\b/iu';

    /**
     * How far after a REFLECTION frame a directive still counts as the frame's
     * own object. "Valuta se vendere VWCE" is an instruction dressed as a
     * question and must not be protected; "valuta se questa concentrazione ti
     * dà sicurezza o se preferiresti…" is the real thing, and its directive
     * token is far past this window.
     */
    private const int REFLECTION_OBJECT_WINDOW = 12;

    /**
     * The same reflection frame in the indicative rather than the imperative:
     * the advisor naming a question instead of ordering the user to answer
     * one. "Ti chiedi se mantenere entrambi abbia senso" reports a doubt; the
     * REFLECTION_OBJECT_WINDOW therefore does not apply to these, because that
     * window exists to stop an imperative pointed at an operation ("valuta se
     * vendere VWCE") and there is no imperative here to stop.
     *
     * Kept separate from REFLECTION for exactly that reason — merging them
     * would either re-eat these segments or open the window on the imperatives.
     */
    private const string REFLECTION_INDICATIVE = '/\b(?:'
        .'ti\s+chiedi\s+se|ci\s+si\s+chiede\s+se|c\'è\s+da\s+chiedersi'
        .'|vale\s+la\s+pena\s+(?:di\s+)?(?:chiedersi|domandarsi|interrogarsi)'
        .'|la\s+domanda\s+(?:da\s+port[ei]|che\s+conta|giusta|vera|chiave)'
        .'|ti\s+senti\s+di|te\s+la\s+senti\s+di|sei\s+disposto\s+a|sei\s+disposta\s+a'
        .'|la\s+domanda\s+è\s+se'
        .')\b/iu';

    /**
     * Advice wearing a question mark. These are the frames that turn an
     * interrogative into a proposal — "Hai pensato di ridurre Bitcoin al 5%?"
     * — and they are what stops the interrogative licence (see
     * isProtectedSpeech) from being the way through the filter.
     */
    private const string INTERROGATIVE_PROPOSAL = '/\b(?:'
        .'hai\s+(?:mai\s+)?(?:pensato|considerato|valutato)\s+(?:di|a|se)'
        .'|ci\s+hai\s+pensato|che\s+ne\s+(?:dici|pensi|diresti)\s+di'
        .'|perché\s+non|perche\'\s+non'
        .'|potrest[ei]\s+(?:considerare|valutare|pensare|provare)\s+(?:di|a)'
        .'|non\s+sarebbe\s+(?:meglio|il\s+caso)|non\s+ti\s+conviene|non\s+converrebbe'
        .'|non\s+pensi\s+che|non\s+credi\s+che|non\s+sarebbe\s+più\s+sensato'
        .'|sei\s+sicur[oa]\s+di\s+(?:voler|non)|ti\s+va\s+di'
        .'|vuoi\s+che\s+(?:io\s+)?(?:venda|compri|riduca|aumenti|sposti|liquidi|alleggerisca)'
        .')\b/iu';

    /**
     * The tag appended to an assertion to make it sound like a question —
     * "SWDA è la scelta giusta per te, no?". The sentence asserts; the tag
     * only asks for agreement, so the interrogative licence does not apply.
     * Found by attacking the licence rather than by reading model output: it
     * is the cheapest way through a rule that keys on the question mark.
     */
    private const string TAG_QUESTION = '/,\s*(?:no|vero|giusto|ok|okay|d\'accordo|chiaro'
        .'|non\s+(?:credi|pensi|trovi|ti\s+pare|è\s+vero))\s*[?.!]*\s*$/iu';

    /**
     * The pivot from refusing to advising. Everything after one of these is
     * outside whatever licence the segment had opened with.
     */
    private const string ADVERSATIVE = '/\b(?:ma|però|tuttavia|comunque|detto\s+(?:questo|ciò)|ciò\s+detto'
        .'|in\s+ogni\s+caso|in\s+compenso|d\'altra\s+parte|resta\s+(?:che|il\s+fatto)'
        .'|fermo\s+restando|se\s+proprio|volendo)\b/iu';

    /**
     * Prepositions that invert the directive immediately after them: "senza
     * vendere in preda al panico" is not an instruction to sell. Only the verb
     * directly governed is neutralised, so "Vendi SWDA senza fretta" keeps its
     * directive.
     *
     * The nouns in the second group do the same thing by nominalising the verb
     * into the thing being discussed rather than the thing being asked for:
     * "riduce la necessità di vendere investimenti in caso di emergenza" is a
     * statement about what an emergency fund is for. The live probe found that
     * sentence redacted, which is the explanation of the product's own advice
     * about emergency funds.
     */
    private const string INVERTED_DIRECTIVE = '/\b(?:'
        .'senza|invece\s+di|anziché|anzichè|piuttosto\s+che|evitando\s+di'
        .'|(?:la\s+|l\'|una\s+)?(?:necessità|esigenza|tentazione|urgenza|voglia|fretta)\s+di'
        .'|(?:il\s+|un\s+)?(?:bisogno|obbligo|rischio|timore|dovere)\s+di'
        .'|(?:la\s+|una\s+)?(?:paura|possibilità|libertà|facoltà)\s+di'
        .')\s+(?:dover\s+)?$/iu';

    /** How far back from a directive the INVERTED_DIRECTIVE frame is looked for. */
    private const int INVERSION_WINDOW = 24;

    /**
     * The directive verb aimed at an abstraction instead of at a position:
     * "la diversificazione serve a ridurre il rischio", "aumentando il rischio
     * del tuo portafoglio". Nothing is being bought or sold there — it is the
     * sentence every explanation of risk is made of.
     *
     * Found by running the filter over real model replies rather than over
     * hand-written phrases, which is the method WEA-14 showed to be the only
     * one that surfaces this class: a short invented sentence does not name a
     * position and then talk about risk in the same breath, and a real one
     * does it on every other line.
     *
     * `concentrazione`, `allocazione`, `esposizione` and `peso` are pointedly
     * NOT here: "ridurre la concentrazione in VWCE" is the recommendation.
     */
    private const string ABSTRACT_OBJECT = '/^\W*(?:(?:ulteriormente|molto|notevolmente|sensibilmente|leggermente|troppo)\s+)?'
        .'(?:(?:il|lo|la|i|gli|le|un|uno|una|dei|delle|degli|l\')\s*)?'
        .'(?:rischi[oi]|volatilit[àa]|cost[oi]|spes[ae]|commission[ei]|impost[ae]|tass[ae]'
        .'|incertezz[ae]|complessit[àa]|probabilit[àa]|dubbi|stress|ansia|drawdown|impatt[oi]'
        .'|oscillazion[ei]|fluttuazion[ei]|sensibilit[àa]|margine\s+di\s+errore)\b/iu';

    /** How far after a directive its object is looked for. */
    private const int OBJECT_WINDOW = 40;

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
    private const int LOOKBACK = 5;

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
        //
        // A full stop followed by a digit is not a boundary either: Italian
        // groups thousands with it, so `Porta VWCE a 5.000€` was being cut
        // into `Porta VWCE a 5` and `000€` — and the amount, which is the
        // whole signal of a move stated in euro, ended up in a segment of its
        // own where nothing could see it.
        $parts = preg_split('/((?:\.(?!\d)|[!?;\n])+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false) {
            return [self::REFUSAL, 1, 0];
        }

        /** @var list<array{index: int, names: bool, header: bool, question: bool}> $segments */
        $segments = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0 && trim($part) !== '') {
                $segments[] = [
                    'index' => $index,
                    'names' => $this->mentionsInstrument($part),
                    'header' => preg_match(self::TABLE_TARGET_HEADER, $part) === 1,
                    // The split consumed the terminator, so whether this
                    // segment is a question is recorded from the delimiter
                    // that follows it rather than from the segment itself.
                    'question' => str_contains($parts[$index + 1] ?? '', '?'),
                ];
            }
        }

        $redacted = 0;
        $kept = 0;

        foreach ($segments as $ordinal => $segment) {
            $part = $parts[$segment['index']];
            $notational = $this->notationalDirectiveOffsets($part);
            $operative = $this->operativeDirectiveOffsets($part);
            $directives = array_merge($operative, $this->judgementDirectiveOffsets($part), $notational);
            sort($directives);

            $tabled = $this->isTargetTableRow($segments, $ordinal, $part);

            if ($directives === [] && ! $tabled) {
                $kept++;

                continue;
            }

            // A question earns the interrogative licence only when it asks
            // about a judgement: no operative verb, no proposed number, and
            // not an assertion with a tag bolted on. "Vendi VWCE?" and "Porta
            // VWCE al 25%?" are instructions whose punctuation changes nothing.
            $asking = $segment['question']
                && $notational === []
                && $operative === []
                && preg_match(self::TAG_QUESTION, $part) !== 1;

            // A table row is never a refusal or a reflection question, so the
            // protected-speech gate does not apply to it.
            if (! $tabled && $this->isProtectedSpeech($part, $directives, $asking)) {
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
     * Whether this segment is a row of a proposed-allocation table: a
     * markdown row carrying an instrument and at least two numbers, under a
     * header that announced a target. The rows carry the instruction and no
     * verb at all, so nothing else in the filter can see them; the header is
     * what separates them from an ordinary portfolio table.
     *
     * @param  list<array{index: int, names: bool, header: bool}>  $segments
     */
    private function isTargetTableRow(array $segments, int $ordinal, string $part): bool
    {
        if (preg_match(self::TABLE_ROW, $part) !== 1) {
            return false;
        }

        if (preg_match_all('/'.self::QUANTITY.'/u', $part) < 2) {
            return false;
        }

        for ($back = 1; $back <= self::LOOKBACK; $back++) {
            if (($segments[$ordinal - $back]['header'] ?? false) === true) {
                return true;
            }
        }

        return false;
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

    /**
     * The directives that name an operation — buy, sell, reduce, switch — as
     * byte offsets into the segment. Offsets rather than a boolean because
     * isProtectedSpeech() has to know *where* each directive sits relative to
     * a refusal or a reflection frame: a directive before the refusal that is
     * supposed to excuse it is not excused by it.
     *
     * A directive immediately governed by an INVERTED_DIRECTIVE frame is
     * dropped here rather than reported and then forgiven — "senza vendere in
     * preda al panico" carries no directive at all.
     *
     * @return list<int>
     */
    private function operativeDirectiveOffsets(string $segment): array
    {
        return $this->offsetsOf($segment, [
            self::HARD_DIRECTIVES,
            self::PASSIVE_DIRECTIVES,
            self::NOMINAL_DIRECTIVES,
            self::FOREIGN_DIRECTIVES,
        ]);
    }

    /**
     * The directives that state a judgement rather than an operation: what
     * would suit the user, what they ought to do. Split from the operative
     * ones because only these can be licensed by the question mark — "la tua
     * allocazione riflette la tua tolleranza?" asks about fit, and asking is
     * what the clauses want, whereas "vendi VWCE?" is an instruction whose
     * punctuation changes nothing.
     *
     * @return list<int>
     */
    private function judgementDirectiveOffsets(string $segment): array
    {
        return $this->offsetsOf($segment, [
            self::SOFT_DIRECTIVES,
            self::SUITABILITY,
        ]);
    }

    /**
     * The directives whose only signal is a number: a target weight, a
     * transition, a move in euro. Scanned separately from the lexical ones
     * because the interrogative licence turns on the distinction — a question
     * that proposes a specific number is a proposal, and one that does not is
     * the reflection question the clauses ask for.
     *
     * Empty whenever the segment is reporting (DESCRIPTIVE) or supposing
     * (HYPOTHETICAL): both are the innocent reading of a bare pair of numbers,
     * which is all this family has to go on.
     *
     * @return list<int>
     */
    private function notationalDirectiveOffsets(string $segment): array
    {
        if ($this->isDescriptive($segment) || preg_match(self::HYPOTHETICAL, $segment) === 1) {
            return [];
        }

        $patterns = [
            self::NOTATION_ARROW,
            self::NOTATION_RANGE,
            self::NOTATION_CAP,
            self::NOTATION_MOVE,
            self::NOTATION_DELTA,
            self::NOTATION_MORE_LESS,
        ];

        // A target weight only means something next to the weight itself,
        // and only as a percentage: the app talks about a goal in euro.
        if (preg_match('/\d{1,3}(?:[.,]\d+)?\s*%/u', $segment) === 1) {
            $patterns[] = self::NOTATION_TARGET;
        }

        return $this->offsetsOf($segment, $patterns);
    }

    /**
     * @param  list<string>  $patterns
     * @return list<int>
     */
    private function offsetsOf(string $segment, array $patterns): array
    {
        $offsets = [];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $segment, $matches, PREG_OFFSET_CAPTURE) < 1) {
                continue;
            }

            /** @var list<array{0: string, 1: int}> $found */
            $found = $matches[0];

            foreach ($found as [$text, $offset]) {
                if ($this->isInverted($segment, $offset) || $this->hasAbstractObject($segment, $offset + strlen($text))) {
                    continue;
                }

                $offsets[] = $offset;
            }
        }

        sort($offsets);

        return $offsets;
    }

    /**
     * Whether a segment is reporting rather than prescribing. Only consulted
     * for the notational family, whose signal is a bare number and therefore
     * the one that cannot tell "porta VWCE a 5.000€" from "hai versato 5.000€
     * su VWCE" on its own.
     */
    private function isDescriptive(string $segment): bool
    {
        return preg_match(self::DESCRIPTIVE, $segment) === 1;
    }

    /** Whether the directive at this offset is governed by "senza" / "invece di". */
    private function isInverted(string $segment, int $offset): bool
    {
        $start = max(0, $offset - self::INVERSION_WINDOW);

        return preg_match(self::INVERTED_DIRECTIVE, substr($segment, $start, $offset - $start)) === 1;
    }

    /** Whether the directive ending at this offset acts on an abstraction, not a position. */
    private function hasAbstractObject(string $segment, int $end): bool
    {
        return preg_match(self::ABSTRACT_OBJECT, substr($segment, $end, self::OBJECT_WINDOW)) === 1;
    }

    /**
     * Whether this segment is speech the clauses *require* or permit — a
     * refusal, the MiFID disclaimer, the Art. 50(1) AI Act declaration, or a
     * reflection question — in which case the directives it carries are its
     * subject matter rather than its content, and removing it would delete the
     * compliance rather than enforce it.
     *
     * Four ways the licence is withheld, which is what keeps this gate from
     * being the way through the filter:
     *
     * 1. A directive that starts *before* the refusal frame is not covered by
     *    it ("Vendi VWCE, dato che non posso dirti altro").
     * 2. An ADVERSATIVE after the frame ends the licence, which is the whole
     *    refuse-in-form-advise-in-substance family from the WEA-10 harness.
     * 3. A directive sitting immediately after a reflection frame is that
     *    frame's object, not a reflection: "valuta se vendere VWCE" is an
     *    instruction wearing a question mark.
     * 4. An interrogative that carries an INTERROGATIVE_PROPOSAL frame, or a
     *    notational directive (checked by the caller, see $asking), is a
     *    proposal rather than a question.
     *
     * @param  list<int>  $directives  offsets of the directives found, ascending
     * @param  bool  $asking  the segment is a question proposing no number
     */
    private function isProtectedSpeech(string $segment, array $directives, bool $asking = false): bool
    {
        $licence = null;

        // A disclaimer qualifies the whole segment: it routinely comes after
        // the act it describes ("indicare cosa comprare costituirebbe
        // consulenza finanziaria"), so it cannot govern forward only.
        if (preg_match(self::DISCLAIMER, $segment) === 1) {
            $licence = 0;
        }

        if (preg_match(self::REFUSAL_FRAME, $segment, $m, PREG_OFFSET_CAPTURE) === 1) {
            $licence = min($licence ?? PHP_INT_MAX, $m[0][1]);
        }

        // The interrogative licence, and the reason the filter stopped eating
        // the reflection questions the clauses ask for. A question asserts
        // nothing: "la tua allocazione riflette la tua tolleranza al rischio?"
        // trips SUITABILITY on the same words as the forbidden claim, and only
        // the question mark tells them apart.
        //
        // Narrow on purpose. The licence is already withheld from a question
        // that names a number (the caller's $asking), and withheld again here
        // from the frames that turn a question into a proposal — so "hai
        // pensato di ridurre Bitcoin?" and "porta VWCE al 25%?" stay blocked.
        if ($asking && preg_match(self::INTERROGATIVE_PROPOSAL, $segment) !== 1) {
            $licence = 0;
        }

        if (preg_match(self::REFLECTION_INDICATIVE, $segment, $m, PREG_OFFSET_CAPTURE) === 1) {
            $licence = min($licence ?? PHP_INT_MAX, $m[0][1]);
        }

        if (preg_match(self::REFLECTION, $segment, $m, PREG_OFFSET_CAPTURE) === 1) {
            $frameEnd = $m[0][1] + strlen($m[0][0]);

            foreach ($directives as $offset) {
                if ($offset >= $frameEnd && $offset - $frameEnd <= self::REFLECTION_OBJECT_WINDOW) {
                    return false;
                }
            }

            $licence = min($licence ?? PHP_INT_MAX, $m[0][1]);
        }

        if ($licence === null) {
            return false;
        }

        if (preg_match(self::ADVERSATIVE, $segment, $m, PREG_OFFSET_CAPTURE) === 1 && $m[0][1] > $licence) {
            return false;
        }

        foreach ($directives as $offset) {
            if ($offset < $licence) {
                return false;
            }
        }

        return true;
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
