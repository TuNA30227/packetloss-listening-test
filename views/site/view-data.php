<?php
use yii\helpers\Html;
?>

<h1>問卷結果管理後台</h1>

<h2>🗂 所有問卷紀錄</h2>
<?php if (empty($results)): ?>
    <p>目前無資料。</p>
<?php else: ?>
<table class="table table-bordered" border="1" cellpadding="6" cellspacing="0">
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
<?php endif; ?>

<h3>📊 類別統計</h3>
<?php if (empty($stats)): ?>
    <p>無統計資料。</p>
<?php else: ?>
<table class="table table-bordered" border="1" cellpadding="6" cellspacing="0">
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
<?php endif; ?>
