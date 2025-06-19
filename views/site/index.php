<?php
use yii\helpers\Url;
?>

<style>
/* 修正 navbar 蓋住內容 */
body {
  padding-top: 70px;
}
</style>

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
  您的瀏覽器不支援音訊播放。
</audio>

<br><br>

<form id="start-form">
    <label for="username">請輸入您的姓名：</label><br>
    <input type="text" id="username" required><br><br>
    <button type="submit">開始正式測驗</button>
</form>


<div id="test-area" style="display:none;">
    <p id="sample-label">第 1 / 135 題</p>

    <div style="width: 100%; background: #eee; height: 20px; margin-bottom: 10px;">
        <div id="progress-bar" style="height: 100%; background: #4CAF50; width: 0%; transition: width 0.4s;"></div>
    </div>

    <audio id="audio-player" controls autoplay>
        <source id="audio-source" src="" type="audio/wav">
    </audio>
    <br>
    <button type="button" id="replay-button">🔁 重播</button>

    <div id="mos-form-container">
        <label>請選擇分數：</label><br>
        <?php for ($i = 1; $i <= 5; $i++): ?>
            <label><input type="radio" name="score" value="<?= $i ?>"> <?= $i ?></label>
        <?php endfor; ?>
        <br><br>
        <button type="button" id="prev-button" disabled>上一題</button>
        <button type="button" id="next-button">下一題</button>
    </div>
</div>

<div id="download-area" style="display:none; margin-top: 30px;"></div>

<style>
.btn-download {
    display: inline-block;
    padding: 10px 20px;
    background-color: #4CAF50;
    color: white;
    font-size: 16px;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    margin-top: 10px;
}
.btn-download:hover {
    background-color: #45a049;
}
</style>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const audioList = Array.from({ length: 135 }, (_, i) => `sample${i + 1}_compensated.wav`)
                            .sort(() => Math.random() - 0.5)
                            .slice(0, 5);
    let currentIndex = 0;
    let userName = '';
    let answers = new Array(audioList.length).fill(null);

    function getCategory(sampleName) {
        const index = parseInt(sampleName.match(/\d+/)[0], 10);
        if (index <= 27) return 'clean';
        if (index <= 54) return 'noisy';
        if (index <= 81) return 'pwn';
        if (index <= 108) return 'pwn_ses';
        return 'fcn';
    }

    function updateQuestionDisplay() {
        document.getElementById('test-area').style.display = 'block';
        document.getElementById('sample-label').innerText = `第 ${currentIndex + 1} / ${audioList.length} 題`;
        document.getElementById('progress-bar').style.width = ((currentIndex + 1) / audioList.length) * 100 + "%";
        document.getElementById('audio-source').src = '/audio/' + audioList[currentIndex];
        document.getElementById('audio-player').load();
        document.getElementById('prev-button').disabled = currentIndex === 0;

        const score = answers[currentIndex];
        document.querySelectorAll('input[name="score"]').forEach(radio => {
            radio.checked = (radio.value == score);
        });

        document.getElementById('mos-form-container').scrollIntoView({ behavior: 'smooth' });
    }

    document.getElementById('start-form').addEventListener('submit', function (e) {
        e.preventDefault();
        const nameInput = document.getElementById('username');
        if (!nameInput.value.trim()) {
            alert("請輸入您的姓名！");
            return;
        }
        userName = nameInput.value.trim();
        document.getElementById('start-form').style.display = 'none';
        updateQuestionDisplay();
    });

    async function submitAnswer() {
        const score = document.querySelector('input[name="score"]:checked');
        if (!score) {
            alert("請先選擇一個分數！");
            return false;
        }

        answers[currentIndex] = score.value;

        try {
            await fetch('/index.php?r=site/ajax-submit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: userName,
                    sample: audioList[currentIndex],
                    score: score.value,
                    category: getCategory(audioList[currentIndex])
                })
            });
            return true;
        } catch (error) {
            alert('送出失敗，請稍後再試。');
            return false;
        }
    }

    document.getElementById('next-button').addEventListener('click', async function () {
        const ok = await submitAnswer();
        if (!ok) return;

        currentIndex++;
        if (currentIndex < audioList.length) {
            updateQuestionDisplay();
        } else {
            // 問卷結束，送出整份答案產生個人 CSV
            const payload = {
                name: userName,
                scores: audioList.map((sample, i) => ({
                    sample: sample,
                    score: answers[i]
                }))
            };

            fetch('/index.php?r=site/submit-csv', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(res => res.json())
            .then(data => {
                const downloadDiv = document.getElementById('download-area');
                downloadDiv.style.display = 'block';
                downloadDiv.innerHTML = `
                    <h2>✅ 問卷完成，感謝您的填寫！</h2>
                    <p>請點擊下方按鈕下載您的結果：</p>
                    <a href="${data.file}" download class="btn-download">
                        📥 下載您的 CSV 結果
                    </a>
                `;
            })
            .catch(() => {
                alert("❌ 發生錯誤，請稍後再試");
            });

            document.getElementById('test-area').style.display = 'none';
        }
    });

    document.getElementById('replay-button').addEventListener('click', function () {
        const audio = document.getElementById('audio-player');
        audio.currentTime = 0;
        audio.play();
    });

    document.getElementById('prev-button').addEventListener('click', function () {
        if (currentIndex > 0) {
            currentIndex--;
            updateQuestionDisplay();
        }
    });
});
</script>
