    document.addEventListener('DOMContentLoaded', function () {
      function normalizeTier(t) {
        if (!t) return 'tier1';
        t = String(t).toLowerCase().trim();
        if (t === '1' || t === 'tier1') return 'tier1';
        if (t === '2' || t === 'tier2') return 'tier2';
        return 'tier3';
      }
      const saved = localStorage.getItem('tier');
      const tier = normalizeTier(saved);
      const badge = document.getElementById('tier-badge-img');
      if (tier === 'tier1') badge.src = 'images/tohome/upgrade-to-tier2.png';
      else if (tier === 'tier2') badge.src = 'images/tohome/upgrade-to-tier1.png';
      else badge.src = 'images/tohome/tier3.png';

      document.getElementById('settings-btn').addEventListener('click', function (e) {
        e.stopPropagation();
        window.location.href = 'setting.php';
      });

      lottie.loadAnimation({
        container: document.getElementById('lottie-coins'),
        renderer: 'svg',
        loop: true,
        autoplay: true,
        path: 'json/security.json'
      });
    });