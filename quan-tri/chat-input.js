// Register before the spreadsheet bundle so its global shortcuts cannot consume chat input.
(() => {
  const panelFor = event => event.target instanceof Element ? event.target.closest('.returns-panel') : null;
  for (const type of ['copy', 'cut', 'paste', 'contextmenu']) {
    window.addEventListener(type, event => {
      if (panelFor(event)) event.stopImmediatePropagation();
    }, true);
  }
  window.addEventListener('keydown', event => {
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
