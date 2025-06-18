<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;

class SiteController extends Controller
{
    public function actionIndex()
    {
        return $this->render('index');
    }

    public function actionAjaxSubmit()
    {
        Yii::$app->response->format = \yii\web\Response::FORMAT_JSON;

        try {
            $data = json_decode(file_get_contents('php://input'), true);

            // 寫入 debug 檔案查看前端傳來的資料
            file_put_contents(Yii::getAlias('@app/runtime/debug_ajax.txt'), print_r($data, true));

            // 🐞 DEBUG: 環境變數有沒有成功讀到
            $jsonCreds = getenv('GOOGLE_APPLICATION_CREDENTIALS_JSON');
            file_put_contents(Yii::getAlias('@app/runtime/debug_env.txt'), $jsonCreds ? '✅ 環境變數已讀取' : '❌ 環境變數為空');

            if (!isset($data['name'], $data['sample'], $data['score'], $data['category'])) {
                throw new \Exception("資料不完整");
            }

            if (!$jsonCreds) {
                throw new \Exception("GOOGLE_APPLICATION_CREDENTIALS_JSON 環境變數未設定");
            }

            $client = new \Google_Client();
            $client->setAuthConfig(json_decode($jsonCreds, true));
            $client->addScope(\Google_Service_Sheets::SPREADSHEETS);
            $service = new \Google_Service_Sheets($client);

            $spreadsheetId = '1KoD90ls7hdtgFGzRc29Vhch557jOMRd4UjkftG3go3w';
            $range = 'A2:D';
            $values = [[$data['name'], $data['sample'], $data['score'], $data['category']]];
            $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);

            $params = ['valueInputOption' => 'RAW'];
            $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);

            // 可寫入 Google 回傳內容進一步除錯
            file_put_contents(Yii::getAlias('@app/runtime/google_result.txt'), print_r($result, true));

            return ['status' => 'success'];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ];
        }
    }

    // ✅ 匯出 CSV 檔案
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

    // ✅ 顯示後台統計資料
    public function actionViewData()
    {
        $csvFile = Yii::getAlias('@app/data/mos_results.csv');
        if (!file_exists($csvFile)) {
            return $this->render('view-data', [
                'results' => [],
                'stats' => [],
            ]);
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

    // ✅ 備用非 AJAX 提交寫入 Google Sheet
    public function actionSubmit()
    {
        $name = $_POST['name'] ?? '';
        $sample = $_POST['sample'] ?? '';
        $score = $_POST['score'] ?? '';
        $category = $_POST['category'] ?? '';

        require_once __DIR__ . '/../vendor/autoload.php';

        $jsonCreds = getenv('GOOGLE_CREDENTIALS');
        if ($jsonCreds === false) {
            echo "<h2>❌ Google credentials missing</h2>";
            return;
        }

        $credPath = sys_get_temp_dir() . '/credentials.json';
        file_put_contents($credPath, $jsonCreds);

        $client = new \Google_Client();
        $client->setApplicationName('MOS Listening Form');
        $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
        $client->setAuthConfig($credPath);
        $client->setAccessType('offline');

        $service = new \Google_Service_Sheets($client);
        $spreadsheetId = '1KoD90ls7hdtgFGzRc29Vhch557jOMRd4UjkftG3go3w';
        $range = 'Sheet1!A2';

        $values = [[$name, $sample, $score, $category]];
        $body = new \Google_Service_Sheets_ValueRange(['values' => $values]);
        $params = ['valueInputOption' => 'USER_ENTERED'];

        try {
            $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
            echo "<h2>✅ 感謝您的填寫！資料已寫入 Google Sheet。</h2>";
        } catch (\Exception $e) {
            echo "<h2>❌ 發生錯誤：" . htmlspecialchars($e->getMessage()) . "</h2>";
        }
    }
}
