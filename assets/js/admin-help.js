(function(){
  document.addEventListener('click', function(e){
    var btn = e.target.closest('[data-asm-help-next]');
    if (!btn) return;
    var target = document.querySelector(btn.getAttribute('data-asm-help-next'));
    if (target) { target.scrollIntoView({behavior:'smooth', block:'start'}); target.style.outline='3px solid #401268'; setTimeout(function(){ target.style.outline=''; }, 1600); }
  });
})();
