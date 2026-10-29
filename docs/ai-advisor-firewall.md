# The AI advisor firewall

The advisor sees the user's positions **by name**, with values and returns, next
to a risk profile built from an interview, a declared goal and a salary. That is
four of the five cumulative tests ESMA applies to a personal recommendation
(ESMA35-43-3861; Art. 4(1)(4) MiFID II, Arts. 18-bis/18-ter TUF) satisfied by the
architecture itself. The fifth — a recommendation about a *specific financial
instrument* — is the only one left, and it is the one this code exists to hold.

Giving investment advice in Italy without authorisation is *abusivismo*, so
"the prompt says not to" is not a control. What follows is what is actually
enforced, and where the enforcement stops.

## Three layers

### Layer 1 — the prompt (necessary, not sufficient)

`resources/advisor-prompts/conversation.txt.example` and `analysis.txt.example`
set the advisor's task and tone. They no longer tell the model that it *is* the
user's financial adviser, and no longer tell it to avoid referring the user to a
professional; they say the opposite.

These files are overridable: a local `<name>.txt` wins over the committed
`<name>.txt.example`, and the `.txt` files are gitignored. So this layer alone
protects nothing — it is the layer that makes the advisor *behave* well, not the
layer that makes it *stay* within the boundary.

### Layer 2 — the boundary clauses, in source

`App\Advisor\AdvisorBoundaries::CLAUSES` holds the non-negotiable limits:
no operative instruction on a specific instrument (by name, ISIN, ticker or
periphrasis), no suitability judgement, no market prediction, no entry/exit
timing, plus the refusal template to use when the user asks anyway. What stays
allowed is spelled out with equal care: asset classes in the abstract, the
user's own numbers, concepts, the profile interview, neutral scenarios, and
what to go and check.

`AdvisorPrompt::load()` (`app/Advisor/AdvisorPrompt.php:25`) prepends that
constant to whatever prompt file it resolves. It is the only way a system prompt
is produced — the two call sites are `ContinueChat::systemPrompt()` and
`PrismAdvisorProvider::systemPrompt()`, and both go through `load()`. A
customised or private prompt therefore cannot ship without the boundary.

The clauses are in Italian because the advisor speaks Italian to the user — and
the rule that it *only* speaks Italian is one of them. That rule used to sit in
the loadable prompt file, which made it configuration; it is load-bearing for
Layer 3, a lexical filter over Italian, so a self-hoster who switched the
prompt's language would have silently turned the filter off. It now lives with
the limits, where changing it is a source edit.

### The Art. 50 disclosure is emitted by the app, not the model

Art. 50(1) AI Act requires telling the user they are interacting with an AI
system. That used to be a clause asking the model to declare itself on its first
reply, and the WEA-10 review measured it at 2/10 against the tuned private
prompt — which is what a legal disclosure looks like when it depends on the
model's cooperation.

It is now markup: `resources/js/Components/Advisor/AiDisclosure.tsx`, rendered
on the advisor page above the fold and outside the "is a model configured"
branch, and again on the Goal page wherever saved advisor text is shown. It
appears before the model has produced a token and it still appears when the
model is down, refusing, or ignoring its prompt. The same component carries the
MiFID disclaimer (informational tool, not investment advice, OCF register),
which is WEA-3 §4.5 and had no implementation at all before. The clauses still
tell the model not to *deny* being an AI if asked; they no longer carry the
disclosure obligation.

### Layer 3 — a deterministic filter on what the model produced

`App\Advisor\AdvisorOutputFilter` does not trust the model. It splits a reply on
sentence and line boundaries and removes any segment that carries **both**:

- an operative directive — a buy/sell/reduce/rebalance verb (including its
  enclitic forms: `vendine`, `ridurla`, `venderglielo`), a soft directive
  (`dovresti`, `ti conviene`, `ti consiglio`), a suitability judgement
  (`adatto a te`, `in linea con il tuo…`), the same act in the **passive**
  (`andrebbe ridotta`, `va venduto` — `PASSIVE_DIRECTIVES`), the same act turned
  into a **noun** (`Raccomandazione: riduzione di…`, `Un taglio del 20%
  avrebbe senso` — `NOMINAL_DIRECTIVES`), or a directive in **another language**
  (`FOREIGN_DIRECTIVES`); **and**
- a reference to a specific instrument — matched against the user's own asset
  names, tickers, ISINs and category names (soft-deleted rows included: a
  position sold is still a named instrument), an ISIN- or ticker-shaped token
  for something they do not hold, a **periphrasis** that singles out one holding
  without naming it (`la tua posizione più grande`, `quello che rende di meno` —
  `PERIPHRASIS`), or a **named instrument the user does not own**
  (`KNOWN_INSTRUMENTS`: issuers, indices, crypto, brokers).

`KNOWN_INSTRUMENTS` exists because the protected set is built from the user's
own holdings, so by construction it could not see a recommendation to buy
something they do not hold — which is the least ambiguous personal
recommendation there is. It is a list of the names an Italian retail investor
actually meets, not an instrument database, and it is meant to be extended.

A directive is judged against the instrument named in its own segment, in the
**previous three** segments, or in the one that follows (a heading names its
subject on the next line). One segment of lookback was the window the WEA-10
harness walked through: the model names a position, describes it for a sentence
or two, then acts on it with a pronoun. The wider window is kept from eating
legitimate text by `EXPLANATORY` — a neighbouring-segment instrument does not
count when this segment is explaining rather than instructing (`ribilanciare
significa…`). A colon is deliberately **not** a segment boundary: splitting on
it put `Raccomandazione:` and the instrument in different segments, where
neither half tripped the co-occurrence rule.

Three things it deliberately does **not** remove:

- **Asset classes.** `riduci la liquidità` passes; `riduci VWCE` does not.
  Generic advice about *types* of instrument is outside the definition of
  investment advice (ESMA ¶48-50), and removing it would gut the product for no
  compliance gain. The class names are listed in `GENERIC_CLASS_NAMES`, matched
  accent-insensitively, and excluded from the protected set even when the user
  named an asset or category that way.
- **"What to check".** `dovresti verificare il TER di VWCE` passes: the soft
  directives are cleared when an epistemic verb follows within a few words. This
  is the advisor's most useful output and the prompt explicitly asks for it.
- **Domain acronyms.** `TER`, `KID`, `PAC`, `OCF`, currency codes and the words
  the model capitalises for emphasis are listed in `NOT_TICKERS`, so they are not
  mistaken for a ticker.

When part of a reply is removed, the rest is kept and a footer explains the gap.
When nothing survives, the whole reply is replaced by the refusal template. Each
redaction is logged (`Advisor reply filtered`) with counts only — never content.

The removal notice says what the filter *did*, not what the model wrote: this is
a lexical check, so a removal means "this matched", not "this was advice". The
earlier wording asserted the latter, and WEA-14 found it attached to five
compliant refusals. Removing too much is not the safe direction of error — see
below.

**Protected speech.** A segment that refuses, discloses, declares the AI, asks
the user to reflect, or poses a hypothesis is never removed, however many
directive tokens it carries (`isProtectedSpeech()`). A refusal has to name what
it refuses, so by construction it pairs a directive verb with an instrument name
— the exact shape the co-occurrence rule fires on. Each licence is withheld on
tested terms, because a licence is also a way through: a directive before the
refusal frame is not covered by it, an adversative ends it, an imperative aimed
at an operation is not a reflection, and the interrogative licence covers
judgement words only — never an operative verb, a proposed number, or an
assertion with a tag bolted on.

**The filter runs twice, on purpose.**

- *Write side*, before the reply is persisted: all four paths in `ContinueChat`
  (`run`, `complete`, `proposeNow`, `runStreaming`) and `GenerateAdvisorReport`.
- *Read side*, before the reply is serialised: `AdvisorMessage::displayContent()`
  (`app/Models/AdvisorMessage.php:55`), called by every serializer that sends a
  message to the UI — `IndexController`, `StatusController`, `MessageController`,
  `ProposeController`.

**Widgets go through it too.** `content` was filtered from the start and
`widgets` was not, one line below it in the same serializer — and the widgets
are the surface the product uses to give operative instructions, so they were
the one that most needed it. `filterWidgets()` walks a widget payload and
screens the keys the model actually writes (`MODEL_WRITTEN_KEYS`: `action`,
`rationale`, `notes`, `label`, `description`); app-computed values — chart
series, totals, percentages — are left alone, because the model never writes
those. `filterField()` is the same check as `filter()` without the
conversational wrapping: a widget field is a line in a card, so a removed one
becomes the marker alone rather than the refusal template and a footer.

**Saved milestones are the one piece of advisor text that outlives the chat.**
`POST /advisor/goal/milestones` persists a proposal's `notes` / `action` /
`rationale` into `goal_milestones`, and the Goal page then shows them for good —
outside the advisor, with none of its framing and none of its footer. They are
filtered on the way in (`StoreGoalMilestonesController`) *and* on the way out
(`FetchGoalData`), the second because rows saved before this existed are already
in the database. The tool description that asks for that text was rewritten at
the same time: it used to request `"Sposta il 5% del portafoglio da Bitcoin a
Obbligazioni e rivedi il PAC"` as its example — an operative instruction, a
named instrument and a personalised percentage, i.e. precisely the output the
clauses forbid. It now asks for the change described by asset *class*.

Write-side filtering alone would leave anything already in the database — stored
before this existed, or by a future path that forgets to call the filter — on
screen unchecked. Read-side filtering alone would leave the unfiltered text in
the database, which is the copy that matters if anyone ever asks what the system
said. The filter is idempotent, so running it in both places costs nothing.
`displayContent()` returns user turns untouched: those are the user's own words.

## Where this stops — read this part

**Streaming is not covered.** `ContinueChat::runStreaming()` filters what it
stores but cannot filter deltas already handed to the caller, because the filter
screens whole sentences. No route reaches it today and none should be added
without first buffering to a sentence boundary and filtering before emitting.
The comment on that method says so.

**A self-hoster can disable all three layers.** Anyone who controls the machine
can edit `AdvisorBoundaries.php` and delete the call in `AdvisorOutputFilter`.
That is not fixable, and pretending otherwise would be worse than saying it.
What the design buys is a specific, narrower property:

> The distributed version is safe by default, and disabling the boundary is a
> deliberate source-code edit, not a configuration change.

Before this work, dropping the guardrail meant writing a `conversation.txt` — an
untracked config file, no code change, no diff, nothing to notice. Now it means
editing committed source, which shows up in `git diff`, in review, and in the
AGPL obligation to publish the modified source of a network-served version. The
line moved from "a config file nobody reviews" to "a commit somebody signed".

**It is a lexical filter, not a comprehension engine.** It catches the phrasings
a model actually produces, in Italian, matched against the user's real holdings.

This page used to describe the residual gap as "a recommendation expressed
entirely by periphrasis with no directive verb and no instrument name". The
WEA-10 review showed that statement was narrower than the real gap, in a way
that flattered the filter: `Vendi la tua posizione più grande` has a directive
verb and no instrument name, and it went straight through. Periphrasis and the
non-imperative moods are covered now; the honest statement of what is left is:

> The filter matches *surface forms*. A recommendation carried entirely by
> implication — a sequence of individually innocuous sentences whose only
> possible reading is "sell this" — is not reachable by any word list, and
> neither is one in a language whose vocabulary is not enumerated here. The
> foreign-language lists are a backstop against the model drifting into
> English, not coverage of those languages.

Layer 2 forbids exactly these; Layer 3 cannot verify them. The clauses and the
filter are complementary for this reason, and neither is a substitute for the
other.

**The filter is only as good as its word lists.** New phrasings and new Italian
verbs will be found; `HARD_DIRECTIVES`, `SOFT_DIRECTIVES`, `SUITABILITY`,
`PASSIVE_DIRECTIVES`, `NOMINAL_DIRECTIVES`, `FOREIGN_DIRECTIVES`, `PERIPHRASIS`,
`KNOWN_INSTRUMENTS`, `NOT_TICKERS` and `GENERIC_CLASS_NAMES` are meant to be
extended, with a test per addition.
`tests/Feature/Advisor/AdvisorOutputFilterTest.php` covers both directions: what
must be removed, and what must survive. It carries both bypass harnesses — the
WEA-10 one (50 phrases across eight categories, rebuilt from that review's
document) and the WEA-14 one (50 more) — plus a false-positive guard for each
risky addition, because widening the lists is the obvious way to start eating
the analysis.

**Measured, not asserted.** On the 50 reconstructed WEA-10 phrases: 43 passed
the filter before that work, 0 after. (The review's own harness scored 38/50;
the reconstruction is slightly harsher than the original.) On the 50 WEA-14
phrases: 42 passed when that review measured them — 47 before WEA-13 widened the
lists — and 0 pass now. Closing them needed more than more words: the commonest
shape of an allocation recommendation carries no verb at all, so the filter had
to learn to read the notation (`VWCE: 40% → 25%`), the move stated in euro, and
the proposed-allocation table.

**Over-blocking is not the prudent direction of error, and the claim that it was
is withdrawn.** WEA-14 ran the filter over six real model replies and it altered
all six, removing thirteen segments: the MiFID disclaimer, the Art. 50(1)
declaration, and reflection questions the clauses list among the *permitted*
outputs. The cause is structural — a refusal has to name what it refuses, so it
carries a directive verb and an instrument name in one segment by necessity —
and the app then captioned the gap "an operative instruction was removed",
which accuses a compliant reply of non-compliance. Deleting the disclaimer is a
compliance loss, not a conservative one.

So the hand-written false-positive set scoring zero was never the right measure:
invented phrases are short and do not name the positions, while a real reply
names them on every line, which understates the rate by construction. The
measure that counts is `tests/Feature/Advisor/LiveModelProbeTest.php` — fourteen
questions through the shipped prompt, tool loop and budget, against whatever
`services.advisor.driver` selects. It is not part of the gate (it costs a model
call); run it with `LIVE_MODEL_PROBE=1` after touching the filter, and read the
segments it reports as removed rather than the count.

Measured on `regolo` / `qwen3.5-122b`: **18 segments removed across 9 of 14
replies before this work, 0 after** — and all 18 were compliant speech, since
none of the fourteen replies contained a forbidden recommendation. The gates
that fixed it are the protected-speech ones in `isProtectedSpeech()`: a refusal,
a disclaimer, a reflection question, an interrogative, a hypothetical. Each is
withheld on terms that are themselves tested, because a licence is also a way
through: the interrogative one covers judgement words only, never an operative
verb, a proposed number, or an assertion with a tag bolted on.

**The briefing is a `system` message, so what goes into it is an injection
surface.** `RenderAdvisorContext::userText()` collapses control characters and
wraps a user-controlled value in guillemets. The profile's `name`, `notes` and
`memory` skipped it: a crafted value escaped its bullet and opened what read as
a new top-level section of the system message. `memory` is written by
`remember_fact`, the only profile tool that writes with no confirmation click,
so the value persisted and was replayed on every later turn. All three go
through `userText()` now, with a regression test per field.

What the guillemets *mean* is declared in `AdvisorBoundaries::CLAUSES`, not in
the prompt files. WEA-14 found the earlier claim — that the prompt declared it —
true only of `analysis.txt.example` and of one machine's private
`conversation.txt`; on a clean clone, on the conversational path, they were two
symbols with no stated meaning. `userText()` also escapes guillemets inside the
value, because the defence rests on a single character and a value carrying a
closing `»` could otherwise end its own delimiter.

## Related

- Compliance analysis and the drafted clauses: WEA-3, §4.2-4.4.
- Token/spend caps on the advisor: `App\Advisor\AdvisorBudget`.
