const TOKEN_KEY = 'pce_token';

function token() {
  return sessionStorage.getItem(TOKEN_KEY);
}

function authHeaders() {
  return {
    'Content-Type': 'application/json',
    ...(token() ? { Authorization: `Bearer ${token()}` } : {}),
  };
}

async function apiFetch(path, options = {}) {
  return fetch(path, { ...options, headers: authHeaders() });
}

// --- Auth ---
export async function login(pin) {
  const res = await fetch('/api/auth/login', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ pin }),
  });
  if (!res.ok) return false;
  const { token: t } = await res.json();
  sessionStorage.setItem(TOKEN_KEY, t);
  return true;
}

export function logout() {
  const t = token();
  sessionStorage.removeItem(TOKEN_KEY);
  if (t) fetch('/api/auth/logout', { method: 'POST', headers: { Authorization: `Bearer ${t}` } }).catch(() => {});
}

export async function changePin(currentPin, newPin) {
  const res = await apiFetch('/api/auth/pin', {
    method: 'PUT',
    body: JSON.stringify({ currentPin, newPin }),
  });
  return res.ok;
}

// --- Estimates ---
export async function getEstimates() {
  const res = await apiFetch('/api/estimates');
  return res.ok ? res.json() : [];
}

export async function saveEstimate(estimate) {
  if (estimate.id) {
    const res = await apiFetch(`/api/estimates/${estimate.id}`, {
      method: 'PUT',
      body: JSON.stringify(estimate),
    });
    return res.json();
  }
  const res = await apiFetch('/api/estimates', {
    method: 'POST',
    body: JSON.stringify(estimate),
  });
  return res.json();
}

export async function deleteEstimate(id) {
  await apiFetch(`/api/estimates/${id}`, { method: 'DELETE' });
}

export async function getEstimateById(id) {
  const res = await apiFetch(`/api/estimates/${id}`);
  return res.ok ? res.json() : null;
}

// --- Clients ---
export async function getClients() {
  const res = await apiFetch('/api/clients');
  return res.ok ? res.json() : [];
}

export async function saveClient(client) {
  if (client.id) {
    const res = await apiFetch(`/api/clients/${client.id}`, {
      method: 'PUT',
      body: JSON.stringify(client),
    });
    return res.json();
  }
  const res = await apiFetch('/api/clients', {
    method: 'POST',
    body: JSON.stringify(client),
  });
  return res.json();
}

export async function deleteClient(id) {
  await apiFetch(`/api/clients/${id}`, { method: 'DELETE' });
}
