"""HTTP + actual RFC6455 WebSocket integration checks; Python standard library only."""
import base64
import hashlib
import html
import http.cookiejar
from html.parser import HTMLParser
import json
import os
import re
import socket
import struct
import time
import urllib.error
import urllib.parse
import urllib.request
import uuid

BASE = os.environ.get('BASE_URL', 'http://localhost:8080').rstrip('/')
WS_BASE = os.environ.get('WS_BASE_URL', BASE)
ORIGIN = os.environ.get('WS_ORIGIN', 'http://localhost:8080')

def csrf(page):
    class Parser(HTMLParser):
        token = None
        def handle_starttag(self, tag, attrs):
            attrs = dict(attrs)
            if tag == 'input' and attrs.get('name') == 'YII_CSRF_TOKEN':
                self.token = attrs.get('value')
    parser = Parser(); parser.feed(page)
    assert parser.token, 'Missing CSRF field'
    return parser.token

class Client:
    def __init__(self):
        self.opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
    def request(self, route='', data=None):
        url = BASE + '/index.php' + ('?r=' + route if route else '')
        try:
            with self.opener.open(url, None if data is None else urllib.parse.urlencode(data).encode(), timeout=10) as r:
                return r.status, r.read().decode()
        except urllib.error.HTTPError as e:
            return e.code, e.read().decode()
    def form(self, route, data):
        status, page = self.request(route)
        assert status == 200, (route, status, page[:400])
        return self.request(route, dict(data, YII_CSRF_TOKEN=csrf(page)))

class WS:
    def __init__(self, origin=ORIGIN):
        u = urllib.parse.urlsplit(WS_BASE)
        self.sock = socket.create_connection((u.hostname, u.port or 80), timeout=8)
        self.file = self.sock.makefile('rb')
        key = base64.b64encode(os.urandom(16)).decode()
        self.sock.sendall((f'GET /ws HTTP/1.1\r\nHost: {u.netloc}\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: {key}\r\nSec-WebSocket-Version: 13\r\nOrigin: {origin}\r\n\r\n').encode())
        status = self.file.readline()
        assert b'101' in status, status
        headers = {}
        while True:
            line = self.file.readline()
            if line == b'\r\n': break
            k,v = line.decode().split(':',1); headers[k.lower()] = v.strip()
        expected = base64.b64encode(hashlib.sha1((key+'258EAFA5-E914-47DA-95CA-C5AB0DC85B11').encode()).digest()).decode()
        assert headers['sec-websocket-accept'] == expected
    def send(self, obj):
        data = json.dumps(obj).encode(); mask = os.urandom(4)
        size = len(data)
        head = bytes([0x81, 0x80 | size]) if size < 126 else bytes([0x81, 0xfe])+struct.pack('!H', size)
        self.sock.sendall(head + mask + bytes(b ^ mask[i%4] for i,b in enumerate(data)))
    def receive(self):
        head = self.file.read(2); assert len(head)==2, 'WebSocket disconnected'
        opcode = head[0]&15; size = head[1]&127
        if size == 126: size=struct.unpack('!H',self.file.read(2))[0]
        if size == 127: size=struct.unpack('!Q',self.file.read(8))[0]
        data = self.file.read(size)
        if opcode==8: return {'closed': True}
        assert opcode==1, opcode
        return json.loads(data)
    def changed(self):
        assert self.receive()['type']=='changed', 'Missing live notification'
    def close(self):
        self.file.close(); self.sock.close()

for _ in range(30):
    try:
        if Client().request()[0] == 200: break
    except OSError: pass
    time.sleep(1)
else: raise AssertionError('Application did not become ready')

guest, admin = Client(), Client()
for route in ['admin/index','admin/feed','admin/token','admin/edit&id=1']:
    assert guest.request(route)[0] == 403, route
assert guest.request('events/index')[0] == 403
assert guest.request('site/index', {'body':'without csrf'})[0] == 400
assert guest.form('site/index', {'body':'   '})[0] == 200
assert 'cannot be blank' in guest.form('site/index', {'body':'   '})[1]
assert 'too long' in guest.form('site/index', {'body':'x'*2001})[1]
assert 'Invalid username' in admin.form('site/login', {'username':'admin','password':'wrong'})[1]
status, page = admin.form('site/login', {'username':os.getenv('ADMIN_USERNAME','admin'), 'password':os.environ['ADMIN_PASSWORD']})
assert status==200 and 'Moderate comments' in page, page[:500]
assert admin.request('site/logout')[0] == 405

unauthorized = WS(); unauthorized.send({'type':'auth','token':'forged.signature'})
assert unauthorized.receive().get('closed'); unauthorized.close()
live = WS(); live.send({'type':'auth','token':json.loads(admin.request('admin/token')[1])['token']})
assert live.receive()['type']=='ready'
body = 'test-'+str(uuid.uuid4())+' <script>alert(1)</script> Привет 👋'
status,page = guest.form('site/index', {'body':body})
assert status==200 and html.escape(body, quote=False) in page and '<script>alert(1)</script>' not in page
live.changed()
feed = admin.request('admin/feed')[1]
identifier = re.search(r'data-id="(\d+)"',feed)[1]
assert html.escape(body, quote=False) in feed
assert admin.request('admin/delete&id='+identifier)[0] == 405
assert guest.form('site/index', {'body':'a'*2000})[0] == 200
live.changed()
status,page = admin.form('admin/edit&id='+identifier, {'body':'Updated '+body})
assert status==200 and 'Updated '+html.escape(body, quote=False) in page
live.changed()
# Delete using the CSRF token in the admin index (delete is intentionally POST-only).
token = csrf(page)
status,page = admin.request('admin/delete&id='+identifier, {'YII_CSRF_TOKEN':html.unescape(token)})
assert status==200 and body not in html.unescape(page)
live.changed(); live.close()
# A new connection gets a fresh snapshot after missing an event.
reconnected=WS(); reconnected.send({'type':'auth','token':json.loads(admin.request('admin/token')[1])['token']})
assert reconnected.receive()['type']=='ready'
assert body not in html.unescape(admin.request('admin/feed')[1]); reconnected.close()
status,page=admin.request('site/logout',{'YII_CSRF_TOKEN':html.unescape(token)})
assert status==200 and admin.request('admin/token')[0]==403
print('PASS: guest posting, validation, escaped output, login/logout, authorization, CSRF, edit/delete, real WebSocket notifications, reconnect snapshot.')
