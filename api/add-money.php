<?php
session_start();

// ✅ Check login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$uid = $_SESSION['user_id'];
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// ✅ Restrict to mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}

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
  <link rel="stylesheet" href="css/add-money.css">
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
        <div class="schedule-options" id="scheduleOptions">
          <label for="timeSelect">Select Time (Minutes)</label>
          <select id="timeSelect">
            <option value="1">1 Minute</option>
            <option value="2">2 Minutes</option>
            <option value="3">3 Minutes</option>
            <option value="5">5 Minutes</option>
            <option value="10">10 Minutes</option>
            <option value="30">30 Minutes</option>
            <option value="60">60 Minutes</option>
          </select>
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
    const response = await fetch('bks.php', { method: 'POST' });
    bankData = await response.json();
    renderBankList(bankData);
  } catch (err) {
    console.error("Failed to load banks:", err);
  }
}

function renderBankList(banks) {
  const bankList = document.getElementById('bankList');
  bankList.innerHTML = "";
  banks.forEach(bank => {
    const item = document.createElement('div');
    item.className = 'bank-item';
    item.innerHTML = `
      <img src="${bank.url || ''}" alt="logo" class="bank-logo">
      <span class="bank-name">${bank.name}</span>
    `;
    item.addEventListener('click', () => {
      document.getElementById('bankInput').value = bank.name;
      document.getElementById('dialogOverlay').style.display = 'none';
      document.getElementById('bankInput').setAttribute('data-code', bank.code);
      document.getElementById('bankInput').setAttribute('data-url', bank.url);
    });
    bankList.appendChild(item);
  });
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
  const scheduleTime = document.getElementById("timeSelect").value;

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