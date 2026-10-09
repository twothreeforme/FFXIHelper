<?php

/**
 * Special:AutomatonBuilder - plan a PUP automaton (head, frame, 12 attachments).
 *
 * Thin host page: all markup lives in HXI_HTMLTabAutomaton and all data in HXI_AutomatonData,
 * so moving the builder into Equipsets later only needs a tab button, a content div and a
 * setLinks() call there (see HXI_AutomatonController.js).
 */
class SpecialAutomatonBuilder extends SpecialPage {

    public function __construct( ) {
        parent::__construct( 'AutomatonBuilder' );
    }

	static function onBeforePageDisplay( $out, $skin ) : void  {
		if ( $out->getTitle() == "Special:AutomatonBuilder" )  {
			$out->addModules(['HXI_AutomatonBuilder']);
		}
	}

	function execute( $par ) {
		$this->setHeaders();
		$output = $this->getOutput();
		$request = $this->getRequest();

		$data = new HXI_AutomatonData();
		$items = $data->getItems();
		$tab = new HXI_HTMLTabAutomaton();

		if ( count( $items ) == 0 ) {
			$output->addHTML( $tab->unavailable() );
			return;
		}

		// Shared link state: ?head=&frame=&att=  (see HXI_Automaton::toQuery())
		$automaton = HXI_Automaton::fromRequest( $request->getText( 'head' ),
												 $request->getText( 'frame' ),
												 $request->getText( 'att' ),
												 $items );

		// Player inputs: ?mjob=&mlvl=&sjob=&slvl=&askill=&amerit=&agear=  (see HXI_AutomatonInputs::fromRequest())
		$gearCaps = $data->gearCaps();
		$inputs = HXI_AutomatonInputs::fromRequest( $request, $gearCaps, fn( $level ) => $data->playerSkillMax( $level ) );

		$output->addJsConfigVars( 'HXI_Automaton', $data->clientPayload( $automaton, $inputs ) );
		$output->addHTML( $tab->render( $automaton, $inputs, $gearCaps, $data->playerSkillMax( $inputs->level() ) ) );
	}
}
