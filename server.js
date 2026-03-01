import express from 'express';
import { readFileSync, writeFileSync, existsSync, mkdirSync } from 'fs';
import { join, dirname } from 'path';
import { fileURLToPath } from 'url';
import { randomUUID } from 'crypto';

const __dirname = dirname(fileURLToPath(import.meta.url));
const DATA_DIR = join(__dirname, 'data');

if (!existsSync(DATA_DIR)) mkdirSync(DATA_DIR);

function readJSON(file, defaultValue) {
  const path = join(DATA_DIR, file);
  if (!existsSync(path)) return defaultValue;
  try {
    return JSON.parse(readFileSync(path, 'utf8'));
  } catch {
    return defaultValue;
  }
}

function writeJSON(file, data) {
  writeFileSync(join(DATA_DIR, file), JSON.stringify(data, null, 2));
}

// In-memory token store — cleared on restart (mirrors sessionStorage behaviour)
const validTokens = new Set();

// Simple rate limiter: max `limit` requests per `windowMs` per IP
function makeRateLimiter(windowMs, limit) {
  const counts = new Map();
  return (req, res, next) => {
    const ip = req.ip || req.socket.remoteAddress;
    const now = Date.now();
    const entry = counts.get(ip) || { count: 0, start: now };
    if (now - entry.start > windowMs) { entry.count = 0; entry.start = now; }
    entry.count += 1;
    counts.set(ip, entry);
    if (entry.count > limit) return res.status(429).json({ error: 'Too many requests' });
    next();
  };
}

const loginLimiter = makeRateLimiter(15 * 60 * 1000, 20); // 20 attempts per 15 min
const apiLimiter = makeRateLimiter(60 * 1000, 300);        // 300 req per minute
const staticLimiter = makeRateLimiter(60 * 1000, 600);     // 600 req per minute

const app = express();
app.use(express.json());
app.use(staticLimiter, express.static(join(__dirname, 'dist')));

function requireAuth(req, res, next) {
  const auth = req.headers.authorization;
  if (!auth || !auth.startsWith('Bearer ')) return res.status(401).json({ error: 'Unauthorized' });
  const t = auth.slice(7);
  if (!validTokens.has(t)) return res.status(401).json({ error: 'Unauthorized' });
  next();
}

// --- Auth ---
app.post('/api/auth/login', loginLimiter, (req, res) => {
  const { pin } = req.body;
  const config = readJSON('config.json', { pin: '1234' });
  if (pin !== config.pin) return res.status(401).json({ error: 'Invalid PIN' });
  const t = randomUUID();
  validTokens.add(t);
  res.json({ token: t });
});

app.post('/api/auth/logout', requireAuth, (req, res) => {
  validTokens.delete(req.headers.authorization.slice(7));
  res.json({ ok: true });
});

app.put('/api/auth/pin', requireAuth, (req, res) => {
  const { currentPin, newPin } = req.body;
  const config = readJSON('config.json', { pin: '1234' });
  if (currentPin !== config.pin) return res.status(401).json({ error: 'Invalid current PIN' });
  if (!newPin || newPin.length < 4 || newPin.length > 6 || !/^\d+$/.test(newPin)) {
    return res.status(400).json({ error: 'Invalid new PIN' });
  }
  writeJSON('config.json', { ...config, pin: newPin });
  res.json({ ok: true });
});

// --- Estimates ---
app.get('/api/estimates', apiLimiter, requireAuth, (req, res) => {
  res.json(readJSON('estimates.json', []));
});

app.post('/api/estimates', requireAuth, (req, res) => {
  const estimates = readJSON('estimates.json', []);
  const now = new Date().toISOString();
  const estimate = { ...req.body, id: randomUUID(), createdAt: now, updatedAt: now };
  estimates.push(estimate);
  writeJSON('estimates.json', estimates);
  res.status(201).json(estimate);
});

app.get('/api/estimates/:id', requireAuth, (req, res) => {
  const estimate = readJSON('estimates.json', []).find((e) => e.id === req.params.id);
  if (!estimate) return res.status(404).json({ error: 'Not found' });
  res.json(estimate);
});

app.put('/api/estimates/:id', requireAuth, (req, res) => {
  const estimates = readJSON('estimates.json', []);
  const idx = estimates.findIndex((e) => e.id === req.params.id);
  if (idx === -1) return res.status(404).json({ error: 'Not found' });
  estimates[idx] = { ...req.body, id: req.params.id, createdAt: estimates[idx].createdAt, updatedAt: new Date().toISOString() };
  writeJSON('estimates.json', estimates);
  res.json(estimates[idx]);
});

app.delete('/api/estimates/:id', requireAuth, (req, res) => {
  writeJSON('estimates.json', readJSON('estimates.json', []).filter((e) => e.id !== req.params.id));
  res.json({ ok: true });
});

// --- Clients ---
app.get('/api/clients', requireAuth, (req, res) => {
  res.json(readJSON('clients.json', []));
});

app.post('/api/clients', requireAuth, (req, res) => {
  const clients = readJSON('clients.json', []);
  const client = { ...req.body, id: randomUUID() };
  clients.push(client);
  writeJSON('clients.json', clients);
  res.status(201).json(client);
});

app.get('/api/clients/:id', requireAuth, (req, res) => {
  const client = readJSON('clients.json', []).find((c) => c.id === req.params.id);
  if (!client) return res.status(404).json({ error: 'Not found' });
  res.json(client);
});

app.put('/api/clients/:id', requireAuth, (req, res) => {
  const clients = readJSON('clients.json', []);
  const idx = clients.findIndex((c) => c.id === req.params.id);
  if (idx === -1) return res.status(404).json({ error: 'Not found' });
  const { name, phone, email, address } = req.body;
  clients[idx] = { name, phone, email, address, id: req.params.id };
  writeJSON('clients.json', clients);
  res.json(clients[idx]);
});

app.delete('/api/clients/:id', requireAuth, (req, res) => {
  writeJSON('clients.json', readJSON('clients.json', []).filter((c) => c.id !== req.params.id));
  res.json({ ok: true });
});

// Fallback — let React Router handle all non-API routes
app.use(staticLimiter, (req, res) => {
  res.sendFile(join(__dirname, 'dist', 'index.html'));
});

const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
  console.log(`Pool Cost Estimator running on http://localhost:${PORT}`);
});
