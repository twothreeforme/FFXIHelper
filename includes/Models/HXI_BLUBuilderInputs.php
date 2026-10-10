<?php

/**
 * Everything about the PLAYER that a blue magic set depends on: race (base stats), jobs/levels (BLU level,
 * native traits) and the Assimilation merit (extra blue magic points). The spell set itself is HXI_BLUBuild.
 *
 * Two sources fill this in:
 *  - standalone Special:BLUBuilder: the "Blue Mage" inputs window (fromRequest() + bluOnly() for shared links:
 *    BLU main or BLU sub only, see bluOnly())
 *  - Equipsets (later): fromEquipmentSet() for race/jobs/levels; Assimilation once Equipsets tracks BLU merits.
 * The browser keeps the same shape (HXI_TabBLUBuilder.js setInputs()), so the rest of the code doesn't care which.
 */
class HXI_BLUBuilderInputs {

    public const BLU = 16;                  // HXI_Variables::$jobArrayByID
    public const MAX_LEVEL = 75;            // server level cap; matches HXI_HTMLOptions::levelRange()
    public const MAX_SUB_LEVEL = 37;        // matches HXI_HTMLOptions::subLevelRange()
    public const MERIT_LEVEL = 75;          // job merits count for the main job at Lv75+ (LSB merit.cpp GetMeritValue)
    public const MAX_MERIT = 5;             // upgrades per merit (LSB merits.sql `upgrade`)
    public const MAX_GROUP = 10;            // upgrades per group (LSB merit.cpp meritCatInfo MCATEGORY_BLU_1/_2)

    /**
     * BLU merits on Horizon, in share-link order (bmerit=a-b-c-...). group 1 / group 2 as on horizonffxi.wiki/Blue_Mage.
     * Convergence is a Lv45 job ability on Horizon, not a merit; Efflux is above Lv75.
     */
    public const MERITS = [
        'assimilation'        => 2,
        'chainAffinityRecast' => 1,
        'burstAffinityRecast' => 1,
        'monsterCorrelation'  => 1,
        'physicalPotency'     => 1,
        'magicalAccuracy'     => 1,
        'diffusion'           => 2,
        'enchainment'         => 2,
    ];

    public int $race = 0;                   // HXI_Race
    public int $mjob = self::BLU;
    public int $mlvl = self::MAX_LEVEL;
    public int $sjob = 0;
    public int $slvl = 37;
    /** Equipsets' "Max" sub level checkbox (sub level follows main level / 2). */
    public bool $maxSub = true;
    /** BLU merit upgrades per MERITS key (0-5, 10 per group). Only count for BLU main at Lv75 (meritsActive()). */
    public array $merits = [];

    public function __construct() {
        $this->merits = array_fill_keys( array_keys( self::MERITS ), 0 );
    }

    /**
     * Shared link params. Race/jobs/levels use the same names as Equipsets (race/mjob/mlvl/sjob/slvl);
     * bmerit = merit upgrades as a dash list in MERITS order (a single number = Assimilation only).
     */
    public static function fromRequest( WebRequest $request ): self {
        $in = new self();
        if ( $request->getCheck( 'race' ) ) $in->race = HXI_Race::tryFrom( $request->getInt( 'race' ) )?->value ?? 0;
        if ( $request->getCheck( 'mjob' ) ) $in->mjob = self::clamp( $request->getInt( 'mjob' ), 0, 18 );
        if ( $request->getCheck( 'mlvl' ) ) $in->mlvl = self::clamp( $request->getInt( 'mlvl' ), 0, self::MAX_LEVEL );
        if ( $request->getCheck( 'sjob' ) ) $in->sjob = self::clamp( $request->getInt( 'sjob' ), 0, 18 );
        if ( $request->getCheck( 'slvl' ) ) {
            $in->slvl = self::clamp( $request->getInt( 'slvl' ), 0, self::MAX_SUB_LEVEL );
            $in->maxSub = $in->slvl == self::maxSubLevel( $in->mlvl );
        }
        else $in->slvl = self::maxSubLevel( $in->mlvl );
        $in->setMerits( explode( '-', $request->getText( 'bmerit' ) ) );
        return $in;
    }

    /**
     * Standalone page: the player is always a Blue Mage, either main (Lv1-75, no sub) or sub (Lv1-37, no main
     * job; main level is the lowest that allows the sub level). A link with any other jobs is pulled to the
     * closest of those two. Equipsets keeps the full jobs/levels from the gear set and doesn't call this.
     */
    public function bluOnly(): void {
        if ( $this->sjob == self::BLU && $this->mjob != self::BLU ) {
            $this->setBluLevel( true, $this->slvl > 0 ? $this->slvl : self::MAX_SUB_LEVEL );
        }
        else $this->setBluLevel( false, $this->mlvl > 0 ? $this->mlvl : self::MAX_LEVEL );
    }

    /** BLU main at $level, or BLU sub at $level (clamped to the main/sub cap). Same rule as the JS form. */
    public function setBluLevel( bool $sub, int $level ): void {
        if ( $sub ) {
            $this->sjob = self::BLU;
            $this->slvl = self::clamp( $level, 1, self::MAX_SUB_LEVEL );
            $this->mjob = 0;
            $this->mlvl = min( self::MAX_LEVEL, $this->slvl * 2 );
        }
        else {
            $this->mjob = self::BLU;
            $this->mlvl = self::clamp( $level, 1, self::MAX_LEVEL );
            $this->sjob = 0;
            $this->slvl = 0;
        }
        $this->maxSub = false;
    }

    /** BLU as sub job (and not main). */
    public function bluSub(): bool {
        return $this->sjob == self::BLU && $this->mjob != self::BLU;
    }

    /**
     * Merit upgrades in MERITS order. Each is clamped to 0-5; a group over 10 loses upgrades from its last merits
     * first (the game won't let you spend more, so a hand-edited link is pulled back to what's possible).
     */
    public function setMerits( array $values ): void {
        $used = [ 1 => 0, 2 => 0 ];
        foreach ( array_keys( self::MERITS ) as $i => $key ) {
            $group = self::MERITS[$key];
            $v = self::clamp( (int)( $values[$i] ?? 0 ), 0, self::MAX_MERIT );
            $v = min( $v, self::MAX_GROUP - $used[$group] );
            $used[$group] += $v;
            $this->merits[$key] = $v;
        }
    }

    /** Job merits only apply to BLU main at Lv75+ (LSB merit.cpp GetMeritValue). */
    public function meritsActive(): bool {
        return $this->mjob == self::BLU && $this->mlvl >= self::MERIT_LEVEL;
    }

    /** Upgrades of one merit that actually apply (0 when not BLU main Lv75). */
    public function merit( string $key ): int {
        return $this->meritsActive() ? ( $this->merits[$key] ?? 0 ) : 0;
    }

    /**
     * Equipsets seam: race/jobs/levels from a gear set. Assimilation stays 0 until Equipsets tracks BLU merits
     * (see CONTEXT.md - BLU Builder).
     */
    public static function fromEquipmentSet( HXI_EquipmentSet $set, int $race = 0 ): self {
        $in = new self();
        $in->race = $race;
        $in->mjob = $set->mjob;
        $in->mlvl = $set->mlvl;
        $in->sjob = $set->sjob;
        $in->slvl = $set->slvl;
        $in->maxSub = $set->slvl == self::maxSubLevel( $set->mlvl );
        return $in;
    }

    /** BLU level: main level when BLU is main, sub level when /BLU, else 0 (LSB blueutils::GetTotalSlots). */
    public function bluLevel(): int {
        if ( $this->mjob == self::BLU ) return $this->mlvl;
        if ( $this->sjob == self::BLU ) return $this->slvl;
        return 0;
    }

    /** Spell slots: clamp((level - 1) / 10 * 2 + 6, 6, 20), 0 without BLU (LSB blueutils::GetTotalSlots). */
    public function slots(): int {
        $level = $this->bluLevel();
        if ( $level <= 0 ) return 0;
        return self::clamp( intdiv( $level - 1, 10 ) * 2 + 6, 6, 20 );
    }

    /**
     * Blue magic points: clamp((level - 1) / 10 * 5 + 10, 0, 55), + Assimilation at Lv75 (LSB
     * blueutils::GetTotalBlueMagicPoints). Merits only count for the main job (LSB merit.cpp GetMeritValue).
     */
    public function bluePoints(): int {
        $level = $this->bluLevel();
        if ( $level <= 0 ) return 0;
        return self::clamp( intdiv( $level - 1, 10 ) * 5 + 10, 0, 55 ) + $this->merit( 'assimilation' );
    }

    /** Same rule as Equipsets' Max checkbox. */
    public static function maxSubLevel( int $mlvl ): int {
        return $mlvl > 1 ? intdiv( $mlvl, 2 ) : 1;
    }

    public function toArray(): array {
        return [
            'race' => $this->race, 'mjob' => $this->mjob, 'mlvl' => $this->mlvl, 'sjob' => $this->sjob, 'slvl' => $this->slvl,
            'maxSub' => $this->maxSub, 'merits' => $this->merits,
        ];
    }

    private static function clamp( int $v, int $min, int $max ): int {
        return max( $min, min( $max, $v ) );
    }
}

?>
