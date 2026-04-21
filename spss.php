<?php
$result = [];
$rows = [];
$casesCount = [];
$chartData = [];

/* ================= FUNCTIONS ================= */
function mean($arr)
{
  return count($arr) ? array_sum($arr) / count($arr) : 0;
}

function std($arr)
{
  if (count($arr) < 2) return 0;
  $m = mean($arr);
  $s = 0;
  foreach ($arr as $v) {
    $s += pow($v - $m, 2);
  }
  return sqrt($s / count($arr));
}

function median($arr)
{
  if (count($arr) == 0) return 0;
  sort($arr);
  $c = count($arr);
  $m = floor($c / 2);
  return ($c % 2) ? $arr[$m] : ($arr[$m - 1] + $arr[$m]) / 2;
}

/* ================= STATUS ================= */
function healthStatus($bp, $sugar, $bmi)
{
  if ($bp > 150 || $sugar > 200 || $bmi > 32) return "High Risk";
  elseif ($bp > 130 || $sugar > 150 || $bmi > 28) return "Medium Risk";
  else return "Healthy";
}

/* ================= UPLOAD ================= */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

  $file = $_FILES["file"]["tmp_name"];

  if (($h = fopen($file, "r")) !== FALSE) {

    $headers = fgetcsv($h);
    $data = [];

    foreach ($headers as $hname) {
      $data[$hname] = [];
    }

    while (($row = fgetcsv($h)) !== FALSE) {

      $r = [];

      foreach ($row as $i => $v) {

        $col = $headers[$i] ?? null;
        if (!$col) continue;

        $r[$col] = $v;

        if (is_numeric($v)) {
          $data[$col][] = $v;
        }
      }

      $status = healthStatus(
        $r["blood_pressure"] ?? 0,
        $r["sugar_level"] ?? 0,
        $r["bmi"] ?? 0
      );

      $r["status"] = $status;

      $casesCount[$status] = ($casesCount[$status] ?? 0) + 1;

      $rows[] = $r;
    }

    fclose($h);

    foreach ($data as $k => $v) {
      $result[$k] = [
        "mean" => round(mean($v), 2),
        "median" => round(median($v), 2),
        "std" => round(std($v), 2)
      ];
    }

    foreach ($rows as $r) {
      $chartData[] = $r["bmi"] ?? 0;
    }
  }
}
?>

<!DOCTYPE html>
<html>

<head>

  <title>SPSS Dashboard</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    body {
      background: linear-gradient(135deg, #f8fafc, #eef2ff);
      font-family: Segoe UI;
    }

    h2 {
      color: #4f46e5;
      font-weight: bold;
    }

    .card {
      border-radius: 18px;
      border: none;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.05);
    }

    .stat {
      background: linear-gradient(135deg, #e0f2fe, #f0f9ff);
      padding: 15px;
      border-radius: 12px;
      text-align: center;
    }

    .table th {
      background: #4f46e5;
      color: white;
    }

    canvas {
      max-height: 260px;
    }
  </style>

</head>

<body>

  <div class="container py-4">

    <h2 class="text-center">🩺 SPSS Medical Dashboard</h2>

    <!-- UPLOAD -->
    <div class="card p-3 mb-3">
      <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
        <input type="file" name="file" class="form-control" required>
        <button class="btn btn-primary">Analyze</button>
      </form>
    </div>

    <?php if ($result): ?>

      <!-- STATS -->
      <div class="row g-3 mb-3">

        <div class="col-md-4">
          <div class="stat">
            <h5>Patients</h5>
            <h3><?= count($rows) ?></h3>
          </div>
        </div>

        <div class="col-md-4">
          <div class="stat">
            <h5>Healthy</h5>
            <h3><?= count(array_filter($rows, fn($r) => $r["status"] == "Healthy")) ?></h3>
          </div>
        </div>

        <div class="col-md-4">
          <div class="stat">
            <h5>High Risk</h5>
            <h3><?= count(array_filter($rows, fn($r) => $r["status"] == "High Risk")) ?></h3>
          </div>
        </div>

      </div>

      <!-- TABLE -->
      <div class="card p-3 mb-3">
        <h5>📊 Statistics</h5>

        <table class="table table-bordered">
          <tr>
            <th>Column</th>
            <th>Mean</th>
            <th>Median</th>
            <th>Std</th>
          </tr>

          <?php foreach ($result as $k => $v): ?>
            <tr>
              <td><?= $k ?></td>
              <td><?= $v["mean"] ?></td>
              <td><?= $v["median"] ?></td>
              <td><?= $v["std"] ?></td>
            </tr>
          <?php endforeach; ?>

        </table>
      </div>

      <!-- PATIENTS -->
      <div class="card p-3 mb-3">
        <h5>🧠 Patients</h5>

        <table class="table table-bordered">
          <tr>
            <th>Name</th>
            <th>BP</th>
            <th>Sugar</th>
            <th>BMI</th>
            <th>Status</th>
          </tr>

          <?php foreach ($rows as $r): ?>
            <tr>
              <td><?= $r["name"] ?? "" ?></td>
              <td><?= $r["blood_pressure"] ?? 0 ?></td>
              <td><?= $r["sugar_level"] ?? 0 ?></td>
              <td><?= $r["bmi"] ?? 0 ?></td>
              <td><?= $r["status"] ?></td>
            </tr>
          <?php endforeach; ?>

        </table>
      </div>

      <!-- CHARTS -->
      <div class="row g-3">

        <div class="col-md-6">
          <div class="card p-3">
            <h5>📈 BMI Trend</h5>
            <canvas id="c1"></canvas>
          </div>
        </div>

        <div class="col-md-6">
          <div class="card p-3">
            <h5>🥧 Cases</h5>
            <canvas id="c2"></canvas>
          </div>
        </div>

      </div>

      <script>
        new Chart(document.getElementById("c1"), {
          type: "line",
          data: {
            labels: <?= json_encode(range(1, count($chartData))) ?>,
            datasets: [{
              label: "BMI",
              data: <?= json_encode($chartData) ?>,
              borderColor: "#4f46e5",
              tension: 0.4
            }]
          }
        });

        new Chart(document.getElementById("c2"), {
          type: "doughnut",
          data: {
            labels: <?= json_encode(array_keys($casesCount)) ?>,
            datasets: [{
              data: <?= json_encode(array_values($casesCount)) ?>
            }]
          }
        });
      </script>

    <?php endif; ?>

  </div>

</body>

</html>