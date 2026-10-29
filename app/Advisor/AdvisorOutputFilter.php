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
 *
 * And hence the fourth, GENERIC_OBJECT, which is about the *instrument* half
 * rather than the directive half. LOOKBACK carries a name forward five segments
 * so an anaphor can be resolved, but a real reply names a position on nearly
 * every line, so that condition holds almost always and the co-occurrence rule
 * degenerates into a one-sided one — every directive removed, the instrument
 * half contributing nothing. A segment whose directives already say they act on
 * a class or on the portfolio therefore inherits nothing from its neighbours.
 * That is what keeps the ¶48-50 safe harbour alive in context and not only in
 * isolation, and measuring it needs real replies: on hand-written phrases the
 * lookback is never exercised, so the defect is invisible by construction.
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
        // "Mettere alla prova la pazienza", "mettere in discussione un piano",
        // "mettere da parte" — idioms, not an instruction to place money.
        .'|mett(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'(?!\s+(?:alla\s+prova|in\s+discussione|in\s+conto|in\s+chiaro|a\s+fuoco|da\s+parte))'
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
        // Not a regression of the fourth round — the third review checked, and
        // it passed before it too. Its object is the one idiom that is not a
        // position ("ridimensionare le aspettative"), which ABSTRACT_OBJECT
        // now carries.
        .'|ridimension(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
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
        // The plural of the third-person conditional, allowed only where that
        // is what it is. Every branch above spells the singular `-rebbe` and
        // none of them spelled `-rebbero`, so "molti investitori venderebbero
        // Bitcoin" was not a directive at all — found by attacking the sixth
        // review's own correction, which is what made that subject reachable.
        // THIRD_PERSON carries the same `(?:ro)?`, so the arithmetic reading
        // ("due cali del 30% ridurrebbero il tuo patrimonio") is protected in
        // the plural exactly as in the singular.
        .')(?:(?<=rebbe)ro)?'.self::CLITIC.'\b/iu';

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
        .')\b(?!(?:\W+\w+){0,3}\W+(?:'.self::EPISTEMIC.'))/iu';

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
     * The common noun that denotes a holding rather than a class of them, and
     * so is what a demonstrative points at when it points at one: "quella
     * posizione", "il primo strumento".
     *
     * One list and not two: it is read here, by the ordinal periphrasis, and
     * by pointsBack(), which asks the same question about the noun a
     * demonstrative governs. The fifth review's §6 is why — two lists that
     * have to agree will not agree.
     */
    private const string POSITION_NOUN = 'strument[oi]|posizion[ei]|righ[ae]|rig[ae]|voc[ei]'
        .'|asset|fond[oi]|ETF|titol[oi]|quot[ae]';

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
        .'(?:'.self::POSITION_NOUN.')'
        .'|(?:della|nella|in)\s+tabella\s+(?:qui\s+)?(?:sopra|sotto)'
        .')/iu';

    /**
     * One character of the same sentence. A full stop ends it only when a
     * digit does not follow, because Italian groups thousands with it — the
     * same exception `screen()` makes when it splits. Without it a window
     * stops dead at `107.000€`, which is where every figure in this app sits,
     * and the live probe found the goal trajectory "da 107.000€ a 300.000€ in
     * 14 anni richiede versamenti regolari" redacted for exactly that reason.
     */
    private const string SAME_SENTENCE = '(?:[^.!?;\n]|\.(?=\d))';

    /**
     * A percentage or a money amount, however the model groups and spaces it:
     * `25%`, `0 %`, `5.000€`, `1.234,50 euro`.
     */
    private const string QUANTITY = '(?:'.self::QUANTITY_IN_DIGITS.'|'.self::QUANTITY_SPELLED.')';

    private const string QUANTITY_IN_DIGITS = '\d{1,3}(?:[.\s]\d{3})*(?:[.,]\d+)?\s*(?:%|€|euro\b)';

    /**
     * The same amount written in letters: "al cinque per cento", "a duecento
     * euro al mese", "a metà del portafoglio".
     *
     * The sixth round closed the attribution span against a quantity spelled
     * out and left the rest of the filter reading digits, so "Porta Bitcoin al
     * 5%" was removed and "Porta Bitcoin al cinque per cento" — the same
     * instruction — was not. That was declared rather than fixed at the time,
     * on the ground that wiring it in touches every call site of QUANTITY at
     * once; the seventh review's answer was to complete the numeral class
     * first, which it now is, and then make this one change.
     *
     * The unit is required. A bare numeral is a count of anything —
     * "negli ultimi cinque anni" — and only `per cento`, a currency, or a
     * share-of-the-whole makes it an amount of the portfolio.
     */
    private const string QUANTITY_SPELLED = '(?:'.self::QUANTITY_IN_LETTERS.')'
        .'(?:\h+(?:'.self::QUANTITY_IN_LETTERS.')){0,3}'
        .'\h*(?:per\h*cento|percento|%|€|euro\b'
        .'|(?:di|del|dello|della|dei|degli|delle)\h+'
        .'(?:portafogli[oi]|patrimoni[oi]|capitale|totale|posizion[ei]|investiment\w+))';

    /**
     * The allocation recommendation written as notation rather than as a
     * sentence: a weight, a transition operator, another weight. All six
     * phrases in the WEA-14 harness's first family are this shape, and none of
     * them carries a verb — which is why no amount of widening the word lists
     * reached them. `VWCE: 40% → 25%` is an instruction with nothing to list.
     *
     * Whatever the units, the arrow is a move. Two previous rounds tried to
     * keep a reading for the restatement — `62.000€ → 58%` is the same money
     * said twice, which is how the model writes a bullet list of the current
     * allocation, and the live probe found five such lines deleted from one
     * reply. First the units were made to decide, which let through
     * `18.000€ → 5%`; then the restatement was granted against an explicit
     * share-of-whole, and the fourth review walked straight out through it:
     * `Bitcoin: 18.000€ → 5% del portafoglio` is a target weight that happens
     * to name the whole, and the premise that a target would not name it is
     * false — the model writes `→ **58%** del patrimonio investibile` for the
     * restatement, so it writes it for the target too.
     *
     * Telling them apart is arithmetic, not lexical: `62.000€ → 58%` restates
     * because 58% *is* that position's weight. The filter has the weights, so
     * that check is possible and is the right fix; it is also more than a
     * pattern. Until it exists the ambiguity is resolved the other way, on the
     * review's instruction: every arrow is a move, and the cost is the
     * occasional current-allocation bullet removed. Of the two errors that is
     * the one to make here — over-blocking a list line is product damage,
     * letting a target weight through is the exposure the layer exists for.
     *
     * A label on the left needs no unit to agree with, so `VWCE → 25%` stays
     * notation.
     */
    private const string ARROW = '\s*(?:→|➔|➜|⇒|=>|-+>|~>)\s*';

    /**
     * A name for the destination of the arrow: `16% → obiettivo 5%`,
     * `18.000€ → target 5%`. The arrow is the transition operator, so whatever
     * sits between it and the destination quantity labels that destination.
     * Bounded by length rather than by a word list on purpose — a list of the
     * labels seen so far is the per-instance fix the fourth review named as
     * the thread running through every reopening.
     */
    private const string ARROW_LABEL = '(?:\p{L}{2,12}\s+){0,2}';

    private const string PERCENTAGE = '\d{1,3}(?:[.\s]\d{3})*(?:[.,]\d+)?\s*%';

    private const string MONEY = '\d{1,3}(?:[.\s]\d{3})*(?:[.,]\d+)?\s*(?:€|euro\b)';

    private const string NOTATION_ARROW = '/(?:'
        .self::PERCENTAGE.self::ARROW.self::ARROW_LABEL.self::PERCENTAGE
        .'|'.self::MONEY.self::ARROW.self::ARROW_LABEL.self::MONEY
        .'|'.self::MONEY.self::ARROW.self::ARROW_LABEL.self::PERCENTAGE
        .'|'.self::PERCENTAGE.self::ARROW.self::ARROW_LABEL.self::MONEY
        .'|[\p{L}\p{N}]{2,}'.self::ARROW.self::ARROW_LABEL.self::QUANTITY
        .')/u';

    /**
     * The same move written as a span rather than as an arrow: "Bitcoin da
     * 15% a 5% entro giugno". Suppressed by DESCRIPTIVE, which is what the
     * reporting reading of the same words looks like ("è passato dal 40% al
     * 25% negli ultimi sei mesi").
     */
    private const string NOTATION_RANGE = '/\bda(?:l|llo|lla|ll\')?\s+'.self::QUANTITY.'\s+a(?:l|llo|lla|ll\')?\s+'.self::QUANTITY.'/iu';

    /**
     * What a price does, as an infinitive. Used by HYPOTHETICAL to recognise a
     * scenario built on a verb rather than on a subjunctive — the subject of
     * these is always the money, never the user.
     */
    private const string MARKET_MOTION = '(?:scender|salir|perder|guadagnar|crollar|dimezzars'
        .'|raddoppiar|aumentar|calar|andar|arrivar|toccar)e';

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
        // Holding *at a weight* is a proposed weight: "Mantieni VWCE al 25%"
        // is the same instruction as "Porta VWCE al 25%", and without this the
        // interrogative licence for staying put would hand it the question
        // mark as a way through. Composure is unaffected — "mantenere la
        // calma … se perdesse il 50%" is dropped by ABSTRACT_OBJECT first.
        .'|mantien[ia]|manten(?:ere?|endo|iamo|ete)|manterr(?:ei|esti|ebbe)'
        // Same exclusion as HARD_DIRECTIVES: "tieni presente che un singolo
        // strumento supera il 50%" is the advisor's own idiom, and without
        // this it reads as an instruction to hold at 50%.
        .'|(?:tien[ia]|ten(?:ere|endo))(?!\s+(?:present|cont|d\'occhio|a\s+mente))|terr(?:ei|esti|ebbe)'
        .'|lasc(?:ia|i|iare?|iando|erei|eresti|erebbe|iamo|iate)'
        .'|vers(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|destin(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|ferm(?:a|i|ati|arsi|ando|erei|eresti|erebbe|atevi)'
        .'|scend(?:i|ere?|endo|erei|eresti|erebbe|iamo|ete)'
        .'|sal(?:i|ire?|endo|irei|iresti|irebbe|iamo|ite)'
        .'|limit(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .'|alloc(?:a|hi|are?|ando|herei|heresti|herebbe|hiamo|ate)'
        .'|concentr(?:a|i|are?|ando|erei|eresti|erebbe|iamo|ate)'
        .')'.self::CLITIC.'\b(?:(?!per\s+raggiunger|obiettivo|traguardo)'.self::SAME_SENTENCE.'){0,60}?'.self::QUANTITY.'/iu';

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
        // The same supposition built on an infinitive instead of a subjunctive:
        // "Se l'idea di vedere il valore di Bitcoin scendere del 50% ti causa
        // ansia". Same stems, so this widens the shape and not the vocabulary;
        // the subject in between is why the fixed three-word window above
        // cannot reach it.
        .'|\bse\s+[^,;:]{0,80}?\b'.self::MARKET_MOTION.'\b'
        // The motion watched rather than made: "quale percentuale del tuo
        // patrimonio saresti disposto a vedere scendere del 50%". What falls
        // is the money, not something the user is being told to do, so the
        // pair of numbers is a scenario and not a proposed move.
        .'|\b(?:vedere|veder[mt]i|assistere\s+a|guardare)\s+[^,;:]{0,40}?\b'.self::MARKET_MOTION.'\b'
        // The counterfactual past: "Se avessi definito in anticipo una
        // percentuale target…" supposes a plan that was never made.
        .'|\bse\s+(?:\w+\s+){0,2}(?:avess[ie]|foss[ie]|dovess[ie])\b'
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
     * The refusal stated as attribution rather than as inability: "la decisione
     * su *cosa* comprare o vendere è tua". The clauses ask for exactly this —
     * hand the decision back — and the shape names the operations it hands
     * back, so it carries directive verbs by necessity, like every other
     * refusal. Filtered on its own it survives, because it names no
     * instrument; in a real reply the lookback supplies one from a line or two
     * earlier and it is deleted. That is the WEA-14 finding arriving on a
     * sentence nobody had written down.
     *
     * Deliberately *not* in REFUSAL_FRAME, and the difference matters. A
     * refusal governs forward — everything after "non posso" is its subject
     * matter — but an attribution does not: "la scelta è tua, io venderei
     * VWCE" is advice with a preamble. So this licence covers only the
     * directives inside its own span. See isProtectedSpeech().
     *
     * The span is what the fourth review attacked, and a bounded span is still
     * a licence: everything *inside* it was covered, and eighty characters of
     * `[^.;:!?]` hold an aside comfortably. "La decisione su quanto vendere di
     * Bitcoin (io direi il 40%) resta tua" is refuse-in-form-advise-in-
     * substance with the advice parked in a parenthesis — fourteen such shapes
     * passed, every one of them removed by the commit before.
     *
     * The round after that answered by excluding what an aside is made of: the
     * punctuation, then the apposition markers and the first person by word.
     * The fifth review walked out of it in eleven more shapes, every one of
     * them the synonym of a listed word — `ad esempio` was excluded and `per
     * esempio` was not, `io` was and `personalmente` was not — and that is not
     * a list that was too short. A list of what is forbidden is open by
     * enumeration; the next synonym is always outside it.
     *
     * So the span says what it may *contain* instead. The argument for it was
     * already written here and is simply applied now: a legitimate attribution
     * names *operations* — which is what the filter's own refusal text does
     * ("indicarti se comprare, vendere o quanto detenere") — so the span is
     * interrogatives, conjunctions, operation verbs, and prepositional
     * phrases, and nothing else reaches the closing "è tua". Every word class
     * in it is closed in the language rather than closed by somebody's list,
     * which is what makes the whole span closed by construction: `magari`,
     * `al posto tuo`, `(io direi il 40%)` and `fino al cinque per cento` are
     * out because they are not any of those things, not because they were
     * foreseen.
     *
     * `di` as the opening preposition stays out, because "la decisione di
     * ridurre Bitcoin al 5%" is not an attribution, it is the recommendation
     * wearing one as a hat.
     *
     * The cost is declared rather than discovered: an attribution whose
     * operation takes a bare object ("la decisione su se vendere Bitcoin è
     * tua") no longer holds the licence, because a bare noun phrase is how
     * "ovvero venderne una parte" is spelled too. See
     * attributionFalsePositivesNowAccepted().
     */
    private const string DECISION_IS_YOURS = '/\b(?:la|ogni|l\')\s*(?:decisione|scelta|ultima\s+parola)'
        .'\h+(?:finale\h+)?(?:su|sul|sulla|circa|riguardo\h+a)'
        // The object of that preposition, which the preposition licenses the
        // same way one inside the span licenses its own.
        .'(?:\h+'.self::ATTRIBUTION_NOMINAL.')?'
        .'(?:\h+(?:'.self::ATTRIBUTION_TOKEN.'))*'
        // The operation's own object, bare, and only here: last before the
        // closing. See ATTRIBUTION_BARE_OBJECT.
        .'(?:\h+'.self::ATTRIBUTION_OPERATION.'\h+'.self::ATTRIBUTION_BARE_OBJECT.')?'
        .'\h+(?:è|resta|rimane|spetta)\h+(?:sempre\h+|comunque\h+|soltanto\h+|solo\h+)?(?:tua|a\h+te)\b/iu';

    /** The interrogative an attribution hands the decision over as. */
    private const string ATTRIBUTION_INTERROGATIVE = 'che\h+cosa|cosa|quant[aeio]|qual[ei]|come|se|quando|dove|che';

    /**
     * Operation verbs, as infinitives with or without a clitic: this is what
     * an attribution may name.
     *
     * This is the one of the span's four word classes that the language does
     * not close, and the sixth review showed what that costs. HARD_DIRECTIVES
     * recognises operations this list did not — `tagliare`, `sostituire`,
     * `dimezzare` — so "la decisione su cosa tagliare di Bitcoin resta tua" was
     * a directive inside an attribution the licence no longer covered, and it
     * was redacted. Silently, because nothing was bypassed and nobody was
     * looking at the conforming side. Two lists that must agree will not on
     * their own, so test_every_hard_directive_stem_can_be_attributed() makes
     * the drift fail the gate the moment a verb is added to one and not the
     * other.
     */
    private const string ATTRIBUTION_OPERATION = '(?:(?:ri)?comprar|(?:ri)?acquistar|(?:ri)?vender'
        .'|liquidar|disinvestir'
        .'|investir|reinvestir|tener|detener|mantener|conservar|ridurr|ridur|aumentar|incrementar'
        .'|diminuir|alleggerir|rafforzar|spostar|trasferir|convertir|ribilanciar|riequilibrar'
        .'|bilanciar|allocar|destinar|versar|accantonar|risparmiar|diversificar|concentrar'
        .'|far|muover|proceder|agir|modificar|cambiar|riveder|chiuder|aprir|entrar|uscir'
        .'|attender|aspettar'
        // The stems HARD_DIRECTIVES spells and this list did not.
        .'|sostituir|optar|puntar|tagliar|evitar|scaricar|scommetter|raddoppiar|dimezzar'
        .'|azzerar|monetizzar|metter|preferir|eliminar|abbandonar|congelar|ridimensionar'
        .'|smetter|toglier|approfittar|lasciar|star'
        .')(?:e|ne|ti|si|mi|ci|vi|l[aeio]|sene|sel[ao])';

    /**
     * A prepositional phrase: what the operation acts on, named through a
     * preposition rather than bare. The first-person possessives are
     * deliberately absent from the determiners — an attribution hands the
     * decision to *you*, so it never needs "a mio avviso" to do it.
     */
    private const string ATTRIBUTION_PHRASE = '(?:(?:di|a|da|in|con|su|per|tra|fra|verso|circa|entro|presso'
        .'|del|dello|della|dei|degli|delle|al|allo|alla|ai|agli|alle|dal|dallo|dalla|dai|dagli|dalle'
        .'|nel|nello|nella|nei|negli|nelle|sul|sullo|sulla|sui|sugli|sulle|col|coi)\h+'
        .'|(?:d|dell|all|dall|nell|sull)\'\h*)'
        .'(?:(?:il|lo|la|i|gli|le|un|uno|una|quest[aeio]|quell[aeio]|quei|quegli|tu[ao]|tu[oe]i|tue'
        .'|ogni|ciascun[ao]?|altr[aeio]|stess[ao])\h+|(?:un|l|quell|dell)\'\h*)?'
        .self::ATTRIBUTION_NOMINAL;

    /**
     * A name, with no digit in it and no number spelled out in it: a quantity
     * in an attribution is a recommendation.
     *
     * The digits were barred from the start and the letters were not, and the
     * sixth review walked the whole family out through that gap: "la decisione
     * su quanto ridurre di Bitcoin al cinque per cento resta tua" is the same
     * recommendation as "al 5%", and `cinque` is `\p{L}+`. The one case the
     * gate held was held by `fino`, which is outside the span for reasons that
     * have nothing to do with numbers — a closed list of instances passing is
     * not the family being closed.
     *
     * Italian numerals *are* a closed class, so this closes by construction
     * rather than by enumeration of what anybody happened to send: the
     * cardinals including their compounds, the fractions, and the month names,
     * which are how a date is written without a digit ("entro dicembre" is
     * market timing, which the clauses forbid on its own).
     */
    private const string ATTRIBUTION_NOMINAL = '(?!(?:'.self::NOT_A_NAME.')\b)\p{L}[\p{L}\'’]*';

    /**
     * What an attribution may not take as the name of a thing: a quantity, or
     * a date. Both are closed classes, and both are a recommendation when they
     * stand where a name should — "al cinque per cento" is an amount and
     * "entro venerdì" is market timing.
     */
    private const string NOT_A_NAME = self::QUANTITY_IN_LETTERS.'|'.self::DATE_IN_LETTERS;

    /**
     * The Italian numerals spelled out, generated from the morphology rather
     * than listed.
     *
     * The seventh review is why. The round before wrote the compounds as a
     * tens stem plus a unit, which produces `ventiuno` and `ventitre` — two
     * spellings Italian does not use. The language elides the tens vowel in
     * front of `uno` and `otto` (`ventuno`, `ventotto`, `quarantotto`) and
     * accents the `tre` of a compound (`ventitré`). That is 24 of the 72
     * compounds between 21 and 99 absent by construction, in the constant
     * whose docblock claimed to close the class by construction — and the
     * provider that was supposed to hold it listed `ventuno` and passed
     * anyway, because every one of its sentences carried `per cento` and
     * `cento` is itself in the class. A provider derived from the constant it
     * tests cannot fail for a missing member; see the inertness check in
     * AdvisorOutputFilterTest.
     *
     * So the rule is written instead of the results: the stem takes either its
     * own vowel with an optional unit behind it, or `uno`/`otto` directly.
     */
    private const string QUANTITY_IN_LETTERS = 'zero|un[oa]?|due|tr[eé]|quattro|cinque|sei|sette|otto|nove'
        .'|dieci|undici|dodici|tredici|quattordici|quindici|sedici|diciassette|diciotto|diciannove'
        .'|(?:vent|trent|quarant|cinquant|sessant|settant|ottant|novant)'
        .'(?:[ia](?:due|tr[eé]|quattro|cinque|sei|sette|nove)?|uno|otto)'
        // The multiplied magnitudes: `duecento`, `ventimila`. Written as the
        // multiplier plus the magnitude, for the same reason as above.
        .'|(?:(?:due|tre|quattro|cinque|sei|sette|otto|nove|dieci'
        .'|vent|trent|quarant|cinquant|sessant|settant|ottant|novant)[i]?)?(?:cent[oi]|mila)'
        .'|mille|milion[ei]|miliard[oi]|percento'
        // The fractions and the ordinals. `-esimo` is the productive ordinal
        // suffix, so it covers every ordinal above ten without a list.
        .'|mezz[aeio]|metà|terz[aeio]|quart[aeio]|quint[aeio]|sest[aeio]|settim[aeio]|ottav[aeio]'
        .'|non[aeio]|decim[aeio]|prim[aeio]|second[aeio]|ultim[aeio]|penultim[aeio]'
        .'|\p{L}+esim[aeio]'
        // The collectives, which are a quantity said as a noun.
        .'|dozzin[ae]|decin[ae]|centinai[ao]|migliai[ao]|paio|coppia';

    /**
     * The dates spelled out without a digit — months, weekdays, the relative
     * days, the parts of the day, the seasons and the fixed holidays.
     *
     * The months were here first, on the argument that "entro dicembre" is
     * market timing, which the clauses forbid on its own. The seventh review
     * made the point that the argument is about *dates* and the list was about
     * months: a bare time complement takes no determiner in Italian, which is
     * exactly why it slips into the one slot the attribution span leaves open
     * for a bare noun, and nine of them walked through it.
     */
    private const string DATE_IN_LETTERS = 'gennaio|febbraio|marzo|aprile|maggio|giugno|luglio'
        .'|agosto|settembre|ottobre|novembre|dicembre'
        .'|luned[ìi]|marted[ìi]|mercoled[ìi]|gioved[ìi]|venerd[ìi]|sabato|domenica'
        .'|oggi|domani|dopodomani|ieri|stasera|stamattina|stanotte|stamane'
        .'|mattina|mattino|pomeriggio|sera|serata|notte|mezzogiorno|mezzanotte'
        .'|primavera|estate|autunno|inverno|weekend'
        .'|natale|capodanno|ferragosto|pasqua|epifania|immacolata|ognissanti';

    /**
     * The one bare noun an operation or an interrogative may take as its
     * object, and the sixth review is why it is here.
     *
     * The round before withheld it, on the ground that a bare noun phrase is
     * how "ovvero venderne una parte" is spelled too — and so lost "la
     * decisione su se vendere Bitcoin è tua", which is the handover sentence
     * the clauses ask the advisor to write. That cost does not have to be
     * paid: what tells an object from an apposition is the determiner, and
     * `una parte` has one while `Bitcoin` does not. So one bare noun is
     * allowed immediately after the interrogative or the operation that takes
     * it, and only if it is not a determiner.
     *
     * One, not a run of them: the second noun of a run is the apposition.
     * That is what the two places it is admitted are for, and they are the two
     * places a bare noun is grammatically required rather than merely
     * possible. After an interrogative *determiner* it is that determiner's
     * own head — "quali strumenti", "quanta liquidità" — so the run ends
     * there. After an operation it is the operation's object, and it is
     * admitted only as the last thing before the closing: an object completes
     * its verb, so anything following it is an apposition and not an object.
     * That single restriction is what keeps "vendere magari Bitcoin" and
     * "fare subito vendere di Bitcoin" out, without a list of the adverbs
     * anybody has thought of.
     */
    private const string ATTRIBUTION_BARE_OBJECT = '(?!(?:'.self::DETERMINER.')\b)'.self::ATTRIBUTION_NOMINAL;

    /** The interrogatives that are determiners, and so take a noun of their own. */
    private const string ATTRIBUTION_INTERROGATIVE_DETERMINER = 'qual[ei]|quant[aeio]|che';

    private const string ATTRIBUTION_TOKEN = '(?:'.self::ATTRIBUTION_INTERROGATIVE_DETERMINER.')'
        .'\h+'.self::ATTRIBUTION_BARE_OBJECT
        .'|'.self::ATTRIBUTION_INTERROGATIVE
        .'|e|ed|o|od|oppure|né|ne'
        .'|'.self::ATTRIBUTION_OPERATION
        .'|'.self::ATTRIBUTION_PHRASE;

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
        .'valuta\s+se|verifica\s+se|controlla\s+se|considera\s+se|rifletti\s+su|ragiona\s+su'
        // The same frame built with a modal — "dovresti valutare se",
        // "potresti verificare se". It belongs here and not among the
        // indicative frames precisely so that the object window still applies:
        // "dovresti valutare se vendere VWCE" must stay an instruction.
        //
        // The verb list is EPISTEMIC_WINDOWED rather than a list written out
        // beside it, and the fourth review is why. The third round answered
        // "vale la pena sapere se vendere Bitcoin" by adding `sapere` here;
        // the licence it was escaping through comes from EPISTEMIC, so five
        // other stems in that list — `approfondire`, `confrontare`,
        // `osservare`, `monitorare`, `leggere` — escaped the window exactly
        // the same way. Two lists that must agree will not; one list split
        // once, on a stated reason, will.
        .'|(?:dovrest[ei]|potrest[ei]|puoi|prova\s+a|vale\s+la\s+pena(?:\s+di)?)\s+'
        .'(?:'.self::EPISTEMIC_WINDOWED.')\w*\s+se'
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
     *
     * Measured over the gap with an aside dropped, for the reason the
     * event-subject window is: the fifth review noted this window was left in
     * the per-instance form its neighbour had just been taken out of, and
     * "valuta se, viste le valutazioni correnti, vendere Bitcoin adesso" is
     * twelve characters of inciso away from being protected. See
     * reflectionDistance().
     */
    private const int REFLECTION_OBJECT_WINDOW = 12;

    /** An aside, by each of the three ways it is punctuated. See reflectionDistance(). */
    private const string ASIDE = '(?:,[^,()]*,|\([^()]*\)|—[^—]*—|–[^–]*–)';

    /**
     * Epistemic verbs whose object is something to find out — the things the
     * clauses ask the advisor to tell the user to do ("dire che cosa l'utente
     * dovrebbe VERIFICARE o CONTROLLARE"). "Vale la pena verificare se vendere
     * Bitcoin" names an operation to perform, not a thing to find out, so
     * these are the stems REFLECTION puts an object window on.
     */
    private const string EPISTEMIC_WINDOWED = 'verificar|controllar|valutar|considerar|capir|saper|confrontar'
        .'|monitorar|legger|informart|documentart|parlar|osservar|guardar'
        // Only the idiom. Bare `tener` cleared "ti conviene tenere VWCE" as
        // though it were "tenere presente", which turned a hold recommendation
        // into no directive at all.
        .'|ricordar|tener\s+(?:present|cont|d\'occhio|a\s+mente)|aggiornar|approfondir|rivedere i dati';

    /**
     * The reflexive half of the same family — "chiedersi se", "domandarsi se".
     * The clauses name it among the things the advisor SHOULD do, and what is
     * asked is a proposition, so the directive inside it is the proposition's
     * verb: exactly what an object window would reject. Hence the split, and
     * hence REFLECTION_INDICATIVE licensing these without one.
     */
    private const string EPISTEMIC_REFLEXIVE = 'chieder|domandar';

    /**
     * Both halves. Shared by SOFT_DIRECTIVES, which clears a soft directive
     * that governs one of them, and by REFLECTION_INDICATIVE, where the same
     * list turns "vale la pena" into a reflection frame rather than a verdict.
     */
    private const string EPISTEMIC = self::EPISTEMIC_WINDOWED.'|'.self::EPISTEMIC_REFLEXIVE;

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
     *
     * The whole *chiedersi* family lives here rather than in REFLECTION. "Dire
     * che cosa l'utente dovrebbe CHIEDERSI" is named in the clauses as a thing
     * the advisor should do, and the question asked is a proposition, so the
     * directive inside it is the proposition's verb — which is precisely what
     * the object window would reject. The window stays on *valutare* /
     * *verificare*, where the object is an operation to perform.
     */
    private const string REFLECTION_INDICATIVE = '/\b(?:'
        .'chiediti|chieditelo|domandati|ti\s+sei\s+(?:mai\s+)?chiest[oa]'
        // The bare infinitive covers every way the advisor introduces it —
        // "dovresti chiederti", "prova a chiederti", "vale la pena chiederti",
        // and `Chiederti se…` opening a bullet.
        .'|chiedert[ei]|domandart[ei]|interrogart[ei]'
        .'|ti\s+chiedi\s+se|ci\s+si\s+chiede\s+se|c\'è\s+da\s+chiedersi'
        // "Vale la pena sapere se mantieni entrambi per una ragione specifica"
        // names something to find out, and the operative verb inside it is the
        // proposition's, not an order — the same reading the *chiedersi* family
        // gets. Restricted to the epistemic verbs the clauses already name, so
        // "vale la pena vendere" is untouched.
        .'|vale\s+la\s+pena\s+(?:di\s+)?(?:'.self::EPISTEMIC.')\w*'
        .'|(?:una|la)\s+domanda\s+da\s+(?:port[ei]|fart[ei]|fars[ei])'
        .'|la\s+domanda\s+(?:che\s+conta|giusta|vera|chiave|è\s+se)'
        .'|ti\s+senti\s+di|te\s+la\s+senti\s+di|sei\s+disposto\s+a|sei\s+disposta\s+a'
        .')\b/iu';

    /**
     * The stress test asked as a question about the user's own nerve:
     * "Potresti mantenere la posizione o avresti la tentazione di vendere?".
     * `potresti` is left out of REFLECTION because on its own it proposes as
     * often as it asks — "potresti vendere VWCE?" — so the frame is granted
     * only for verbs that name *staying put* and only in a real interrogative.
     * Holding on is the one move that needs no instruction, which is what makes
     * the question safe to ask and the assertion still worth removing.
     *
     * The live probe found the whole question deleted, and the clauses ask the
     * advisor to pose it.
     */
    private const string CAPACITY_QUESTION = '/\b(?:potrest[ei]|riuscirest[ei]\s+a|sarest[ei]\s+in\s+grado\s+di|te\s+la\s+sentirest[ei]\s+di)'
        .'\s+(?:'.self::HOLDING_VERB.')\w*/iu';

    /**
     * Staying put: keeping a position, waiting, sitting through a drawdown.
     *
     * These are the one class of directive that proposes nothing when it is
     * asked rather than asserted, because doing nothing needs no instruction —
     * so in a real interrogative they do not withhold the licence. It is the
     * largest false-positive family the live probe found on fresh samples:
     * "Bitcoin è uno strumento che intendi mantenere per tutto questo
     * periodo?", "Hai una ragione specifica per tenere entrambi?", "Quanto
     * tempo puoi permetterti di aspettare?" are all questions the clauses ask
     * the advisor to pose, and all three were deleted.
     *
     * The hold *recommendation* is untouched: "dovresti mantenere VWCE?" and
     * "ti conviene tenere entrambi?" carry a SOFT_DIRECTIVE, which is not in
     * this class and still blocks the licence on its own.
     */
    private const string HOLDING_VERB = 'manten|mantien|manterr|tener|tien|terr|conserv|sopport'
        .'|regg|resist|rest|riman|continu|conviv|aspett|attend';

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
     * The indirect question, which carries no question mark of its own: the
     * bullets under "ecco cosa verificare" are clauses opening with *se*, and
     * "se la tua allocazione è coerente con il tuo profilo" asserts nothing —
     * it names the thing to check, which the clauses ask the advisor to do.
     * The live probe found one of these redacted on SUITABILITY.
     *
     * Treated exactly like an interrogative, so it earns the licence only on
     * the same terms: judgement words alone, never an operative verb or a
     * proposed number. "Se vuoi, vendi VWCE" is therefore still removed.
     *
     * The bullet is required, not optional, and that is the whole difference
     * between *whether* and *if*. A list item opening with `se` is a thing to
     * check; a sentence opening with `se` is an ordinary conditional with a
     * main clause after the comma, and reading it as a question handed out the
     * licence for free. The live probe caught that: "Se la paura di perdere
     * tutto è forte, potrebbe essere un segnale che la quota di Bitcoin è
     * troppo alta per la tua serenità" is a suitability judgement on a named
     * instrument, and it survived on nothing but its leading `se`.
     */
    private const string INDIRECT_QUESTION = '/^\s*(?:[-*#>•]+|\d+[.)])[\s"\'«“(]*(?:e\s+|oppure\s+)?'
        // The bullet that asks outright but loses its question mark to the
        // list formatting: "- Quanto tempo puoi permetterti di aspettare".
        // Same licence and therefore the same terms — an operative verb or a
        // proposed number still withholds it.
        .'(?:se|quanto|quanta|quanti|quante|quale|quali|come|perché|perche|quando|dove|chi|cosa|che\s+cosa)\b/iu';

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
        // "...per non dover vendere in caso di emergenza" — the point of the
        // emergency fund, stated as what it spares you.
        .'|(?:per\s+)?non\s+dover(?:si)?'
        .'|(?:la\s+|l\'|una\s+)?(?:necessità|esigenza|tentazione|urgenza|voglia|fretta)\s+di'
        .'|(?:il\s+|un\s+)?(?:bisogno|obbligo|rischio|timore|dovere)\s+di'
        .'|(?:la\s+|una\s+)?(?:paura|possibilità|libertà|facoltà)\s+di'
        .')\s+(?:dover\s+)?$/iu';

    /** How far back from a directive the INVERTED_DIRECTIVE frame is looked for. */
    private const int INVERSION_WINDOW = 24;

    /**
     * The directive whose subject is a market event rather than the user:
     * "Un calo del 50% su Bitcoin ridurrebbe il tuo patrimonio totale dell'8%".
     * That is arithmetic about a scenario, and the live probe found two
     * consecutive lines of it deleted from one reply.
     *
     * The event noun has to be the subject, so it is looked for behind the
     * directive and the verb has to be third person — "un calo ridurrebbe" is
     * a consequence, "riduci" after the word *calo* is still an instruction.
     *
     * The window is deliberately short, and the fourth review is why. The
     * third round found the same sentence surviving at 91 characters and
     * removed at 149, the only difference a parenthesis, and the round after
     * answered it by widening the window to 160. That was the wrong
     * instrument: the test behind this pattern checks the *person* of the verb,
     * not who the subject is, so a hundred extra characters is room for a
     * different subject to appear — and the one that appears is a proxy for
     * the user. "Una correzione del mercato è sempre possibile, e nella tua
     * situazione un investitore prudente ridurrebbe la quota di Bitcoin al 5%"
     * is advice in the third person, and `mercato` and `andamento` are
     * ordinary ways to open a sentence. Four such shapes passed at 160 and
     * none at 60.
     *
     * What the apposition needed was not a wider window but to not be counted:
     * isConsequence() drops parenthetical asides before measuring.
     *
     * Dropping them is itself room bought, though, and the same attack lands
     * on it — pad a parenthesis and the proxy subject behind it comes inside
     * sixty characters. So the span is held to one *clause* as well as to one
     * sentence. An event that is the subject of this very verb has nothing of
     * the sort between the two.
     *
     * How the clause is held is the fifth review's correction, and it is a
     * change of kind rather than of length. The round before spelled the
     * boundary as a list of conjunctions, and a list of conjunctions is open:
     * `quindi` was in it and `dunque`, `pertanto` and `allora` were not, the
     * comma was in it and the colon and the dash were not, `chi` was in it and
     * `chiunque` got in by being spelled with the same three letters. Nine
     * shapes walked out that way.
     *
     * The rule that closes them is not a longer list but the one thing every
     * one of them has to do. THIRD_PERSON has already established that the
     * verb is a third-person conditional, so if its subject is not the event
     * then the span has to *contain* that subject — and a subject is a
     * determiner with a noun, or a pronoun. Those are closed word classes in
     * the language, not a semantic family somebody has to keep enumerating.
     * So the span may not cross a bare indefinite determiner or an indefinite
     * pronoun: `un investitore prudente`, `chi è esposto come te`, `chiunque
     * sia esposto`. One introduced by a preposition is the event's own
     * modifier, not a new subject, which is what the lookbehinds let through —
     * "un calo del 30% in un anno" is still arithmetic.
     *
     * The conjunctions stay as a second net, no longer load-bearing.
     *
     * The slot that introduces the event is DETERMINER itself, and the
     * seventh review is why. The round before taught the pattern the plural
     * conditional and then listed five of the determiners that can stand in
     * front of a plural event, so the other fourteen — `quattro`, `alcuni`,
     * `molti`, `parecchi`, `numerosi` — turned fourteen sentences of
     * conforming arithmetic into redactions, in the commit written to undo
     * exactly that harm. Which determiner introduces the event decides
     * nothing: the licence is kept safe by the span test below, which does not
     * look at it. So the slot is the closed class and not a sample of it, and
     * the numerals are there because a count is how an event is usually
     * introduced.
     */
    private const string EVENT_SUBJECT = '/\b(?:'.self::DETERMINER.'|'.self::QUANTITY_IN_LETTERS.'|\d+)\s+'
        .'(?:cal[oi]|croll[oi]|ribass[oi]|rialz[oi]|disces[ae]|salit[ae]|perdit[ae]|guadagn[oi]'
        .'|scenario|recessione|correzione|mercato|inflazione|andamento)\b'
        .'(?<span>(?:[^.!?;:,\n—–]|\.(?=\d)){0,60})$/iu';

    /**
     * What may not stand between the event and its verb. Applied to the span
     * EVENT_SUBJECT captures, rather than repeated inside it: the assertions
     * are long and a bounded repeat expands them sixty times, which PCRE
     * refuses to compile.
     *
     * Read the first branch inside out. The lookbehinds hold when the
     * determiner is *not* introduced by a preposition, so the branch only
     * matches there — "in un anno" and "a chi guarda i grafici" modify the
     * event, "e un investitore" starts a clause of its own. The second branch
     * is the older net, kept for a clause that starts without a new subject
     * and no longer load-bearing.
     *
     * The determiners are the whole closed class and not the half of it the
     * sixth review walked out of, and the way they were walked out of is the
     * lesson: `ciascuno`, `nessuno` and `qualcuno` were listed and their
     * apocopated forms `ciascun`, `nessun`, `qualcun` were not; `ogni` and
     * `qualche` were nobody's idea of a pronoun and are determiners all the
     * same; `tale` and the demonstratives were never there. A class is closed
     * in the language; it is closed in the filter only when the list that
     * spells it out is complete, which is why DETERMINER is enumerated from a
     * grammar and tested as a class rather than as the instances somebody
     * happened to send in.
     *
     * The round before withheld the definite articles on the ground that
     * barring them would take the temporal adjunct with them ("un calo del 30%
     * il prossimo anno ridurrebbe il tuo patrimonio dell'8%"), and deleting
     * that arithmetic is the WEA-14 harm this pattern exists to undo. That was
     * a choice between two options where a third one holds: the thing that
     * tells a proxy subject from a temporal adjunct is not the determiner but
     * the *noun*, and a bare noun phrase modifying an event in Italian is an
     * expression of time. So every determiner is barred except in front of a
     * time noun — and TIME_NOUN is short, lexical, and fails in the right
     * direction, because an omission there costs an arithmetic sentence that
     * is visible rather than letting a recommendation through.
     *
     * Built rather than written out because the prepositions are one
     * lookbehind each. A single `(?<!\b(?:di|in|…))` is a lookbehind whose
     * branches have different lengths, which PCRE2 only accepts from 10.43 —
     * so it compiles on a developer's PHP 8.5 and fails to compile on CI's,
     * where the whole filter then throws rather than mismatching. One
     * assertion per preposition is fixed-length by construction, and deriving
     * them from PREPOSITION keeps it one list.
     */
    private function newClausePattern(): string
    {
        return self::$newClausePattern ??= '/(?:'
            .implode('', array_map(
                static fn (string $preposition): string => '(?<!\b'.$preposition.')',
                explode('|', self::PREPOSITION),
            ))
            .'\s(?:'.self::DETERMINER.')\b(?!'.self::TIME_NOUN_AHEAD.')'
            .'|\s(?:'.self::NEW_CLAUSE_CONJUNCTION.')\s'
            .')/iu';
    }

    /**
     * The older net: a clause that starts without a new subject. Kept, no
     * longer load-bearing.
     */
    private const string NEW_CLAUSE_CONJUNCTION = 'e|ed|ma|però|pero|quindi|perciò|percio'
        .'|dunque|pertanto|allora|mentre|laddove|oppure|ossia|anzi|tuttavia|eppure|invece';

    /**
     * The Italian prepositions, simple, articulated and improper — the class
     * that tells the event's own modifier from a new subject. Articulated, and
     * not only simple, because the half-list cost a conforming sentence the
     * moment the determiners grew: "un calo del 30% sul tuo VWCE ridurrebbe il
     * patrimonio dell'8%" is arithmetic, and `sul` was not `su`.
     *
     * The elided forms are absent on purpose: they are written against the
     * following word with no space, and the space is what this sits in front
     * of.
     */
    private const string PREPOSITION = self::SIMPLE_PREPOSITION
        .'|'.self::ARTICULATED_PREPOSITION
        .'|'.self::IMPROPER_PREPOSITION;

    private const string SIMPLE_PREPOSITION = 'di|a|da|in|con|su|per|tra|fra';

    /**
     * The preposition fused with its article, which is why it is also the left
     * context a pro-form of identity or order needs — "a favore dello stesso"
     * carries the article inside the preposition. See ANAPHOR_AFTER_ARTICLE.
     */
    private const string ARTICULATED_PREPOSITION = 'del|dello|della|dei|degli|delle'
        .'|al|allo|alla|ai|agli|alle|dal|dallo|dalla|dai|dagli|dalle'
        .'|nel|nello|nella|nei|negli|nelle|col|coi|sul|sullo|sulla|sui|sugli|sulle';

    /**
     * The improper prepositions the round before stopped at, which the seventh
     * review measured as fourteen more sentences of conforming arithmetic
     * being deleted. The list is the grammar's — see
     * tests/Feature/Advisor/closed-classes.md.
     */
    private const string IMPROPER_PREPOSITION = 'come|verso|dopo|entro|senza|sotto|sopra'
        .'|contro|durante|presso|oltre'
        .'|mediante|salvo|tranne|eccetto|secondo|circa|lungo|dietro|davanti'
        .'|nonostante|malgrado|attraverso|tramite|rasente|fuorché|fuorche'
        .'|escluso|incluso|compreso|eccettuato|stante|tolto|visto|causa';

    /**
     * The Italian determiners and the indefinite pronouns that stand in for
     * one, as a class rather than as a sample: articles, demonstratives,
     * possessives, indefinites and the interrogatives, each with the
     * apocopated and elided forms the language actually writes. Shared by
     * NEW_CLAUSE, which bars a subject from a span, and by
     * ATTRIBUTION_BARE_OBJECT, which uses the same class to tell an object
     * from an apposition.
     */
    /**
     * The article alone, which is also the left context a pro-form of identity
     * or order needs before it is a pronoun rather than an adverb — see
     * ANAPHOR_AFTER_ARTICLE. Split out of DETERMINER rather than written
     * twice, which is the fifth review's §6.
     */
    private const string ARTICLE = 'il|lo|la|i|gli|le|un|uno|una';

    private const string DETERMINER = self::ARTICLE.'|l\'|un\''
        .'|quest[aeio]|quest\'|codest[aeio]|codest\'|cotest[aeio]|cotest\''
        .'|quell[aeio]|quel|quei|quegli|quell\''
        .'|tale|tali|siffatt[aeio]|medesim[aeio]|stess[aeio]'
        .'|mi[ao]|miei|mie|tu[ao]|tuoi|tue|su[ao]|suoi|sue'
        .'|nostr[aeio]|vostr[aeio]|loro|altrui|propri[aeio]?'
        .'|ogni|ognun[ao]|qualche|ciascun[ao]?|nessun[ao]?|alcun[aeio]?|qualcun[ao]?'
        .'|qualsiasi|qualunque|chiunque|chi|cert[aeio]|tant[aeio]|tropp[aeio]'
        .'|molt[aeio]|poc[ao]|poch[ie]|parecchi[ao]?|parecchie|divers[aeio]|var[ie]|vari[aeo]'
        .'|tutt[aeio]|altr[aeio]|talun[aeio]|quant[aeio]|qual[ei]'
        // The quantifiers in their superlative and their emphatic forms, which
        // are the same determiner said louder and were the seventh review's
        // way out: "moltissimi investitori prudenti venderebbero Bitcoin".
        .'|moltissim[aeio]|pochissim[aeio]|tantissim[aeio]|parecchissim[aeio]'
        .'|svariat[aeio]|numeros[aeio]|innumerevol[ei]';

    /**
     * The nouns that make a bare noun phrase an expression of time rather than
     * a new subject, looked for up to two words past the determiner so that an
     * adjective between the two ("il prossimo anno") does not hide it. See
     * NEW_CLAUSE.
     */
    private const string TIME_NOUN_AHEAD = '\h*(?:\p{L}+\h+){0,2}(?:ann[oi]|mes[ei]|settiman[ae]'
        .'|trimestr[ei]|semestr[ei]|decenni[oi]|bienni[oi]|giorn[oi]|volt[ae]|period[oi]'
        .'|moment[oi]|tempo|futuro|scadenz[ae]|orizzonte)\b';

    /** The third-person forms a consequence is stated in, singular and plural. */
    private const string THIRD_PERSON = '/(?:rebbe|erebbe|irebbe|rrebbe)(?:ro)?$/iu';

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
     *
     * The second group is the object of "mantenere" when it is composure and
     * not a position — "saresti in grado di mantenere la calma senza vendere?"
     * is the stress-test question the clauses ask for, and the live probe
     * found it redacted on the word "mantenere".
     */
    private const string ABSTRACT_OBJECT = '/^\W*(?:(?:'.self::DEGREE_ADVERB.')\s+)?'
        .'(?:(?:il|lo|la|i|gli|le|un|uno|una|dei|delle|degli|l\')\s*)?'
        .'(?:rischi[oi]|volatilit[àa]|cost[oi]|spes[ae]|commission[ei]|impost[ae]|tass[ae]'
        .'|incertezz[ae]|complessit[àa]|probabilit[àa]|dubbi|stress|ansia|drawdown|impatt[oi]'
        .'|oscillazion[ei]|fluttuazion[ei]|sensibilit[àa]|margine\s+di\s+errore'
        .'|tolleranz[ae]|capacit[àa]|propension[ei]|aspettativ[ae]'
        // Composure only. `piano`, `strategia` and `equilibrio` are pointedly
        // NOT here: "apri un piano di accumulo su Amundi" is a recommendation,
        // and the harness carries it.
        .'|calma|sangue\s+freddo|lucidit[àa]|disciplina|nervi|rotta|sonni\s+tranquilli)\b/iu';

    /**
     * How far after a directive its object is looked for, in characters.
     *
     * In characters and not in bytes, which is the sixth review's finding and
     * not a tidying: an accent costs two bytes, so a byte window cut
     * "l'esposizione complessiva alla liquidità" at `liquidit` and the class
     * stopped being recognised a word earlier than the window says. Worse, a
     * cut that lands inside a character produces invalid UTF-8, and every
     * `preg_match` with `/u` over it then returns `false` rather than 0 — so
     * objectKind() read `empty` where it should have read `other`, silently
     * and in whichever direction the caller happened to treat `empty`.
     *
     * Forty was also simply too short, which the same example shows once the
     * bytes stop confusing the count: "l'esposizione complessiva alla
     * liquidità" is forty-two characters, so the window cut the complement
     * that decides whether the head is a class at all. The window has to hold
     * a determiner, a relational head, an adjective, a preposition and the
     * complement — and widening it cannot reach further right than the
     * directive's own object, because every pattern read through here is
     * anchored at the head of it.
     */
    private const int OBJECT_WINDOW = 64;

    /**
     * The adverb that can sit between a directive and its object without
     * changing what the object is: "ridurre *parzialmente* il rischio",
     * "ribilanciare *periodicamente* il portafoglio". Shared by the two
     * object-anchored patterns so that an adverb learned for one is not a
     * blind spot in the other — the live probe found "aiuta a bilanciare
     * parzialmente il rischio" removed on exactly that gap.
     */
    private const string DEGREE_ADVERB = 'ulteriormente|molto|notevolmente|sensibilmente|leggermente'
        .'|troppo|parzialmente|periodicamente|regolarmente|progressivamente|gradualmente'
        .'|eventualmente|subito|significativamente|drasticamente|moderatamente|comunque';

    /**
     * The infinitive used as the subject of the sentence rather than as an
     * order — the Italian *infinito sostantivato*. "Mantenere due ETF azionari
     * globali potrebbe non aggiungere diversificazione, ma aumenta la
     * complessità" says what keeping them costs; it does not say to keep them.
     * The live probe found it redacted on "Mantenere".
     *
     * Two conditions, and both are needed. The infinitive must open its clause
     * (segment start, or straight after a colon, a bullet or a list number),
     * which is the nominal-subject position; and the finite verb that follows
     * must be one that states a property or a consequence. "Vendere Bitcoin
     * potrebbe aiutarti" is not covered — `aiutarti` is a benefit, which is
     * how a recommendation argues for itself.
     */
    private const string NOMINALISED_SUBJECT = '/(?:^|[:•]|^\s*[-*]|\d[.)])\s*$/u';

    /** The finite verb that confirms a nominalised infinitive. */
    private const string NOMINALISED_PREDICATE = '/^(?:\W|\w)*?\b(?:'
        .'significa|comporta|implica|richiede|costa|espone|aggiunge|toglie'
        .'|ha\s+senso|non\s+ha\s+senso|è\s+(?:un|una|il|la|lo)\b'
        .'|potrebbe\s+(?:non\s+)?(?:aggiungere|comportare|implicare|richiedere|costare'
        .'|esporre|ridurre|aumentare|significare|avere)'
        .')\b/iu';

    /** How far after a nominalised infinitive its predicate is looked for. */
    private const int NOMINALISED_WINDOW = 90;

    /**
     * Saving toward the goal, which is the one target figure this app exists
     * to discuss. "Posso simulare quanto dovresti versare mensilmente per
     * raggiungere l'obiettivo" is an offer to run the product's own forecast,
     * and the live probe found it redacted on `dovresti`.
     *
     * Suppresses the judgement and notational families only. The contribution
     * verbs here are about putting money aside, so an operative directive in
     * the same breath ("dovresti vendere VWCE per raggiungere l'obiettivo")
     * is untouched: `vendere` is not one of these verbs and HARD_DIRECTIVES is
     * not suppressed.
     */
    private const string GOAL_CONTRIBUTION = '/(?:'
        // The goal named first and the contribution after it — "Gap verso
        // l'obiettivo: da 107.000€ a 300.000€ in 14 anni richiede versamenti
        // regolari". A colon is not a segment boundary, so the heading and the
        // figure are one segment and the order is whichever the model chose.
        .'\b(?:obiettivo|traguardo)\b'.self::SAME_SENTENCE.'{0,120}?\b(?:versar|versament|risparmi|accantonar|contribut)'
        .'|\b(?:versar|versament|risparmiar|accantonar|investir|mettere\s+da\s+parte)\w*'
        .self::SAME_SENTENCE.'{0,80}?\b(?:per\s+raggiunger|per\s+arrivare\s+a|obiettivo|traguardo'
        // A contribution stated as a rate is saving too, whether or not the
        // goal is named in the same sentence: "quanto riesci a versare
        // mensilmente con il tuo reddito attuale (2.400€)" is the app's own
        // forecast input, and the live probe found it redacted.
        .'|mensilmente|al\s+mese|ogni\s+mese|annualmente|all\'anno|ogni\s+anno)'
        .')/iu';

    /**
     * Italian abbreviations whose full stop is not the end of a sentence.
     * `es.` is the one that matters: the model writes "(es. obbligazioni,
     * liquidità)" constantly, and splitting there cut one question into two
     * halves, each missing the context the other held — the live probe found a
     * single reflection question reported as two separate removals.
     *
     * Matched against the segment, whose terminator the split has already taken
     * off, and only when a lowercase word follows — so a genuine sentence end
     * before a capital still splits.
     */
    private const string ABBREVIATION = '/\b(?:es|p\.es|ecc|etc|ca|cfr|art|pag|pagg|fig|vs|sig|dott|prof|ing|avv|nr|num|min|max|ved)$/iu';

    /**
     * Nouns that are spelled like the second person of a directive verb:
     * *acquisti*, *aumenti*, *tagli*, *costi*, *spostamenti*. Italian marks the
     * difference with a determiner or a preposition in front — "di acquisti",
     * "gli aumenti", "i tagli" — because a second-person verb never takes one.
     *
     * The previous round met this collision as `aspetti` ("Aspetti da
     * valutare:" read as an instruction to wait) and solved it by dropping that
     * one conjugation from the list. The live probe then found `acquisti` doing
     * exactly the same thing in "il risultato di acquisti in momenti diversi",
     * so the collision is a class and gets a rule rather than another deletion.
     */
    private const string NOUN_DETERMINER = '/(?:^|[^\p{L}\p{N}\'])(?:(?:'
        // Articles and the articulated prepositions built on them.
        .'il|lo|la|i|gli|le'
        .'|di|del|dello|della|dei|degli|delle'
        .'|al|allo|alla|ai|agli|alle'
        .'|da|dal|dallo|dalla|dai|dagli|dalle'
        .'|in|nel|nello|nella|nei|negli|nelle'
        .'|con|col|coi|su|sul|sullo|sulla|sui|sugli|sulle'
        .'|per|tra|fra|senza|dopo|durante'
        // Possessives and quantifiers, which mark a noun just as firmly.
        .'|quest[ei]|quegli|quell[ei]|tuoi|suoi|miei|nostri|loro'
        .'|alcuni|molti|tanti|pochi|vari|diversi|nuovi|altri|ulteriori'
        .'|continui|grandi|piccoli|futuri|passati|eventuali|successivi'
        .')\s+|(?:l|dell|all|dall|nell|sull|un)\'\s*)$/iu';

    /** How far back the determiner that marks a noun is looked for. */
    private const int DETERMINER_WINDOW = 12;

    /**
     * The nominalised directive under a negation: "Questa è un'analisi dei
     * dati, **non una raccomandazione**." A denial has to name what it denies,
     * which is the same structural trap as the refusal — and here it costs the
     * disclaimer the clauses require, on the single word that makes it one.
     *
     * Confined to the nominal family on purpose. Negation does not neutralise
     * a verb — "non vendere VWCE" is an instruction, not the absence of one —
     * so this may not be generalised to HARD_DIRECTIVES.
     */
    private const string NEGATED_NOMINAL = '/\b(?:non|nessun[ao]?)\s+(?:(?:è|era|sono|si\s+tratta\s+di'
        .'|costituisce|rappresenta|vuole\s+essere|intende\s+essere|va\s+intes[oa]\s+come)\s+)?'
        .'(?:un[ao]?\s*\'?\s*)?$/iu';

    /** The directive nouns a negation can cancel. */
    private const string NOMINAL_FORM = '/^(?:raccomandazion|suggeriment|consiglio)/iu';

    /** How far back the negation that cancels a nominal directive is looked for. */
    private const int NEGATION_WINDOW = 40;

    /**
     * The directive conjugations that are also a common plural noun. Only
     * these consult NOUN_DETERMINER; every other form is unambiguous, and
     * running the check on all of them would start forgiving real imperatives
     * after an innocuous preposition.
     */
    private const string NOUN_HOMOGRAPH = '/^(?:ri)?(?:acquisti|aumenti|incrementi|tagli|spostamenti|ingressi|aperture|chiusure)$/iu';

    /**
     * The word boundary this class means — a letter or a digit on either side,
     * rather than PCRE's `\b`, which counts `_` as a word character and stops
     * at an accent on some builds.
     *
     * Written once and concatenated, not spelled out at each site, and the
     * eighth review is why. The lookbehind gate enumerates the patterns the
     * filter *runs*: the constants, and the argument-free methods that return
     * one. A lookbehind written as a literal inside a method body — which is
     * where two of these were — is neither, and no enumeration over values can
     * reach it. Routing every one through a constant makes the gate's
     * coverage structural instead of incidental; the gate asserts it, in
     * test_every_lookbehind_in_the_source_is_one_the_gate_can_reach().
     */
    private const string NOT_WORD_BEFORE = '(?<![\p{L}\p{N}])';

    private const string NOT_WORD_AFTER = '(?![\p{L}\p{N}])';

    /** The markdown emphasis underscore: one outside a word, so snake_case stays whole. */
    private const string LONE_UNDERSCORE = '/'.self::NOT_WORD_BEFORE.'_|_'.self::NOT_WORD_AFTER.'/u';

    /** A 12-character ISIN: two letters, nine alphanumerics, a check digit. */
    private const string ISIN = '/'.self::NOT_WORD_BEFORE.'[A-Z]{2}[A-Z0-9]{9}[0-9]'.self::NOT_WORD_AFTER.'/u';

    /** An all-caps 3-6 character token — the shape of a ticker. */
    private const string TICKER = '/'.self::NOT_WORD_BEFORE.'[A-Z][A-Z0-9]{2,5}'.self::NOT_WORD_AFTER.'/u';

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

    /**
     * The directive's own object, when that object is a class or the portfolio
     * as a whole rather than a position. A segment whose directive already says
     * what it acts on inherits no instrument from its neighbours: there is
     * nothing in it pointing back.
     *
     * This is what keeps the co-occurrence rule from degenerating into a
     * one-sided one on real output. LOOKBACK carries an instrument forward five
     * segments so that an anaphor can be resolved — "Parliamo di Bitcoin. È la
     * tua posizione più volatile. Io la venderei" — but a real reply names a
     * position on nearly every line, so the lookback is satisfied almost
     * always, and the instrument half of the rule stops contributing. The live
     * probe showed the cost: "Valutare se ridurre la quota di crypto",
     * "ribilanciare periodicamente il portafoglio" and "le decisioni di
     * investimento devono essere coerenti con il tuo profilo di rischio" were
     * all removed, and all three are the generic advice ESMA35-43-3861 ¶48-50
     * permits and the product exists to give.
     *
     * Anchored at the directive, not searched for anywhere in the segment:
     * "Vendine una parte. La concentrazione resta il rischio" must stay
     * removed, and a free search for `concentrazione` would spare it.
     *
     * Everything here is safe to list only because this is consulted *after*
     * the segment has been found not to name an instrument — `concentrazione`,
     * `esposizione` and `profilo di rischio` would all be recommendations the
     * moment a position appeared beside them, and then `names` is already true
     * and this is never reached.
     *
     * The head nouns are in two groups, and the third review is why. A *class*
     * noun denotes the whole or a slice of it whatever came before it: "il
     * portafoglio" is the portfolio. A *relational* noun — exposure, weight,
     * concentration, allocation — denotes a relation to something, and left
     * bare that something is whatever was last named: "Parliamo di Bitcoin. È
     * la tua posizione più volatile. Ridurrei la tua esposizione" is the WEA-10
     * finding arriving by another route, with `esposizione` cancelling the very
     * lookback that would have resolved it. So a relational noun is generic
     * only when it says what it is a share *of*.
     */
    private const string GENERIC_DETERMINER = '(?:(?:il|lo|la|i|gli|le|un|uno|una|dei|delle|degli|l\'|tu[ao]|tu[oe]i|tue|sua|suo'
        // The interrogative determiner, which is how a reflection question
        // names a class: "quali categorie vorresti vedere aumentare".
        .'|quali|quale|quant[ei]|ogni|ciascun[ao]?'
        // The adjective that sits between the determiner and the head noun:
        // "un solo ETF", "una sola classe ad alta volatilità".
        .'|sol[aoi]|solamente|soltanto|unic[ao]|singol[ao]|stess[ao]|altr[aeio]|propri[ao])\s*)*';

    /** A head noun that names the whole or a class of it on its own terms. */
    private const string GENERIC_CLASS_OBJECT = 'portafogli[oi]|patrimoni[oi]|asset\s+allocation'
        .'|ripartizion[ei]|diversificazion[ei]|composizion[ei]'
        .'|class[ei]\s+(?:di\s+)?(?:attività|asset|investiment\w+)|categori[ae]'
        .'|decision[ei]\s+(?:di\s+)?investiment\w*|profil[oi](?:\s+di\s+rischio)?|orizzonte'
        .'|cuscinett[oi]|fond[oi]\s+di\s+emergenza|riserv[ae]';

    /**
     * The word whose whole job is to point at something named earlier: the
     * third-person and demonstrative pronoun. Closed in the language, not by
     * a list someone keeps — which is why this is the test the generic licence
     * can rest on, and an adjective list is not.
     *
     * The elided forms are spelled out because the apostrophe binds the noun
     * to the determiner: "quell'ETF" is one token to a word boundary.
     *
     * Relative pronouns are deliberately absent. `che`, `cui` and `quale`
     * point *inside* the sentence at the noun they modify, not back across a
     * segment boundary, so "ridurre l'azionario che hai scelto" names no
     * instrument. Likewise the first and second person: the user and the
     * advisor are not positions.
     *
     * The clitics — `lo`, `la`, `li`, `le`, `ne`, `gli` — are deliberately
     * absent, and not by oversight: spelled alone they are the definite
     * article, so "gli ETF" would read as a pronoun governing a position noun
     * and a plain class object would stop being generic. They are a pronoun
     * only in a position this test cannot see, attached to the verb rather
     * than standing in the object. The enclitic is measured and declared open
     * in docs/ai-advisor-firewall.md.
     *
     * The list is transcribed in tests/Feature/Advisor/closed-classes.md and
     * gated against it, so a pro-form the grammar has and this does not fails
     * the suite rather than passing the filter.
     *
     * Whatever word follows is captured, because a demonstrative is only an
     * anaphor when it stands for the thing rather than describing it — see
     * governsAnAnaphor().
     */
    private const string ANAPHOR = 'ciò|cio|ess[oaie]|quest[oaie]|quell[oaie]|quel|quei|quegli'
        .'|codest[oaie]|cotest[oaie]|tal[ei]|entrambi|entrambe|ambedue';

    /**
     * The pro-form that is a pronoun only behind an article, and an adverb or
     * a preposition without one.
     *
     * `prima` is why this group exists and why it is not simply in ANAPHOR.
     * "Ridurre l'azionario prima di andare in pensione" is a conforming
     * sentence and `prima` there is a preposition; "a favore del primo" is the
     * anaphor. The article is the test, which is the same answer the filter
     * already gives for a noun spelled like a verb — see NOUN_HOMOGRAPH.
     */
    private const string ANAPHOR_AFTER_ARTICLE = 'stess[oaie]|medesim[oaie]|altr[oaie]'
        .'|prim[oaie]|second[oaie]|terz[oaie]|ultim[oaie]|penultim[oaie]';

    private const string ANAPHORIC_PRONOUN = '/'.self::NOT_WORD_BEFORE.'(?:'
        .'(?:quest|quell|codest|cotest)\''
        .'|(?:'.self::ANAPHOR.')'.self::NOT_WORD_AFTER
        .'|(?:'.self::ARTICLE.'|'.self::ARTICULATED_PREPOSITION.')\s+'
        .'(?:'.self::ANAPHOR_AFTER_ARTICLE.')'.self::NOT_WORD_AFTER
        .'|(?:l|un|dell|all|dall|nell|sull|coll)\''
        .'(?:'.self::ANAPHOR_AFTER_ARTICLE.')'.self::NOT_WORD_AFTER
        .')\s*(?<governed>[\p{L}\']+)?/iu';

    /** A head noun that only means something once it says what it is a share of. */
    private const string RELATIONAL_OBJECT = 'quot[ae]|esposizion[ei]|concentrazion[ei]|allocazion[ei]';

    /**
     * The adjective that names a class, and so says what the relational head
     * is a share of without a complement: "l'esposizione azionaria".
     */
    private const string CLASS_ADJECTIVE = 'azionari|obbligazionari|monetari|immobiliar|valutari|creditizi';

    /**
     * The adjective that only quantifies: it says *all*, or *now*, not *all of
     * what*, so it leaves the head as anaphoric as it was.
     */
    private const string QUANTIFIER_ADJECTIVE = 'complessiv|global|total|general|attual';

    /**
     * The preposition that introduces what a relational head is a share of.
     *
     * The same list the event span reads, and not a second one: the hand-written
     * one held `in` and not `nel`, so "la concentrazione nel portafoglio" never
     * parsed a complement at all. Under the fail-closed parse that is a
     * conforming sentence losing its licence, which is the error this round
     * exists to stop making. Widening it cannot open an anaphor, because the
     * complement still has to be a class name and nothing else.
     */
    private const string RELATIONAL_PREPOSITION = self::PREPOSITION.'|de';

    /**
     * A relational head with whatever says what it is a share *of* captured,
     * so that part can be put through the same class test the head would have
     * had to pass. See isGenericRelational().
     *
     * The adjectival form is listed rather than left open, because an open
     * `\w+` after the noun would read "l'esposizione eccessiva" as a class.
     *
     * The adjectives are in two groups, and the sixth review is why. An
     * `adjective` *names a class*; a `quantifier` — `complessiva`, `globale`,
     * `totale`, `generale` — only quantifies: it says *all*, not *all of what*,
     * so it leaves the head as anaphoric as it was. `attuale` is a quantifier
     * by the same test, saying only *now*.
     *
     * The round before answered that by deleting the four quantifiers from the
     * *pattern*, which is not the same as deleting them from the licensing
     * branch, and it cost two conforming sentences: with `totale` unlistable
     * the whole head failed to parse, so "ridurrei l'esposizione totale
     * all'azionario" lost a complement that names a class perfectly well and
     * was redacted. That is the WEA-14 harm, in the commit that exists to undo
     * it. So they are back in the pattern and licence nothing — see
     * isGenericRelational(), where the complement decides whenever there is
     * one and the class adjective only speaks in its absence.
     *
     * `per` is in the prepositions because "l'allocazione per classe di
     * attività" says what it is an allocation of; its absence was one more
     * enumeration of the most closed class there is.
     *
     * Everything the head does not consume is captured rather than discarded,
     * and the seventh review is why. The pattern parsed greedily to the left
     * and tolerantly to the right: whatever it could not parse it dropped in
     * silence, and then the licensing branch spoke anyway. An adjective
     * outside the two lists — `diretta`, `netta`, `eccessiva`, seventeen more
     * — made the preposition group fail, so the complement came back empty and
     * the class adjective licensed a head that was still carrying an anaphor:
     * "Parliamo di Bitcoin. Ridurrei l'esposizione azionaria diretta a questo"
     * passed in twenty measured shapes. The remedy is not another list of
     * adjectives — §C already says an open `\w+` would read "l'esposizione
     * eccessiva" as a class — it is the direction of the failure: either the
     * object parses whole, or the licence is denied. See isGenericRelational().
     */
    private const string RELATIONAL_OBJECT_HEAD = '/^\W*(?:(?:'.self::DEGREE_ADVERB.')\s+)?'
        .self::GENERIC_DETERMINER
        .'(?:'.self::RELATIONAL_OBJECT.')'
        .'(?<adjective>\s*(?:'.self::CLASS_ADJECTIVE.')[aeio]?\b)?'
        .'(?:\s*(?:'.self::QUANTIFIER_ADJECTIVE.')[aeio]?\b)?'
        .'(?<rest>.*)$/iu';

    /**
     * What a relational head's remainder has to be for the head to be a class:
     * a preposition, then the thing it is a share of. Split out of the head
     * pattern so that the remainder is read rather than skipped when it is
     * neither — see RELATIONAL_OBJECT_HEAD.
     *
     * The preposition has to end where it ends: `a[il]?` without a boundary
     * swallowed the `a` of "l'esposizione azionaria" and read the adjective as
     * a complement.
     */
    private const string RELATIONAL_COMPLEMENT = '/^\s+(?:(?:'.self::RELATIONAL_PREPOSITION.')\b'
        .'|'.self::ELIDED_PREPOSITION.')\s*(?<complement>.*)$/iu';

    /**
     * The articulated prepositions as Italian elides them in front of a vowel.
     * Absent from PREPOSITION on purpose — it sits in front of a space — and
     * so written here, where the complement follows the apostrophe directly.
     */
    private const string ELIDED_PREPOSITION = '(?:all|dall|dell|nell|sull|coll|d)\'';

    /**
     * The run of modifiers a class noun may carry and still be that class, so
     * that a complement can be required to end where the class name ends
     * without "all'azionario globale" counting as unparsed material. The two
     * adjective groups are the same two the head uses: one list, so an
     * adjective learned for the head is not a blind spot in the complement.
     */
    private const string CLASS_MODIFIER_RUN = '/^(?:\s*(?:'.self::CLASS_ADJECTIVE
        .'|'.self::QUANTIFIER_ADJECTIVE.')[aeio]?\b)*/iu';

    private const string GENERIC_OBJECT = '/^\W*(?:(?:'.self::DEGREE_ADVERB.')\s+)?'
        .self::GENERIC_DETERMINER
        .'(?:'.self::GENERIC_CLASS_OBJECT.')\b/iu';

    /** @var non-empty-string|null Built from constants, so it is memoised for the whole process. */
    private static ?string $newClausePattern = null;

    /** @var non-empty-string|null Built from a constant, so it is memoised for the whole process. */
    private static ?string $knownPattern = null;

    /** @var non-empty-string|null Built from a constant, so it is memoised for the whole process. */
    private static ?string $genericClassPattern = null;

    /** @var non-empty-string|null Memoised across a request; null = nothing to match. */
    private ?string $heldPattern = null;

    private bool $heldPatternBuilt = false;

    /** @var non-empty-string|null Memoised across a request; null = nothing to match. */
    private ?string $positionPattern = null;

    private bool $positionPatternBuilt = false;

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
     * Whether a bare category name denotes one specific instrument rather than
     * an asset class.
     *
     * This is the numeric channel, and it is the one thing a lexical filter
     * cannot reach. A milestone's target allocation carries the same
     * proposition as a sentence this class removes, with no text in it:
     * `Azionario 60 / Obbligazioni 30 / Liquidità 10` is an allocation by
     * class, inside the ESMA35-43-3861 ¶48-50 safe harbour the product rests
     * on, while `Bitcoin 5%` is a personalised target weight on a named
     * instrument — persisted as a row and redrawn as a glide path for as long
     * as the goal lives. The clauses already draw that line for text ("se
     * l'utente ha dato un nome di strumento a una categoria, trattala come
     * strumento specifico"); without this the two channels disagreed, and the
     * same proposition was blocked as a sentence and saved as a row.
     *
     * The user names their own categories, so neither reading can be assumed.
     * The test is the one mentionsInstrument() applies inside a segment, minus
     * the category half of heldPattern(): a name is an instrument when it is
     * not one of the GENERIC_CLASS_NAMES *and* it matches a position the user
     * holds, a ticker, an ISIN, or something in KNOWN_INSTRUMENTS.
     *
     * Not being generic is necessary and nowhere near sufficient. A category
     * called `Immobili Milano` or `Pensione integrativa` is a bucket the user
     * invented, not an instrument, and refusing those would refuse nearly every
     * real portfolio — which is why the positive half is matched against the
     * positions and never against the category list. See positionPattern().
     */
    public function namesInstrument(string $name): bool
    {
        $name = trim($name);

        if ($name === '' || $this->isGenericClassName($name)) {
            return false;
        }

        if (preg_match(self::ISIN, $name) === 1) {
            return true;
        }

        if (preg_match($this->knownInstrumentPattern(), $name) === 1) {
            return true;
        }

        $pattern = $this->positionPattern();

        if ($pattern !== null && preg_match($pattern, $name) === 1) {
            return true;
        }

        if (preg_match_all(self::TICKER, $name, $matches) >= 1) {
            foreach ($matches[0] as $token) {
                if (! in_array($token, self::NOT_TICKERS, true)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * The names in a target allocation that name a specific instrument, in the
     * order they were given and without repeats. An empty result means the
     * whole set is by class and may be proposed, saved and drawn.
     *
     * Callers treat a non-empty result as a verdict on the set, not on the one
     * bucket: the weights sum to 100 by contract, so dropping a single bucket
     * would leave an allocation that silently no longer adds up.
     *
     * @param  list<string>  $names
     * @return list<string>
     */
    public function instrumentNames(array $names): array
    {
        $blocked = [];

        foreach ($names as $name) {
            $key = mb_strtolower(trim($name));

            // The first spelling wins: the message names what was refused back
            // to the user, and the user wrote the category once.
            if (! isset($blocked[$key]) && $this->namesInstrument($name)) {
                $blocked[$key] = trim($name);
            }
        }

        return array_values($blocked);
    }

    /**
     * Why a target allocation was refused, for the user. Takes the same line as
     * REFUSAL — name what was refused and why, then say what the product does
     * instead — because a write that silently drops a milestone is
     * indistinguishable from a bug.
     */
    public static function allocationRefusal(string ...$names): string
    {
        return 'Allocazione non salvata: assegna un peso obiettivo a uno strumento specifico ('
            .implode(', ', $names).'), e questo assistente non può indicare quanto detenere di una '
            .'singola posizione. Le tappe esprimono la composizione per classi di attività '
            .'(azionario, obbligazionario, liquidità). Per una raccomandazione personalizzata serve '
            .'un consulente finanziario abilitato (in Italia, iscritto all\'albo OCF).';
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

        $parts = $this->rejoinSentences($parts);

        /** @var list<array{index: int, names: bool, header: bool, question: bool, view: string}> $segments */
        $segments = [];

        foreach ($parts as $index => $part) {
            if ($index % 2 === 0 && trim($part) !== '') {
                // Every pattern in this class reads the sentence, not its
                // formatting, so each segment is matched against a view with
                // the markdown emphasis blanked out. Offsets into the view are
                // offsets into the segment, which is what lets isProtectedSpeech
                // keep comparing them.
                $view = $this->unemphasised($part);

                $segments[] = [
                    'index' => $index,
                    'view' => $view,
                    'names' => $this->mentionsInstrument($view),
                    'header' => preg_match(self::TABLE_TARGET_HEADER, $view) === 1,
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
            $view = $segment['view'];
            $notational = $this->notationalDirectiveOffsets($view);
            $operative = $this->operativeDirectiveOffsets($view);
            $found = array_merge($operative, $this->judgementDirectiveOffsets($view), $notational);

            $directives = array_map(static fn (array $d): int => $d['start'], $found);
            sort($directives);

            $tabled = $this->isTargetTableRow($segments, $ordinal, $view);

            if ($directives === [] && ! $tabled) {
                $kept++;

                continue;
            }

            // A question earns the interrogative licence only when it asks
            // about a judgement: no operative verb, no proposed number, and
            // not an assertion with a tag bolted on. "Vendi VWCE?" and "Porta
            // VWCE al 25%?" are instructions whose punctuation changes nothing.
            //
            // Asking about staying put is the exception, because doing nothing
            // needs no instruction. See HOLDING_VERB.
            $proposing = array_filter(
                $operative,
                static fn (array $d): bool => preg_match('/^(?:'.self::HOLDING_VERB.')/iu', $d['text']) !== 1,
            );

            // …and only when staying put is all the segment asks about. A
            // judgement word *governing* a hold verb is a hold recommendation
            // again — "dovresti mantenere VWCE?", "tenere VWCE è la scelta
            // giusta per te?" — so the two may not appear together.
            $judgement = $this->judgementDirectiveOffsets($view);

            $asking = ($segment['question'] || preg_match(self::INDIRECT_QUESTION, $view) === 1)
                && $notational === []
                && $proposing === []
                && ($operative === [] || $judgement === [])
                && preg_match(self::TAG_QUESTION, $view) !== 1;

            // A table row is never a refusal or a reflection question, so the
            // protected-speech gate does not apply to it.
            if (! $tabled && $this->isProtectedSpeech($view, $directives, $asking, $segment['question'])) {
                $kept++;

                continue;
            }

            if ($this->pointsAtInstrument($segments, $ordinal, $view, $found)) {
                $parts[$segment['index']] = $this->leadingMarkup($parts[$segment['index']]).self::REDACTION;
                $redacted++;
            } else {
                $kept++;
            }
        }

        return [implode('', $parts), $redacted, $kept];
    }

    /**
     * Put back the boundaries that do not end a sentence.
     *
     * Two of them. The split above treats every `.` as one, so "(es.
     * obbligazioni, liquidità)" cut a question in half and left each half
     * without the context the other held — the live probe reported one
     * reflection question as two separate removals, and neither half could see
     * the frame that licensed it.
     *
     * And it treats every newline as one, which the eighth review measured as
     * a way round every test in this class that reads an object: the model
     * wraps its bullets, so "Ridurrei l'esposizione azionaria\na questo"
     * arrives as two segments, the object is the short half, and the anaphor
     * is in a segment that carries no directive. A line break is only a
     * sentence boundary when the next line starts one; a line continued in
     * lower case is the same sentence, and a new bullet or a heading is not —
     * they open with a marker, a digit or a capital.
     *
     * Rebuilt rather than spliced in place, because the array is read by
     * parity — even entries are segments, odd ones the terminators that follow
     * them — and a rejoined pair has to carry its terminator back into the
     * text while keeping that alternation intact.
     *
     * @param  list<string>  $parts
     * @return list<string>
     */
    private function rejoinSentences(array $parts): array
    {
        $total = count($parts);
        $out = [];

        for ($i = 0; $i < $total; $i += 2) {
            $segment = $parts[$i];
            $terminator = $parts[$i + 1] ?? '';

            while (isset($parts[$i + 2])
                && preg_match('/^\s*\p{Ll}/u', $parts[$i + 2]) === 1
                && (
                    ($terminator === '.' && preg_match(self::ABBREVIATION, $segment) === 1)
                    || preg_match('/^\n+$/', $terminator) === 1
                )) {
                $segment .= $terminator.$parts[$i + 2];
                $i += 2;
                $terminator = $parts[$i + 1] ?? '';
            }

            $out[] = $segment;
            $out[] = $terminator;
        }

        return $out;
    }

    /**
     * The segment with its markdown emphasis blanked out, byte for byte, so
     * that an offset into the result is still an offset into the original.
     *
     * The model bolds the opening word of a bullet as a matter of course, and
     * `**Considera** se la tua allocazione è coerente…` is not the phrase
     * `considera se`: the live probe found that one reflection question deleted
     * because two asterisks sat between the frame's two words. Blanking the
     * markers once here fixes every pattern at the same time, in both
     * directions — a directive split by emphasis was equally invisible.
     *
     * `_` is only blanked outside a word, so a snake_case token stays whole.
     */
    private function unemphasised(string $segment): string
    {
        // One marker to one space, never a run to a single space: the whole
        // point is that the result is the same length as what it stands in for.
        $out = str_replace('*', ' ', $segment);

        return (string) preg_replace(self::LONE_UNDERSCORE, ' ', $out);
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
     * @param  list<array{start: int, end: int, text: string}>  $directives
     */
    private function pointsAtInstrument(array $segments, int $ordinal, string $part, array $directives): bool
    {
        if ($segments[$ordinal]['names']) {
            return true;
        }

        if (preg_match(self::EXPLANATORY, $part) === 1) {
            return false;
        }

        // At least one directive says it acts on a class, and none of the
        // others points anywhere else — they either name a class too or, in a
        // coordination like "quali categorie aumentare e quali ridurre", name
        // nothing at all. Nothing here points back, so the neighbours'
        // instrument is proximity rather than reference. See GENERIC_OBJECT.
        $objects = array_map(fn (array $d): string => $this->objectKind($part, $d['end']), $directives);

        if (in_array('generic', $objects, true) && ! in_array('other', $objects, true)) {
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
     * @return list<array{start: int, end: int, text: string}>
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
     * @return list<array{start: int, end: int, text: string}>
     */
    private function judgementDirectiveOffsets(string $segment): array
    {
        if (preg_match(self::GOAL_CONTRIBUTION, $segment) === 1) {
            return [];
        }

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
     * @return list<array{start: int, end: int, text: string}>
     */
    private function notationalDirectiveOffsets(string $segment): array
    {
        if ($this->isDescriptive($segment)
            || preg_match(self::HYPOTHETICAL, $segment) === 1
            || preg_match(self::GOAL_CONTRIBUTION, $segment) === 1) {
            return [];
        }

        $patterns = [
            self::NOTATION_ARROW,
            self::NOTATION_RANGE,
            self::NOTATION_MOVE,
            self::NOTATION_DELTA,
            self::NOTATION_MORE_LESS,
        ];

        // A target weight only means something next to the weight itself,
        // and only as a percentage: the app talks about a goal in euro.
        if (preg_match('/\d{1,3}(?:[.,]\d+)?\s*%/u', $segment) === 1) {
            $patterns[] = self::NOTATION_TARGET;
        }

        // A ceiling is a number, so without one in the segment there is no
        // ceiling being proposed: "hai un tetto massimo oltre il quale non
        // vorresti che arrivasse?" asks the user whether they have one.
        if (preg_match('/'.self::QUANTITY.'/u', $segment) === 1) {
            $patterns[] = self::NOTATION_CAP;
        }

        return $this->offsetsOf($segment, $patterns);
    }

    /**
     * @param  list<string>  $patterns
     * @return list<array{start: int, end: int, text: string}>
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
                $end = $offset + strlen($text);

                if ($this->isInverted($segment, $offset)
                    || $this->isConsequence($segment, $text, $offset)
                    || $this->isNegatedNominal($segment, $text, $offset)
                    || $this->isNoun($segment, $text, $offset)
                    || $this->hasAbstractObject($segment, $end)
                    || $this->isNominalisedSubject($segment, $offset, $end)) {
                    continue;
                }

                $offsets[] = ['start' => $offset, 'end' => $end, 'text' => $text];
            }
        }

        usort($offsets, static fn (array $a, array $b): int => $a['start'] <=> $b['start']);

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

    /**
     * Whether this directive states what an event would do rather than what
     * the user should do. See EVENT_SUBJECT.
     */
    private function isConsequence(string $segment, string $text, int $offset): bool
    {
        if (preg_match(self::THIRD_PERSON, $text) !== 1) {
            return false;
        }

        $lookback = $this->withoutTrailingAside(substr($segment, 0, $offset));

        if (preg_match(self::EVENT_SUBJECT, $lookback, $m) !== 1) {
            return false;
        }

        return preg_match($this->newClausePattern(), $m['span']) !== 1;
    }

    /**
     * How far a directive sits from the reflection frame it might belong to,
     * with an aside between the two not counted as distance.
     *
     * The asides have to fill the gap exactly — nothing between them but space
     * — so this is room that cannot be padded with anything else, which is the
     * restriction withoutTrailingAside() carries for the same reason. A
     * directive *inside* an aside is not reached by this: it is its own entry
     * in the offsets, measured over its own unreduced gap.
     *
     * What is deliberately left per-instance is the window itself: an ordinary
     * adverbial still buys room ("valuta se in questo preciso momento vendere
     * Bitcoin"), because distance is the wrong question. Whether the directive
     * is the frame's object is decided by whether a clause of its own stands
     * between them, not by how many characters do, and replacing the window
     * with that test is a change this round has no measurement budget to
     * attack properly. Declared rather than left to be found.
     */
    private function reflectionDistance(string $segment, int $frameEnd, int $offset): int
    {
        $gap = substr($segment, $frameEnd, $offset - $frameEnd);

        return strlen((string) preg_replace('/^(\h*)(?:'.self::ASIDE.'\h*)+$/u', '$1', $gap));
    }

    /**
     * The text up to the directive with a parenthetical aside dropped, so the
     * distance from a subject to its verb is measured over the sentence and
     * not over the inciso. "Un calo del 50% su Bitcoin (evento storicamente
     * possibile …) ridurrebbe il tuo patrimonio" is the same arithmetic as the
     * sentence without the parenthesis, and the third review found only the
     * long one removed.
     *
     * Only an aside that the verb immediately follows, and that restriction is
     * the whole of it. Dropping asides anywhere is itself room bought, and the
     * same attack lands on it: pad a parenthesis and a proxy subject sitting
     * behind it arrives inside the window, so "Una correzione del mercato (per
     * quanto incerta nei tempi e nell'ampiezza) un investitore prudente
     * ridurrebbe la quota di Bitcoin al 5%" would read as arithmetic. An
     * apposition stands between a subject and *its own* verb; if anything but
     * space follows the bracket, the bracket is not what separates them.
     *
     * The aside leaves a space behind rather than nothing, so no two words are
     * joined into one that neither of them is.
     */
    private function withoutTrailingAside(string $text): string
    {
        return (string) preg_replace('/\([^()]*\)(\s*)$/u', ' $1', $text);
    }

    /**
     * Whether this is a directive noun being denied rather than given.
     * See NEGATED_NOMINAL.
     */
    private function isNegatedNominal(string $segment, string $text, int $offset): bool
    {
        if (preg_match(self::NOMINAL_FORM, $text) !== 1) {
            return false;
        }

        $start = max(0, $offset - self::NEGATION_WINDOW);

        return preg_match(self::NEGATED_NOMINAL, substr($segment, $start, $offset - $start)) === 1;
    }

    /**
     * Whether this match is the noun it is spelled like rather than the verb:
     * *gli aumenti*, *di acquisti*, *i tagli*. See NOUN_HOMOGRAPH.
     */
    private function isNoun(string $segment, string $text, int $offset): bool
    {
        if (preg_match(self::NOUN_HOMOGRAPH, $text) !== 1) {
            return false;
        }

        $start = max(0, $offset - self::DETERMINER_WINDOW);

        return preg_match(self::NOUN_DETERMINER, substr($segment, $start, $offset - $start)) === 1;
    }

    /**
     * What the directive ending at this offset says it acts on: `generic` for a
     * class or the portfolio, `empty` when the object is elided — the second
     * half of "quali categorie aumentare e quali ridurre" — and `other` for
     * anything that names something of its own.
     *
     * @return 'generic'|'empty'|'other'
     */
    private function objectKind(string $segment, int $end): string
    {
        $object = $this->objectAfter($segment, $end);

        // "aumentare (es. obbligazioni, liquidità)" — the example marker
        // introduces the object rather than being one, and both patterns below
        // are anchored at the head of the object.
        $object = (string) preg_replace('/^\W*(?:(?:ad\s+)?es|p\.es|per\s+esempio|ad\s+esempio|ovvero|cioè)\b\.?/iu', '', $object);

        if ($this->namesAClass($object) || $this->isGenericRelational($object)) {
            return $this->pointsBack(substr($segment, $end)) ? 'other' : 'generic';
        }

        return preg_match('/\p{L}/u', $object) === 1 ? 'other' : 'empty';
    }

    /**
     * Whether this object points at something named earlier, in which case it
     * is not generic however it opens.
     *
     * The eighth review is why this exists, and the family it names is the one
     * the round before measured on the wrong half. `objectKind()` decides with
     * an OR, and the seventh round made only the relational branch fail
     * closed; `namesAClass()` stayed anchored at the head and tolerant of the
     * tail, and it is evaluated *first*, so an object opening with a class
     * name never reached the branch that had been fixed. "Parliamo di Bitcoin.
     * Ridurrei l'azionario a questo" passed in every measured shape, before
     * the fix and after it.
     *
     * The family is not "relational object" and never was. It is *an object
     * the filter reads as generic, carrying an anaphor the lookback would
     * resolve* — so the test belongs here, above both branches, rather than
     * being written twice.
     *
     * Making the object fail closed instead — requiring namesOnlyAClass() of
     * a directive's object — was measured first and is not affordable: it
     * takes the reflection question with it, seventeen conforming sentences
     * on a sixty-case corpus, because "che cosa significherebbe ridurre
     * l'azionario del 10%?" has a tail and a question mark and is exactly
     * what the clauses permit. A directive's object may run on; what it may
     * not do is point somewhere else. See ANAPHORIC_PRONOUN.
     *
     * See governsAnAnaphor() for where the line falls between a demonstrative
     * that stands for a holding and one that only says *now*.
     */
    private function pointsBack(string $object): bool
    {
        if (preg_match_all(self::ANAPHORIC_PRONOUN, $object, $matches, PREG_SET_ORDER) < 1) {
            return false;
        }

        foreach ($matches as $match) {
            if ($this->governsAnAnaphor($match['governed'] ?? '')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a demonstrative followed by this word is standing in for a
     * holding, given what it governs.
     *
     * The distinction is pronominal against adnominal, and it is the whole
     * reason this is not simply "a demonstrative denies the licence". Measured
     * on a ninety-three-case conforming corpus, that blunter rule cost eight
     * reflection questions — "che cosa significherebbe ridurre l'azionario in
     * questa fase?", "…in questo momento?", "…quest'anno?" — which is the
     * WEA-14 harm arriving inside the commit that exists to close a bypass.
     *
     * A demonstrative with nothing to determine *is* its referent, so it
     * points back. One that determines a noun points at what that noun
     * denotes: a holding when the noun is a POSITION_NOUN, and a stage of the
     * conversation when it is not.
     *
     * A function word is not a noun a demonstrative could be determining, so
     * the demonstrative was pronominal after all — "a questo e alla liquidità"
     * reads `e`, and the pronoun in front of it still points back.
     */
    private function governsAnAnaphor(string $governed): bool
    {
        if ($governed === '') {
            return true;
        }

        if (preg_match('/^(?:'.self::PREPOSITION.'|'.self::NEW_CLAUSE_CONJUNCTION.')$/iu', $governed) === 1) {
            return true;
        }

        return preg_match('/^(?:'.self::POSITION_NOUN.')$/iu', $governed) === 1;
    }

    /** Whether this text opens with the name of the whole or of a class of it. */
    private function namesAClass(string $text): bool
    {
        return $this->afterTheClassName($text) !== null;
    }

    /**
     * Whether this text is the name of a class and *nothing else*, modifiers
     * from the two closed adjective groups aside.
     *
     * The head-anchored test above is right where the object of a directive is
     * read, because a directive's object may run on into the rest of the
     * sentence. It is wrong for the complement of a relational head, and the
     * seventh review measured the difference: the complement test was anchored
     * at its head too, so a class name in first position licensed whatever
     * anaphor followed it — "l'esposizione alla liquidità a favore di questo",
     * "l'esposizione in azionario su questo", "l'esposizione per categoria su
     * questo", six measured shapes and one of them a regression. The licence
     * is for a head that says what it is a share *of*; a head that says that
     * and then points somewhere else has not said it.
     */
    private function namesOnlyAClass(string $text): bool
    {
        $rest = $this->afterTheClassName($text);

        if ($rest === null) {
            return false;
        }

        return preg_match('/[\p{L}\p{N}]/u', (string) preg_replace(self::CLASS_MODIFIER_RUN, '', $rest)) !== 1;
    }

    /**
     * What follows the class name this text opens with, or null when it does
     * not open with one. The accent-free view is a separate string, so the
     * remainder is taken from whichever view matched rather than by offset.
     */
    private function afterTheClassName(string $text): ?string
    {
        if (preg_match(self::GENERIC_OBJECT, $text, $m) === 1) {
            return substr($text, strlen($m[0]));
        }

        $flat = $this->deaccent($text);

        if (preg_match(self::$genericClassPattern ??= $this->buildGenericClassPattern(), $flat, $m) === 1) {
            return substr($flat, strlen($m[0]));
        }

        return null;
    }

    /**
     * Whether a relational head — exposure, weight, concentration, allocation
     * — says what it is a share *of*, which is the only thing that makes it a
     * class rather than an anaphor.
     *
     * The complement has to pass the same class test the head would have had
     * to pass, and the fourth review is why. The round before accepted any
     * preposition followed by any word, so the anaphor the head could no
     * longer host simply moved one word to the right: "l'esposizione a questo"
     * is still exposure to whatever was named a line earlier, and eleven such
     * shapes passed. A filter that removes "l'esposizione" and keeps
     * "l'esposizione a questo" has not closed a vector, it has asked the model
     * to add two words.
     *
     * The complement cannot be an instrument — if it were, the segment would
     * name one and this check would never run — which is the same argument the
     * whole carve-out already rests on.
     *
     * The order of the two tests is the sixth review's correction, and it is
     * the same defect one more time: a branch that returns true before a test
     * annuls the test. Shortening the adjective list did not move the return —
     * the six class adjectives that stayed were unconditional licences exactly
     * as the four quantifiers had been, and the anaphor simply stepped behind
     * one of them ("ridurrei l'esposizione azionaria a questo"). So the
     * complement decides whenever there is a complement, whatever adjective
     * stands in front of it, and the class adjective licenses only the head
     * that has none.
     *
     * The seventh review's correction is the direction of the failure, and it
     * is the same defect read one level up: a parse that discards what it
     * cannot read licenses on the half it could. So the remainder is read, and
     * material that is neither a complement nor nothing at all denies the
     * licence rather than being skipped past it.
     */
    private function isGenericRelational(string $object): bool
    {
        if (preg_match(self::RELATIONAL_OBJECT_HEAD, $object, $m) !== 1) {
            return false;
        }

        // Nothing but punctuation left: the head stands alone, and only a
        // class adjective can say what it is a share of.
        if (preg_match('/[\p{L}\p{N}]/u', $rest = $m['rest']) !== 1) {
            return $m['adjective'] !== '';
        }

        if (preg_match(self::RELATIONAL_COMPLEMENT, $rest, $c) !== 1) {
            return false;
        }

        return $this->namesOnlyAClass($c['complement']);
    }

    /**
     * One alternation over GENERIC_CLASS_NAMES, anchored to the head of the
     * object and matched against an accent-free view so that `liquidità` meets
     * the stored `liquidita`. Longest first, for the same reason the other two
     * alternations are.
     *
     * @return non-empty-string
     */
    private function buildGenericClassPattern(): string
    {
        $names = self::GENERIC_CLASS_NAMES;
        usort($names, static fn (string $a, string $b): int => mb_strlen($b) <=> mb_strlen($a));

        $alternation = implode('|', array_map(
            static fn (string $name): string => preg_quote($name, '/'),
            $names,
        ));

        return '/^\W*(?:(?:il|lo|la|i|gli|le|un|uno|una|dei|delle|degli|l\'|tu[ao]|tu[oe]i|tue'
            .'|quali|quale|quant[ei]|ogni|ciascun[ao]?'
            .'|sol[aoi]|solamente|soltanto|unic[ao]|singol[ao]|stess[ao]|altr[aeio]|propri[ao])\s*)*'
            .'(?:'.$alternation.')(?![\p{L}\p{N}])/iu';
    }

    /** The segment with its Italian accents flattened, for GENERIC_CLASS_NAMES. */
    private function deaccent(string $segment): string
    {
        return strtr(mb_strtolower($segment), [
            'à' => 'a', 'á' => 'a', 'è' => 'e', 'é' => 'e', 'ì' => 'i', 'í' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ù' => 'u', 'ú' => 'u',
        ]);
    }

    /** Whether the directive ending at this offset acts on an abstraction, not a position. */
    private function hasAbstractObject(string $segment, int $end): bool
    {
        return preg_match(self::ABSTRACT_OBJECT, $this->objectAfter($segment, $end)) === 1;
    }

    /**
     * OBJECT_WINDOW characters of whatever follows the directive. The offset
     * arrives from a `/u` match so it is always on a character boundary; the
     * end of the window is the one that has to be made one. See OBJECT_WINDOW.
     */
    private function objectAfter(string $segment, int $end): string
    {
        return mb_substr(substr($segment, $end), 0, self::OBJECT_WINDOW);
    }

    /**
     * Whether this directive is the sentence's subject rather than its order —
     * an infinitive opening a clause and followed by a verb that states a
     * property. See NOMINALISED_SUBJECT.
     */
    private function isNominalisedSubject(string $segment, int $offset, int $end): bool
    {
        // Only an infinitive can be nominalised; an imperative cannot.
        if (preg_match('/(?:are|ere|ire|rre|arsi|ersi|irsi)$/iu', substr($segment, $offset, $end - $offset)) !== 1) {
            return false;
        }

        return preg_match(self::NOMINALISED_SUBJECT, substr($segment, 0, $offset)) === 1
            && preg_match(self::NOMINALISED_PREDICATE, substr($segment, $end, self::NOMINALISED_WINDOW)) === 1;
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
     * @param  bool  $question  the segment ends in a question mark, whatever it carries
     */
    private function isProtectedSpeech(string $segment, array $directives, bool $asking = false, bool $question = false): bool
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

        // The bounded licence: granted only when every directive sits inside
        // the attribution itself, so nothing after it is covered. An
        // ADVERSATIVE past this point can carry no directive by construction,
        // which is why returning here skips that check safely.
        if (preg_match(self::DECISION_IS_YOURS, $segment, $m, PREG_OFFSET_CAPTURE) === 1) {
            $start = $m[0][1];
            $end = $start + strlen($m[0][0]);

            if (array_all($directives, static fn (int $offset): bool => $offset >= $start && $offset < $end)) {
                return true;
            }
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

        // Asking whether the user could sit still. Only in a real
        // interrogative: the same words asserted are a hold recommendation.
        if ($question && preg_match(self::CAPACITY_QUESTION, $segment, $m, PREG_OFFSET_CAPTURE) === 1) {
            $licence = min($licence ?? PHP_INT_MAX, $m[0][1]);
        }

        if (preg_match(self::REFLECTION, $segment, $m, PREG_OFFSET_CAPTURE) === 1) {
            $frameEnd = $m[0][1] + strlen($m[0][0]);

            foreach ($directives as $offset) {
                // Staying put is not an operation, so it is not the kind of
                // object this window exists to reject: "valutare se mantenere
                // entrambi ha senso" is the reflection the clauses ask for,
                // while "valuta se vendere VWCE" is still an instruction.
                if ($offset >= $frameEnd
                    && $this->reflectionDistance($segment, $frameEnd, $offset) <= self::REFLECTION_OBJECT_WINDOW
                    && preg_match('/^\W*(?:'.self::HOLDING_VERB.')/iu', substr($segment, $offset)) !== 1) {
                    return false;
                }
            }

            $licence = min($licence ?? PHP_INT_MAX, $m[0][1]);
        }

        if ($licence === null) {
            return false;
        }

        // The pivot ends the licence only when something is actually said after
        // it. "Non posso dirti se vendere o tenere, ma posso aiutarti a vedere
        // i dati" is the refusal the clauses require, and breaking the licence
        // on the bare `ma` deleted it — the live probe found exactly that, and
        // it is the finding WEA-14 withheld approval over. What the rule is
        // for is "non posso dirtelo, ma la direzione è ridurre Bitcoin", where
        // the advice sits past the pivot; that still fails here.
        if (preg_match(self::ADVERSATIVE, $segment, $m, PREG_OFFSET_CAPTURE) === 1
            && $m[0][1] > $licence
            && array_any($directives, static fn (int $offset): bool => $offset > $m[0][1])) {
            return false;
        }

        return array_all($directives, fn (int $offset): bool => $offset >= $licence);
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

        return self::$knownPattern = '/'.self::NOT_WORD_BEFORE.'(?:'.$alternation.')'.self::NOT_WORD_AFTER.'/iu';
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

        return $this->heldPattern = $this->namePattern($raw);
    }

    /**
     * The same alternation over the user's POSITIONS alone — their category
     * names deliberately left out.
     *
     * heldPattern() includes the categories because in a sentence a category
     * name is just another way to point at a holding. Testing a bare category
     * name against that pattern would match it against itself: every
     * non-generic category the user ever named would come back "instrument",
     * and the numeric channel would refuse every allocation it was given.
     *
     * What makes a category an instrument is that it is named after one — a
     * position the user holds, a ticker, an ISIN, or something in
     * KNOWN_INSTRUMENTS. See namesInstrument().
     *
     * @return non-empty-string|null
     */
    private function positionPattern(): ?string
    {
        if ($this->positionPatternBuilt) {
            return $this->positionPattern;
        }

        $this->positionPatternBuilt = true;

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

        return $this->positionPattern = $this->namePattern($raw);
    }

    /**
     * One case-insensitive alternation over a set of names, dropping the ones
     * too short to be a signal and the ones that denote an asset class.
     *
     * @param  list<string>  $raw
     * @return non-empty-string|null
     */
    private function namePattern(array $raw): ?string
    {
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

        return '/'.self::NOT_WORD_BEFORE.'(?:'.$alternation.')'.self::NOT_WORD_AFTER.'/iu';
    }

    /**
     * An asset or category whose name is an asset class rather than a specific
     * instrument. Compared accent- and punctuation-insensitively so "Liquidità"
     * and "liquidita" are the same word.
     */
    private function isGenericClassName(string $name): bool
    {
        $normalised = $this->deaccent(trim($name));
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
