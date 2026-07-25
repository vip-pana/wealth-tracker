<?php

declare(strict_types=1);

namespace App\Actions\Advisor;

use App\Actions\Action;
use App\Actions\Transactions\ComputeMonthlySalary;
use App\Advisor\AdvisorPrompt;
use App\Advisor\Tools\AdvisorToolActivityReporter;
use App\Advisor\Tools\AdvisorWidgetCollector;
use App\Contracts\AdvisorProvider;
use App\Models\AdvisorMessage;
use App\Models\AdvisorSession;
use App\Models\InvestorProfile;

class ContinueChat extends Action
{
    /**
     * How many prior turns to send back to a plain-chat session. The advisor
     * reasons over fresh metrics every turn, so a general question doesn't need
     * the whole transcript. Interviews are the exception (see buildMessages):
     * they always send the FULL history, because dropping the user's earlier
     * answers is exactly what made the model re-ask target/date/income and loop.
     */
    private const int HISTORY_TURNS = 20;

    /**
     * Minimum number of user turns before the advisor should offer the "generate
     * proposal" button. Forces a real interview: below this the system prompt
     * tells the model to keep asking instead of offering the button too early.
     */
    private const int MIN_INTERVIEW_TURNS = 4;

    public function __construct(
        private readonly BuildAdvisorContext $buildContext,
        private readonly RenderAdvisorContext $renderContext,
        private readonly AdvisorProvider $provider,
        private readonly AdvisorToolActivityReporter $activity,
        private readonly AdvisorWidgetCollector $widgets,
        private readonly ComputeMonthlySalary $computeMonthlySalary,
    ) {}

    /**
     * Append the user's question to the session, ask the model with fresh
     * context + recent history, persist and return the assistant reply.
     * Returns null when the advisor isn't configured.
     */
    public function run(AdvisorSession $session, string $userMessage): ?AdvisorMessage
    {
        if (! $this->provider->isConfigured()) {
            return null;
        }

        // Ask the model FIRST, persisting nothing yet: if it fails we must not
        // leave an orphan user message behind, because two consecutive user
        // turns confuse the model and poison every later request in the thread.
        $reply = $this->provider->chat($this->buildMessages($session, $userMessage));

        AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_USER,
            'content' => $userMessage,
        ]);

        return AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_ASSISTANT,
            'content' => $reply,
        ]);
    }

    /**
     * Generate the reply for an already-persisted user turn and fill the
     * pending assistant message in place. Used by the background job: the web
     * request has already stored the user message and an empty `pending`
     * assistant message, so the UI can show the sent question immediately and
     * poll for the answer. Marks the assistant message done, or failed on error.
     */
    public function complete(AdvisorSession $session, AdvisorMessage $user, AdvisorMessage $assistant): void
    {
        if (! $this->provider->isConfigured()) {
            $assistant->update(['status' => AdvisorMessage::STATUS_FAILED, 'error' => 'Consulente AI non configurato.']);

            return;
        }

        // Arm the reporter so any tool the model calls writes its live label to
        // this message; the polling UI reads it. Cleared before the final update
        // so the finished reply carries no lingering activity text. The widget
        // collector is armed the same way so a tool with an interactive
        // counterpart can attach it to this reply.
        $this->activity->for($assistant);
        $this->widgets->for($assistant);
        // In a normal chat turn the model may NEVER emit a proposal card: the
        // only path to a card is the user clicking the offered button, which
        // hits ProposeController -> proposeNow(). So the propose_* gates stay
        // shut here. Two competing paths (chat-"yes" vs button) made cards appear
        // unpredictably mid-chat; the button is now the single, explicit path.
        // What a chat turn CAN do is offer the button, gated to interview
        // sessions so a plain chat that merely mentions figures never surfaces
        // it. The current user turn counts too — a hand-typed "voglio ridefinire
        // l'obiettivo" promotes an ordinary chat into an interview from here on.
        $interviewKind = $this->interviewKind($session, $user->content);
        $this->widgets->allowGoalOffer($interviewKind === AdvisorSession::KIND_GOAL_INTERVIEW);
        $this->widgets->allowProfileOffer($interviewKind === AdvisorSession::KIND_PROFILE_INTERVIEW);
        // A plain factual profile statement ("il mio reddito è 2000") can be
        // confirmed on any chat turn via confirm_profile_fact — it only emits a
        // one-click card, never a silent write, so it needs no interview gate.
        $this->widgets->allowProfileFact(true);

        try {
            $reply = $this->provider->chat($this->buildMessages($session, $user->content, $user->id));
        } catch (\Throwable) {
            $this->activity->clear();
            $this->widgets->clear();
            $assistant->update(['status' => AdvisorMessage::STATUS_FAILED, 'error' => 'Il consulente non ha risposto. Riprova.', 'tool_activity' => null]);

            return;
        }

        $widgets = $this->widgets->widgets();
        $this->activity->clear();
        $this->widgets->clear();
        $assistant->update([
            'content' => $reply,
            'status' => AdvisorMessage::STATUS_DONE,
            'tool_activity' => null,
            'widgets' => $widgets === [] ? null : $widgets,
        ]);
    }

    /**
     * Fill a pending assistant turn with a PROPOSAL, triggered by the user
     * clicking the "generate the proposal" button (not a chat message). The
     * click IS the consent, so we open the relevant gate unconditionally and
     * instruct the model to call the propose_* tool now — sidestepping the
     * model's reluctance to act on a parsed "yes". `$kind` is 'profile' or
     * 'goal'. There is no user turn: the interview so far is the whole context.
     */
    public function proposeNow(AdvisorSession $session, AdvisorMessage $assistant, string $kind): void
    {
        if (! $this->provider->isConfigured()) {
            $assistant->update(['status' => AdvisorMessage::STATUS_FAILED, 'error' => 'Consulente AI non configurato.']);

            return;
        }

        $this->activity->for($assistant);
        $this->widgets->for($assistant);
        $this->widgets->allowProfileProposal($kind === 'profile');
        $this->widgets->allowGoalProposal($kind === 'goal');

        $instruction = $kind === 'profile'
            ? 'L\'utente ha premuto il pulsante per generare la proposta di PROFILO. Chiama SUBITO propose_profile_update con i valori emersi nella conversazione. NON chiedere conferma, NON descrivere la card a parole prima di chiamarlo: la chiamata allo strumento è l\'unica azione corretta ora.'
            : 'L\'utente ha premuto il pulsante per generare la proposta. Chiama SUBITO gli strumenti SOLO per le parti che l\'utente ha effettivamente chiesto di rivedere in questa conversazione — NON tutte per abitudine: '
                ."\n- propose_goal_core (importo/data/descrizione) SOLO se l'utente ha chiesto di modificare l'obiettivo principale. Se ha detto che l'obiettivo va bene così com'è, NON chiamarlo."
                ."\n- propose_goal_milestones SOLO se avete lavorato sulle tappe intermedie."
                ."\n- propose_goal_composition SOLO se avete lavorato sull'allocazione target."
                ."\nChiama solo gli strumenti pertinenti, con i valori emersi. NON chiedere conferma, NON descrivere le card a parole prima di chiamarle: le chiamate agli strumenti sono l'unica azione corretta ora."
                ."\nTETTI (cap_amount): se durante la conversazione l'utente ha chiesto un tetto massimo su una categoria (es. «la liquidità non oltre 50.000», «Bitcoin mai sopra 100.000»), DEVI passare cap_amount su QUELLA categoria in OGNI milestone di propose_goal_milestones dove è presente — non ometterlo e non limitarti a scriverlo a parole. Se non hai passato il cap_amount, il tetto NON viene salvato.";

        try {
            $reply = $this->provider->chat($this->buildMessages($session, $instruction));
        } catch (\Throwable) {
            $this->activity->clear();
            $this->widgets->clear();
            $assistant->update(['status' => AdvisorMessage::STATUS_FAILED, 'error' => 'Il consulente non ha risposto. Riprova.', 'tool_activity' => null]);

            return;
        }

        $widgets = $this->widgets->widgets();
        $this->activity->clear();
        $this->widgets->clear();
        $assistant->update([
            'content' => $reply,
            'status' => AdvisorMessage::STATUS_DONE,
            'tool_activity' => null,
            'widgets' => $widgets === [] ? null : $widgets,
        ]);
    }

    /**
     * Like run(), but streams the reply: each text delta is handed to $onChunk
     * as it arrives. Persists the user turn and the full assistant reply only
     * after a successful stream (same no-orphan guarantee as run()). Returns the
     * saved assistant message, or null when the advisor isn't configured.
     *
     * @param  callable(string): void  $onChunk
     */
    public function runStreaming(AdvisorSession $session, string $userMessage, callable $onChunk): ?AdvisorMessage
    {
        if (! $this->provider->isConfigured()) {
            return null;
        }

        $reply = $this->provider->chatStream($this->buildMessages($session, $userMessage), $onChunk);

        AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_USER,
            'content' => $userMessage,
        ]);

        return AdvisorMessage::create([
            'session_id' => $session->id,
            'role' => AdvisorMessage::ROLE_ASSISTANT,
            'content' => $reply,
        ]);
    }

    /**
     * Assemble what the model sees: the conversational system prompt, the
     * user's current portfolio as fresh context, the recent turns of this
     * session (oldest first), then the new question. Metrics are recomputed
     * now, so reopening an old session still reasons about the present state.
     *
     * When called from the background job, the current user turn and the empty
     * pending assistant turn are already persisted; `$excludeFromId` drops them
     * from the history so they aren't duplicated (the new question is appended
     * explicitly below).
     *
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(AdvisorSession $session, string $userMessage, ?int $excludeFromId = null): array
    {
        $briefing = $this->renderContext->run($this->buildContext->run());

        // An interview (goal/profile) must send the WHOLE transcript — truncating
        // it is what dropped the user's earlier answers and made the model re-ask
        // them and loop. A plain question keeps the recent-turns window. What
        // makes a session an interview is its INTENT (kind or an explicit request
        // in the conversation), not the mere mention of figures.
        $interviewKind = $this->interviewKind($session, $userMessage, $excludeFromId);
        $isInterview = $interviewKind !== null;
        $slots = $isInterview ? $this->interviewSlots($session, $userMessage, $excludeFromId) : null;

        $historyQuery = $session->messages()
            ->when($excludeFromId !== null, fn ($q) => $q->where('id', '<', $excludeFromId))
            ->orderByDesc('id');

        if (! $isInterview) {
            $historyQuery->limit(self::HISTORY_TURNS);
        }

        $history = $historyQuery
            ->get()
            ->reverse()
            ->values();

        // Collapse any consecutive same-role turns (e.g. orphaned user messages
        // from a past failure) into one, then append the new question, so the
        // model always sees a clean role alternation and doesn't choke.
        /** @var list<array{role: string, content: string}> $turns */
        $turns = [];
        foreach ($history as $message) {
            $turns[] = ['role' => $message->role, 'content' => $message->content];
        }
        $turns[] = ['role' => AdvisorMessage::ROLE_USER, 'content' => $userMessage];

        /** @var list<array{role: string, content: string}> $conversation */
        $conversation = [];
        foreach ($turns as $turn) {
            $count = count($conversation);
            if ($count > 0 && $conversation[$count - 1]['role'] === $turn['role']) {
                $conversation[$count - 1]['content'] .= "\n\n".$turn['content'];

                continue;
            }
            $conversation[] = $turn;
        }

        $messages = [
            ['role' => 'system', 'content' => $this->systemPrompt()],
            ['role' => 'system', 'content' => "Stato attuale del portafoglio dell'utente (dati aggiornati):\n\n".$briefing],
        ];

        // Concrete pacing signal for the interview: a generic "ask only when the
        // picture is complete" rule isn't enough — the model asks for consent
        // after one or two questions. Telling it the actual turn count, and that
        // it's still too early, holds far better than the rule alone.
        $userTurns = $session->messages()->where('role', AdvisorMessage::ROLE_USER)->count();
        if ($userTurns < self::MIN_INTERVIEW_TURNS) {
            $messages[] = ['role' => 'system', 'content' => "Nota: l'utente ha scritto solo {$userTurns} messaggi in questa sessione. Se sta definendo il profilo di rischio, l'intervista è ancora agli inizi: NON chiedergli ancora se vuole aggiornare il profilo e NON proporlo. Continua a fare domande di approfondimento, una alla volta."];
        }

        // Structured slot-filling: tell the model, deterministically, which
        // interview themes the user has ALREADY answered, so it stops re-asking
        // them and starting over. Only injected once an interview is under way;
        // when every theme is covered it flips to an "offer the button now"
        // directive so the model reaches offer_goal_proposal instead of looping
        // on questions it already has the answers to.
        if ($slots !== null) {
            $messages[] = ['role' => 'system', 'content' => $this->slotFillingBriefing($slots)];
        }

        return [
            ...$messages,
            ...$conversation,
        ];
    }

    /**
     * Which interview a session is, or null for a plain chat. An interview is
     * defined by INTENT, never by the mere mention of figures: the session was
     * opened as one (kind), or a user message in it explicitly asked to
     * define/revise the goal or profile. This gates both the full-history send
     * and the "generate proposal" button, so an ordinary question that happens
     * to name a percentage or a year never surfaces the button.
     *
     * @return AdvisorSession::KIND_GOAL_INTERVIEW|AdvisorSession::KIND_PROFILE_INTERVIEW|null
     */
    private function interviewKind(AdvisorSession $session, string $userMessage, ?int $excludeFromId = null): ?string
    {
        if ($session->isGoalInterview()) {
            return AdvisorSession::KIND_GOAL_INTERVIEW;
        }
        if ($session->isProfileInterview()) {
            return AdvisorSession::KIND_PROFILE_INTERVIEW;
        }

        $messages = $session->messages()
            ->where('role', AdvisorMessage::ROLE_USER)
            ->when($excludeFromId !== null, fn ($q) => $q->where('id', '<', $excludeFromId))
            ->pluck('content')
            ->push($userMessage);

        foreach ($messages as $content) {
            $kind = AdvisorSession::interviewIntentKind(is_string($content) ? $content : '');
            if ($kind !== null) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * Deterministic slot-filler for the goal/profile interview. Scans every user
     * message in the session (plus the turn being sent) for the four themes the
     * advisor must cover before offering a proposal, and returns which are
     * already answered. Pure keyword/number heuristics — no model call. Only
     * meaningful once interviewKind() has confirmed the session is an interview.
     *
     * @return array{target: bool, date: bool, income: bool, tolerance: bool}
     */
    private function interviewSlots(AdvisorSession $session, string $userMessage, ?int $excludeFromId): array
    {
        $contents = $session->messages()
            ->where('role', AdvisorMessage::ROLE_USER)
            ->when($excludeFromId !== null, fn ($q) => $q->where('id', '<', $excludeFromId))
            ->pluck('content')
            ->push($userMessage);

        $blob = mb_strtolower($contents->implode("\n"));

        // A field the user has ALREADY persisted in the profile counts as covered
        // even if it never came up in this conversation — the interview must not
        // re-ask for it. The Goal (target/date) is likewise pre-known, but the
        // OBIETTIVO ATTUALE briefing already carries it, so slots for target/date
        // stay conversation-derived; income and tolerance are the ones the model
        // kept re-asking despite the profile holding them.
        $profile = InvestorProfile::query()->first();

        // Target amount: a figure with a thousands separator / "k"/"mila", or the
        // words milione/mila-euro, or an explicit "obiettivo/target di X".
        $target = preg_match('/\bmilion|\bmila\b|\d[\d.\s]{3,}(?:€|euro)?|\d+\s*k\b|\d+\s*mila/u', $blob) === 1;

        // Target date/horizon: a 4-digit year (2030-2099) or "entro N anni" or an
        // age target ("a 50 anni", "quando avrò N anni").
        $date = preg_match('/\b20[3-9]\d\b|entro\s+\d+\s+anni|a\s+\d{2}\s+anni|\d{2}\s+anni/u', $blob) === 1;

        // Income & stability: covered if a salary is observed from bank
        // transactions, else mentions of salary/income or a monthly figure. The
        // emergency-fund buffer is no longer an interview theme — it's read from
        // the tagged non-investable categories — so it doesn't gate completeness.
        $income = $this->computeMonthlySalary->run() !== null
            || preg_match('/reddito|stipendio|guadagn|netto|al mese|mensil/u', $blob) === 1;

        // Emotional tolerance: covered if the profile already records a risk
        // tolerance, else the reaction-to-a-drawdown vocabulary in this chat.
        $tolerance = $profile?->risk_tolerance !== null
            || preg_match('/-?\s*[1-5]0\s*%|cal[oi]|vender|aspetter|non vend|comprare di più|panico|paura|tollera|rischi/u', $blob) === 1;

        return ['target' => $target, 'date' => $date, 'income' => $income, 'tolerance' => $tolerance];
    }

    /**
     * Turn the slot state into a system directive. Lists what the user has
     * already answered (so the model never re-asks it) and either points at the
     * single missing theme or, when all four are covered, orders the model to
     * offer the proposal button now instead of asking more questions.
     *
     * @param  array{target: bool, date: bool, income: bool, tolerance: bool}  $slots
     */
    private function slotFillingBriefing(array $slots): string
    {
        $labels = [
            'target' => 'importo obiettivo',
            'date' => 'data/orizzonte',
            'income' => 'reddito e stabilità',
            'tolerance' => 'reazione a un forte calo (tolleranza al rischio)',
        ];

        $covered = [];
        $missing = [];
        foreach ($labels as $key => $label) {
            if ($slots[$key]) {
                $covered[] = $label;
            } else {
                $missing[] = $label;
            }
        }

        $lines = ['STATO INTERVISTA (calcolato dai messaggi già scritti dall\'utente): NON richiedere ciò che è già stato risposto.'];
        if ($covered !== []) {
            $lines[] = 'Già raccolto: '.implode('; ', $covered).'. Dai questi per acquisiti e NON richiederli.';
        }
        if ($missing === []) {
            $lines[] = 'Tutti i temi sono coperti. NON fare altre domande e NON ricominciare da capo: chiama SUBITO offer_goal_proposal (o offer_profile_proposal se l\'intervista è sul profilo) per mostrare il pulsante, poi riassumi brevemente ciò che hai capito.';
        } else {
            $lines[] = 'Ancora da chiarire: '.implode('; ', $missing).'. Fai UNA sola domanda sul primo tema mancante, senza ripetere quelli già coperti.';
        }

        return implode("\n", $lines);
    }

    private function systemPrompt(): string
    {
        return AdvisorPrompt::load('conversation');
    }
}
