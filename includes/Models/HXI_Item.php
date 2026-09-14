<?php

/**
 * Base equippable/inventory item.
 * Fields map to item_basic + item_equipment (see sql/item_basic.sql, sql/item_equipment.sql).
 */
class HXI_Item {

    public int $id = 0;
    public int $subId = 0;
    public string $name = "";
    public string $sortName = "";
    public int $type = 0;
    public int $stackSize = 1;
    public int $flags = 0;
    public int $auctionCategory = 0;
    public int $baseSell = 0;

    // item_equipment fields
    public int $level = 0;
    public int $iLevel = 0;
    public int $jobs = 0;
    public int $shieldSize = 0;
    public int $scriptType = 0;
    public int $slot = 0;
    public int $rslot = 0;
    public int $rslotlook = 0;
    public int $suLevel = 0;

    /** @var array<int,int> modId => value, from item_mods */
    private array $mods = [];

    public function __construct(int $id = 0, string $name = "") {
        $this->id = $id;
        $this->name = $name;
    }

    public function canEquipInSlot(HXI_EquipSlot $slot): bool {
        return ($this->slot & $slot->slotMask()) !== 0;
    }

    public function setMod(int $modId, int $value): void {
        $this->mods[$modId] = $value;
    }

    public function getMod(int $modId): int {
        return $this->mods[$modId] ?? 0;
    }

    /** @return array<int,int> */
    public function getMods(): array {
        return $this->mods;
    }
}

?>
