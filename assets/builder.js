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
  let savedId = 0;
  let savedSnapshot = '';
  const nameInput = document.getElementById('pp-ranking-name');
  const libraryStatus = document.getElementById('pp-library-status');
  const saveStatus = document.getElementById('pp-save-status');
  const savedSelect = document.getElementById('pp-saved-select');
  const copyButton = document.getElementById('pp-copy');
  const snapshot = () => JSON.stringify([nameInput.value, ...fields.map(el => el.value)]);
  function fillLibrary(items) {
    savedSelect.replaceChildren(new Option('Tria un rànquing', ''));
    for (const item of items || []) savedSelect.add(new Option(item.name, String(item.id)));
    savedSelect.value = savedId ? String(savedId) : '';
  }
  async function manage(operation, extra = {}) {
    if (!config) throw new Error('Recarrega la pàgina per continuar.');
    const body = new URLSearchParams({ action: 'petit_popular_saved', nonce: config.nonce, operation });
    for (const [key, value] of Object.entries(extra)) body.set(key, value);
    if (operation === 'save') for (const [key, value] of Object.entries(activeArgs())) body.set(`args[${key}]`, value);
    const response = await fetch(config.url, { method: 'POST', credentials: 'same-origin', body });
    const result = await response.json();
    if (!response.ok || !result.success) throw new Error(result.data?.message || 'No s’ha pogut completar l’operació.');
    return result.data;
  }
  function populate(ranking) {
    savedId = Number(ranking.id) || 0;
    nameInput.value = ranking.name || '';
    for (const el of fields) {
      const value = String(ranking.args?.[el.dataset.pp] ?? config?.defaults?.[el.dataset.pp] ?? '');
      if (el.tagName === 'SELECT' && ![...el.options].some(option => option.value === value)) el.add(new Option(`Personalitzat (${value} px)`, value));
      el.value = value;
    }
    const customAccent = root.querySelector('[data-pp="accent"]').value;
    document.getElementById('pp-color-mode').value = customAccent ? 'custom' : 'brand';
    if (customAccent) {
      const expanded = /^#[0-9a-f]{3}$/i.test(customAccent) ? '#' + [...customAccent.slice(1)].map(c => c + c).join('') : customAccent;
      document.getElementById('pp-accent-picker').value = expanded;
      root.querySelector('[data-pp="accent"]').value = document.getElementById('pp-accent-picker').value;
    }
    for (const key of ['title_color', 'date_color']) {
      const el = root.querySelector(`[data-pp="${key}"]`);
      document.getElementById(`pp-${key}-mode`).value = el.value ? 'custom' : 'default';
      if (el.value) {
        const hex = /^#[0-9a-f]{3}$/i.test(el.value) ? '#' + [...el.value.slice(1)].map(c => c + c).join('') : el.value;
        document.getElementById(`pp-${key}-picker`).value = hex;
        el.value = document.getElementById(`pp-${key}-picker`).value;
      }
    }
    savedSnapshot = snapshot();
    savedSelect.value = savedId ? String(savedId) : '';
    update();
  }
  // Prevent edits during a pending save/load from being silently overwritten.
  async function libraryAction(button, action) {
    const controls = [...root.querySelectorAll('input,select,button,textarea'), ...document.querySelectorAll('.pp-library input,.pp-library select,.pp-library button,.pp-library textarea')];
    const previous = controls.map(el => el.disabled);
    controls.forEach(el => { el.disabled = true; });
    let errorMessage = '';
    try { await action(); }
    catch (error) { errorMessage = error.message; libraryStatus.textContent = errorMessage; }
    finally { controls.forEach((el, i) => { el.disabled = previous[i]; }); update(); if (errorMessage) saveStatus.textContent = errorMessage; }
  }
  fillLibrary(config?.saved || []);
  document.getElementById('pp-save').addEventListener('click', event => libraryAction(event.currentTarget, async () => {
    libraryStatus.textContent = '';
    const result = await manage('save', { id: String(savedId), name: nameInput.value });
    populate(result.ranking); fillLibrary(result.saved);
    libraryStatus.textContent = 'Rànquing desat. El codi amb ID reflectirà les edicions que desis.';
  }));
  document.getElementById('pp-load').addEventListener('click', event => libraryAction(event.currentTarget, async () => {
    if (!savedSelect.value) throw new Error('Tria un rànquing de la llista.');
    populate(await manage('load', { id: savedSelect.value }));
    libraryStatus.textContent = 'Rànquing recuperat. Ja el pots editar.';
  }));
  document.getElementById('pp-new').addEventListener('click', () => {
    populate({ id: 0, name: '', args: config?.defaults || {} });
    libraryStatus.textContent = 'Nou rànquing. Escriu un nom i desa’l.';
  });
  document.getElementById('pp-import').addEventListener('click', event => libraryAction(event.currentTarget, async () => {
    populate(await manage('import', { code: document.getElementById('pp-import-code').value }));
    libraryStatus.textContent = savedId ? 'Rànquing recuperat pel seu ID.' : 'Opcions recuperades. Posa-hi un nom i desa’l; després substitueix el codi antic pel nou codi amb ID.';
  }));
  document.getElementById('pp-delete').addEventListener('click', event => {
    if (!savedSelect.value) { libraryStatus.textContent = 'Tria un rànquing de la llista.'; return; }
    if (!window.confirm('Vols eliminar aquest rànquing? Els shortcodes amb aquest ID deixaran de mostrar-lo.')) return;
    libraryAction(event.currentTarget, async () => {
      const id = Number(savedSelect.value);
      const result = await manage('delete', { id: String(id) });
      if (id === savedId) populate({ id: 0, name: '', args: config?.defaults || {} });
      fillLibrary(result.saved); libraryStatus.textContent = 'Rànquing eliminat.';
    });
  });
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
    const customColor = document.getElementById('pp-color-mode').value === 'custom';
    root.querySelector('[data-pp="accent"]').value = customColor ? document.getElementById('pp-accent-picker').value : '';
    for (const key of ['title_color', 'date_color']) {
      root.querySelector(`[data-pp="${key}"]`).value = document.getElementById(`pp-${key}-mode`).value === 'custom' ? document.getElementById(`pp-${key}-picker`).value : '';
    }
    const values = Object.fromEntries(fields.map(el => [el.dataset.pp, el.value]));
    for (const el of fields) {
      const k = el.dataset.pp;
      let visible = true;
      if (k === 'post_months') visible = values.type === 'posts';
      if (k === 'image_position') visible = values.layout === 'vertical' && values.show_image !== 'no';
      if (k === 'accent') visible = customColor;
      if (k === 'columns') visible = !['vertical', 'list', 'plain'].includes(values.layout);
      if (k === 'radius') visible = values.layout !== 'plain';
      if (['title_color', 'date_color'].includes(k)) visible = document.getElementById(`pp-${k}-mode`).value === 'custom';
      if (k === 'days') visible = values.period === 'days';
      if (['from', 'to'].includes(k)) visible = values.period === 'custom';
      if (['event_period', 'ongoing'].includes(k)) visible = values.type === 'events';
      if (k === 'event_days') visible = values.type === 'events' && values.event_period === 'next';
      if (['event_from', 'event_to'].includes(k)) visible = values.type === 'events' && values.event_period === 'custom';
      el.closest('p').hidden = !visible;
    }
    tiles.querySelectorAll('button').forEach(button => button.setAttribute('aria-pressed', String(button.dataset.layout === values.layout)));
    const inlineCode = '[petit_popular ' + Object.entries(activeArgs()).map(([key, value]) => `${key}="${value}"`).join(' ') + ']';
    code.value = savedId ? `[petit_popular id="${savedId}"]` : inlineCode;
    const dirty = savedId && snapshot() !== savedSnapshot;
    copyButton.disabled = Boolean(dirty);
    saveStatus.textContent = dirty ? 'Canvis pendents: desa’ls per actualitzar el rànquing publicat.' : (savedId ? 'Codi estable: pots editar aquest rànquing sense canviar-lo a les pàgines.' : 'Desa el rànquing amb un nom per recuperar-lo més endavant.');
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
