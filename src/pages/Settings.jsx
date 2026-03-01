import { useState } from 'react';
import { changePin } from '../utils/storage';

export default function Settings() {
  const [currentPin, setCurrentPin] = useState('');
  const [newPin, setNewPin] = useState('');
  const [confirmPin, setConfirmPin] = useState('');
  const [message, setMessage] = useState(null); // {type: 'success'|'error', text: string}

  async function handleChangePin(e) {
    e.preventDefault();
    setMessage(null);

    if (newPin.length < 4) {
      setMessage({ type: 'error', text: 'New PIN must be at least 4 digits.' });
      return;
    }
    if (!/^\d+$/.test(newPin)) {
      setMessage({ type: 'error', text: 'PIN must contain only digits.' });
      return;
    }
    if (newPin !== confirmPin) {
      setMessage({ type: 'error', text: 'PINs do not match.' });
      return;
    }

    const ok = await changePin(currentPin, newPin);
    if (!ok) {
      setMessage({ type: 'error', text: 'Current PIN is incorrect.' });
      return;
    }
    setCurrentPin('');
    setNewPin('');
    setConfirmPin('');
    setMessage({ type: 'success', text: 'PIN updated successfully!' });
  }

  const inputCls = 'w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-cyan-400 mt-1';

  return (
    <div className="max-w-md mx-auto">
      <div className="mb-6">
        <h1 className="text-2xl font-bold text-gray-800">Settings</h1>
        <p className="text-sm text-gray-500 mt-1">Manage your app preferences</p>
      </div>

      {/* Change PIN */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5 mb-6">
        <h2 className="font-semibold text-gray-700 mb-4 flex items-center gap-2">
          <svg className="w-5 h-5 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
              d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
          </svg>
          Change PIN
        </h2>

        {message && (
          <div className={`mb-4 p-3 rounded-lg text-sm ${
            message.type === 'success' ? 'bg-green-50 text-green-700' : 'bg-red-50 text-red-700'
          }`}>
            {message.text}
          </div>
        )}

        <form onSubmit={handleChangePin} className="space-y-4">
          <div>
            <label className="text-sm font-medium text-gray-700">Current PIN</label>
            <input
              type="password"
              inputMode="numeric"
              value={currentPin}
              onChange={(e) => setCurrentPin(e.target.value)}
              maxLength={6}
              placeholder="••••"
              className={inputCls}
            />
          </div>
          <div>
            <label className="text-sm font-medium text-gray-700">New PIN</label>
            <input
              type="password"
              inputMode="numeric"
              value={newPin}
              onChange={(e) => setNewPin(e.target.value)}
              maxLength={6}
              placeholder="••••"
              className={inputCls}
            />
          </div>
          <div>
            <label className="text-sm font-medium text-gray-700">Confirm New PIN</label>
            <input
              type="password"
              inputMode="numeric"
              value={confirmPin}
              onChange={(e) => setConfirmPin(e.target.value)}
              maxLength={6}
              placeholder="••••"
              className={inputCls}
            />
          </div>
          <button
            type="submit"
            className="w-full py-2.5 bg-cyan-600 hover:bg-cyan-700 text-white font-semibold rounded-lg transition-colors"
          >
            Update PIN
          </button>
        </form>
      </div>

      {/* About */}
      <div className="bg-white rounded-xl shadow-sm border border-gray-100 p-5">
        <h2 className="font-semibold text-gray-700 mb-3 flex items-center gap-2">
          <svg className="w-5 h-5 text-cyan-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
              d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
          </svg>
          About
        </h2>
        <p className="text-sm text-gray-600">Pool Cost Estimator v1.0</p>
        <p className="text-xs text-gray-400 mt-1">
          A professional tool for estimating pool construction costs.
          Data is stored on the server and accessible from any device.
        </p>
      </div>
    </div>
  );
}
