import { useState, useEffect } from 'react';
import { useNavigate, useParams } from 'react-router-dom';
import { getClients, saveEstimate, getEstimateById } from '../utils/storage';
import { calculateEstimate, MATERIALS, SHAPES } from '../utils/calculations';

const defaultForm = {
  projectName: '',
  clientId: '',
  // pool dimensions
  length: '',
  width: '',
  depth: '',
  shape: 'rectangular',
  material: 'concrete',
  // add-ons
  jacuzzi: false,
  lightingCount: 0,
  heating: false,
  cover: false,
  waterFeature: false,
  steps: false,
  deckArea: '',
  fencing: false,
  automation: false,
  // misc
  notes: '',
};

export default function EstimateForm() {
  const { id } = useParams();
  const navigate = useNavigate();
  const isEditing = Boolean(id);

  const [form, setForm] = useState(defaultForm);
  const [clients, setClients] = useState([]);
  const [errors, setErrors] = useState({});

  useEffect(() => {
    getClients().then(setClients);
  }, []);

  useEffect(() => {
    if (!id) return;
    getEstimateById(id).then((existing) => {
      if (!existing) { navigate('/'); return; }
      setForm({
        ...defaultForm,
        ...existing.formData,
        projectName: existing.projectName || '',
        clientId: existing.clientId || '',
        notes: existing.notes || '',
      });
    });
  }, [id, navigate]);

  function set(field, value) {
    setForm((f) => ({ ...f, [field]: value }));
    if (errors[field]) setErrors((e) => ({ ...e, [field]: '' }));
  }

  function validate() {
    const errs = {};
    if (!form.length || parseFloat(form.length) <= 0) errs.length = 'Required';
    if (!form.width || parseFloat(form.width) <= 0) errs.width = 'Required';
    if (!form.depth || parseFloat(form.depth) <= 0) errs.depth = 'Required';
    return errs;
  }

  async function handleSubmit(e) {
    e.preventDefault();
    const errs = validate();
    if (Object.keys(errs).length) {
      setErrors(errs);
      return;
    }

    const { projectName, clientId, notes, ...formData } = form;
    const result = calculateEstimate(formData);

    const estimateData = {
      ...(isEditing ? { id } : {}),
      projectName,
      clientId,
      notes,
      formData,
      result,
    };

    const saved = await saveEstimate(estimateData);
    navigate(`/estimates/${saved.id || id}`);
  }

  const inputCls = (field) =>
    `w-full border rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 ${
      errors[field] ? 'border-red-400' : 'border-gray-200'
    }`;

  const checkboxCls = 'w-5 h-5 rounded border-gray-300 text-cyan-600 focus:ring-cyan-500 cursor-pointer';
  const labelCls = 'text-sm font-medium text-gray-700';

  return (
    <div className="max-w-2xl mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-800">
          {isEditing ? 'Edit Estimate' : 'New Estimate'}
        </h1>
        <p className="text-sm text-gray-500 mt-1">Fill in the pool details to generate a cost estimate</p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-6">
        {/* Project Info */}
        <section className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h2 className="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <span className="w-6 h-6 bg-cyan-100 text-cyan-700 rounded-full flex items-center justify-center text-xs font-bold">1</span>
            Project Information
          </h2>
          <div className="grid gap-4 sm:grid-cols-2">
            <div>
              <label className={labelCls}>Project Name</label>
              <input
                type="text"
                value={form.projectName}
                onChange={(e) => set('projectName', e.target.value)}
                placeholder="e.g., Smith Backyard Pool"
                className={inputCls('projectName') + ' mt-1'}
              />
            </div>
            <div>
              <label className={labelCls}>Client</label>
              <select
                value={form.clientId}
                onChange={(e) => set('clientId', e.target.value)}
                className={inputCls('clientId') + ' mt-1'}
              >
                <option value="">— No client selected —</option>
                {clients.map((c) => (
                  <option key={c.id} value={c.id}>{c.name}</option>
                ))}
              </select>
            </div>
          </div>
        </section>

        {/* Pool Dimensions */}
        <section className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h2 className="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <span className="w-6 h-6 bg-cyan-100 text-cyan-700 rounded-full flex items-center justify-center text-xs font-bold">2</span>
            Pool Dimensions
          </h2>
          <div className="grid gap-4 sm:grid-cols-3">
            {[
              { field: 'length', label: 'Length (ft)' },
              { field: 'width', label: 'Width (ft)' },
              { field: 'depth', label: 'Avg Depth (ft)' },
            ].map(({ field, label }) => (
              <div key={field}>
                <label className={labelCls}>{label}</label>
                <input
                  type="number"
                  min="1"
                  step="0.5"
                  value={form[field]}
                  onChange={(e) => set(field, e.target.value)}
                  placeholder="0"
                  className={inputCls(field) + ' mt-1'}
                />
                {errors[field] && <p className="text-red-500 text-xs mt-0.5">{errors[field]}</p>}
              </div>
            ))}
          </div>

          <div className="grid gap-4 sm:grid-cols-2 mt-4">
            <div>
              <label className={labelCls}>Pool Shape</label>
              <select
                value={form.shape}
                onChange={(e) => set('shape', e.target.value)}
                className={inputCls('shape') + ' mt-1'}
              >
                {Object.entries(SHAPES).map(([key, { label }]) => (
                  <option key={key} value={key}>{label}</option>
                ))}
              </select>
            </div>
            <div>
              <label className={labelCls}>Construction Material</label>
              <select
                value={form.material}
                onChange={(e) => set('material', e.target.value)}
                className={inputCls('material') + ' mt-1'}
              >
                {Object.entries(MATERIALS).map(([key, { label }]) => (
                  <option key={key} value={key}>{label}</option>
                ))}
              </select>
            </div>
          </div>
        </section>

        {/* Add-ons */}
        <section className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h2 className="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <span className="w-6 h-6 bg-cyan-100 text-cyan-700 rounded-full flex items-center justify-center text-xs font-bold">3</span>
            Add-ons & Features
          </h2>

          <div className="grid gap-4 sm:grid-cols-2">
            {[
              { field: 'jacuzzi', label: 'Spa / Jacuzzi', price: '$9,000' },
              { field: 'heating', label: 'Heating System', price: '$4,500' },
              { field: 'cover', label: 'Safety Cover', price: '$2,000' },
              { field: 'waterFeature', label: 'Water Feature / Waterfall', price: '$3,500' },
              { field: 'steps', label: 'Built-in Steps / Stairs', price: '$1,500' },
              { field: 'fencing', label: 'Perimeter Fencing', price: '$3,000' },
              { field: 'automation', label: 'Smart Pool Automation', price: '$2,500' },
            ].map(({ field, label, price }) => (
              <label key={field} className="flex items-center gap-3 p-3 rounded-lg border border-gray-100 hover:bg-gray-50 cursor-pointer">
                <input
                  type="checkbox"
                  checked={form[field]}
                  onChange={(e) => set(field, e.target.checked)}
                  className={checkboxCls}
                />
                <div>
                  <p className="text-sm font-medium text-gray-700">{label}</p>
                  <p className="text-xs text-gray-400">{price}</p>
                </div>
              </label>
            ))}
          </div>

          <div className="grid gap-4 sm:grid-cols-2 mt-4">
            <div>
              <label className={labelCls}>LED Lighting (# of lights, $350 each)</label>
              <input
                type="number"
                min="0"
                step="1"
                value={form.lightingCount}
                onChange={(e) => set('lightingCount', e.target.value)}
                placeholder="0"
                className={inputCls('lightingCount') + ' mt-1'}
              />
            </div>
            <div>
              <label className={labelCls}>Deck / Patio Area (sq ft, $25/sq ft)</label>
              <input
                type="number"
                min="0"
                step="1"
                value={form.deckArea}
                onChange={(e) => set('deckArea', e.target.value)}
                placeholder="0"
                className={inputCls('deckArea') + ' mt-1'}
              />
            </div>
          </div>
        </section>

        {/* Notes */}
        <section className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
          <h2 className="font-semibold text-gray-700 mb-4 flex items-center gap-2">
            <span className="w-6 h-6 bg-cyan-100 text-cyan-700 rounded-full flex items-center justify-center text-xs font-bold">4</span>
            Notes
          </h2>
          <textarea
            value={form.notes}
            onChange={(e) => set('notes', e.target.value)}
            placeholder="Any special requirements, site conditions, or additional notes…"
            rows={4}
            className="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 resize-none"
          />
        </section>

        {/* Actions */}
        <div className="flex gap-3 pb-4">
          <button
            type="button"
            onClick={() => navigate(-1)}
            className="flex-1 py-3 border border-gray-200 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition-colors"
          >
            Cancel
          </button>
          <button
            type="submit"
            className="flex-1 py-3 bg-cyan-600 hover:bg-cyan-700 text-white font-semibold rounded-xl transition-colors shadow"
          >
            {isEditing ? 'Save Changes' : 'Calculate Estimate'}
          </button>
        </div>
      </form>
    </div>
  );
}
