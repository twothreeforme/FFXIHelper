# Context (read this first)

Minimal state for picking up this refactor with low token cost. Full background/rationale is in
REFACTOR_CHANGELOG.md — only open that if you need the "why."

## Project
MediaWiki extension (FFXIPackageHelper). PHP 8.1+. Branch: `major-rewrite`.
New/renamed classes use `HXI_` prefix. Class autoloading = `AutoloadClasses` map in
`extension.json` (not PSR-4) — any rename/move must update that map + every call site.

## Done
- `includes/Models/` refactored: `HXI_Character` + `HXI_EquipmentSet` (split from old
  `FFXIPH_Character`, which conflated `user_chars`+`user_sets`), `HXI_Mob` +
  `HXI_MobStatCalculator` (split from `FFXIPH_Mob`/`FFXIPH_MobUtils`), `HXI_Race` (enum),
  `HXI_MeritsCodec`, `HXI_Item`/`HXI_Weapon`/`HXI_Equipment`/`HXI_EquipSlot`/`HXI_ItemFactory` (new).
- `FFXIPackageHelper_Stats` renamed to `HXI_CharacterStatCalculator` (rename only, no shared
  interface with Mob's calculator — deliberately deferred).
- `sql/DAT_details.sql` (new table `dat_details`: itemid/name/longname/descr/races) +
  `DatabaseQueryWrapper::getFullItem($itemid)` (one query, all 5 item tables joined) +
  `HXI_ItemFactory::fromFullItemRows()` build a real item object instead of the old
  DB-row + 75k-line-static-array lookup.
- Legacy `[id,slot,rslot,mods,skilltype,name]` positional equipment array is fully retired.
  Everything (`HXI_CharacterStatCalculator`, `APIModuleEquipsets`, `HXI_Equipsets`)
  uses `HXI_EquipmentParser::getItemObjects()` (real `HXI_Item`/`HXI_Weapon`) now.
- `sql_ASB/` deleted (deprecated dupe of `sql/`).
- Two pre-existing bugs fixed: `Character::setMerit()` wrote to the wrong array key; `getEquipment()`
  aliased the weapon-skill column differently than `getItem()`, so search results always showed
  skill type 0.
- Broader `FFXIPH_`/`FFXIPackageHelper_` → `HXI_` rename done: 21 classes outside Models/
  (Tabs/helpers/Page Directs), ~40 files. `FFXIPackageHelper_Equipment` → `HXI_EquipmentParser`
  (collision avoidance — `HXI_Equipment` already exists as the Models/ 16-slot container).
- `sql/DAT_details.sql` imported by user; confirmed working (see cache-clear note below).
- `HXI_Variables`' array-based pseudo-enums cleaned up: `$skill` → real `HXI_Skill` enum (was
  dead/unused), `$mobType` → real `HXI_MobType` enum (bitmask flags — each case is one flag,
  combine/check via `->value` against the raw int), `$modArray` (827 entries) → `HXI_ModDictionary`
  class (`getName()`/`all()`) since it's DB-driven and not a fixed closed set.
  `$jobArrayByID`/`$effectType`/`$mobModArray`/etc. left as-is in Variables (out of scope, though
  `$effectType`/`$mobModArray` are similarly dictionary-shaped if this comes up again).
- Equipment **search** migrated too (`HXI_QueryController::queryEquipsetsSearchItems`,
  `APIModuleEquipmentSearch`): `getEquipment()`/`getEquipmentFromDB()` widened in place to join
  `item_weapon`+`dat_details`; new `HXI_ItemFactory::fromFullItemRowsGrouped()` builds real objects
  from a multi-item row set; new `HXI_CustomItemIconMap` (the old ~22-entry custom-item icon
  borrow map, kept separate from `dat_details` since icons still need a real item id even though
  display text is already resolved). `DataModel::parseEquipment()` is now fully dead (zero
  callers) — since deleted.

- **Gear Sets tab UI redesign** (uncommitted): FFXI-window styling in `resources/styles/HXI_Equipsets_GearSets.css`
  (replaces `HXI_Equipsets_SetList.css`); markup in `HXI_Equipsets::showEquipsets()` / `HXI_HTMLOptions::setsList()`.
  Responsive via CSS grid areas (phone 1 col, >=700px 2 col, >=1100px 3 col). Stats/resist/sets-list HTML
  is div-based now (API swaps it in via innerHTML; element ids unchanged). Not yet run on a real wiki.

## Not done / open
- `HXI_BaseStatCalculator` (abstract, shared modifier accumulator) exists; Mob and Character
  calculators extend it. No `HXI_StatCalculator` interface - their entry points differ.
- All remaining `FFXIPH_` / `FFXIPackageHelper_` prefixes were renamed to `HXI_`: PHP/JS/CSS
  file names, ResourceLoader module names, DOM ids and CSS classes, and `FFXIPH_ItemDescription`
  -> `HXI_ItemDescription` (`HXI_ItemDescription.php`). Historical comments naming deleted
  legacy classes (`FFXIPH_Character`, `FFXIPH_Mob`, `FFXIPH_MobUtils`, `FFXIPackageHelper_ItemDetails`)
  were deliberately left. Any on-wiki CSS/JS (e.g. MediaWiki:Common.css) targeting the old
  `FFXIPackageHelper_*` ids/classes must be updated to `HXI_*`.
- **After any class rename in this project, clear MediaWiki's extension-registration cache /
  reset opcache** before assuming a resulting failure is a code bug — this already happened once
  (LSBSearch briefly broke, cache clear fixed it, not a code issue).
- No PHP CLI or MediaWiki instance available in this environment at any point this session — all
  verification here is manual code review; the user has confirmed LSBSearch/DAT_details working
  in their actual environment, but no full smoke test of the Equipsets/Character/Set flow yet.
- **BUG (deferred): puppet items missing from `dat_details`.** `sql/DAT_details.sql` has no rows for
  `item_puppet` items (heads 8193-8198, frames 8224-8227, attachments 8449+); `item_puppet` itself
  is also not in `sql/` yet (Automaton Builder adds it). Until fixed, the Automaton Builder shows
  placeholder description text. Fix = regenerate/append DAT rows for those itemids; no code change
  should be needed (builder reads `dat_details.descr` with a placeholder fallback).
- **FLAG: Automaton Builder has no saving.** v1 is share-link only (`?head=&frame=&att=`). Saving is
  REQUIRED when the builder moves into Equipsets (new table or `user_sets` column; `HXI_Automaton`
  is the model to persist - head/frame/attachment ids already match LSB's `char_pet` ids).
- 23 puppet item icons (`File:itemid_<id>.png`) are not uploaded on the local wiki (e.g. 8463 Speedloader II,
  8547 Analyzer, 8654 Optic Fiber II). Builder shows a grey tile for them; upload the files to fix.

## Automaton Builder (Special:AutomatonBuilder)
- Data: `sql/item_puppet.sql` (LSB copy, import into LSB_Data) + `HXI_AutomatonAttachmentMods` (GENERATED
  from LSB `scripts/globals/automaton.lua` by `tools/generate_automaton_mods.py` - regenerate, don't hand edit).
  Horizon changes go in `HXI_AutomatonData::OVERRIDES` (empty: PUP isn't live on Horizon yet).
- Models: `HXI_Automaton`, `HXI_PuppetItem`, `HXI_AutomatonHead/Frame/Element` enums. Markup:
  `HXI_HTMLTabAutomaton`. JS: `includes/js/tabs/Automaton/` (`HXI_AutomatonModel.js` = pure rules/math,
  `HXI_TabAutomaton.js` = UI with Equipsets-style `setLinks()`, `HXI_AutomatonController.js` = standalone entry).
- Stats (HP/MP/STR-CHR, skill caps, evasion/DEF, frame traits, Regen/Refresh): `HXI_AutomatonStats.js`, data in
  `HXI_AutomatonFrameData` (GENERATED from LSB `scripts/globals/pets/automaton.lua`, same script) + `skill_caps`
  (DB). Evasion/DEF ranks are hard-coded in LSB C++, so they live in `HXI_AutomatonData::FRAME_DEF_EVA_RANKS`.
- Player inputs = `HXI_AutomatonInputs` (PHP) / same shape in JS: jobs+levels, automaton skills (null = at cap),
  Automaton Skills merits, gear bonuses (elemental capacity, melee/ranged/magic skill, automaton level).
  Standalone: "Puppetmaster" form, which uses the exact Equipsets job/level controls (`HXI_HTMLOptions`).
- Gear bonus inputs are capped by `HXI_AutomatonData::gearCaps()`: LSB has no code cap on these mods, so the
  ceiling = best PUP-wearable item per slot at/below `HXI_AutomatonInputs::MAX_LEVEL` (75), read from
  item_mods/item_equipment at page load. Today only Wayang Kulit Mantle (Lv74, Automaton Melee Skill +2)
  qualifies; capacity/ranged/magic/level are locked at 0. New or changed gear moves the caps automatically.
- Head/frame "None" (`HXI_Automaton::NONE` = 0) is a real in-game state: a new PUP's automaton has no head or
  frame at all. (LSB's loader resetting a missing part to Harlequin is a server fallback, not the game rule.)
- "Your skill" inputs are capped at rank A+ for the automaton level (`skill_caps[level].r1`: 276 at Lv75,
  114 at Lv37 /PUP) - LSB charutils caps the player's automaton skills there. Not clamped when PUP isn't main/sub.
- Equipsets integration: render `HXI_HTMLTabAutomaton::render($automaton, $inputs, false)` (no inputs form) in a
  tab div, `addJsConfigVars('HXI_Automaton', ...)` with `HXI_AutomatonInputs::fromEquipmentSet($set)`, add the
  JS/CSS files to the `HXI_Equipsets` module, call `setLinks({ syncUrl: false, inputs: "external" })`, then
  `setInputs({...})` whenever the set's jobs/levels/merits/gear change. Equipsets does not track Automaton
  Skills merits or automaton gear mods yet - those need adding there (gear: AUTO_ELEM_CAPACITY,
  AUTO_MELEE/RANGED/MAGIC_SKILL, AUTOMATON_LVL_BONUS from item_mods; pet gear stats from item_mods_pet).
- Open: Horizon wiki's Mana Tank page lists Dark x3 capacity; LSB has Dark 2. Kept LSB (the page says
  "no Horizon changes", so it's likely retail data) - confirm before PUP goes live.

## BLU Builder (Special:BLUBuilder)
- Data: LSB tables copied to `sql/` (LSB rev `b7cf888227`): `spell_list.sql`, `blue_spell_list.sql`, `blue_spell_mods.sql`,
  `blue_traits.sql` - import all four into LSB_Data (done locally 2026-10-09). Horizon changes live in
  `HXI_BLUBuilderData::OVERRIDES` (keyed by `spell_list.name`, each with a source note shown in the UI as an "HZ" badge).
  Source: horizonffxi.wiki Category:Blue_Magic + spell pages (raw wikitext: `/w/index.php?title=X&action=raw`).
  Only `{{changes}}`-flagged values are overridden; Horizon lowers some post-75 spells into the cap (Vanity Dive 28,
  Empty Thrash 34, Occultation 38, Auroral Drape 42, Quadratic Continuum 54, Winds of Promyvion 56).
- Horizon-only trait "Magic Accuracy Bonus" = `HXI_BLUTraitCategory::MagicAccuracy` (100), no tiers in blue_traits:
  points are shown, value flagged pending (`EXTRA_CATEGORIES`), adds nothing to stats.
- Models: `HXI_BLUBuild` (set + LSB blueutils rules: status() mirrors ValidateBlueSpells, blueTraits() = CalculateTraits),
  `HXI_BLUBuilderInputs` (jobs/levels/BLU merits; slots()/bluePoints()/merit()), `HXI_BLUSpell`, `HXI_MagicElement`.
  JS: `includes/js/tabs/BLUBuilder/` (`HXI_BLUBuilderModel.js` = same rules, no DOM; `HXI_TabBLUBuilder.js` = UI;
  `HXI_BLUBuilderController.js` = standalone entry). Share link: `?spells=id-id-..&mlvl&sjob&slvl&bmerit=a-b-..` (merits in `HXI_BLUBuilderInputs::MERITS` order;
  a single number = Assimilation).
- Character Bonuses (client side, `renderBonuses()`): the set spells' stat bonuses + the blue traits that apply, summed per
  modifier. No character stats are calculated and there is no race input (decided by the user, 2026-10-10). The stat
  calculator still takes `$extraTraits` (max-merged with job traits = no stacking) and `$extraMods` (summed) for the
  Equipsets integration (`HXI_BLUBuild::blueTraits()` / `spellMods()`). Trait points are displayed x4 (`TRAIT_POINT_SCALE`, Horizon's "8 per tier"); LSB stores 2 per tier.
- Job abilities are a Horizon list in `HXI_BLUBuilderData::JOB_ABILITIES` (Convergence = Lv45 JA on Horizon).
- Spell descriptions: none yet. Create `dat_spell_details (spellid, descr)` in LSB_Data and they appear - no code change.
- Shared-code fixes made for this tool (they change Equipsets numbers): `BASE_HP`/`BASE_MP` (1095/1096) added to
  `HXI_ModDictionary` and to HP/MP (Max HP/MP Boost traits were silently dropped); `getTraits()` excludes
  SOA/ROV/ABYSSEA traits (e.g. WAR Max HP Boost, WAR DA II); `getSkillRanks()` no longer errors with no sub job.
- Equipsets integration: render `HXI_HTMLTabBLUBuilder::render($inputs, $categories, false)` in a tab div,
  `addJsConfigVars('HXI_BLUBuilder', ...)` with `HXI_BLUBuilderInputs::fromEquipmentSet($set)`, add the JS/CSS
  (+ `mediawiki.api`) to the Equipsets module, `setLinks({ syncUrl: false, inputs: "external" })`, `setInputs({...})` on
  job/level change. Equipsets has no BLU merits yet (Assimilation).
- Decided (user, 2026-10-09): the SOA/ROV/ABYSSEA trait filter is correct for Horizon; saving follows the Automaton plan
  (share link now, saving required in Equipsets); other BLU merits become inputs once Horizon publishes usable values.

## Not done / open (BLU Builder)
- **FLAG: no saving** - share link only. Saving REQUIRED when moved into Equipsets (`HXI_BLUBuild` spell ids = LSB
  `chars.set_blue_spells` + 0x200 offset).
- Unverified Horizon data (wiki says BLU "needs verification"): Magic Accuracy trait tiers; weights assumed 4 trait points
  for Metallic Body, Blood Drain, Bomb Toss, Blitzstrahl, Infrasonics, Quadratic Continuum; Blood Saber weight 2 /
  Geist Wall weight 1 taken from the Auto Refresh table on the Blood Saber page; Refueling: category list says Resist
  Slow, spell page says no trait (kept no trait); Blood Drain: category list says Conserve MP, spell page says None (used
  Conserve MP). Stub pages (Auroral Drape, Blitzstrahl, Geist Wall, Quadratic Continuum, Winds of Promyvion) keep LSB values.
- BLU merits are inputs (Group 1: Chain/Burst Affinity Recast, Monster Correlation, Physical Potency, Magical Accuracy;
  Group 2: Assimilation, Diffusion, Enchainment). 0-5 each, 10 per group (LSB meritCatInfo), BLU main Lv75 only. Values in
  `HXI_BLUBuilderData::MERIT_INFO` (Horizon pages; Monster Correlation +4%/upgrade and Diffusion +5% duration per upgrade
  after the first are LSB - Horizon gives none). They change abilities (recast, TP, Diffusion unlock) and spells (Details),
  not character stats. FLAG: LSB's Physical Potency is stronger than Horizon's page (accuracy x2 and +attack ratio); Horizon kept.
- Stats have no weapon/gear, so ATT/ACC are weapon-skill-less; compare the +/- deltas, not absolutes.

## Commits (branch `major-rewrite`)
- `d81689d` — Character/Mob split + Item/Weapon/Equipment classes + HXI_ renaming.
- `c2a4775` — dat_details table + getFullItem() + HXI_ItemFactory + full array retirement.
- `3ef7abd` — CONTEXT.md + changelog housekeeping.
- `1689e09` — broader HXI_ rename, 21 classes.
- `333042a`, `c6d403e`, `32b20f0`, `45c75bd`, `9bbd4c4`, `e708274` — DAT_details.sql INSERT-per-row
  fix + search debug logging added/removed (root cause was cache staleness, not code).
- (pending) — HXI_Skill/HXI_MobType/HXI_ModDictionary cleanup.

## Style note
User wants terse, direct replies — minimal pleasantries, no padding.
