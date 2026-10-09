/*
 * Entry point for the standalone Special:AutomatonBuilder page.
 * Inside Equipsets this file is not needed - HXI_Equipsets_TabsController.js calls
 * TabAutomaton.setLinks({ syncUrl: false }) like it does for the other tabs.
 */
var TabAutomaton = require("./HXI_TabAutomaton.js");

var initiallyLoaded = false;
mw.hook('wikipage.content').add( function () {
  if ( initiallyLoaded == true ) return;
  initiallyLoaded = true;

  try { TabAutomaton.setLinks({ syncUrl: true }); }
  catch (e) { console.error("Automaton Builder failed to initialise", e); }
});
