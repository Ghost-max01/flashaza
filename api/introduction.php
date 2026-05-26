<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Opay Transfer</title>
<style>
* {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: 'Roboto', Arial, sans-serif;
    }
    
    body {
      background-color: #FFFFFF;
      color: #000000;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      padding: 8px;
      align-items: center;
    }
    
    /* QR Code */
    .qr-container {
      margin: 20px 0;
      text-align: center;
    }
    
    /* Transfer Info */
    .transfer-info {
      display: flex;
      justify-content: center;
      margin: 15px 0;
    }
    
    .instant-free {
      padding: 8px;
      color: #00B678;
      font-size: 16px;
    }
    
    .transfer-text {
      padding: 8px;
      color: inherit;
      font-size: 16px;
    }
    
    /* Logo Container*/
    .logo {
      height: 250px;
      width: 250px;
      max-width: 100%;
    }
    
    .logo-container {
      margin: 10px 0 20px;
      text-align: center;
    }
    
    /* Description */
    .description {
      text-align: center;
      margin-bottom: 20px;
    }
    
    .description p {
      padding: 8px;
      color: inherit;
      font-size: 14px;
    }
    
    /* Spacer */
    .spacer {
      flex-grow: 1;
    }
    
    /* Buttons */
    .button-container {
      width: 100%;
      max-width: calc(100% - 60px);
      margin: 0 auto;
    }
    
    .create-button {
      height: 40px;
      background-color: #00B678;
      border-radius: 20px;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 8px;
      margin-bottom: 15px;
      cursor: pointer;
    }
    
    .create-button span {
      color: #FFFFFF;
      font-size: 16px;
    }
    
    .login-button {
      height: 40px;
      border: 1px solid #00B678;
      border-radius: 20px;
      display: flex;
      justify-content: center;
      align-items: center;
      padding: 8px;
      cursor: pointer;
    }
    
    .login-button span {
      color: #00B678;
      font-size: 16px;
    }
    
    /* Footer Image */
    .footer-image {
      text-align: center;
      margin-top: 25px;
    }
    
    .footer-image img {
      max-width: 100%;
    }

    /* ===== DARK MODE STYLES ===== */
    @media (prefers-color-scheme: dark) {
      body {
        background-color: #121212;
        color: #FFFFFF;
      }

      .transfer-text {
        color: #FFFFFF;
      }

      .description p {
        color: #DDDDDD;
      }

      .login-button {
        border: 1px solid #00B678;
      }

      .login-button span {
        color: #00B678;
      }
    }
    </style>
</head>
<body>
  <!-- QR Code -->
  <div class="qr-container">
    <img src="images/dashboard/qr_opay.png" alt="OPay QR" style="height: 35px; width:100%;">
  </div>
  
  <!-- Transfer Info -->
  <div class="transfer-info">
    <div class="instant-free">Instant and Free</div>
    <div class="transfer-text">Transfer</div>
  </div>
  
  <!-- Logo -->
  <div class="logo-container">
    <img src="images/dashboard/olog.png" alt="Opay Logo" class="logo">
  </div>
  
  <!-- Description -->
  <div class="description">
    <p>Enjoy Opay to Opay transfer &amp; up to 90 FREE</p>
    <p>transfers monthly to other banks</p>
  </div>
  
  <!-- Spacer -->
  <div class="spacer"></div>
  
  <!-- Buttons -->
  <div class="button-container">
    <div class="create-button" onclick="window.location.href='signup.php'">
      <span>Create a new account</span>
    </div>
    
    <div class="login-button" onclick="window.location.href='login.php'">
      <span>Login</span>
    </div>
  </div>
  
  <!-- Footer Image -->
  <div class="footer-image">
    <img src="images/dashboard/footer.png" alt="Footer">
  </div>
</body>
</html>