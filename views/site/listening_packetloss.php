<?php
use yii\helpers\Url;
$this->title = '封包丟失聽測';
?>

<h2>語音品質聽測說明</h2>

<p>請依照您聽到的語音品質，選擇最符合的評分：</p>
<ul>
  <li><b>5分</b>：完全清晰、無缺損</li>
  <li><b>4分</b>：輕微失真，但仍清楚</li>
  <li><b>3分</b>：可理解，但有明顯損傷</li>
  <li><b>2分</b>：很難聽清楚，影響理解</li>
  <li><b>1分</b>：幾乎無法理解</li>
</ul>

<h3>🔊 示範音檔（5分品質）</h3>
<audio controls>
  <source src="/audio/sample_demo_5.wav" type="audio/wav">
</audio>

<br><br>

<form id="start-form">
    <label>請輸入您的姓名：</label><br>
    <input type="text" id="username" required><br><br>
    <button type="submit">開始正式測驗</button>
</form>

<!-- ✅ 正式測驗區 -->
<div id="test-area" style="display:none;">
    <p id="sample-label">第 1 / 60 題</p>

    <!-- 進度條 -->
    <div style="width: 100%; background: #eee; height: 20px; margin-bottom: 10px;">
        <div id="progress-bar" style="height: 100%; background: #4CAF50; width: 0%; transition: width 0.4s;"></div>
    </div>

    <audio id="audio-player" controls autoplay>
      <source id="audio-source" src="" type="audio/wav">
    </audio>

    <form id="mos-form">
        <label>請選擇分數：</label><br>
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <label><input type="radio" name="score" value="<?= $i ?>"> <?= $i ?></label>
        <?php endfor; ?>
        <br><br>
        <button type="submit">下一題</button>
        <button type="button" id="prev-button">上一題</button>
    </form>
</div>

<script>
const audioList = Array.from({length: 60}, (_, i) => `sample${i + 1}_compensated.wav`);
let currentIndex = 0;
let userName = '';
let answers = Array(audioList.length).fill(null);

function updateQuestionDisplay() {
    const label = document.getElementById('sample-label');
    label.innerText = `第 ${currentIndex + 1} / ${audioList.length} 題`;
    const progress = ((currentIndex) / audioList.length) * 100;
    document.getElementById('progress-bar').style.width = progress + "%";

    document.getElementById('audio-source').src = '/audio/' + audioList[currentIndex];
    document.getElementById('audio-player').load();

    document.querySelectorAll('input[name="score"]').forEach(r => {
        r.checked = false;
        if (answers[currentIndex] && answers[currentIndex] == r.value) {
            r.checked = true;
        }
    });
}

document.getElementById('start-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const nameInput = document.getElementById('username');
    if (!nameInput.value.trim()) {
        alert("請輸入您的姓名！");
        return;
    }
    userName = nameInput.value.trim();
    document.getElementById('start-form').style.display = 'none';
    document.getElementById('test-area').style.display = 'block';
    updateQuestionDisplay();
});

document.getElementById('mos-form').addEventListener('submit', function(e) {
    e.preventDefault();
    const score = document.querySelector('input[name="score"]:checked');
    if (!score) {
        alert("請先選擇一個分數！");
        return;
    }

    answers[currentIndex] = score.value;

    fetch('<?= Url::to(['site/ajax-submit']) ?>', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
            name: userName,
            sample: audioList[currentIndex],
            score: score.value
        })
    });

    currentIndex++;
    if (currentIndex < audioList.length) {
        updateQuestionDisplay();
    } else {
        document.body.innerHTML = "<h2>✅ 感謝您的填寫！</h2>";
    }
});

document.getElementById('prev-button').addEventListener('click', function() {
    if (currentIndex === 0) {
        alert("已經是第一題了！");
        return;
    }
    currentIndex--;
    updateQuestionDisplay();
});
</script>
