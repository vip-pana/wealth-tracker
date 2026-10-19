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
timing, plus the AI-disclosure requirement of Art. 50 AI Act and the refusal
template to use when the user asks anyway. What stays allowed is spelled out
with equal care: asset classes in the abstract, the user's own numbers,
concepts, the profile interview, neutral scenarios, and what to go and check.

`AdvisorPrompt::load()` (`app/Advisor/AdvisorPrompt.php:25`) prepends that
constant to whatever prompt file it resolves. It is the only way a system prompt
is produced — the two call sites are `ContinueChat::systemPrompt()` and
`PrismAdvisorProvider::systemPrompt()`, and both go through `load()`. A
customised or private prompt therefore cannot ship without the boundary.

The clauses are in Italian because the advisor speaks Italian to the user.

### Layer 3 — a deterministic filter on what the model produced

`App\Advisor\AdvisorOutputFilter` does not trust the model. It splits a reply on
sentence and line boundaries and removes any segment that carries **both**:

- an operative directive — a buy/sell/reduce/rebalance verb (including its
  enclitic forms: `vendine`, `ridurla`, `venderglielo`), a soft directive
  (`dovresti`, `ti conviene`, `ti consiglio`), or a suitability judgement
  (`adatto a te`, `in linea con il tuo…`); **and**
- a reference to a specific instrument — matched against the user's own asset
  names, tickers, ISINs and category names (soft-deleted rows included: a
  position sold is still a named instrument), or an ISIN- or ticker-shaped token
  for something they do not hold.

A directive is judged against the instrument named in its own segment *or the
one before it*, because the model routinely describes a position in one sentence
and acts on it in the next.

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
Over-blocking is the intended direction of error.

**The filter runs twice, on purpose.**

- *Write side*, before the reply is persisted: all four paths in `ContinueChat`
  (`run`, `complete`, `proposeNow`, `runStreaming`) and `GenerateAdvisorReport`.
- *Read side*, before the reply is serialised: `AdvisorMessage::displayContent()`
  (`app/Models/AdvisorMessage.php:55`), called by every serializer that sends a
  message to the UI — `IndexController`, `StatusController`, `MessageController`,
  `ProposeController`.

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
It does not catch a recommendation expressed entirely by periphrasis with no
directive verb and no instrument name ("the one that is doing worst deserves a
rethink"). Layer 2 forbids exactly that; Layer 3 cannot verify it. The clauses
and the filter are complementary for this reason, and neither is a substitute
for the other.

**The filter is only as good as its word lists.** New phrasings and new Italian
verbs will be found; `HARD_DIRECTIVES`, `SOFT_DIRECTIVES`, `SUITABILITY`,
`NOT_TICKERS` and `GENERIC_CLASS_NAMES` are meant to be extended, with a test
per addition. `tests/Feature/Advisor/AdvisorOutputFilterTest.php` covers both
directions: what must be removed, and what must survive.

## Related

- Compliance analysis and the drafted clauses: WEA-3, §4.2-4.4.
- Token/spend caps on the advisor: `App\Advisor\AdvisorBudget`.
