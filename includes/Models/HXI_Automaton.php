<?php

/**
 * One automaton build: head + frame + up to 12 attachments.
 *
 * Mirrors the rules in LSB src/map/utils/puppetutils.cpp setAttachment():
 *  - an attachment can only be equipped once
 *  - per element, the attachments' total cost must not exceed head + frame capacity
 * The browser (HXI_AutomatonModel.js) enforces the same rules live; this class validates
 * shared links server side and is the shape a saved build will use once this moves into Equipsets.
 */
class HXI_Automaton {

    public const SLOTS = 12;

    /** Query/JS value for "no head" / "no frame" (real ids: heads 1-6, frames 0x20-0x23, so 0 never clashes). */
    public const NONE = 0;

    /** null = no head / no frame chosen: it adds no capacity (and, for the frame, no stats). */
    public ?HXI_AutomatonHead $head;
    public ?HXI_AutomatonFrame $frame;
    /** @var int[] attachment ids (item_puppet.itemid - 0x2100), 0 = empty slot */
    public array $attachments;

    public function __construct( ?HXI_AutomatonHead $head = HXI_AutomatonHead::Harlequin,
                                 ?HXI_AutomatonFrame $frame = HXI_AutomatonFrame::Harlequin,
                                 array $attachments = [] ) {
        $this->head = $head;
        $this->frame = $frame;
        $this->attachments = array_pad( array_slice( array_map( 'intval', $attachments ), 0, self::SLOTS ), self::SLOTS, 0 );
    }

    /**
     * Build from query-string values (?head=&frame=&att=). head/frame "0" = None, missing or unknown ids fall
     * back to Harlequin; unknown attachments become an empty slot and duplicates are dropped, so a hand-edited
     * link can't produce an illegal build.
     * Over-capacity attachments are KEPT - the page highlights them instead of silently removing them.
     *
     * @param HXI_PuppetItem[] $items keyed by itemid
     */
    public static function fromRequest( string $head, string $frame, string $att, array $items ): self {
        $h = $head === (string)self::NONE ? null : ( HXI_AutomatonHead::tryFrom( (int)$head ) ?? HXI_AutomatonHead::Harlequin );
        $f = $frame === (string)self::NONE ? null : ( HXI_AutomatonFrame::tryFrom( (int)$frame ) ?? HXI_AutomatonFrame::Harlequin );

        $ids = [];
        foreach ( self::decodeAttachments( $att ) as $id ) {
            $item = $items[ $id + HXI_PuppetItem::ATTACHMENT_ID_BASE ] ?? null;
            $valid = $id > 0 && $item !== null && $item->isAttachment() && !in_array( $id, $ids, true );
            $ids[] = $valid ? $id : 0;
        }
        return new self( $h, $f, $ids );
    }

    /**
     * "1-34-0-65" <-> [1, 34, 0, 65]. Dash-separated attachment ids keep links short and readable.
     */
    public static function decodeAttachments( string $att ): array {
        if ( trim( $att ) === '' ) return [];
        return array_slice( array_map( 'intval', explode( '-', $att ) ), 0, self::SLOTS );
    }

    public function encodeAttachments(): string {
        return implode( '-', array_map( 'intval', $this->attachments ) );
    }

    public function toQuery(): array {
        return [ 'head' => $this->head?->value ?? self::NONE, 'frame' => $this->frame?->value ?? self::NONE, 'att' => $this->encodeAttachments() ];
    }

    /**
     * @param HXI_PuppetItem[] $items keyed by itemid
     * @return int[] capacity per element provided by head + frame
     */
    public function capacityMax( array $items ): array {
        $max = array_fill( 0, 8, 0 );
        foreach ( [ $this->head?->itemId(), $this->frame?->itemId() ] as $itemId ) {
            if ( $itemId === null || !isset( $items[$itemId] ) ) continue; // None: adds nothing
            foreach ( $items[$itemId]->capacities() as $e => $v ) $max[$e] += $v;
        }
        return $max;
    }

    /**
     * @param HXI_PuppetItem[] $items keyed by itemid
     * @return int[] capacity per element used by the equipped attachments
     */
    public function capacityUsed( array $items ): array {
        $used = array_fill( 0, 8, 0 );
        foreach ( $this->attachments as $id ) {
            $item = $items[ $id + HXI_PuppetItem::ATTACHMENT_ID_BASE ] ?? null;
            if ( $id === 0 || $item === null ) continue;
            foreach ( $item->capacities() as $e => $v ) $used[$e] += $v;
        }
        return $used;
    }

    /**
     * @param HXI_PuppetItem[] $items keyed by itemid
     * @return int[] element value => [used, max] for every element over capacity (empty = build is legal)
     */
    public function overCapacity( array $items ): array {
        $used = $this->capacityUsed( $items );
        $max = $this->capacityMax( $items );
        $over = [];
        foreach ( $used as $e => $u ) {
            if ( $u > $max[$e] ) $over[$e] = [ $u, $max[$e] ];
        }
        return $over;
    }
}

?>
