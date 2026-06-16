# Web スクレイパー 作業ログ

**作業日**: 2026-06-16  
**リポジトリ**: wasse77-art/claude-codex-test  
**ブランチ**: `claude/web-image-scraper-select-jus7cj`  
**サーバー**: Xserver スタンダードプラン（PHP / 共有ホスティング）

---

## プロジェクト概要

URLを入力してWebページ内の**画像・動画を収集・選択・保存**できるPHPアプリ。  
Xserver の共有ホスティング上で動作。スマホ・PCどちらからもアクセス可能。

---

## ファイル構成

```
php/
├── index.php          ← メインUI（フロントエンド、全タブ共通）
├── scrape.php         ← 画像スクレイピングバックエンド
├── proxy.php          ← 画像プロキシ（CORS回避、JSZip用）
├── download.php       ← サーバー側ZIP生成（レガシー、現在は未使用）
├── video_scrape.php   ← 動画スクレイピングバックエンド
├── video_proxy.php    ← 動画ストリーミングプロキシ（ダウンロード用）
└── video_analyze.php  ← 動画形式分析バックエンド（NEW）
```

### Xserver への配置場所

```
/home/アカウント名/ドメイン名/public_html/scraper/
```

上記フォルダに php/ 内の全ファイルを配置する。  
アクセスURL例: `https://yourdomain.com/scraper/`

---

## 機能一覧（タブ構成）

### 🖼️ タブ1: 画像スクレイパー

| 機能 | 説明 |
|------|------|
| URL入力・収集 | PHPでHTMLを取得しDOMXPathで画像URL抽出 |
| 画像グリッド表示 | 収集した画像をサムネイルカードで表示 |
| 個別選択 | タップ/クリックで選択・解除 |
| ドラッグ選択（PC） | マウスドラッグで範囲一括選択/解除 |
| スワイプ選択（スマホ） | 指をスライドして範囲一括選択/解除 |
| Shift+クリック（PC） | 範囲選択（アンカーから連続選択） |
| 全選択/全解除 | チェックボックスで一括操作 |
| ズームスライダー | 9段階でカードサイズを変更（PC・スマホ共通） |
| ZIPダウンロード | 選択画像をJSZipでブラウザ側ZIP生成・進捗表示 |
| ZIPファイル名 | 最初に収集したページのタイトルをファイル名に使用 |
| ZIP内ファイル順序 | ゼロパディング連番プレフィックスで収集順を維持 |
| 収集を続ける | 別URLから追加収集してコレクションに追記 |
| セッション区切り | 複数URL収集時にセッション区切りを表示 |
| リセットボタン | 収集済み画像を全削除（確認モーダルあり） |

### 🎬 タブ2: 動画スクレイパー

| 機能 | 説明 |
|------|------|
| 動画URL収集 | HTMLタグ・正規表現でMP4等の直接埋め込み動画を検出 |
| 対応形式 | mp4, webm, ogg, mov, avi, mkv, m4v, flv, wmv, 3gp |
| 個別ダウンロード | video_proxy.php 経由でストリーミング受信 |
| 進捗表示 | ReadableStream + getReader() でバイト数/% 表示 |
| 一括ダウンロード | 選択した動画を順番にダウンロード |
| 別ページも検索 | 複数URLから動画を追加収集 |

### 🔍 タブ3: 動画分析（NEW）

| 機能 | 説明 |
|------|------|
| 動画形式検出 | PHPでHTMLを取得・解析して動画種別を判定 |
| 対応種別 | HTML5直接埋め込み / HLS / DASH / YouTube / Vimeo / Dailymotion / Twitch / ニコニコ / Bilibili / Twitter(X) / プレーヤーライブラリ |
| HLS暗号化判定 | .m3u8マニフェストを取得して `#EXT-X-KEY` で判定 |
| ステータス色分け | 🟢緑=直接DL可 / 🟡黄=外部ツール必要 / 🔴赤=DL困難 |
| ダウンロード案内 | 各種別ごとにyt-dlp / ffmpeg 等の具体的な手順を表示 |

---

## 今日の主な変更内容

### 1. 動画分析タブ追加（`video_analyze.php` 新規作成 + `index.php` 更新）

- 11種類の動画形式を検出するバックエンドを新規作成
- フロントエンドに「🔍 動画分析」タブを追加
- 色分けカードで結果を表示

### 2. ZIPファイル名を最初のページタイトルに変更

```javascript
// 最初のスクレイピングのpage_titleを保存
let firstPageTitle = '';
// sessionCount === 1 のときに記録
if (sessionCount === 1 && data.page_title) firstPageTitle = data.page_title;

// ZIPダウンロード時に使用
const rawTitle = firstPageTitle || 'scraped_images';
const zipName  = rawTitle.replace(/[\\/:*?"<>|]/g, '_').replace(/\s+/g, '_')
                         .replace(/_+/g, '_').replace(/^_|_$/g, '').slice(0, 80) + '.zip';
```

### 3. スマホ スワイプ複数選択

`touchmove` + `elementFromPoint()` で指が通ったカードを順次選択。  
縦スクロールとの区別: `Math.abs(dy) > Math.abs(dx) * 1.8` なら通常スクロールに戻す。

```javascript
grid.addEventListener('touchmove', e => {
  if (!touchOnCard || !e.touches.length) return;
  const touch = e.touches[0];
  if (!touchDragging) {
    const dx = touch.clientX - touchStartX;
    const dy = touch.clientY - touchStartY;
    if (Math.sqrt(dx*dx + dy*dy) < 12) return;
    if (Math.abs(dy) > Math.abs(dx) * 1.8 && Math.abs(dy) > 15) {
      touchOnCard = false; return; // 縦スクロールとして扱う
    }
    // ドラッグ選択開始
    ...
  }
  e.preventDefault();
  // elementFromPoint でカードを検出して選択
}, {passive: false});
```

### 4. 画像グリッド ズームスライダー（PC・スマホ共通）

- 9段階スライダー（カード幅 300px〜55px）
- `auto-fill` + `minmax(Xpx, 1fr)` で列数を自動計算
- デフォルト: スマホ=ステップ4(170px≈2列), PC=ステップ2(250px≈5列)
- 設定は `localStorage` に保存

```javascript
const ZOOM_STEPS  = [300, 250, 200, 170, 140, 115, 90, 70, 55];
const ZOOM_LABELS = ['極大','大','やや大','標準','やや小','小','より小','極小','最小'];

function setZoom(step) {
  const px = ZOOM_STEPS[step - 1];
  grid.style.gridTemplateColumns = `repeat(auto-fill, minmax(${px}px, 1fr))`;
  localStorage.setItem('imgGridZoom', step);
}
```

### 5. リセットボタン追加

アクションバーに「🗑️ リセット」ボタンを追加。確認モーダル経由で全画像・セッションをクリア。

---

## 技術スタック

| 項目 | 内容 |
|------|------|
| バックエンド | PHP + curl + DOMDocument + DOMXPath + ZipArchive |
| フロントエンド | Vanilla JS + JSZip CDN |
| 画像ZIP | JSZip（クライアント側生成）+ proxy.php（CORS回避） |
| 動画DL | video_proxy.php（CURLOPT_WRITEFUNCTION で128KBストリーム） |
| スタイル | CSS Grid + Flexbox、レスポンシブ（モバイルファースト） |

---

## 今後の検討事項（PC環境での開発候補）

### yt-dlp デスクトップアプリ
- YouTube・ニコニコ等1000+サイト対応の動画DLツール
- Python + CustomTkinter（GUI） + PyInstaller（.exe/.app 化）で作成可能
- `import yt_dlp` でPythonライブラリとして内部呼び出し
- ダウンロード進捗はコールバックで取得可能
- Mac用 `.app` / Windows用 `.exe` に変換して配布可能

```bash
# CLIでの基本使用例
pip install yt-dlp
yt-dlp https://www.youtube.com/watch?v=xxxxx
yt-dlp --cookies-from-browser chrome https://...  # ログイン済みコンテンツ
```

---

## Xserver アップロード手順

1. FTP/SFTPクライアント（FileZilla等）でXserverに接続
2. `public_html/scraper/`（または任意のフォルダ）に以下をアップロード:
   - `index.php`
   - `scrape.php`
   - `proxy.php`
   - `video_scrape.php`
   - `video_proxy.php`
   - `video_analyze.php`
3. ブラウザで `https://yourdomain.com/scraper/` にアクセスして動作確認

> **注意**: `download.php` は現在フロントエンドから呼ばれていないが残しておいても問題なし。

---

## コミット履歴（今日分）

| コミット | 内容 |
|---------|------|
| `ea14fc7` | 動画分析タブ追加（video_analyze.php 新規 + index.php UI） |
| `6c7b886` | ZIPファイル名を最初のページタイトルに変更 |
| `5f89508` | スマホ スワイプ複数選択追加 |
| `87e6f91` | 列数ピッカー（2/3/4/5ボタン）＋リセットボタン追加 |
| `2973485` | PCにズームスライダー追加（スマホは引き続きボタン） |
| `c2ff376` | PC・スマホ共通ズームスライダーに統一 |
