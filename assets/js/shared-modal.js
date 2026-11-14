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

(function () {
  'use strict';
  var suite = window.RescuePluginSuite = window.RescuePluginSuite || {};
  var owners = new Set(), saved = [], scrollY = 0, fixed = false;
  function write(element, property, value) {
    var entry = saved.find(function (item) { return item.element === element && item.property === property; });
    if (!entry) {
      entry = { element: element, property: property, value: element.style.getPropertyValue(property), priority: element.style.getPropertyPriority(property) };
      saved.push(entry);
    }
    element.style.setProperty(property, value);
    entry.applied = element.style.getPropertyValue(property);
  }
  suite.lockScroll = function (owner, fixedPosition) {
    if (owners.has(owner)) return;
    if (!owners.size) scrollY = window.scrollY || window.pageYOffset || 0;
    owners.add(owner);
    write(document.documentElement, 'overflow', 'hidden');
    write(document.body, 'overflow', 'hidden');
    if (fixedPosition && !fixed) {
      fixed = true;
      write(document.body, 'position', 'fixed');
      write(document.body, 'top', '-' + scrollY + 'px');
      write(document.body, 'left', '0px');
      write(document.body, 'right', '0px');
      write(document.body, 'width', '100%');
    }
  };
  suite.unlockScroll = function (owner) {
    if (!owners.delete(owner) || owners.size) return;
    saved.forEach(function (entry) {
      // A host component may have changed a style while the dialog was open.
      if (entry.element.style.getPropertyValue(entry.property) !== entry.applied || entry.element.style.getPropertyPriority(entry.property)) return;
      if (entry.value) entry.element.style.setProperty(entry.property, entry.value, entry.priority);
      else entry.element.style.removeProperty(entry.property);
    });
    if (fixed) window.scrollTo(0, scrollY);
    saved = []; fixed = false;
  };
})();
