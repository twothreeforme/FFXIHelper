---
name: hxi-new-tool
description: Build a new FFXI tool (Special page) in the FFXIPackageHelper MediaWiki extension that matches the Automaton Builder and can later be dropped into Special:Equipsets as a tab, with the same FFXI-window theme, responsive layout and UX. Use when asked to create a new tool, calculator, planner or builder for the HorizonXI wiki.
---

# New HXI tool (Equipsets-compatible Special page)

Reference implementation: **Special:AutomatonBuilder**. Before writing anything, read these and copy their
patterns rather than inventing new ones:

| Concern | Reference file |
|---|---|
| Host page | `includes/SpecialPages/SpecialAutomatonBuilder.php` |
| Markup | `includes/tabs/HXI_HTMLTabAutomaton.php` |
| Data / payload | `includes/helpers/data/HXI_AutomatonData.php` |
| Models | `includes/Models/HXI_Automaton.php`, `HXI_AutomatonInputs.php`, `HXI_Automaton{Element,Head,Frame}.php` |
| DB queries | `includes/helpers/Database Management/HXI_DatabaseQueryWrapper.php` |
| JS (rules, no DOM) | `includes/js/tabs/Automaton/HXI_AutomatonModel.js`, `HXI_AutomatonStats.js` |
| JS (UI) | `includes/js/tabs/Automaton/HXI_TabAutomaton.js` |
| JS (standalone entry) | `includes/js/tabs/Automaton/HXI_AutomatonController.js` |
| Styles | `resources/styles/HXI_Automaton.css` (palette shared with `HXI_Equipsets_GearSets.css`) |
| Code generator | `tools/generate_automaton_mods.py` |
| Project state | `CONTEXT.md` (read first, update last) |

Below, `<Tool>` = PascalCase name (e.g. `Ninjutsu`), `<xx>` = short CSS/DOM prefix (Automaton uses `ab`).

## 0. Environment

- Repo: `C:\Users\light\documents\Github\mediawiki-extension-FFXIHelper` (symlinked into `D:\Wiki\mediawiki\extensions\FFXIHelper`).
- MediaWiki 1.40.1, PHP 8.2 (xampp `D:\Wiki\xampp`), MariaDB. Game data DB `LSB_Data` via `openLSBSearchConnection()`.
- Server source (rules of truth): `../LandSandBoat` (C++ `src/map/...`, Lua `scripts/...`, SQL `sql/...`).
- Test at `http://localhost/index.php/Special:<Tool>`.
- Private server = **HorizonXI**: max level **75**, era-restricted content. Check horizonffxi.wiki for Horizon
  changes; bg-wiki for retail behaviour. Horizon differences go in an `OVERRIDES` constant, never by editing LSB data.
- The `mysql` CLI hangs on its password prompt: query the DB with a small PHP `mysqli` script in the scratchpad that
  reads credentials from `LocalSettings.php`.

## 1. Plan before code

1. Read the request, `CONTEXT.md`, and the relevant LSB code/SQL/Lua. Find where the server actually implements the rule.
2. Present a short plan in phases plus **numbered questions** for anything that is the user's decision
   (scope, data source, what to show when data is missing, saving vs share link). Wait for answers.
3. Don't treat LSB load-time fallbacks or defaults as game rules (e.g. LSB resets a missing automaton head to
   Harlequin, but a new PUP really has none). When unsure about a game rule, ask.

## 2. Files to add (checklist)

PHP (no PSR-4 — **every class must be added to `AutoloadClasses` in `extension.json`**):
- `includes/SpecialPages/Special<Tool>.php` — thin host page (see §3).
- `includes/tabs/HXI_HTMLTab<Tool>.php` — all markup; `render(..., bool $showInputs = true)` and `unavailable()`.
- `includes/helpers/data/HXI_<Tool>Data.php` — loads DB rows, applies overrides, builds `clientPayload()`.
- `includes/Models/HXI_<Tool>*.php` — the build/state model (`fromRequest()`, `toQuery()`, validation) and an
  inputs model `HXI_<Tool>Inputs` (`fromRequest()`, `fromEquipmentSet(HXI_EquipmentSet)`, `toArray()`).
  Use PHP backed enums for closed sets (`label()`, ids matching LSB).
- New SQL in `HXI_DatabaseQueryWrapper` methods (one query per need, joined; `LEFT JOIN dat_details` for display names/descriptions).
- Generated data (from LSB Lua/C++ tables): `tools/generate_<tool>_*.py` writes
  `includes/helpers/data/HXI_<Tool>*.php` with a "GENERATED - do not edit" header and the LSB source revision.
- Missing LSB tables: copy to `sql/<table>.sql` and note the import in `CONTEXT.md`.

JS (`includes/js/tabs/<Tool>/`):
- `HXI_<Tool>Model.js` — pure rules and math, no DOM, mirrors the PHP model and cites the LSB function it copies.
- `HXI_Tab<Tool>.js` — UI; exports the Equipsets tab contract (§4).
- `HXI_<Tool>Controller.js` — standalone entry: on first `mw.hook('wikipage.content')` call `setLinks({ syncUrl: true })` in try/catch.

CSS: `resources/styles/HXI_<Tool>.css`, everything scoped under `.HXI_<xx>`.

Registration (`extension.json` + i18n):
- `SpecialPages`: `"<Tool>": "Special<Tool>"`
- `ExtensionMessagesFiles`: `"<Tool>Alias": "i18n/FFXIPackageHelper.alias.php"`
- `Hooks.BeforePageDisplay`: `"Special<Tool>::onBeforePageDisplay"`
- `ResourceModules.HXI_<Tool>`: `localBasePath: ""`, `remoteExtPath: "LSBSearch"`, `packageFiles` (Controller first,
  then Tab, Model, ...), `styles`, `dependencies: ["mediawiki.notification"]` (+ others actually used).
- `i18n/FFXIPackageHelper.alias.php`: `'<Tool>' => [ '<Tool>' ]`; `i18n/en.json`: `"<tool lowercase>": "<Display Name>"`.

## 3. PHP patterns

Host page (copy `SpecialAutomatonBuilder`):
```php
class Special<Tool> extends SpecialPage {
    public function __construct() { parent::__construct( '<Tool>' ); }

    static function onBeforePageDisplay( $out, $skin ) : void {
        if ( $out->getTitle() == "Special:<Tool>" ) $out->addModules( [ 'HXI_<Tool>' ] );
    }

    function execute( $par ) {
        $this->setHeaders();
        $data = new HXI_<Tool>Data();
        $tab = new HXI_HTMLTab<Tool>();
        if ( /* required data missing */ ) { $this->getOutput()->addHTML( $tab->unavailable() ); return; }

        $state  = HXI_<Tool>::fromRequest( /* query params */ );          // share-link state, validated
        $inputs = HXI_<Tool>Inputs::fromRequest( $this->getRequest(), ... ); // player inputs, clamped
        $this->getOutput()->addJsConfigVars( 'HXI_<Tool>', $data->clientPayload( $state, $inputs ) );
        $this->getOutput()->addHTML( $tab->render( $state, $inputs ) );
    }
}
```
- PHP renders the static shell (windows, selects with server-side `selected`); JS renders everything that changes.
- Server validates shared links the same way the JS does (unknown ids dropped, duplicates removed, values clamped).
  Keep illegal-but-explainable states (e.g. over capacity) and highlight them instead of silently fixing.
- `render()` returns exactly one root `<div id="HXI_<xx>" class="HXI_<xx>">` so it can be placed inside an Equipsets tab div.
- `$showInputs = false` omits the inputs form for Equipsets (inputs come from the gear set).
- `unavailable()` explains what is missing and how to fix it (e.g. "import `sql/x.sql`").
- Text that is not available yet (e.g. `dat_details` rows missing): use a placeholder constant like
  `"Description not yet available."`, mark it pending in the payload (`descrPending`), show a ⚑ flag in the UI,
  and design the read path so filling the DB later needs **no code change**.

## 4. JS contract (Equipsets-compatible)

ResourceLoader `packageFiles`: `require("./X.js")` / `module.exports`. Data via `mw.config.get('HXI_<Tool>')`.

`HXI_Tab<Tool>.js` exports:
- `setLinks(opts)` — wires the markup once. `opts.syncUrl` (true standalone: `history.replaceState` the share query;
  false in Equipsets), `opts.inputs`: `"form"` (own inputs window) or `"external"` (host calls `setInputs`).
- `setInputs(partial)` — merges into current inputs and re-renders (Equipsets calls this when jobs/levels/merits/gear change).
- `getInputs()`.
- A "Copy share link" button using the same message/behaviour as Equipsets
  (`mw.notify("Copied to Clipboard !", { autoHide: true, type: "warn" })`).

Rules:
- Keep rules/math in the Model/Stats file (no DOM) so it can be unit-tested in Node and reused by Equipsets.
- Inputs that Equipsets already has (main/sub job, levels, Max sub) must use the **identical controls**:
  `HXI_HTMLOptions::jobDropDown()`, `levelRange()`, `subLevelRange()`, the `HXI_gs_jobs`/`HXI_gs_jobRow`/`HXI_gs_max`
  markup, and the same "Max sub" behaviour (see `inputsWindow()` in `HXI_HTMLTabAutomaton.php`).
- Every number input has a real ceiling from the game: derive it from LSB code or the DB (e.g. gear-bonus caps =
  best wearable item per slot at Lv ≤ 75, skill caps from `skill_caps`), set `max=` server side and clamp in JS.
  A disabled input (cap 0) explains why ("No gear at Lv75 or below"). Don't fall back to arbitrary limits like 999.
- Defaults: show the effective default as the input **placeholder** (e.g. "at cap" shows the cap), not blank.
- Every refused action or clamped value gives feedback: `mw.notify(msg, { type: "error"|"warn", autoHide: true, tag: "HXI_<xx>_<what>" })`.
  Inside an open `<dialog>` the notification is hidden by the top layer, so **also** show the message inline in the dialog.
- Red highlight + notification when a change elsewhere makes the current state invalid (notify only for newly invalid parts).
- Missing icons: `img.onerror` swaps in a grey placeholder tile instead of leaving a broken image/404 loop.
- "None"/empty is a valid choice wherever the game allows nothing to be equipped.

## 5. Theme and layout

Look = FFXI menu window, same palette as the Gear Sets tab. Copy the `:root`-style token block from
`HXI_Automaton.css` onto `.HXI_<xx>` (rename `--ab-*` → `--<xx>-*`): `--top #4a5170`, `--mid #363c56`, `--bot #262b3d`,
`--edge #c9d0c8`, `--gold #ecdc9a`, `--text #f4f5ee`, `--muted #ccd2c8`, `--well`, `--line`, `--neg`, `--over`,
plus element colours when needed.

Window markup (always):
```html
<section class="HXI_win HXI_<xx>_<area>">
  <h2 class="HXI_win_title">Title <span class="HXI_<xx>_count">…</span></h2>
  …content…
</section>
```
Copy the `.HXI_win`, `.HXI_win_title`, subtitle, focus-visible, input/select/table styles from `HXI_Automaton.css`
under your own scope. Never style bare elements or `.HXI_win` globally — it must coexist with `.HXI_gs` and `.HXI_ab` on one page.

Screen constraints (mandatory):
- Mobile first. **No horizontal page scroll from 320px to 1920px.** Content inside windows wraps/flexes; wide tables
  collapse or scroll inside their own container only.
- Layout = CSS grid with named areas:
  - `< 700px`: one column, windows in task order (inputs → main build → details).
  - `700–1399px`: two columns via `grid-template-areas`.
  - `≥ 1400px`: independent flex columns (`.HXI_<xx>_col { display: flex; flex-direction: column }`); below 1400 the
    column wrappers are `display: contents` so grid areas place the windows. (1400, not 1100: the wiki sidebar eats width.)
  - Give grid children `min-width: 0`; use `minmax()` widths; root `max-width: 1240px; margin: 0 auto`.
  - Small-screen tweaks at `max-width: 420px / 360px / 340px` (tighter window padding, smaller gaps).
- `box-sizing: border-box` on everything inside the scope — the wiki skin forces `content-box` on some inputs.
- Element/selection colours via tokens, `@media (prefers-reduced-motion: reduce)` disables transitions.
- Accessible: real `<label for>`, buttons not divs, `:focus-visible` outline in gold.

## 6. Verify (run, don't assume)

1. Clear MediaWiki caches/opcache after adding classes or editing `extension.json` before blaming code.
2. Load `http://localhost/index.php/Special:<Tool>`: no PHP errors, no console errors, no 404s.
3. Node checks of the Model/Stats math against hand-computed values from the LSB formulas (scratchpad script).
4. Headless browser at widths 320, 360, 420, 700, 1024, 1280, 1400, 1600, 1920: `document.documentElement.scrollWidth <= innerWidth`,
   and screenshots at phone/tablet/desktop.
5. Exercise every interaction: refusals notify, clamps notify, share link round-trips, None/empty states render.
6. Confirm Special:Equipsets, Special:LSBSearch and Special:WeatherForecast still render.

Temporary scripts go in the session scratchpad, never the repo.

## 7. Finish

- Update `CONTEXT.md`: a `## <Tool> (Special:<Tool>)` section (data sources + how to regenerate, models, JS files,
  caps and where they come from, Equipsets integration steps) and add deferred bugs/FLAGs under "Not done / open"
  (e.g. "saving REQUIRED when moved into Equipsets", missing icons, unconfirmed Horizon data).
- Report to the user, tersely: what was built, files touched, how it was verified, then an **assumptions log**
  (each assumption + source + what would change it) and open questions. Explain design choices as you would to a
  junior software engineer: what each file does and why it is split that way.
- Do not commit unless asked.
