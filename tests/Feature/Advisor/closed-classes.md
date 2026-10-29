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
