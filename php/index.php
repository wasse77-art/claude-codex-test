<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Web スクレイパー</title>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js"></script>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', 'Hiragino Sans', sans-serif;
      background: #f0f4f8;
      color: #2d3748;
      min-height: 100vh;
    }

    /* ── Header ─────────────────────────────── */
    header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: #fff;
      padding: 22px 20px;
      text-align: center;
      box-shadow: 0 3px 12px rgba(0,0,0,.18);
    }
    header h1 { font-size: 1.75em; font-weight: 700; }
    header p  { opacity: .85; margin-top: 5px; font-size: .9em; }

    /* ── Layout ─────────────────────────────── */
    .container { max-width: 1280px; margin: 0 auto; padding: 20px; }

    /* ── Tabs ────────────────────────────────── */
    .tabs {
      display: flex; gap: 6px; padding: 6px;
      background: #fff; border-radius: 12px; margin-bottom: 18px;
      box-shadow: 0 2px 8px rgba(0,0,0,.07);
    }
    .tab-btn {
      flex: 1; padding: 11px; border: none; border-radius: 8px;
      font-size: .97em; font-weight: 600; cursor: pointer;
      color: #718096; background: transparent; transition: all .2s;
    }
    .tab-btn.active {
      background: linear-gradient(135deg, #667eea, #764ba2);
      color: #fff;
    }

    /* ── Cards ──────────────────────────────── */
    .card {
      background: #fff; border-radius: 12px;
      padding: 22px 26px; margin-bottom: 18px;
      box-shadow: 0 2px 8px rgba(0,0,0,.07);
    }
    .card h2 { font-size: 1em; color: #4a5568; margin-bottom: 12px; }

    /* ── URL input ───────────────────────────── */
    .url-row { display: flex; gap: 10px; }
    .url-input {
      flex: 1; padding: 12px 16px;
      border: 2px solid #e2e8f0; border-radius: 8px;
      font-size: 1em; outline: none; transition: border-color .2s;
    }
    .url-input:focus { border-color: #667eea; }

    /* ── Buttons ─────────────────────────────── */
    .btn {
      padding: 12px 22px; border: none; border-radius: 8px;
      font-size: .95em; font-weight: 600; cursor: pointer;
      transition: transform .15s, box-shadow .15s; white-space: nowrap;
    }
    .btn:disabled { opacity: .5; cursor: not-allowed; transform: none !important; box-shadow: none !important; }
    .btn-purple { background: linear-gradient(135deg, #667eea, #764ba2); color: #fff; }
    .btn-purple:not(:disabled):hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(102,126,234,.4); }
    .btn-green  { background: linear-gradient(135deg, #48bb78, #38a169); color: #fff; }
    .btn-green:not(:disabled):hover  { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(72,187,120,.4); }
    .btn-blue   { background: linear-gradient(135deg, #4299e1, #2b6cb0); color: #fff; }
    .btn-blue:not(:disabled):hover   { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(66,153,225,.4); }
    .btn-gray   { background: #e2e8f0; color: #4a5568; }
    .btn-gray:not(:disabled):hover   { background: #cbd5e0; }
    .btn-sm     { padding: 8px 14px !important; font-size: .85em !important; }

    /* ── Error banner ────────────────────────── */
    .error-msg {
      display: none; margin-top: 10px; padding: 11px 15px;
      background: #fff5f5; border: 1px solid #feb2b2;
      border-radius: 8px; color: #c53030; font-size: .9em;
    }

    /* ── Notice ──────────────────────────────── */
    .notice {
      display: none; margin-bottom: 16px; padding: 11px 15px;
      background: #fffbf0; border: 1px solid #f6e05e;
      border-radius: 8px; color: #744210; font-size: .82em; line-height: 1.6;
    }

    /* ── Stats bar ───────────────────────────── */
    .stats-bar { display: none; align-items: center; justify-content: space-between; }
    .stats-group { display: flex; gap: 28px; }
    .stat { text-align: center; }
    .stat-value { font-size: 2em; font-weight: 700; color: #667eea; line-height: 1; }
    .stat-label { font-size: .75em; color: #718096; margin-top: 3px; }

    /* ── Controls bar ────────────────────────── */
    .controls-bar { display: none; align-items: center; justify-content: space-between; }
    .select-all-label {
      display: flex; align-items: center; gap: 8px;
      font-weight: 600; color: #4a5568; cursor: pointer; user-select: none;
    }
    .select-all-label input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #667eea; }
    .sel-info  { font-size: .9em; color: #718096; }
    .drag-hint { font-size: .78em; color: #a0aec0; }

    /* ── Image grid ──────────────────────────── */
    .image-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 14px; margin-bottom: 18px;
    }
    .image-grid.is-dragging,
    .image-grid.is-dragging * { cursor: crosshair !important; }

    .img-card {
      background: #fff; border-radius: 10px; overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,.08); cursor: pointer;
      transition: transform .18s, box-shadow .18s, outline .1s;
      outline: 3px solid transparent;
    }
    .img-card:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,.13); }
    .img-card.selected { outline-color: #667eea; }
    .img-thumb { position: relative; width: 100%; padding-bottom: 75%; background: #f7fafc; }
    .img-thumb img {
      position: absolute; inset: 0; width: 100%; height: 100%;
      object-fit: cover; pointer-events: none;
    }
    .img-thumb .img-error {
      position: absolute; inset: 0;
      display: flex; align-items: center; justify-content: center;
      color: #a0aec0; font-size: .75em; text-align: center; padding: 8px;
    }
    .img-chk {
      position: absolute; top: 7px; left: 7px;
      width: 20px; height: 20px; cursor: pointer; accent-color: #667eea; z-index: 1;
    }
    .img-info { padding: 7px 10px; }
    .img-name { font-size: .7em; color: #718096; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .img-host { font-size: .63em; color: #a0aec0; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* ── Session divider ─────────────────────── */
    .session-divider {
      grid-column: 1 / -1; padding: 6px 0 2px;
      font-weight: 600; color: #4a5568; font-size: .85em;
      display: flex; align-items: center; gap: 8px;
    }
    .session-tag {
      background: #667eea; color: #fff; border-radius: 4px;
      padding: 2px 8px; font-size: .75em; font-weight: 500;
    }

    /* ── Video list ──────────────────────────── */
    .video-list { display: flex; flex-direction: column; gap: 10px; margin-bottom: 18px; }
    .video-card {
      background: #fff; border-radius: 10px; padding: 14px 16px;
      box-shadow: 0 2px 6px rgba(0,0,0,.08);
      display: flex; align-items: center; gap: 14px;
      outline: 3px solid transparent; transition: outline .1s;
      cursor: pointer;
    }
    .video-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,.12); }
    .video-card.selected { outline-color: #667eea; }
    .video-icon-wrap { font-size: 2em; flex-shrink: 0; width: 44px; text-align: center; }
    .video-details { flex: 1; min-width: 0; }
    .video-name {
      font-size: .88em; font-weight: 600; color: #2d3748;
      white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }
    .video-meta { display: flex; gap: 8px; align-items: center; margin-top: 5px; flex-wrap: wrap; }
    .video-badge {
      background: #667eea; color: #fff; font-size: .65em;
      font-weight: 700; padding: 2px 6px; border-radius: 4px; flex-shrink: 0;
    }
    .video-src { font-size: .7em; color: #a0aec0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .video-actions { display: flex; align-items: center; gap: 10px; flex-shrink: 0; }
    .video-chk { width: 20px; height: 20px; cursor: pointer; accent-color: #667eea; }

    /* ── Action bar ──────────────────────────── */
    .action-bar {
      display: none; gap: 14px; justify-content: center; flex-wrap: wrap;
      position: sticky; bottom: 16px;
    }
    .action-bar .btn { padding: 14px 30px; font-size: 1em; }

    /* ── Empty state ─────────────────────────── */
    .empty-state { text-align: center; padding: 70px 20px; color: #a0aec0; }
    .empty-icon  { font-size: 3.5em; margin-bottom: 14px; }

    /* ── Loading ─────────────────────────────── */
    .loading { display: none; text-align: center; padding: 40px; color: #718096; }
    .loading.active { display: block; }
    .spinner {
      width: 40px; height: 40px;
      border: 4px solid #e2e8f0; border-top-color: #667eea;
      border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 14px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }

    /* ── Progress overlay ────────────────────── */
    .progress-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,.6); z-index: 3000;
      align-items: center; justify-content: center;
    }
    .progress-overlay.active { display: flex; }
    .progress-box {
      background: #fff; border-radius: 16px; padding: 36px 32px;
      max-width: 400px; width: 90%; text-align: center;
      box-shadow: 0 20px 60px rgba(0,0,0,.3);
    }
    .progress-phase { font-size: 1.05em; font-weight: 700; color: #2d3748; margin-bottom: 22px; }
    .progress-bar-wrap { background: #e2e8f0; border-radius: 99px; height: 14px; overflow: hidden; margin-bottom: 14px; }
    .progress-bar-fill {
      height: 100%; background: linear-gradient(90deg, #667eea, #764ba2);
      border-radius: 99px; width: 0%; transition: width .2s ease;
    }
    .progress-count    { font-size: 1.6em; font-weight: 700; color: #667eea; margin-bottom: 8px; }
    .progress-filename { font-size: .78em; color: #a0aec0; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 320px; margin: 0 auto; }

    /* ── Modal ───────────────────────────────── */
    .modal-overlay {
      display: none; position: fixed; inset: 0;
      background: rgba(0,0,0,.5); z-index: 1000;
      align-items: center; justify-content: center;
    }
    .modal-overlay.active { display: flex; }
    .modal {
      background: #fff; border-radius: 16px; padding: 32px 28px;
      max-width: 420px; width: 92%;
      box-shadow: 0 20px 60px rgba(0,0,0,.3); text-align: center;
    }
    .modal h3 { font-size: 1.25em; margin-bottom: 10px; }
    .modal p  { color: #718096; line-height: 1.65; margin-bottom: 24px; }
    .modal-btns { display: flex; flex-direction: column; gap: 10px; }
    .modal-btns .btn { width: 100%; padding: 13px; }

    /* ── Toast ───────────────────────────────── */
    .toast {
      position: fixed; bottom: 80px; left: 50%;
      transform: translateX(-50%) translateY(120px);
      background: #2d3748; color: #fff;
      padding: 11px 24px; border-radius: 8px; font-size: .9em;
      z-index: 2000; transition: transform .3s ease; pointer-events: none;
      white-space: nowrap;
    }
    .toast.show { transform: translateX(-50%) translateY(0); }

    /* ── Mobile ──────────────────────────────── */
    body.has-images,
    body.has-videos { padding-bottom: 90px; }

    @media (max-width: 640px) {
      .url-row { flex-direction: column; }
      .drag-hint { display: none; }
    }
    @media (max-width: 480px) {
      header { padding: 14px 16px; }
      header h1 { font-size: 1.35em; }
      header p  { font-size: .78em; }
      .container { padding: 10px; }
      .card { padding: 14px 16px; border-radius: 10px; margin-bottom: 12px; }
      .card h2 { font-size: .92em; }
      .image-grid { grid-template-columns: repeat(2, 1fr); gap: 9px; }
      .img-chk { width: 26px; height: 26px; top: 6px; left: 6px; }
      .select-all-label input[type="checkbox"] { width: 24px; height: 24px; }
      .stats-group { gap: 18px; }
      .stat-value  { font-size: 1.6em; }
      .action-bar {
        position: fixed; bottom: 0; left: 0; right: 0;
        border-radius: 0; margin-bottom: 0;
        padding: 12px 16px;
        padding-bottom: calc(12px + env(safe-area-inset-bottom));
        flex-direction: column; gap: 8px; z-index: 100;
        box-shadow: 0 -4px 20px rgba(0,0,0,.12);
      }
      .action-bar .btn { width: 100%; padding: 15px; font-size: 1em; }
      body.has-images,
      body.has-videos { padding-bottom: calc(140px + env(safe-area-inset-bottom)); }
      .toast { bottom: calc(160px + env(safe-area-inset-bottom)); }
      .modal-overlay { align-items: flex-end; }
      .modal {
        border-radius: 20px 20px 0 0; width: 100%; max-width: 100%;
        padding: 28px 20px;
        padding-bottom: calc(24px + env(safe-area-inset-bottom));
      }
      .video-card { flex-wrap: wrap; }
      .video-actions { width: 100%; justify-content: flex-end; }
    }
    @media (hover: none) {
      .img-card:hover { transform: none; box-shadow: 0 2px 6px rgba(0,0,0,.08); }
      .btn:hover { transform: none !important; box-shadow: none !important; }
    }
  </style>
</head>
<body>

<header>
  <h1>🖼️ Web スクレイパー</h1>
  <p>画像・動画をURLから収集・選択・保存できます</p>
</header>

<div class="container">

  <!-- Tabs -->
  <div class="tabs">
    <button class="tab-btn active" data-tab="image" onclick="switchTab('image')">🖼️ 画像スクレイパー</button>
    <button class="tab-btn"        data-tab="video" onclick="switchTab('video')">🎬 動画スクレイパー</button>
  </div>

  <!-- ════════ IMAGE SECTION ════════ -->
  <div id="imageSection">

    <div class="card" id="urlCard">
      <h2>📌 収集するページのURLを入力</h2>
      <div class="url-row">
        <input type="url" class="url-input" id="urlInput"
               placeholder="https://example.com"
               autocomplete="off" inputmode="url" enterkeyhint="go">
        <button class="btn btn-purple" id="scrapeBtn" onclick="startScraping()">
          スクレイピング開始
        </button>
      </div>
      <div class="error-msg" id="errorMsg"></div>
    </div>

    <div class="loading" id="loading">
      <div class="spinner"></div>
      <p>画像を収集中...</p>
    </div>

    <div class="card stats-bar" id="statsBar">
      <div class="stats-group">
        <div class="stat"><div class="stat-value" id="totalCount">0</div><div class="stat-label">収集枚数</div></div>
        <div class="stat"><div class="stat-value" id="selectedCount">0</div><div class="stat-label">選択中</div></div>
        <div class="stat"><div class="stat-value" id="sessionCount">0</div><div class="stat-label">収集回数</div></div>
      </div>
    </div>

    <div class="card controls-bar" id="controlsBar">
      <label class="select-all-label">
        <input type="checkbox" id="selectAllChk" onchange="toggleSelectAll(this)">
        全て選択 / 全て解除
      </label>
      <span class="drag-hint">💡 ドラッグ or Shift+クリックで範囲選択（PC）</span>
      <span class="sel-info" id="selInfo">0枚選択中</span>
    </div>

    <div class="image-grid" id="imageGrid"></div>

    <div class="empty-state" id="emptyState">
      <div class="empty-icon">🔍</div>
      <p>URLを入力してスクレイピングを開始してください</p>
    </div>

    <div class="card action-bar" id="actionBar">
      <button class="btn btn-green" onclick="onSaveClick()">💾 ZIPで保存する</button>
      <button class="btn btn-blue"  onclick="onContinueClick()">➕ 収集を続ける</button>
    </div>

  </div><!-- /imageSection -->

  <!-- ════════ VIDEO SECTION ════════ -->
  <div id="videoSection" style="display:none">

    <div class="card">
      <h2>📌 動画を収集するページのURLを入力</h2>
      <div class="url-row">
        <input type="url" class="url-input" id="videoUrlInput"
               placeholder="https://example.com"
               autocomplete="off" inputmode="url" enterkeyhint="go">
        <button class="btn btn-purple" id="videoScrapeBtn" onclick="startVideoScraping()">
          動画を検索
        </button>
      </div>
      <div class="error-msg" id="videoErrorMsg"></div>
    </div>

    <div class="notice" id="videoNotice">
      ⚠️ HTMLに直接埋め込まれた動画ファイル（MP4・WebM等）のみ収集できます。<br>
      YouTube・Vimeo・ストリーミングサービスの動画は収集できません。
    </div>

    <div class="loading" id="videoLoading">
      <div class="spinner"></div>
      <p>動画を検索中...</p>
    </div>

    <div class="card controls-bar" id="videoControlsBar">
      <label class="select-all-label">
        <input type="checkbox" id="selectAllVideosChk" onchange="toggleSelectAllVideos(this)">
        全て選択 / 全て解除
      </label>
      <span class="sel-info" id="videoSelInfo">0件選択中</span>
    </div>

    <div class="video-list" id="videoList"></div>

    <div class="empty-state" id="videoEmptyState">
      <div class="empty-icon">🎬</div>
      <p>URLを入力して動画を検索してください</p>
    </div>

    <div class="card action-bar" id="videoActionBar">
      <button class="btn btn-green" onclick="downloadSelectedVideos()">⬇️ 選択した動画をダウンロード</button>
      <button class="btn btn-blue"  onclick="addMoreVideos()">➕ 別のページも検索する</button>
    </div>

  </div><!-- /videoSection -->

</div><!-- /container -->

<!-- Progress overlay (shared) -->
<div class="progress-overlay" id="progressOverlay">
  <div class="progress-box">
    <div class="progress-phase"    id="progressPhase">画像をダウンロード中...</div>
    <div class="progress-bar-wrap">
      <div class="progress-bar-fill" id="progressBarFill"></div>
    </div>
    <div class="progress-count"    id="progressCount">0 / 0</div>
    <div class="progress-filename" id="progressFilename"></div>
  </div>
</div>

<!-- Modal (shared) -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal">
    <h3 id="modalTitle"></h3>
    <p  id="modalBody"></p>
    <div class="modal-btns" id="modalBtns"></div>
  </div>
</div>

<!-- Toast (shared) -->
<div class="toast" id="toast"></div>

<script>
  /* ════════ Shared utilities ════════ */

  function esc(str) {
    const d = document.createElement('div');
    d.textContent = str ?? '';
    return d.innerHTML;
  }
  function hostname(url) {
    try { return new URL(url).hostname; } catch { return url; }
  }
  function uid() {
    return `${Date.now()}_${Math.random().toString(36).slice(2, 9)}`;
  }
  function toast(msg, ms = 3500) {
    const el = document.getElementById('toast');
    el.textContent = msg; el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), ms);
  }

  /* ════════ Tab switching ════════ */

  function switchTab(tab) {
    ['image', 'video'].forEach(t => {
      document.getElementById(t + 'Section').style.display = t === tab ? '' : 'none';
    });
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.toggle('active', b.dataset.tab === tab));
    if (tab === 'image') {
      document.body.classList.toggle('has-images', allImages.length > 0);
      document.body.classList.remove('has-videos');
    } else {
      document.body.classList.toggle('has-videos', allVideos.length > 0);
      document.body.classList.remove('has-images');
    }
  }

  /* ════════════════════════════════
     IMAGE SCRAPER
  ════════════════════════════════ */

  let allImages      = [];
  let sessionCount   = 0;
  let isDragging     = false;
  let dragMode       = null;
  let dragStartId    = null;
  let draggedIds     = new Set();
  let lastClickedIdx = -1;

  /* ── Drag: document-level handlers ──────────── */
  document.addEventListener('mousemove', e => {
    if (!isDragging) return;
    const el   = document.elementFromPoint(e.clientX, e.clientY);
    const card = el && el.closest('.img-card');
    if (!card) return;
    const id = card.id.slice(5);
    if (!id || id === dragStartId || draggedIds.has(id)) return;
    draggedIds.add(id);
    toggleById(id, dragMode === 'select');
  });
  document.addEventListener('mouseup', () => {
    if (!isDragging) return;
    isDragging = false; dragMode = null; dragStartId = null; draggedIds.clear();
    document.getElementById('imageGrid').classList.remove('is-dragging');
    document.body.style.userSelect = '';
  });
  document.addEventListener('dragstart', e => e.preventDefault());

  /* ── Boot ─────────────────────────────────── */
  document.getElementById('urlInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') startScraping();
  });

  /* ── Helpers ──────────────────────────────── */
  function showImgError(msg) {
    const el = document.getElementById('errorMsg');
    el.textContent = msg; el.style.display = 'block';
  }
  function hideImgError() { document.getElementById('errorMsg').style.display = 'none'; }
  function setImgLoading(on) {
    document.getElementById('loading').classList.toggle('active', on);
    document.getElementById('scrapeBtn').disabled = on;
  }

  function refreshImgUI() {
    const has = allImages.length > 0;
    document.getElementById('statsBar').style.display    = has ? 'flex'  : 'none';
    document.getElementById('controlsBar').style.display = has ? 'flex'  : 'none';
    document.getElementById('actionBar').style.display   = has ? 'flex'  : 'none';
    document.getElementById('emptyState').style.display  = has ? 'none'  : 'block';
    document.body.classList.toggle('has-images', has);
    updateImgStats();
  }

  function updateImgStats() {
    const total    = allImages.length;
    const selected = allImages.filter(i => i.selected).length;
    document.getElementById('totalCount').textContent    = total;
    document.getElementById('selectedCount').textContent = selected;
    document.getElementById('sessionCount').textContent  = sessionCount;
    document.getElementById('selInfo').textContent       = `${selected}枚選択中`;
    const chk = document.getElementById('selectAllChk');
    if (total === 0)             { chk.indeterminate = false; chk.checked = false; }
    else if (selected === total) { chk.indeterminate = false; chk.checked = true;  }
    else if (selected === 0)     { chk.indeterminate = false; chk.checked = false; }
    else                         { chk.indeterminate = true; }
  }

  function renderGrid() {
    const grid = document.getElementById('imageGrid');
    grid.innerHTML = '';
    let curSession = 0;
    allImages.forEach(img => {
      if (img.session !== curSession) {
        curSession = img.session;
        if (sessionCount > 1) {
          const div = document.createElement('div');
          div.className = 'session-divider';
          div.innerHTML = `収集 #${img.session} <span class="session-tag">${esc(hostname(img.source))}</span>`;
          grid.appendChild(div);
        }
      }
      const card = document.createElement('div');
      card.className = `img-card${img.selected ? ' selected' : ''}`;
      card.id = `card_${img.id}`;

      card.addEventListener('mousedown', e => {
        if (e.button !== 0 || e.target.type === 'checkbox') return;
        e.preventDefault();
        const idx = allImages.findIndex(i => i.id === img.id);
        if (e.shiftKey && lastClickedIdx >= 0 && idx !== lastClickedIdx) {
          const start = Math.min(lastClickedIdx, idx);
          const end   = Math.max(lastClickedIdx, idx);
          const ns    = !img.selected;
          for (let j = start; j <= end; j++) {
            if (allImages[j]) toggleById(allImages[j].id, ns);
          }
          lastClickedIdx = idx;
        } else {
          const ns = !img.selected;
          isDragging = true; dragMode = ns ? 'select' : 'deselect';
          dragStartId = img.id; draggedIds.clear(); draggedIds.add(img.id);
          lastClickedIdx = idx;
          document.getElementById('imageGrid').classList.add('is-dragging');
          document.body.style.userSelect = 'none';
          toggleById(img.id, ns);
        }
      });

      card.innerHTML = `
        <div class="img-thumb">
          <input type="checkbox" class="img-chk" id="chk_${img.id}"
                 ${img.selected ? 'checked' : ''}
                 onclick="event.stopPropagation()"
                 onchange="toggleById('${img.id}', this.checked)">
          <img src="${esc(img.url)}" alt="${esc(img.alt || img.filename)}" loading="lazy"
               onerror="this.replaceWith(Object.assign(document.createElement('div'),
                 {className:'img-error',textContent:'読み込みエラー'}))">
        </div>
        <div class="img-info">
          <div class="img-name" title="${esc(img.filename)}">${esc(img.filename)}</div>
          <div class="img-host" title="${esc(img.source)}">${esc(hostname(img.source))}</div>
        </div>`;
      grid.appendChild(card);
    });
  }

  function toggleById(id, checked) {
    const img = allImages.find(i => i.id === id);
    if (!img) return;
    img.selected = checked ?? !img.selected;
    document.getElementById(`card_${id}`)?.classList.toggle('selected', img.selected);
    const chk = document.getElementById(`chk_${id}`);
    if (chk) chk.checked = img.selected;
    updateImgStats();
  }

  function toggleSelectAll(chk) {
    allImages.forEach(img => {
      img.selected = chk.checked;
      document.getElementById(`card_${img.id}`)?.classList.toggle('selected', chk.checked);
      const c = document.getElementById(`chk_${img.id}`);
      if (c) c.checked = chk.checked;
    });
    updateImgStats();
  }

  async function startScraping() {
    const url = document.getElementById('urlInput').value.trim();
    if (!url) { showImgError('URLを入力してください'); return; }
    hideImgError(); setImgLoading(true);
    try {
      const res  = await fetch('scrape.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({url}),
      });
      const data = await res.json();
      if (!data.success) { showImgError(data.error || 'スクレイピングに失敗しました'); }
      else {
        sessionCount++;
        allImages = [...allImages, ...data.images.map(img => ({...img, id: uid(), session: sessionCount, selected: false}))];
        renderGrid(); refreshImgUI();
        toast(`${data.images.length}枚の画像を収集しました`);
        document.getElementById('urlInput').value = '';
      }
    } catch (err) { showImgError('ネットワークエラー: ' + err.message); }
    setImgLoading(false);
  }

  /* ── Progress ─────────────────────────────── */
  function showProgress(phase, current, total) {
    document.getElementById('progressPhase').textContent    = phase;
    document.getElementById('progressBarFill').style.width  = '0%';
    document.getElementById('progressCount').textContent    = `${current} / ${total}`;
    document.getElementById('progressFilename').textContent = '';
    document.getElementById('progressOverlay').classList.add('active');
  }
  function updateProgress(current, total, filename) {
    const pct = total > 0 ? Math.round((current / total) * 100) : 0;
    document.getElementById('progressBarFill').style.width  = pct + '%';
    document.getElementById('progressCount').textContent    = `${current} / ${total} (${pct}%)`;
    document.getElementById('progressFilename').textContent = filename || '';
  }
  function hideProgress() {
    document.getElementById('progressOverlay').classList.remove('active');
  }

  /* ── ZIP download ─────────────────────────── */
  async function downloadZip() {
    const selected = allImages.filter(i => i.selected);
    if (!selected.length) { toast('画像を1枚以上選択してください'); return false; }
    const total  = selected.length;
    const padLen = String(total).length;
    showProgress('画像をダウンロード中...', 0, total);
    const zip = new JSZip(); const nameCounts = {}; let okCount = 0;
    for (let i = 0; i < selected.length; i++) {
      const img = selected[i];
      updateProgress(i, total, img.filename);
      try {
        const res = await fetch('proxy.php?url=' + encodeURIComponent(img.url));
        if (!res.ok) continue;
        const blob = await res.blob();
        let fname = img.filename || 'image';
        const dot = fname.lastIndexOf('.');
        let base  = dot >= 0 ? fname.slice(0, dot)  : fname;
        let ext   = dot >= 0 ? fname.slice(dot + 1) : 'jpg';
        const key = `${base}.${ext}`;
        if (key in nameCounts) { nameCounts[key]++; fname = `${base}_${nameCounts[key]}.${ext}`; }
        else { nameCounts[key] = 0; fname = key; }
        zip.file(`${String(i + 1).padStart(padLen, '0')}_${fname}`, blob);
        okCount++;
      } catch (e) { /* skip */ }
    }
    document.getElementById('progressPhase').textContent    = 'ZIPファイルを生成中...';
    document.getElementById('progressFilename').textContent = '';
    try {
      const zipBlob = await zip.generateAsync({type: 'blob'}, meta => {
        const p = meta.percent.toFixed(0);
        document.getElementById('progressBarFill').style.width = p + '%';
        document.getElementById('progressCount').textContent   = `ZIP生成: ${p}%`;
      });
      Object.assign(document.createElement('a'), {
        href: URL.createObjectURL(zipBlob), download: 'scraped_images.zip'
      }).click();
      hideProgress();
      toast(`✅ ${okCount}枚の画像をZIPで保存しました`);
      return true;
    } catch (e) { hideProgress(); toast('❌ ZIPの生成に失敗しました'); return false; }
  }

  /* ── Modal ────────────────────────────────── */
  function showModal(title, body, buttons) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalBody').innerHTML    = body;
    const wrap = document.getElementById('modalBtns');
    wrap.innerHTML = '';
    buttons.forEach(({text, cls, action}) => {
      const b = document.createElement('button');
      b.className = `btn ${cls}`; b.textContent = text;
      b.onclick   = () => { closeModal(); action(); };
      wrap.appendChild(b);
    });
    document.getElementById('modalOverlay').classList.add('active');
  }
  function closeModal(e) {
    if (e && e.target !== document.getElementById('modalOverlay')) return;
    document.getElementById('modalOverlay').classList.remove('active');
  }

  function onSaveClick() {
    const cnt = allImages.filter(i => i.selected).length;
    if (!cnt) { toast('画像を1枚以上選択してください'); return; }
    showModal('保存しますか？',
      `選択中の <strong>${cnt}枚</strong> の画像をZIPで保存します。<br>保存後の動作を選んでください。`,
      [
        { text: '💾 保存して終了する',    cls: 'btn-green', action: async () => { await downloadZip(); } },
        { text: '💾 保存して収集を続ける', cls: 'btn-blue',  action: async () => {
          const ok = await downloadZip();
          if (ok) { allImages.forEach(i => i.selected = false); renderGrid(); updateImgStats(); scrollToImgUrl(); }
        }},
        { text: 'キャンセル', cls: 'btn-gray', action: () => {} },
      ]
    );
  }
  function onContinueClick() {
    const cnt = allImages.filter(i => i.selected).length;
    if (cnt > 0) {
      showModal('収集を続ける',
        `現在 <strong>${cnt}枚</strong> の画像が選択されています。<br>保存してから続けますか？`,
        [
          { text: '💾 保存して続ける',    cls: 'btn-green', action: async () => {
            const ok = await downloadZip();
            if (ok) { allImages.forEach(i => i.selected = false); renderGrid(); updateImgStats(); scrollToImgUrl(); }
          }},
          { text: '➕ 保存せずに続ける', cls: 'btn-blue',  action: () => scrollToImgUrl() },
          { text: 'キャンセル',          cls: 'btn-gray',  action: () => {} },
        ]
      );
    } else { scrollToImgUrl(); }
  }
  function scrollToImgUrl() {
    document.getElementById('urlCard').scrollIntoView({behavior: 'smooth'});
    setTimeout(() => document.getElementById('urlInput').focus(), 400);
  }

  /* ════════════════════════════════
     VIDEO SCRAPER
  ════════════════════════════════ */

  let allVideos = [];

  document.getElementById('videoUrlInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') startVideoScraping();
  });

  function showVideoError(msg) {
    const el = document.getElementById('videoErrorMsg');
    el.textContent = msg; el.style.display = 'block';
  }
  function hideVideoError() { document.getElementById('videoErrorMsg').style.display = 'none'; }
  function setVideoLoading(on) {
    document.getElementById('videoLoading').classList.toggle('active', on);
    document.getElementById('videoScrapeBtn').disabled = on;
  }

  function refreshVideoUI() {
    const has = allVideos.length > 0;
    document.getElementById('videoControlsBar').style.display = has ? 'flex' : 'none';
    document.getElementById('videoActionBar').style.display   = has ? 'flex' : 'none';
    document.getElementById('videoEmptyState').style.display  = has ? 'none' : 'block';
    document.getElementById('videoNotice').style.display      = 'block';
    document.body.classList.toggle('has-videos', has);
    updateVideoStats();
  }

  function updateVideoStats() {
    const total    = allVideos.length;
    const selected = allVideos.filter(v => v.selected).length;
    document.getElementById('videoSelInfo').textContent = `${selected}件選択中`;
    const chk = document.getElementById('selectAllVideosChk');
    if (total === 0)             { chk.indeterminate = false; chk.checked = false; }
    else if (selected === total) { chk.indeterminate = false; chk.checked = true;  }
    else if (selected === 0)     { chk.indeterminate = false; chk.checked = false; }
    else                         { chk.indeterminate = true; }
  }

  function renderVideoList() {
    const list = document.getElementById('videoList');
    list.innerHTML = '';
    allVideos.forEach(video => {
      const card = document.createElement('div');
      card.className = `video-card${video.selected ? ' selected' : ''}`;
      card.id = `vcard_${video.id}`;
      card.addEventListener('click', e => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'BUTTON') return;
        toggleVideoById(video.id, null);
      });
      card.innerHTML = `
        <div class="video-icon-wrap">🎬</div>
        <div class="video-details">
          <div class="video-name" title="${esc(video.filename)}">${esc(video.filename)}</div>
          <div class="video-meta">
            <span class="video-badge">${esc(video.ext)}</span>
            <span class="video-src">${esc(hostname(video.source))}</span>
          </div>
        </div>
        <div class="video-actions">
          <input type="checkbox" class="video-chk" id="vchk_${video.id}"
                 ${video.selected ? 'checked' : ''}
                 onchange="toggleVideoById('${video.id}', this.checked)">
          <button class="btn btn-blue btn-sm"
                  onclick="downloadSingleVideo('${esc(video.url)}','${esc(video.filename)}')">
            ⬇️ DL
          </button>
        </div>`;
      list.appendChild(card);
    });
  }

  function toggleVideoById(id, checked) {
    const video = allVideos.find(v => v.id === id);
    if (!video) return;
    video.selected = checked ?? !video.selected;
    document.getElementById(`vcard_${id}`)?.classList.toggle('selected', video.selected);
    const chk = document.getElementById(`vchk_${id}`);
    if (chk) chk.checked = video.selected;
    updateVideoStats();
  }

  function toggleSelectAllVideos(chk) {
    allVideos.forEach(v => {
      v.selected = chk.checked;
      document.getElementById(`vcard_${v.id}`)?.classList.toggle('selected', chk.checked);
      const c = document.getElementById(`vchk_${v.id}`);
      if (c) c.checked = chk.checked;
    });
    updateVideoStats();
  }

  async function startVideoScraping() {
    const url = document.getElementById('videoUrlInput').value.trim();
    if (!url) { showVideoError('URLを入力してください'); return; }
    hideVideoError(); setVideoLoading(true);
    try {
      const res  = await fetch('video_scrape.php', {
        method: 'POST', headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({url}),
      });
      const data = await res.json();
      if (!data.success) { showVideoError(data.error || 'スクレイピングに失敗しました'); }
      else {
        const newVids = data.videos.map(v => ({...v, id: uid(), selected: false}));
        allVideos = [...allVideos, ...newVids];
        renderVideoList(); refreshVideoUI();
        toast(data.count > 0
          ? `${data.count}件の動画を見つけました`
          : '動画が見つかりませんでした（直接埋め込みの動画がないページの可能性があります）'
        );
        document.getElementById('videoUrlInput').value = '';
      }
    } catch (err) { showVideoError('ネットワークエラー: ' + err.message); }
    setVideoLoading(false);
  }

  function formatBytes(b) {
    if (b < 1024)        return b + ' B';
    if (b < 1024 * 1024) return (b / 1024).toFixed(1) + ' KB';
    return (b / 1024 / 1024).toFixed(1) + ' MB';
  }

  async function downloadSingleVideo(url, filename, label) {
    const proxyUrl = 'video_proxy.php?url=' + encodeURIComponent(url)
                   + '&filename=' + encodeURIComponent(filename);

    // Show progress overlay
    document.getElementById('progressPhase').textContent    = label || `${filename} をダウンロード中...`;
    document.getElementById('progressBarFill').style.width  = '0%';
    document.getElementById('progressCount').textContent    = '接続中...';
    document.getElementById('progressFilename').textContent = filename;
    document.getElementById('progressOverlay').classList.add('active');

    try {
      const res = await fetch(proxyUrl);
      if (!res.ok) throw new Error(`HTTP ${res.status}`);

      const contentLength = res.headers.get('content-length');
      const total   = contentLength ? parseInt(contentLength, 10) : 0;
      const reader  = res.body.getReader();
      const chunks  = [];
      let   received = 0;

      while (true) {
        const {done, value} = await reader.read();
        if (done) break;
        chunks.push(value);
        received += value.length;

        if (total > 0) {
          const pct = Math.round((received / total) * 100);
          document.getElementById('progressBarFill').style.width = pct + '%';
          document.getElementById('progressCount').textContent   =
            `${formatBytes(received)} / ${formatBytes(total)} (${pct}%)`;
        } else {
          // Content-Length unknown — show bytes received with moving bar
          document.getElementById('progressBarFill').style.width = '100%';
          document.getElementById('progressCount').textContent   =
            `受信中: ${formatBytes(received)}`;
        }
      }

      // Assemble blob and trigger download
      const blob    = new Blob(chunks, {type: res.headers.get('content-type') || 'video/mp4'});
      const blobUrl = URL.createObjectURL(blob);
      const a = Object.assign(document.createElement('a'), {href: blobUrl, download: filename});
      document.body.appendChild(a); a.click(); document.body.removeChild(a);
      URL.revokeObjectURL(blobUrl);

      hideProgress();
      return true;
    } catch (e) {
      hideProgress();
      toast('❌ ダウンロード失敗: ' + e.message);
      return false;
    }
  }

  async function downloadSelectedVideos() {
    const selected = allVideos.filter(v => v.selected);
    if (!selected.length) { toast('動画を1件以上選択してください'); return; }
    const total = selected.length;
    for (let i = 0; i < selected.length; i++) {
      const video = selected[i];
      const label = total > 1
        ? `動画 ${i + 1} / ${total} をダウンロード中...`
        : `${video.filename} をダウンロード中...`;
      const ok = await downloadSingleVideo(video.url, video.filename, label);
      if (!ok) break; // stop on error
    }
    if (selected.length > 1) toast(`✅ ${selected.length}件の動画のダウンロードが完了しました`);
  }

  function addMoreVideos() {
    document.querySelector('#videoSection .card').scrollIntoView({behavior: 'smooth'});
    setTimeout(() => document.getElementById('videoUrlInput').focus(), 400);
  }
</script>
</body>
</html>
