(() => {
  'use strict';
  const config = window.PetitPopular;
  if (!config || document.visibilityState === 'prerender') return;
  let sent = false;
  const track = async () => {
    if (sent || document.visibilityState !== 'visible') return;
    sent = true;
    const data = new URLSearchParams({
      action: 'petit_popular_view', post_id: config.id, nonce: config.nonce,
    });
    try {
      await fetch(config.url, {
        method: 'POST', credentials: 'same-origin', body: data, keepalive: true,
      });
    } catch (_) { /* Analytics must never interrupt reading. */ }
  };
  document.addEventListener('visibilitychange', track);
  track();
})();
