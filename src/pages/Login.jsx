import { useState } from 'react';
import { useAuth } from '../hooks/useAuth';

export default function Login() {
  const { login } = useAuth();
  const [pin, setPin] = useState('');
  const [error, setError] = useState('');
  const [shaking, setShaking] = useState(false);

  async function handleSubmit(e) {
    e.preventDefault();
    if (!await login(pin)) {
      setError('Incorrect PIN. Please try again.');
      setShaking(true);
      setPin('');
      setTimeout(() => setShaking(false), 500);
    }
  }

  function handleDigit(d) {
    if (pin.length < 6) setPin((p) => p + d);
  }

  function handleBackspace() {
    setPin((p) => p.slice(0, -1));
    setError('');
  }

  return (
    <div className="min-h-screen bg-gradient-to-br from-cyan-600 to-cyan-900 flex items-center justify-center p-4">
      <div className="bg-white rounded-2xl shadow-2xl p-8 w-full max-w-sm">
        {/* Logo / Icon */}
        <div className="flex justify-center mb-4">
          <div className="w-16 h-16 bg-cyan-600 rounded-full flex items-center justify-center">
            <svg className="w-9 h-9 text-white" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                d="M3 10h18M3 14h18M3 18c1.333-1.333 2.667-2 4-2s2.667.667 4 2 2.667 2 4 2 2.667-.667 4-2" />
            </svg>
          </div>
        </div>

        <h1 className="text-2xl font-bold text-center text-gray-800 mb-1">Pool Cost Estimator</h1>
        <p className="text-sm text-center text-gray-500 mb-6">Enter your PIN to continue</p>

        {/* PIN dots */}
        <form onSubmit={handleSubmit}>
          <div className={`flex justify-center gap-3 mb-4 ${shaking ? 'animate-bounce' : ''}`}>
            {[...Array(4)].map((_, i) => (
              <div
                key={i}
                className={`w-4 h-4 rounded-full border-2 transition-colors ${
                  pin.length > i ? 'bg-cyan-600 border-cyan-600' : 'border-gray-300'
                }`}
              />
            ))}
          </div>

          {error && (
            <p className="text-red-500 text-sm text-center mb-3">{error}</p>
          )}

          {/* Numeric keypad */}
          <div className="grid grid-cols-3 gap-3 mb-4">
            {[1, 2, 3, 4, 5, 6, 7, 8, 9].map((d) => (
              <button
                key={d}
                type="button"
                onClick={() => handleDigit(String(d))}
                className="py-4 text-xl font-semibold rounded-xl bg-gray-100 hover:bg-cyan-50 active:bg-cyan-100 transition-colors text-gray-800"
              >
                {d}
              </button>
            ))}
            <div /> {/* spacer */}
            <button
              type="button"
              onClick={() => handleDigit('0')}
              className="py-4 text-xl font-semibold rounded-xl bg-gray-100 hover:bg-cyan-50 active:bg-cyan-100 transition-colors text-gray-800"
            >
              0
            </button>
            <button
              type="button"
              onClick={handleBackspace}
              className="py-4 flex items-center justify-center rounded-xl bg-gray-100 hover:bg-red-50 active:bg-red-100 transition-colors text-gray-600"
            >
              <svg className="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                  d="M12 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2M3 12l6.414 6.414a2 2 0 001.414.586H19a2 2 0 002-2V7a2 2 0 00-2-2h-8.172a2 2 0 00-1.414.586L3 12z" />
              </svg>
            </button>
          </div>

          <button
            type="submit"
            disabled={pin.length < 4}
            className="w-full py-3 bg-cyan-600 hover:bg-cyan-700 disabled:opacity-40 disabled:cursor-not-allowed text-white font-semibold rounded-xl transition-colors"
          >
            Unlock
          </button>
        </form>

        <p className="text-xs text-center text-gray-400 mt-4">Default PIN: 1234</p>
      </div>
    </div>
  );
}
