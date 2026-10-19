# Yii1 Comment Room

A small commenting system built with **Yii 1.1.32**. Visitors post without registering; an authenticated administrator can edit and delete comments and receives live updates over WebSockets.

## Run with Docker

Requires Docker Engine and Docker Compose v2. No local PHP, Node.js, or database installation is required.

```sh
cp .env.example .env
```

Set `ADMIN_PASSWORD` (12+ characters) and `WS_SECRET` (32+ random characters) in `.env`. Generate a secret with `openssl rand -hex 32`. Keep `.env` private.

```sh
docker compose up --build -d --wait
```

- Public feed: <http://localhost:8080>
- Admin: <http://localhost:8080/index.php?r=site/login>
- Username: `admin` (configurable with `ADMIN_USERNAME`)
- Password: the value you put in `.env`

If changing the host/port, update both `PORT` and `PUBLIC_ORIGIN` in `.env`. WebSocket origin checks require the exact origin used by the browser (including port). Open the admin page in one browser and post a guest comment in another; the admin list updates without reloading. Edits and deletions also update other open admin sessions.

```sh
docker compose logs -f
docker compose down
```

SQLite data survives container restarts in the `comments-data` named volume. `docker compose down -v` permanently removes it.

## Tests

Against the running Docker stack:

```sh
docker compose --profile test run --rm tests
```

The tests use Python's standard library and a real RFC6455 WebSocket connection. They check guest posting, blank/oversized input, escaped HTML, invalid/valid login, protected admin endpoints, CSRF rejection, POST-only deletion/logout, edit/delete, WebSocket authentication, create/edit/delete notifications, and reconnect snapshots. Tests add test comments to the database; use a disposable stack. CI builds the Docker stack and runs the same checks.

## Structure and design

- `app/`: Yii1 MVC application; public document root is `app/public`, so application code and dependencies are outside the web root.
- `websocket/`: Node.js `ws` server, authenticated with short-lived HMAC tokens issued only to logged-in administrators. Tokens are sent in the first WebSocket message rather than in a URL.
- `docker/nginx.conf`: same-origin HTTP/WebSocket reverse proxy. Only the gateway publishes a host port.
- `compose.yaml`: PHP/Apache app, WebSocket relay, Nginx gateway, optional test container.
- `tests/integration.py`: end-to-end HTTP and WebSocket checks.

Every comment mutation and its event are committed in the same SQLite transaction. The relay reads the durable event cursor through a shared-secret endpoint every 500 ms and pushes invalidations through WebSockets. **The admin browser does not poll.** It fetches a new rendered list when a socket event arrives. This design avoids losing a mutation when the relay is temporarily unavailable. On connection/reconnection the browser reloads the snapshot, covering events missed while disconnected. The socket is refreshed after five minutes through the authenticated session. Notifications contain no comment content.

The UI shows the newest 200 comments. Comment text is limited to 2,000 characters and rendered as escaped plain text. Authentication uses environment-configured credentials with a runtime password hash, session regeneration, and a basic per-session login attempt limit. All state-changing browser requests use Yii CSRF protection. Secrets and runtime data are excluded from Git.

## Scope

This is a single-admin test project. SQLite and a small append-only event log keep setup simple. A larger deployment would add pagination, shared/IP-based rate limiting, event retention, monitoring, and an external database/broker. Put a TLS terminator in front of Nginx and configure HTTPS secure cookies before an internet-facing deployment. The supplied setup is a local HTTP demo.

Framework reference: <https://github.com/yiisoft/yii/tree/1.1.32>.
