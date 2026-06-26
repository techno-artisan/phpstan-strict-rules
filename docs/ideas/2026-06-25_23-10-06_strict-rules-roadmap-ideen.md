# Ideenkatalog: Wertvollste nächste Ergänzungen für `techno-artisan/phpstan-strict-rules`

> **Status:** Lebende Roadmap — teilweise umgesetzt (siehe Abschnitt 0)
> **Datum:** 2026-06-25 23:10:06 · **aktualisiert:** 2026-06-26 (nach `v0.1.0-beta.4`)
> **Stand des Pakets:** `0.1.0-beta.4`, vier Regeln
> **Zweck:** Strukturierte Sammlung und Bewertung möglicher technischer Ergänzungen,
> als Grundlage für die Auswahl der nächsten Design-Spec.

---

## 0. Umsetzungsstand (Stand `v0.1.0-beta.4`)

Seit der Erstfassung dieses Katalogs umgesetzt:

- **✅ A1 — `DisallowLooseComparisonRule`** — veröffentlicht in **`v0.1.0-beta.2`**.
  Verbietet `==` / `!=` / `<>` rein syntaktisch (inkl. `== null`); Identifier
  `technoArtisan.looseComparison`. Schließt die „kein loser Vergleich"-These.
- **✅ B1 (schlanke Variante) — Reflection-Guard** — in `v0.1.0-beta.2`.
  `RulesRegistrationTest` entdeckt jede Klasse in `src/Rules/` per Reflection und
  erzwingt Registrierung in `rules.neon` + `final` + `strict_types`. **Bewusst NICHT**
  umgesetzt: das `#[StrictRule]`-Attribut und die README-Generierung (YAGNI bei vier
  Regeln) — bleiben als optionaler Ausbau dokumentiert (siehe B1 unten).
- **✅ Doku (Teil von C3) — README-Überarbeitung** — Tagline, „Why this package?"-
  Positionierung ggü. `phpstan/phpstan-strict-rules`, Badges, Vorher/Nachher-Beispiel
  + Identifier je Regel, „Ignoring a rule", Requirements/Development.
- **✅ C1 + C2 + C3 — Qualitäts-Infrastruktur** — veröffentlicht in **`v0.1.0-beta.3`**
  (Design-Spec `docs/superpowers/specs/2026-06-26-ci-quality-infrastructure-design.md`).
  CI-Matrix PHPStan lowest/highest (C1), Infection mit MSI 100 % (C2), 100 % Line-
  Coverage-Gate + `composer normalize` + NEON-Lint (C3). Schließt Lücke #3.
- **✅ Ehrliche README-Neupositionierung** — **`v0.1.0-beta.4`**. Neue
  „Relationship to phpstan/phpstan-strict-rules"-Sektion mit Overlap-Map; die README
  führt nun mit der einzig eindeutig einzigartigen Regel (`TypedClassConstantRule`).
  **Strategische Korrektur:** Die in diesem Katalog (u. a. A1, Abschnitt 6) noch tragende
  These „rein syntaktisch = Differenzierungsmerkmal" wurde dabei als **faktisch falsch**
  verworfen — Details in Abschnitt **0.1**.

**Damit erledigt:** Lücken #1 (loser Vergleich), #2 (manuelle Selbst-Disziplin),
#3 (CI-Matrix / Mutation-Tests / Coverage) und #4 (dünne Doku).

**Nächste sinnvolle Schritte:** **zuerst** die in Abschnitt 0.1 beschriebene
Positionierungsfrage klären. Die zuvor empfohlenen **A2** (`@`) und **A5** (leeres `catch`)
bleiben attraktiv (kein Overlap mit `phpstan-strict-rules`), stehen aber bis dahin unter
Vorbehalt; **A4** (`switch`) weiterhin nur als opt-in (siehe Abschnitt 5).

---

## 0.1 Strategische Neubewertung nach `v0.1.0-beta.4`

`v0.1.0-beta.4` hat die Positionierung gegenüber
[`phpstan/phpstan-strict-rules`](https://github.com/phpstan/phpstan-strict-rules) auf
Faktenbasis korrigiert. Das berührt die **Begründungsgrundlage** dieses Katalogs, nicht
den technischen Umsetzungsstand aus Abschnitt 0.

**Overlap-Realität — drei der vier Regeln sind redundant:**

| Konstrukt | `phpstan-strict-rules` | dieses Paket | Status |
| --------- | ---------------------- | ------------ | ------ |
| `empty()` | verbietet alle (`DisallowedEmptyRule`) | verbietet alle | **Overlap** |
| `==` / `!=` / `<>` | verbietet alle (`DisallowedLooseComparisonRule`) | verbietet alle | **Overlap** |
| loses `in_array`/`array_search`/`array_keys` | type-aware + `base64_decode` (`StrictFunctionCallsRule`) | rein syntaktisch (verlangt literal `true`) | **Overlap** (kleine Restdifferenz) |
| untypisierte Klassenkonstanten | — (kein Äquivalent) | **`TypedClassConstantRule`** | **einzigartig** |

**Folgen für die These dieses Katalogs:**

- Die in **A1** und **Abschnitt 6** noch tragende Aussage — `phpstan-strict-rules` lasse
  `==` zwischen gleichen Typen zu, weshalb die rein syntaktische Strenge das
  **Differenzierungsmerkmal** sei — ist **faktisch falsch**: `DisallowedLooseComparisonRule`
  verbietet `==`/`!=` ebenso kompromisslos, und beim `empty()`-Verbot gilt dasselbe.
- Es bleibt nur eine schmale syntaktische Restdifferenz bei der Array-Such-Regel (sie
  flaggt auch `in_array($x, $list, $flag)`, wenn `$flag` lediglich als `true` *inferiert*
  ist) — ein Detail, kein tragfähiges Alleinstellungsmerkmal.
- **Eindeutig einzigartig ist allein `TypedClassConstantRule`.**

**Offene Produktfrage (zurückgestellt):** Sollen die drei redundanten Regeln überhaupt im
Paket bleiben? Die README löst die Reibung vorerst pragmatisch (Anleitung, wie man die
Doppel-Meldungen bei Parallelbetrieb beider Pakete abschaltet). Solange das nicht
entschieden ist, ist „das strict-Regelset mit weiteren Verbots-Regeln abrunden" **keine
unstrittige Default-Richtung** mehr — siehe die neu bewertete Roadmap in Abschnitt 5.

---

## 1. Ausgangslage

Das Paket ist eine PHPStan-Extension (`type: phpstan-extension`) mit zum Zeitpunkt der
Erstfassung **drei Regeln** (heute **vier** — siehe Abschnitt 0):

| Regel | Knoten | Charakter |
| ----- | ------ | --------- |
| `DisallowEmptyConstructRule` | `Expr\Empty_` | syntaktisch, kompromisslos |
| `TypedClassConstantRule` | `Stmt\ClassConst` | syntaktisch (nutzt `Scope` nur für den Klassennamen) |
| `DisallowLooseInArrayRule` | `Expr\FuncCall` | syntaktisch, kompromisslos |
| `DisallowLooseComparisonRule` *(seit `v0.1.0-beta.2`)* | `Expr\BinaryOp` | syntaktisch, kompromisslos (filtert `Equal`/`NotEqual`) |

**Beobachtete Eigenschaften der Codebase (Stärken, auf denen aufzubauen ist):**

- Roter Faden / These: **„loose semantics hide bugs"** — lose `empty()`-Semantik raus,
  loses `in_array()/array_search()/array_keys()` raus.
- Alle Regeln sind **rein syntaktisch** (AST statt Typinferenz): vorhersehbar,
  kompromisslos, keine `Scope`-Abhängigkeit außer für Metadaten.
- Durchgängige Disziplin: jede Regel ist `final`, hat `declare(strict_types=1)`,
  einen `RuleTestCase`, eine Fixture (per Pfad geladen, außerhalb PSR-4), eine
  README-Zeile, einen CHANGELOG-Eintrag und eine Design-Spec.
- Identifier-Konvention `technoArtisan.<camelCase>`.
- Dogfooding: `composer phpstan` analysiert `src/` auf `level: max` mit den eigenen Regeln.

**Beobachtete Lücken / Reibungspunkte (Ansatzpunkte):**

1. **Kernthese unvollständig:** Das Paket verbietet loses `empty()` und loses
   Array-Suchen, lässt aber den **häufigsten** losen Vergleich überhaupt — `==` / `!=`
   — durch. Für ein Paket dieses Namens ist das die auffälligste Lücke.
   ✅ *Erledigt in `v0.1.0-beta.2` (A1 — `DisallowLooseComparisonRule`).*
2. **Manuelle Selbst-Disziplin:** `RulesRegistrationTest` zählt jede Regel **einzeln
   von Hand** auf (`assertContains(...)` pro Klasse). Eine neue Regel, die zu
   registrieren oder zu dokumentieren vergessen wird, fällt nicht automatisch auf —
   dieselbe „vergiss-nicht"-Falle, die die `CLAUDE.md`-Checkliste manuell abzufedern
   versucht. Eine nicht in `rules.neon` eingetragene Regel läuft beim Konsumenten
   **stillschweigend nie** — ein echtes nutzerseitiges Risiko.
   ✅ *Erledigt in `v0.1.0-beta.2` (B1 schlank — Reflection-Guard im `RulesRegistrationTest`).*
3. **Schmale CI-Absicherung:** genau **eine** PHP-Version (8.5) und **eine**
   PHPStan-Version. Keine Lowest/Highest-Matrix, keine Mutation-Tests, keine Coverage.
   ✅ *Erledigt (C1 + C2 + C3 — CI-Matrix, Infection, Coverage-Gate, `composer normalize`, NEON-Lint).*
4. **Dünne Doku:** README ist eine reine Tabelle ohne Vorher/Nachher-Beispiele oder
   Begründungen — für Adoption und Überzeugungskraft ausbaufähig.
   ✅ *Weitgehend erledigt in `v0.1.0-beta.2` (README-Überarbeitung; vgl. C3).*

---

## 2. Bewertungskriterien

Jede Idee wird entlang von fünf Achsen eingeordnet (Hoch / Mittel / Niedrig):

- **Direktnutzen** — fängt die Idee echte Bugs im Code der Konsumenten?
- **Innovation** — wie originell/differenzierend gegenüber bestehenden Rule-Paketen?
- **Aufwand** — Implementierungs- und Pflegekosten.
- **Risiko** — Gefahr von False Positives, Kontroversität, Wartungslast.
- **Fit** — passt es zur bestehenden Philosophie (syntaktisch, kompromisslos,
  „loose semantics hide bugs")?

---

## 3. Ideenkatalog

### Gruppe A — Nutzer-orientierte Regeln (fangen Bugs im Konsumenten-Code)

#### A1 — `DisallowLooseComparisonRule` (Flaggschiff-Kandidat) — ✅ UMGESETZT (`v0.1.0-beta.2`)

**Idee:** Verbietet die losen Vergleichsoperatoren `==`, `!=` und `<>`; erzwingt
`===` / `!==`.

**Motivation / Fit:** Schließt die **„kein loser Vergleich"-Trilogie**
(`empty()` → loses Array-Suchen → lose Operatoren) zu einer kohärenten These zusammen.
`==`-Bugs sind allgegenwärtig und subtil:

```php
0   == 'foo';     // in alten PHP-Versionen true; Quelle klassischer Auth-Bypässe
'1e1' == '10';    // true  — numerische String-Koerzierung
'1'  == '01';     // true
null == false;    // true
in_array(...) ... // bereits abgedeckt; der Operator selbst bisher nicht
```

**Design-Skizze:**
- `getNodeType()` → der Rule muss auf **zwei** Knoten reagieren
  (`Expr\BinaryOp\Equal`, `Expr\BinaryOp\NotEqual`). Da `getNodeType()` nur **einen**
  Typ zurückgibt, ist der saubere Weg, auf die gemeinsame Oberklasse
  `Expr\BinaryOp` zu lauschen und im `processNode()` per `instanceof` auf
  `Equal`/`NotEqual` zu filtern (alle anderen `BinaryOp` ignorieren). `<>` parst
  PHP-seitig zu demselben `NotEqual`-Knoten und ist damit automatisch mit abgedeckt.
- Eine Fehlermeldung pro Operator-Vorkommen; Identifier `technoArtisan.looseComparison`.

**Offene Designfragen (die diese Regel zu einer echten Spec-Diskussion machen):**
- **`== null` erlauben?** `$x === null` ist bereits strikt und idiomatischer; viele
  Codebasen schreiben aber gewohnheitsmäßig `$x == null`. *Empfehlung: kompromisslos
  bleiben* (kein Sonderfall) — konsistent mit `DisallowEmptyConstructRule` und
  `DisallowLooseInArrayRule`, die ausdrücklich **keine** Ausnahmen kennen. Ein
  optionales `allowNullComparison`-Flag wäre denkbar, widerspräche aber dem
  bisherigen „keine End-Nutzer-Konfigurierbarkeit"-Prinzip (siehe Spec von
  `DisallowLooseInArrayRule`).
- **Abgrenzung zu PHPStan-Bordmitteln:** ⚠️ *In `v0.1.0-beta.4` korrigiert (siehe
  Abschnitt 0.1).* Die ursprüngliche Annahme — `phpstan/phpstan-strict-rules` lasse `==`
  zwischen gleichen Typen zu, weshalb die rein syntaktische Strenge das
  **Differenzierungsmerkmal** sei — ist **falsch**: dessen `DisallowedLooseComparisonRule`
  verbietet `==`/`!=` ebenso kompromisslos. Diese Regel **überlappt** somit vollständig
  und ist nur enthalten, damit das Paket allein lauffähig ist.
- Yoda- vs. Nicht-Yoda-Schreibweise ist irrelevant (Operator-Knoten ist derselbe).

**Bewertung:** Direktnutzen **Hoch** · Innovation **Mittel** · Aufwand **Niedrig** ·
Risiko **Mittel** (manche Teams empfinden `== null` als legitim) · Fit **Sehr hoch**.

---

#### A2 — `DisallowErrorSuppressionRule`

**Idee:** Verbietet den `@`-Fehlerunterdrückungs-Operator.

**Motivation / Fit:** `@` versteckt Fehler statt sie zu behandeln — dasselbe
„versteckt Bugs"-Narrativ. Perfekt im syntaktischen Stil.

**Design-Skizze:**
- `getNodeType()` → `Expr\ErrorSuppress`.
- Eine Meldung pro Vorkommen; Identifier `technoArtisan.errorSuppression`.
- Triviale, kompromisslose `processNode()` (analog `DisallowEmptyConstructRule`:
  bloße Existenz des Knotens genügt).

**Bewertung:** Direktnutzen **Mittel-Hoch** · Innovation **Niedrig** ·
Aufwand **Sehr niedrig** · Risiko **Niedrig** · Fit **Hoch**.
Solider, risikoarmer vierter Baustein; weniger „Flaggschiff" als A1.

---

#### A3 — `RequireStrictTypesDeclarationRule`

**Idee:** Jede PHP-Datei muss `declare(strict_types=1);` enthalten.

**Motivation / Fit:** Das Paket **schreibt sich diese Regel in der `CLAUDE.md` selbst
vor** — sie als Regel anzubieten ist maximal „on brand" (Dogfooding nach außen).

**Design-Skizze:**
- Erfordert einen **datei-ebenen** Knoten, da Abwesenheit detektiert wird:
  `getNodeType()` → `PHPStan\Node\FileNode`. Im `processNode()` die Top-Level-Statements
  durchsehen und prüfen, ob ein `Stmt\Declare_` mit `strict_types = 1` vor dem ersten
  „echten" Statement steht.
- Identifier `technoArtisan.requireStrictTypes`.
- Nuance: Dateien ohne PHP-Code / reine Sichtbarkeits-Edgecases, `declare` mit
  mehreren Direktiven, korrekte Positionsprüfung.

**Abgrenzung:** Es gibt bereits etablierte Sniffs/Regeln dafür (Slevomat,
`phpstan-strict-rules` teils). Novelty daher gering, Fit aber hoch.

**Bewertung:** Direktnutzen **Mittel** · Innovation **Niedrig** ·
Aufwand **Mittel** (FileNode-Logik) · Risiko **Niedrig-Mittel** · Fit **Hoch**.

---

#### A4 — `DisallowLooseSwitchRule` (bzw. „bevorzuge `match`")

**Idee:** `switch` vergleicht intern lose (`==`). Die Regel meldet `switch` und legt
`match(true)`/`match` nahe, das strikt (`===`) vergleicht.

**Motivation / Fit:** Direkte Fortsetzung der losen-Vergleich-These auf eine
Kontrollstruktur. `match` ist die strikte, seit PHP 8 verfügbare Alternative.

**Design-Skizze:**
- `getNodeType()` → `Stmt\Switch_`. Kompromisslose Meldung pro `switch`.
- Identifier `technoArtisan.looseSwitch`.

**Risiko/Kontroverse:** `match` ist nicht in jedem Fall ein 1:1-Ersatz (Fallthrough,
mehrere Statements pro Zweig). Eine generelle `switch`-Verbannung ist meinungsstärker
als die übrigen Regeln. Eher als **opt-in** denkbar.

**Bewertung:** Direktnutzen **Mittel** · Innovation **Mittel** · Aufwand **Niedrig** ·
Risiko **Hoch** (Kontroversität) · Fit **Mittel-Hoch**.

---

#### A5 — `DisallowEmptyCatchRule`

**Idee:** Verbietet leere `catch`-Blöcke, die Ausnahmen stillschweigend schlucken.

**Design-Skizze:** `getNodeType()` → `Stmt\Catch_`; melden, wenn `stmts === []`.
Identifier `technoArtisan.emptyCatch`. Nuance: ein `catch` mit nur einem Kommentar
gilt AST-seitig als leer — bewusst entscheiden, ob das ok ist.

**Bewertung:** Direktnutzen **Mittel** · Innovation **Niedrig** · Aufwand **Niedrig** ·
Risiko **Niedrig** · Fit **Hoch** („versteckt Fehler").

---

#### A6 — Weitere risikoarme Kandidaten (Kurzliste)

- `DisallowVariableVariablesRule` — `$$x` (Node `Expr\Variable` mit nicht-String-Name).
  Fit hoch, Nutzen mittel.
- `DisallowEvalRule` / `DisallowGotoRule` — `Expr\Eval_` / `Stmt\Goto_`. Sehr einfach,
  Nutzen punktuell.
- `DisallowImplicitOctalRule` o. ä. — Nische.

> Diese eignen sich gut, um später ein **kohärentes „strict"-Regelset** abzurunden,
> sind aber einzeln weniger flaggschiffwürdig als A1.

---

### Gruppe B — Architektur / Selbst-Disziplin (skaliert die Qualität des Pakets)

#### B1 — Selbst-erzwingende Meta-Architektur via Regel-Metadaten-Attribut ⭐ (innovativster Kandidat) — ✅ TEILWEISE UMGESETZT (`v0.1.0-beta.2`: schlanker Reflection-Guard; Attribut + README-Codegen zurückgestellt)

**Idee:** Jede Regel trägt ihre Metadaten deklarativ über ein **PHP-8.5-Attribut**:

```php
#[StrictRule(
    identifier: 'technoArtisan.looseComparison',
    summary: 'Reports loose == / != comparisons; use === / !== instead.',
    since: '0.2.0',
)]
final class DisallowLooseComparisonRule implements Rule { /* … */ }
```

Ein **einziger** Meta-Test (über Reflection/`Finder` über `src/Rules/`) erzwingt dann
automatisch für **jede** Regelklasse:

1. ist in `rules.neon` registriert (ersetzt die manuelle `assertContains`-Liste in
   `RulesRegistrationTest`);
2. ist `final` und hat `declare(strict_types=1)`;
3. trägt das `#[StrictRule]`-Attribut;
4. der dort deklarierte Identifier beginnt mit `technoArtisan.` und stimmt mit dem im
   Code via `->identifier(...)` verwendeten überein;
5. ist im README dokumentiert.

**Optionaler Ausbau (Single Source of Truth):** Ein kleines Skript/ein Test
**generiert oder verifiziert** die README-Regeltabelle aus den Attributen — kein
Doku-Drift mehr.

**Warum das die wertvollste *strukturelle* Ergänzung ist:**
- Tötet **dauerhaft** die in der Ausgangslage (#2) und im Projekt-Memory notierte
  Registrierungs-/Doku-Falle — nicht per Checkliste, sondern per ausführbarem Test.
- Verwandelt die manuelle `CLAUDE.md`-„Adding a rule"-Checkliste in **erzwungene
  Invarianten**. Jede künftige Regel erbt die Disziplin automatisch.
- Stärkste Erzählung: **ein strict-rules-Paket, das streng mit sich selbst ist** —
  Dogfooding der eigenen Werte nach innen. Hohe Überzeugungskraft gegenüber
  potentiellen Nutzern, die Verlässlichkeit suchen.

**Risiko/Aufwand:** Reflection-/Finder-Logik und ein Attribut-Typ sind überschaubar;
Hauptaufwand ist Sorgfalt beim Meta-Test (Pfade, Fixtures ausschließen).

**Bewertung:** Direktnutzen **Mittel** (indirekt: Vertrauen/Korrektheit) ·
Innovation **Hoch** · Aufwand **Mittel** · Risiko **Niedrig** · Fit **Sehr hoch**.

> **Sequenzierungs-Hinweis:** Der Wert von B1 steigt mit jeder weiteren Regel.
> Idealer Zeitpunkt: **direkt nachdem** die nächste Regel (z. B. A1) die Regelzahl auf
> vier erhöht hat — dann zahlt sich die Automatisierung sofort sichtbar aus.

---

#### B2 — Abstrakte Basisklasse für „verbotener-Konstrukt"-Regeln (bewusst zurückgestellt)

**Idee:** `DisallowEmptyConstructRule`, ein künftiges `DisallowErrorSuppressionRule`
usw. teilen das Muster „Knoten X existiert → eine Meldung". Eine abstrakte
`AbstractBannedConstructRule` könnte das bündeln.

**Bewertung / Empfehlung:** **YAGNI, vorerst zurückstellen.** Bei aktuell drei sehr
unterschiedlichen Knotentypen schafft die Abstraktion mehr Kopplung als Nutzen.
Erst sinnvoll, wenn ≥ 3 echte „bloße-Existenz"-Regeln existieren. Hier dokumentiert,
damit die Option bei wachsendem Regelset nicht vergessen wird.

---

### Gruppe C — Qualitäts-Infrastruktur (stärkt Vertrauen & Kompatibilität)

#### C1 — CI-Matrix: PHPStan lowest vs. latest

**Idee:** Die CI zusätzlich mit `--prefer-lowest` (PHPStan `2.0.x`) **und** mit der
neuesten `^2`-Version laufen lassen. Optional ein Job, der die Regeln gegen ein kleines
Beispiel-Konsumentenprojekt ausführt (Integrations-Smoke-Test).

**Nutzen:** Das Paket wird von vielen Projekten mit unterschiedlichen PHPStan-Patch-
Ständen konsumiert. Eine Lowest/Highest-Matrix fängt API-Drift in der PHPStan-Rule-API
früh ab. (Eine PHP-Matrix ist mangels `^8.5`-Spielraum derzeit gegenstandslos.)

**Bewertung:** Direktnutzen **Mittel** · Innovation **Niedrig** · Aufwand **Niedrig** ·
Risiko **Niedrig** · Fit **Mittel**.

---

#### C2 — Mutation-Testing mit Infection

**Idee:** Infection in CI integrieren, mit Mindest-MSI-Schwelle.

**Nutzen:** Für eine **Regel-Bibliothek** ist die Aussagekraft der Tests
geschäftskritisch — eine Regel, deren Test grün bleibt, obwohl die Regel kaputt ist,
ist schlimmer als keine Regel. Infection beweist, dass die Tests Mutanten **töten**,
und ist damit ein starkes, glaubwürdiges Qualitätssignal nach außen.

**Bewertung:** Direktnutzen **Mittel** · Innovation **Mittel** · Aufwand **Mittel** ·
Risiko **Niedrig** · Fit **Hoch** (passt zum Disziplin-Anspruch).

---

#### C3 — Weitere Hygiene-Bausteine (Kurzliste)

- **Code-Coverage** als CI-Artefakt/Schwelle (ergänzt C2).
- **`composer normalize`** + **NEON-Lint** als CI-Check (hält Manifest/Config sauber).
- **README-Ausbau** je Regel: Vorher/Nachher-Beispiel, Begründung, Ignorier-Hinweis —
  stärkt Adoption und Überzeugungskraft (eher Doku als Technik).

---

## 4. Vergleichsmatrix

| # | Idee | Direktnutzen | Innovation | Aufwand | Risiko | Fit |
| - | ---- | ------------ | ---------- | ------- | ------ | --- |
| **A1** | `DisallowLooseComparisonRule` (`==`/`!=`) | **Hoch** | Mittel | Niedrig | Mittel | **Sehr hoch** |
| A2 | `DisallowErrorSuppressionRule` (`@`) | Mittel-Hoch | Niedrig | Sehr niedrig | Niedrig | Hoch |
| A3 | `RequireStrictTypesDeclarationRule` | Mittel | Niedrig | Mittel | Niedrig-Mittel | Hoch |
| A4 | `DisallowLooseSwitchRule` | Mittel | Mittel | Niedrig | **Hoch** | Mittel-Hoch |
| A5 | `DisallowEmptyCatchRule` | Mittel | Niedrig | Niedrig | Niedrig | Hoch |
| **B1** | Meta-Architektur (Attribut + Meta-Test) | Mittel¹ | **Hoch** | Mittel | Niedrig | **Sehr hoch** |
| B2 | Abstrakte Basisklasse | Niedrig | Niedrig | Niedrig | Mittel² | Mittel |
| C1 | CI-Matrix (lowest/latest) | Mittel | Niedrig | Niedrig | Niedrig | Mittel |
| C2 | Infection Mutation-Testing | Mittel | Mittel | Mittel | Niedrig | Hoch |

¹ indirekt über Vertrauen/Korrektheit · ² Kopplungsrisiko, daher zurückgestellt.

> **Umsetzungsstatus:** **A1** ✅ · **B1** ✅ schlank (Attribut + README-Codegen
> zurückgestellt) · **C1** ✅ · **C2** ✅ · **C3** ✅. Offen: A2, A4, A5, B1-Vollausbau.

---

## 5. Empfehlung & Roadmap

> **Aktualisiert nach `v0.1.0-beta.2`.** Die ursprünglich empfohlenen ersten beiden
> Schritte sind erledigt:
> 1. ~~**A1** `DisallowLooseComparisonRule`~~ — ✅ umgesetzt (`v0.1.0-beta.2`).
> 2. ~~**B1** Meta-Architektur~~ — ✅ schlank umgesetzt (Reflection-Guard); Attribut +
>    README-Codegen bewusst zurückgestellt (YAGNI bei vier Regeln).

**C1, C2 und C3 sind umgesetzt.** Vorrangig ist nun jedoch nicht eine weitere Regel,
sondern die in **Abschnitt 0.1** beschriebene Positionierungsfrage: drei der vier Regeln
überlappen mit `phpstan-strict-rules`, eindeutig einzigartig ist nur `TypedClassConstantRule`.
Zu klären ist die Paket-Identität (eigenständiges, teils redundantes „strict"-Set vs. Fokus
auf nicht-abgedeckte Regeln), **bevor** das Regelset weiter wächst.

**Vorgeschlagene Sequenz (Rest):**

1. ~~**C1** CI-Matrix (PHPStan lowest/highest) — fängt API-Drift der Rule-API früh ab;
   sehr geringer Aufwand.~~ ✅ umgesetzt
2. ~~**C2** Infection (Mutation-Testing mit MSI-Schwelle) — beweist, dass die Regel-Tests
   Mutanten töten; das glaubwürdigste Qualitätssignal für eine Regel-Bibliothek.~~ ✅ umgesetzt
3. **Positionierung entscheiden (neu, vorrangig)** — Ergebnis von Abschnitt 0.1: bleiben
   die redundanten Regeln, oder verschiebt sich der Fokus auf Regeln **ohne** Overlap?
4. Risikoarme Regeln **A2** (`DisallowErrorSuppressionRule`, `@`) und **A5**
   (`DisallowEmptyCatchRule`) — beide haben in `phpstan-strict-rules` *kein* direktes
   Äquivalent (zu verifizieren) und brächten damit, anders als die drei bestehenden
   überlappenden Regeln, echten Zusatznutzen. Gute Kandidaten — aber erst nach Schritt 3.
5. **A4** (`switch`) nur bewusst als opt-in (Kontroversität).
6. **B1-Vollausbau** (`#[StrictRule]`-Attribut + README-Generierung) erst, wenn das
   Regelset deutlich wächst und der manuelle Doku-Abgleich spürbar wird.

---

## 6. Bewusst (vorerst) verworfen — YAGNI

- **End-Nutzer-Konfigurierbarkeit** einzelner Regeln (Flags) — widerspricht dem
  bisherigen kompromisslosen, nicht-konfigurierbaren Prinzip; erst bei nachgefragtem
  Bedarf.
- **Abstrakte Basisklasse (B2)** — erst ab ≥ 3 echten „bloße-Existenz"-Regeln.
- **Regel-„Level"/Tier-Gruppierung in `rules.neon`** — bei dieser Regelzahl unnötige
  Komplexität.
- **Typ-bewusste Varianten** der losen-Vergleich-Regeln — der bewusst syntaktische,
  kompromisslose Ansatz bleibt die Design-Linie, ist aber **nicht** (mehr) als
  Differenzierungsmerkmal gegenüber `phpstan-strict-rules` zu verstehen: dessen `==`- und
  `empty()`-Verbote überlappen vollständig (siehe Abschnitt 0.1).

---

## 7. Nächste Schritte

Sobald eine Richtung gewählt ist, ist der reguläre Pfad dieses Repos:
**Design-Spec** unter `docs/superpowers/specs/YYYY-MM-DD-<topic>-design.md` →
**Implementierungsplan** → Umsetzung mit Regelklasse, Registrierung in `rules.neon`,
`RuleTestCase` + Fixture, README-Zeile, CHANGELOG-Eintrag — abgesichert durch die
beiden lokalen Vertragskommandos `composer test` und `composer phpstan`.
