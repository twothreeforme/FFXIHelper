<?php

/**
 * Blue magic trait categories. Ids 1-28 = LSB blue_spell_list.trait_category / blue_traits.trait_category.
 * Set spells add their weight to their category; enough points unlock a tier (LSB blueutils::CalculateTraits).
 *
 * MagicAccuracy is Horizon-only (Bomb Toss, Blitzstrahl, Infrasonics on horizonffxi.wiki/Category:Blue_Magic).
 * Its id is outside LSB's range so a future LSB category can't collide with it. Horizon publishes no tier
 * values for it, so it has no rows in blue_traits - see HXI_BLUBuilderData::EXTRA_CATEGORIES.
 */
enum HXI_BLUTraitCategory: int {
    case BeastKiller       = 1;
    case AutoRegen         = 2;
    case LizardKiller      = 3;
    case ClearMind         = 4;
    case ResistSleep       = 5;
    case MagicAttackBonus  = 6;
    case UndeadKiller      = 7;
    case AttackBonus       = 8;
    case RapidShot         = 9;
    case MaxMPBoost        = 10;
    case DefenseBonus      = 11;
    case PlantoidKiller    = 12;
    case MagicDefenseBonus = 13;
    case AutoRefresh       = 14;
    case MaxHPBoost        = 15;
    case AccuracyBonus     = 16;
    case ConserveMP        = 17;
    case EvasionBonus      = 18;
    case ResistGravity     = 19;
    case StoreTP           = 20;
    case Counter           = 21;
    case FastCast          = 22;
    case SkillchainBonus   = 23;
    case DoubleAttack      = 24; // tier 2 is Triple Attack
    case DualWield         = 25;
    case Zanshin           = 26;
    case MagicBurstBonus   = 27;
    case Gilfinder         = 28; // tier 2 is Treasure Hunter
    case MagicAccuracy     = 100;

    public function label(): string {
        return match($this) {
            self::BeastKiller       => "Beast Killer",
            self::AutoRegen         => "Auto Regen",
            self::LizardKiller      => "Lizard Killer",
            self::ClearMind         => "Clear Mind",
            self::ResistSleep       => "Resist Sleep",
            self::MagicAttackBonus  => "Magic Attack Bonus",
            self::UndeadKiller      => "Undead Killer",
            self::AttackBonus       => "Attack Bonus",
            self::RapidShot         => "Rapid Shot",
            self::MaxMPBoost        => "Max MP Boost",
            self::DefenseBonus      => "Defense Bonus",
            self::PlantoidKiller    => "Plantoid Killer",
            self::MagicDefenseBonus => "Magic Defense Bonus",
            self::AutoRefresh       => "Auto Refresh",
            self::MaxHPBoost        => "Max HP Boost",
            self::AccuracyBonus     => "Accuracy Bonus",
            self::ConserveMP        => "Conserve MP",
            self::EvasionBonus      => "Evasion Bonus",
            self::ResistGravity     => "Resist Gravity",
            self::StoreTP           => "Store TP",
            self::Counter           => "Counter",
            self::FastCast          => "Fast Cast",
            self::SkillchainBonus   => "Skillchain Bonus",
            self::DoubleAttack      => "Double Attack",
            self::DualWield         => "Dual Wield",
            self::Zanshin           => "Zanshin",
            self::MagicBurstBonus   => "Magic Burst Bonus",
            self::Gilfinder         => "Gilfinder",
            self::MagicAccuracy     => "Magic Accuracy Bonus",
        };
    }
}

?>
