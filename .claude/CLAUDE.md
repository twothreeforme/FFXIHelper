# CLAUDE.md

MediaWiki extension (FFXIPackageHelper) for the HorizonXI wiki. PHP 8.1+. Classes use the `HXI_` prefix and are
autoloaded from the `AutoloadClasses` map in `extension.json` (not PSR-4): adding, renaming or moving a class means
updating that map. Wider project state: `CONTEXT.md`. New tools: the `hxi-new-tool` skill.

## Blue Mage tool (Special:BLUBuilder)

Plans a Blue Mage spell set: shows set points and slots, the blue traits the set unlocks, the bonuses it adds, BLU job
abilities and spell details. Built to drop into Special:Equipsets as a tab later.

### Product rules (decided by the user, don't undo)
- **Blue Mage is always the main job** on this page. No main job dropdown: the main row shows "Blue Mage" + a level
  (1-75, no "None"). Sub job is a dropdown without Blue Mage. The "Max" sub level checkbox is checked by default.
- **No race input** and **no character stat calculation**. The "Character Bonuses" window only lists what the set
  adds (spell stat bonuses + blue traits that apply), summed per modifier, e.g. `DEF +4`, `Attack +8`.
- Blue Magic list and Set Spells list share the row layout: the element icon sits on the right, just left of the
  set-points badge.

### Files
| Layer | File |
|---|---|
| Special page (thin host) | `includes/SpecialPages/SpecialBLUBuilder.php` - loads RL module `HXI_BLUBuilder` |
| Markup (static shell) | `includes/tabs/HXI_HTMLTabBLUBuilder.php` - `render($inputs, $categories, $showInputs)` |
| Data + Horizon overrides | `includes/helpers/data/HXI_BLUBuilderData.php` - `getSpells()`, `getTraitTiers()`, `clientPayload()` |
| Set rules (PHP) | `includes/Models/HXI_BLUBuild.php` - `status()`, `activeSpells()`, `blueTraits()`, `spellMods()` |
| Player inputs | `includes/Models/HXI_BLUBuilderInputs.php` - jobs/levels/merits, `fromRequest()`, `fromEquipmentSet()` |
| Value types | `HXI_BLUSpell`, `HXI_BLUTraitCategory`, `HXI_MagicElement` (all in `includes/Models/`) |
| Rules (JS, no DOM) | `includes/js/tabs/BLUBuilder/HXI_BLUBuilderModel.js` - mirrors the PHP rules |
| UI (JS) | `includes/js/tabs/BLUBuilder/HXI_TabBLUBuilder.js` - `setLinks()`, `setInputs()`, all rendering |
| Standalone entry | `includes/js/tabs/BLUBuilder/HXI_BLUBuilderController.js` - `setLinks({ syncUrl: true })` |
| Styles | `resources/styles/HXI_BLUBuilder.css` (FFXI-window theme, `.HXI_blu` scope) |
| DB queries | `HXI_DatabaseQueryWrapper`: `getBlueSpells()`, `getBlueSpellMods()`, `getBlueTraits()`, `getJobTraits()`, `getSpellDescriptions()` |

### How it works
- PHP renders the shell; everything that changes (set, points meter, spell list, traits, bonuses, abilities, details)
  is rendered client side from the `HXI_BLUBuilder` mw.config payload (`HXI_BLUBuilderData::clientPayload()`).
  No API calls: the old `blubuilder_stats` API was removed.
- Rules follow LSB `src/map/utils/blueutils.cpp`, kept identical in PHP and JS - change both together:
  - slots = clamp((lvl-1)/10*2 + 6, 6, 20); points = clamp((lvl-1)/10*5 + 10, 0, 55) + Assimilation merit
  - set validation order (ValidateBlueSpells): level-locked, then over points, then past the last slot
  - traits (CalculateTraits): weights add per category; a reached tier replaces lower tiers; a blue trait never
    stacks with the same job trait - the higher rank wins
  - merits: 0-5 each, 10 per group, only count for BLU main at Lv75
- Trait points are shown x4 (`TRAIT_POINT_SCALE`; Horizon wiki says 8 per tier, LSB stores 2).
- Share link: `?spells=id-id-..&mlvl&sjob&slvl&bmerit=a-b-..` (merits in `HXI_BLUBuilderInputs::MERITS` order).
  `mjob`/`race` in old links are ignored. `syncUrl` keeps it in the address bar on the standalone page.

### Data
- LSB tables copied to `sql/` (`spell_list`, `blue_spell_list`, `blue_spell_mods`, `blue_traits`, `traits`), imported
  into LSB_Data. If missing, the page shows `unavailable()`.
- Horizon changes go in `HXI_BLUBuilderData::OVERRIDES` (keyed by `spell_list.name`, `note` required - shown as the
  "HZ" badge). Only override values Horizon marks with `{{changes}}`. Source: horizonffxi.wiki raw wikitext
  (`/w/index.php?title=X&action=raw`).
- Horizon-only trait categories (no LSB tiers) go in `EXTRA_CATEGORIES`; they show points but add no bonus.
- Modifier display names/formats: `HXI_BLUBuilderData::MOD_LABELS` (`flat`, `pct`, `tick`).
- Job abilities: `JOB_ABILITIES` (Horizon list; Convergence is a Lv45 JA). Merit text: `MERIT_INFO`.
- Spell descriptions: none yet. Creating `dat_spell_details (spellid, descr)` makes them appear with no code change.

### Equipsets integration (not done)
Render `HXI_HTMLTabBLUBuilder::render($inputs, $categories, false)` in a tab div, add the payload with
`HXI_BLUBuilderInputs::fromEquipmentSet($set)`, add the module's JS/CSS to Equipsets, call
`setLinks({ syncUrl: false, inputs: "external" })` and `setInputs({...})` on job/level change. The JS still handles
non-BLU main jobs (BLU sub) for this case. `HXI_CharacterStatCalculator`'s `$extraTraits`/`$extraMods` +
`HXI_BLUBuild::blueTraits()`/`spellMods()` are kept for feeding the set into Equipsets stats.

### Open
- No saving (share link only); required once inside Equipsets (LSB `chars.set_blue_spells`, spell id + 0x200).
- Several Horizon trait weights/tiers are unverified - see `CONTEXT.md` "Not done / open (BLU Builder)".
- The `mediawiki.api` dependency of the `HXI_BLUBuilder` module is no longer used by the page.

### Checking changes
No PHP in this shell; JS: `node --check <file>`. Test on the wiki at Special:BLUBuilder.
