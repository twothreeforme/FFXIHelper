<?php

/**
 * Markup for the Automaton Builder. Self-contained so it can be dropped into Equipsets as a
 * tab later: render() returns one root element (#HXI_ab), and HXI_TabAutomaton.js's setLinks()
 * wires it up from the 'HXI_Automaton' mw.config payload (HXI_AutomatonData::clientPayload()).
 *
 * PHP renders the static shell; everything that changes as the user builds (slots, capacity
 * bars, effects, picker list) is rendered client side.
 */
class HXI_HTMLTabAutomaton {

    /**
     * @param array $gearCaps   HXI_AutomatonData::gearCaps() - ceilings for the gear bonus inputs
     * @param int $skillMax     HXI_AutomatonData::playerSkillMax() at the inputs' level - ceiling for "your skill"
     * @param bool $showInputs false inside Equipsets: jobs/levels/merits/gear come from the gear set there,
     *                         passed in through HXI_TabAutomaton.js setInputs() instead of this form.
     */
    public function render( HXI_Automaton $automaton, HXI_AutomatonInputs $inputs, array $gearCaps, int $skillMax, bool $showInputs = true ): string {
        // Columns only matter on desktop; below 1100px they are display:contents and the windows
        // are ordered by grid areas (see HXI_Automaton.css)
        return "<div id=\"HXI_ab\" class=\"HXI_ab\">" .
                    "<div class=\"HXI_ab_layout\">" .
                        "<div class=\"HXI_ab_col\">" .
                            ( $showInputs ? $this->inputsWindow( $inputs, $gearCaps, $skillMax ) : "" ) .
                            $this->buildWindow( $automaton ) .
                            $this->detailsWindow() .
                        "</div>" .
                        "<div class=\"HXI_ab_col\">" .
                            $this->attachmentsWindow() .
                            $this->statsWindow() .
                        "</div>" .
                        "<div class=\"HXI_ab_col\">" .
                            $this->effectsWindow() .
                        "</div>" .
                    "</div>" .
                    $this->pickerDialog() .
                    "<noscript><p class=\"HXI_ab_note\">The Automaton Builder needs JavaScript enabled.</p></noscript>" .
                "</div>";
    }

    public function unavailable(): string {
        return "<div class=\"HXI_ab\"><div class=\"HXI_win HXI_ab_error\">" .
                    "<span class=\"HXI_win_title\">Automaton Builder</span>" .
                    "<p>Automaton data is unavailable. The <code>item_puppet</code> table is missing from the LSB_Data database " .
                    "(import <code>sql/item_puppet.sql</code>).</p>" .
                "</div></div>";
    }

    /**
     * Everything the stats need from the player. Job/level rows are the exact Equipsets controls
     * (HXI_HTMLOptions dropdowns + Max sub checkbox, see HXI_Equipsets::querySection()).
     */
    private function inputsWindow( HXI_AutomatonInputs $in, array $gearCaps, int $skillMax ): string {
        $maxedSub = "<label class=\"HXI_gs_max\"><input id=\"HXI_ab_checkboxMaxSub\" type=\"checkbox\"" . ( $in->maxSub ? " checked=\"checked\"" : "" ) . "><span>Max</span></label>";

        $jobs = "<div class=\"HXI_gs_jobs\">" .
                    "<div class=\"HXI_gs_jobRow\"><label class=\"HXI_gs_jobLabel\" for=\"HXI_ab_selectMJob\">Main</label>" .
                        HXI_HTMLOptions::jobDropDown( "HXI_ab_selectMJob", $in->mjob ) .
                        "<span class=\"HXI_gs_lvl\"><label for=\"HXI_ab_selectMLevel\">Lv</label>" . HXI_HTMLOptions::levelRange( "HXI_ab_selectMLevel", $in->mlvl ) . "</span>" .
                    "</div>" .
                    "<div class=\"HXI_gs_jobRow\"><label class=\"HXI_gs_jobLabel\" for=\"HXI_ab_selectSJob\">Sub</label>" .
                        HXI_HTMLOptions::jobDropDown( "HXI_ab_selectSJob", $in->sjob ) . $maxedSub .
                        "<span class=\"HXI_gs_lvl\"><label for=\"HXI_ab_selectSLevel\">Lv</label>" . HXI_HTMLOptions::subLevelRange( "HXI_ab_selectSLevel", $in->slvl ) . "</span>" .
                    "</div>" .
                "</div>";

        $skillRows = "";
        foreach ( HXI_AutomatonInputs::SKILLS as $k ) {
            $label = ucfirst( $k );
            $value = $in->skills[$k];
            $capped = $value === null;
            $skillRows .= "<tr>" .
                            "<th scope=\"row\"><label for=\"HXI_ab_skill_$k\">$label</label></th>" .
                            "<td><input type=\"number\" id=\"HXI_ab_skill_$k\" class=\"HXI_ab_num\" min=\"0\" max=\"$skillMax\" inputmode=\"numeric\"" .
                                ( $capped ? " disabled" : " value=\"$value\"" ) . "></td>" .
                            "<td class=\"HXI_ab_center\"><input type=\"checkbox\" id=\"HXI_ab_skillCap_$k\" aria-label=\"$label skill at cap\"" . ( $capped ? " checked" : "" ) . "></td>" .
                          "</tr>";
        }

        $merits = "";
        for ( $m = 0; $m <= HXI_AutomatonInputs::MAX_SKILL_MERITS; $m++ ) {
            $sel = $m == $in->merits ? " selected" : "";
            $merits .= "<option value=\"$m\"$sel>$m</option>";
        }

        $gearLabels = [ 'elemCapacity' => 'Elemental Capacity', 'melee' => 'Automaton Melee Skill', 'ranged' => 'Automaton Ranged Skill',
                        'magic' => 'Automaton Magic Skill', 'level' => 'Automaton Level' ];
        // Ceilings = the best gear a PUP can wear at or below the level cap (HXI_AutomatonData::gearCaps()).
        // A bonus no such gear provides is locked at 0.
        $gearRows = "";
        foreach ( HXI_AutomatonInputs::GEAR as $k ) {
            $max = $gearCaps[$k]['max'] ?? 0;
            $sources = $gearCaps[$k]['sources'] ?? [];
            $hint = $max > 0
                ? "Max +$max: " . htmlspecialchars( implode( ", ", $sources ) )
                : "No gear at Lv" . HXI_AutomatonInputs::MAX_LEVEL . " or below";
            $gearRows .= "<tr>" .
                            "<th scope=\"row\"><label for=\"HXI_ab_gear_$k\">{$gearLabels[$k]}</label>" .
                                "<span class=\"HXI_ab_hint\">$hint</span></th>" .
                            "<td><span class=\"HXI_ab_plusSign\">+</span><input type=\"number\" id=\"HXI_ab_gear_$k\" class=\"HXI_ab_num\" min=\"0\" max=\"$max\" inputmode=\"numeric\" value=\"{$in->gear[$k]}\"" .
                                ( $max == 0 ? " disabled" : "" ) . "></td>" .
                         "</tr>";
        }

        return "<details class=\"HXI_win HXI_ab_inputs\" open>" .
                    "<summary class=\"HXI_win_title\">Puppetmaster</summary>" .
                    $jobs .
                    "<p id=\"HXI_ab_pupNote\" class=\"HXI_ab_note\" hidden>Set Puppetmaster as your main or sub job to see automaton stats.</p>" .
                    "<table class=\"HXI_ab_inputTable\">" .
                        "<thead><tr><th scope=\"col\">Automaton skill</th><th scope=\"col\">Your skill</th><th scope=\"col\">At cap</th></tr></thead>" .
                        "<tbody>$skillRows</tbody>" .
                    "</table>" .
                    "<table class=\"HXI_ab_inputTable\">" .
                        "<tbody><tr>" .
                            "<th scope=\"row\"><label for=\"HXI_ab_merits\">Automaton Skills merits</label>" .
                                "<span class=\"HXI_ab_hint\">+" . HXI_AutomatonData::SKILL_MERIT_VALUE . " skill each, PUP main Lv75</span></th>" .
                            "<td><select id=\"HXI_ab_merits\" class=\"HXI_dynamiccontent_customDropDown\">$merits</select></td>" .
                        "</tr></tbody>" .
                    "</table>" .
                    "<table class=\"HXI_ab_inputTable\">" .
                        "<thead><tr><th scope=\"col\" colspan=\"2\">Gear bonuses</th></tr></thead>" .
                        "<tbody>$gearRows</tbody>" .
                    "</table>" .
                "</details>";
    }

    private function statsWindow(): string {
        return "<section class=\"HXI_win HXI_ab_stats\">" .
                    "<h2 class=\"HXI_win_title\">Automaton Stats <span id=\"HXI_ab_statsLevel\" class=\"HXI_ab_count\"></span></h2>" .
                    "<div id=\"HXI_ab_statsBody\"></div>" .
                "</section>";
    }

    private function buildWindow( HXI_Automaton $automaton ): string {
        $none = HXI_Automaton::NONE;
        $heads = "<option value=\"$none\"" . ( $automaton->head === null ? " selected" : "" ) . ">None</option>";
        foreach ( HXI_AutomatonHead::cases() as $h ) {
            $sel = $h === $automaton->head ? " selected" : "";
            $heads .= "<option value=\"{$h->value}\"$sel>{$h->label()}</option>";
        }
        $frames = "<option value=\"$none\"" . ( $automaton->frame === null ? " selected" : "" ) . ">None</option>";
        foreach ( HXI_AutomatonFrame::cases() as $f ) {
            $sel = $f === $automaton->frame ? " selected" : "";
            $frames .= "<option value=\"{$f->value}\"$sel>{$f->label()}</option>";
        }

        return "<section class=\"HXI_win HXI_ab_build\">" .
                    "<h2 class=\"HXI_win_title\">Automaton</h2>" .
                    "<div class=\"HXI_ab_parts\">" .
                        "<label class=\"HXI_ab_part\">" .
                            "<span class=\"HXI_ab_partIcon\" id=\"HXI_ab_headIcon\"></span>" .
                            "<span class=\"HXI_ab_partLabel\">Head</span>" .
                            "<select id=\"HXI_ab_head\" class=\"HXI_ab_select\">$heads</select>" .
                        "</label>" .
                        "<label class=\"HXI_ab_part\">" .
                            "<span class=\"HXI_ab_partIcon\" id=\"HXI_ab_frameIcon\"></span>" .
                            "<span class=\"HXI_ab_partLabel\">Frame</span>" .
                            "<select id=\"HXI_ab_frame\" class=\"HXI_ab_select\">$frames</select>" .
                        "</label>" .
                    "</div>" .
                    "<h3 class=\"HXI_ab_subtitle\">Elemental Capacity</h3>" .
                    "<div id=\"HXI_ab_capacity\" class=\"HXI_ab_capacity\"></div>" .
                "</section>";
    }

    private function attachmentsWindow(): string {
        return "<section class=\"HXI_win HXI_ab_attach\">" .
                    "<h2 class=\"HXI_win_title\">Attachments <span id=\"HXI_ab_count\" class=\"HXI_ab_count\"></span></h2>" .
                    "<div id=\"HXI_ab_warning\" class=\"HXI_ab_warning\" role=\"alert\" hidden></div>" .
                    "<div id=\"HXI_ab_slots\" class=\"HXI_ab_slots\"></div>" .
                    "<div class=\"HXI_ab_actions\">" .
                        "<button type=\"button\" id=\"HXI_ab_share\" class=\"HXI_ab_btn HXI_ab_btnPrimary\">Copy share link</button>" .
                        "<button type=\"button\" id=\"HXI_ab_reset\" class=\"HXI_ab_btn\">Clear attachments</button>" .
                    "</div>" .
                "</section>";
    }

    private function effectsWindow(): string {
        $selects = "";
        for ( $m = 1; $m <= 3; $m++ ) {
            $selects .= "<label class=\"HXI_ab_maneuver\"><span>Maneuver $m</span>" .
                            "<select class=\"HXI_ab_select HXI_ab_maneuverSelect\" data-maneuver=\"$m\"><option value=\"\">None</option></select>" .
                        "</label>";
        }

        return "<section class=\"HXI_win HXI_ab_effects\">" .
                    "<h2 class=\"HXI_win_title\">Effects</h2>" .
                    "<div class=\"HXI_ab_maneuvers\">$selects</div>" .
                    "<div id=\"HXI_ab_effectList\" class=\"HXI_ab_effectList\"></div>" .
                "</section>";
    }

    private function detailsWindow(): string {
        return "<section class=\"HXI_win HXI_ab_details\">" .
                    "<h2 class=\"HXI_win_title\">Details</h2>" .
                    "<div id=\"HXI_ab_detailBody\" class=\"HXI_ab_detailBody\">" .
                        "<p class=\"HXI_ab_note\">Select an attachment, head or frame to see its details.</p>" .
                    "</div>" .
                "</section>";
    }

    private function pickerDialog(): string {
        return "<dialog id=\"HXI_ab_picker\" class=\"HXI_ab_picker\" aria-labelledby=\"HXI_ab_pickerTitle\">" .
                    "<div class=\"HXI_win\">" .
                        "<div class=\"HXI_win_title HXI_ab_pickerHead\">" .
                            "<span id=\"HXI_ab_pickerTitle\">Choose attachment</span>" .
                            "<button type=\"button\" class=\"HXI_ab_close\" id=\"HXI_ab_pickerClose\" aria-label=\"Close\">&times;</button>" .
                        "</div>" .
                        "<div class=\"HXI_ab_pickerTools\">" .
                            "<input type=\"search\" id=\"HXI_ab_pickerSearch\" class=\"HXI_ab_search\" placeholder=\"Search attachments\" autocomplete=\"off\">" .
                            "<div id=\"HXI_ab_pickerFilter\" class=\"HXI_ab_filter\" role=\"group\" aria-label=\"Filter by element\"></div>" .
                            "<label class=\"HXI_ab_fitsOnly\"><input type=\"checkbox\" id=\"HXI_ab_pickerFits\"> Only show attachments that fit</label>" .
                            "<div id=\"HXI_ab_pickerMsg\" class=\"HXI_ab_warning\" role=\"alert\" hidden></div>" .
                        "</div>" .
                        "<div id=\"HXI_ab_pickerList\" class=\"HXI_ab_pickerList\" role=\"listbox\"></div>" .
                        "<div class=\"HXI_ab_pickerFoot\">" .
                            "<button type=\"button\" id=\"HXI_ab_pickerRemove\" class=\"HXI_ab_btn\">Remove from slot</button>" .
                        "</div>" .
                    "</div>" .
                "</dialog>";
    }
}

?>
