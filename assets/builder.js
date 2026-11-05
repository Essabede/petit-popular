(() => {
  'use strict';
  const brand = document.getElementById('pp-site-brand');
  const accent = document.getElementById('pp-accent');
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
  const update = () => {
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
    const attrs = fields.filter(el => !el.closest('p').hidden && el.value !== '')
      .map(el => `${el.dataset.pp}="${el.value.replace(/["\[\]<>]/g, '')}"`);
    document.getElementById('pp-code').value = `[petit_popular ${attrs.join(' ')}]`;
  };
  root.addEventListener('input', update);
  root.addEventListener('change', update);
  update();
})();
