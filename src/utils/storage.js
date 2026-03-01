const KEYS = {
  PIN: 'pce_pin',
  ESTIMATES: 'pce_estimates',
  CLIENTS: 'pce_clients',
};

const DEFAULT_PIN = '1234';

// --- PIN ---
export function getPin() {
  return localStorage.getItem(KEYS.PIN) || DEFAULT_PIN;
}

export function setPin(pin) {
  localStorage.setItem(KEYS.PIN, pin);
}

export function checkPin(pin) {
  return pin === getPin();
}

// --- Estimates ---
export function getEstimates() {
  try {
    return JSON.parse(localStorage.getItem(KEYS.ESTIMATES)) || [];
  } catch {
    return [];
  }
}

export function saveEstimate(estimate) {
  const estimates = getEstimates();
  if (estimate.id) {
    const idx = estimates.findIndex((e) => e.id === estimate.id);
    if (idx >= 0) {
      estimates[idx] = { ...estimate, updatedAt: new Date().toISOString() };
    } else {
      estimates.push({ ...estimate, updatedAt: new Date().toISOString() });
    }
  } else {
    const newEstimate = {
      ...estimate,
      id: crypto.randomUUID(),
      createdAt: new Date().toISOString(),
      updatedAt: new Date().toISOString(),
    };
    estimates.push(newEstimate);
    localStorage.setItem(KEYS.ESTIMATES, JSON.stringify(estimates));
    return newEstimate;
  }
  localStorage.setItem(KEYS.ESTIMATES, JSON.stringify(estimates));
  return estimate;
}

export function deleteEstimate(id) {
  const estimates = getEstimates().filter((e) => e.id !== id);
  localStorage.setItem(KEYS.ESTIMATES, JSON.stringify(estimates));
}

export function getEstimateById(id) {
  return getEstimates().find((e) => e.id === id) || null;
}

// --- Clients ---
export function getClients() {
  try {
    return JSON.parse(localStorage.getItem(KEYS.CLIENTS)) || [];
  } catch {
    return [];
  }
}

export function saveClient(client) {
  const clients = getClients();
  if (client.id) {
    const idx = clients.findIndex((c) => c.id === client.id);
    if (idx >= 0) {
      clients[idx] = client;
    } else {
      clients.push(client);
    }
  } else {
    const newClient = { ...client, id: crypto.randomUUID() };
    clients.push(newClient);
    localStorage.setItem(KEYS.CLIENTS, JSON.stringify(clients));
    return newClient;
  }
  localStorage.setItem(KEYS.CLIENTS, JSON.stringify(clients));
  return client;
}

export function deleteClient(id) {
  const clients = getClients().filter((c) => c.id !== id);
  localStorage.setItem(KEYS.CLIENTS, JSON.stringify(clients));
}
