(function(){
  "use strict";
  window.RescuePluginSuite = window.RescuePluginSuite || {};
  window.RescuePluginSuite.trapFocus = function(modal){
    if(!modal) return function(){};
    var selector='a[href],button:not([disabled]),textarea,input,select,[tabindex]:not([tabindex="-1"])';
    function onKey(e){
      if(e.key !== 'Tab') return;
      var nodes=Array.prototype.slice.call(modal.querySelectorAll(selector)).filter(function(el){return el.offsetParent !== null;});
      if(!nodes.length) return;
      var first=nodes[0], last=nodes[nodes.length-1];
      if(e.shiftKey && document.activeElement===first){e.preventDefault();last.focus();}
      else if(!e.shiftKey && document.activeElement===last){e.preventDefault();first.focus();}
    }
    modal.addEventListener('keydown', onKey);
    return function(){ modal.removeEventListener('keydown', onKey); };
  };
})();
