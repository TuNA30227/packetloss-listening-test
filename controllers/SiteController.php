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

    // ✅ 將問卷作答寫入 CSV
    public function actionAjaxSubmit()
    {
        Yii::$app->response->format = Response::FORMAT_JSON;
        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['name'], $data['sample'], $data['score'])) {
            return ['status' => 'error', 'message' => '缺少欄位'];
        }

        $file = Yii::getAlias('@app/runtime/mos_results.csv');
        $newLine = [$data['name'], $data['sample'], $data['score']];

        $fp = fopen($file, 'a');
        fputcsv($fp, $newLine);
        fclose($fp);

        return ['status' => 'ok'];
    }

    // ✅ 匯出 CSV 並統計
    public function actionExportCsv()
    {
        $filename = 'mos_results.csv';
        $filePath = Yii::getAlias('@app/runtime/mos_results.csv');

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

        if (($handle = fopen($filePath, 'r')) !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                list($name, $sample, $score) = $row;
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

                fputcsv($fp, [$name, $sample, $score, $category]);
            }
            fclose($handle);
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

    // ✅ 後台查看 CSV 資料
    public function actionViewData()
    {
        $filePath = Yii::getAlias('@app/runtime/mos_results.csv');
        $rows = [];

        if (file_exists($filePath)) {
            if (($handle = fopen($filePath, 'r')) !== false) {
                while (($row = fgetcsv($handle)) !== false) {
                    $rows[] = [
                        'name' => $row[0],
                        'sample' => $row[1],
                        'score' => $row[2],
                    ];
                }
                fclose($handle);
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

        foreach ($rows as &$r) {
            preg_match('/sample(\d+)_compensated\.wav/', $r['sample'], $m);
            $index = isset($m[1]) ? (int)$m[1] : 0;
            $r['category'] = 'unknown';

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
                'avg' => $avg,
            ];
        }

        return $this->render('view-data', [
            'results' => $rows,
            'stats' => $summary,
        ]);
    }
}
