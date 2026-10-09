<?php

/**
 * One blue magic spell as the BLU Builder sees it: LSB spell_list + blue_spell_list + blue_spell_mods,
 * with Horizon changes already applied (HXI_BLUBuilderData::OVERRIDES).
 */
class HXI_BLUSpell {

    /** LSB scripts/enum/skillchain.lua, index = id (blue_spell_list.primary_sc etc.) */
    public const SKILLCHAINS = [ 1 => 'Transfixion', 2 => 'Compression', 3 => 'Liquefaction', 4 => 'Scission',
        5 => 'Reverberation', 6 => 'Detonation', 7 => 'Induration', 8 => 'Impaction', 9 => 'Gravitation',
        10 => 'Distortion', 11 => 'Fusion', 12 => 'Fragmentation', 13 => 'Light', 14 => 'Darkness' ];

    /**
     * @param array<int,int> $mods        modifier id => value, added to the player while the spell is set
     * @param int[]          $skillchains skillchain ids (SKILLCHAINS); a physical spell has at least one
     * @param string[]       $horizon     notes for every value Horizon changed (shown in the UI)
     */
    public function __construct(
        public readonly int $id,
        public readonly string $key,
        public readonly string $name,
        public readonly int $level,
        public readonly int $points,
        public readonly HXI_MagicElement $element,
        public readonly int $mp,
        public readonly int $castTime,
        public readonly int $recastTime,
        public readonly ?HXI_BLUTraitCategory $category,
        public readonly int $weight,
        public readonly array $mods,
        public readonly array $skillchains,
        public readonly array $horizon = [],
    ) {}

    /** Physical blue magic has skillchain properties (LSB blue_spell_list primary_sc != 0). */
    public function isPhysical(): bool {
        return count( $this->skillchains ) > 0;
    }
}

?>
