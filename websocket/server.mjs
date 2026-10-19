import http from 'node:http';
import crypto from 'node:crypto';
import { WebSocketServer, WebSocket } from 'ws';
const secret = process.env.WS_SECRET;
if (!secret || secret.length < 32) throw new Error('WS_SECRET must contain at least 32 characters');
const server = http.createServer((req, res) => { res.writeHead(req.url === '/health' ? 200 : 404); res.end(); });
const wss = new WebSocketServer({noServer: true, maxPayload: 4096});
server.on('upgrade', (req, socket, head) => {
  if (req.url !== '/ws' || req.headers.origin !== process.env.PUBLIC_ORIGIN) { socket.destroy(); return; }
  wss.handleUpgrade(req, socket, head, ws => wss.emit('connection', ws));
});
wss.on('connection', ws => {
  const authTimeout = setTimeout(() => ws.close(1008, 'Authenticate first'), 5000);
  let expiry;
  ws.alive = true;
  ws.on('pong', () => { ws.alive = true; });
  ws.on('message', raw => {
    try {
      if (ws.authenticated) return;
      const {type, token} = JSON.parse(raw.toString());
      if (type !== 'auth' || typeof token !== 'string') throw new Error();
      const [payload, signature, extra] = token.split('.');
      const expected = crypto.createHmac('sha256', secret).update(payload).digest('hex');
      if (extra || !signature || signature.length !== expected.length || !crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expected))) throw new Error();
      const claims = JSON.parse(Buffer.from(payload, 'base64').toString());
      if (claims.role !== 'admin' || !Number.isInteger(claims.exp) || claims.exp <= Date.now()/1000 || claims.exp > Date.now()/1000 + 310) throw new Error();
      ws.authenticated = true; clearTimeout(authTimeout);
      expiry = setTimeout(() => ws.close(1008, 'Token expired'), Math.max(1, claims.exp*1000-Date.now()));
      ws.send(JSON.stringify({type:'ready'}));
    } catch (_) { ws.close(1008, 'Unauthorized'); }
  });
  ws.on('error', () => {});
  ws.on('close', () => { clearTimeout(authTimeout); clearTimeout(expiry); });
});
setInterval(() => {
  for (const ws of wss.clients) { if (!ws.alive) { ws.terminate(); continue; } ws.alive = false; ws.ping(); }
}, 30000).unref();
let cursor;
async function poll() {
  try {
    const url = new URL(process.env.EVENTS_URL || 'http://app/index.php?r=events/index');
    if (cursor !== undefined) url.searchParams.set('after', cursor);
    const response = await fetch(url, {headers: {'X-Events-Secret': secret}, signal: AbortSignal.timeout(4000)});
    if (!response.ok) throw new Error(`Events HTTP ${response.status}`);
    const batch = await response.json();
    if (batch.events.length) {
      for (const ws of wss.clients) if (ws.authenticated && ws.readyState === WebSocket.OPEN) {
        if (ws.bufferedAmount > 65536) { ws.terminate(); continue; }
        ws.send(JSON.stringify({type: 'changed'}));
      }
    }
    cursor = batch.cursor;
  } catch (error) { console.error('Event relay:', error.message); }
  setTimeout(poll, 500);
}
// Establish a durable cursor before accepting browser connections.
async function start() {
  try {
    const response = await fetch(process.env.EVENTS_URL || 'http://app/index.php?r=events/index', {headers:{'X-Events-Secret':secret},signal:AbortSignal.timeout(4000)});
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    cursor = (await response.json()).cursor;
    server.listen(8081, '0.0.0.0'); poll();
  } catch (error) { console.error('Waiting for Yii:', error.message); setTimeout(start, 1000); }
}
start();
process.on('SIGTERM', () => process.exit(0));
