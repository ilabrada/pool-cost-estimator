import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { getEstimateById, getClients } from '../utils/storage';
import { formatCurrency } from '../utils/calculations';
import { generatePDF } from '../utils/pdf';

export default function EstimateDetail() {
  const { id } = useParams();
  const navigate = useNavigate();
  const [estimate, setEstimate] = useState(null);
  const [clientName, setClientName] = useState('');

  useEffect(() => {
    getEstimateById(id).then((est) => {
      if (!est) { navigate('/'); return; }
      setEstimate(est);
      if (est.clientId) {
        getClients().then((clients) => {
          const client = clients.find((c) => c.id === est.clientId);
          setClientName(client?.name || '');
        });
      }
    });
  }, [id, navigate]);

  if (!estimate) return null;

  const { result, formData, projectName, notes } = estimate;
  const fd = formData || {};

  function handlePrint() {
    window.print();
  }

  function handlePDF() {
    generatePDF(estimate, clientName);
  }

  return (
    <div className="max-w-2xl mx-auto">
      {/* Header */}
      <div className="flex items-start justify-between mb-6 gap-3">
        <div>
          <h1 className="text-2xl font-bold text-gray-800">{projectName || 'Estimate'}</h1>
          {clientName && <p className="text-cyan-600 font-medium text-sm mt-0.5">{clientName}</p>}
          <p className="text-xs text-gray-400 mt-0.5">
            Created {new Date(estimate.createdAt).toLocaleDateString('en-US', {
              year: 'numeric', month: 'long', day: 'numeric',
            })}
          </p>
        </div>
        <div className="flex gap-2 flex-shrink-0">
          <button
            onClick={handlePDF}
            className="flex items-center gap-1.5 px-3 py-2 bg-cyan-600 hover:bg-cyan-700 text-white text-sm font-medium rounded-lg transition-colors"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
            </svg>
            PDF
          </button>
          <button
            onClick={handlePrint}
            className="flex items-center gap-1.5 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z" />
            </svg>
            Print
          </button>
          <Link
            to={`/estimates/${id}/edit`}
            className="flex items-center gap-1.5 px-3 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium rounded-lg transition-colors"
          >
            <svg className="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
            </svg>
            Edit
          </Link>
        </div>
      </div>

      {/* Pool Specs */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <h2 className="font-semibold text-gray-700 mb-3">Pool Specifications</h2>
        <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
          {[
            { label: 'Dimensions', value: `${fd.length}′ × ${fd.width}′ × ${fd.depth}′ deep` },
            { label: 'Shape', value: fd.shape ? fd.shape.charAt(0).toUpperCase() + fd.shape.slice(1) : '—' },
            { label: 'Material', value: result?.material || '—' },
            { label: 'Surface Area', value: result ? `${result.surfaceArea?.toFixed(0)} sq ft` : '—' },
            { label: 'Volume', value: result ? `${(result.volume * 7.48).toFixed(0)} gallons` : '—' },
          ].map(({ label, value }) => (
            <div key={label} className="bg-gray-50 rounded-lg p-3">
              <p className="text-xs text-gray-500">{label}</p>
              <p className="font-semibold text-gray-800 text-sm mt-0.5">{value}</p>
            </div>
          ))}
        </div>
      </div>

      {/* Cost Breakdown */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
        <h2 className="font-semibold text-gray-700 mb-3">Cost Breakdown</h2>
        <div className="divide-y divide-gray-50">
          {(result?.lineItems || []).map((item, i) => (
            <div key={i} className="flex justify-between items-center py-2.5 text-sm">
              <span className="text-gray-700 pr-4">{item.label}</span>
              <span className="font-semibold text-gray-800 whitespace-nowrap">{formatCurrency(item.amount)}</span>
            </div>
          ))}
        </div>

        {/* Totals */}
        <div className="mt-4 pt-3 border-t border-gray-200 space-y-2">
          <div className="flex justify-between text-sm text-gray-600">
            <span>Subtotal</span>
            <span>{formatCurrency(result?.subtotal || 0)}</span>
          </div>
          <div className="flex justify-between text-sm text-gray-500">
            <span>Contingency (5%)</span>
            <span>{formatCurrency(result?.contingency || 0)}</span>
          </div>
          <div className="flex justify-between items-center pt-3 border-t-2 border-cyan-600">
            <span className="font-bold text-gray-800 text-base">Total Estimate</span>
            <span className="font-bold text-cyan-700 text-2xl">{formatCurrency(result?.total || 0)}</span>
          </div>
        </div>
      </div>

      {/* Notes */}
      {notes && (
        <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-4">
          <h2 className="font-semibold text-gray-700 mb-2">Notes</h2>
          <p className="text-sm text-gray-600 whitespace-pre-wrap">{notes}</p>
        </div>
      )}

      {/* Disclaimer */}
      <p className="text-xs text-gray-400 text-center mt-2 mb-6">
        This estimate is valid for 30 days. Prices may vary based on site conditions and final design.
      </p>

      <div className="flex gap-3 pb-6">
        <Link to="/" className="flex-1 text-center py-3 border border-gray-200 rounded-xl text-gray-700 font-semibold hover:bg-gray-50 transition-colors text-sm">
          Back to Dashboard
        </Link>
      </div>
    </div>
  );
}
