import { useState, useEffect } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { getEstimates, getClients, deleteEstimate } from '../utils/storage';
import { formatCurrency } from '../utils/calculations';

export default function Dashboard() {
  const [estimates, setEstimates] = useState([]);
  const [clients, setClients] = useState([]);
  const [search, setSearch] = useState('');
  const [confirmDelete, setConfirmDelete] = useState(null);
  const navigate = useNavigate();

  useEffect(() => {
    getEstimates().then(setEstimates);
    getClients().then(setClients);
  }, []);

  async function refresh() {
    setEstimates(await getEstimates());
  }

  function clientName(clientId) {
    const c = clients.find((c) => c.id === clientId);
    return c ? c.name : '';
  }

  async function handleDelete(id) {
    await deleteEstimate(id);
    await refresh();
    setConfirmDelete(null);
  }

  const filtered = estimates
    .filter((e) => {
      const q = search.toLowerCase();
      return (
        (e.projectName || '').toLowerCase().includes(q) ||
        clientName(e.clientId).toLowerCase().includes(q)
      );
    })
    .sort((a, b) => new Date(b.updatedAt) - new Date(a.updatedAt));

  return (
    <div>
      <div className="flex items-center justify-between mb-6">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">Estimates</h1>
          <p className="text-sm text-gray-500">{estimates.length} saved estimate{estimates.length !== 1 ? 's' : ''}</p>
        </div>
        <Link
          to="/estimates/new"
          className="flex items-center gap-2 bg-cyan-600 hover:bg-cyan-700 text-white px-4 py-2 rounded-lg font-semibold transition-colors text-sm shadow"
        >
          <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M12 4v16m8-8H4" />
          </svg>
          New Estimate
        </Link>
      </div>

      {estimates.length > 0 && (
        <div className="mb-4">
          <input
            type="text"
            placeholder="Search by project name or client…"
            value={search}
            onChange={(e) => setSearch(e.target.value)}
            className="w-full border border-gray-200 rounded-lg px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400"
          />
        </div>
      )}

      {filtered.length === 0 ? (
        <div className="text-center py-20 text-gray-400">
          <svg className="w-16 h-16 mx-auto mb-4 opacity-30" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={1}
              d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          <p className="text-lg font-medium">No estimates yet</p>
          <p className="text-sm mt-1">Create your first estimate to get started</p>
          <Link
            to="/estimates/new"
            className="inline-block mt-4 bg-cyan-600 text-white px-6 py-2 rounded-lg font-semibold hover:bg-cyan-700 transition-colors"
          >
            Create Estimate
          </Link>
        </div>
      ) : (
        <div className="grid gap-4 sm:grid-cols-2">
          {filtered.map((estimate) => (
            <div
              key={estimate.id}
              className="bg-white rounded-xl shadow-sm border border-gray-100 p-4 hover:shadow-md transition-shadow"
            >
              <div className="flex items-start justify-between mb-2">
                <div>
                  <h3 className="font-semibold text-gray-800 truncate">
                    {estimate.projectName || 'Untitled Estimate'}
                  </h3>
                  {clientName(estimate.clientId) && (
                    <p className="text-xs text-cyan-600 font-medium">{clientName(estimate.clientId)}</p>
                  )}
                </div>
                <span className="text-lg font-bold text-cyan-700 whitespace-nowrap ml-2">
                  {estimate.result ? formatCurrency(estimate.result.total) : '—'}
                </span>
              </div>

              {estimate.formData && (
                <p className="text-xs text-gray-500 mb-3">
                  {estimate.formData.length}′ × {estimate.formData.width}′ × {estimate.formData.depth}′ deep &middot;{' '}
                  {estimate.formData.material
                    ? estimate.formData.material.charAt(0).toUpperCase() + estimate.formData.material.slice(1)
                    : ''}
                </p>
              )}

              <p className="text-xs text-gray-400 mb-3">
                {new Date(estimate.updatedAt).toLocaleDateString('en-US', {
                  year: 'numeric', month: 'short', day: 'numeric',
                })}
              </p>

              <div className="flex gap-2">
                <button
                  onClick={() => navigate(`/estimates/${estimate.id}`)}
                  className="flex-1 text-center text-sm py-1.5 bg-cyan-50 hover:bg-cyan-100 text-cyan-700 rounded-lg font-medium transition-colors"
                >
                  View
                </button>
                <button
                  onClick={() => navigate(`/estimates/${estimate.id}/edit`)}
                  className="flex-1 text-center text-sm py-1.5 bg-gray-50 hover:bg-gray-100 text-gray-700 rounded-lg font-medium transition-colors"
                >
                  Edit
                </button>
                <button
                  onClick={() => setConfirmDelete(estimate.id)}
                  className="text-sm py-1.5 px-3 bg-red-50 hover:bg-red-100 text-red-600 rounded-lg font-medium transition-colors"
                >
                  <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                      d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
            </div>
          ))}
        </div>
      )}

      {/* Delete confirmation modal */}
      {confirmDelete && (
        <div className="fixed inset-0 bg-black/40 flex items-center justify-center z-50 p-4">
          <div className="bg-white rounded-2xl p-6 max-w-sm w-full shadow-xl">
            <h3 className="font-bold text-gray-800 text-lg mb-2">Delete Estimate?</h3>
            <p className="text-gray-500 text-sm mb-6">This action cannot be undone.</p>
            <div className="flex gap-3">
              <button
                onClick={() => setConfirmDelete(null)}
                className="flex-1 py-2 border border-gray-200 rounded-lg text-gray-700 font-medium hover:bg-gray-50 transition-colors"
              >
                Cancel
              </button>
              <button
                onClick={() => handleDelete(confirmDelete)}
                className="flex-1 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg font-medium transition-colors"
              >
                Delete
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
