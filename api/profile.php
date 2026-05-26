<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

include "config.php"; // $pdo

$uid = $_SESSION['user_id'];

// Pull user row
$stmt = $pdo->prepare("SELECT name, number, email, profile, plan, email_alert, subscription_date FROM users WHERE uid=? LIMIT 1");
$stmt->execute([$uid]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) { echo "User not found"; exit; }

// Set timezone to Africa/Lagos
date_default_timezone_set('Africa/Lagos');

// Current time
$current_time = new DateTime('now');

// Check subscription
if (!empty($user['subscription_date'])) {
    $subscription_time = new DateTime($user['subscription_date']);

    if ($current_time > $subscription_time) {
        // Subscription has expired
        $update_stmt = $pdo->prepare("UPDATE users 
            SET subscription_date = now, plan = 'free', email_alert = 0 
            WHERE uid = :user_id");
        $update_stmt->execute(['user_id' => $user_id]);
        
        // Update local user data
        $user['subscription_date'] = null;
        $user['plan'] = 'free';
        $user['email_alert'] = 0;
    }
}

// Check if user has active subscription
$has_subscription = !empty($user['subscription_date']) && $current_time <= new DateTime($user['subscription_date']);
// Values
$name       = trim($user['name'] ?? "Unknown");
$halfName   = explode(" ", $name)[0];          // "half" = first word
$number     = $user['number'] ?? "";
$email      = $user['email'] ?? "";
$profileImg = $user['profile'] ?: "images/default_avatar.png";
$plan       = $user['plan'] ?? "Free";
// DB permission flag for enabling email alerts
$canEmailAlert = (int)($user['email_alert'] ?? 0) === 1;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <!-- Disable zooming -->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
  <title>My Profile</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="css/profile.css">
  <style>
    /* Visual enhancements for profile image upload */
    .avatar {
      position: relative;
      cursor: pointer;
      display: inline-block;
      transition: all 0.3s ease;
    }
    
    .avatar:hover {
      opacity: 0.8;
      transform: scale(1.05);
    }
    
    .avatar:hover::after {
      content: 'Change';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: rgba(0,0,0,0.7);
      color: white;
      font-size: 12px;
      padding: 3px;
      text-align: center;
      border-bottom-left-radius: 35px;
      border-bottom-right-radius: 35px;
    }
    
    .avatar img {
      border: 2px solid #6e8efb;
      box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    /* Toast notification */
    .toast {
      position: fixed;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0,0,0,0.7);
      color: white;
      padding: 12px 20px;
      border-radius: 8px;
      font-size: 14px;
      z-index: 1000;
      opacity: 0;
      transition: opacity 0.3s ease;
    }
    
    .toast.show {
      opacity: 1;
    }
  </style>
</head>
<body>
<div class="container">
    <!-- Header -->
    <div class="header">
        <div class="header-icon" onclick="history.back()"><i class="fas fa-arrow-left"></i></div>
        <div class="header-title">My Profile</div>
        
    </div>

    <!-- Content -->
    <div class="content">
        <!-- Profile Card -->
        <div class="profile-card">
            <div class="avatar" id="avatar" title="Change profile photo">
                <img src="<?= htmlspecialchars($profileImg) ?>" alt="profile" style="width:70px;height:70px;border-radius:50%;object-fit:cover;">
            </div>
            <div class="username" id="usernameText"><?= htmlspecialchars($halfName) ?></div>

            <div class="detail-row" onclick="handleAccountNumberClick()">
                <div class="detail-label">OPay Account Number</div>
                <div class="detail-value" id="acctNumber"><?= htmlspecialchars($number) ?></div>
                <div class="detail-icon"><i class="fas fa-chevron-right"></i></div>
            </div>

            <div class="detail-row" onclick="handleTierClick()">
                <div class="detail-label">Account Tier</div>
                <img id="tierImg" class="tier-badge" src="images/toban/tier3.png" alt="Tier">
                <div class="upgrade-button">
                    <div class="upgrade-text">Upgrade</div>
                    <div class="upgrade-icon"><i class="fas fa-chevron-right"></i></div>
                </div>
            </div>
        </div>

        <!-- Account Name Change Section -->
        <div class="account-name-section">
            <div class="account-name-header">Account Name</div>
            <div class="account-name-content">
                <input type="text" class="account-name-input" id="accountNameInput" placeholder="Enter new account name" value="<?= htmlspecialchars($name) ?>">
                <button class="account-name-button" onclick="handleAccountNameChange()">Update Account Name</button>
            </div>
        </div>

        <!-- Info Card -->
        <div class="info-card">
            <div class="detail-row static">
                <div class="detail-label">Mobile Number</div>
                <div class="detail-value">+234<?= htmlspecialchars($number) ?></div>
            </div>

            <div class="detail-row static">
                <div class="detail-label">Email</div>
                <div class="detail-value"><?= htmlspecialchars($email) ?></div>
                <div class="detail-icon"><i class="fas fa-chevron-right"></i></div>
            </div>

            <div class="detail-row static">
                <div class="detail-label">Email Alert</div>
                <label class="switch">
                    <input type="checkbox" id="emailAlertToggle">
                    <span class="slider"></span>
                </label>
            </div>

            <div class="detail-row static">
                <div class="detail-label">Activation Plan</div>
                <div class="detail-value"><?= htmlspecialchars($plan) ?></div>
            </div>
        </div>

        <!-- Deposit Card -->
        <div class="deposit-card" onclick="handleDepositClick()">
            <div class="deposit-title">DEPOSIT TO OWEALTH</div>
            <div class="deposit-description">
                This feature enhances the transaction history to look more realistic only for premium users
            </div>
        </div>
    </div>
</div>

<!-- Subscription Dialog -->
<div class="subscription-dialog" id="subscriptionDialog">
    <div class="dialog-content">
        <div class="dialog-icon">🔒</div>
        <div class="dialog-title">Access Denied</div>
        <div class="dialog-message">You don't have an active subscription to access this feature. Kindly upgrade your account to continue.</div>
        <div class="dialog-buttons">
            <button class="dialog-button button-dismiss" onclick="dismissDialog()">Dismiss</button>
            <button class="dialog-button button-upgrade" onclick="upgradeAccount()">Upgrade Account</button>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="toast" id="toast"></div>

<!-- Hidden file picker -->
<input type="file" id="filePicker" accept="image/*" style="display:none">

<!-- Number Modal -->
<div class="modal-backdrop" id="modal-number">
  <div class="modal">
    <div class="modal-header">Update Account Number</div>
    <div class="modal-body">
      <input class="input" id="numberInput" type="tel" inputmode="numeric" maxlength="10" placeholder="10-digit account number">
      <div class="hint">Must be 10 digits and unique.</div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-ghost" onclick="closeModal('modal-number')">Cancel</button>
      <button class="btn btn-primary" onclick="saveNumber()">Update</button>
    </div>
  </div>
</div>

<!-- Tier Modal -->
<div class="modal-backdrop" id="modal-tier">
  <div class="modal">
    <div class="modal-header">Choose Account Tier</div>
    <div class="modal-body">
      <div class="tier-grid">
        <div class="tier-card" onclick="setTier('tier1')">
          <img src="images/toban/tier1.png" alt="Tier 1"><div>Tier 1</div>
        </div>
        <div class="tier-card" onclick="setTier('tier2')">
          <img src="images/toban/tier2.png" alt="Tier 2"><div>Tier 2</div>
        </div>
        <div class="tier-card" onclick="setTier('tier3')">
          <img src="images/toban/tier3.png" alt="Tier 3"><div>Tier 3</div>
        </div>
      </div>
      <div class="hint" style="margin-top:10px">Your selection saves locally as preference.</div>
    </div>
    <div class="modal-actions">
      <button class="btn btn-primary" onclick="closeModal('modal-tier')">Done</button>
    </div>
  </div>
</div>

<script>
  const SERVER_CAN_EMAIL_ALERT = <?= $canEmailAlert ? 'true' : 'false' ?>;
  const HAS_SUBSCRIPTION = <?= $has_subscription ? 'true' : 'false' ?>;

  // ---------- Toast Notification ----------
  function showToast(message, duration = 3000) {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.classList.add('show');
    
    setTimeout(() => {
      toast.classList.remove('show');
    }, duration);
  }

  // ---------- Subscription Dialog Functions ----------
  function showSubscriptionDialog() {
      document.getElementById("subscriptionDialog").classList.add("active");
  }

  function dismissDialog() {
      document.getElementById("subscriptionDialog").classList.remove("active");
  }

  function upgradeAccount() {
      window.location.href = "plan.php";
  }

  // ---------- Handle Restricted Actions ----------
  function handleAccountNumberClick() {
      if (HAS_SUBSCRIPTION) {
          openNumberModal();
      } else {
          showSubscriptionDialog();
      }
  }

  function handleTierClick() {
      if (HAS_SUBSCRIPTION) {
          openTierModal();
      } else {
          showSubscriptionDialog();
      }
  }

  function handleDepositClick() {
      if (HAS_SUBSCRIPTION) {
          depositOwealth();
      } else {
          showSubscriptionDialog();
      }
  }

  function handleAccountNameChange() {
      if (HAS_SUBSCRIPTION) {
          updateAccountName();
      } else {
          showSubscriptionDialog();
      }
  }

  // ---------- Account Name Change ----------
  function updateAccountName() {
      const newName = document.getElementById('accountNameInput').value.trim();
      
      if (newName.length < 2) {
          showToast('Account name must be at least 2 characters long');
          return;
      }
      
      fetch('update_user.php', {
          method: 'POST',
          headers: {'Content-Type': 'application/json'},
          body: JSON.stringify({ field: 'name', value: newName })
      })
      .then(response => response.json())
      .then(result => {
          if (result.ok) {
              showToast('Account name updated successfully');
              // Update the displayed name
              document.getElementById('usernameText').textContent = newName.split(' ')[0];
          } else {
              showToast(result.error || 'Failed to update account name');
          }
      })
      .catch(error => {
          showToast('Network error: ' + error.message);
      });
  }

  // ---------- Helpers ----------
  function openModal(id){ document.getElementById(id).style.display='flex'; }
  function closeModal(id){ document.getElementById(id).style.display='none'; }
  function openNumberModal(){
    const v = document.getElementById('acctNumber').textContent.trim();
    document.getElementById('numberInput').value = v;
    openModal('modal-number');
  }
  function openTierModal(){ openModal('modal-tier'); }

  function setTier(tier){
    localStorage.setItem('tier', tier);
    const map = {
      'tier1':'images/toban/tier1.png',
      'tier2':'images/toban/tier2.png',
      'tier3':'images/toban/tier3.png'
    };
    document.getElementById('tierImg').src = map[tier] || map['tier3'];
    showToast('Tier updated to ' + tier);
  }

  // ---------- Init (tier + email alert) ----------
  document.addEventListener('DOMContentLoaded', ()=>{
    // Tier image from shared preference (default tier3)
    const savedTier = localStorage.getItem('tier') || 'tier3';
    setTier(savedTier);

    // Email Alert toggle follows shared preference first
    const savedEA = localStorage.getItem('email_alert');
    const toggle = document.getElementById('emailAlertToggle');
    toggle.checked = (savedEA === 'true');

    // Avatar upload
    document.getElementById('avatar').addEventListener('click', function() {
      document.getElementById('filePicker').click();
    });
    
    // Show file name when selected
    document.getElementById('filePicker').addEventListener('change', function() {
      if (this.files.length > 0) {
        handleProfileUpload();
      }
    });
  });

  // ---------- Upload Profile (max 5MB) ----------
  function handleProfileUpload() {
    const fileInput = document.getElementById('filePicker');
    const file = fileInput.files[0];
    
    if(!file) return;
    
    if(file.size > 5*1024*1024){ 
      showToast('Image must be smaller than 5MB'); 
      return; 
    }
    
    // Show loading state
    const avatarImg = document.querySelector('#avatar img');
    const originalSrc = avatarImg.src;
    avatarImg.style.opacity = '0.5';

    const formData = new FormData();
    formData.append('profile', file);

    fetch('upload_profile.php', { method:'POST', body:formData })
      .then(r=>r.json())
      .then(res=>{
        if(res.ok){
          avatarImg.src = res.url + '?t=' + new Date().getTime(); // Avoid cache
          showToast('Profile picture updated successfully');
        }else{
          showToast(res.error || 'Upload failed');
          avatarImg.src = originalSrc;
        }
        avatarImg.style.opacity = '1';
      })
      .catch(() => {
        showToast('Network error uploading file');
        avatarImg.src = originalSrc;
        avatarImg.style.opacity = '1';
      });
  }

  // ---------- Save Number ----------
  function saveNumber(){
    const val = document.getElementById('numberInput').value.trim();
    if(!/^\d{10}$/.test(val)){ 
      showToast('Account number must be 10 digits'); 
      return; 
    }
    
    fetch('update_user.php', {
      method:'POST',
      headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ field:'number', value:val })
    })
    .then(r=>r.json())
    .then(res=>{
      if(res.ok){
        document.getElementById('acctNumber').textContent = val;
        closeModal('modal-number');
        showToast('Account number updated successfully');
      }else{
        showToast(res.error || 'Update failed');
      }
    })
    .catch(() => showToast('Network error'));
  }

  // ---------- Email Alert Toggle Logic ----------
  document.getElementById('emailAlertToggle').addEventListener('change', function(){
    const turningOn = this.checked;

    if (turningOn) {
      // Only allow ON if server permission is true
      if (SERVER_CAN_EMAIL_ALERT) {
        localStorage.setItem('email_alert', 'true');
        fetch('update_user.php', {
          method:'POST', headers:{'Content-Type':'application/json'},
          body: JSON.stringify({ field:'email_alert', value:1 })
        })
        .then(() => showToast('Email alerts enabled'));
      } else {
        showToast('This feature is not available for you. Contact admin.');
        this.checked = false;
      }
    } else {
      localStorage.setItem('email_alert', 'false');
      fetch('update_user.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ field:'email_alert', value:0 })
      })
      .then(() => showToast('Email alerts disabled'));
    }
  });

  function depositOwealth(){
      if(!confirm("Do you want to deposit to OWealth?")) return;

      fetch("deposit_owealth.php", {
          method: "POST"
      })
      .then(r=>r.json())
      .then(res=>{
          if(res.ok){
              // ✅ redirect after success
              window.location.href = "dashboard.php";
          } else {
              showToast("Error: "+res.error);
          }
      })
      .catch(err=>{
          showToast("Network error: " + err.message);
      });
  }
</script>
<script>
  // Disable right-click
  document.addEventListener("contextmenu", function(e){
    e.preventDefault();
  });

  // Disable common inspect keys
  document.onkeydown = function(e) {
    if (e.keyCode == 123) { // F12
      return false;
    }
    if (e.ctrlKey && e.shiftKey && (e.keyCode == 'I'.charCodeAt(0) || e.keyCode == 'J'.charCodeAt(0))) {
      return false;
    }
    if (e.ctrlKey && (e.keyCode == 'U'.charCodeAt(0))) { // Ctrl+U
      return false;
    }
  }
</script>

</body>
</html>