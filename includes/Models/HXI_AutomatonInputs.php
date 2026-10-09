<?php

/**
 * Everything about the PLAYER that the automaton's stats depend on (LSB petutils::LoadAutomatonStats,
 * puppetutils::getSkillCap). The automaton build itself (head/frame/attachments) is HXI_Automaton.
 *
 * Two sources fill this in:
 *  - standalone Special:AutomatonBuilder: the "Puppetmaster" input table (fromRequest() for shared links)
 *  - Equipsets (later): fromEquipmentSet() for jobs/levels; merits/skills/gear will come from the
 *    character + gear set once the builder is merged there.
 * The browser keeps the same shape (HXI_TabAutomaton.js setInputs()), so the stats code doesn't care which.
 */
class HXI_AutomatonInputs {

    public const PUP = 18;              // HXI_Variables::$jobArrayByID
    public const MAX_LEVEL = 75;        // server level cap; matches HXI_HTMLOptions::levelRange()
    public const MAX_SUB_LEVEL = 37;    // matches HXI_HTMLOptions::subLevelRange()
    public const MAX_SKILL_MERITS = 5;  // LSB merits.sql automaton_skills: 5 upgrades, +2 skill each
    public const SKILLS = [ 'melee', 'ranged', 'magic' ];
    public const GEAR = [ 'elemCapacity', 'melee', 'ranged', 'magic', 'level' ];
    /** gear input => LSB modifier id (modifier.h) it represents */
    public const GEAR_MODS = [
        'elemCapacity' => 987,  // AUTO_ELEM_CAPACITY
        'melee'        => 101,  // AUTO_MELEE_SKILL
        'ranged'       => 102,  // AUTO_RANGED_SKILL
        'magic'        => 103,  // AUTO_MAGIC_SKILL
        'level'        => 1044, // AUTOMATON_LVL_BONUS
    ];

    public int $mjob = self::PUP;
    public int $mlvl = self::MAX_LEVEL;
    public int $sjob = 0;
    public int $slvl = 37;
    /** Equipsets' "Max" sub level checkbox (sub level follows main level / 2). */
    public bool $maxSub = true;
    /** Player's automaton skill per type; null = assume it's at the cap. */
    public array $skills = [ 'melee' => null, 'ranged' => null, 'magic' => null ];
    /** Automaton Skills merit upgrades (0-5). */
    public int $merits = 0;
    /** Bonuses from the player's gear: elemental capacity, automaton skills, automaton level. */
    public array $gear = [ 'elemCapacity' => 0, 'melee' => 0, 'ranged' => 0, 'magic' => 0, 'level' => 0 ];

    /**
     * Shared link params. Jobs/levels use the same names as Equipsets (mjob/mlvl/sjob/slvl); the rest are
     * dash lists: askill=melee-ranged-magic ("c" = at cap), amerit=N, agear=capacity-melee-ranged-magic-level.
     *
     * @param array $gearCaps HXI_AutomatonData::gearCaps() - gear values are clamped to what gear can reach
     * @param callable $skillMax fn( int $level ): int - HXI_AutomatonData::playerSkillMax(), the ceiling for
     *                           "your skill" at the automaton's level
     */
    public static function fromRequest( WebRequest $request, array $gearCaps, callable $skillMax ): self {
        $in = new self();
        if ( $request->getCheck( 'mjob' ) ) $in->mjob = self::clamp( $request->getInt( 'mjob' ), 0, 18 );
        if ( $request->getCheck( 'mlvl' ) ) $in->mlvl = self::clamp( $request->getInt( 'mlvl' ), 0, self::MAX_LEVEL );
        if ( $request->getCheck( 'sjob' ) ) $in->sjob = self::clamp( $request->getInt( 'sjob' ), 0, 18 );
        if ( $request->getCheck( 'slvl' ) ) {
            $in->slvl = self::clamp( $request->getInt( 'slvl' ), 0, self::MAX_SUB_LEVEL );
            $in->maxSub = $in->slvl == self::maxSubLevel( $in->mlvl );
        }
        else $in->slvl = self::maxSubLevel( $in->mlvl );

        $skills = explode( '-', $request->getText( 'askill' ) );
        foreach ( self::SKILLS as $i => $k ) {
            $v = $skills[$i] ?? '';
            $in->skills[$k] = ( $v === '' || $v === 'c' || !ctype_digit( $v ) ) ? null : self::clamp( (int)$v, 0, 999 );
        }

        $in->merits = self::clamp( $request->getInt( 'amerit' ), 0, self::MAX_SKILL_MERITS );

        $gear = explode( '-', $request->getText( 'agear' ) );
        foreach ( self::GEAR as $i => $k ) $in->gear[$k] = self::clamp( (int)( $gear[$i] ?? 0 ), 0, $gearCaps[$k]['max'] ?? 0 );

        // last: the skill ceiling depends on the jobs/levels/gear parsed above (no PUP = no level = leave as is)
        if ( $in->level() > 0 ) {
            $max = $skillMax( $in->level() );
            foreach ( self::SKILLS as $k ) {
                if ( $in->skills[$k] !== null ) $in->skills[$k] = min( $in->skills[$k], $max );
            }
        }

        return $in;
    }

    /**
     * Equipsets seam: jobs/levels from a gear set. Skills/merits/gear stay at defaults until Equipsets
     * tracks them (see CONTEXT.md - Automaton Builder).
     */
    public static function fromEquipmentSet( HXI_EquipmentSet $set ): self {
        $in = new self();
        $in->mjob = $set->mjob;
        $in->mlvl = $set->mlvl;
        $in->sjob = $set->sjob;
        $in->slvl = $set->slvl;
        $in->maxSub = $set->slvl == self::maxSubLevel( $set->mlvl );
        return $in;
    }

    /**
     * Automaton level: PUP main = main level + gear level bonus, PUP sub = sub level, else 0
     * (LSB petutils::CalculateAutomatonStats; same as HXI_AutomatonStats.js level()).
     */
    public function level(): int {
        if ( $this->mjob == self::PUP && $this->mlvl > 0 ) return $this->mlvl + $this->gear['level'];
        if ( $this->sjob == self::PUP && $this->slvl > 0 ) return $this->slvl;
        return 0;
    }

    /** Same rule as Equipsets' Max checkbox. */
    public static function maxSubLevel( int $mlvl ): int {
        return $mlvl > 1 ? intdiv( $mlvl, 2 ) : 1;
    }

    public function toArray(): array {
        return [
            'mjob' => $this->mjob, 'mlvl' => $this->mlvl, 'sjob' => $this->sjob, 'slvl' => $this->slvl,
            'maxSub' => $this->maxSub, 'skills' => $this->skills, 'merits' => $this->merits, 'gear' => $this->gear,
        ];
    }

    private static function clamp( int $v, int $min, int $max ): int {
        return max( $min, min( $max, $v ) );
    }
}

?>
