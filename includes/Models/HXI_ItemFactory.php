<?php

/**
 * Builds a single HXI_Item/HXI_Weapon from the rows returned by
 * DatabaseQueryWrapper::getFullItem() - one query joining item_basic, item_equipment,
 * item_weapon, item_mods and dat_details, instead of a DB row plus a separate lookup
 * into the FFXIPackageHelper_ItemDetails static array.
 */
class HXI_ItemFactory {

    /**
     * @param iterable $rows result set from DatabaseQueryWrapper::getFullItem() - one row
     * per mod (all other columns repeat across rows for the same item).
     */
    public static function fromFullItemRows($rows): ?HXI_Item {
        $first = null;
        foreach ($rows as $row) { $first = $row; break; }
        if ($first === null) return null;

        $isWeapon = !is_null($first->skilltype ?? null);

        $name = self::resolveName($first);
        $item = $isWeapon ? new HXI_Weapon((int)$first->itemid, $name) : new HXI_Item((int)$first->itemid, $name);

        $item->subId = (int)($first->subid ?? 0);
        $item->sortName = (string)($first->sortname ?? "");
        $item->type = (int)($first->type ?? 0);
        $item->stackSize = (int)($first->stackSize ?? 1);
        $item->flags = (int)($first->flags ?? 0);
        $item->auctionCategory = (int)($first->aH ?? 0);
        $item->baseSell = (int)($first->BaseSell ?? 0);

        $item->level = (int)($first->level ?? 0);
        $item->iLevel = (int)($first->ilevel ?? 0);
        $item->jobs = (int)($first->jobs ?? 0);
        $item->shieldSize = (int)($first->shieldSize ?? 0);
        $item->scriptType = (int)($first->scriptType ?? 0);
        $item->slot = (int)($first->slot ?? 0);
        $item->rslot = (int)($first->rslot ?? 0);
        $item->rslotlook = (int)($first->rslotlook ?? 0);
        $item->suLevel = (int)($first->su_level ?? 0);

        $item->longname = (string)($first->longname ?? "");
        $item->descr = (string)($first->descr ?? "");
        $item->races = (string)($first->races ?? "");

        if ($isWeapon) {
            $item->skill = (int)$first->skilltype;
            $item->subskill = (int)($first->subskill ?? 0);
            $item->ilvlSkill = (int)($first->ilvl_skill ?? 0);
            $item->ilvlParry = (int)($first->ilvl_parry ?? 0);
            $item->ilvlMAcc = (int)($first->ilvl_macc ?? 0);
            $item->dmgType = (int)($first->dmgType ?? 0);
            $item->hit = (int)($first->hit ?? 1);
            $item->delay = (int)($first->delay ?? 0);
            $item->dmg = (int)($first->dmg ?? 0);
            $item->unlockPoints = (int)($first->unlock_points ?? 0);
        }

        foreach ($rows as $row) {
            if (!is_null($row->modid ?? null) && !is_null($row->modValue ?? null)) {
                $item->setMod((int)$row->modid, (int)$row->modValue);
            }
        }

        return $item;
    }

    private static function resolveName($row): string {
        if (!empty($row->displayName)) return (string)$row->displayName;
        return (string)($row->showname ?? "");
    }
}

?>
