<?php
session_start();
require_once "config.php";

if (!isset($_SESSION['admin_email'])) {
    header("Location: index.php");
    exit();
}

/* FETCH REQUESTS */
$stmt = $pdo->query("SELECT * FROM payment_requests ORDER BY date DESC");
$requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* HANDLE ACTIONS */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $id = $_POST['request_id'];
    $action = $_POST['action'];

    $stmt = $pdo->prepare("SELECT * FROM payment_requests WHERE request_id=?");
    $stmt->execute([$id]);
    $req = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($req) {

        if ($action === "accept") {
            $days = 0;
            if ($req['plan']=="week") $days=7;
            if ($req['plan']=="month") $days=30;
            if ($req['plan']=="lifetime") $days=600;

            $expiry = date("Y-m-d H:i:s", strtotime("+$days days"));

            $pdo->prepare("UPDATE users SET plan=?, subscription_date=? WHERE uid=?")
                ->execute([$req['plan'], $expiry, $req['uid']]);

            $pdo->prepare("DELETE FROM payment_requests WHERE request_id=?")
                ->execute([$id]);

            $_SESSION['msg']="Payment approved successfully!";
        }

        if ($action === "decline") {
            $pdo->prepare("DELETE FROM payment_requests WHERE request_id=?")
                ->execute([$id]);

            $_SESSION['msg']="Payment declined!";
        }
    }

    header("Location: payment-request.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Payment Requests</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
body{
    font-family: 'Segoe UI', sans-serif;
    background:#f5f7fb;
    margin:0;
    padding:30px;
}
.topbar{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
}
.topbar h2{
    font-size:28px;
    color:#2d3748;
}
.back{
    background:#6C37F2;
    color:#fff;
    padding:12px 20px;
    border-radius:10px;
    text-decoration:none;
    font-weight:600;
}
.table{
    background:#fff;
    border-radius:14px;
    padding:20px;
    box-shadow:0 5px 20px rgba(0,0,0,0.05);
}
table{
    width:100%;
    border-collapse:collapse;
}
th{
    text-align:left;
    padding:15px;
    color:#718096;
    font-weight:600;
}
td{
    padding:15px;
    border-top:1px solid #edf2f7;
}
img{
    width:70px;
    height:70px;
    border-radius:10px;
    object-fit:cover;
    cursor:pointer;
    transition:.2s;
}
img:hover{transform:scale(1.05);}
.badge{
    background:#edf2ff;
    color:#6C37F2;
    padding:6px 12px;
    border-radius:20px;
    font-size:13px;
    font-weight:600;
}
.btn{
    padding:8px 14px;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:600;
}
.accept{background:#38a169;color:white;}
.decline{background:#e53e3e;color:white;}
.alert{
    background:#e6fffa;
    color:#065f46;
    padding:15px;
    border-radius:10px;
    margin-bottom:20px;
}
.modal{
    position:fixed;
    inset:0;
    background:rgba(0,0,0,.8);
    display:none;
    align-items:center;
    justify-content:center;
    z-index:999;
}
.modal img{
    max-width:90%;
    max-height:90%;
    border-radius:12px;
}
</style>
</head>

<body>

<div class="topbar">
    <h2>Payment Requests</h2>
    <a href="dashboard.php" class="back"><i class="fa fa-arrow-left"></i> Dashboard</a>
</div>

<?php if(isset($_SESSION['msg'])): ?>
<div class="alert"><?= $_SESSION['msg']; unset($_SESSION['msg']); ?></div>
<?php endif; ?>

<div class="table">
<table>
<tr>
<th>Receipt</th>
<th>Name</th>
<th>Email</th>
<th>Phone</th>
<th>Plan</th>
<th>Date</th>
<th>Action</th>
</tr>

<?php foreach($requests as $r): ?>
<tr>
<td>
<img src="<?= $r['image']; ?>" onclick="openImg('<?= $r['image']; ?>')">
</td>
<td><?= $r['name']; ?></td>
<td><?= $r['email']; ?></td>
<td><?= $r['number']; ?></td>
<td><span class="badge"><?= ucfirst($r['plan']); ?></span></td>
<td><?= date("d M Y H:i", strtotime($r['date'])); ?></td>
<td>
<form method="post">
<input type="hidden" name="request_id" value="<?= $r['request_id']; ?>">
<button name="action" value="accept" class="btn accept">Accept</button>
<button name="action" value="decline" class="btn decline">Decline</button>
</form>
</td>
</tr>
<?php endforeach; ?>

</table>
</div>

<div class="modal" id="modal" onclick="this.style.display='none'">
<img id="modalImg">
</div>

<script>
function openImg(src){
    document.getElementById("modalImg").src = src;
    document.getElementById("modal").style.display="flex";
}
</script>

</body>
</html>
