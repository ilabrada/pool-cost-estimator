// Cost calculation for pool construction estimates.
// All prices are in USD and represent typical US contractor rates.

export const MATERIALS = {
  concrete: { label: 'Concrete (Gunite)', pricePerSqFt: 65 },
  fiberglass: { label: 'Fiberglass', pricePerSqFt: 55 },
  vinyl: { label: 'Vinyl Liner', pricePerSqFt: 35 },
};

export const SHAPES = {
  rectangular: { label: 'Rectangular' },
  oval: { label: 'Oval' },
  freeform: { label: 'Freeform / Custom' },
};

export const ADD_ONS = {
  jacuzzi: { label: 'Spa / Jacuzzi', cost: 9000 },
  lighting: { label: 'LED Lighting (per light)', cost: 350, perUnit: true },
  heating: { label: 'Heating System', cost: 4500 },
  cover: { label: 'Safety Cover', cost: 2000 },
  waterFeature: { label: 'Water Feature / Waterfall', cost: 3500 },
  steps: { label: 'Built-in Steps / Stairs', cost: 1500 },
  deckPerSqFt: { label: 'Deck / Patio (per sq ft)', cost: 25, perUnit: true },
  fencing: { label: 'Perimeter Fencing', cost: 3000 },
  automation: { label: 'Smart Pool Automation', cost: 2500 },
};

/**
 * Compute the surface area (bottom + walls) used for material costing.
 * For rectangular pools: bottom + 2 long sides + 2 short sides.
 */
function poolSurface(length, width, depth) {
  const bottom = length * width;
  const longWalls = 2 * length * depth;
  const shortWalls = 2 * width * depth;
  return bottom + longWalls + shortWalls;
}

/**
 * Calculate the full estimate breakdown.
 * @param {object} formData - values from the estimate form
 * @returns {object} breakdown with lineItems array and totals
 */
export function calculateEstimate(formData) {
  const {
    length = 0,
    width = 0,
    depth = 0,
    material = 'concrete',
    shape = 'rectangular',
    // add-ons
    jacuzzi = false,
    lightingCount = 0,
    heating = false,
    cover = false,
    waterFeature = false,
    steps = false,
    deckArea = 0,
    fencing = false,
    automation = false,
  } = formData;

  const l = parseFloat(length) || 0;
  const w = parseFloat(width) || 0;
  const d = parseFloat(depth) || 0;
  const surfaceArea = poolSurface(l, w, d);
  const mat = MATERIALS[material] || MATERIALS.concrete;

  // Shape factor: freeform costs ~10% more, oval slightly less
  const shapeFactor = shape === 'freeform' ? 1.1 : shape === 'oval' ? 0.95 : 1.0;

  const lineItems = [];

  // Base construction cost
  const baseCost = surfaceArea * mat.pricePerSqFt * shapeFactor;
  lineItems.push({
    label: `${mat.label} Shell (${surfaceArea.toFixed(0)} sq ft × $${mat.pricePerSqFt}/sq ft)`,
    amount: baseCost,
  });

  // Excavation: $12 per cubic yard
  const volume = l * w * d; // cubic feet
  // Excavation: $65 per cubic yard (labor + equipment)
  const excavationCost = (volume / 27) * 65;
  lineItems.push({
    label: `Excavation (${(volume / 27).toFixed(1)} cu yds × $65/cu yd)`,
    amount: excavationCost,
  });

  // Plumbing & filtration: flat + per sq ft
  const plumbingCost = 3500 + surfaceArea * 4;
  lineItems.push({ label: 'Plumbing & Filtration System', amount: plumbingCost });

  // Add-ons
  if (jacuzzi) lineItems.push({ label: ADD_ONS.jacuzzi.label, amount: ADD_ONS.jacuzzi.cost });

  const lights = parseInt(lightingCount) || 0;
  if (lights > 0)
    lineItems.push({
      label: `${ADD_ONS.lighting.label} × ${lights}`,
      amount: ADD_ONS.lighting.cost * lights,
    });

  if (heating) lineItems.push({ label: ADD_ONS.heating.label, amount: ADD_ONS.heating.cost });
  if (cover) lineItems.push({ label: ADD_ONS.cover.label, amount: ADD_ONS.cover.cost });
  if (waterFeature) lineItems.push({ label: ADD_ONS.waterFeature.label, amount: ADD_ONS.waterFeature.cost });
  if (steps) lineItems.push({ label: ADD_ONS.steps.label, amount: ADD_ONS.steps.cost });

  const deckSqFt = parseFloat(deckArea) || 0;
  if (deckSqFt > 0)
    lineItems.push({
      label: `Deck / Patio (${deckSqFt} sq ft × $${ADD_ONS.deckPerSqFt.cost}/sq ft)`,
      amount: ADD_ONS.deckPerSqFt.cost * deckSqFt,
    });

  if (fencing) lineItems.push({ label: ADD_ONS.fencing.label, amount: ADD_ONS.fencing.cost });
  if (automation) lineItems.push({ label: ADD_ONS.automation.label, amount: ADD_ONS.automation.cost });

  const subtotal = lineItems.reduce((s, i) => s + i.amount, 0);
  const contingency = subtotal * 0.05; // 5% contingency
  const total = subtotal + contingency;

  return {
    lineItems,
    subtotal,
    contingency,
    total,
    surfaceArea,
    volume,
    shapeFactor,
    material: mat.label,
  };
}

export function formatCurrency(amount) {
  return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD', maximumFractionDigits: 0 }).format(amount);
}
