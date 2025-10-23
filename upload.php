<?php
require 'includes/header.php';
?>
<section class="white"><div class="container">

<h2>Upload Realised Hours (CSV) for selected year: <?= $selectedYear ?></h2>

<div class="mb-3">
  <label for="csv" class="form-label">Select CSV File</label>
  <input type="file" id="csv" class="form-control" accept=".csv">
</div>
<button id="uploadBtn" class="btn btn-primary">Upload</button>

<div id="logBox" class="mt-4" style="white-space: pre-wrap; font-family: monospace; background: #f8f9fa; padding: 1rem; border: 1px solid #ccc; max-height: 300px; overflow-y: scroll;"></div>

<div id="uploadStatus" style="display:none;" class="mt-4">
  <div class="progress mb-2" style="height: 25px;">
    <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
         role="progressbar" style="width: 0%">0%</div>
</div>
<script>
document.getElementById('uploadBtn').addEventListener('click', async () => {
  const fileInput = document.getElementById('csv');
  const logBox = document.getElementById('logBox');
  logBox.textContent = 'Uploading...\n';
  
  const statusBox = document.getElementById('uploadStatus');
  const progressEl = document.getElementById('uploadProgressBar');

  statusBox.style.display = 'block';
  progressEl.style.width = '0%';
  progressEl.textContent = '0%';
  
  const file = fileInput.files[0];
  if (!file) {
    logBox.textContent += 'No file selected.\n';
    return;
  }

  const formData = new FormData();
  formData.append('csv', file);
  formData.append('year', '<?= $selectedYear ?>');
  formData.append('csrf_token', window.csrfToken);

  const response = await fetch('upload_handler.php', {
    method: 'POST',
    body: formData,
    headers: {
      'X-CSRF-Token': window.csrfToken
    }
  });

  const reader = response.body.getReader();
  const decoder = new TextDecoder();

    let buffer = '';
    while (true) {
      const { done, value } = await reader.read();
      if (done) break;

      buffer += decoder.decode(value, { stream: true });

      let lines = buffer.split('\n');
      buffer = lines.pop(); // Last line might be incomplete, save for next round

      for (let line of lines) {
        line = line.trim();
        if (!line) continue;

        const match = line.match(/Progress:\s*(\d+)%/i);
        if (match) {
          const percent = parseInt(match[1]);
          progressEl.style.width = percent + '%';
          progressEl.textContent = percent + '%';
        } else {
          logBox.textContent += line + '\n';
          logBox.scrollTop = logBox.scrollHeight;
        }
      }
    }

    if (buffer.trim()) {
      logBox.textContent += buffer.trim() + '\n';
    }
    progressEl.style.width = '100%';
    progressEl.textContent = '100%';
    logBox.textContent += '\nDone!';
    progressEl.classList.remove('progress-bar-animated');
});
</script>

</div></section>
<?php require 'includes/footer.php'; ?>
