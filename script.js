/* -------------------------------------------------
   script.js – Live $BOTIFY DexScreener Stats ONLY
   ------------------------------------------------- */

document.addEventListener('DOMContentLoaded', () => {
  // Trigger fade-ins
  document.querySelectorAll('.animate-fadeIn').forEach(el => el.style.opacity = '0');
  setTimeout(() => {
    document.querySelectorAll('.animate-fadeIn').forEach(el => {
      el.style.animationPlayState = 'running';
    });
  }, 100);

  /* ---------- Number Formatter (with commas) ---------- */
  const fmt = (n) => {
    if (!n || isNaN(n)) return '0';
    return Number(n).toLocaleString('en-US', { maximumFractionDigits: 2 });
  };

  /* ---------- Live DexScreener Stats (Your BOTIFY CA) ---------- */
  const CONTRACT_ADDRESS = 'BYZ9CcZGKAXmN2uDsKcQMM9UnZacija4vWcns9Th69xb'; // Your official $BOTIFY CA
  const API_URL = `https://api.dexscreener.com/latest/dex/tokens/${CONTRACT_ADDRESS}`;

  async function fetchLiveStats() {
    try {
      const res = await fetch(API_URL);
      if (!res.ok) throw new Error('API error');
      const data = await res.json();
      const pair = data.pairs?.[0]; // Primary pair (e.g., BOTIFY/SOL)
      if (!pair) throw new Error('No pair found');

      // Update with comma-formatted values
      document.getElementById('mcap').textContent = `$${FMT(pair.marketCap)}`;
      document.getElementById('vol').textContent = `$${FMT(pair.volume?.h24)}`;
      const change = (pair.priceChange?.h24 ?? 0).toFixed(2);
      const changeEl = document.getElementById('change');
      changeEl.textContent = `${change}%`;
      changeEl.className = change >= 0 ? 'text-green-400' : 'text-red-400';
      document.getElementById('price').textContent = `$${FMT(pair.priceUsd)}`;
    } catch (err) {
      console.error('DexScreener fetch failed:', err);
      // Fallback placeholders
      document.getElementById('mcap').textContent = '$0';
      document.getElementById('vol').textContent = '$0';
      document.getElementById('change').textContent = '0%';
      document.getElementById('change').className = 'text-red-400';
      document.getElementById('price').textContent = '$0';
    }
  }

  // Load initial data + auto-refresh every 30s
  fetchLiveStats();
  setInterval(fetchLiveStats, 30000);

  /* ---------- Wallet Connect REMOVED ----------
     The "Connect Wallet" button now does NOTHING.
     No Phantom detection, no connection, no status.
  ------------------------------------------------ */
});