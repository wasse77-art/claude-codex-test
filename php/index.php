<!DOCTYPE html>
<html lang="ja">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
  <title>Web画像スクレイパー</title>
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

    /* ── Cards ──────────────────────────────── */
    .card {
      background: #fff;
      border-radius: 12px;
      padding: 22px 26px;
      margin-bottom: 18px;
      box-shadow: 0 2px 8px rgba(0,0,0,.07);
    }
    .card h2 { font-size: 1em; color: #4a5568; margin-bottom: 12px; }

    /* ── URL input ───────────────────────────── */
    .url-row { display: flex; gap: 10px; }
    .url-input {
      flex: 1;
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 8px;
      font-size: 1em;
      outline: none;
      transition: border-color .2s;
    }
    .url-input:focus { border-color: #667eea; }

    /* ── Buttons ─────────────────────────────── */
    .btn {
      padding: 12px 22px;
      border: none;
      border-radius: 8px;
      font-size: .95em;
      font-weight: 600;
      cursor: pointer;
      transition: transform .15s, box-shadow .15s;
      white-space: nowrap;
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

    /* ── Error banner ────────────────────────── */
    .error-msg {
      display: none;
      margin-top: 10px;
      padding: 11px 15px;
      background: #fff5f5;
      border: 1px solid #feb2b2;
      border-radius: 8px;
      color: #c53030;
      font-size: .9em;
    }

    /* ── Stats bar ───────────────────────────── */
    .stats-bar { display: none; align-items: center; justify-content: space-between; }
    .stats-group { display: flex; gap: 28px; }
    .stat { text-align: center; }
    .stat-value { font-size: 2em; font-weight: 700; color: #667eea; line-height: 1; }
    .stat-label { font-size: .75em; color: #718096; margin-top: 3px; }

    /* ── Controls ────────────────────────────── */
    .controls-bar { display: none; align-items: center; justify-content: space-between; }
    .select-all-label {
      display: flex; align-items: center; gap: 8px;
      font-weight: 600; color: #4a5568; cursor: pointer; user-select: none;
    }
    .select-all-label input[type="checkbox"] { width: 20px; height: 20px; cursor: pointer; accent-color: #667eea; }
    .sel-info { font-size: .9em; color: #718096; }

    /* ── Image grid ──────────────────────────── */
    .image-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(170px, 1fr));
      gap: 14px;
      margin-bottom: 18px;
    }
    .img-card {
      background: #fff; border-radius: 10px; overflow: hidden;
      box-shadow: 0 2px 6px rgba(0,0,0,.08);
      cursor: pointer;
      transition: transform .18s, box-shadow .18s, outline .1s;
      outline: 3px solid transparent;
    }
    .img-card:hover { transform: translateY(-2px); box-shadow: 0 6px 14px rgba(0,0,0,.13); }
    .img-card.selected { outline-color: #667eea; }
    .img-thumb {
      position: relative; width: 100%; padding-bottom: 75%; background: #f7fafc;
    }
    .img-thumb img {
      position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover;
    }
    .img-thumb .img-error {
      position: absolute; inset: 0;
      display: flex; align-items: center; justify-content: center;
      color: #a0aec0; font-size: .75em; text-align: center; padding: 8px;
    }
    .img-chk {
      position: absolute; top: 7px; left: 7px;
      width: 20px; height: 20px;
      cursor: pointer; accent-color: #667eea; z-index: 1;
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
    }
    .toast.show { transform: translateX(-50%) translateY(0); }

    /* ── Mobile ──────────────────────────────── */
    body.has-images { padding-bottom: 90px; }

    @media (max-width: 640px) {
      .url-row { flex-direction: column; }
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
        flex-direction: column; gap: 8px;
        z-index: 100;
        box-shadow: 0 -4px 20px rgba(0,0,0,.12);
      }
      .action-bar .btn { width: 100%; padding: 15px; font-size: 1em; }
      body.has-images { padding-bottom: calc(140px + env(safe-area-inset-bottom)); }

      .toast { bottom: calc(160px + env(safe-area-inset-bottom)); }

      .modal-overlay { align-items: flex-end; }
      .modal {
        border-radius: 20px 20px 0 0; width: 100%; max-width: 100%;
        padding: 28px 20px;
        padding-bottom: calc(24px + env(safe-area-inset-bottom));
      }
    }

    @media (hover: none) {
      .img-card:hover { transform: none; box-shadow: 0 2px 6px rgba(0,0,0,.08); }
      .btn:hover { transform: none !important; box-shadow: none !important; }
    }
  </style>
</head>
<body>

<header>
  <h1>🖼️ Web画像スクレイパー</h1>
  <p>URLを指定してWebページの画像を収集・選択・ZIP保存できます</p>
</header>

<div class="container">

  <!-- URL input -->
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

  <!-- Loading -->
  <div class="loading" id="loading">
    <div class="spinner"></div>
    <p>画像を収集中...</p>
  </div>

  <!-- Stats -->
  <div class="card stats-bar" id="statsBar">
    <div class="stats-group">
      <div class="stat">
        <div class="stat-value" id="totalCount">0</div>
        <div class="stat-label">収集枚数</div>
      </div>
      <div class="stat">
        <div class="stat-value" id="selectedCount">0</div>
        <div class="stat-label">選択中</div>
      </div>
      <div class="stat">
        <div class="stat-value" id="sessionCount">0</div>
        <div class="stat-label">収集回数</div>
      </div>
    </div>
  </div>

  <!-- Controls -->
  <div class="card controls-bar" id="controlsBar">
    <label class="select-all-label">
      <input type="checkbox" id="selectAllChk" onchange="toggleSelectAll(this)">
      全て選択 / 全て解除
    </label>
    <span class="sel-info" id="selInfo">0枚選択中</span>
  </div>

  <!-- Image grid -->
  <div class="image-grid" id="imageGrid"></div>

  <!-- Empty state -->
  <div class="empty-state" id="emptyState">
    <div class="empty-icon">🔍</div>
    <p>URLを入力してスクレイピングを開始してください</p>
  </div>

  <!-- Action bar -->
  <div class="card action-bar" id="actionBar">
    <button class="btn btn-green" onclick="onSaveClick()">💾 ZIPで保存する</button>
    <button class="btn btn-blue"  onclick="onContinueClick()">➕ 収集を続ける</button>
  </div>

</div>

<!-- Modal -->
<div class="modal-overlay" id="modalOverlay" onclick="closeModal(event)">
  <div class="modal">
    <h3 id="modalTitle"></h3>
    <p  id="modalBody"></p>
    <div class="modal-btns" id="modalBtns"></div>
  </div>
</div>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
  /* ─── State ──────────────────────────────────────────────── */
  let allImages    = [];
  let sessionCount = 0;

  /* ─── Boot ───────────────────────────────────────────────── */
  document.getElementById('urlInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') startScraping();
  });

  /* ─── Utilities ──────────────────────────────────────────── */
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

  function toast(msg, ms = 3000) {
    const el = document.getElementById('toast');
    el.textContent = msg;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), ms);
  }

  function showError(msg) {
    const el = document.getElementById('errorMsg');
    el.textContent = msg;
    el.style.display = 'block';
  }
  function hideError() { document.getElementById('errorMsg').style.display = 'none'; }

  function setLoading(on) {
    document.getElementById('loading').classList.toggle('active', on);
    document.getElementById('scrapeBtn').disabled = on;
  }

  /* ─── UI visibility ──────────────────────────────────────── */
  function refreshUI() {
    const has = allImages.length > 0;
    document.getElementById('statsBar').style.display    = has ? 'flex'  : 'none';
    document.getElementById('controlsBar').style.display = has ? 'flex'  : 'none';
    document.getElementById('actionBar').style.display   = has ? 'flex'  : 'none';
    document.getElementById('emptyState').style.display  = has ? 'none'  : 'block';
    document.body.classList.toggle('has-images', has);
    updateStats();
  }

  function updateStats() {
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

  /* ─── Render grid ────────────────────────────────────────── */
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
          div.innerHTML = `収集 #${img.session}
            <span class="session-tag">${esc(hostname(img.source))}</span>`;
          grid.appendChild(div);
        }
      }

      const card = document.createElement('div');
      card.className = `img-card${img.selected ? ' selected' : ''}`;
      card.id = `card_${img.id}`;
      card.addEventListener('click', e => {
        if (e.target.type !== 'checkbox') toggleById(img.id, null);
      });

      card.innerHTML = `
        <div class="img-thumb">
          <input type="checkbox" class="img-chk" id="chk_${img.id}"
                 ${img.selected ? 'checked' : ''}
                 onclick="event.stopPropagation()"
                 onchange="toggleById('${img.id}', this.checked)">
          <img src="${esc(img.url)}"
               alt="${esc(img.alt || img.filename)}"
               loading="lazy"
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

  /* ─── Selection helpers ──────────────────────────────────── */
  function toggleById(id, checked) {
    const img = allImages.find(i => i.id === id);
    if (!img) return;
    img.selected = checked ?? !img.selected;
    document.getElementById(`card_${id}`)?.classList.toggle('selected', img.selected);
    const chk = document.getElementById(`chk_${id}`);
    if (chk) chk.checked = img.selected;
    updateStats();
  }

  function toggleSelectAll(chk) {
    allImages.forEach(img => {
      img.selected = chk.checked;
      document.getElementById(`card_${img.id}`)?.classList.toggle('selected', chk.checked);
      const c = document.getElementById(`chk_${img.id}`);
      if (c) c.checked = chk.checked;
    });
    updateStats();
  }

  /* ─── Scraping ───────────────────────────────────────────── */
  async function startScraping() {
    const url = document.getElementById('urlInput').value.trim();
    if (!url) { showError('URLを入力してください'); return; }

    hideError();
    setLoading(true);

    try {
      const res  = await fetch('scrape.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({url}),
      });
      const data = await res.json();

      if (!data.success) {
        showError(data.error || 'スクレイピングに失敗しました');
        setLoading(false);
        return;
      }

      sessionCount++;
      const newImgs = data.images.map(img => ({
        ...img, id: uid(), session: sessionCount, selected: false,
      }));
      allImages = [...allImages, ...newImgs];

      renderGrid();
      refreshUI();
      toast(`${data.images.length}枚の画像を収集しました`);
      document.getElementById('urlInput').value = '';

    } catch (err) {
      showError('ネットワークエラー: ' + err.message);
    }

    setLoading(false);
  }

  /* ─── ZIP download ───────────────────────────────────────── */
  async function downloadZip() {
    const selected = allImages.filter(i => i.selected);
    if (!selected.length) { toast('画像を1枚以上選択してください'); return false; }

    toast('ZIPファイルを作成中…', 8000);

    try {
      const res = await fetch('download.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({images: selected}),
      });
      if (!res.ok) throw new Error('サーバーエラー');

      const blob = await res.blob();
      const a    = Object.assign(document.createElement('a'), {
        href:     URL.createObjectURL(blob),
        download: 'scraped_images.zip',
      });
      a.click();
      URL.revokeObjectURL(a.href);
      toast(`✅ ${selected.length}枚の画像をZIPで保存しました`);
      return true;
    } catch (err) {
      toast('❌ エラー: ' + err.message);
      return false;
    }
  }

  /* ─── Modal ──────────────────────────────────────────────── */
  function showModal(title, body, buttons) {
    document.getElementById('modalTitle').textContent = title;
    document.getElementById('modalBody').innerHTML    = body;
    const wrap = document.getElementById('modalBtns');
    wrap.innerHTML = '';
    buttons.forEach(({text, cls, action}) => {
      const b = document.createElement('button');
      b.className   = `btn ${cls}`;
      b.textContent = text;
      b.onclick     = () => { closeModal(); action(); };
      wrap.appendChild(b);
    });
    document.getElementById('modalOverlay').classList.add('active');
  }

  function closeModal(e) {
    if (e && e.target !== document.getElementById('modalOverlay')) return;
    document.getElementById('modalOverlay').classList.remove('active');
  }

  /* ─── Action: Save ───────────────────────────────────────── */
  function onSaveClick() {
    const cnt = allImages.filter(i => i.selected).length;
    if (!cnt) { toast('画像を1枚以上選択してください'); return; }

    showModal(
      '保存しますか？',
      `選択中の <strong>${cnt}枚</strong> の画像をZIPで保存します。<br>保存後の動作を選んでください。`,
      [
        {
          text: '💾 保存して終了する',
          cls:  'btn-green',
          action: async () => { await downloadZip(); },
        },
        {
          text: '💾 保存して収集を続ける',
          cls:  'btn-blue',
          action: async () => {
            const ok = await downloadZip();
            if (ok) {
              allImages.forEach(i => i.selected = false);
              renderGrid(); updateStats(); scrollToUrl();
            }
          },
        },
        { text: 'キャンセル', cls: 'btn-gray', action: () => {} },
      ]
    );
  }

  /* ─── Action: Continue ───────────────────────────────────── */
  function onContinueClick() {
    const cnt = allImages.filter(i => i.selected).length;

    if (cnt > 0) {
      showModal(
        '収集を続ける',
        `現在 <strong>${cnt}枚</strong> の画像が選択されています。<br>保存してから続けますか？`,
        [
          {
            text: '💾 保存して続ける',
            cls:  'btn-green',
            action: async () => {
              const ok = await downloadZip();
              if (ok) {
                allImages.forEach(i => i.selected = false);
                renderGrid(); updateStats(); scrollToUrl();
              }
            },
          },
          {
            text: '➕ 保存せずに続ける',
            cls:  'btn-blue',
            action: () => scrollToUrl(),
          },
          { text: 'キャンセル', cls: 'btn-gray', action: () => {} },
        ]
      );
    } else {
      scrollToUrl();
    }
  }

  function scrollToUrl() {
    document.getElementById('urlCard').scrollIntoView({behavior: 'smooth'});
    setTimeout(() => document.getElementById('urlInput').focus(), 400);
  }
</script>
</body>
</html>
