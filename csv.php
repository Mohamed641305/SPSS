<?php
session_start();
include "db.php";

/* ================= SECURITY ================= */
if (!isset($_SESSION['admin_login'])) {
  header("Location: login.php");
  exit();
}

/* ================= INPUTS ================= */
$search = $_GET["search"] ?? "";
$disease = $_GET["disease"] ?? "";

/* ================= AI FUNCTION ================= */
function predict($bp, $sugar, $bmi)
{
  $score = 0;

  if ($bp < 120) $score += 10;
  elseif ($bp < 140) $score += 25;
  elseif ($bp < 160) $score += 40;
  else $score += 60;

  if ($sugar < 100) $score += 10;
  elseif ($sugar < 150) $score += 25;
  elseif ($sugar < 200) $score += 40;
  else $score += 60;

  if ($bmi < 25) $score += 10;
  elseif ($bmi < 30) $score += 25;
  elseif ($bmi < 35) $score += 40;
  else $score += 60;

  if ($score <= 40) return "Low";
  if ($score <= 80) return "Medium";
  if ($score <= 120) return "High";
  return "Critical";
}

/* ================= QUERY ================= */
$sql = "SELECT * FROM patients WHERE 1=1";
$params = [];

if ($search != "") {
  $sql .= " AND name LIKE :search";
  $params["search"] = "%$search%";
}

if ($disease != "") {
  $sql .= " AND disease_type = :disease";
  $params["disease"] = $disease;
}

$stmt = $connect->prepare($sql);
$stmt->execute($params);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= DATA ================= */
$rows = [];
$low = $medium = $high = $critical = 0;

/* chart diseases */
$diseaseChart = [];

foreach ($res as $r) {

  $level = predict(
    $r["blood_pressure"],
    $r["sugar_level"],
    $r["bmi"]
  );

  if ($level == "Low") $low++;
  elseif ($level == "Medium") $medium++;
  elseif ($level == "High") $high++;
  else $critical++;

  /* disease chart */
  $d = $r["disease_type"];
  if (!isset($diseaseChart[$d])) {
    $diseaseChart[$d] = 0;
  }
  $diseaseChart[$d]++;

  $rows[] = $r;
}
?>

<!DOCTYPE html>
<html>
<head>

<title>Medical Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{
  background:#f4f7ff;
  font-family:Segoe UI;
}

/* HEADER */
.header{
  background:linear-gradient(135deg,#0f766e,#14b8a6);
  color:white;
  padding:20px;
  border-radius:18px;
}

/* CARDS */
.card-box{
  padding:22px;
  border-radius:16px;
  color:white;
  text-align:center;
  font-weight:bold;
}

.c-low{background:#10b981}
.c-medium{background:#f59e0b}
.c-high{background:#3b82f6}
.c-critical{background:#ef4444}

/* TABLE */
.table-box{
  background:white;
  padding:20px;
  border-radius:15px;
  margin-top:20px;
  box-shadow:0 5px 20px rgba(0,0,0,0.1);
}

table{
  width:100%;
  border-collapse:collapse;
}

th{
  background:#0f766e;
  color:white;
  padding:12px;
  text-align:center;
}

td{
  text-align:center;
  padding:12px;
  border-bottom:1px solid #eee;
}

tr:hover td{
  background:#f1f5f9;
}

/* CHARTS */
.chart-container{
  display:grid;
  grid-template-columns:repeat(3,1fr);
  gap:15px;
  margin-top:25px;
}

.chart-box{
  background:white;
  padding:15px;
  border-radius:15px;
  height:320px;
}

canvas{
  width:100% !important;
  height:260px !important;
}
</style>

</head>

<body>

<div class="container py-4">

<!-- HEADER -->
<div class="header">
  <h4>🏥 Medical Dashboard</h4>
</div>

<!-- CARDS -->
<div class="row mt-3 g-3">

  <div class="col-md-3"><div class="card-box c-low">🟢 Low <h3><?= $low ?></h3></div></div>
  <div class="col-md-3"><div class="card-box c-medium">🟡 Medium <h3><?= $medium ?></h3></div></div>
  <div class="col-md-3"><div class="card-box c-high">🟠 High <h3><?= $high ?></h3></div></div>
  <div class="col-md-3"><div class="card-box c-critical">🔴 Critical <h3><?= $critical ?></h3></div></div>

</div>

<!-- SEARCH -->
<div class="table-box mt-3">

<form class="row g-2">

  <div class="col-md-6">
    <input name="search" class="form-control"
    value="<?= htmlspecialchars($search) ?>"
    placeholder="Search patient...">
  </div>

  <div class="col-md-4">
    <select name="disease" class="form-control">
      <option value="">All</option>
      <option>Diabetes</option>
      <option>Hypertension</option>
      <option>Heart Disease</option>
      <option>Asthma</option>
    </select>
  </div>

  <div class="col-md-2">
    <button class="btn btn-success w-100">Filter</button>
  </div>

</form>

</div>

<!-- TABLE -->
<div class="table-box">

<table>

<tr>
<th>Name</th>
<th>Disease</th>
<th>BP</th>
<th>Sugar</th>
<th>BMI</th>
<th>Status</th>
</tr>

<?php foreach($rows as $r): ?>
<tr>
<td><?= htmlspecialchars($r["name"]) ?></td>
<td><?= $r["disease_type"] ?></td>
<td><?= $r["blood_pressure"] ?></td>
<td><?= $r["sugar_level"] ?></td>
<td><?= $r["bmi"] ?></td>
<td><?= predict($r["blood_pressure"],$r["sugar_level"],$r["bmi"]) ?></td>
</tr>
<?php endforeach; ?>

</table>

</div>

<!-- CHARTS -->
<div class="chart-container">

<div class="chart-box"><canvas id="pie"></canvas></div>
<div class="chart-box"><canvas id="bar"></canvas></div>
<div class="chart-box"><canvas id="doughnut"></canvas></div>

</div>

</div>

<script>

/* PIE */
new Chart(document.getElementById("pie"),{
type:"pie",
data:{
labels:["Low","Medium","High","Critical"],
datasets:[{
data:[<?= $low ?>,<?= $medium ?>,<?= $high ?>,<?= $critical ?>],
backgroundColor:["#10b981","#f59e0b","#3b82f6","#ef4444"]
}]
}
});

/* BAR */
new Chart(document.getElementById("bar"),{
type:"bar",
data:{
labels:["Low","Medium","High","Critical"],
datasets:[{
data:[<?= $low ?>,<?= $medium ?>,<?= $high ?>,<?= $critical ?>],
backgroundColor:["#10b981","#f59e0b","#3b82f6","#ef4444"]
}]
}
});

/* DOUGHNUT (DISEASES FIXED) */
new Chart(document.getElementById("doughnut"),{
type:"doughnut",
data:{
labels:<?= json_encode(array_keys($diseaseChart)) ?>,
datasets:[{
data:<?= json_encode(array_values($diseaseChart)) ?>,
backgroundColor:["#3b82f6","#10b981","#f59e0b","#ef4444","#8b5cf6"]
}]
},
options:{
cutout:"65%"
}
});

</script>

</body>
</html>
