(() => {
  const status = document.getElementById('live-status');
  let socket, retry = 1000, refreshAgain = false, refreshing = false, stopped = false;
  async function refresh() {
    if (refreshing) { refreshAgain = true; return; }
    refreshing = true;
    try {
      const response = await fetch('/index.php?r=admin/feed', {cache: 'no-store'});
      if (response.status === 403) { stopped = true; socket?.close(); location.href = '/index.php?r=site/login'; return; }
      if (!response.ok) throw new Error('Feed unavailable');
      document.getElementById('comments').innerHTML = await response.text();
    } catch (_) { status.textContent = 'Unable to refresh. Reconnecting…'; socket?.close(); }
    finally { refreshing = false; if (refreshAgain) { refreshAgain = false; refresh(); } }
  }
  async function connect() {
    if (stopped) return;
    try {
      const response = await fetch('/index.php?r=admin/token', {cache: 'no-store'});
      if (response.status === 403) { location.href = '/index.php?r=site/login'; return; }
      if (!response.ok) throw new Error('Authentication failed');
      const {token} = await response.json();
      socket = new WebSocket(`${location.protocol === 'https:' ? 'wss' : 'ws'}://${location.host}/ws`);
      socket.onopen = () => socket.send(JSON.stringify({type: 'auth', token}));
      socket.onmessage = ({data}) => {
        const message = JSON.parse(data);
        if (message.type === 'ready') { retry = 1000; status.textContent = 'Live updates connected'; refresh(); }
        if (message.type === 'changed') refresh();
      };
      socket.onclose = reconnect;
      socket.onerror = () => socket.close();
    } catch (_) { reconnect(); }
  }
  function reconnect() {
    if (stopped) return;
    status.textContent = 'Disconnected. Reconnecting…';
    setTimeout(connect, retry); retry = Math.min(retry * 2, 15000);
  }
  window.addEventListener('pagehide', () => { stopped = true; socket?.close(); });
  connect();
})();
