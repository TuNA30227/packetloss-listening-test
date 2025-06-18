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
            return ['status' => 'error', 'message' => '缺少欄位'];
        }

        $file = Yii::getAlias('@app/runtime/mos_results.csv');
        $isNew = !file_exists($file);
        $fp = fopen($file, 'a');

        if ($isNew) {
            fputcsv($fp, ['name', 'sample', 'score', 'timestamp']);
        }

        fputcsv($fp, [
            $data['name'],
            $data['sample'],
            $data['score'],
            date('Y-m-d H:i:s'),
        ]);

        fclose($fp);
        return ['status' => 'ok'];
    }

    // ✅ 匯出 CSV + 統計
    public function actionExportCsv()
    {
        $filename = 'mos_results_export.csv';
        $file = Yii::getAlias('@app/runtime/mos_results.csv');

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

        if (!file_exists($file)) {
            fputcsv($fp, ['No data found']);
            fclose($fp);
            return;
        }

        $lines = array_map('str_getcsv', file($file));
        $header = array_shift($lines);
        fputcsv($fp, array_merge($header, ['category']));

        foreach ($lines as $row) {
            $sample = $row[1] ?? '';
            preg_match('/sample(\d+)_compensated\.wav/', $sample, $m);
            $index = isset($m[1]) ? (int)$m[1] : 0;
            $category = 'unknown';

            foreach ($categories as $label => $range) {
                if (in_array($index, $range)) {
                    $category = $label;
                    $stats[$label][] = (int)$row[2];
                    break;
                }
            }

            fputcsv($fp, array_merge($row, [$category]));
        }

        fputcsv($fp, []);
        fputcsv($fp, ['category', 'count', 'avg_score']);
        foreach ($stats as $label => $scores) {
            $count = count($scores);
            $avg = $count ? round(array_sum($scores) / $count, 2) : 0;
            fputcsv($fp, [$label, $count, $avg]);
        }

        fclose($fp);
        Yii::$app->end();
    }

    // ✅ 瀏覽後台（從 CSV 讀取）
    public function actionViewData()
    {
        $file = Yii::getAlias('@app/runtime/mos_results.csv');
        $results = [];

        if (file_exists($file)) {
            $lines = array_map('str_getcsv', file($file));
            $header = array_shift($lines);

            foreach ($lines as $line) {
                $results[] = array_combine($header, $line);
            }
        }

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

        foreach ($results as &$r) {
            $r['category'] = 'unknown';
            preg_match('/sample(\d+)_compensated\.wav/', $r['sample'] ?? '', $m);
            $index = isset($m[1]) ? (int)$m[1] : 0;

            foreach ($categories as $label => $range) {
                if (in_array($index, $range)) {
                    $r['category'] = $label;
                    $stats[$label][] = (int)$r['score'];
                    break;
                }
            }
        }

        $summary = [];
        foreach ($stats as $label => $scores) {
            $count = count($scores);
            $avg = $count ? round(array_sum($scores) / $count, 2) : 0;
            $summary[$label] = [
                'count' => $count,
                'avg'   => $avg,
            ];
        }

        return $this->render('view-data', [
            'results' => $results,
            'stats'   => $summary,
        ]);
    }
}
