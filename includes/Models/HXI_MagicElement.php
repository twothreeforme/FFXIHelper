<?php

/**
 * Spell elements, ids as in LSB sql/spell_list.sql (@ELEMENT_NONE = 0 ... @ELEMENT_DARK = 8).
 * Not HXI_AutomatonElement: automaton capacity elements start at Fire = 0 and have no "None".
 */
enum HXI_MagicElement: int {
    case None    = 0;
    case Fire    = 1;
    case Ice     = 2;
    case Wind    = 3;
    case Earth   = 4;
    case Thunder = 5;
    case Water   = 6;
    case Light   = 7;
    case Dark    = 8;

    public function label(): string {
        return match($this) {
            self::None    => "None",
            self::Fire    => "Fire",
            self::Ice     => "Ice",
            self::Wind    => "Wind",
            self::Earth   => "Earth",
            self::Thunder => "Thunder",
            self::Water   => "Water",
            self::Light   => "Light",
            self::Dark    => "Dark",
        };
    }

    /**
     * Wiki file name of the element icon (same set HXI_Equipsets::resistances() uses); null for None.
     */
    public function iconName(): ?string {
        return match($this) {
            self::None    => null,
            self::Thunder => "Trans_Lightning.gif",
            default       => "Trans_" . $this->label() . ".gif",
        };
    }
}

?>
