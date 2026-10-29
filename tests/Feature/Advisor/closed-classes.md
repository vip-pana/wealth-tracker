# The closed word classes the firewall depends on

Transcribed from a grammar of Italian, **not** from `AdvisorOutputFilter`.

That distinction is the whole point of this file. Four rounds of review closed a
family "by construction" on a class that is closed in the language, and each
time the next round walked out of it through a member the implementation had
not written down. The fifth round's answer was to enumerate the class in a data
provider — and the seventh round showed that a provider typed out of the
constant it tests cannot fail for a missing member, only for a regression. It
proved it by deleting all 33 cardinals from `QUANTITY_IN_LETTERS` and watching
the 55-case provider stay green.

So the enumeration lives here, where it can disagree with the filter. A member
added to the filter and not to this file changes nothing; a member added here
and not to the filter fails the gate. Keep it that way: when a test fails
because of a word below, the fix is in `app/Advisor/AdvisorOutputFilter.php`,
not here. Correct this file only against a grammar.

The numerals are not listed. They are generated in the test from the two
morphological rules Italian actually uses — the tens vowel elides in front of
`uno` and `otto`, and the `tre` of a compound takes an accent — because a list
of a hundred forms is a list somebody will prune.

## The source

The eighth review asked *which* grammar, and the question is fair: all this
file's authority comes from being independent of the filter, and a reader who
cannot find the source can only take the transcription on trust.

- **Treccani, _La grammatica italiana_** (2012), the free online edition at
  <https://www.treccani.it/enciclopedia/elenco-opere/La_grammatica_italiana/> —
  entries *Preposizioni*, *Articoli*, *Aggettivi e pronomi dimostrativi*,
  *Aggettivi e pronomi indefiniti*, *Pronomi personali*, *Numerali*. Chosen as
  the primary source precisely because it is open: anyone reviewing this file
  can check a word against it without owning a book.
- Cross-checked against **L. Serianni, _Grammatica italiana. Italiano comune e
  lingua letteraria_**, UTET — the chapters on the preposition and the pronoun.

Entry titles are given rather than section or page numbers: the entries are
stable and findable, and the numbering differs between the print and the online
edition. Correct this file against those entries, not against the filter.

## Simple prepositions

- di
- a
- da
- in
- con
- su
- per
- tra
- fra

## Articulated prepositions

- del
- dello
- della
- dei
- degli
- delle
- al
- allo
- alla
- ai
- agli
- alle
- dal
- dallo
- dalla
- dai
- dagli
- dalle
- nel
- nello
- nella
- nei
- negli
- nelle
- col
- coi
- sul
- sullo
- sulla
- sui
- sugli
- sulle

## Improper prepositions

- come
- verso
- dopo
- entro
- senza
- sotto
- sopra
- contro
- durante
- presso
- oltre
- mediante
- salvo
- tranne
- eccetto
- secondo
- circa
- lungo
- dietro
- davanti
- nonostante
- malgrado
- attraverso
- tramite
- rasente
- fuorché
- escluso
- incluso
- compreso
- eccettuato
- stante
- tolto
- visto
- causa

## Determiners, singular

- il
- lo
- la
- l'
- un
- uno
- una
- un'
- questo
- questa
- quest'
- codesto
- codesta
- cotesto
- cotesta
- quello
- quella
- quel
- quell'
- tale
- siffatto
- medesimo
- stesso
- mio
- tuo
- suo
- nostro
- vostro
- loro
- altrui
- proprio
- ogni
- ognuno
- qualche
- ciascun
- ciascuno
- nessun
- nessuno
- alcun
- alcuno
- qualcuno
- qualsiasi
- qualunque
- chiunque
- chi
- certo
- tanto
- troppo
- molto
- poco
- parecchio
- diverso
- vario
- tutto
- altro
- taluno
- quanto
- quale

## Determiners, plural

- i
- gli
- le
- questi
- queste
- quei
- quegli
- quelle
- tali
- miei
- mie
- tuoi
- tue
- suoi
- sue
- nostri
- vostri
- propri
- medesimi
- alcuni
- certi
- tanti
- troppi
- molti
- pochi
- parecchi
- parecchissimi
- diversi
- vari
- svariati
- numerosi
- innumerevoli
- moltissimi
- pochissimi
- tantissimi
- tutti
- altri
- taluni
- quali
- quanti

## Dates without a digit

- gennaio
- febbraio
- marzo
- aprile
- maggio
- giugno
- luglio
- agosto
- settembre
- ottobre
- novembre
- dicembre
- lunedì
- martedì
- mercoledì
- giovedì
- venerdì
- sabato
- domenica
- oggi
- domani
- dopodomani
- ieri
- stasera
- stamattina
- stanotte
- stamane
- mattina
- mattino
- pomeriggio
- sera
- serata
- notte
- mezzogiorno
- mezzanotte
- primavera
- estate
- autunno
- inverno
- weekend
- Natale
- Capodanno
- Ferragosto
- Pasqua
- Epifania
- Immacolata
- Ognissanti

## Anaphoric pro-forms

The words whose job is to stand for something named earlier. Demonstratives,
third-person personal pronouns and the pro-forms of totality, from the
*Aggettivi e pronomi dimostrativi*, *Pronomi personali* and *Aggettivi e
pronomi indefiniti* entries.

The clitics — `lo`, `la`, `li`, `le`, `ne`, `gli` — are grammatically in this
class and deliberately **not** listed: spelled alone they are the definite
article, and the filter reads them in a position where it cannot tell the two
apart. The enclitic is declared open in `docs/ai-advisor-firewall.md`; adding
it here would fail the gate for a member the filter is not allowed to close.

- ciò
- esso
- essa
- essi
- esse
- questo
- questa
- questi
- queste
- quello
- quella
- quelli
- quelle
- quel
- quei
- quegli
- codesto
- codesta
- codesti
- codeste
- cotesto
- cotesta
- cotesti
- coteste
- entrambi
- entrambe
- ambedue

## Anaphoric pro-forms used with a noun

The same class, in the forms that cannot stand alone: `quel`, `quei` and
`quegli` are the adnominal allomorphs of `quello`, and `tale` is adnominal. The
split is here and not in the filter — one constant covers both headings — so
that each list can be put in a frame that is actually Italian.

- quel
- quei
- quegli
- tale
- tali

## Anaphoric pro-forms that need an article

The same job, done by a word that is a pronoun only behind an article and
something else without one: `prima` is a preposition in *prima di vendere* and
a pronoun in *a favore della prima*. From the *Aggettivi e pronomi indefiniti*
and *Numerali* entries, the ordinals and the pro-forms of identity and
alterity.

- stesso
- stessa
- stessi
- stesse
- medesimo
- medesima
- medesimi
- medesime
- altro
- altra
- altri
- altre
- primo
- prima
- primi
- prime
- secondo
- seconda
- secondi
- seconde
- terzo
- terza
- terzi
- terze
- ultimo
- ultima
- ultimi
- ultime
- penultimo
- penultima
- penultimi
- penultime

## Articles

From the *Articoli* entry. The elided and plural forms the filter writes
against a following word (`l'`, `un'`) are not listed: they are matched
attached to their noun, not as a word of their own.

- il
- lo
- la
- i
- gli
- le
- un
- uno
- una
