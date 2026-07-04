import jQuery from 'jquery';

// Some jQuery plugins (nestable2) read window.jQuery/$ directly at module
// evaluation time instead of doing their own `require('jquery')`. ES module
// imports are all evaluated before any of the importing module's own body
// code runs, so `window.jQuery = jQuery` living in app.js's body would run
// too late for those plugins' side-effect imports. Isolating the assignment
// in its own module and importing it first guarantees it runs during the
// dependency-evaluation phase, ahead of anything importing it afterwards.
window.jQuery = jQuery;
window.$ = jQuery;

export default jQuery;
