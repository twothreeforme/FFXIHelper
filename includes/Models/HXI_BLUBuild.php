<?php

/**
 * One blue magic set: up to 20 spell ids in set order.
 *
 * Rules mirror LSB src/map/utils/blueutils.cpp (and HXI_BLUBuilderModel.js, which applies them live):
 *  - a spell can be set once (IsSpellSet)
 *  - spells above the BLU level are unset (CheckSpellLevels)
 *  - set points must fit the blue magic points (HasEnoughSetPoints / ValidateBlueSpells)
 *  - only the first N spells fit the level's slots (GetTotalSlots / ValidateBlueSpells)
 * A shared link that breaks a rule is KEPT: status() marks the spells that wouldn't be set in game, the page
 * highlights them, and they don't count toward traits or stats.
 */
class HXI_BLUBuild {

    public const MAX_SLOTS = 20;   // LSB m_SetBlueSpells is a 20-byte array

    public const OK = 'ok';
    public const LEVEL = 'level';      // spell level above the BLU level
    public const POINTS = 'points';    // over the blue magic points
    public const SLOTS = 'slots';      // past the last slot
    public const NO_BLU = 'noBlu';     // BLU is neither main nor sub

    /** @var int[] spell ids (LSB spell_list.spellid), set order, no duplicates */
    public array $spells;

    public function __construct( array $spells = [] ) {
        $this->spells = array_slice( array_values( array_unique( array_map( 'intval', $spells ) ) ), 0, self::MAX_SLOTS );
    }

    /**
     * Build from the query string (?spells=513-547-...). Unknown ids and duplicates are dropped so a
     * hand-edited link can't produce something the game can't; rule breaks are kept (see class comment).
     *
     * @param HXI_BLUSpell[] $spellsById
     */
    public static function fromRequest( string $spells, array $spellsById ): self {
        $ids = [];
        foreach ( explode( '-', $spells ) as $part ) {
            if ( !ctype_digit( trim( $part ) ) ) continue;
            $id = (int)$part;
            if ( isset( $spellsById[$id] ) && !in_array( $id, $ids, true ) ) $ids[] = $id;
        }
        return new self( $ids );
    }

    public function encode(): string {
        return implode( '-', $this->spells );
    }

    public function toQuery(): array {
        return [ 'spells' => $this->encode() ];
    }

    /**
     * Same order as LSB ValidateBlueSpells: drop level-locked spells, then walk the rest in set order and
     * drop each spell that doesn't fit the points, then drop everything past the last slot.
     *
     * @param HXI_BLUSpell[] $spellsById
     * @return array<int,string> spell id => self::OK / LEVEL / POINTS / SLOTS / NO_BLU
     */
    public function status( array $spellsById, HXI_BLUBuilderInputs $inputs ): array {
        $level = $inputs->bluLevel();
        $maxPoints = $inputs->bluePoints();
        $maxSlots = $inputs->slots();
        $status = [];
        $points = 0;
        $set = 0;
        foreach ( $this->spells as $id ) {
            $spell = $spellsById[$id];
            if ( $level <= 0 ) $status[$id] = self::NO_BLU;
            else if ( $spell->level > $level ) $status[$id] = self::LEVEL;
            else if ( $points + $spell->points > $maxPoints ) $status[$id] = self::POINTS;
            else {
                $points += $spell->points;
                $status[$id] = $set < $maxSlots ? self::OK : self::SLOTS;
                if ( $status[$id] == self::OK ) $set++;
            }
        }
        return $status;
    }

    /**
     * The spells that are really set in game (status OK).
     *
     * @param HXI_BLUSpell[] $spellsById
     * @return HXI_BLUSpell[]
     */
    public function activeSpells( array $spellsById, HXI_BLUBuilderInputs $inputs ): array {
        $active = [];
        foreach ( $this->status( $spellsById, $inputs ) as $id => $s ) {
            if ( $s == self::OK ) $active[] = $spellsById[$id];
        }
        return $active;
    }

    /**
     * Blue traits unlocked by a set of spells - LSB blueutils::CalculateTraits, up to (not including) the
     * comparison with the player's own job traits, which the stat calculator does (higher value wins).
     *  1. add each spell's weight to its trait category
     *  2. every tier whose points are reached (and isn't job-point only) is eligible; a tier replaces any
     *     eligible tier with the same trait id + modifier and a lower or equal rank
     * BLUE_JOB_TRAIT_BONUS (tier bonus from gear) is not modelled: no gear at Lv75 or below has it.
     *
     * @param HXI_BLUSpell[] $spells the active spells
     * @param array $tiers blue_traits rows: [ 'category', 'points', 'traitid', 'modid', 'value', 'rank', 'jpOnly' ],
     *                     ordered by tier ascending (LSB loads them that way and the erase relies on it)
     * @return array [ 'points' => [category => total], 'traits' => tier rows that apply ]
     */
    public static function blueTraits( array $spells, array $tiers ): array {
        $points = [];
        foreach ( $spells as $spell ) {
            if ( $spell->category === null ) continue;
            $c = $spell->category->value;
            $points[$c] = ( $points[$c] ?? 0 ) + $spell->weight;
        }

        $eligible = [];
        foreach ( $points as $category => $total ) {
            foreach ( $tiers as $t ) {
                if ( $t['category'] != $category || $total < $t['points'] || $t['jpOnly'] ) continue;
                $eligible = array_values( array_filter( $eligible, fn( $e ) =>
                    !( $e['traitid'] == $t['traitid'] && $e['rank'] <= $t['rank'] && $e['modid'] == $t['modid'] ) ) );
                $eligible[] = $t;
            }
        }
        return [ 'points' => $points, 'traits' => $eligible ];
    }

    /**
     * Modifier id => value of the set spells' own stat bonuses (blue_spell_mods), summed.
     *
     * @param HXI_BLUSpell[] $spells
     */
    public static function spellMods( array $spells ): array {
        $mods = [];
        foreach ( $spells as $spell ) {
            foreach ( $spell->mods as $modId => $value ) $mods[$modId] = ( $mods[$modId] ?? 0 ) + $value;
        }
        return $mods;
    }
}

?>
