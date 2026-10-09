<?php

/**
 * Automaton frames. Values match LSB's xi.automaton.frame / AutomatonFrame (char_pet blob ids).
 */
enum HXI_AutomatonFrame: int {
    case Harlequin  = 0x20;
    case Valoredge  = 0x21;
    case Sharpshot  = 0x22;
    case Stormwaker = 0x23;

    public function label(): string {
        return match($this) {
            self::Harlequin  => "Harlequin",
            self::Valoredge  => "Valoredge",
            self::Sharpshot  => "Sharpshot",
            self::Stormwaker => "Stormwaker",
        };
    }

    /**
     * item_puppet.itemid of this frame (LSB: 0x2000 + frame id).
     */
    public function itemId(): int {
        return 0x2000 + $this->value;
    }
}

?>
