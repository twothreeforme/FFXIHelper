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
  Everything (`HXI_CharacterStatCalculator`, `APIModuleEquipsets`, `FFXIPackageHelper_Equipsets`)
  uses `FFXIPackageHelper_Equipment::getItemObjects()` (real `HXI_Item`/`HXI_Weapon`) now.
- `sql_ASB/` deleted (deprecated dupe of `sql/`).
- Two pre-existing bugs fixed: `Character::setMerit()` wrote to the wrong array key; `getEquipment()`
  aliased the weapon-skill column differently than `getItem()`, so search results always showed
  skill type 0.

## Not done / open
- `FFXIPH_ItemDescription` (`FFXIPH_Item.php`) — untouched, deferred by request.
- Equipment **search** feature (`FFXIPackageHelper_QueryController`/`APIModuleEquipmentSearch`) —
  still on the old array + `ItemDetails` path, not migrated to the factory.
- No `HXI_StatCalculator` interface / `HXI_BaseStatCalculator` abstract base built.
- Broader `FFXIPH_`/`FFXIPackageHelper_` → `HXI_` rename across the rest of the codebase — not
  started, planned as its own future pass.
- **`sql/DAT_details.sql` needs manual import** into the live DB (user owns this) before
  `getFullItem()` returns real display data — until then those columns come back NULL.
- **Nothing has been executed/tested.** No PHP CLI or MediaWiki instance available in this
  environment at any point. Manual smoke test still required before trusting this in production.

## Commits (branch `major-rewrite`)
- `d81689d` — Character/Mob split + Item/Weapon/Equipment classes + HXI_ renaming.
- `c2a4775` — dat_details table + getFullItem() + HXI_ItemFactory + full array retirement.

## Style note
User wants terse, direct replies — minimal pleasantries, no padding.
