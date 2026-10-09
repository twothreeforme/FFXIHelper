<?php

use MediaWiki\MediaWikiServices;

/**
 * Data layer for the BLU Builder.
 *
 * Sources:
 *  - spell_list + blue_spell_list (DB) : spells, BLU level, set points, trait category/weight, skillchains
 *  - blue_spell_mods (DB)              : stat bonuses while a spell is set
 *  - blue_traits (DB)                  : trait tiers per category
 *  - traits (DB)                       : the jobs' own traits (blue traits never stack with them)
 *  - dat_spell_details (DB, optional)  : spell descriptions - no such table yet, so every spell shows the
 *                                        placeholder; creating it later needs no code change (descriptions())
 *  - self::OVERRIDES                   : Horizon changes layered over the LSB data
 * The four LSB tables are copies in sql/ (import into LSB_Data; LSB revision in CONTEXT.md).
 */
class HXI_BLUBuilderData {

    public const PLACEHOLDER_DESCRIPTION = "Description not yet available.";

    /**
     * Horizon changes, keyed by LSB spell_list.name. Applied on top of the LSB rows so re-importing the LSB
     * tables never wipes them. Keys:
     *   'level'    => int     BLU level (Horizon moves some post-75 spells into the 75 cap)
     *   'points'   => int     set points
     *   'category' => int     HXI_BLUTraitCategory value, 0 = no trait
     *   'weight'   => int     trait weight in LSB units (1 = "4 trait points" on the Horizon wiki, a tier = 8)
     *   'mods'     => array   replaces the stat bonuses (modid => value)
     *   'mp'       => int     MP cost
     *   'skillchains' => int[] skillchain ids (HXI_BLUSpell::SKILLCHAINS); any = physical blue magic
     *   'note'     => string  what changed and where it comes from - shown on the spell (required)
     * Only changes Horizon marks with {{changes}} are here; values Horizon doesn't publish keep LSB's
     * (or are marked "assumed" in the note). Source: horizonffxi.wiki, Category:Blue_Magic + spell pages, 2026-10-09.
     */
    public const OVERRIDES = [
        'pollen'         => [ 'mp' => 5,
                              'note' => "MP cost 5 (retail 8)." ],
        'metallic_body'  => [ 'points' => 3, 'category' => 17, 'weight' => 1,
                              'note' => "Set points 1 -> 3; trait Max MP Boost -> Conserve MP (weight not published, assumed 4 trait points)." ],
        'blastbomb'      => [ 'category' => 6, 'weight' => 1,
                              'note' => "Contributes 4 trait points to Magic Attack Bonus (none in retail)." ],
        'blood_drain'    => [ 'category' => 17, 'weight' => 1,
                              'note' => "Contributes to Conserve MP per the Blue Magic list (the spell page still says None; weight assumed 4 trait points)." ],
        'vanity_dive'    => [ 'level' => 28, 'points' => 4, 'mods' => [ 11 => 1 ], 'skillchains' => [ 4 ],
                              'note' => "Level 82 -> 28; set points 2 -> 4; stats AGI+3 CHR-2 -> AGI+1; physical slashing, Scission." ],
        'bomb_toss'      => [ 'category' => 100, 'weight' => 1,
                              'note' => "Contributes to Magic Accuracy Bonus (Horizon-only trait; weight and tier values not published)." ],
        'empty_thrash'   => [ 'level' => 34, 'category' => 15, 'weight' => 1,
                              'note' => "Level 87 -> 34; 4 trait points to Max HP Boost instead of Double Attack." ],
        'digest'         => [ 'category' => 17, 'weight' => 1,
                              'note' => "Contributes 4 trait points to Conserve MP (none in retail)." ],
        'occultation'    => [ 'level' => 38,
                              'note' => "Level 88 -> 38; one shadow per 75 Blue Magic skill (retail 50)." ],
        'blank_gaze'     => [ 'category' => 6, 'weight' => 1,
                              'note' => "Contributes 4 trait points to Magic Attack Bonus (none in retail)." ],
        'auroral_drape'  => [ 'level' => 42,
                              'note' => "Level 84 -> 42 (other values not published, LSB kept)." ],
        'blitzstrahl'    => [ 'category' => 100, 'weight' => 1,
                              'note' => "Contributes to Magic Accuracy Bonus (Horizon-only trait; weight and tier values not published)." ],
        'geist_wall'     => [ 'category' => 14, 'weight' => 1,
                              'note' => "Contributes to Auto Refresh (weight 1 per the Horizon Auto Refresh table on Blood Saber)." ],
        'blood_saber'    => [ 'points' => 3, 'category' => 14, 'weight' => 2,
                              'note' => "Set points 2 -> 3; contributes to Auto Refresh at weight 2 per the Horizon Auto Refresh table (staff confirmation; the spell card shows a different value)." ],
        'quad_continuum' => [ 'level' => 54, 'category' => 11, 'weight' => 1,
                              'note' => "Level 85 -> 54; trait Dual Wield -> Defense Bonus (weight assumed 4 trait points)." ],
        'winds_of_promy' => [ 'level' => 56,
                              'note' => "Level 89 -> 56 (other values not published, LSB kept)." ],
        'infrasonics'    => [ 'category' => 100, 'weight' => 1,
                              'note' => "Contributes to Magic Accuracy Bonus (Horizon-only trait; weight and tier values not published)." ],
        'magic_hammer'   => [ 'category' => 0,
                              'note' => "No longer contributes to Magic Attack Bonus (no trait points)." ],
    ];

    /**
     * Trait categories that exist on Horizon but not in LSB blue_traits. 'tiers' stays empty until Horizon
     * publishes values: the page shows the points collected and flags the trait, but adds nothing to stats.
     */
    public const EXTRA_CATEGORIES = [
        100 => [ 'traitid' => null, 'pending' => "Horizon hasn't published this trait's tiers or values." ],
    ];

    /** LSB spell_list.name values that humanize() can't turn into the in-game name. */
    private const DISPLAY_NAMES = [
        'quad_continuum' => 'Quadratic Continuum',
        'winds_of_promy' => 'Winds of Promyvion',
        'mp_drainkiss'   => 'MP Drainkiss',
        '1000_needles'   => '1000 Needles',
    ];

    /**
     * BLU job abilities on Horizon (horizonffxi.wiki/Blue_Mage + ability pages). Abilities above Lv75 are left
     * out; Convergence is a Lv45 job ability on Horizon instead of a merit.
     * 'sp' = two-hour ability (main job only), 'merit' = needs the Group 2 merit (main job only).
     */
    public const JOB_ABILITIES = [
        [ 'name' => 'Azure Lore',     'level' => 1,  'recast' => 7200, 'sp' => true,
          'descr' => 'Enhances the effect of blue magic spells for 30 seconds.' ],
        [ 'name' => 'Burst Affinity', 'level' => 25, 'recast' => 120, 'recastMerit' => 'burstAffinityRecast',
          'descr' => 'Lets your next magical blue magic spell be used in a Magic Burst.' ],
        [ 'name' => 'Chain Affinity', 'level' => 40, 'recast' => 120, 'recastMerit' => 'chainAffinityRecast', 'tpMerit' => 'enchainment',
          'descr' => 'Lets your next physical blue magic spell be used in a Skillchain. Effect varies with TP.' ],
        [ 'name' => 'Convergence',    'level' => 45, 'recast' => 600, 'horizon' => 'Learned at Lv45 on Horizon (retail: Lv75 merit).',
          'descr' => 'Increases the power of your next magical blue magic spell and limits its area of effect to one target.' ],
        [ 'name' => 'Diffusion',      'level' => 75, 'recast' => 600, 'merit' => 'diffusion',
          'descr' => 'Grants the effect of your next support blue magic spell to party members in range.' ],
    ];

    /**
     * How each merit (HXI_BLUBuilderInputs::MERITS) is shown. 'per' = value per upgrade; 'effect' uses {v} for
     * per x upgrades ('diffusion' uses per x (upgrades - 1): the first upgrade unlocks the ability).
     * Values from the Horizon merit pages; where Horizon gives none, LSB (source names the file).
     */
    public const MERIT_INFO = [
        'assimilation'        => [ 'label' => 'Assimilation', 'per' => 1, 'effect' => 'Blue magic points +{v}',
                                   'source' => 'horizonffxi.wiki/Assimilation' ],
        'chainAffinityRecast' => [ 'label' => 'Chain Affinity Recast', 'per' => 4, 'effect' => 'Chain Affinity recast -{v}s',
                                   'source' => 'horizonffxi.wiki/Chain_Affinity_Recast' ],
        'burstAffinityRecast' => [ 'label' => 'Burst Affinity Recast', 'per' => 4, 'effect' => 'Burst Affinity recast -{v}s',
                                   'source' => 'horizonffxi.wiki/Burst_Affinity_Recast' ],
        'monsterCorrelation'  => [ 'label' => 'Monster Correlation', 'per' => 4, 'effect' => 'Damage +{v}% against families a spell is strong against',
                                   'source' => 'LSB scripts/combat/basic/damage_multipliers.lua (Horizon gives no value)' ],
        'physicalPotency'     => [ 'label' => 'Physical Potency', 'per' => 2, 'effect' => 'Physical blue magic accuracy +{v}',
                                   'source' => 'horizonffxi.wiki/Physical_Potency' ],
        'magicalAccuracy'     => [ 'label' => 'Magical Accuracy', 'per' => 2, 'effect' => 'Magical blue magic accuracy +{v}',
                                   'source' => 'horizonffxi.wiki/Magical_Accuracy' ],
        'diffusion'           => [ 'label' => 'Diffusion', 'per' => 5, 'effect' => 'Unlocks Diffusion; spread spells last +{v}% longer',
                                   'source' => 'horizonffxi.wiki/Diffusion; duration from LSB scripts/globals/bluemagic.lua' ],
        'enchainment'         => [ 'label' => 'Enchainment', 'per' => 100, 'effect' => 'Chain Affinity spells get +{v} TP',
                                   'source' => 'horizonffxi.wiki/Enchainment' ],
    ];

    /**
     * How each modifier is shown. format: flat "+N", pct "+N%", tick "+N/tick". Missing ids fall back to the
     * HXI_ModDictionary name, flat.
     */
    public const MOD_LABELS = [
        1    => [ 'DEF', 'flat' ],
        2    => [ 'HP', 'flat' ],
        5    => [ 'MP', 'flat' ],
        8    => [ 'STR', 'flat' ],
        9    => [ 'DEX', 'flat' ],
        10   => [ 'VIT', 'flat' ],
        11   => [ 'AGI', 'flat' ],
        12   => [ 'INT', 'flat' ],
        13   => [ 'MND', 'flat' ],
        14   => [ 'CHR', 'flat' ],
        23   => [ 'Attack', 'flat' ],
        24   => [ 'Ranged Attack', 'flat' ],
        25   => [ 'Accuracy', 'flat' ],
        26   => [ 'Ranged Accuracy', 'flat' ],
        28   => [ 'Magic Attack Bonus', 'flat' ],
        29   => [ 'Magic Defense Bonus', 'flat' ],
        68   => [ 'Evasion', 'flat' ],
        71   => [ 'MP Recovered While Healing', 'flat' ],
        73   => [ 'Store TP', 'flat' ],
        170  => [ 'Fast Cast', 'pct' ],
        174  => [ 'Skillchain Bonus', 'pct' ],
        227  => [ 'Lizard Killer', 'flat' ],
        229  => [ 'Plantoid Killer', 'flat' ],
        230  => [ 'Beast Killer', 'flat' ],
        231  => [ 'Undead Killer', 'flat' ],
        240  => [ 'Resist Sleep', 'flat' ],
        249  => [ 'Resist Gravity', 'flat' ],
        259  => [ 'Dual Wield', 'pct' ],
        288  => [ 'Double Attack', 'pct' ],
        291  => [ 'Counter', 'pct' ],
        295  => [ 'Clear Mind', 'flat' ],
        296  => [ 'Conserve MP', 'flat' ],
        302  => [ 'Triple Attack', 'pct' ],
        303  => [ 'Treasure Hunter', 'flat' ],
        306  => [ 'Zanshin', 'pct' ],
        359  => [ 'Rapid Shot', 'pct' ],
        369  => [ 'Refresh', 'tick' ],
        370  => [ 'Regen', 'tick' ],
        487  => [ 'Magic Burst Bonus', 'pct' ],
        897  => [ 'Gilfinder', 'flat' ],
        1095 => [ 'Max HP', 'flat' ],
        1096 => [ 'Max MP', 'flat' ],
    ];

    /** @var HXI_BLUSpell[]|null keyed by spell id, every BLU spell at or below the level cap */
    private ?array $spells = null;
    private ?array $tiers = null;

    /**
     * @return HXI_BLUSpell[] keyed by spell id; empty if the BLU tables haven't been imported
     */
    public function getSpells(): array {
        if ( $this->spells !== null ) return $this->spells;

        $this->spells = [];
        try {
            $db = new DatabaseQueryWrapper();
            $rows = $db->getBlueSpells();
            $modRows = $db->getBlueSpellMods();
        } catch ( Exception $e ) {
            wfDebugLog( 'BLUBuilder', get_called_class() . ":getSpells: " . $e->getMessage() );
            return $this->spells;
        }

        $mods = [];
        foreach ( $modRows as $m ) $mods[ (int)$m->spellid ][ (int)$m->modid ] = (int)$m->value;

        $list = [];
        foreach ( $rows as $row ) {
            $o = self::OVERRIDES[ $row->name ] ?? [];
            $level = (int)( $o['level'] ?? $row->level );
            if ( $level < 1 || $level > HXI_BLUBuilderInputs::MAX_LEVEL ) continue;

            $category = (int)( $o['category'] ?? $row->trait_category );
            $skillchains = $o['skillchains'] ?? array_values( array_filter( [ (int)$row->primary_sc, (int)$row->secondary_sc, (int)$row->tertiary_sc ] ) );

            $list[] = new HXI_BLUSpell(
                (int)$row->spellid,
                $row->name,
                self::humanize( $row->name ),
                $level,
                (int)( $o['points'] ?? $row->set_points ),
                HXI_MagicElement::tryFrom( (int)$row->element ) ?? HXI_MagicElement::None,
                (int)( $o['mp'] ?? $row->mpCost ),
                (int)$row->castTime,
                (int)$row->recastTime,
                HXI_BLUTraitCategory::tryFrom( $category ),
                (int)( $o['weight'] ?? $row->trait_category_weight ),
                $o['mods'] ?? ( $mods[ (int)$row->spellid ] ?? [] ),
                $skillchains,
                isset( $o['note'] ) ? [ $o['note'] ] : []
            );
        }

        usort( $list, fn( $a, $b ) => [ $a->level, $a->name ] <=> [ $b->level, $b->name ] );
        foreach ( $list as $spell ) $this->spells[ $spell->id ] = $spell;
        return $this->spells;
    }

    /**
     * blue_traits rows as plain arrays (the shape HXI_BLUBuild::blueTraits() and the JS model use),
     * ordered by category then tier.
     */
    public function getTraitTiers(): array {
        if ( $this->tiers !== null ) return $this->tiers;

        $this->tiers = [];
        foreach ( ( new DatabaseQueryWrapper() )->getBlueTraits() as $row ) {
            $this->tiers[] = [
                'category' => (int)$row->trait_category,
                'points'   => (int)$row->trait_points_needed,
                'traitid'  => (int)$row->traitid,
                'modid'    => (int)$row->modifier,
                'value'    => (int)$row->value,
                'rank'     => (int)$row->tier,
                'jpOnly'   => (bool)$row->job_points_only,
            ];
        }
        return $this->tiers;
    }

    /**
     * Spell descriptions keyed by spell id. Reads dat_spell_details (spellid, descr) when that table exists;
     * there is no source for spell text yet, so today this is always empty.
     */
    public function descriptions(): array {
        try {
            $out = [];
            foreach ( ( new DatabaseQueryWrapper() )->getSpellDescriptions() as $row ) {
                if ( trim( (string)$row->descr ) !== '' ) $out[ (int)$row->spellid ] = $row->descr;
            }
            return $out;
        } catch ( Exception $e ) {
            wfDebugLog( 'BLUBuilder', get_called_class() . ":descriptions: " . $e->getMessage() );
            return [];
        }
    }

    /**
     * "smite_of_rage" -> "Smite of Rage", "sub-zero_smash" -> "Sub-Zero Smash"
     */
    public static function humanize( string $name ): string {
        if ( isset( self::DISPLAY_NAMES[$name] ) ) return self::DISPLAY_NAMES[$name];

        $words = explode( ' ', str_replace( '_', ' ', $name ) );
        foreach ( $words as $i => &$w ) {
            $w = ( $i > 0 && $w === 'of' ) ? $w : ucwords( $w, '-' );
        }
        return implode( ' ', $words );
    }

    /** "max hp boost" -> "Max HP Boost" (traits.name is lower case). */
    public static function traitName( string $name ): string {
        $words = explode( ' ', trim( $name ) );
        foreach ( $words as &$w ) {
            $w = in_array( $w, [ 'hp', 'mp', 'tp', 'ii' ], true ) ? strtoupper( $w ) : ucfirst( $w );
        }
        return implode( ' ', $words );
    }

    public static function modLabel( int $modId ): array {
        return self::MOD_LABELS[$modId] ?? [ HXI_ModDictionary::getName( $modId ), 'flat' ];
    }

    /**
     * Everything the browser needs, sent once via mw.config ('HXI_BLUBuilder').
     * descr is ALWAYS present (real text or the placeholder) and descrPending marks the placeholder.
     */
    public function clientPayload( HXI_BLUBuild $initial, HXI_BLUBuilderInputs $inputs ): array {
        $spells = $this->getSpells();
        $descriptions = $this->descriptions();
        $db = new DatabaseQueryWrapper();

        // element icons
        $iconNames = array_filter( array_map( fn( $e ) => $e->iconName(), HXI_MagicElement::cases() ) );
        $urls = HXI_AutomatonData::fileUrls( array_values( $iconNames ) );
        $elements = [];
        foreach ( HXI_MagicElement::cases() as $e ) {
            $elements[ $e->value ] = [ 'label' => $e->label(), 'icon' => $e->iconName() ? $urls[ $e->iconName() ] : null ];
        }

        $mods = function ( array $m ) {
            $out = [];
            foreach ( $m as $modId => $value ) {
                [ $label, $format ] = self::modLabel( $modId );
                $out[] = [ 'id' => $modId, 'label' => $label, 'format' => $format, 'value' => $value ];
            }
            return $out;
        };

        $spellList = [];
        foreach ( $spells as $s ) {
            $spellList[] = [
                'id'           => $s->id,
                'name'         => $s->name,
                'level'        => $s->level,
                'points'       => $s->points,
                'element'      => $s->element->value,
                'mp'           => $s->mp,
                'cast'         => $s->castTime / 1000,
                'recast'       => $s->recastTime / 1000,
                'category'     => $s->category?->value,
                'weight'       => $s->weight,
                'mods'         => $mods( $s->mods ),
                'skillchains'  => array_map( fn( $sc ) => HXI_BLUSpell::SKILLCHAINS[$sc] ?? (string)$sc, $s->skillchains ),
                'physical'     => $s->isPhysical(),
                'horizon'      => $s->horizon,
                'descr'        => $descriptions[ $s->id ] ?? self::PLACEHOLDER_DESCRIPTION,
                'descrPending' => !isset( $descriptions[ $s->id ] ),
            ];
        }

        // trait names come from the traits table (same traitid as blue_traits)
        $jobTraits = [];
        $traitNames = [];
        foreach ( $db->getJobTraits( HXI_BLUBuilderInputs::MAX_LEVEL ) as $row ) {
            $traitNames[ (int)$row->traitid ] ??= self::traitName( $row->name );
            $jobTraits[] = [ 'traitid' => (int)$row->traitid, 'job' => (int)$row->job, 'level' => (int)$row->level,
                             'rank' => (int)$row->traitRank, 'modid' => (int)$row->modifier, 'value' => (int)$row->value,
                             'merit' => (int)$row->meritid > 0 ];
        }

        $categories = [];
        foreach ( HXI_BLUTraitCategory::cases() as $c ) {
            $categories[ $c->value ] = [ 'label' => $c->label(), 'pending' => self::EXTRA_CATEGORIES[ $c->value ]['pending'] ?? null ];
        }

        // label null = no display name for this modifier; the page then uses the trait's name
        $modLabels = [];
        $modIds = array_merge( array_keys( self::MOD_LABELS ), array_column( $this->getTraitTiers(), 'modid' ), array_column( $jobTraits, 'modid' ) );
        foreach ( array_unique( $modIds ) as $modId ) {
            $modLabels[$modId] = [ 'label' => self::MOD_LABELS[$modId][0] ?? null, 'format' => self::MOD_LABELS[$modId][1] ?? 'flat' ];
        }

        return [
            'spells'      => $spellList,
            'elements'    => $elements,
            'categories'  => $categories,
            'tiers'       => $this->getTraitTiers(),
            'traitNames'  => $traitNames,
            'jobTraits'   => $jobTraits,
            'modLabels'   => $modLabels,
            'abilities'   => self::JOB_ABILITIES,
            'merits'      => array_map( fn( $key, $group ) => [ 'key' => $key, 'group' => $group ] + self::MERIT_INFO[$key],
                                        array_keys( HXI_BLUBuilderInputs::MERITS ), HXI_BLUBuilderInputs::MERITS ),
            'meritLimits' => [ 'level' => HXI_BLUBuilderInputs::MERIT_LEVEL, 'each' => HXI_BLUBuilderInputs::MAX_MERIT,
                               'group' => HXI_BLUBuilderInputs::MAX_GROUP ],
            'jobs'        => HXI_Variables::$jobArrayByID,
            'initial'     => $initial->spells,
            'inputs'      => $inputs->toArray(),
            'maxLevel'    => HXI_BLUBuilderInputs::MAX_LEVEL,
            'maxSlots'    => HXI_BLUBuild::MAX_SLOTS,
            'blu'         => HXI_BLUBuilderInputs::BLU,
        ];
    }
}

?>
