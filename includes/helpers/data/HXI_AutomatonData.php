<?php

use MediaWiki\MediaWikiServices;

/**
 * Data layer for the Automaton Builder.
 *
 * Sources:
 *  - item_puppet (DB)                    : heads/frames/attachments + elemental capacity
 *  - dat_details (DB, LEFT JOIN)         : display name + description - puppet items are NOT in
 *                                          dat_details yet (deferred bug, CONTEXT.md), so both fall
 *                                          back here until those rows are added; no code change needed then.
 *  - HXI_AutomatonAttachmentMods (generated from LSB automaton.lua) : per-maneuver modifier values
 *  - self::OVERRIDES                     : Horizon-specific changes layered over the LSB data
 */
class HXI_AutomatonData {

    public const PLACEHOLDER_DESCRIPTION = "Description not yet available.";

    /**
     * Horizon-specific changes, keyed by item_puppet.name. Applied on top of the LSB data so a
     * regenerate of HXI_AutomatonAttachmentMods never wipes them. Supported keys:
     *   'element'   => int    packed capacity (same format as item_puppet.element)
     *   'modifiers' => array  replaces the modifier list (same format as HXI_AutomatonAttachmentMods::MODIFIERS)
     *   'disabled'  => true   hide the attachment entirely (not available on Horizon)
     * Empty on purpose: PUP is not live on Horizon yet and its wiki lists no attachment changes.
     */
    public const OVERRIDES = [];

    /**
     * item_puppet.name => name used by LSB's automaton.lua, where they differ.
     */
    private const LUA_NAME_ALIASES = [
        'ten._spring_v' => 'tension_spring_v',
    ];

    /**
     * Display names that can't be derived from item_puppet.name. Only used while dat_details has no row.
     */
    private const DISPLAY_NAMES = [
        'ten._spring_v' => 'Tension Spring V',
    ];

    /**
     * How each LSB modifier is shown. format:
     *   flat   +N          pct    +N%
     *   pct100 +N/100 %    (LSB stores these in 1/100ths of a percent)
     *   mult100 xN/100     (Flame Holder: multiplies weapon skill fTP)
     * Anything missing falls back to the raw LSB modifier name, flat.
     */
    public const MOD_LABELS = [
        'ACC'                         => [ 'Accuracy', 'flat' ],
        'ATTP'                        => [ 'Attack', 'pct' ],
        'AUTO_ANALYZER'               => [ 'Analyzer', 'flat' ],
        'AUTO_DECISION_DELAY'         => [ 'Decision Delay Reduction', 'flat' ],
        'AUTO_EQUALIZER'              => [ 'Equalizer', 'flat' ],
        'AUTO_HEALING_DELAY'          => [ 'Healing Delay', 'flat' ],
        'AUTO_HEALING_THRESHOLD'      => [ 'Healing HP Threshold', 'pct' ],
        'AUTO_MAB_COEFFICIENT'        => [ 'Magic Attack Coefficient', 'flat' ],
        'AUTO_MAGIC_COOLDOWN'         => [ 'Magic Recast Reduction', 'flat' ],
        'AUTO_PERFORMANCE_BOOST'      => [ 'Attachment Performance', 'pct' ],
        'AUTO_RANGED_DAMAGEP'         => [ 'Ranged Damage', 'pct' ],
        'AUTO_RANGED_DELAY'           => [ 'Ranged Attack Delay Reduction', 'flat' ],
        'AUTO_SCAN_RESISTS'           => [ 'Scan Resistances', 'flat' ],
        'AUTO_SCHURZEN'               => [ 'Schurzen', 'flat' ],
        'AUTO_SHIELD_BASH_DELAY'      => [ 'Shield Bash Recast Reduction', 'flat' ],
        'AUTO_SHIELD_BASH_SLOW'       => [ 'Shield Bash Slow', 'pct' ],
        'AUTO_STEAM_JACKET_REDUCTION' => [ 'Steam Jacket Damage Reduction', 'pct' ],
        'BURDEN_DECAY'                => [ 'Burden Decay', 'flat' ],
        'COMBAT_SKILLUP_RATE'         => [ 'Combat Skill-up Rate', 'pct' ],
        'CONSERVE_MP'                 => [ 'Conserve MP', 'pct' ],
        'COUNTER'                     => [ 'Counter', 'pct' ],
        'CRITHITRATE'                 => [ 'Critical Hit Rate', 'pct' ],
        'CURE_POTENCY'                => [ 'Cure Potency', 'pct' ],
        'DMGPHYS'                     => [ 'Physical Damage Taken', 'pct100' ],
        'DOUBLE_ATTACK'               => [ 'Double Attack', 'pct' ],
        'DOUBLE_SHOT_RATE'            => [ 'Double Shot', 'pct' ],
        'ELEMENTAL_CELERITY'          => [ 'Elemental Celerity', 'pct' ],
        'ENMITY'                      => [ 'Enmity', 'flat' ],
        'EVA'                         => [ 'Evasion', 'flat' ],
        'FASTCAST'                    => [ 'Fast Cast', 'pct' ],
        'HASTE_MAGIC'                 => [ 'Magic Haste', 'pct100' ],
        'MACC'                        => [ 'Magic Accuracy', 'flat' ],
        'MAGIC_BURST_BONUS_UNCAPPED'  => [ 'Magic Burst Damage', 'pct' ],
        'MAGIC_DAMAGE'                => [ 'Magic Damage', 'flat' ],
        'MAIN_DMG_RATING'             => [ 'Melee Damage Rating', 'flat' ],
        'MATT'                        => [ 'Magic Attack Bonus', 'flat' ],
        'MDEF'                        => [ 'Magic Defense Bonus', 'flat' ],
        'MP_COST_REDUCTION'           => [ 'MP Cost Reduction', 'pct' ],
        'OCCULT_ACUMEN'               => [ 'Occult Acumen', 'flat' ],
        'RACC'                        => [ 'Ranged Accuracy', 'flat' ],
        'RANGED_DMG_RATING'           => [ 'Ranged Damage Rating', 'flat' ],
        'RATTP'                       => [ 'Ranged Attack', 'pct' ],
        'REFRESH'                     => [ 'Refresh', 'flat' ],
        'REGEN'                       => [ 'Regen', 'flat' ],
        'SHIELDBLOCKRATE'             => [ 'Shield Block Rate', 'pct' ],
        'SHIELD_BASH'                 => [ 'Shield Bash Damage', 'flat' ],
        'SKILLCHAINBONUS'             => [ 'Skillchain Damage', 'pct' ],
        'STATUSRES'                   => [ 'Status Resistance', 'flat' ],
        'STORETP'                     => [ 'Store TP', 'flat' ],
        'VOLT_GUN_POTENCY'            => [ 'Volt Gun Potency', 'pct' ],
        'WEAPONSKILL_DAMAGE_BASE'     => [ 'Weapon Skill fTP', 'mult100' ],
        // frame passives (HXI_AutomatonFrameData::FRAME_MODS)
        'DMG'                         => [ 'Damage Taken', 'pct100' ],
        'DMGBREATH'                   => [ 'Breath Damage Taken', 'pct100' ],
        'DMGMAGIC'                    => [ 'Magic Damage Taken', 'pct100' ],
        'PIERCE_SDT'                  => [ 'Piercing Damage Taken', 'ratio100' ],
    ];

    /**
     * Evasion skill / DEF rank per frame - hard-coded in LSB petutils::LoadAutomatonStats (switch on frame),
     * not in Lua, so not generated. Value = skill_caps[level][rank].
     */
    public const FRAME_DEF_EVA_RANKS = [
        0x20 => [ 'evasion' => 4,  'defense' => 11 ], // Harlequin
        0x21 => [ 'evasion' => 7,  'defense' => 8 ],  // Valoredge
        0x22 => [ 'evasion' => 2,  'defense' => 12 ], // Sharpshot
        0x23 => [ 'evasion' => 10, 'defense' => 12 ], // Stormwaker
    ];

    /** LSB scripts/enum/skill_rank.lua, index = rank */
    public const RANK_LABELS = [ '-', 'A+', 'A', 'B+', 'B', 'B-', 'C+', 'C', 'C-', 'D', 'E', 'F', 'G' ];

    /** LSB merits.sql automaton_skills: +2 skill per upgrade, PUP main at Lv75+ only (merit.cpp GetMeritValue). */
    public const SKILL_MERIT_VALUE = 2;

    /** @var HXI_PuppetItem[]|null keyed by itemid */
    private ?array $items = null;

    private ?array $gearCaps = null;
    private ?array $skillCaps = null;

    /** LSB skill rank A+ (scripts/enum/skill_rank.lua) - the player's automaton skills always use it. */
    public const PLAYER_SKILL_RANK = 1;

    /**
     * skill_caps table (level => [r0..r13]), loaded once per request.
     */
    public function skillCapsTable(): array {
        if ( $this->skillCaps !== null ) return $this->skillCaps;
        try {
            $this->skillCaps = ( new DatabaseQueryWrapper() )->getSkillCapsTable();
        } catch ( Exception $e ) {
            wfDebugLog( 'AutomatonBuilder', get_called_class() . ":skillCapsTable: " . $e->getMessage() );
            $this->skillCaps = [];
        }
        return $this->skillCaps;
    }

    /**
     * Highest automaton skill the PLAYER can have at this level: LSB charutils::BuildingCharSkillsTable caps
     * automaton melee/ranged/magic at rank A+ for the level ("capped down to the Automaton's rating" later).
     */
    public function playerSkillMax( int $level ): int {
        return $this->skillCapsTable()[ min( $level, 99 ) ][ self::PLAYER_SKILL_RANK ] ?? 0;
    }

    /**
     * Highest gear bonus a PUP can reach for each HXI_AutomatonInputs::GEAR_MODS input, from the item data:
     * best item per equipment slot (ears/rings: best two), wearable by PUP at or below the server level cap.
     * LSB itself puts no ceiling on these mods, so the gear that exists is the real limit. Computed from the
     * DB rather than hard-coded so new/changed Horizon gear moves the ceiling automatically.
     *
     * @return array input key => [ 'max' => int, 'sources' => [ "Item Name +N", ... ] ]
     */
    public function gearCaps(): array {
        if ( $this->gearCaps !== null ) return $this->gearCaps;

        $keyByMod = array_flip( HXI_AutomatonInputs::GEAR_MODS );
        // per input key, per slot group: list of [value, name]
        $bySlot = [];
        try {
            $rows = ( new DatabaseQueryWrapper() )->getItemsWithMods( array_values( HXI_AutomatonInputs::GEAR_MODS ),
                                                                     HXI_AutomatonInputs::MAX_LEVEL,
                                                                     1 << ( HXI_AutomatonInputs::PUP - 1 ) );
            foreach ( $rows as $row ) {
                $key = $keyByMod[ (int)$row->modid ] ?? null;
                $slot = (int)$row->slot;
                if ( $key === null || $slot === 0 ) continue;
                $group = $slot & -$slot; // lowest slot bit: ear1/ear2 and ring1/ring2 share one group
                // dat_details.name is the abbreviated in-game label ("Wyg. Klt. Mantle"); longname is the full name
                $name = $row->longname ? ucwords( $row->longname ) : self::humanize( $row->name );
                $bySlot[$key][$group][] = [ (int)$row->value, $name ];
            }
        } catch ( Exception $e ) {
            wfDebugLog( 'AutomatonBuilder', get_called_class() . ":gearCaps: " . $e->getMessage() );
        }

        $this->gearCaps = [];
        foreach ( HXI_AutomatonInputs::GEAR_MODS as $key => $modId ) {
            $max = 0;
            $sources = [];
            foreach ( $bySlot[$key] ?? [] as $group => $items ) {
                usort( $items, fn( $a, $b ) => $b[0] <=> $a[0] );
                $wearable = in_array( $group, [ HXI_EquipSlot::Ear1->slotMask() & -HXI_EquipSlot::Ear1->slotMask(),
                                                HXI_EquipSlot::Ring1->slotMask() & -HXI_EquipSlot::Ring1->slotMask() ], true ) ? 2 : 1;
                foreach ( array_slice( $items, 0, $wearable ) as [ $value, $name ] ) {
                    $max += $value;
                    $sources[] = "$name +$value";
                }
            }
            $this->gearCaps[$key] = [ 'max' => $max, 'sources' => $sources ];
        }
        return $this->gearCaps;
    }

    /**
     * @return HXI_PuppetItem[] keyed by itemid; empty if item_puppet hasn't been imported
     */
    public function getItems(): array {
        if ( $this->items !== null ) return $this->items;

        $this->items = [];
        try {
            $rows = ( new DatabaseQueryWrapper() )->getPuppetItems();
        } catch ( Exception $e ) {
            wfDebugLog( 'AutomatonBuilder', get_called_class() . ":getItems: " . $e->getMessage() );
            return $this->items;
        }

        foreach ( $rows as $row ) {
            $override = self::OVERRIDES[ $row->name ] ?? [];
            if ( !empty( $override['disabled'] ) ) continue;

            $this->items[ (int)$row->itemid ] = new HXI_PuppetItem(
                (int)$row->itemid,
                $row->name,
                $row->displayName ?: self::humanize( $row->name ),
                (int)$row->slot,
                (int)( $override['element'] ?? $row->element ),
                ( $row->descr === null || trim( $row->descr ) === '' ) ? null : $row->descr
            );
        }
        return $this->items;
    }

    /**
     * The one place a missing description becomes placeholder text.
     */
    public static function describe( HXI_PuppetItem $item ): string {
        return $item->description ?? self::PLACEHOLDER_DESCRIPTION;
    }

    /**
     * "auto-repair_kit_ii" -> "Auto-Repair Kit II"
     */
    public static function humanize( string $name ): string {
        if ( isset( self::DISPLAY_NAMES[$name] ) ) return self::DISPLAY_NAMES[$name];

        $words = explode( ' ', str_replace( '_', ' ', $name ) );
        foreach ( $words as &$w ) {
            $w = preg_match( '/^(i|ii|iii|iv|v)$/', $w ) ? strtoupper( $w ) : ucwords( $w, '-' );
        }
        return implode( ' ', $words );
    }

    /**
     * @return array list of [mod, values[0..3], opticFiber]; empty for special-effect-only attachments
     */
    public static function modifiersFor( string $name ): array {
        if ( isset( self::OVERRIDES[$name]['modifiers'] ) ) return self::OVERRIDES[$name]['modifiers'];
        $luaName = self::LUA_NAME_ALIASES[$name] ?? $name;
        return HXI_AutomatonAttachmentMods::MODIFIERS[$luaName] ?? [];
    }

    /**
     * Auto-Repair Kit / Mana Tank scaling data for this attachment, or null.
     */
    public static function scalingFor( string $name ): ?array {
        foreach ( HXI_AutomatonAttachmentMods::SCALING as $type => $group ) {
            if ( isset( $group['items'][$name] ) ) return [ 'type' => $type ] + $group['items'][$name];
        }
        return null;
    }

    /**
     * Resolve wiki file names to URLs in one batch. Files missing from the wiki fall back to
     * Special:Redirect, which still resolves if the file is uploaded later.
     *
     * @param string[] $names
     * @return array name => url
     */
    public static function fileUrls( array $names ): array {
        $titles = [];
        foreach ( $names as $n ) {
            $t = Title::makeTitleSafe( NS_FILE, $n );
            if ( $t ) $titles[$n] = $t;
        }

        $found = MediaWikiServices::getInstance()->getRepoGroup()->findFiles( array_values( array_map( fn( $t ) => $t->getDBkey(), $titles ) ) );

        $urls = [];
        foreach ( $names as $n ) {
            $file = isset( $titles[$n] ) ? ( $found[ $titles[$n]->getDBkey() ] ?? null ) : null;
            $urls[$n] = $file ? $file->getUrl() : SpecialPage::getTitleFor( 'Redirect', 'file/' . $n )->getLocalURL();
        }
        return $urls;
    }

    public static function iconName( int $itemId ): string {
        return "itemid_" . $itemId . ".png";
    }

    /**
     * Everything the browser needs, sent once via mw.config ('HXI_Automaton').
     * descr is ALWAYS present (real text or the placeholder), and descrPending marks the placeholder,
     * so the JS never has to know where descriptions come from.
     */
    /**
     * Data for the stats calculation (HXI_AutomatonStats.js), mirroring LSB LoadAutomatonStats/getSkillCap.
     */
    public function statsPayload(): array {
        $frameMods = [];
        foreach ( HXI_AutomatonFrameData::FRAME_MODS as $frame => $mods ) {
            $frameMods[$frame] = array_map( function ( $m ) {
                [ $label, $format ] = self::MOD_LABELS[ $m[0] ] ?? [ $m[0], 'flat' ];
                return [ 'key' => $m[0], 'label' => $label, 'format' => $format, 'value' => $m[1] ];
            }, $mods );
        }

        $skillCaps = $this->skillCapsTable();

        return [
            'pup'           => HXI_AutomatonInputs::PUP,
            'statKeys'      => HXI_AutomatonFrameData::STAT_KEYS,
            'frameStats'    => HXI_AutomatonFrameData::STATS,
            'frameRanks'    => HXI_AutomatonFrameData::FRAME_RANKS,
            'headRankBonus' => HXI_AutomatonFrameData::HEAD_RANK_BONUS,
            'frameMods'     => $frameMods,
            'defEvaRanks'   => self::FRAME_DEF_EVA_RANKS,
            'rankLabels'    => self::RANK_LABELS,
            'skillCaps'     => $skillCaps,
            'meritValue'    => self::SKILL_MERIT_VALUE,
            'playerRank'    => self::PLAYER_SKILL_RANK,
        ];
    }

    public function clientPayload( HXI_Automaton $initial, HXI_AutomatonInputs $inputs ): array {
        $items = $this->getItems();

        $iconNames = array_map( fn( $i ) => self::iconName( $i->itemId ), $items );
        foreach ( HXI_AutomatonElement::cases() as $e ) $iconNames[] = $e->iconName();
        $urls = self::fileUrls( array_values( $iconNames ) );

        $elements = [];
        foreach ( HXI_AutomatonElement::cases() as $e ) {
            $elements[] = [ 'id' => $e->value, 'label' => $e->label(), 'icon' => $urls[ $e->iconName() ] ];
        }

        $base = fn( HXI_PuppetItem $i ) => [
            'itemId'       => $i->itemId,
            'name'         => $i->displayName,
            'descr'        => self::describe( $i ),
            'descrPending' => $i->description === null,
            'icon'         => $urls[ self::iconName( $i->itemId ) ],
        ];

        $heads = [];
        foreach ( HXI_AutomatonHead::cases() as $h ) {
            if ( isset( $items[ $h->itemId() ] ) ) $heads[] = [ 'id' => $h->value, 'label' => $h->label(), 'caps' => array_values( $items[ $h->itemId() ]->capacities() ) ] + $base( $items[ $h->itemId() ] );
        }

        $frames = [];
        foreach ( HXI_AutomatonFrame::cases() as $f ) {
            if ( isset( $items[ $f->itemId() ] ) ) $frames[] = [ 'id' => $f->value, 'label' => $f->label(), 'caps' => array_values( $items[ $f->itemId() ]->capacities() ) ] + $base( $items[ $f->itemId() ] );
        }

        $attachments = [];
        foreach ( $items as $i ) {
            if ( !$i->isAttachment() ) continue;
            $element = $i->primaryElement();

            $mods = [];
            foreach ( self::modifiersFor( $i->name ) as [ $mod, $values, $optic ] ) {
                [ $label, $format ] = self::MOD_LABELS[$mod] ?? [ $mod, 'flat' ];
                $mods[] = [ 'key' => $mod, 'label' => $label, 'format' => $format, 'values' => $values, 'optic' => $optic ];
            }

            $attachments[] = [
                'id'      => $i->attachmentId(),
                'element' => $element?->value,
                'cost'    => $element ? $i->capacity( $element ) : 0,
                'mods'    => $mods,
                'scaling' => self::scalingFor( $i->name ),
                'special' => count( $mods ) === 0,
                'optic'   => str_starts_with( $i->name, 'optic_fiber' ),
            ] + $base( $i );
        }

        $scaling = [];
        foreach ( HXI_AutomatonAttachmentMods::SCALING as $type => $group ) {
            $scaling[$type] = [ 'stat' => $group['stat'], 'tick' => $group['tick'], 'frameDivisors' => $group['frameDivisors'] ];
        }

        return [
            'slots'       => HXI_Automaton::SLOTS,
            'elements'    => $elements,
            'heads'       => $heads,
            'frames'      => $frames,
            'attachments' => $attachments,
            'scaling'     => $scaling,
            'stats'       => $this->statsPayload(),
            'initial'     => [ 'head' => $initial->head?->value ?? HXI_Automaton::NONE, 'frame' => $initial->frame?->value ?? HXI_Automaton::NONE,
                               'attachments' => $initial->attachments ],
            'inputs'      => $inputs->toArray(),
            'gearCaps'    => $this->gearCaps(),
            'maxLevel'    => HXI_AutomatonInputs::MAX_LEVEL,
        ];
    }
}

?>
