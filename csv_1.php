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

/* ================= AI FUNCTION (IMPROVED) ================= */
function predict($bp, $sugar, $bmi)
{
  $score = 0;

  // BP
  if ($bp < 120) $score += 10;
  elseif ($bp < 140) $score += 25;
  elseif ($bp < 160) $score += 40;
  else $score += 60;

  // Sugar
  if ($sugar < 100) $score += 10;
  elseif ($sugar < 150) $score += 25;
  elseif ($sugar < 200) $score += 40;
  else $score += 60;

  // BMI
  if ($bmi < 25) $score += 10;
  elseif ($bmi < 30) $score += 25;
  elseif ($bmi < 35) $score += 40;
  else $score += 60;

  if ($score > 120) return "Critical";
  if ($score > 80) return "High";
  if ($score > 40) return "Medium";
  return "Low";
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

$sql .= " LIMIT 300";

$stmt = $connect->prepare($sql);
$stmt->execute($params);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= DATA ================= */
$rows = [];
$bpArr = [];
$sugarArr = [];

$low = 0;
$medium = 0;
$high = 0;
$critical = 0;

foreach ($res as $r) {

  $bp = (float)$r["blood_pressure"];
  $sugar = (float)$r["sugar_level"];
  $bmi = (float)$r["bmi"];

  $bpArr[] = $bp;
  $sugarArr[] = $sugar;

  $level = predict($bp, $sugar, $bmi);

  if ($level == "Low") $low++;
  elseif ($level == "Medium") $medium++;
  elseif ($level == "High") $high++;
  else $critical++;

  $rows[] = $r;
}

/* ================= DISEASE ================= */
$diseaseData = [];
$dRes = $connect->query("SELECT disease_type, COUNT(*) c FROM patients GROUP BY disease_type");

if ($dRes) {
  while ($d = $dRes->fetch(PDO::FETCH_ASSOC)) {
    $diseaseData[$d["disease_type"]] = $d["c"];
  }
}
?>

<!DOCTYPE html>
<html>

<head>

  <title>Medical Dashboard</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    body {
      background: linear-gradient(135deg, #eef2f7, #dbeafe);
    }

    .header {
      background: linear-gradient(135deg, #0f766e, #14b8a6);
      color: white;
      padding: 18px;
      border-radius: 18px;
    }

    .card-box {
      padding: 20px;
      border-radius: 20px;
      color: white;
      text-align: center;
    }

    .c-low { background: #10b981 }
    .c-medium { background: #f59e0b }
    .c-high { background: #3b82f6 }
    .c-critical { background: #ef4444 }

    .table-box {
      background: white;
      border-radius: 20px;
      padding: 20px;
      margin-top: 20px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
    }

    table {
      width: 100%;
      border-collapse: separate;
      border-spacing: 0 8px;
    }

    th {
      background: #0f766e;
      color: white;
      padding: 12px;
      text-align: center;
    }

    td {
      background: #f9fafb;
      padding: 12px;
      text-align: center;
    }

    tr:hover td {
      background: #e0f2fe;
    }

    .badge-custom {
      padding: 6px 12px;
      border-radius: 20px;
      color: white;
    }

    .chart-box {
      background: white;
      padding: 20px;
      border-radius: 20px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.08);
      height: 380px;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    canvas {
      width: 100% !important;
      height: 320px !important;
    }
  </style>

</head>

<body>

<div class="container py-4">

<div class="header">
  <h4>🧠 Medical Dashboard</h4>
</div>

<div class="row mt-3 g-3">

  <div class="col-md-3"><div class="card-box c-low">Low <h2><?= $low ?></h2></div></div>
  <div class="col-md-3"><div class="card-box c-medium">Medium <h2><?= $medium ?></h2></div></div>
  <div class="col-md-3"><div class="card-box c-high">High <h2><?= $high ?></h2></div></div>
  <div class="col-md-3"><div class="card-box c-critical">Critical <h2><?= $critical ?></h2></div></div>

</div>

<div class="table-box">

<form class="row g-2">

  <div class="col-md-6">
    <input name="search" class="form-control" value="<?= htmlspecialchars($search) ?>" placeholder="Search patient...">
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

<div class="table-box">

<table>

<thead>
<tr>
<th>Name</th>
<th>Disease</th>
<th>BP</th>
<th>Sugar</th>
<th>BMI</th>
<th>Status</th>
</tr>
</thead>

<tbody>

<?php foreach($rows as $r): ?>
<tr>

<td><?= htmlspecialchars($r["name"]) ?></td>
<td><?= $r["disease_type"] ?></td>
<td><?= $r["blood_pressure"] ?></td>
<td><?= $r["sugar_level"] ?></td>
<td><?= $r["bmi"] ?></td>

<?php
$level = predict($r["blood_pressure"], $r["sugar_level"], $r["bmi"]);

$color = match($level){
  "Low" => "#10b981",
  "Medium" => "#f59e0b",
  "High" => "#3b82f6",
  "Critical" => "#ef4444"
};
?>

<td>
<span class="badge-custom" style="background:<?= $color ?>">
  <?= $level ?>
</span>
</td>

</tr>
<?php endforeach; ?>

</tbody>
</table>

</div>

<div class="row g-4 mt-3 align-items-stretch">

<div class="col-md-4 d-flex">
  <div class="chart-box w-100"><canvas id="pie"></canvas></div>
</div>

<div class="col-md-4 d-flex">
  <div class="chart-box w-100"><canvas id="scatter"></canvas></div>
</div>

<div class="col-md-4 d-flex">
  <div class="chart-box w-100"><canvas id="disease"></canvas></div>
</div>

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

/* BAR (REPLACED SCATTER) */
new Chart(document.getElementById("scatter"), {
  type: "bar",
  data: {
    labels: [
      <?php foreach ($rows as $r): ?>
        "<?= htmlspecialchars($r['name']) ?>",
      <?php endforeach; ?>
    ],
    datasets: [
      {
        label: "Blood Pressure",
        data: [
          <?php foreach ($rows as $r): ?>
            <?= $r["blood_pressure"] ?>,
          <?php endforeach; ?>
        ],
        backgroundColor: "#3b82f6"
      },
      {
        label: "Sugar Level",
        data: [
          <?php foreach ($rows as $r): ?>
            <?= $r["sugar_level"] ?>,
          <?php endforeach; ?>
        ],
        backgroundColor: "#ef4444"
      }
    ]
  }
});

/* DISEASE */
new Chart(document.getElementById("disease"),{
type:"pie",
data:{
labels:<?= json_encode(array_keys($diseaseData)) ?>,
datasets:[{
data:<?= json_encode(array_values($diseaseData)) ?>,
backgroundColor:["#3b82f6","#10b981","#f59e0b","#ef4444","#8b5cf6"]
}]
}
});

</script>

</body>
</html>
