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
  $score =
    ($bp / 200) * 40 +
    ($sugar / 300) * 35 +
    ($bmi / 50) * 25;

  if ($score > 75) return "Critical 🔴";
  if ($score > 50) return "High 🟠";
  if ($score > 30) return "Medium 🟡";
  return "Low 🟢";
}

/* ================= QUERY (PDO FIXED) ================= */
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
$bpArr = [];
$sugarArr = [];
$healthy = 0;
$risk = 0;

foreach ($res as $r) {

  $bp = (float)$r["blood_pressure"];
  $sugar = (float)$r["sugar_level"];
  $bmi = (float)$r["bmi"];

  $bpArr[] = $bp;
  $sugarArr[] = $sugar;

  if ($bp > 140 || $sugar > 180 || $bmi > 30) $risk++;
  else $healthy++;

  $rows[] = $r;
}

/* ================= DISEASE STATS ================= */
$diseaseData = [];
$dRes = $connect->query("SELECT disease_type, COUNT(*) c FROM patients GROUP BY disease_type");

if ($dRes) {
  while ($d = $dRes->fetch(PDO::FETCH_ASSOC)) {
    $diseaseData[$d["disease_type"]] = $d["c"];
  }
}

/* ================= CORRELATION ================= */
function correlation($x, $y)
{
  $n = count($x);
  if ($n < 2) return 0;

  $sx = array_sum($x);
  $sy = array_sum($y);

  $sxy = $sx2 = $sy2 = 0;

  for ($i = 0; $i < $n; $i++) {
    $sxy += $x[$i] * $y[$i];
    $sx2 += $x[$i] * $x[$i];
    $sy2 += $y[$i] * $y[$i];
  }

  $den = sqrt(($n * $sx2 - $sx * $sx) * ($n * $sy2 - $sy * $sy));

  return $den == 0 ? 0 : (($n * $sxy - $sx * $sy) / $den);
}

$correlation = correlation($bpArr, $sugarArr);
?>

<!DOCTYPE html>
<html>
<head>

<title>Medical Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<style>
body{
  background: linear-gradient(135deg,#eef2f7,#dbeafe);
  font-family: "Segoe UI";
}

body.dark{
  background:#0b1220;
  color:white;
}

.header{
  background: linear-gradient(135deg,#0f766e,#14b8a6);
  color:white;
  padding:18px;
  border-radius:18px;
}

.card-box{
  padding:22px;
  border-radius:20px;
  color:white;
  text-align:center;
}

.c1{background:linear-gradient(135deg,#2563eb,#60a5fa)}
.c2{background:linear-gradient(135deg,#10b981,#34d399)}
.c3{background:linear-gradient(135deg,#f59e0b,#fbbf24)}
.c4{background:linear-gradient(135deg,#ef4444,#f87171)}

.table-box{
  background:white;
  border-radius:20px;
  padding:18px;
  margin-top:20px;
}

body.dark .table-box{
  background:#1e293b;
}

table{
  width:100%;
}

th{
  background:#0f766e;
  color:white;
  padding:10px;
}

td{
  text-align:center;
  padding:8px;
}

.charts{
  display:grid;
  grid-template-columns:repeat(auto-fit,minmax(300px,1fr));
  gap:15px;
  margin-top:20px;
}

.chart-box{
  background:white;
  padding:15px;
  border-radius:20px;
}
</style>

</head>

<body>

<div class="container py-4">

<!-- HEADER -->
<div class="header d-flex justify-content-between">
  <h4>🧠 Medical Dashboard</h4>
  <button class="btn btn-light btn-sm" onclick="document.body.classList.toggle('dark')">🌙</button>
</div>

<!-- CARDS -->
<div class="row mt-3 g-3">

  <div class="col-md-4">
    <div class="card-box c2">Healthy <h2><?= $healthy ?></h2></div>
  </div>

  <div class="col-md-4">
    <div class="card-box c4">Risk <h2><?= $risk ?></h2></div>
  </div>

  <div class="col-md-4">
    <div class="card-box c3">Correlation <h2><?= round($correlation,2) ?></h2></div>
  </div>

</div>

<!-- SEARCH -->
<div class="table-box">

<form class="row g-2">

  <div class="col-md-6">
    <input name="search" class="form-control" placeholder="Search patient...">
  </div>

  <div class="col-md-4">
    <select name="disease" class="form-control">
      <option value="">All Diseases</option>
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
<th>Risk</th>
</tr>

<?php foreach($rows as $r): ?>
<tr>
<td><?= $r["name"] ?></td>
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
<div class="charts">

<div class="chart-box"><canvas id="pie"></canvas></div>
<div class="chart-box"><canvas id="scatter"></canvas></div>
<div class="chart-box"><canvas id="disease"></canvas></div>

</div>

</div>

<script>

/* PIE */
new Chart(document.getElementById("pie"),{
type:"pie",
data:{
labels:["Healthy","Risk"],
datasets:[{data:[<?= $healthy ?>,<?= $risk ?>]}]
}
});

/* SCATTER */
new Chart(document.getElementById("scatter"),{
type:"scatter",
data:{
datasets:[{
label:"BP vs Sugar",
data:[
<?php foreach($bpArr as $i=>$v): ?>
{x:<?= $v ?>, y:<?= $sugarArr[$i] ?>},
<?php endforeach; ?>
]
}]
}
});

/* DISEASE */
new Chart(document.getElementById("disease"),{
type:"pie",
data:{
labels:<?= json_encode(array_keys($diseaseData)) ?>,
datasets:[{data:<?= json_encode(array_values($diseaseData)) ?>}]
}
});

</script>

</body>
</html>
