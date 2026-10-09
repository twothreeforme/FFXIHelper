<?php

/**
 * The 8 automaton elements, in LSB's capacity order: item_puppet.element packs one 4-bit
 * value per element, element N living at bits (N*4)..(N*4+3).
 */
enum HXI_AutomatonElement: int {
    case Fire    = 0;
    case Ice     = 1;
    case Wind    = 2;
    case Earth   = 3;
    case Thunder = 4;
    case Water   = 5;
    case Light   = 6;
    case Dark    = 7;

    public function label(): string {
        return match($this) {
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
     * Wiki file name of the element icon (same set HXI_Equipsets::resistances() uses).
     */
    public function iconName(): string {
        return match($this) {
            self::Thunder => "Trans_Lightning.gif",
            default       => "Trans_" . $this->label() . ".gif",
        };
    }

    /**
     * This element's 4-bit value out of a packed item_puppet.element column.
     */
    public function unpack(int $packed): int {
        return ($packed >> ($this->value * 4)) & 0xF;
    }
}

?>
