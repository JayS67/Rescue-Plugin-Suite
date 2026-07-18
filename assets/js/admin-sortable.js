(function(){
  'use strict';
  function values(list){return Array.prototype.slice.call(list.querySelectorAll('[data-value]')).map(function(li){return li.getAttribute('data-value')||'';}).filter(Boolean);}
  function inputFor(list){var id=list.getAttribute('data-sortable-input');return id?document.getElementById(id):list.parentNode.querySelector('.plugin-sortable-input');}
  function sync(list){var input=inputFor(list);if(!input)return;input.value=values(list).join('\n');var status=list.parentNode.querySelector('[data-sortable-status]');if(status){status.textContent='Unsaved order changes';status.hidden=false;}}
  function afterElement(list,y){return Array.prototype.slice.call(list.querySelectorAll('li:not(.dragging)')).reduce(function(c,el){var r=el.getBoundingClientRect(),o=y-r.top-r.height/2;return o<0&&o>c.offset?{offset:o,element:el}:c;},{offset:-Infinity,element:null}).element;}
  function init(list){if(list.dataset.bound==='1')return;list.dataset.bound='1';var dragged=null;list.addEventListener('dragstart',function(e){var item=e.target.closest('li[data-value]');if(!item)return;dragged=item;item.classList.add('dragging');item.setAttribute('aria-grabbed','true');if(e.dataTransfer)e.dataTransfer.effectAllowed='move';});
    list.addEventListener('dragend',function(){if(dragged){dragged.classList.remove('dragging');dragged.setAttribute('aria-grabbed','false');}dragged=null;sync(list);});
    list.addEventListener('dragover',function(e){if(!dragged)return;e.preventDefault();var after=afterElement(list,e.clientY);if(after)list.insertBefore(dragged,after);else list.appendChild(dragged);sync(list);});
    list.addEventListener('keydown',function(e){var item=e.target.closest('li[data-value]');if(!item||(e.key!=='ArrowUp'&&e.key!=='ArrowDown'))return;e.preventDefault();if(e.key==='ArrowUp'&&item.previousElementSibling)list.insertBefore(item,item.previousElementSibling);if(e.key==='ArrowDown'&&item.nextElementSibling)list.insertBefore(item.nextElementSibling,item);item.focus();sync(list);});sync(list);}
  document.addEventListener('DOMContentLoaded',function(){document.querySelectorAll('.plugin-sortable').forEach(init);});
})();
