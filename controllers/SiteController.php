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

        $model = new MosResult();
        $model->name = $data['name'];
        $model->sample = $data['sample'];
        $model->score = (int)$data['score'];

        if ($model->save()) {
            return ['status' => 'ok'];
        } else {
            return ['status' => 'error', 'message' => $model->errors];
        }
    }

    // ✅ 匯出 MySQL 裡的資料為 CSV，含分類與統計
    public function actionExportCsv()
    {
        $filename = 'mos_results.csv';

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
        fputcsv($fp, ['User Name', 'Sample', 'Score', 'Category']);

        $results = MosResult::find()->orderBy('id')->all();

        foreach ($results as $r) {
            $sampleName = $r->sample;
            preg_match('/sample(\d+)_compensated\.wav/', $sampleName, $matches);
            $sampleIndex = isset($matches[1]) ? (int)$matches[1] : 0;
            $category = 'unknown';

            foreach ($categories as $label => $range) {
                if (in_array($sampleIndex, $range)) {
                    $category = $label;
                    $stats[$label][] = (int)$r->score;
                    break;
                }
            }

            fputcsv($fp, [
                $r->name,
                $sampleName,
                $r->score,
                $category
            ]);
        }

        // 加上統計
        fputcsv($fp, []);
        fputcsv($fp, ['Category', 'Count', 'Average Score']);

        foreach ($stats as $label => $scores) {
            $count = count($scores);
            $avg = $count > 0 ? round(array_sum($scores) / $count, 2) : 0;
            fputcsv($fp, [$label, $count, $avg]);
        }

        fclose($fp);
        Yii::$app->end();
    }
}
