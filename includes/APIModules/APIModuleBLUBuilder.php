<?php

/**
 * api.php?action=blubuilder_stats - character stats without and with a blue magic set.
 *
 * Both columns come from HXI_CharacterStatCalculator (the Equipsets calculator), so the BLU Builder and
 * Equipsets can never disagree. The set adds:
 *  - the set spells' stat bonuses (blue_spell_mods), like gear
 *  - the blue traits they unlock (HXI_BLUBuild::blueTraits()), which never stack with the jobs' own traits
 * No gear is worn: the numbers are race + jobs + levels (+ the set).
 *
 * Params use the share-link names (HXI_BLUBuilderInputs::fromRequest, HXI_BLUBuild::fromRequest):
 * race, mjob, mlvl, sjob, slvl, bmerit, spells.
 */
class APIModuleBLUBuilder extends ApiBase {

    public function __construct( $main, $action ) {
        parent::__construct( $main, $action );
    }

    protected function getAllowedParams() {
        // values are parsed and clamped by HXI_BLUBuilderInputs::fromRequest() / HXI_BLUBuild::fromRequest()
        return [
            'race'   => 0,
            'mjob'   => HXI_BLUBuilderInputs::BLU,
            'mlvl'   => HXI_BLUBuilderInputs::MAX_LEVEL,
            'sjob'   => 0,
            'slvl'   => 0,
            'bmerit' => '',   // merit upgrades, dash list in HXI_BLUBuilderInputs::MERITS order
            'spells' => '',
        ];
    }

    function execute() {
        $params = $this->extractRequestParams();
        $data = new HXI_BLUBuilderData();
        $spells = $data->getSpells();
        if ( count( $spells ) == 0 ) $this->dieWithError( 'BLU spell data is unavailable (import sql/blue_spell_list.sql etc.).', 'nodata' );

        $inputs = HXI_BLUBuilderInputs::fromRequest( $this->getRequest() );
        $build = HXI_BLUBuild::fromRequest( $params['spells'], $spells );
        $active = $build->activeSpells( $spells, $inputs );

        $extraTraits = [];
        foreach ( HXI_BLUBuild::blueTraits( $active, $data->getTraitTiers() )['traits'] as $t ) {
            $extraTraits[ $t['modid'] ] = max( $extraTraits[ $t['modid'] ] ?? 0, $t['value'] );
        }

        $base = $this->stats( $inputs, [], [] );
        $set = $this->stats( $inputs, $extraTraits, HXI_BLUBuild::spellMods( $active ) );

        $result = $this->getResult();
        $result->addValue( 'blubuilder', 'base', $base );
        $result->addValue( 'blubuilder', 'set', $set );
        $result->addValue( 'blubuilder', 'status', $build->status( $spells, $inputs ) );
    }

    private function stats( HXI_BLUBuilderInputs $in, array $extraTraits, array $extraMods ): array {
        $calc = new HXI_CharacterStatCalculator( $in->race, $in->mlvl, $in->slvl, $in->mjob, $in->sjob, null,
                                                 array_fill( 0, 16, null ), $extraTraits, $extraMods );
        $out = [
            'HP' => (int)$calc->HP, 'MP' => (int)$calc->MP,
            'STR' => (int)$calc->STR, 'DEX' => (int)$calc->DEX, 'VIT' => (int)$calc->VIT, 'AGI' => (int)$calc->AGI,
            'INT' => (int)$calc->INT, 'MND' => (int)$calc->MND, 'CHR' => (int)$calc->CHR,
            'DEF' => (int)$calc->DEF, 'ATT' => (int)$calc->ATT, 'ACC' => (int)$calc->ACC, 'EVA' => (int)$calc->EVA,
        ];
        // every other modifier a blue spell or blue trait can touch, by modifier id
        $mods = [];
        foreach ( array_keys( HXI_BLUBuilderData::MOD_LABELS ) as $modId ) {
            $mods[$modId] = $calc->modifier( HXI_ModDictionary::getName( $modId ) );
        }
        $out['mods'] = $mods;
        return $out;
    }
}

?>
