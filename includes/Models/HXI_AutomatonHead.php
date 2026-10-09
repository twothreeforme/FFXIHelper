<?php

/**
 * Automaton heads. Values match LSB's xi.automaton.head / AutomatonHead (char_pet blob ids),
 * so a saved build can later be stored/compared against server data without translation.
 */
enum HXI_AutomatonHead: int {
    case Harlequin    = 0x01;
    case Valoredge    = 0x02;
    case Sharpshot    = 0x03;
    case Stormwaker   = 0x04;
    case Soulsoother  = 0x05;
    case Spiritreaver = 0x06;

    public function label(): string {
        return match($this) {
            self::Harlequin    => "Harlequin",
            self::Valoredge    => "Valoredge",
            self::Sharpshot    => "Sharpshot",
            self::Stormwaker   => "Stormwaker",
            self::Soulsoother  => "Soulsoother",
            self::Spiritreaver => "Spiritreaver",
        };
    }

    /**
     * item_puppet.itemid of this head (LSB: 0x2000 + head id).
     */
    public function itemId(): int {
        return 0x2000 + $this->value;
    }
}

?>
