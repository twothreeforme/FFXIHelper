<?php


class HXI_HTMLOptions {
    public function __construct() {
      }

      public static function jobDropDown($classname, $sharedJob = null){
        $html = "<select id=\"". $classname ."\" defaultValue=\"0\" class=\"HXI_dynamiccontent_customDropDown\">";
        $html .= "<option value=\"0\">Any</option>";
        $html .= "<option value=\"1\">Warrior</option>";
        $html .= "<option value=\"2\">Monk</option>";
        $html .= "<option value=\"3\">White Mage</option>";
        $html .= "<option value=\"4\">Black Mage</option>";
        $html .= "<option value=\"5\">Red Mage</option>";
        $html .= "<option value=\"6\">Thief</option>";
        $html .= "<option value=\"7\">Paladin</option>";
        $html .= "<option value=\"8\">Dark Knight</option>";
        $html .= "<option value=\"9\">Beastmaster</option>";
        $html .= "<option value=\"10\">Bard</option>";
        $html .= "<option value=\"11\">Ranger</option>";
        $html .= "<option value=\"12\">Samurai</option>";
        $html .= "<option value=\"13\">Ninja</option>";
        $html .= "<option value=\"14\">Dragoon</option>";
        $html .= "<option value=\"15\">Summoner</option>";
        $html .= "<option value=\"16\">Blue Mage</option>";
        $html .= "<option value=\"17\">Corsair</option>";
        $html .= "<option value=\"18\">Puppetmaster</option>";
        // $html .= "<option value=\"19\">Dancer</option>";
        // $html .= "<option value=\"20\">Scholar</option>";
        // $html .= "<option value=\"21\">Geomancer</option>";
        // $html .= "<option value=\"22\">Rune Fencer</option>";

        // Select the job option from the shared link
        if ( $sharedJob != 0 ){
            $html = str_replace("value=\"$sharedJob\"", "value=\"$sharedJob\" selected=\"selected\"", $html);
        }
        
        $html .= "</select>";
        return $html;
    }

    public static function raceDropDown($classname, $sharedRace = null){
        $html = "<select id=\"". $classname ."\" defaultValue=\"0\" class=\"HXI_dynamiccontent_customDropDown\" disabled>";
        $html .= "<option value=\"0\" selected=\"selected\">Hume</option>";
        $html .= "<option value=\"1\">Elvaan</option>";
        $html .= "<option value=\"2\">Tarutaru</option>";
        $html .= "<option value=\"3\">Mithra</option>";
        $html .= "<option value=\"4\">Galka</option>";
        $html .= "</select>";

        // Select the race option from the shared link
        if ( $sharedRace != 0 ){
            $html = str_replace("value=\"$sharedRace\"", "value=\"$sharedRace\" selected=\"selected\"", $html);
        }
        
        //throw new Exception ($html);
        return $html;
    }

    public static function setsDropDown($classname){
        $user = RequestContext::getMain()->getUser();
        $uid = $user->getId();
        $db = new DatabaseQueryWrapper();
        $userSets = $db->getUserSetsFromUserID($uid);

        $html = "<select id=\"". $classname ."\" defaultValue=\"0\" class=\"HXI_dynamiccontent_customDropDown\" ";

        if ( count($userSets) > 0 ){
            $html .= ">";
            //$vars = new HXI_Variables();

            $currentJobType = 0;
            foreach ($userSets as $jobtype => $val ) {
                if ( $currentJobType != $jobtype ){
                    $currentJobType = $jobtype;
                    $html .= " <optgroup label=\"" . $jobtype . "\">";
                }
                foreach ( $val as $set ){
                    $html .= "<option value=\"" . $set["usersetid"] . "\">" . $set["setname"] . "</option>";
                    // $html .= "<option value=\"1\">Elvaan</option>";
                    //$html .= "<button id=\"HXI_setButton_" . $set["setname"] . "\" class=\"" . $classname . "\">" . $set["setname"] . "</button>";
                }
            }

        }
        else {
            $html .= "disabled>";
            $html .= "<option value=\"0\">None</option>";
        }

        $html .= "</select>";
        return $html;

    }

    public static function levelRange($classname, $sharedLvl = null){
        $html = "<select id=\"". $classname ."\" class=\"HXI_dynamiccontent_customDropDown\">";

        for ($i = 0; $i <= 75; $i++) {
            if ( $i == 0 ) $html .= "<option value=\"" . $i . "\">None</option>";
            else $html .= "<option value=\"" . $i . "\">" . $i . "</option>";
        }
        $html .= "</select>";

        // Select the lvl range option from the shared link
        if ( $sharedLvl != 0 ){
            $html = str_replace("value=\"$sharedLvl\"", "value=\"$sharedLvl\" selected=\"selected\"", $html);
        }
        

        return $html;
    }

    public static function subLevelRange($classname, $sharedLvl = null){
        $html = "<select id=\"". $classname ."\" class=\"HXI_dynamiccontent_customDropDown\">";

        for ($i = 0; $i <= 37; $i++) {
            if ( $i == 0 ) $html .= "<option value=\"" . $i . "\">None</option>";
            else $html .= "<option value=\"" . $i . "\">" . $i . "</option>";
        }
        $html .= "</select>";

        // Select the lvl range option from the shared link
        if ( $sharedLvl != 0 ){
            $html = str_replace("value=\"$sharedLvl\"", "value=\"$sharedLvl\" selected=\"selected\"", $html);
        }        

        return $html;
    }

    private static function zoneNamelist($fishing = null){
        $db = new DatabaseQueryWrapper();
        if ( $fishing == true ) $zonelist = $db->getZoneListFishing();
        else $zonelist = $db->getZoneList();

        foreach ($zonelist as $row) {
			$temp = ParserHelper::zoneERA_forList($row->name);
			if ( !isset($temp) ) continue;
            if ( $fishing == false && ExclusionsHelper::zoneIsTown($temp) ) continue;
            if ( ctype_digit($temp) ) continue;
			$result[$temp]=$row->name;
			//print_r($result[$temp] .", " . $row->name);
		}
		$result[' ** Search All Zones ** '] = "searchallzones";
		ksort($result);
		return $result ;
    }

    public static function zonesDropDown($id = null){
        if ( $id == null ) $id = "HXI_dynamiccontent_selectZoneName";
        $html = "<select id=\"$id\" class=\"HXI_dynamiccontent_customDropDown\">";
        $zoneNamesList = self::zoneNameList();

        foreach ($zoneNamesList as $key => $value) {
            $html .= "<option value=\"" . $value . "\">" . $key . "</option>";
        }

        $html .= "</select>";
        return $html;
    }

    public static function fishZonesDropDown(){
        $html = "<select id=\"HXI_dynamiccontent_selectFishingZone\" class=\"HXI_dynamiccontent_customDropDown\">";
        $zoneNamesList = self::zoneNameList(true);

        foreach ($zoneNamesList as $key => $value) {
            $html .= "<option value=\"" . $value . "\">" . $key . "</option>";
        }

        $html .= "</select>";
        return $html;
    }

    /**
     * 
     *      True = shoud select default character from array
     *      False = should select NONE character
     *      (string) = should select character with name (string)
     */
    public static function charactersButtonsList($userCharacters, $selectDefaultCharacter = null){

        // "<button id=\"HXI_newCharButton\" class=\"HXI_newCharButton\"></button>"
        // $html = "<button id=\"HXI_newCharButton\" class=\"HXI_newCharButton\"></button>";
        $html = "";

        //wfDebugLog( 'Equipsets', get_called_class() . ":charactersButtonsList:" . json_encode($selectDefaultCharacter) . ":gettype " .  gettype($selectDefaultCharacter) );
        //wfDebugLog( 'Equipsets', get_called_class() . ":charactersButtonsList:" . $html );

        if ( !is_null($userCharacters) && count($userCharacters) > 0 ){
            /* array of FFIXPH_Character objects */
            foreach ($userCharacters as $char) {
                $classlist = "HXI_charButton";    
                if ( $char->def != 0 ) {
                    $classlist .= " HXI_charButton_default";
                    
                    if ( $selectDefaultCharacter === true )  {
                        //$html = str_replace("HXI_charButtonselectDefaultCharactered", "", $html);
                        $classlist .= " HXI_charButtonselected";
                    }
                }

                if ( gettype($selectDefaultCharacter) == 'string' && $char->charname == $selectDefaultCharacter && !str_contains($classlist, "HXI_charButtonselected") ) {
                    $classlist .= " HXI_charButtonselected";
                }

                $html .= "<button id=\"HXI_charButton_" . $char->charname . "\" class=\"" . $classlist . "\">" . $char->charname . "</button>";
            }
        }
        
        $classlist = "HXI_charButton";
        if ( $selectDefaultCharacter === false ||  is_null($userCharacters) ){
            $classlist .= " HXI_charButtonselected";
            //wfDebugLog( 'Equipsets', get_called_class() . ":charactersButtonsList:" . json_encode($selectDefaultCharacter) );
        }
        $html = "<button id=\"HXI_charButtonNone\" class=\"" . $classlist . "\">None</button>" . $html;
        //$html .= "<button id=\"HXI_charButtonNone\" class=\"HXI_charButton\">None</button>";

        return $html;
    }


    public static function setsButtonsList(){
        $html = "";

        $user = RequestContext::getMain()->getUser();
        $uid = $user->getId();
        if ( $uid != 0 && $uid != null ){
            $db = new DatabaseQueryWrapper();
            $userSets = $db->getUserSetsFromUserID($uid);
            //throw new Exception ( json_encode($userSets));
            if ( count($userSets) > 0 ){
                foreach ($userSets as $set) {
                    // throw new Exception ( json_encode($set));
                    $classlist = "HXI_setButton";
                    // if ( $set["def"] != 0  ) $classlist .= " HXI_setButton_default";
                    $html .= "<button id=\"HXI_setButton_" . $set["usersetid"] . "\" class=\"" . $classlist . "\">" . $set["setname"] . "</button>";
                }
            }
        }

        return $html;
    }

    public static function selectableButtonsBar($barClassname, $userCharacters, $selectDefaultCharacter = null){
        $divName = "";
        $newButton = "";
        if ( $barClassname == "HXI_equipsets_setSelect"){
            $divName = "HXI_equipsets_setSelect";
            $newButton = "HXI_newSetButton";
        }
        else if ( $barClassname == "HXI_equipsets_charSelect" ){
            $divName = "HXI_equipsets_charSelect";
            $newButton = "HXI_newCharButton";
        }

        $html = "<div id=\"$divName\">" .
            "<button id=\"$newButton\" class=\"$newButton\">
                <svg width=\"10\" height=\"10\" viewBox=\"0 0 10 10\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\">
                    <line x1=\"0\" y1=\"5\" x2=\"10\" y2=\"5\"  stroke-linecap=\"round\"/>
                    <line x1=\"5\" y1=\"0\" x2=\"5\" y2=\"10\"  stroke-linecap=\"round\"/>
                </svg>
                <span id=\"$newButton-text\">";
        if ( $newButton == "HXI_newSetButton") $html .= "Save this set" ;
        else $html .= "New";
        $html .= "</span></button>";
        
        $html .= "<div id=\"HXI_equipsets_charactersButtonsList\">";
        if ( $barClassname == "HXI_equipsets_charSelect" ) $html .= self::charactersButtonsList($userCharacters, $selectDefaultCharacter);
        //else if ( $barClassname == "HXI_equipsets_setSelect") $html .= self::setsButtonsList();
					
		$html .= "</div></div>";
        return $html;
    }

    public static function saveButton($classname){
        $html = "<button id=\"$classname\" class=\"$classname\">
                <svg width=\"10\" height=\"10\" viewBox=\"0 0 10 10\" version=\"1.1\" xmlns=\"http://www.w3.org/2000/svg\">
                    <line x1=\"0\" y1=\"5\" x2=\"10\" y2=\"5\"  stroke-linecap=\"round\"/>
                    <line x1=\"5\" y1=\"0\" x2=\"5\" y2=\"10\"  stroke-linecap=\"round\"/>
                </svg>
                <span id=\"$classname-text\">";
        if ( $classname == "HXI_newSetButton") $html .= "Save this set" ;
        else $html .= "New";
        $html .= "</span></button>";
        return $html;
    }

    public static function setsList(){

        $html = "<div id=\"HXI_Equipsets_setManagement\" class=\"HXI_Equipsets_setManagement\">";
        //$html .= HXI_HTMLOptions::selectableButtonsBar("HXI_equipsets_setSelect");
        
        $html .="<div style=\"width: 100%; flex-wrap: nowrap; display: flex;flex-direction: row;justify-content: space-between;\">";
        $html .="<h3 style=\"display:inline-block;margin-top:0em;padding:0px;\">Available Sets</h3>";
        $html .="<div id=\"HXI_menuIcon\" class=\"HXI_menuIcon\">" .
                    "<div class=\"HXI_menuIcon_bar1\"></div>" .
                    "<div class=\"HXI_menuIcon_bar2\"></div>" .
                    "<div class=\"HXI_menuIcon_bar3\"></div>" .
                "</div>";
        $html .="</div>";
        
        $html .= "<div id=\"HXI_Equipsets_setManagement_setsList\" class=\"HXI_Equipsets_setManagement_setsList\">";
        $user = RequestContext::getMain()->getUser();
        $uid = $user->getId();
        if ( $uid != 0 && $uid != null ){
            $db = new DatabaseQueryWrapper();
            $userSets = $db->getUserSetsFromUserID($uid);
            //throw new Exception ( json_encode($userSets));
            if ( count($userSets) > 0 ){

                $html .= self::setsListTable($userSets);
            }

        }
        $html .= "</div>";
        //$html .= "<button id=\"HXI_deleteSetButton\" class=\"HXI_deleteSetButton\">Remove set</button>";

        $html .= "</div>";
        return $html;
    }

    public static function setsListTable($userSets){
        $html = "";
            $html = "<table id=\"HXI_Equipsets_setManagement_setsListTable\" class=\"HXI_Equipsets_setManagement_setsListTable\">";
            if ( count($userSets) > 0 ){
                foreach ($userSets as $jobtype => $val ) {
                    $html .="<tr>
                                <th colspan=\"2\">$jobtype</th>
                            </tr>";

                    foreach ( $val as $set ){
                        $html .="<tr>";
                        if (  gettype($set) == "string") throw new Exception ( json_encode($val));
                        $html .= "<td data-value=\"" . $set["usersetid"] . "\">" . $set["setname"] ."</td>";
                        $html .= "<td class =\"HXI_Equipsets_setManagement_setsListTable_Remove\" data-value=\"" . $set["usersetid"] . "\" style=\"color:red;text-align:end;width: 1%;white-space: nowrap;\" >Remove</td>";
                        $html .="<tr>";
                    }

                }
            }
            $html .= "</table>";

        return $html;
    }

}

?>