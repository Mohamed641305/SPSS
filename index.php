<?php

$rows = [];
$data = [];
$cases = [];

/* ================= STATS ================= */
function mean($a)
{
    return count($a) ? array_sum($a) / count($a) : 0;
}

function std($a)
{
    if (count($a) < 2) return 0;
    $m = mean($a);
    $s = 0;
    foreach ($a as $v) $s += pow($v - $m, 2);
    return sqrt($s / count($a));
}

function correlation($x, $y)
{
    $n = min(count($x), count($y));
    if (!$n) return 0;

    $mx = mean($x);
    $my = mean($y);

    $num = $dx = $dy = 0;

    for ($i = 0; $i < $n; $i++) {
        $num += ($x[$i] - $mx) * ($y[$i] - $my);
        $dx += pow($x[$i] - $mx, 2);
        $dy += pow($y[$i] - $my, 2);
    }

    return ($dx && $dy) ? $num / sqrt($dx * $dy) : 0;
}

/* ================= AI RISK ================= */
function ai($bp, $sugar, $bmi)
{

    $score = ($bp / 200) * 40 + ($sugar / 300) * 35 + ($bmi / 50) * 25;

    if ($score < 40) return "🟢 Low Risk";
    if ($score < 70) return "🟡 Medium Risk";
    return "🔴 High Risk";
}

/* ================= UPLOAD ================= */
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $file = $_FILES["file"]["tmp_name"];
    $h = fopen($file, "r");

    $headers = fgetcsv($h);

    while ($r = fgetcsv($h)) {

        $row = array_combine($headers, $r);

        $bp = $row['blood_pressure'] ?? 0;
        $sugar = $row['sugar_level'] ?? 0;
        $bmi = $row['bmi'] ?? 0;

        $row["status"] = ai($bp, $sugar, $bmi);

        $rows[] = $row;

        if (is_numeric($bp)) $data['blood_pressure'][] = $bp;
        if (is_numeric($sugar)) $data['sugar_level'][] = $sugar;
        if (is_numeric($bmi)) $data['bmi'][] = $bmi;

        $cases[$row["status"]] = ($cases[$row["status"]] ?? 0) + 1;
    }

    fclose($h);
}
?>

<!DOCTYPE html>
<html>

<head>

    <title>Medical SPSS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        /* ================= MEDICAL DASHBOARD STYLE ================= */

        body {
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            font-family: Segoe UI;
            color: #0f172a;
        }

        /* HEADER */
        h2 {
            text-align: center;
            font-weight: 800;
            color: #0284c7;
            margin-bottom: 20px;
        }

        /* CARDS */
        .card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e2e8f0;
            box-shadow: 0 10px 25px rgba(2, 132, 199, 0.08);
        }

        /* STATS */
        .stat {
            text-align: center;
            padding: 15px;
            background: linear-gradient(135deg, #e0f7ff, #ffffff);
            border-radius: 14px;
        }

        /* TABLE */
        table {
            color: #0f172a !important;
        }

        .table thead th {
            background: #0ea5e9 !important;
            color: white !important;
        }

        /* CHART SIZE */
        canvas {
            width: 100% !important;
            height: 280px !important;
        }

        /* BUTTON */
        .btn-primary {
            background: #0ea5e9;
            border: none;
        }
    </style>

</head>

<body>

    <div class="container py-4">

        <h2>🏥 Medical SPS Dashboard</h2>

        <!-- UPLOAD -->
        <div class="card p-3 mb-3">
            <form method="POST" enctype="multipart/form-data" class="d-flex gap-2">
                <input type="file" name="file" class="form-control">
                <button class="btn btn-primary">Analyze</button>
            </form>
        </div>

        <?php if ($rows): ?>

            <!-- STATS -->
            <div class="row g-3">

                <div class="col-md-3 card stat">
                    Total Patients<br>
                    <h4><?= count($rows) ?></h4>
                </div>

                <div class="col-md-3 card stat">
                    Avg BMI<br>
                    <h4><?= round(mean($data['bmi'] ?? []), 2) ?></h4>
                </div>

                <div class="col-md-3 card stat">
                    Std BMI<br>
                    <h4><?= round(std($data['bmi'] ?? []), 2) ?></h4>
                </div>

                <div class="col-md-3 card stat">
                    Correlation<br>
                    <h4><?= round(correlation($data['blood_pressure'] ?? [], $data['sugar_level'] ?? []), 2) ?></h4>
                </div>

            </div>

            <!-- TABLE -->
            <div class="card p-3 mt-3">

                <table class="table table-hover table-bordered">

                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>BP</th>
                            <th>Sugar</th>
                            <th>BMI</th>
                            <th>Risk Level</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($rows as $r): ?>
                            <tr>
                                <td><?= $r['name'] ?? '' ?></td>
                                <td><?= $r['blood_pressure'] ?? 0 ?></td>
                                <td><?= $r['sugar_level'] ?? 0 ?></td>
                                <td><?= $r['bmi'] ?? 0 ?></td>
                                <td><?= $r['status'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>

                </table>

            </div>

            <!-- CHARTS -->
            <div class="row g-3 mt-3">

                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>📈 BMI Trend Analysis</h5>
                        <canvas id="bmiChart"></canvas>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card p-3">
                        <h5>🥧 Risk Distribution</h5>
                        <canvas id="riskChart"></canvas>
                    </div>
                </div>

            </div>

            <!-- HEATMAP -->
            <div class="card p-3 mt-3">
                <h5>📊 Correlation Heatmap</h5>
                <canvas id="heatmap"></canvas>
            </div>

            <script>
                /* BMI LINE CHART */
                new Chart(document.getElementById("bmiChart"), {
                    type: "line",
                    data: {
                        labels: <?= json_encode(range(1, count($data['bmi'] ?? []))) ?>,
                        datasets: [{
                            label: "BMI",
                            data: <?= json_encode($data['bmi'] ?? []) ?>,
                            borderColor: "#0ea5e9",
                            backgroundColor: "rgba(14,165,233,0.2)",
                            tension: 0.4
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });

                /* RISK CHART */
                new Chart(document.getElementById("riskChart"), {
                    type: "doughnut",
                    data: {
                        labels: <?= json_encode(array_keys($cases)) ?>,
                        datasets: [{
                            data: <?= json_encode(array_values($cases)) ?>,
                            backgroundColor: ["#22c55e", "#facc15", "#ef4444"]
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
                    }
                });

                /* HEATMAP */
                new Chart(document.getElementById("heatmap"), {
                    type: "bar",
                    data: {
                        labels: ["BP-Sugar", "BP-BMI", "Sugar-BMI"],
                        datasets: [{
                            label: "Correlation",
                            data: [
                                <?= correlation($data['blood_pressure'] ?? [], $data['sugar_level'] ?? []) ?>,
                                <?= correlation($data['blood_pressure'] ?? [], $data['bmi'] ?? []) ?>,
                                <?= correlation($data['sugar_level'] ?? [], $data['bmi'] ?? []) ?>
                            ],
                            backgroundColor: "#38bdf8"
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                display: false
                            }
                        }
                    }
                });
            </script>

        <?php endif; ?>

    </div>

</body>

</html>