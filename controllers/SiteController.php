<?php

namespace app\controllers;

use Yii;
use yii\web\Controller;
use yii\web\Response;
use app\models\MosResult;

class SiteController extends Controller
{
    public function actionIndex()
    {
        return $this->render('index');
    }

    // ✅ 儲存到 MySQL
    public function actionAjaxSubmit()
{
    Yii::$app->response->format = Response::FORMAT_JSON;
    $data = json_decode(file_get_contents('php://input'), true);

    if (!isset($data['name'], $data['sample'], $data['score'])) {
        return ['status' => 'error', 'message' => '缺少欄位'];
    }

    Yii::$app->db->createCommand()->insert('mos_result', [
        'name' => $data['name'],
        'sample' => $data['sample'],
        'score' => $data['score'],
    ])->execute();

    return ['status' => 'ok'];
 }
    
    }

    // ✅ 匯出 MySQL 裡的資料為 CSV，含分類與統計
    public function actionExportCsv()
{
    $filename = 'mos_results.csv';

    $rows = Yii::$app->db->createCommand('SELECT * FROM mos_result ORDER BY id ASC')->queryAll();

    // 題目分類
    $categories = [
        'clean'     => range(1, 31),
        'noisy'     => range(32, 58),
        'pwn'       => range(59, 85),
        'pwn_ses'   => range(86, 113),
        'fcn'       => range(114, 141),
    ];

    $stats = [
        'clean' => [],
        'noisy' => [],
        'pwn' => [],
        'pwn_ses' => [],
        'fcn' => [],
    ];

    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"$filename\"");

    $fp = fopen('php://output', 'w');
    fputcsv($fp, ['Name', 'Sample', 'Score', 'Category']);

    foreach ($rows as $r) {
        preg_match('/sample(\d+)_compensated\.wav/', $r['sample'], $m);
        $index = isset($m[1]) ? (int)$m[1] : 0;
        $category = 'unknown';

        foreach ($categories as $label => $range) {
            if (in_array($index, $range)) {
                $category = $label;
                $stats[$label][] = (int)$r['score'];
                break;
            }
        }

        fputcsv($fp, [$r['name'], $r['sample'], $r['score'], $category]);
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


