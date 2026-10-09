<?php

/**
 * Special:BLUBuilder - plan a Blue Mage spell set and see its traits, stats and job abilities.
 *
 * Thin host page: all markup lives in HXI_HTMLTabBLUBuilder and all data in HXI_BLUBuilderData, so moving the
 * builder into Equipsets later only needs a tab button, a content div and a setLinks() call there
 * (see HXI_BLUBuilderController.js).
 */
class SpecialBLUBuilder extends SpecialPage {

    public function __construct( ) {
        parent::__construct( 'BLUBuilder' );
    }

	static function onBeforePageDisplay( $out, $skin ) : void  {
		if ( $out->getTitle() == "Special:BLUBuilder" )  {
			$out->addModules(['HXI_BLUBuilder']);
		}
	}

	function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$request = $this->getRequest();

		$data = new HXI_BLUBuilderData();
		$spells = $data->getSpells();
		$tab = new HXI_HTMLTabBLUBuilder();

		if ( count( $spells ) == 0 ) {
			$output->addHTML( $tab->unavailable() );
			return;
		}

		// Shared link state: ?spells=513-547-...  (see HXI_BLUBuild::toQuery())
		$build = HXI_BLUBuild::fromRequest( $request->getText( 'spells' ), $spells );

		// Player inputs: ?race=&mjob=&mlvl=&sjob=&slvl=&bmerit=  (see HXI_BLUBuilderInputs::fromRequest())
		$inputs = HXI_BLUBuilderInputs::fromRequest( $request );

		$categories = [];
		foreach ( HXI_BLUTraitCategory::cases() as $c ) $categories[ $c->value ] = $c->label();
		asort( $categories );

		$output->addJsConfigVars( 'HXI_BLUBuilder', $data->clientPayload( $build, $inputs ) );
		$output->addHTML( $tab->render( $inputs, $categories ) );
	}
}
