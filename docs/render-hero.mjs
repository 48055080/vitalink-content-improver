// Render Vitalink hero PNG via Chrome DevTools Protocol
// 用法：node render-hero.mjs

import { writeFile } from 'node:fs/promises';

const TARGET = 'http://localhost:9222';
const SRC = 'D:/projects/freelance/wp-plugins/vitalink-content-improver/docs/mockup-hero.html';
const OUT = 'D:/projects/freelance/wp-plugins/vitalink-content-improver/docs/hero.png';

const tabsRes = await fetch(`${TARGET}/json`);
const tabs = await tabsRes.json();
const pageTab = tabs.find(t => t.type === 'page');
if (!pageTab) { console.error('No page target'); process.exit(1); }

const browser = new WebSocket(pageTab.webSocketDebuggerUrl);
let msgId = 0;
const pending = new Map();
browser.addEventListener('message', e => {
  const m = JSON.parse(e.data);
  if (m.id && pending.has(m.id)) {
    const { r, j } = pending.get(m.id);
    pending.delete(m.id);
    m.error ? j(new Error(m.error.message)) : r(m.result);
  }
});
await new Promise(r => browser.addEventListener('open', r, { once: true }));

const call = (method, params = {}, sessionId) => new Promise((r, j) => {
  const id = ++msgId;
  pending.set(id, { r, j });
  const msg = { id, method, params };
  if (sessionId) msg.sessionId = sessionId;
  browser.send(JSON.stringify(msg));
});

const fileUrl = 'file:///' + SRC.replace(/\\/g, '/');
console.error(`[render] ${SRC}`);
const { targetId } = await call('Target.createTarget', { url: fileUrl });
const { sessionId } = await call('Target.attachToTarget', { targetId, flatten: true });
await call('Page.enable', {}, sessionId);
await new Promise(r => setTimeout(r, 1500));
const { data } = await call('Page.captureScreenshot', { format: 'png', captureBeyondViewport: true }, sessionId);
await writeFile(OUT, Buffer.from(data, 'base64'));
console.log('saved', OUT, Buffer.from(data, 'base64').length, 'bytes');
await call('Target.closeTarget', { targetId });
await new Promise(r => setTimeout(r, 200));
browser.close();