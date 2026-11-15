(function () {
  'use strict';
  function init(root) {
    if (root.dataset.rpsViewSwitch !== 'true' || root.dataset.rpsViewReady === 'true') return;
    const group = root.querySelector('.rps-view-switch');
    const grid = root.querySelector('#asm-grid, #asm-plugin-grid');
    if (!group || !grid) return;
    root.dataset.rpsViewReady = 'true';
    const buttons = Array.from(group.querySelectorAll('[data-rps-select-view]'));
    const storageKey = 'rps-animal-view:' + location.pathname + ':' + root.id;
    function apply(view, save) {
      if (view !== 'grid' && view !== 'list') return;
      root.dataset.rpsView = view;
      buttons.forEach(button => {
        button.setAttribute('aria-pressed', button.dataset.rpsSelectView === view ? 'true' : 'false');
        button.setAttribute('aria-controls', grid.id);
      });
      // A view change rearranges the current results without changing filters or page.
      if (save) { try { sessionStorage.setItem(storageKey, view); } catch (ignored) {} }
    }
    let saved;
    try { saved = sessionStorage.getItem(storageKey); } catch (ignored) {}
    apply(saved || root.dataset.rpsView || 'grid', false);
    buttons.forEach((button, index) => {
      button.addEventListener('click', () => apply(button.dataset.rpsSelectView, true));
      button.addEventListener('keydown', event => {
        if (!['ArrowLeft','ArrowRight','Home','End'].includes(event.key)) return;
        event.preventDefault();
        const target = event.key === 'Home' ? 0 : event.key === 'End' ? buttons.length - 1 : (index + (event.key === 'ArrowLeft' ? -1 : 1) + buttons.length) % buttons.length;
        buttons[target].focus(); apply(buttons[target].dataset.rpsSelectView, true);
      });
    });
  }
  function start() { document.querySelectorAll('[data-rps-ui][data-rps-view-switch]').forEach(init); }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
})();
