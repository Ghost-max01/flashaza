<?php
if (session_status()===PHP_SESSION_NONE) session_start();

// ✅ Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = $_SESSION['user_id'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// ✅ Database + subscription check
require_once 'config.php';
$stmt = $pdo->prepare("SELECT subscription_date FROM users WHERE uid = :uid");
$stmt->execute(['uid' => $uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ✅ Use Africa/Lagos timezone + proper datetime comparison
date_default_timezone_set('Africa/Lagos');
$current_date = new DateTime("now");
$has_subscription = false;

if (!empty($user['subscription_date'])) {
    $subscription_date = new DateTime($user['subscription_date']);
    $has_subscription = $current_date <= $subscription_date;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Add Money - Banking App</title>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap">
  <link rel="stylesheet" href="../css/add-money.css">
</head>
<body>
  <!-- Subscription Dialog -->
  <div class="subscription-dialog" id="subscriptionDialog" style="display: <?= $has_subscription ? 'none' : 'flex'; ?>;">
    <div class="dialog-content">
        <div class="dialog-icon"></div>
        <div class="dialog-title">Access Denied</div>
        <div class="dialog-message">You don't have an active subscription to use this feature. Kindly upgrade your account to continue.</div>
        <div class="dialog-buttons">
            <button class="dialog-button button-dismiss" onclick="dismissDialog()">Dismiss</button>
            <button class="dialog-button button-upgrade" onclick="upgradeAccount()">Upgrade Account</button>
        </div>
    </div>
  </div>

  <div class="container" style="<?= !$has_subscription ? 'filter: blur(5px); pointer-events: none;' : ''; ?>">
    <div class="header">
      <a href="dashboard.php" class="back-btn"></a>
      <div class="title">Add Money</div>
      <span class="continue-btn" id="continueBtn">Continue</span>
    </div>

    <div class="form-container">
      <!-- Amount -->
      <div class="form-section">
        <div class="section-title">Amount</div>
        <div class="input-container">
          <input type="number" inputmode="decimal" placeholder="Input The Amount" id="amount" pattern="[0-9]*">
          <div class="error" id="amountError"></div>
        </div>
      </div>

      <!-- Account Number -->
      <div class="form-section">
        <div class="section-title">Account Number</div>
        <div class="input-container">
          <input type="number" inputmode="numeric" placeholder="Input Sender Account Number" id="accountnumber" maxlength="10" pattern="[0-9]*">
          <div class="error" id="accountError"></div>
        </div>
      </div>

      <!-- Sender Name -->
      <div class="form-section">
        <div class="section-title">Sender Name</div>
        <div class="input-container">
          <input type="text" placeholder="Input Sender Name" id="accountname">
          <div class="error" id="nameError"></div>
        </div>
      </div>

      <!-- Bank -->
      <div class="form-section">
        <div class="section-title">Bank Name</div>
        <div class="input-container">
          <input type="text" id="bankInput" placeholder="Select Bank" readonly>
          <div class="error" id="bankError"></div>
        </div>
      </div>

      <!-- Note -->
      <div class="form-section">
        <div class="section-title">Note</div>
        <div class="input-container">
          <input type="text" placeholder="Input Narration (Optional)" id="narration">
        </div>
      </div>

      <!-- Schedule -->
      <div class="form-section">
        <div class="switch-container">
          <label class="switch">
            <input type="checkbox" id="switch1">
            <span class="slider"></span>
          </label>
          <span>Schedule Transaction</span>
        </div>
  <div class="schedule-options" id="scheduleOptions" style="display:none; margin-top:8px;">
    <label for="datetimeInput">Select Date & Time</label>
    <input type="datetime-local" id="datetimeInput" />
  </div>
      </div>
    </div>
  </div>

  <!-- Bank Select Dialog -->
  <div class="dialog-overlay" id="dialogOverlay">
    <div class="dialog">
      <div class="dialog-header">
        <h3>Select Bank</h3>
        <span class="dialog-close" id="dialogClose">&times;</span>
      </div>
      <input type="text" id="dialogSearch" class="dialog-search" placeholder="Search bank...">
      <div class="bank-list" id="bankList"></div>
    </div>
  </div>

<script>
// ✅ Subscription flag
const HAS_SUBSCRIPTION = <?= $has_subscription ? 'true' : 'false'; ?>;

function dismissDialog() {
    window.location.href = "dashboard.php";
}

function upgradeAccount() {
    window.location.href = "plan.php";
}

let bankData = [];

async function fetchBanks() {
  try {
    // ── Primary: Paystack bank list (may already have some logos from server) ──
    const payRes = await fetch('paystack-banks.php');
    if (!payRes.ok) throw new Error('Paystack HTTP ' + payRes.status);
    const payPayload = await payRes.json();
    const payBanks = Array.isArray(payPayload.data) ? payPayload.data : [];

    // ── Client-side logo enrichment (multi-strategy matching) ──
    let logoByCode = {};   // bank code → logo URL
    let logoBySlug = {};   // slug → logo URL
    let logoByName = {};   // normalized name → logo URL
    let logoByShort = {};  // short name (first 2 words) → logo URL

    // Aggressive normalization: strip common suffixes for better matching
    function normalizeName(name) {
      return String(name || '').toLowerCase()
        .replace(/[^a-z0-9 ]/g, ' ')
        .replace(/\b(plc|limited|ltd|nigeria|ng|lc)\b/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    }

    // Even more aggressive: strip "microfinance bank", "mfb", "bank" for short matching
    function shortName(name) {
      return normalizeName(name)
        .replace(/\b(microfinance bank|microfinance|mfb|bank|digital|financial|services|finance|money|mobile)\b/g, '')
        .replace(/\s+/g, ' ')
        .trim();
    }

    // Local overrides mapping for major banks
    function getLocalLogo(code, slug, name) {
      code = String(code || '').trim();
      slug = String(slug || '').toLowerCase().trim();
      name = String(name || '').toLowerCase().trim();

      if (code === '999992' || code === '100004' || slug.includes('opay') || slug.includes('paycom') || name.includes('opay')) {
        return '../images/toban/opay.png';
      }
      if (code === '044' || slug.includes('access') || name.includes('access bank')) {
        return '../images/toban/access.png';
      }
      if (code === '011' || slug.includes('first-bank') || name.includes('first bank')) {
        return '../images/toban/first.png';
      }
      if (code === '058' || slug.includes('gtb') || slug.includes('guaranty-trust') || name.includes('guaranty trust')) {
        return '../images/toban/gt.png';
      }
      if (code === '033' || slug === 'uba' || slug.includes('united-bank-for-africa') || name.includes('united bank for africa') || name === 'uba') {
        return '../images/toban/uba.png';
      }
      if (code === '057' || slug.includes('zenith') || name.includes('zenith bank')) {
        return '../images/toban/zenith.png';
      }
      return '';
    }

    // Store a logo into all relevant maps
    function indexLogo(entry, logoUrl) {
      const code = String(entry.code || '').trim();
      const slug = String(entry.slug || '').trim();
      const name = entry.name || entry.bank_name || '';
      const nName = normalizeName(name);
      const sName = shortName(name);

      if (code && logoUrl) logoByCode[code] = logoUrl;
      if (slug && logoUrl) logoBySlug[slug] = logoUrl;
      if (nName && logoUrl && !logoByName[nName]) logoByName[nName] = logoUrl;
      if (sName && sName.length > 2 && logoUrl && !logoByShort[sName]) logoByShort[sName] = logoUrl;
    }

    const SM_BASE = 'https://supermx1.github.io/nigerian-banks-api/';

    // Fetch both logo sources in parallel (best-effort, never blocks)
    await Promise.allSettled([
      // Source 1: supermx1 — matched by bank CODE (high coverage)
      fetch(SM_BASE + 'data.json')
        .then(r => r.ok ? r.json() : Promise.reject('not ok'))
        .then(banks => {
          banks.forEach(b => {
            let logo = String(b.logo || '').trim();
            if (!logo) return;
            if (!logo.startsWith('http')) logo = SM_BASE + logo;
            indexLogo(b, logo);
          });
        }),
      // Source 2: NigerianBanks.xyz — matched by NAME (fallback)
      fetch('https://nigerianbanks.xyz/')
        .then(r => r.ok ? r.json() : Promise.reject('not ok'))
        .then(banks => {
          banks.forEach(nb => {
            const logo = nb.logo || nb.url || nb.image || nb.logo_url || nb.icon || '';
            if (logo && String(logo).startsWith('http')) {
              indexLogo(nb, logo);
            }
          });
        })
    ]);

    // ── Map to { name, code, url } — the shape renderBankList expects ──
    bankData = payBanks.map(pb => {
      const name = pb.name || '';
      const code = String(pb.code || '');
      const slug = pb.slug || '';
      const serverLogo = pb.logo || '';
      const nName = normalizeName(name);
      const sName = shortName(name);

      // Priority chain: local override → server → code → slug → full name → short name → speculative URL
      const local = getLocalLogo(code, slug, name);
      const url = local
        || serverLogo
        || logoByCode[code]
        || logoBySlug[slug]
        || logoByName[nName]
        || logoByShort[sName]
        || (slug ? SM_BASE + 'logos/' + slug + '.png' : '');

      return { name, code, url };
    });

    bankData.sort((a, b) => a.name.localeCompare(b.name));
    renderBankList(bankData);

  } catch (err) {
    console.error("Failed to load banks from Paystack, falling back to bks.php:", err);
    // ── Fallback: original bks.php so the page never breaks ──
    try {
      const fallbackRes = await fetch('bks.php', { method: 'POST' });
      bankData = await fallbackRes.json();
      renderBankList(bankData);
    } catch (fallbackErr) {
      console.error("Fallback also failed:", fallbackErr);
    }
  }
}

function renderBankList(banks) {
  const bankList = document.getElementById('bankList');
  bankList.innerHTML = "";
  const timeoutMs = 3000;
  let anyAdded = 0;

  function normalizeUrl(url) {
    if (!url) return '';
    url = String(url).trim();
    if (url.startsWith('http') || url.startsWith('/')) return url;
    // convert relative like ../images/toban/foo.png -> /images/toban/foo.png
    return url.replace(/^(?:\.\.\/)+/, '/');
  }

  banks.forEach(bank => {
    const raw = bank.url || '';
    const logoUrl = normalizeUrl(raw);
    if (!logoUrl) return; // skip banks with no logo

    // Preload image and only add bank if image loads successfully
    const img = new Image();
    let settled = false;
    const timer = setTimeout(() => {
      if (settled) return;
      settled = true; // treat as failed after timeout
      img.onload = img.onerror = null;
    }, timeoutMs);

    img.onload = () => {
      if (settled) return;
      settled = true; clearTimeout(timer);
      const item = document.createElement('div');
      item.className = 'bank-item';
      item.innerHTML = `\n        <img src="${logoUrl}" alt="logo" class="bank-logo">\n        <span class="bank-name">${bank.name}</span>\n      `;
      item.addEventListener('click', () => {
        document.getElementById('bankInput').value = bank.name;
        document.getElementById('dialogOverlay').style.display = 'none';
        document.getElementById('bankInput').setAttribute('data-code', bank.code);
        document.getElementById('bankInput').setAttribute('data-url', logoUrl);
      });
      bankList.appendChild(item);
      anyAdded++;
    };

    img.onerror = () => {
      if (settled) return;
      settled = true; clearTimeout(timer);
      img.onload = img.onerror = null;
    };

    // Start loading
    try { img.src = logoUrl; } catch (e) { clearTimeout(timer); }
  });

  // If after timeout nothing added, show empty state
  setTimeout(() => {
    if (anyAdded === 0) {
      bankList.innerHTML = '<div class="empty-state"><p>No banks available</p></div>';
    }
  }, timeoutMs + 50);
}

// ✅ Dialog open/close
document.getElementById('bankInput').addEventListener('click', () => {
  if (HAS_SUBSCRIPTION) {
    document.getElementById('dialogOverlay').style.display = 'flex';
  }
});
document.getElementById('dialogClose').addEventListener('click', () => {
  document.getElementById('dialogOverlay').style.display = 'none';
});

// ✅ Search filter
document.getElementById('dialogSearch').addEventListener('input', function () {
  const search = this.value.toLowerCase();
  const filtered = bankData.filter(bank => bank.name.toLowerCase().includes(search));
  renderBankList(filtered);
});

// ✅ Account number restriction
const accountInput = document.getElementById('accountnumber');
accountInput.addEventListener('input', function () {
  if (this.value.length > 10) {
    this.value = this.value.slice(0, 10);
  }
});

// ✅ Schedule toggle
const switch1 = document.getElementById('switch1');
const scheduleOptions = document.getElementById('scheduleOptions');
scheduleOptions.style.display = 'none';
switch1.addEventListener('change', function () {
  scheduleOptions.style.display = this.checked ? 'block' : 'none';
});

// ✅ Validation + send PHP
document.getElementById("continueBtn").addEventListener("click", async function () {
  if (!HAS_SUBSCRIPTION) {
    document.getElementById("subscriptionDialog").style.display = "flex";
    return;
  }

  let valid = true;
  const amount = document.getElementById("amount").value.trim();
  const account = document.getElementById("accountnumber").value.trim();
  const name = document.getElementById("accountname").value.trim();
  const bank = document.getElementById("bankInput").value;
  const url = document.getElementById("bankInput").getAttribute("data-url") || "";
  const narration = document.getElementById("narration").value.trim();
  const scheduleOn = switch1.checked;
  const scheduleTime = document.getElementById("datetimeInput").value;

  // reset errors
  document.getElementById("amountError").textContent = "";
  document.getElementById("accountError").textContent = "";
  document.getElementById("nameError").textContent = "";
  document.getElementById("bankError").textContent = "";

  // validations
  if (!amount) { document.getElementById("amountError").textContent = "Amount is required"; valid = false; }
  if (account.length < 10) { document.getElementById("accountError").textContent = "Account number must be 10 digits"; valid = false; }
  if (!name) { document.getElementById("nameError").textContent = "Sender name is required"; valid = false; }
  if (!bank) { document.getElementById("bankError").textContent = "Please select a bank"; valid = false; }

  if (!valid) return;

  try {
    console.log("⏳ Sending to process.php…", { amount, account, name, bank, scheduleOn, scheduleTime });

    const payload = { amount, accountnumber: account, accountname: name, bankname: bank, narration, url, scheduleOn, scheduleTime };
    const response = await fetch("process.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });

    const result = await response.json();
    console.log("✅ Response from backend:", result);

    if (result.playSound) {
      const audio = new Audio("sound/success.mp3");
      try {
        await audio.play();
        audio.onended = () => {
          if (result.redirect) window.location.href = result.redirect;
        };
      } catch (err) {
        if (result.redirect) window.location.href = result.redirect;
      }
    } else if (result.redirect) {
      window.location.href = result.redirect;
    } else if (result.message) {
      alert(result.message);
    }

  } catch (err) {
    console.error("❌ Error calling process.php:", err);
    alert("Something went wrong. Check console logs.");
  }
});

document.addEventListener('DOMContentLoaded', function() {
  if (HAS_SUBSCRIPTION) fetchBanks();
});
</script>
</body>
</html>