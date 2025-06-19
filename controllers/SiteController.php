<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    public function actionIndex()
    {
        $csvFile = Yii::getAlias('@app/web/results.csv');
        $scores = [];

        if (file_exists($csvFile)) {
            $methods = [
                'Clean'     => range(1, 27),
                'Noisy'     => range(28, 54),
                'PWN'       => range(55, 81),
                'PWN+SES'   => range(82, 108),
                'FCN'       => range(109, 135),
            ];

            $data = array_map('str_getcsv', file($csvFile));
            unset($data[0]); // 移除表頭

            foreach ($methods as $method => $range) {
                $scores[$method] = ['sum' => 0, 'count' => 0];
            }

            foreach ($data as $row) {
                if (count($row) < 3) continue;

                $sampleName = $row[1];
                $score = floatval($row[2]);

                if (preg_match('/sample(\d+)_/', $sampleName, $match)) {
                    $sampleNum = intval($match[1]);
                    foreach ($methods as $method => $range) {
                        if (in_array($sampleNum, $range)) {
                            $scores[$method]['sum'] += $score;
                            $scores[$method]['count']++;
                            break;
                        }
                    }
                }
            }

            foreach ($scores as $method => &$info) {
                $info['avg'] = $info['count'] > 0 ? round($info['sum'] / $info['count'], 2) : '-';
            }
        }

        return $this->render('index', ['scores' => $scores]);
    }


    public function actionSubmitCsv()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;

        $data = json_decode(file_get_contents('php://input'), true);
        $name = trim($data['name'] ?? 'anonymous');
        $scores = $data['scores'] ?? [];

        $categories = [
            'Clean'     => range(1, 27),
            'Noisy'     => range(28, 54),
            'PWN'       => range(55, 81),
            'PWN+SES'   => range(82, 108),
            'FCN'       => range(109, 135)
        ];

        $categoryScores = [];
        foreach ($categories as $cat => $_) {
            $categoryScores[$cat] = [];
        }

        foreach ($scores as $item) {
            preg_match('/sample(\d+)_/', $item['sample'], $matches);
            if ($matches) {
                $index = (int)$matches[1];
                foreach ($categories as $cat => $range) {
                    if (in_array($index, $range)) {
                        $categoryScores[$cat][] = (int)$item['score'];
                        break;
                    }
                }
            }
        }

        $averages = [];
        foreach ($categoryScores as $cat => $list) {
            $averages[$cat] = count($list) > 0 ? round(array_sum($list) / count($list), 2) : 0;
        }

        $timestamp = date('Y-m-d H:i:s');
        $filenameTime = date('Ymd_His');
        $safeName = preg_replace('/[^a-zA-Z0-9_]/u', '_', $name);
        $csvDir = Yii::getAlias('@app/web/results/');
        $csvFile = $csvDir . $safeName . '_' . $filenameTime . '.csv';

        if (!file_exists($csvDir)) {
            mkdir($csvDir, 0777, true);
        }

        $fp = fopen($csvFile, 'w');
        fputcsv($fp, ['Name', 'Clean', 'Noisy', 'PWN', 'PWN+SES', 'FCN', 'Timestamp']);
        fputcsv($fp, array_merge([$name], array_values($averages), [$timestamp]));
        fclose($fp);

        return [
            'status' => 'success',
            'message' => 'CSV 已儲存',
            'file' => '/results/' . basename($csvFile),
        ];
    }

    public function actionExportCsv()
    {
        $sourceFile = Yii::getAlias('@app/data/mos_results.csv');
        if (!file_exists($sourceFile)) {
            throw new \yii\web\NotFoundHttpException('No data found.');
        }

        $rows = array_map('str_getcsv', file($sourceFile));
        $headers = array_shift($rows);

        $categories = [
            'clean'     => range(1, 31),
            'noisy'     => range(32, 58),
            'pwn'       => range(59, 85),
            'pwn_ses'   => range(86, 113),
            'fcn'       => range(114, 141),
        ];

        $stats = ['clean'=>[], 'noisy'=>[], 'pwn'=>[], 'pwn_ses'=>[], 'fcn'=>[]];

        $filename = 'mos_results_export.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header("Content-Disposition: attachment; filename=\"$filename\"");

        $fp = fopen('php://output', 'w');
        fputcsv($fp, ['Name', 'Sample', 'Score', 'Category']);

        foreach ($rows as $r) {
            preg_match('/sample(\d+)_compensated\.wav/', $r[1], $m);
            $index = isset($m[1]) ? (int)$m[1] : 0;
            $category = 'unknown';
            foreach ($categories as $label => $range) {
                if (in_array($index, $range)) {
                    $category = $label;
                    $stats[$label][] = (int)$r[2];
                    break;
                }
            }
            fputcsv($fp, [$r[0], $r[1], $r[2], $category]);
        }

        fputcsv($fp, []);
        fputcsv($fp, ['Category', 'Count', 'Average Score']);
        foreach ($stats as $label => $scores) {
            $count = count($scores);
            $avg = $count ? round(array_sum($scores) / $count, 2) : 0;
            fputcsv($fp, [$label, $count, $avg]);
        }

        fclose($fp);
        Yii::$app->end();
    }

    public function actionViewData()
    {
        $csvFile = Yii::getAlias('@app/data/mos_results.csv');
        if (!file_exists($csvFile)) {
            return $this->render('view-data', ['results' => [], 'stats' => []]);
        }

        $rows = array_map('str_getcsv', file($csvFile));
        $headers = array_shift($rows);

        $categories = [
            'clean'     => range(1, 31),
            'noisy'     => range(32, 58),
            'pwn'       => range(59, 85),
            'pwn_ses'   => range(86, 113),
            'fcn'       => range(114, 141),
        ];

        $stats = ['clean'=>[], 'noisy'=>[], 'pwn'=>[], 'pwn_ses'=>[], 'fcn'=>[]];
        $results = [];

        foreach ($rows as $r) {
            $name = $r[0] ?? '';
            $sample = $r[1] ?? '';
            $score = $r[2] ?? 0;
            preg_match('/sample(\d+)_compensated\.wav/', $sample, $m);
            $index = isset($m[1]) ? (int)$m[1] : 0;
            $category = 'unknown';
            foreach ($categories as $label => $range) {
                if (in_array($index, $range)) {
                    $category = $label;
                    $stats[$label][] = (int)$score;
                    break;
                }
            }
            $results[] = [
                'name' => $name,
                'sample' => $sample,
                'score' => $score,
                'category' => $category,
            ];
        }

        $summary = [];
        foreach ($stats as $label => $scores) {
            $count = count($scores);
            $avg = $count ? round(array_sum($scores) / $count, 2) : 0;
            $summary[$label] = [
                'count' => $count,
                'avg' => $avg,
            ];
        }

        return $this->render('view-data', [
            'results' => $results,
            'stats' => $summary,
        ]);
    }

    public function actionSubmit()
{
    $name = $_POST['name'] ?? '';
    $sample = $_POST['sample'] ?? '';
    $score = $_POST['score'] ?? '';
    $category = $_POST['category'] ?? '';

    require_once __DIR__ . '/../vendor/autoload.php';


 clean-main

    // 寫成暫存檔案
    $credPath = sys_get_temp_dir() . '/credentials.json';
    file_put_contents($credPath, $jsonCreds);

    
    $values = [[$name, $sample, $score, $category]];
    $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
     $params = ['valueInputOption' => 'USER_ENTERED'];
clean-main

    $service = new \Google_Service_Sheets($client);
    $spreadsheetId = '1pPZyPkN3EVFlj4-7aDUkb402By6h_-fm4-sR-2RhACU';  // 確認填入正確ID
    $range = 'Sheet1!A2';

    $values = [[$name, $sample, $score, $category]];
    $body = new \Google_Service_Sheets_ValueRange([
        'values' => $values
    ]);
    $params = ['valueInputOption' => 'USER_ENTERED'];

    try {
        $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
        echo "<h2>✅ 感謝您的填寫！資料已寫入 Google Sheet。</h2>";
    } catch (\Exception $e) {
        echo "<h2>❌ 發生錯誤：" . htmlspecialchars($e->getMessage()) . "</h2>";
    }
}

}
