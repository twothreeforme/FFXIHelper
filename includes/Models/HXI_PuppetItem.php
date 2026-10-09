<?php

/**
 * One item_puppet row: an automaton head, frame or attachment.
 *
 * For a head/frame, the packed element value is the capacity it PROVIDES; for an attachment
 * it is the capacity it COSTS (see HXI_AutomatonElement for the packing).
 */
class HXI_PuppetItem {

    public const SLOT_HEAD       = 1;
    public const SLOT_FRAME      = 2;
    public const SLOT_ATTACHMENT = 3;

    /** item_puppet.itemid minus this = the attachment id LSB stores in char_pet (1-255). */
    public const ATTACHMENT_ID_BASE = 0x2100;

    public int $itemId;
    /** item_puppet.name, e.g. "tension_spring_ii" - also the key into HXI_AutomatonAttachmentMods. */
    public string $name;
    public string $displayName;
    public int $slot;
    public int $elementSlots;
    /** dat_details.descr, or null until puppet items are added to dat_details (see CONTEXT.md). */
    public ?string $description;

    public function __construct( int $itemId, string $name, string $displayName, int $slot, int $elementSlots, ?string $description = null ) {
        $this->itemId = $itemId;
        $this->name = $name;
        $this->displayName = $displayName;
        $this->slot = $slot;
        $this->elementSlots = $elementSlots;
        $this->description = $description;
    }

    public function isAttachment(): bool {
        return $this->slot === self::SLOT_ATTACHMENT;
    }

    public function attachmentId(): int {
        return $this->itemId - self::ATTACHMENT_ID_BASE;
    }

    public function capacity( HXI_AutomatonElement $element ): int {
        return $element->unpack( $this->elementSlots );
    }

    /**
     * @return int[] capacity per element, indexed by HXI_AutomatonElement value (0-7)
     */
    public function capacities(): array {
        $caps = [];
        foreach ( HXI_AutomatonElement::cases() as $e ) $caps[$e->value] = $this->capacity( $e );
        return $caps;
    }

    /**
     * The element whose maneuvers drive this attachment (every LSB attachment costs exactly one element).
     */
    public function primaryElement(): ?HXI_AutomatonElement {
        foreach ( HXI_AutomatonElement::cases() as $e ) {
            if ( $this->capacity( $e ) > 0 ) return $e;
        }
        return null;
    }
}

?>
