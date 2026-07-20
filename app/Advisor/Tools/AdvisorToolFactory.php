<?php

declare(strict_types=1);

namespace App\Advisor\Tools;

use App\Actions\Advisor\BuildAdvisorContext;
use App\Models\Category;
use App\Models\Goal;
use App\Models\GoalCategoryAllocation;
use App\Models\InvestorProfile;
use App\Models\Snapshot;
use Illuminate\Support\Carbon;
use Prism\Prism\Facades\Tool;
use Prism\Prism\Schema\ArraySchema;
use Prism\Prism\Schema\EnumSchema;
use Prism\Prism\Schema\NumberSchema;
use Prism\Prism\Schema\ObjectSchema;
use Prism\Prism\Schema\StringSchema;
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
            $this->confirmProfileFact(),
            $this->rememberFact(),
            $this->proposeGoalCore(),
            $this->proposeGoalMilestones(),
            $this->proposeGoalComposition(),
            $this->offerProfileProposal(),
            $this->offerGoalProposal(),
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
            ->for('Simula l\'effetto di un diverso importo mensile del piano di accumulo (PAC) sul tempo stimato per raggiungere l\'obiettivo. Usalo quando l\'utente chiede cosa succede se cambia il versamento mensile — anche quando prevede di AUMENTARLO nel tempo (es. "verso 400€ e cresco del 10% l\'anno"): in quel caso passa annual_increase_pct.')
            ->withNumberParameter('monthly_amount', 'Importo mensile iniziale in euro, es. 400')
            ->withNumberParameter('annual_increase_pct', 'Aumento percentuale del versamento mensile ogni 12 mesi, es. 10 per +10% l\'anno. Ometti o passa 0 per un versamento costante.', required: false)
            ->using(function (int|float $monthly_amount, int|float|null $annual_increase_pct = null): string {
                $increase = $annual_increase_pct !== null ? (float) $annual_increase_pct : 0.0;
                $this->activity->report('Sto simulando un versamento mensile di '.$this->eur((float) $monthly_amount).($increase > 0.0 ? ' con crescita annua del '.$this->num($increase, 1).'%' : '').'…');
                $context = $this->buildContext->run();
                $portfolio = $this->portfolio($context);

                if ($portfolio === null) {
                    return 'Non ci sono ancora dati sufficienti per simulare il PAC.';
                }

                $expectedReturn = $this->expectedAnnualReturn($context);
                $this->emitPacWidget($portfolio, $expectedReturn, (float) $monthly_amount, $increase);

                return $this->describePacSimulation($portfolio, $expectedReturn, (float) $monthly_amount, $increase);
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
            ->for('Proponi una modifica al profilo investitore dell\'utente (nome, data di nascita, orizzonte, tolleranza al rischio, note sul profilo di rischio) quando la conversazione ha chiarito uno o più di questi elementi. Il profilo NON contiene obiettivo o allocazione target: quelli vivono nella sezione Obiettivo e si modificano con i relativi strumenti (propose_goal_core / propose_goal_composition). Il fondo di emergenza NON è un campo del profilo: lo deduci dal cuscinetto reale già nel contesto (categorie non investibili), non chiederlo. Il REDDITO non è un campo del profilo: è osservato automaticamente dalle transazioni bancarie (lo VEDI già nel contesto come «Reddito netto mensile»), non chiederlo per salvarlo. Per un fatto o preferenza durevole da RICORDARE (es. "non vuole obbligazioni") NON usare questo strumento: usa remember_fact, che salva subito. NON salva: mostra all\'utente una proposta che lui conferma con un click. Compila SOLO i campi realmente emersi dalla conversazione; lascia vuoti gli altri. Dopo averlo chiamato, spiega a parole cosa hai proposto e invita l\'utente a confermare.')
            ->withStringParameter('name', 'Nome dell\'utente, se te l\'ha detto (es. "Vincenzo"). Ometti se non emerso.', required: false)
            ->withStringParameter('birth_date', 'Data di nascita in formato AAAA-MM-GG (es. 1990-05-14), se emersa. Serve per l\'età. Ometti se non emersa.', required: false)
            ->withStringParameter('horizon', 'Orizzonte temporale: uno tra "short" (breve, <3 anni), "medium" (medio, 3-10 anni), "long" (lungo, 10+ anni). Ometti se non emerso.', required: false)
            ->withStringParameter('risk_tolerance', 'Tolleranza al rischio: uno tra "low" (bassa), "medium" (media), "high" (alta). Ometti se non emerso.', required: false)
            ->withStringParameter('notes', 'Sintesi del ragionamento sul profilo di rischio (max 1000 caratteri): capacità di rischio (orizzonte, stabilità del reddito, cuscinetto di liquidità), tolleranza emotiva (reazione a un forte calo), e contesto rilevante. Compilalo quando hai condotto un\'intervista di profilazione, così il "perché" resta salvato.', required: false)
            ->using(function (?string $name = null, ?string $birth_date = null, ?string $horizon = null, ?string $risk_tolerance = null, ?string $notes = null): string {
                $this->activity->report('Sto preparando una proposta per il tuo profilo…');

                return $this->describeProfileProposal($name, $birth_date, $horizon, $risk_tolerance, $notes);
            });
    }

    /**
     * Update a factual profile field the user stated OUTRIGHT in an ordinary
     * chat ("il mio reddito è salito a 2000", "ora ho un fondo di emergenza
     * separato") — no profiling interview, no button. Emits the same one-click
     * confirmation card as propose_profile_update (so it still never writes
     * silently), but is allowed on any chat turn (isProfileFactAllowed), because
     * confirming a plain fact doesn't need the interview-consent gate. Kept
     * distinct from propose_profile_update, which is the reasoned end-of-
     * interview proposal (with notes/synthesis) and stays consent-gated.
     */
    private function confirmProfileFact(): PrismTool
    {
        return Tool::as('confirm_profile_fact')
            ->for('Usalo quando l\'utente DICHIARA direttamente un dato del suo profilo in chat, per aggiornarlo subito (senza intervista). Esempi: «mi chiamo Vincenzo», «il mio orizzonte è cambiato, ora è a lungo termine». Il fondo di emergenza NON è un campo del profilo: si gestisce marcando le categorie come non investibili, non qui. Il REDDITO non è un campo del profilo: è osservato dalle transazioni bancarie, non aggiornarlo qui. Per un fatto o preferenza durevole da RICORDARE (es. «ricordati che non voglio obbligazioni») NON usare questo strumento: usa remember_fact, che salva subito senza card. Mostra una card di conferma con un solo click: NON salva da solo. Compila SOLO i campi che l\'utente ha effettivamente dichiarato in questo messaggio; lascia vuoti gli altri. Dopo averlo chiamato, conferma a parole cosa aggiornerai e invita a premere Applica.')
            ->withStringParameter('name', 'Nome dell\'utente, se l\'ha dichiarato (es. "Vincenzo"). Ometti se non dichiarato.', required: false)
            ->withStringParameter('horizon', 'Orizzonte temporale: uno tra "short", "medium", "long". Ometti se non dichiarato.', required: false)
            ->withStringParameter('risk_tolerance', 'Tolleranza al rischio: uno tra "low", "medium", "high". Ometti se non dichiarato.', required: false)
            ->using(function (?string $name = null, ?string $horizon = null, ?string $risk_tolerance = null): string {
                $this->activity->report('Sto aggiornando il tuo profilo…');

                return $this->describeProfileFact($name, $horizon, $risk_tolerance);
            });
    }

    /**
     * Remember ONE durable fact/preference about the user. Unlike every other
     * profile tool, this one WRITES immediately (no confirmation card): the user
     * asked for the memory field to be the single thing the advisor keeps up to
     * date on its own, told after the fact rather than gated behind a click.
     * The new fact is appended to the existing memory (mergedMemory: bulleted,
     * de-duplicated, clamped), so an earlier one is never lost. Still gated on
     * isProfileFactAllowed so it only writes during a real chat turn (a no-op
     * on report generation / unit tests where no message is armed). The returned
     * text tells the model exactly what was stored so it can report it back.
     */
    private function rememberFact(): PrismTool
    {
        return Tool::as('remember_fact')
            ->for('Memorizza UN SOLO fatto o preferenza durevole dell\'utente, es. «non vuole obbligazioni», «preferisce ETF ad accumulo», «investe da 5 anni». A differenza degli altri strumenti del profilo, questo SALVA SUBITO da solo, senza card di conferma: è l\'unico dato del profilo che aggiorni in autonomia. Viene AGGIUNTO alla memoria esistente, non la sostituisce: passa solo il fatto NUOVO emerso ora. Usalo quando emerge una preferenza o un fatto durevole da tenere a mente. Dopo averlo chiamato, comunica all\'utente a parole che l\'hai memorizzato e mostragli esattamente cosa hai salvato.')
            ->withStringParameter('fact', 'Il singolo fatto o preferenza durevole da ricordare, conciso, es. "Non vuole obbligazioni".')
            ->using(function (string $fact): string {
                if (! $this->widgets->isProfileFactAllowed()) {
                    return 'Non posso aggiornare la memoria in questo contesto. Rispondi normalmente.';
                }

                return $this->storeMemoryFact($fact);
            });
    }

    /**
     * Offer to generate the profile proposal: emits a proposal_offer widget (a
     * button) INSTEAD of asking the user in words whether to propose. The click
     * — not a parsed "yes" — is the consent, which the model can't reliably act
     * on itself (it kept re-asking). Emitting only shows the button; the actual
     * card is generated by the dedicated /propose endpoint when the user clicks.
     */
    private function offerProfileProposal(): PrismTool
    {
        return Tool::as('offer_profile_proposal')
            ->for('Usa questo strumento quando l\'intervista di profilazione ha coperto i temi necessari (obiettivo, orizzonte, reddito/cuscinetto, reazione ai cali) e sei pronto a proporre il profilo. Mostra all\'utente un PULSANTE per generare la proposta, invece di chiedergli a parole se vuole procedere. Dopo averlo chiamato, riassumi brevemente a parole cosa hai capito e invita l\'utente a premere il pulsante per vedere la proposta.')
            ->using(function (): string {
                if (! $this->widgets->isProfileOfferAllowed()) {
                    return 'NON mostrare nessun pulsante di proposta ora: questa non è una sessione di definizione del profilo. Rispondi normalmente alla domanda dell\'utente.';
                }
                $this->activity->report('Sto preparando la proposta di profilo…');
                $this->widgets->add('proposal_offer', ['kind' => 'profile']);

                return 'Pulsante per generare la proposta di profilo mostrato all\'utente. Riassumi a parole cosa hai capito e invitalo a premerlo. NON chiedere un ulteriore consenso a parole: il pulsante È il consenso.';
            });
    }

    /**
     * Offer to generate a goal proposal (core/milestones/composition): same
     * button-instead-of-asking pattern as offerProfileProposal.
     */
    private function offerGoalProposal(): PrismTool
    {
        return Tool::as('offer_goal_proposal')
            ->for('Usa questo strumento quando la conversazione ha chiarito abbastanza sull\'OBIETTIVO (importo, data, scopo, ed eventualmente tappe o composizione) e sei pronto a proporlo. Mostra all\'utente un PULSANTE per generare la proposta, invece di chiedergli a parole se vuole procedere. Dopo averlo chiamato, riassumi brevemente cosa hai capito e invitalo a premere il pulsante.')
            ->using(function (): string {
                if (! $this->widgets->isGoalOfferAllowed()) {
                    return 'NON mostrare nessun pulsante di proposta ora: questa non è una sessione di definizione dell\'obiettivo. Rispondi normalmente alla domanda dell\'utente.';
                }
                $this->activity->report('Sto preparando la proposta di obiettivo…');
                $this->widgets->add('proposal_offer', ['kind' => 'goal']);

                return 'Pulsante per generare la proposta di obiettivo mostrato all\'utente. Riassumi a parole cosa hai capito e invitalo a premerlo. NON chiedere un ulteriore consenso a parole: il pulsante È il consenso.';
            });
    }

    /**
     * Propose the CORE of the goal (target amount, target date, description/why).
     * Like propose_profile_update it does NOT write: it emits a goal_core_proposal
     * widget the user confirms with a click. Every field optional so the model
     * proposes only what the conversation settled.
     */
    private function proposeGoalCore(): PrismTool
    {
        $today = Carbon::now()->format('Y-m-d');

        return Tool::as('propose_goal_core')
            ->for('Proponi l\'obiettivo principale dell\'utente: importo target, data target e una descrizione del "perché" (a cosa serve). NON salva: mostra una card che l\'utente conferma con un click. Compila SOLO i campi emersi dalla conversazione. Usalo dopo aver capito, con le risposte dell\'utente, cosa vuole raggiungere e perché.')
            ->withNumberParameter('target_value', 'Importo obiettivo in euro, es. 1000000. Ometti se non emerso.', required: false)
            ->withStringParameter('target_date', "Data obiettivo in formato AAAA-MM-GG, futura (dopo {$today}). Ometti se non emersa.", required: false)
            ->withStringParameter('description', 'Descrizione dell\'obiettivo e del suo scopo (max 500 caratteri), es. "Primo milione per libertà finanziaria entro il 2050". Ometti se non emersa.', required: false)
            ->using(function (int|float|null $target_value = null, ?string $target_date = null, ?string $description = null): string {
                $this->activity->report('Sto preparando una proposta per il tuo obiettivo…');

                return $this->describeGoalCoreProposal(
                    $target_value !== null ? (float) $target_value : null,
                    $target_date,
                    $description,
                );
            });
    }

    /**
     * Propose a set of intermediate milestones toward the goal. Emits a
     * goal_milestones_proposal widget; the confirming write replaces only the
     * milestones, leaving the goal core and target composition untouched.
     */
    private function proposeGoalMilestones(): PrismTool
    {
        $today = Carbon::now()->format('Y-m-d');
        $categories = $this->goalCategoryNames();
        $list = implode(', ', $categories);

        return Tool::as('propose_goal_milestones')
            ->for('Proponi delle tappe intermedie (milestone) verso l\'obiettivo: ognuna con importo, data, etichetta, un\'AZIONE concreta, il suo RAZIONALE e l\'ALLOCAZIONE TARGET a quella tappa. NON salva: mostra una card che l\'utente conferma. Usalo quando avete ragionato su come scomporre l\'obiettivo in traguardi intermedi realistici. Le tappe devono essere quelle di un vero consulente: non basta l\'importo, servono azione, spiegazione e come dovrebbe essere allocato il portafoglio a quel punto (il "glide-path": tipicamente si riduce il rischio avvicinandosi all\'obiettivo).')
            ->withArrayParameter(
                'milestones',
                'Elenco delle tappe intermedie proposte, in ordine cronologico.',
                new ObjectSchema(
                    'milestone',
                    'Una tappa intermedia verso l\'obiettivo.',
                    [
                        new StringSchema('label', 'Breve etichetta della tappa, es. "Metà percorso". Facoltativa.'),
                        new StringSchema('action', 'L\'AZIONE concreta da compiere una volta raggiunta questa tappa, in modo specifico e attuabile (es. "Sposta il 5% del portafoglio da Bitcoin a Obbligazioni e rivedi il PAC"). Se parli di una "fase di mantenimento" o di un cambio di strategia, spiega cosa significa in pratica.'),
                        new StringSchema('rationale', 'Il PERCHÉ di questa azione: il ragionamento che faresti come consulente (es. "Avvicinandoti all\'obiettivo il rischio di sequenza dei rendimenti aumenta: ridurre gli asset volatili protegge il capitale accumulato"). Chiaro e concreto, spiega anche i termini tecnici che usi.'),
                        new NumberSchema('target_value', 'Importo della tappa in euro, es. 500000.'),
                        new StringSchema('target_date', "Data della tappa in formato AAAA-MM-GG, futura (dopo {$today})."),
                        new ArraySchema(
                            'allocation',
                            'L\'allocazione target del portafoglio a questa tappa, usando le CATEGORIE REALI dell\'utente ('.$list.'), tutte incluse e con percentuali che sommano 100%. Rappresenta come dovrebbe essere composto il portafoglio una volta raggiunta la tappa.',
                            new ObjectSchema(
                                'bucket',
                                'Una categoria con la sua quota target a questa tappa.',
                                [
                                    new EnumSchema('category', 'La categoria (una tra quelle reali dell\'utente).', $categories),
                                    new NumberSchema('percentage', 'Quota percentuale (0-100).'),
                                    new NumberSchema('cap_amount', 'FACOLTATIVO. Tetto massimo in valore assoluto per questa categoria a questa tappa (nella valuta del portafoglio, es. 50000). Quando la percentuale applicata all\'importo della tappa supererebbe questo tetto, la categoria si ferma al tetto e la quota eccedente viene ridistribuita sulle altre categorie senza tetto. Usalo quando l\'utente vuole che una categoria non cresca oltre una certa cifra (es. "la liquidità non oltre 50.000", "Bitcoin mai sopra 100.000"). Ometti se non c\'è un tetto.'),
                                ],
                                requiredFields: ['category', 'percentage'],
                            ),
                        ),
                    ],
                    requiredFields: ['target_value', 'target_date'],
                ),
            )
            ->using(function (array $milestones): string {
                $this->activity->report('Sto preparando le tappe intermedie…');

                /** @var list<array<string, mixed>> $milestones */
                return $this->describeGoalMilestonesProposal($milestones);
            });
    }

    /**
     * Propose a TARGET COMPOSITION as macro buckets (Liquidità / ETF / Cripto)
     * with a rationale. This is a SUGGESTION: the confirm card lets the user edit
     * the exact percentages before applying, so the model never sets the final
     * numbers. Emits a goal_composition_proposal widget.
     */
    private function proposeGoalComposition(): PrismTool
    {
        $categories = $this->goalCategoryNames();
        $list = implode(', ', $categories);

        return Tool::as('propose_goal_composition')
            ->for('Suggerisci una composizione target del portafoglio per categoria, con una spiegazione del ragionamento. È un SUGGERIMENTO: la card mostra le percentuali proposte ma l\'utente può modificarle prima di applicare — non decidi tu i numeri finali. Usa ESATTAMENTE le categorie reali dell\'utente ('.$list.'), includendole tutte, e le percentuali devono sommare 100%. Usalo quando avete discusso come dovrebbe essere allocato il portafoglio per l\'obiettivo, coerentemente col profilo di rischio.')
            ->withArrayParameter(
                'buckets',
                'Le categorie con la percentuale suggerita. Includi TUTTE le categorie reali ('.$list.') e fai in modo che la somma sia 100%.',
                new ObjectSchema(
                    'bucket',
                    'Una categoria con la sua quota suggerita.',
                    [
                        new EnumSchema('category', 'La categoria (una tra quelle reali dell\'utente).', $categories),
                        new NumberSchema('percentage', 'Quota percentuale suggerita (0-100).'),
                    ],
                    requiredFields: ['category', 'percentage'],
                ),
            )
            ->withStringParameter('rationale', 'Spiegazione dei trade-off dietro questa composizione (max 1000 caratteri): perché questi pesi, come si legano al profilo di rischio e all\'orizzonte.', required: false)
            ->using(function (array $buckets, ?string $rationale = null): string {
                $this->activity->report('Sto preparando una composizione target da valutare…');

                /** @var list<array<string, mixed>> $buckets */
                return $this->describeGoalCompositionProposal($buckets, $rationale);
            });
    }

    /**
     * The real category names the goal composition should use: the categories of
     * the goal's current target allocation, falling back to all portfolio
     * categories. This keeps the advisor's proposal on the SAME categories the
     * user already uses (so no bucket — e.g. Oro — silently disappears).
     *
     * make() builds every tool on each request, so this runs at tool-CONSTRUCTION
     * time; it must not throw if the DB isn't reachable (e.g. a unit test with no
     * migrated tables), or the whole chat surfaces "il modello non ha risposto".
     * Degrade to an empty list — describeGoalCompositionProposal then rejects any
     * bucket, which is the safe no-op.
     *
     * @return list<string>
     */
    private function goalCategoryNames(): array
    {
        try {
            $goal = Goal::query()->with('categoryAllocations.category')->first();

            if ($goal instanceof Goal) {
                $names = $goal->categoryAllocations
                    ->map(fn (GoalCategoryAllocation $a): ?string => $a->category !== null ? $a->category->name : $a->macro_category)
                    ->filter(fn (?string $n): bool => $n !== null && $n !== '')
                    ->unique()
                    ->values();

                if ($names->isNotEmpty()) {
                    /** @var list<string> */
                    return $names->all();
                }
            }

            /** @var list<string> */
            return Category::query()->orderBy('sort_order')->pluck('name')->all();
        } catch (\Throwable) {
            return [];
        }
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
    private function describePacSimulation(array $portfolio, array $expectedReturn, float $monthlyAmount, float $annualIncreasePct = 0.0): string
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

        // Compound projection: the current net worth grows at the assumed rate
        // while the monthly PAC is added and itself compounds. A linear
        // "remaining / monthly" ignores compounding and yields absurd century
        // ETAs on long-horizon goals. When annualIncreasePct > 0 the monthly
        // contribution steps up every 12 months (a growing PAC). Kept identical
        // to projectPac() in lib/pacMath.ts so the widget matches.
        $maxMonths = 1200; // 100 years — a hard stop for the search
        $months = $this->projectPacMonths($current, $target, $monthlyAmount, $annual, $annualIncreasePct, $maxMonths);

        $intro = 'Simulazione con versamento mensile di '.$this->eur($monthlyAmount);
        if ($annualIncreasePct > 0.0) {
            $intro .= ' in crescita del '.$this->num($annualIncreasePct, 1).'% ogni anno';
        }

        $lines = [
            $intro.':',
            'Patrimonio attuale '.$this->eur($current).', obiettivo '.$this->eur($target).'.',
            'Ipotesi di rendimento annuo: '.$this->num($annual * 100, 1).'% ('.$expectedReturn['source'].') — è un\'assunzione di pianificazione, NON una previsione di mercato.',
        ];

        if ($months >= $maxMonths) {
            $lines[] = 'Con questi versamenti e questa ipotesi di rendimento, l\'obiettivo non viene raggiunto entro un orizzonte ragionevole (oltre 100 anni). Il target è probabilmente troppo ambizioso rispetto al versamento: vale la pena rivedere obiettivo, importo o orizzonte.';

            return implode("\n", $lines);
        }

        $lines[] = 'Stima (con capitalizzazione composta): '.$months.' mesi, ovvero '.$this->num($months / 12, 1).' anni, per raggiungere l\'obiettivo.';
        $lines[] = 'IMPORTANTE: riporta all\'utente ESATTAMENTE questi valori ('.$months.' mesi, '.$this->num($months / 12, 1).' anni), senza arrotondarli né ricalcolarli: sono gli stessi mostrati nel simulatore interattivo, quindi devono coincidere.';

        // Per-year contribution schedule: with a step-up the monthly amount
        // changes each year, so spell it out rather than making the user (or the
        // model) recompute it. Even flat, it's a useful "how much per year" recap.
        $schedule = $this->pacContributionSchedule($monthlyAmount, $annualIncreasePct, (int) ceil($months / 12));
        $lines[] = $annualIncreasePct > 0.0
            ? 'Piano dei versamenti (il mensile cresce del '.$this->num($annualIncreasePct, 1).'% ogni anno):'
            : 'Piano dei versamenti (mensile costante):';
        foreach ($schedule as $row) {
            $lines[] = '  - Anno '.$row['year'].': '.$this->eur($row['monthly']).'/mese ('.$this->eur($row['yearly']).' nell\'anno).';
        }

        if (($goalEta['low_confidence'] ?? false) === true) {
            $lines[] = 'ATTENZIONE: pochi mesi di dati tracciati. La stima dipende molto dall\'ipotesi di rendimento: trattala come ordine di grandezza, non come previsione.';
        }

        return implode("\n", $lines);
    }

    /**
     * The monthly contribution (and its 12× yearly total) for each year of the
     * plan, stepping up by $annualIncreasePct at each full year. Capped so a very
     * long horizon doesn't produce an unwieldy list. Used by both the tool text
     * and the widget; the frontend mirrors the same step logic.
     *
     * @return list<array{year: int, monthly: float, yearly: float}>
     */
    private function pacContributionSchedule(float $monthlyAmount, float $annualIncreasePct, int $years): array
    {
        $years = max(1, min($years, 40));
        $step = 1.0 + $annualIncreasePct / 100.0;

        $rows = [];
        $monthly = $monthlyAmount;
        for ($y = 1; $y <= $years; $y++) {
            if ($y > 1) {
                $monthly *= $step;
            }
            $rows[] = ['year' => $y, 'monthly' => round($monthly, 2), 'yearly' => round($monthly * 12, 2)];
        }

        return $rows;
    }

    /**
     * Months to grow $current to $target, contributing $monthlyAmount each month
     * (stepped up by $annualIncreasePct every 12 months) while the balance
     * compounds at $annualReturn. Returns $maxMonths when the target isn't reached
     * within the cap. Mirrors projectPac() in lib/pacMath.ts — keep in step.
     */
    private function projectPacMonths(float $current, float $target, float $monthlyAmount, float $annualReturn, float $annualIncreasePct, int $maxMonths): int
    {
        $monthlyRate = (1.0 + $annualReturn) ** (1.0 / 12.0) - 1.0;
        $step = 1.0 + $annualIncreasePct / 100.0;

        $balance = $current;
        $contribution = $monthlyAmount;
        $months = 0;
        while ($balance < $target && $months < $maxMonths) {
            // Step the contribution up at each full year elapsed.
            if ($months > 0 && $months % 12 === 0) {
                $contribution *= $step;
            }
            $balance = $balance * (1.0 + $monthlyRate) + $contribution;
            $months++;
        }

        return $months;
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
    private function emitPacWidget(array $portfolio, array $expectedReturn, float $monthlyAmount, float $annualIncreasePct = 0.0): void
    {
        $goalEta = $portfolio['goalEta'];

        if ($goalEta === null || ! isset($goalEta['target_value']) || ($goalEta['reached'] ?? false) === true) {
            return;
        }

        $this->widgets->add('pac_simulator', [
            'current_net_worth' => $portfolio['totalNetWorth'],
            'target_value' => is_numeric($goalEta['target_value']) ? (float) $goalEta['target_value'] : 0.0,
            'monthly_amount' => $monthlyAmount,
            'annual_increase_pct' => $annualIncreasePct,
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

        $goal = Goal::query()->with([
            'categoryAllocations.category',
            'milestones.categoryAllocations.category',
        ])->first();
        $targets = [];
        if ($goal instanceof Goal) {
            // Compare against the CURRENT glide-path step (next unreached
            // milestone's allocation), not a single global target.
            foreach ($goal->currentTargetAllocation((float) $portfolio['totalNetWorth']) as $a) {
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
            $lines[] = 'Versamento mensile necessario: '.$this->eur($required).'.';
            $lines[] = 'IMPORTANTE: riporta all\'utente ESATTAMENTE questa cifra ('.$this->eur($required).'), senza arrotondarla né ricalcolarla a modo tuo: è lo stesso numero mostrato nel simulatore interattivo, quindi devono coincidere.';
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
    private function describeProfileProposal(?string $name, ?string $birthDate, ?string $horizon, ?string $riskTolerance, ?string $notes = null): string
    {
        $name = $name !== null && trim($name) !== '' ? mb_substr(trim($name), 0, 100) : null;
        $birthDate = $birthDate !== null && trim($birthDate) !== '' ? $this->parsePastDate(trim($birthDate)) : null;
        $horizon = in_array($horizon, ['short', 'medium', 'long'], true) ? $horizon : null;
        $riskTolerance = in_array($riskTolerance, ['low', 'medium', 'high'], true) ? $riskTolerance : null;
        $notes = $notes !== null && trim($notes) !== '' ? mb_substr(trim($notes), 0, 1000) : null;

        $proposed = array_filter([
            'name' => $name,
            'birth_date' => $birthDate,
            'horizon' => $horizon,
            'risk_tolerance' => $riskTolerance,
            'notes' => $notes,
        ], fn ($v): bool => $v !== null);

        if ($proposed === []) {
            return 'Non ho abbastanza elementi per proporre una modifica al profilo. Chiedi all\'utente orizzonte e tolleranza al rischio.';
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
        if ($name !== null) {
            $lines[] = '  - Nome: '.$name;
        }
        if ($birthDate !== null) {
            $lines[] = '  - Data di nascita: '.$birthDate;
        }
        if ($horizon !== null) {
            $lines[] = '  - Orizzonte: '.$horizonLabels[$horizon];
        }
        if ($riskTolerance !== null) {
            $lines[] = '  - Tolleranza al rischio: '.$riskLabels[$riskTolerance];
        }
        if ($notes !== null) {
            $lines[] = '  - Note: '.$notes;
        }
        $lines[] = 'La proposta è mostrata all\'utente con un pulsante per confermare: non è ancora salvata.';

        return implode("\n", $lines);
    }

    /**
     * Emit a one-click confirmation card for a factual profile field the user
     * stated directly in chat. Reuses the profile_proposal widget (same card,
     * same POST /advisor/profile write path), but is gated on isProfileFactAllowed
     * — open on ordinary chat turns — instead of the interview-consent gate.
     * Enum/number values are validated the same way; invalid ones are dropped.
     */
    private function describeProfileFact(?string $name, ?string $horizon, ?string $riskTolerance): string
    {
        $name = $name !== null && trim($name) !== '' ? mb_substr(trim($name), 0, 100) : null;
        $horizon = in_array($horizon, ['short', 'medium', 'long'], true) ? $horizon : null;
        $riskTolerance = in_array($riskTolerance, ['low', 'medium', 'high'], true) ? $riskTolerance : null;

        $proposed = array_filter([
            'name' => $name,
            'horizon' => $horizon,
            'risk_tolerance' => $riskTolerance,
        ], fn ($v): bool => $v !== null);

        if ($proposed === []) {
            return 'Non ho capito quale dato del profilo aggiornare. Chiedi all\'utente di precisare (nome, orizzonte, tolleranza).';
        }

        if (! $this->widgets->isProfileFactAllowed()) {
            return 'Non posso aggiornare il profilo in questo contesto. Rispondi normalmente.';
        }

        $this->widgets->add('profile_proposal', $proposed);

        $horizonLabels = ['short' => 'breve', 'medium' => 'medio', 'long' => 'lungo'];
        $riskLabels = ['low' => 'bassa', 'medium' => 'media', 'high' => 'alta'];

        $lines = ['Aggiornamento del profilo (da confermare con un click):'];
        if ($name !== null) {
            $lines[] = '  - Nome: '.$name;
        }
        if ($horizon !== null) {
            $lines[] = '  - Orizzonte: '.$horizonLabels[$horizon];
        }
        if ($riskTolerance !== null) {
            $lines[] = '  - Tolleranza al rischio: '.$riskLabels[$riskTolerance];
        }
        $lines[] = 'La card è mostrata con un pulsante Applica: non è ancora salvata.';

        return implode("\n", $lines);
    }

    /**
     * Validate the proposed goal-core fields, emit the confirmation widget and
     * return text describing what was proposed. Gated by the shared goal-proposal
     * consent flag, like the profile proposal. Returns without a widget when
     * nothing valid was proposed or the date is invalid/past.
     */
    private function describeGoalCoreProposal(?float $targetValue, ?string $targetDate, ?string $description): string
    {
        $targetValue = $targetValue !== null && $targetValue > 0.0 ? $targetValue : null;
        $description = $description !== null && trim($description) !== '' ? mb_substr(trim($description), 0, 500) : null;

        $date = null;
        if ($targetDate !== null && trim($targetDate) !== '') {
            $parsed = $this->parseFutureDate(trim($targetDate));
            if ($parsed === null) {
                return 'La data obiettivo proposta non è valida o non è futura. Chiedi all\'utente una data obiettivo futura nel formato AAAA-MM-GG.';
            }
            $date = $parsed;
        }

        $proposed = array_filter([
            'target_value' => $targetValue,
            'target_date' => $date,
            'description' => $description,
        ], fn ($v): bool => $v !== null);

        if ($proposed === []) {
            return 'Non ho abbastanza elementi per proporre un obiettivo. Chiedi all\'utente importo, data e scopo.';
        }

        if (! $this->widgets->isGoalProposalAllowed()) {
            return 'NON proporre ancora. Non mostrare nessuna card. Prima CHIEDI esplicitamente all\'utente se vuole che imposti questo obiettivo, oppure se preferisce continuare a ragionarci. Attendi il suo consenso.';
        }

        $this->widgets->add('goal_core_proposal', $proposed);

        $lines = ['Proposta di obiettivo (da confermare):'];
        if ($targetValue !== null) {
            $lines[] = '  - Importo target: '.$this->eur($targetValue);
        }
        if ($date !== null) {
            $lines[] = '  - Data target: '.$date;
        }
        if ($description !== null) {
            $lines[] = '  - Descrizione: '.$description;
        }
        $lines[] = 'La proposta è mostrata con un pulsante per confermare: non è ancora salvata.';

        return implode("\n", $lines);
    }

    /**
     * Validate the proposed milestones, emit the widget and describe them. Each
     * milestone needs a positive value and a valid future date; invalid ones are
     * dropped. Gated by the goal-proposal consent flag.
     *
     * @param  list<array<string, mixed>>  $milestones
     */
    private function describeGoalMilestonesProposal(array $milestones): string
    {
        $valid = [];
        foreach ($milestones as $m) {
            $value = is_numeric($m['target_value'] ?? null) ? (float) $m['target_value'] : null;
            $rawDate = is_string($m['target_date'] ?? null) ? trim($m['target_date']) : '';
            $date = $rawDate !== '' ? $this->parseFutureDate($rawDate) : null;
            if ($value === null) {
                continue;
            }
            if ($value <= 0.0) {
                continue;
            }
            if ($date === null) {
                continue;
            }

            $label = is_string($m['label'] ?? null) && trim($m['label']) !== ''
                ? mb_substr(trim($m['label']), 0, 100)
                : null;
            $action = is_string($m['action'] ?? null) && trim($m['action']) !== ''
                ? mb_substr(trim($m['action']), 0, 500)
                : null;
            $rationale = is_string($m['rationale'] ?? null) && trim($m['rationale']) !== ''
                ? mb_substr(trim($m['rationale']), 0, 800)
                : null;
            $allocation = $this->validMilestoneAllocation($m['allocation'] ?? null);

            $valid[] = ['label' => $label, 'action' => $action, 'rationale' => $rationale, 'target_value' => $value, 'target_date' => $date, 'allocation' => $allocation];
        }

        if ($valid === []) {
            return 'Non ho tappe valide da proporre. Ogni tappa serve di un importo positivo e una data futura (AAAA-MM-GG).';
        }

        if (! $this->widgets->isGoalProposalAllowed()) {
            return 'NON proporre ancora. Non mostrare nessuna card. Prima CHIEDI esplicitamente all\'utente se vuole che imposti queste tappe intermedie. Attendi il suo consenso.';
        }

        $this->widgets->add('goal_milestones_proposal', ['milestones' => $valid]);

        $lines = ['Proposta di tappe intermedie (da confermare):'];
        foreach ($valid as $m) {
            $label = $m['label'] !== null ? $m['label'].' — ' : '';
            $lines[] = '  - '.$label.$this->eur($m['target_value']).' entro il '.$m['target_date'];
            if ($m['action'] !== null) {
                $lines[] = '    Azione: '.$m['action'];
            }
            if ($m['rationale'] !== null) {
                $lines[] = '    Perché: '.$m['rationale'];
            }
            if ($m['allocation'] !== []) {
                $parts = array_map(function (array $b): string {
                    $s = $b['category'].' '.$this->num($b['percentage'], 1).'%';

                    return $b['cap_amount'] !== null ? $s.' (tetto '.$this->eur($b['cap_amount']).')' : $s;
                }, $m['allocation']);
                $lines[] = '    Allocazione: '.implode(', ', $parts);
            }
        }
        $lines[] = 'La proposta è mostrata con un pulsante per confermare: non è ancora salvata.';

        return implode("\n", $lines);
    }

    /**
     * Validate a milestone's target allocation: keep only real category names,
     * clamp each 0-100, and accept the set only when it sums to ~100. A missing
     * or non-100 allocation degrades to [] (the milestone is still valid, just
     * without a glide-path step) — we don't reject the whole milestone over it.
     * Each kept entry carries the category's colour (same lookup as the donut)
     * so the widget renders the glide-path bar in the user's category colours,
     * and an optional `cap_amount` — an absolute ceiling on that category at the
     * milestone (currency-agnostic). A non-positive cap is dropped to null.
     *
     * @return list<array{category: string, percentage: float, color: string, cap_amount: float|null}>
     */
    private function validMilestoneAllocation(mixed $allocation): array
    {
        if (! is_array($allocation)) {
            return [];
        }

        $allowed = $this->goalCategoryNames();
        $colours = Category::query()->pluck('color', 'name')->all();
        $out = [];
        $total = 0.0;
        foreach ($allocation as $b) {
            if (! is_array($b)) {
                continue;
            }
            $category = is_string($b['category'] ?? null) ? $b['category'] : '';
            $pct = is_numeric($b['percentage'] ?? null) ? (float) $b['percentage'] : null;
            if (! in_array($category, $allowed, true)) {
                continue;
            }
            if ($pct === null) {
                continue;
            }
            $clamped = max(0.0, min(100.0, $pct));
            $colour = $colours[$category] ?? null;
            $cap = is_numeric($b['cap_amount'] ?? null) && (float) $b['cap_amount'] > 0.0 ? (float) $b['cap_amount'] : null;
            $out[] = ['category' => $category, 'percentage' => $clamped, 'color' => is_string($colour) ? $colour : '#94a3b8', 'cap_amount' => $cap];
            $total += $clamped;
        }

        return abs($total - 100.0) <= 0.5 ? $out : [];
    }

    /**
     * Validate the proposed macro-composition buckets, emit the widget and
     * describe them. Buckets are a SUGGESTION: percentages are clamped 0-100 and
     * invalid categories dropped, but the sum is NOT forced to 100 — the widget
     * lets the user edit the numbers before applying and warns if they don't add
     * up. Gated by the goal-proposal consent flag.
     *
     * @param  list<array<string, mixed>>  $buckets
     */
    private function describeGoalCompositionProposal(array $buckets, ?string $rationale): string
    {
        $rationale = $rationale !== null && trim($rationale) !== '' ? mb_substr(trim($rationale), 0, 1000) : null;

        $allowed = $this->goalCategoryNames();

        $valid = [];
        foreach ($buckets as $b) {
            $category = is_string($b['category'] ?? null) ? $b['category'] : '';
            $pct = is_numeric($b['percentage'] ?? null) ? (float) $b['percentage'] : null;
            if (! in_array($category, $allowed, true)) {
                continue;
            }
            if ($pct === null) {
                continue;
            }

            $valid[] = ['category' => $category, 'percentage' => max(0.0, min(100.0, $pct))];
        }

        if ($valid === []) {
            return 'Non ho una composizione valida da suggerire. Le categorie ammesse sono: '.implode(', ', $allowed).', con percentuali 0-100.';
        }

        $total = 0.0;
        foreach ($valid as $b) {
            $total += $b['percentage'];
        }

        // The composition must add up. A local/cloud model routinely drops a
        // category (leaving e.g. 92%). Rather than emit a card the user has to
        // fix by hand, refuse and tell the model to recompute to 100% including
        // every real category — deterministic, not left to prompt obedience.
        if (abs($total - 100.0) > 0.5) {
            return 'La composizione proposta somma '.$this->num($total, 1).'%, non 100%. Ricalcola i pesi in modo che TUTTE le categorie reali ('.implode(', ', $allowed).') siano incluse e la somma faccia esattamente 100%, poi riprova. NON mostrare nessuna card finché non torna 100%.';
        }

        if (! $this->widgets->isGoalProposalAllowed()) {
            return 'NON proporre ancora. Non mostrare nessuna card. Prima CHIEDI esplicitamente all\'utente se vuole valutare una composizione target. Attendi il suo consenso.';
        }

        $this->widgets->add('goal_composition_proposal', [
            'buckets' => $valid,
            'rationale' => $rationale,
            'total_pct' => round($total, 1),
        ]);

        $lines = ['Composizione target suggerita (modificabile prima di confermare):'];
        foreach ($valid as $b) {
            $lines[] = '  - '.$b['category'].': '.$this->num($b['percentage'], 1).'%';
        }
        $lines[] = 'Totale: '.$this->num($total, 1).'%.';
        if ($rationale !== null) {
            $lines[] = 'Motivazione: '.$rationale;
        }
        $lines[] = 'È un suggerimento: l\'utente può correggere le percentuali sulla card prima di applicarle. Nulla è ancora salvato.';

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

    /**
     * Parse a AAAA-MM-GG string and return it as a normalized Y-m-d string only
     * if it is a valid FUTURE date; null otherwise. Used to validate the dates
     * the model proposes for the goal and its milestones.
     */
    private function parseFutureDate(string $date): ?string
    {
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }

        if (! $parsed instanceof Carbon || $parsed->isPast()) {
            return null;
        }

        return $parsed->format('Y-m-d');
    }

    /**
     * Compose the `memory` value for a profile-proposal card by APPENDING the new
     * durable fact to what the profile already holds, so an earlier memory is
     * never lost when the AI proposes a new one (the card POSTs the whole field,
     * which replaces it). Deterministic — not left to the model. Memory is stored
     * as one "• "-bulleted line per fact; a case-insensitive duplicate is skipped,
     * and the result is clamped to the 2000-char column limit (oldest lines
     * dropped first if it overflows). The manual profile dialog still replaces the
     * field wholesale — this append path is only for the AI's proposal cards.
     */
    /**
     * Append a durable fact to the profile memory and PERSIST it immediately —
     * the one autonomous profile write (see rememberFact). Merges via
     * mergedMemory (bulleted, de-duplicated, clamped) and saves onto the single
     * InvestorProfile row, creating it if the user has none yet. Returns a
     * confirmation the model relays to the user, telling them the fact was
     * saved. When mergedMemory yields null (empty/blank fact) nothing is written.
     */
    private function storeMemoryFact(string $fact): string
    {
        $merged = $this->mergedMemory($fact);
        if ($merged === null) {
            return 'Non ho un fatto valido da ricordare. Chiedi all\'utente di precisare cosa vuole che tenga a mente.';
        }

        $profile = InvestorProfile::query()->first() ?? new InvestorProfile;
        $profile->memory = $merged;
        $profile->save();

        return "Ho memorizzato questo nel profilo (salvato automaticamente, senza bisogno di conferma):\n  - ".trim($fact)
            ."\nComunica all'utente che l'hai memorizzato e mostragli esattamente cosa hai salvato.";
    }

    private function mergedMemory(?string $newFact): ?string
    {
        $newFact = $newFact !== null ? trim($newFact) : '';
        if ($newFact === '') {
            return null;
        }

        // Strip a leading bullet the model might echo, then re-add ours.
        $newFact = ltrim($newFact, "•- \t");
        $existing = InvestorProfile::query()->first()?->memory;

        $lines = [];
        if (is_string($existing) && trim($existing) !== '') {
            foreach (preg_split('/\R/', $existing) ?: [] as $line) {
                $line = trim($line);
                if ($line !== '') {
                    $lines[] = $line;
                }
            }
        }

        $bulleted = '• '.$newFact;
        $alreadyThere = array_any($lines, fn ($line) => mb_strtolower(ltrim((string) $line, "• \t")) === mb_strtolower($newFact));
        if (! $alreadyThere) {
            $lines[] = $bulleted;
        }

        // Clamp to the column limit, dropping the oldest lines first if needed.
        while (count($lines) > 1 && mb_strlen(implode("\n", $lines)) > 2000) {
            array_shift($lines);
        }

        return mb_substr(implode("\n", $lines), 0, 2000);
    }

    /**
     * Parse a birth date: a valid Y-m-d strictly in the past. Anything else
     * (bad format, today/future) is dropped so the model can't set a nonsensical
     * birth date. Mirrors the StoreInvestorProfileRequest `date|before:today` rule.
     */
    private function parsePastDate(string $date): ?string
    {
        try {
            $parsed = Carbon::createFromFormat('Y-m-d', $date);
        } catch (\Throwable) {
            return null;
        }

        if (! $parsed instanceof Carbon || ! $parsed->isPast()) {
            return null;
        }

        return $parsed->format('Y-m-d');
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
