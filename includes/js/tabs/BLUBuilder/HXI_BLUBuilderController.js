/*
 * Entry point for the standalone Special:BLUBuilder page.
 * Inside Equipsets this file is not needed - HXI_Equipsets_TabsController.js calls
 * TabBLUBuilder.setLinks({ syncUrl: false, inputs: "external" }) like it does for the other tabs.
 */
var TabBLUBuilder = require("./HXI_TabBLUBuilder.js");

var initiallyLoaded = false;
mw.hook('wikipage.content').add( function () {
  if ( initiallyLoaded == true ) return;
  initiallyLoaded = true;

  try { TabBLUBuilder.setLinks({ syncUrl: true }); }
  catch (e) { console.error("BLU Builder failed to initialise", e); }
});
