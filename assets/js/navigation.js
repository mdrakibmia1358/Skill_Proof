// Shared disclosure menu for public, account and dashboard navigation.
(() => {
  const button = document.getElementById('menuButton');
  const menu = document.getElementById('mobileMenu');
  if (!button || !menu) return;

  const breakpoint = Number(button.dataset.menuBreakpoint) || 768;
  const desktop = window.matchMedia(`(min-width: ${breakpoint}px)`);
  const setOpen = (open, restoreFocus = false) => {
    menu.classList.toggle('hidden', !open);
    button.setAttribute('aria-expanded', String(open));
    if (restoreFocus) button.focus();
  };

  setOpen(false);
  button.addEventListener('click', () => {
    setOpen(button.getAttribute('aria-expanded') !== 'true');
  });
  menu.addEventListener('click', (event) => {
    if (event.target.closest('a')) setOpen(false);
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
      setOpen(false, true);
    }
  });
  desktop.addEventListener('change', () => setOpen(false));
})();
