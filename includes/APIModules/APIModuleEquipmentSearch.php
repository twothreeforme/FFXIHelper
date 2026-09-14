<?php

//use ApiBase;

class APIModuleEquipmentSearch extends ApiBase {
    public function __construct( $main, $action ) {
        parent::__construct( $main, $action);
    }

    protected function getAllowedParams() {
        return [
            'action' => null,
			'equipmentname' => "0",
            'job' => "0",
            'minitemlvl' => "0",
            'slot' => "0",
    		];
	}

    function execute( ) {
        $params = $this->extractRequestParams();
        $result = $this->getResult();

        $queryData = [  $params['equipmentname'],
                        $params['job'],
                        $params['minitemlvl'],
                        $params['slot'],
                     ];

        wfDebugLog( 'Other', get_called_class() . ":execute: params=" . json_encode($params) );

        $finalHtml = $this->queryEquipment($queryData);
        $finalHtml = ParserHelper::wikiParse($finalHtml);
        $result->addValue($params['action'], "equipment", $finalHtml);

        wfDebugLog( 'Other', get_called_class() . ":execute: final HTML length=" . strlen($finalHtml) );
    }

    private function queryEquipment($queryData){
        $dm = new DataModel();
        $db = new DatabaseQueryWrapper();

        wfDebugLog( 'Other', get_called_class() . ":queryEquipment: queryData=" . json_encode($queryData) );

        // USE THIS ONEs
        $initialQuery = $db->getEquipmentFromDB($queryData);
        wfDebugLog( 'Other', get_called_class() . ":queryEquipment: getEquipmentFromDB returned " . count($initialQuery) . " row(s)" );

        if ( count($initialQuery) > 0 )  $db->incrementHitCounter("equipment");

        $initialQuery = $dm->parseEquipment($initialQuery, $queryData[1]);
        wfDebugLog( 'Other', get_called_class() . ":queryEquipment: parseEquipment returned " . count($initialQuery ?? []) . " item(s)" );

        $html = "";

        $html .= HXI_HTMLTableHelper::table_EquipmentQuery($initialQuery);
        wfDebugLog( 'Other', get_called_class() . ":queryEquipment: rendered HTML length=" . strlen($html) );
        return $html;
	}

}
?>