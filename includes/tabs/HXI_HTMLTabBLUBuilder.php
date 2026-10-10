<?php

/**
 * Markup for the BLU Builder. Self-contained so it can be dropped into Equipsets as a tab later: render()
 * returns one root element (#HXI_blu), and HXI_TabBLUBuilder.js's setLinks() wires it up from the
 * 'HXI_BLUBuilder' mw.config payload (HXI_BLUBuilderData::clientPayload()).
 *
 * PHP renders the static shell; everything that changes as the user builds (set, points, traits, stats,
 * spell list) is rendered client side.
 */
class HXI_HTMLTabBLUBuilder {

    /**
     * @param bool $showInputs false inside Equipsets: race/jobs/levels come from the gear set there, passed in
     *                         through HXI_TabBLUBuilder.js setInputs() instead of this form.
     */
    public function render( HXI_BLUBuilderInputs $inputs, array $categories, bool $showInputs = true ): string {
        // Columns only matter on desktop; below 1400px they are display:contents and the windows
        // are ordered by grid areas (see HXI_BLUBuilder.css)
        return "<div id=\"HXI_blu\" class=\"HXI_blu\">" .
                    "<div class=\"HXI_blu_layout\">" .
                        "<div class=\"HXI_blu_col\">" .
                            ( $showInputs ? $this->inputsWindow( $inputs ) : "" ) .
                            $this->setWindow() .
                            $this->abilitiesWindow() .
                        "</div>" .
                        "<div class=\"HXI_blu_col\">" .
                            $this->spellsWindow( $categories ) .
                            $this->detailsWindow() .
                        "</div>" .
                        "<div class=\"HXI_blu_col\">" .
                            $this->traitsWindow() .
                            $this->statsWindow() .
                        "</div>" .
                    "</div>" .
                    "<noscript><p class=\"HXI_blu_note\">The BLU Builder needs JavaScript enabled.</p></noscript>" .
                "</div>";
    }

    public function unavailable(): string {
        return "<div class=\"HXI_blu\"><div class=\"HXI_win HXI_blu_error\">" .
                    "<span class=\"HXI_win_title\">BLU Builder</span>" .
                    "<p>Blue magic data is unavailable. The <code>spell_list</code>, <code>blue_spell_list</code>, " .
                    "<code>blue_spell_mods</code> and <code>blue_traits</code> tables are missing from the LSB_Data database " .
                    "(import the files of the same name from <code>sql/</code>).</p>" .
                "</div></div>";
    }

    /**
     * Race + BLU as Main or Sub + one level select (1-75 main, 1-37 sub). The page is for Blue Mage only, so
     * there are no job dropdowns; HXI_TabBLUBuilder.js setupInputsForm() refills the levels when the role changes.
     */
    private function inputsWindow( HXI_BLUBuilderInputs $in ): string {
        // raceDropDown() renders disabled (Equipsets takes race from the character); here it's an input
        $race = str_replace( " disabled>", ">", HXI_HTMLOptions::raceDropDown( "HXI_blu_selectRace", $in->race ) );

        $sub = $in->bluSub();
        $role = "<select id=\"HXI_blu_selectRole\" class=\"HXI_dynamiccontent_customDropDown\">" .
                    "<option value=\"main\"" . ( $sub ? "" : " selected" ) . ">Main</option>" .
                    "<option value=\"sub\"" . ( $sub ? " selected" : "" ) . ">Sub</option>" .
                "</select>";

        $cap = $sub ? HXI_BLUBuilderInputs::MAX_SUB_LEVEL : HXI_BLUBuilderInputs::MAX_LEVEL;
        $current = $sub ? $in->slvl : $in->mlvl;
        $levels = "";
        for ( $i = 1; $i <= $cap; $i++ ) {
            $levels .= "<option value=\"$i\"" . ( $i == $current ? " selected" : "" ) . ">$i</option>";
        }
        $level = "<select id=\"HXI_blu_selectLevel\" class=\"HXI_dynamiccontent_customDropDown\">$levels</select>";

        $jobs = "<div class=\"HXI_gs_jobs\">" .
                    "<div class=\"HXI_gs_jobRow\"><label class=\"HXI_gs_jobLabel\" for=\"HXI_blu_selectRace\">Race</label>" . $race . "</div>" .
                    "<div class=\"HXI_gs_jobRow\"><label class=\"HXI_gs_jobLabel\" for=\"HXI_blu_selectRole\">BLU</label>" . $role .
                        "<span class=\"HXI_gs_lvl\"><label for=\"HXI_blu_selectLevel\">Lv</label>" . $level . "</span>" .
                    "</div>" .
                "</div>";

        return "<details class=\"HXI_win HXI_blu_inputs\" open>" .
                    "<summary class=\"HXI_win_title\">Blue Mage</summary>" .
                    $jobs .
                    "<p id=\"HXI_blu_meritNote\" class=\"HXI_blu_note\" hidden>Merits only count for Blue Mage main at Lv" . HXI_BLUBuilderInputs::MERIT_LEVEL . ".</p>" .
                    $this->meritTable( $in, 1 ) .
                    $this->meritTable( $in, 2 ) .
                "</details>";
    }

    /** One merit group: a 0-5 select per merit; the effect hints and group count are filled in by JS. */
    private function meritTable( HXI_BLUBuilderInputs $in, int $group ): string {
        $rows = "";
        foreach ( HXI_BLUBuilderInputs::MERITS as $key => $g ) {
            if ( $g != $group ) continue;
            $options = "";
            for ( $m = 0; $m <= HXI_BLUBuilderInputs::MAX_MERIT; $m++ ) {
                $options .= "<option value=\"$m\"" . ( $m == $in->merits[$key] ? " selected" : "" ) . ">$m</option>";
            }
            $label = htmlspecialchars( HXI_BLUBuilderData::MERIT_INFO[$key]['label'] );
            $rows .= "<tr>" .
                        "<th scope=\"row\"><label for=\"HXI_blu_merit_$key\">$label</label>" .
                            "<span id=\"HXI_blu_meritFx_$key\" class=\"HXI_blu_hint\"></span></th>" .
                        "<td><select id=\"HXI_blu_merit_$key\" class=\"HXI_dynamiccontent_customDropDown HXI_blu_merit\" data-merit=\"$key\">$options</select></td>" .
                     "</tr>";
        }
        return "<table class=\"HXI_blu_inputTable HXI_blu_meritTable\">" .
                    "<thead><tr><th scope=\"col\" colspan=\"2\">Group $group merits " .
                        "<span id=\"HXI_blu_meritCount$group\" class=\"HXI_blu_count\"></span></th></tr></thead>" .
                    "<tbody>$rows</tbody>" .
                "</table>";
    }

    private function setWindow(): string {
        return "<section class=\"HXI_win HXI_blu_set\">" .
                    "<h2 class=\"HXI_win_title\">Set Spells <span id=\"HXI_blu_count\" class=\"HXI_blu_count\"></span></h2>" .
                    "<div id=\"HXI_blu_points\" class=\"HXI_blu_points\"></div>" .
                    "<div id=\"HXI_blu_warning\" class=\"HXI_blu_warning\" role=\"alert\" hidden></div>" .
                    "<ol id=\"HXI_blu_slots\" class=\"HXI_blu_slots\"></ol>" .
                    "<div class=\"HXI_blu_actions\">" .
                        "<button type=\"button\" id=\"HXI_blu_share\" class=\"HXI_blu_btn HXI_blu_btnPrimary\">Copy share link</button>" .
                        "<button type=\"button\" id=\"HXI_blu_reset\" class=\"HXI_blu_btn\">Clear spells</button>" .
                    "</div>" .
                "</section>";
    }

    /** @param array $categories HXI_BLUTraitCategory value => label, for the trait filter */
    private function spellsWindow( array $categories ): string {
        $options = "<option value=\"\">All traits</option><option value=\"0\">No trait</option>";
        foreach ( $categories as $value => $label ) $options .= "<option value=\"$value\">" . htmlspecialchars( $label ) . "</option>";

        return "<section class=\"HXI_win HXI_blu_spells\">" .
                    "<h2 class=\"HXI_win_title\">Blue Magic <span id=\"HXI_blu_spellCount\" class=\"HXI_blu_count\"></span></h2>" .
                    "<div class=\"HXI_blu_tools\">" .
                        "<input type=\"search\" id=\"HXI_blu_search\" class=\"HXI_blu_search\" placeholder=\"Search spells, stats or traits\" autocomplete=\"off\" aria-label=\"Search spells\">" .
                        "<div class=\"HXI_blu_toolRow\">" .
                            "<select id=\"HXI_blu_traitFilter\" class=\"HXI_blu_select\" aria-label=\"Filter by trait\">$options</select>" .
                            "<label class=\"HXI_blu_check\"><input type=\"checkbox\" id=\"HXI_blu_fitsOnly\"> Only spells that fit</label>" .
                        "</div>" .
                    "</div>" .
                    "<div id=\"HXI_blu_spellList\" class=\"HXI_blu_spellList\"></div>" .
                "</section>";
    }

    private function traitsWindow(): string {
        return "<section class=\"HXI_win HXI_blu_traits\">" .
                    "<h2 class=\"HXI_win_title\">Job Traits</h2>" .
                    "<div id=\"HXI_blu_traitBody\"></div>" .
                "</section>";
    }

    private function statsWindow(): string {
        return "<section class=\"HXI_win HXI_blu_stats\">" .
                    "<h2 class=\"HXI_win_title\">Character Stats <span id=\"HXI_blu_statsLevel\" class=\"HXI_blu_count\"></span></h2>" .
                    "<div id=\"HXI_blu_statsBody\" aria-live=\"polite\"></div>" .
                    "<p class=\"HXI_blu_note\">No gear: race, jobs and levels only. Gear sets are in Special:Equipsets.</p>" .
                "</section>";
    }

    private function abilitiesWindow(): string {
        return "<section class=\"HXI_win HXI_blu_abilities\">" .
                    "<h2 class=\"HXI_win_title\">Job Abilities</h2>" .
                    "<div id=\"HXI_blu_abilityBody\"></div>" .
                "</section>";
    }

    private function detailsWindow(): string {
        return "<section class=\"HXI_win HXI_blu_details\">" .
                    "<h2 class=\"HXI_win_title\">Details</h2>" .
                    "<div id=\"HXI_blu_detailBody\" class=\"HXI_blu_detailBody\">" .
                        "<p class=\"HXI_blu_note\">Select a spell to see its details.</p>" .
                    "</div>" .
                "</section>";
    }
}

?>
