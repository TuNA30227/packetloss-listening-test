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
    public function actionExportCsv()
    {
        $sourceFile = Yii::getAlias('@app/data/mos_results.csv');
        if (!file_exists($sourceFile)) {
            throw new \yii\web\NotFoundHttpException('No data found.');
        }

        $rows = array_map('str_getcsv', file($sourceFile));
        $headers = array_shift($rows); // name, sample, score

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
}
