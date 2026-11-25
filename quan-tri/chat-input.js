// Register before the spreadsheet bundle so its global shortcuts cannot consume chat input.
(() => {
  const panelFor = event => event.target instanceof Element ? event.target.closest('.returns-panel') : null;
  window.addEventListener('copy', event => {
    if (panelFor(event)) return;
    const detail = {event, handled: false};
    window.dispatchEvent(new CustomEvent('victor-copy-data', {detail}));
    if (detail.handled) { event.preventDefault(); event.stopImmediatePropagation(); }
  }, true);
  for (const type of ['copy', 'cut', 'paste', 'contextmenu']) {
    window.addEventListener(type, event => {
      if (panelFor(event)) event.stopImmediatePropagation();
    }, true);
  }
  window.addEventListener('keydown', event => {
    if ((event.ctrlKey || event.metaKey) && !event.altKey && !event.shiftKey && event.key.toLowerCase() === 'c' && !panelFor(event)) {
      const detail = {event, handled: false};
      window.dispatchEvent(new CustomEvent('victor-copy-selection', {detail}));
      if (detail.handled) { event.preventDefault(); event.stopImmediatePropagation(); return; }
    }
    if (['Backspace', 'Delete'].includes(event.key) && !panelFor(event)) {
      const detail = {event, handled: false};
      window.dispatchEvent(new CustomEvent('victor-clear-selection', {detail}));
      if (detail.handled) { event.preventDefault(); event.stopImmediatePropagation(); return; }
    }
    const panel = panelFor(event);
    if (!panel) return;
    // Leave native copy/paste and text selection intact; isolate all spreadsheet shortcuts.
    event.stopImmediatePropagation();
    if (event.key === 'Escape') { panel.querySelector('header button')?.click(); return; }
    const input = event.target;
    if (!(input instanceof HTMLTextAreaElement) || event.key !== 'Enter' || event.isComposing) return;
    event.preventDefault();
    if (input.disabled || input.readOnly) return;
    if (event.ctrlKey || event.metaKey || event.shiftKey) {
      input.setRangeText('\n', input.selectionStart, input.selectionEnd, 'end');
      input.dispatchEvent(new Event('input', {bubbles: true}));
    } else if (!event.repeat && input.value.trim()) {
      const form = input.closest('form');
      const send = form?.querySelector('button[type="submit"]');
      if (send && !send.disabled) form.requestSubmit(send);
    }
  }, true);
})();
