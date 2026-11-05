(() => {
  'use strict';
  const brand = document.getElementById('pp-site-brand');
  const accent = document.getElementById('pp-site-accent');
  if (brand && accent) {
    const apply = () => {
      const colors = { sabadell: '#2e9c87', terrassa: '#fc634f' };
      if (colors[brand.value]) accent.value = colors[brand.value];
      accent.disabled = brand.value !== 'custom';
    };
    brand.addEventListener('change', apply);
    apply();
  }
  const root = document.getElementById('pp-builder');
  if (!root) return;
  const fields = [...root.querySelectorAll('[data-pp]')];
  const code = document.getElementById('pp-code');
  const frame = document.getElementById('pp-preview-frame');
  const status = document.getElementById('pp-preview-status');
  const config = window.PetitPopularBuilder;
  const layout = root.querySelector('[data-pp="layout"]');
  const tiles = document.createElement('div');
  tiles.className = 'pp-layout-choices';
  tiles.setAttribute('role', 'group');
  tiles.setAttribute('aria-label', 'Tria el disseny');
  for (const option of layout.options) {
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'pp-layout-choice';
    button.dataset.layout = option.value;
    const icon = document.createElement('span');
    icon.className = `pp-layout-icon pp-icon-${option.value}`;
    icon.setAttribute('aria-hidden', 'true');
    for (let i = 0; i < 3; i++) icon.appendChild(document.createElement('i'));
    button.append(icon, document.createTextNode(option.text));
    button.addEventListener('click', () => { layout.value = option.value; update(); });
    tiles.appendChild(button);
  }
  layout.closest('p').appendChild(tiles);
  layout.classList.add('screen-reader-text');
  let timer;
  let request;
  let generation = 0;
  function activeArgs() {
    return Object.fromEntries(fields.filter(el => !el.closest('p').hidden && el.value !== '')
      .map(el => [el.dataset.pp, el.value.replace(/["\[\]<>]/g, '')]));
  }
  async function preview() {
    if (!config || !frame) return;
    const current = ++generation;
    if (request) request.abort();
    request = new AbortController();
    status.textContent = 'Actualitzant la previsualització…';
    frame.setAttribute('aria-busy', 'true');
    const mode = document.getElementById('pp-preview-mode').value;
    document.getElementById('pp-preview-note').textContent = mode === 'demo'
      ? 'Mostra amb textos, imatges i xifres fictícies. Els filtres temporals s’apliquen al rànquing real.'
      : 'Continguts de la web amb visites dins dels filtres seleccionats. Aquesta vista no afegeix visites.';
    const body = new URLSearchParams({ action: 'petit_popular_preview', nonce: config.nonce, mode });
    for (const [key, value] of Object.entries(activeArgs())) body.set(`args[${key}]`, value);
    try {
      const response = await fetch(config.url, { method: 'POST', credentials: 'same-origin', body, signal: request.signal });
      const result = await response.json();
      if (current !== generation) return;
      if (!response.ok || !result.success) throw new Error(result.data?.message || 'No s’ha pogut carregar la previsualització.');
      const style = document.createElement('link');
      style.rel = 'stylesheet'; style.href = config.styles;
      frame.srcdoc = '<!doctype html><html lang="ca"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        + style.outerHTML + '<style>body{margin:0;padding:16px;font-family:system-ui,sans-serif;color:#20252d}*{box-sizing:border-box}a{pointer-events:none}</style></head><body>'
        + result.data.html + '</body></html>';
      status.textContent = 'Previsualització actualitzada.';
    } catch (error) {
      if (error.name !== 'AbortError' && current === generation) {
        status.textContent = error.message || 'No s’ha pogut carregar la previsualització.';
        frame.removeAttribute('srcdoc');
      }
    } finally {
      if (current === generation) frame.setAttribute('aria-busy', 'false');
    }
  }
  function update() {
    const values = Object.fromEntries(fields.map(el => [el.dataset.pp, el.value]));
    for (const el of fields) {
      const k = el.dataset.pp;
      let visible = true;
      if (k === 'image_position') visible = values.layout === 'vertical';
      if (k === 'columns') visible = !['vertical', 'list'].includes(values.layout);
      if (k === 'days') visible = values.period === 'days';
      if (['from', 'to'].includes(k)) visible = values.period === 'custom';
      if (['event_period', 'ongoing'].includes(k)) visible = values.type === 'events';
      if (k === 'event_days') visible = values.type === 'events' && values.event_period === 'next';
      if (['event_from', 'event_to'].includes(k)) visible = values.type === 'events' && values.event_period === 'custom';
      el.closest('p').hidden = !visible;
    }
    tiles.querySelectorAll('button').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.layout === values.layout)));
    code.value = '[petit_popular ' + Object.entries(activeArgs()).map(([key, value]) => `${key}="${value}"`).join(' ') + ']';
    document.getElementById('pp-copy-status').textContent = '';
    frame.style.width = document.getElementById('pp-preview-width').value;
    clearTimeout(timer);
    // Cancel immediately so an older response cannot repaint newer settings.
    generation++;
    if (request) request.abort();
    timer = setTimeout(preview, 350);
  }
  frame.addEventListener('load', () => {
    if (!frame.hasAttribute('srcdoc')) return;
    const resize = () => {
      if (frame.contentDocument?.body) frame.style.height = Math.min(1800, Math.max(280, frame.contentDocument.body.scrollHeight + 32)) + 'px';
    };
    resize();
    frame.contentDocument?.querySelectorAll('img').forEach(img => img.addEventListener('load', resize));
  });
  document.getElementById('pp-copy').addEventListener('click', async () => {
    const message = document.getElementById('pp-copy-status');
    try { await navigator.clipboard.writeText(code.value); message.textContent = 'Codi copiat!'; }
    catch (_) { code.focus(); code.select(); message.textContent = 'Prem Ctrl+C o Cmd+C per copiar-lo.'; }
  });
  root.addEventListener('input', update);
  root.addEventListener('change', update);
  update();
})();
