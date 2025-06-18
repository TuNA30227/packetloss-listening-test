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

    // ✅ 儲存到 CSV（不使用 MySQL）
    
    public function actionAjaxSubmit()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['name'], $data['sample'], $data['score'])) {
            return ['status' => 'error', 'message' => 'Missing fields'];
        }

        $csvFile = Yii::getAlias('@app/data/mos_results.csv');
        $isNew = !file_exists($csvFile);

        $fp = fopen($csvFile, 'a');
        if ($isNew) {
            fputcsv($fp, ['name', 'sample', 'score']);
        }
        fputcsv($fp, [$data['name'], $data['sample'], $data['score']]);
        fclose($fp);

        return ['status' => 'ok'];
    }

    // ✅ 匯出 CSV 檔案（含分類與平均）
    public function actionAjaxSubmit()
{
    $body = file_get_contents("php://input");
    $data = json_decode($body, true);

    $name = $data['name'] ?? '';
    $sample = $data['sample'] ?? '';
    $score = $data['score'] ?? '';
    $category = $data['category'] ?? '';

    require_once __DIR__ . '/../vendor/autoload.php';

    $client = new \Google_Client();
    $client->setApplicationName('MOS Listening Form');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAuthConfig(__DIR__ . '/../credentials.json'); // 根據你實際放的位置調整
    $client->setAccessType('offline');

    $service = new \Google_Service_Sheets($client);

    $spreadsheetId = '你的 Google Sheet ID'; // 👈 替換成你的
    $range = 'Sheet1!A2';
    $values = [[$name, $sample, $score, $category]];
    $body = new \Google_Service_Sheets_ValueRange([
        'values' => $values
    ]);
    $params = ['valueInputOption' => 'USER_ENTERED'];

    try {
        $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
        return json_encode(['status' => 'success']);
    } catch (Exception $e) {
        return json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
}


    // ✅ 後台顯示資料
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
    public function actionSubmit()
{
    $name = $_POST['name'] ?? '';
    $sample = $_POST['sample'] ?? '';
    $score = $_POST['score'] ?? '';
    $category = $_POST['category'] ?? '';

    // 載入 Google API 套件
    require_once __DIR__ . '/../vendor/autoload.php';

    $client = new \Google_Client();
    $client->setApplicationName('MOS Listening Form');
    $client->setScopes([\Google_Service_Sheets::SPREADSHEETS]);
    $client->setAuthConfig(__DIR__ . '/../credentials.json'); // 如果你放在根目錄下請調整路徑
    $client->setAccessType('offline');

    $service = new \Google_Service_Sheets($client);

    // 試算表 ID（從網址中複製）
    $spreadsheetId = '1KoD90ls7hdtgFGzRc29Vhch557jOMRd4UjkftG3go3w'; // 例如：1abcD2efG3hIJKlmNOPQRstuVWXYZ45678xxx
    $range = 'Sheet1!A2'; // 將資料插入 Sheet1，自 A2 起

    // 寫入資料陣列
    $values = [[$name, $sample, $score, $category]];
    $body = new \Google_Service_Sheets_ValueRange([
        'values' => $values
    ]);
    $params = [
        'valueInputOption' => 'USER_ENTERED'
    ];

    try {
        $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
        echo "<h2>✅ 感謝您的填寫！資料已寫入 Google Sheet。</h2>";
    } catch (Exception $e) {
        echo "<h2>❌ 發生錯誤：" . htmlspecialchars($e->getMessage()) . "</h2>";
    }
}

}
