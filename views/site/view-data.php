<?php

use yii\helpers\Html;

?>

<h2>🗂 所有問卷紀錄</h2>
<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr>
            <th>#</th>
            <th>Name</th>
            <th>Sample</th>
            <th>Score</th>
            <th>Category</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($results as $i => $r): ?>
        <tr>
            <td><?= $i + 1 ?></td>
            <td><?= Html::encode($r['name']) ?></td>
            <td><?= Html::encode($r['sample']) ?></td>
            <td><?= Html::encode($r['score']) ?></td>
            <td><?= Html::encode($r['category']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<h3>📊 類別統計</h3>
<table border="1" cellpadding="6" cellspacing="0">
    <thead>
        <tr><th>Category</th><th>Count</th><th>Average Score</th></tr>
    </thead>
    <tbody>
    <?php foreach ($stats as $label => $data): ?>
        <tr>
            <td><?= Html::encode($label) ?></td>
            <td><?= Html::encode($data['count']) ?></td>
            <td><?= Html::encode($data['avg']) ?></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
