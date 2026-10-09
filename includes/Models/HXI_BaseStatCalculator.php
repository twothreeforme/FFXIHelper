<?php

/**
 * Shared base for the character and mob stat calculators.
 * Only holds what both genuinely share: the modifier accumulator. The two calculators' entry points
 * differ (HXI_CharacterStatCalculator is built from gear + getStats(), HXI_MobStatCalculator::calculate()
 * fills a HXI_Mob from SQL rows), so no common interface is defined.
 */
abstract class HXI_BaseStatCalculator {

    protected array $modifiers = [];

    /**
     * Adds $modValue to the running total for $modlabel (a HXI_ModDictionary / mob mod name).
     */
    /** Running total for one modifier (HXI_ModDictionary / mob mod name), 0 if never added. */
    public function modifier( string $modlabel ): int {
        return (int)( $this->modifiers[$modlabel] ?? 0 );
    }

    protected function addMod($modlabel, $modValue){
        if ( !isset($this->modifiers[$modlabel]) ) $this->modifiers[$modlabel] = intval($modValue);
        else $this->modifiers[$modlabel] += intval($modValue);
    }
}
