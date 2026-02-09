// Simple WebSocket broadcaster for local development
// Usage: npm init -y && npm i express ws
// Run: node websocket-server.js

const express = require('express');
const http = require('http');
const WebSocket = require('ws');

const PORT = process.env.WS_PORT || 6001;
const app = express();
app.use(express.json());

const server = http.createServer(app);
const wss = new WebSocket.Server({ server });

wss.on('connection', (ws) => {
  console.log('WS client connected');
  ws.send(JSON.stringify({ event: 'connected' }));
});

app.post('/notify', (req, res) => {
  const payload = req.body || {};
  const msg = JSON.stringify(payload);
  let count = 0;
  wss.clients.forEach(client => {
    if (client.readyState === WebSocket.OPEN) {
      client.send(msg);
      count++;
    }
  });
  console.log('Broadcast event', payload, 'to', count, 'clients');
  res.json({ ok: true, sentTo: count });
});

server.listen(PORT, () => console.log(`WebSocket server listening on http://192.168.43.151:${PORT}`));

