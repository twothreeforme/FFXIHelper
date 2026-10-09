<?php

/**
 * GENERATED FILE - do not edit by hand.
 * Source: LandSandBoat scripts/globals/automaton.lua @ b7cf888227 2026-07-15
 * Regenerate: python tools/generate_automaton_mods.py ../LandSandBoat
 * Horizon-specific changes belong in HXI_AutomatonData::OVERRIDES, not here.
 */
class HXI_AutomatonAttachmentMods {

    /**
     * attachment name (item_puppet.name) => list of [mod, values by maneuver count 0..3, opticFiber]
     * A null value means the amount is computed elsewhere (REGEN/REFRESH, see SCALING).
     */
    public const MODIFIERS = [
        'accelerator'         => [ ['EVA', [5, 10, 15, 20], true] ],
        'accelerator_ii'      => [ ['EVA', [10, 15, 20, 25], true] ],
        'accelerator_iii'     => [ ['EVA', [20, 30, 40, 50], true] ],
        'accelerator_iv'      => [ ['EVA', [30, 45, 60, 80], true] ],
        'analyzer'            => [ ['AUTO_ANALYZER', [1, 2, 4, 6], false] ],
        'amplifier'           => [ ['MAGIC_BURST_BONUS_UNCAPPED', [10, 20, 35, 50], true], ['ELEMENTAL_CELERITY', [25, 25, 25, 25], true] ],
        'amplifier_ii'        => [ ['MAGIC_BURST_BONUS_UNCAPPED', [20, 30, 50, 70], true], ['ELEMENTAL_CELERITY', [25, 25, 25, 25], true] ],
        'arcanic_cell'        => [ ['OCCULT_ACUMEN', [10, 20, 35, 50], true] ],
        'arcanic_cell_ii'     => [ ['OCCULT_ACUMEN', [20, 40, 70, 100], true] ],
        'arcanoclutch'        => [ ['MAGIC_DAMAGE', [20, 40, 60, 80], true] ],
        'arcanoclutch_ii'     => [ ['MAGIC_DAMAGE', [40, 60, 80, 120], true] ],
        'armor_plate'         => [ ['DMGPHYS', [-500, -700, -1000, -1500], true] ],
        'armor_plate_ii'      => [ ['DMGPHYS', [-1000, -1500, -2000, -2500], true] ],
        'armor_plate_iii'     => [ ['DMGPHYS', [-1500, -2000, -2500, -3000], true] ],
        'armor_plate_iv'      => [ ['DMGPHYS', [-2000, -2500, -3000, -4000], true] ],
        'auto-repair_kit'     => [ ['REGEN', [null, null, null, null], true] ],
        'auto-repair_kit_ii'  => [ ['REGEN', [null, null, null, null], true] ],
        'auto-repair_kit_iii' => [ ['REGEN', [null, null, null, null], true] ],
        'auto-repair_kit_iv'  => [ ['REGEN', [null, null, null, null], true] ],
        'barrier_module'      => [ ['SHIELDBLOCKRATE', [0, 5, 10, 15], true], ['AUTO_SHIELD_BASH_DELAY', [0, 5, 10, 15], false] ],
        'barrier_module_ii'   => [ ['SHIELDBLOCKRATE', [0, 10, 20, 30], true], ['AUTO_SHIELD_BASH_DELAY', [0, 5, 10, 15], false] ],
        'coiler'              => [ ['DOUBLE_ATTACK', [3, 10, 20, 30], true] ],
        'coiler_ii'           => [ ['DOUBLE_ATTACK', [10, 15, 25, 35], true] ],
        'damage_gauge'        => [ ['AUTO_HEALING_THRESHOLD', [50, 60, 70, 80], false], ['AUTO_HEALING_DELAY', [3, 3, 3, 3], false] ],
        'damage_gauge_ii'     => [ ['AUTO_HEALING_THRESHOLD', [60, 70, 80, 90], false], ['AUTO_HEALING_DELAY', [3, 3, 3, 3], false] ],
        'drum_magazine'       => [ ['AUTO_RANGED_DELAY', [3, 6, 9, 15], true] ],
        'dynamo'              => [ ['CRITHITRATE', [3, 5, 7, 9], true] ],
        'dynamo_ii'           => [ ['CRITHITRATE', [5, 10, 15, 20], true] ],
        'dynamo_iii'          => [ ['CRITHITRATE', [10, 15, 25, 35], true] ],
        'flame_holder'        => [ ['WEAPONSKILL_DAMAGE_BASE', [125, 200, 275, 350], true] ],
        'equalizer'           => [ ['AUTO_EQUALIZER', [10, 25, 50, 75], true] ],
        'galvanizer'          => [ ['COUNTER', [10, 20, 35, 50], true] ],
        'hammermill'          => [ ['SHIELD_BASH', [15, 25, 50, 100], true], ['AUTO_SHIELD_BASH_SLOW', [0, 12, 19, 25], true] ],
        'heatsink'            => [ ['BURDEN_DECAY', [2, 4, 5, 6], false] ],
        'ice_maker'           => [ ['AUTO_MAB_COEFFICIENT', [0, 50, 75, 100], true] ],
        'inhibitor'           => [ ['STORETP', [5, 15, 25, 40], true] ],
        'inhibitor_ii'        => [ ['STORETP', [10, 25, 40, 65], true] ],
        'loudspeaker'         => [ ['MATT', [5, 10, 15, 20], true] ],
        'loudspeaker_ii'      => [ ['MATT', [10, 15, 20, 25], true] ],
        'loudspeaker_iii'     => [ ['MATT', [20, 30, 40, 50], true] ],
        'loudspeaker_iv'      => [ ['MATT', [30, 40, 50, 60], true] ],
        'loudspeaker_v'       => [ ['MATT', [40, 50, 60, 70], true] ],
        'magniplug'           => [ ['MAIN_DMG_RATING', [5, 15, 30, 45], true], ['RANGED_DMG_RATING', [5, 15, 30, 45], true] ],
        'magniplug_ii'        => [ ['MAIN_DMG_RATING', [10, 20, 35, 50], true], ['RANGED_DMG_RATING', [10, 20, 35, 50], true] ],
        'mana_booster'        => [ ['FASTCAST', [20, 30, 45, 60], false] ],
        'mana_channeler'      => [ ['MATT', [10, 15, 25, 35], true], ['AUTO_MAGIC_COOLDOWN', [3, 6, 9, 12], true] ],
        'mana_channeler_ii'   => [ ['MATT', [20, 30, 40, 50], true], ['AUTO_MAGIC_COOLDOWN', [6, 12, 18, 24], true] ],
        'mana_conserver'      => [ ['CONSERVE_MP', [15, 30, 45, 60], true] ],
        'mana_jammer'         => [ ['MDEF', [10, 20, 30, 40], true] ],
        'mana_jammer_ii'      => [ ['MDEF', [20, 30, 40, 50], true] ],
        'mana_jammer_iii'     => [ ['MDEF', [30, 40, 50, 60], true] ],
        'mana_jammer_iv'      => [ ['MDEF', [40, 50, 60, 70], true] ],
        'mana_tank'           => [ ['REFRESH', [null, null, null, null], true] ],
        'mana_tank_ii'        => [ ['REFRESH', [null, null, null, null], true] ],
        'mana_tank_iii'       => [ ['REFRESH', [null, null, null, null], true] ],
        'mana_tank_iv'        => [ ['REFRESH', [null, null, null, null], true] ],
        'optic_fiber'         => [ ['AUTO_PERFORMANCE_BOOST', [10, 20, 25, 30], false] ],
        'optic_fiber_ii'      => [ ['AUTO_PERFORMANCE_BOOST', [15, 30, 37, 45], false] ],
        'percolator'          => [ ['COMBAT_SKILLUP_RATE', [5, 10, 15, 20], true] ],
        'power_cooler'        => [ ['MP_COST_REDUCTION', [10, 20, 35, 50], true] ],
        'repeater'            => [ ['DOUBLE_SHOT_RATE', [10, 15, 35, 65], true] ],
        'resister'            => [ ['STATUSRES', [5, 10, 20, 30], true] ],
        'resister_ii'         => [ ['STATUSRES', [10, 20, 40, 60], true] ],
        'scanner'             => [ ['AUTO_SCAN_RESISTS', [0, 1, 1, 1], false] ],
        'schurzen'            => [ ['AUTO_SCHURZEN', [0, 1, 1, 1], false] ],
        'scope'               => [ ['RACC', [10, 20, 30, 40], true] ],
        'scope_ii'            => [ ['RACC', [20, 30, 40, 50], true] ],
        'scope_iii'           => [ ['RACC', [30, 40, 55, 70], true] ],
        'scope_iv'            => [ ['RACC', [40, 50, 65, 80], true] ],
        'speedloader'         => [ ['SKILLCHAINBONUS', [20, 30, 40, 60], true] ],
        'speedloader_ii'      => [ ['SKILLCHAINBONUS', [35, 45, 60, 80], true] ],
        'smoke_screen'        => [ ['EVA', [20, 40, 80, 160], true], ['ACC', [-20, -40, -80, -160], true], ['RACC', [-20, -40, -80, -160], true] ],
        'stabilizer'          => [ ['ACC', [5, 10, 15, 20], true] ],
        'stabilizer_ii'       => [ ['ACC', [10, 15, 20, 25], true] ],
        'stabilizer_iii'      => [ ['ACC', [20, 30, 40, 50], true] ],
        'stabilizer_iv'       => [ ['ACC', [30, 40, 55, 70], true] ],
        'stabilizer_v'        => [ ['ACC', [40, 50, 65, 80], true] ],
        'stealth_screen'      => [ ['ENMITY', [-10, -20, -30, -40], true] ],
        'stealth_screen_ii'   => [ ['ENMITY', [-15, -25, -35, -45], true] ],
        'steam_jacket'        => [ ['AUTO_STEAM_JACKET_REDUCTION', [30, 45, 60, 80], true] ],
        'strobe'              => [ ['ENMITY', [10, 25, 40, 60], true] ],
        'strobe_ii'           => [ ['ENMITY', [20, 40, 65, 100], true] ],
        'tactical_processor'  => [ ['AUTO_DECISION_DELAY', [50, 70, 85, 115], false] ],
        'tension_spring'      => [ ['ATTP', [3, 6, 9, 12], true], ['RATTP', [3, 6, 9, 12], true] ],
        'tension_spring_ii'   => [ ['ATTP', [6, 9, 12, 15], true], ['RATTP', [6, 9, 12, 15], true] ],
        'tension_spring_iii'  => [ ['ATTP', [12, 15, 18, 21], true], ['RATTP', [12, 15, 18, 21], true] ],
        'tension_spring_iv'   => [ ['ATTP', [15, 18, 21, 24], true], ['RATTP', [15, 18, 21, 24], true] ],
        'tension_spring_v'    => [ ['ATTP', [18, 21, 24, 27], true], ['RATTP', [18, 21, 24, 27], true] ],
        'tranquilizer'        => [ ['MACC', [10, 30, 40, 50], true] ],
        'tranquilizer_ii'     => [ ['MACC', [20, 40, 55, 70], true] ],
        'tranquilizer_iii'    => [ ['MACC', [30, 50, 70, 80], true] ],
        'tranquilizer_iv'     => [ ['MACC', [40, 60, 80, 110], true] ],
        'truesights'          => [ ['AUTO_RANGED_DAMAGEP', [5, 15, 30, 45], true] ],
        'turbo_charger'       => [ ['HASTE_MAGIC', [500, 1500, 2000, 2500], true] ],
        'turbo_charger_ii'    => [ ['HASTE_MAGIC', [700, 1700, 2800, 4375], true] ],
        'vivi-valve'          => [ ['CURE_POTENCY', [5, 15, 30, 45], true] ],
        'vivi-valve_ii'       => [ ['CURE_POTENCY', [10, 20, 35, 50], true] ],
        'volt_gun'            => [ ['VOLT_GUN_POTENCY', [0, 20, 40, 100], false] ],
    ];

    /**
     * Auto-Repair Kits (HP/Regen) and Mana Tanks (MP/Refresh).
     * Max HP/MP bonus % = sum(boost) / frameDivisors[frame] (frames missing from the divisor list get none).
     * Regen/Refresh per tick = base[maneuvers] + maxHP|maxMP * multiplier[maneuvers] / 100.
     */
    public const SCALING = [
        'repair_kit' => [
            'stat' => 'HP',
            'tick' => 'REGEN',
            'frameDivisors' => [ 0x20 => 20, 0x21 => 24, 0x22 => 18, 0x23 => 16 ],
            'items' => [
                'auto-repair_kit' => [ 'boost' => 1, 'base' => [0, 1, 2, 3], 'multiplier' => [0, 0.125, 0.225, 0.375] ],
                'auto-repair_kit_ii' => [ 'boost' => 2, 'base' => [0, 3, 6, 9], 'multiplier' => [0, 0.600, 1.200, 1.800] ],
                'auto-repair_kit_iii' => [ 'boost' => 3, 'base' => [0, 9, 12, 15], 'multiplier' => [0, 1.800, 2.400, 3.000] ],
                'auto-repair_kit_iv' => [ 'boost' => 4, 'base' => [0, 15, 18, 21], 'multiplier' => [0, 3.000, 3.600, 4.200] ],
            ],
        ],
        'mana_tank' => [
            'stat' => 'MP',
            'tick' => 'REFRESH',
            'frameDivisors' => [ 0x20 => 20, 0x23 => 24 ],
            'items' => [
                'mana_tank' => [ 'boost' => 1, 'base' => [0, 1, 2, 3], 'multiplier' => [0, 0.2, 0.4, 0.6] ],
                'mana_tank_ii' => [ 'boost' => 2, 'base' => [0, 2, 3, 4], 'multiplier' => [0, 0.4, 0.6, 0.8] ],
                'mana_tank_iii' => [ 'boost' => 3, 'base' => [0, 3, 4, 5], 'multiplier' => [0, 0.6, 0.8, 1.0] ],
                'mana_tank_iv' => [ 'boost' => 4, 'base' => [0, 4, 5, 6], 'multiplier' => [0, 0.8, 1.0, 1.2] ],
            ],
        ],
    ];
}
