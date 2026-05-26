<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$userAgent = $_SERVER['HTTP_USER_AGENT'];

// If not mobile
if (!preg_match('/Mobile|Android|iPhone|iPad|iPod/i', $userAgent)) {
    die("Sorry, this website is only available on mobile devices.");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bank Selection</title>
    <link rel="stylesheet" href="css/bn-list.css">
</head>
<body>
    <div class="linear-layout">
        <div class="linear1">
            <div class="linear2">
                <div class="image-view">
                    <img src="https://cdn3.iconfinder.com/data/icons/feather-5/24/x-512.png" alt="Clear" width="20" height="20">
                </div>
                <div class="text-view-header" style="padding: 8px;">Select Bank</div>
            </div>
            <div class="linear-sracg">
                <div class="image-view">
                    <img src="https://cdn3.iconfinder.com/data/icons/feather-5/24/search-512.png" alt="Search" width="20" height="20">
                </div>
                <input type="text" class="edit-text" placeholder="Search Bank Name">
            </div>
        </div>
        
        <div class="linear18">
            <div class="linear8">
                <div class="linear-frequent">
                    <div class="text-view">Frequently Used Bank</div>
                </div>
                
                <div class="linear5">
                    <div class="linear10">
                        <div class="linear12">
                            <div class="image-view">
                                <img src="images/toban/opay.png" alt="OPay" width="50" height="50">
                            </div>
                            <div class="text-view-small">OPay</div>
                            <div class="image-view">
                                
                            </div>
                        </div>
                        <div class="linear14">
                            <div class="image-view">
                                <img src="images/toban/access.png" alt="Access Bank" width="50" height="50">
                            </div>
                            <div class="text-view-small">Access Bank</div>
                        </div>
                        <div class="linear13">
                            <div class="image-view">
                                <img src="images/toban/uba.png" alt="UBA" width="45" height="45">
                            </div>
                            <div class="text-view-small">United Bank For<br>Africa</div>
                        </div>
                    </div>
                    
                    <div class="linear11">
                        <div class="linear15">
                            <div class="image-view">
                                <img src="images/toban/first.png" alt="First Bank" width="45" height="45" style="margin-top: 15px;">
                            </div>
                            <div class="text-view-small">First Bank Of<br>Nigeria</div>
                        </div>
                        <div class="linear16">
                            <div class="image-view">
                                <img src="images/toban/gt.png" alt="GTBank" width="45" height="45">
                            </div>
                            <div class="text-view-small">Guaranty Trust Bank</div>
                        </div>
                        <div class="linear17">
                            <div class="image-view">
                                <img src="images/toban/zenith.png" alt="Zenith Bank" width="45" height="45">
                            </div>
                            <div class="text-view-small">Zenith Bank</div>
                        </div>
                    </div>
                </div>
                
                <div class="linearA">
                    <div class="text-view-gray">A</div>
                </div>
                
                <ul class="list-view">
                    <!-- Custom list item -->
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/accessbankplc.com" alt="Access Bank">
                        </div>
                        <div class="list-item-text">Access Bank</div>
                    </li>
                    
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/citibank.com" alt="Citi Bank">
                        </div>
                        <div class="list-item-text">Citi Bank</div>
                    </li>
                    
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/ecobank.com" alt="Ecobank">
                        </div>
                        <div class="list-item-text">Ecobank</div>
                    </li>
                    
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/fidelitybank.com" alt="Fidelity Bank">
                        </div>
                        <div class="list-item-text">Fidelity Bank</div>
                    </li>
                    
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/firstbanknigeria.com" alt="First Bank">
                        </div>
                        <div class="list-item-text">First Bank of Nigeria</div>
                    </li>
                    
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/gtbank.com" alt="GTBank">
                        </div>
                        <div class="list-item-text">Guaranty Trust Bank</div>
                    </li>
                    
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/standardchartered.com" alt="Standard Chartered">
                        </div>
                        <div class="list-item-text">Standard Chartered Bank</div>
                    </li>
                    
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/sterlingbank.com" alt="Sterling Bank">
                        </div>
                        <div class="list-item-text">Sterling Bank</div>
                    </li>
                    
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/ubagroup.com" alt="UBA">
                        </div>
                        <div class="list-item-text">United Bank for Africa</div>
                    </li>
                    
                    <li class="linear4">
                        <div class="circle-image-view">
                            <img src="https://logo.clearbit.com/zenithbank.com" alt="Zenith Bank">
                        </div>
                        <div class="list-item-text">Zenith Bank</div>
                    </li>
                </ul>
            </div>
            
            <div class="alphabet-sidebar">
                A<br><br>B<br><br>C<br><br>D<br><br>E<br><br>F<br><br>G<br><br>H<br><br>I<br><br>J<br><br>K<br><br>L<br><br>M<br><br>N<br><br>O<br><br>P<br><br>Q<br><br>R<br><br>S<br><br>T<br><br>U<br><br>V<br><br>W<br><br>X<br><br>Y<br><br>Z
            </div>
        </div>
    </div>
</body>
<script src="js/bn-list.js" defer></script>
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
</html>