import { readFileSync } from 'node:fs';
import vm from 'node:vm';
import assert from 'node:assert/strict';
const code = readFileSync(new URL('../assets/track.js', import.meta.url), 'utf8');
let calls = [];
const listeners = {};
const doc = { visibilityState: 'hidden', addEventListener: (name, fn) => listeners[name] = fn };
const config = { url: '/ajax', id: 42, nonce: 'test' };
vm.runInNewContext(code, {
  window: { PetitPopular: config }, document: doc, URLSearchParams,
  fetch: async (url, opts) => { calls.push({url, opts}); return {}; },
});
assert.equal(calls.length, 0, 'Hidden tab does not count');
doc.visibilityState = 'visible';
await listeners.visibilitychange();
assert.equal(calls.length, 1, 'Visible page counts');
assert.equal(calls[0].opts.body.get('post_id'), '42');
assert.equal(calls[0].opts.body.get('nonce'), 'test');
assert.equal(calls[0].opts.method, 'POST');
await listeners.visibilitychange();
assert.equal(calls.length, 1, 'Repeated visibility changes do not duplicate the same page load');
console.log('PASS: 6 browser tracking checks');
