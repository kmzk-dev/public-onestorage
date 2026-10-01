<?php
// functions/pdf_preview.php: PDFストリーミングプレビュー画面（縦スクロール・仮想遅延レンダリング対応）
if (!defined('ONESTORAGE_RUNNING')) {
    die('Access Denied: Invalid execution context.');
}

global $encryption_enabled;

$file_path_raw = $_GET['path'] ?? '';
// INBOX対応
if (defined('INBOX_DIR_NAME') && str_starts_with($file_path_raw, 'inbox/')) {
    $internal = INBOX_DIR_NAME . '/' . substr($file_path_raw, 6);
    $file_path = realpath(DATA_ROOT . '/' . $internal);
} else {
    $file_path = realpath(DATA_ROOT . '/' . $file_path_raw);
}

// バリデーション
if (!$file_path || strpos($file_path, DATA_ROOT) !== 0 || is_dir($file_path)
    || strtolower(pathinfo($file_path, PATHINFO_EXTENSION)) !== 'pdf') {
    http_response_code(404);
    die('エラー: 指定されたPDFファイルが見つかりません。');
}

$cache_dir = DATA_ROOT . DIRECTORY_SEPARATOR . PREVIEW_CACHE_DIR_NAME;
if (!is_dir($cache_dir)) {
    @mkdir($cache_dir, 0700, true);
}
ensure_dir_protection($cache_dir);

// 30分（1800秒）以上経過した古い一時ファイル（.pdf と .json）の定期クリーンアップ
$now = time();
$old_files = array_merge(
    glob($cache_dir . DIRECTORY_SEPARATOR . '*.pdf') ?: [],
    glob($cache_dir . DIRECTORY_SEPARATOR . '*.json') ?: []
);
foreach ($old_files as $old_file) {
    if (is_file($old_file) && ($now - filemtime($old_file) > 1800)) {
        @unlink($old_file);
    }
}

// 推測不能なUUID相当のトークン生成
try {
    $token = bin2hex(random_bytes(16));
} catch (Exception $e) {
    $token = md5(uniqid((string)mt_rand(), true));
}

// --- Linearized (Web最適化) PDF の自動判定 ---
// 未最適化PDFに対してストリームを有効にするとRangeリクエストが多発しサーバー負荷になるため、
// 先頭1KBをチェックして最適化済みの場合のみフロントエンドでストリームを許可する
$is_linearized = false;
$check_bytes = 1024;
$fp_check = @fopen($file_path, 'rb');
if ($fp_check) {
    $key = get_encryption_key();
    $iv = fread($fp_check, 16);
    $cipher_data = fread($fp_check, $check_bytes);
    if ($key && $iv && $cipher_data) {
        // パディングなしで復号するため OPENSSL_NO_PADDING を指定
        $decrypted = openssl_decrypt($cipher_data, 'aes-256-cbc', $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
        if ($decrypted !== false && strpos($decrypted, '/Linearized') !== false) {
            $is_linearized = true;
        }
    }
    fclose($fp_check);
}

// 参照情報（JSON）を生成（復号処理は serve_preview.php にてRangeリクエスト単位でオンデマンドに行う）
$ref_path = $cache_dir . DIRECTORY_SEPARATOR . $token . '.json';
$ref_data = [
    'path' => $file_path,
    'is_encrypted' => true
];
if (file_put_contents($ref_path, json_encode($ref_data)) === false) {
    http_response_code(500);
    die('エラー: 参照情報の生成に失敗しました。');
}

$serve_url = 'index.php?action=serve_preview&token=' . urlencode($token);
$delete_url = 'index.php?action=delete_preview_cache';
$file_label = htmlspecialchars(basename($file_path), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プレビュー: <?= $file_label ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <style>
        :root {
            --bg-color: #1e293b;
            --bar-color: #0f172a;
            --border-color: #334155;
            --text-color: #f8fafc;
        }
        body, html {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            overflow: hidden;
            background-color: var(--bg-color);
            color: var(--text-color);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }
        .viewer-container {
            display: flex;
            flex-direction: column;
            height: 100vh;
        }
        .viewer-header {
            background-color: var(--bar-color);
            border-bottom: 1px solid var(--border-color);
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            flex-wrap: wrap;
            z-index: 10;
        }
        .file-title {
            font-size: 0.95rem;
            font-weight: 600;
            max-width: 300px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .toolbar-btn {
            background: #334155;
            color: #f8fafc;
            border: 1px solid #475569;
            border-radius: 4px;
            padding: 0.25rem 0.6rem;
            font-size: 0.85rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
        }
        .toolbar-btn:hover:not(:disabled) {
            background: #475569;
        }
        .toolbar-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
        }
        .page-info {
            font-size: 0.85rem;
            color: #cbd5e1;
            min-width: 70px;
            text-align: center;
        }
        .viewer-body {
            flex: 1;
            overflow-y: auto;
            overflow-x: hidden;
            position: relative;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 1.5rem 1rem;
            -webkit-overflow-scrolling: touch;
            scroll-behavior: smooth;
        }
        .pages-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.5rem;
            width: 100%;
            padding-bottom: 3rem;
        }
        .page-slot {
            position: relative;
            background-color: #ffffff;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.5);
            border-radius: 2px;
            display: flex;
            justify-content: center;
            align-items: center;
            box-sizing: border-box;
            transition: width 0.15s ease, height 0.15s ease;
        }
        .page-slot.loading::before {
            content: "P." attr(data-page-num);
            color: #94a3b8;
            font-size: 1.3rem;
            font-weight: 600;
            letter-spacing: 0.05em;
        }
        .page-slot canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 2px;
        }
        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.75);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 20;
            transition: opacity 0.2s ease;
        }
        .loading-overlay.hidden {
            display: none;
        }
        .error-message {
            background-color: #ef4444;
            color: #ffffff;
            padding: 0.75rem 1.25rem;
            border-radius: 6px;
            max-width: 90%;
            text-align: center;
            display: none;
            position: absolute;
            top: 2rem;
            z-index: 30;
        }
    </style>
</head>
<body>
    <div class="viewer-container">
        <header class="viewer-header">
            <div class="d-flex align-items-center">
                <button type="button" class="toolbar-btn me-2" onclick="if(window.parent && window.parent !== window && window.parent.closePreviewModal){ window.parent.closePreviewModal(); } else { window.close(); if(!window.closed) history.back(); }" title="閉じる">
                    <i class="bi bi-x-lg"></i>
                </button>
                <i class="bi bi-file-earmark-pdf-fill text-danger me-2 fs-5"></i>
                <span class="file-title" title="<?= $file_label ?>"><?= $file_label ?></span>
            </div>

            <div class="toolbar-group">
                <button type="button" class="toolbar-btn" id="prevPageBtn" title="前のページへスクロール">
                    <i class="bi bi-chevron-up"></i>
                </button>
                <span class="page-info">
                    <span id="pageNum">1</span> / <span id="pageCount">-</span>
                </span>
                <button type="button" class="toolbar-btn" id="nextPageBtn" title="次のページへスクロール">
                    <i class="bi bi-chevron-down"></i>
                </button>
            </div>

            <div class="toolbar-group">
                <button type="button" class="toolbar-btn" id="zoomOutBtn" title="縮小">
                    <i class="bi bi-zoom-out"></i>
                </button>
                <span class="page-info" id="zoomPercent">100%</span>
                <button type="button" class="toolbar-btn" id="zoomInBtn" title="拡大">
                    <i class="bi bi-zoom-in"></i>
                </button>
                <button type="button" class="toolbar-btn" id="zoomFitBtn" title="幅に合わせる">
                    <i class="bi bi-arrows-expand"></i>
                </button>
            </div>
        </header>

        <main class="viewer-body" id="viewerBody">
            <div class="loading-overlay" id="loadingOverlay">
                <div class="spinner-border text-primary mb-3" role="status"></div>
                <div class="small text-light" id="loadingText">PDFを読み込み中...</div>
            </div>
            <div class="error-message" id="errorMessage"></div>
            <div class="pages-container" id="pagesContainer"></div>
        </main>
    </div>

    <script>
        const token = <?= json_encode($token) ?>;
        const serveUrl = <?= json_encode($serve_url) ?>;
        const deleteUrl = <?= json_encode($delete_url) ?>;
        const isLinearized = <?= $is_linearized ? 'true' : 'false' ?>;

        // 離脱時のクリーンアップ処理（Beacon API + fetch keepalive）
        let cleanedUp = false;
        function cleanup() {
            if (cleanedUp) return;
            cleanedUp = true;
            try {
                const formData = new FormData();
                formData.append('token', token);
                if (navigator.sendBeacon) {
                    navigator.sendBeacon(deleteUrl, formData);
                } else {
                    fetch(deleteUrl, {
                        method: 'POST',
                        body: formData,
                        keepalive: true
                    });
                }
            } catch (e) {
                console.error('Cleanup error:', e);
            }
        }
        window.addEventListener('pagehide', cleanup);
        window.addEventListener('beforeunload', cleanup);
        window.cleanup = cleanup;
        window.previewToken = token;

        // 親ウィンドウ（モーダル）へトークンを通知
        try {
            if (window.parent && window.parent !== window) {
                window.parent.postMessage({ type: 'preview_token', token: token }, '*');
            }
        } catch (e) {}

        // pdf.js の初期化
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';

        let pdfDoc = null;
        let currentPage = 1;
        let scale = 1.0;
        let fitToWidth = true;
        let defaultAspectRatio = 1.414; // デフォルトA4比率（実測値で更新）
        let defaultBaseWidth = 595;     // デフォルト幅（実測値で更新）
        const pageDimensions = new Map(); // pageNum => { width, height, aspectRatio }

        const pagesContainer = document.getElementById('pagesContainer');
        const prevPageBtn = document.getElementById('prevPageBtn');
        const nextPageBtn = document.getElementById('nextPageBtn');
        const pageNumSpan = document.getElementById('pageNum');
        const pageCountSpan = document.getElementById('pageCount');
        const zoomOutBtn = document.getElementById('zoomOutBtn');
        const zoomInBtn = document.getElementById('zoomInBtn');
        const zoomFitBtn = document.getElementById('zoomFitBtn');
        const zoomPercentSpan = document.getElementById('zoomPercent');
        const loadingOverlay = document.getElementById('loadingOverlay');
        const errorMessage = document.getElementById('errorMessage');
        const viewerBody = document.getElementById('viewerBody');

        function showError(msg) {
            loadingOverlay.classList.add('hidden');
            errorMessage.textContent = msg;
            errorMessage.style.display = 'block';
        }

        function updateZoomDisplay() {
            zoomPercentSpan.textContent = Math.round(scale * 100) + '%';
        }

        // --- メモリ安全な仮想スクロール管理 ---
        const renderedPages = new Map(); // pageNum => { canvas, renderTask, rendering }
        let observer = null;

        function getPageDims(pageNum) {
            return pageDimensions.get(pageNum) || {
                width: defaultBaseWidth,
                height: defaultBaseWidth * defaultAspectRatio,
                aspectRatio: defaultAspectRatio
            };
        }

        function updateSingleSlotDimension(slot, pageNum) {
            const dims = getPageDims(pageNum);
            const containerWidth = viewerBody.clientWidth - 40;
            let slotWidth, slotHeight;

            if (fitToWidth) {
                slotWidth = Math.min(Math.max(containerWidth, 300), 1400);
                slotHeight = slotWidth * dims.aspectRatio;
            } else {
                slotWidth = dims.width * scale;
                slotHeight = dims.height * scale;
            }

            slot.style.width = Math.floor(slotWidth) + 'px';
            slot.style.height = Math.floor(slotHeight) + 'px';
        }

        function updateSlotDimensions() {
            const containerWidth = viewerBody.clientWidth - 40;
            if (fitToWidth) {
                const targetWidth = Math.min(Math.max(containerWidth, 300), 1400);
                scale = targetWidth / defaultBaseWidth;
            }
            updateZoomDisplay();

            const slots = document.querySelectorAll('.page-slot');
            slots.forEach(slot => {
                const pageNum = parseInt(slot.getAttribute('data-page-num'), 10);
                updateSingleSlotDimension(slot, pageNum);
            });
        }

        /**
         * 対象ページの描画
         */
        function renderPage(pageNum, slot) {
            if (renderedPages.has(pageNum)) {
                return;
            }

            const record = { canvas: null, renderTask: null, rendering: true };
            renderedPages.set(pageNum, record);

            pdfDoc.getPage(pageNum).then(page => {
                // 描画キャンセル済みの場合は破棄
                if (!renderedPages.has(pageNum)) return;

                // ページの真の寸法とアスペクト比を取得して保存＆スロット枠サイズを正確に補正
                const defaultViewport = page.getViewport({ scale: 1.0 });
                pageDimensions.set(pageNum, {
                    width: defaultViewport.width,
                    height: defaultViewport.height,
                    aspectRatio: defaultViewport.height / defaultViewport.width
                });
                updateSingleSlotDimension(slot, pageNum);

                const currentSlotWidth = parseFloat(slot.style.width) || (defaultViewport.width * scale);
                const pageScale = currentSlotWidth / defaultViewport.width;
                const outputScale = window.devicePixelRatio || 1;
                const viewport = page.getViewport({ scale: pageScale * outputScale });

                const canvas = document.createElement('canvas');
                canvas.width = Math.floor(viewport.width);
                canvas.height = Math.floor(viewport.height);
                canvas.style.width = '100%';
                canvas.style.height = '100%';
                canvas.style.display = 'block';

                const ctx = canvas.getContext('2d');
                const renderContext = {
                    canvasContext: ctx,
                    viewport: viewport
                };

                const renderTask = page.render(renderContext);
                record.renderTask = renderTask;
                record.canvas = canvas;

                renderTask.promise.then(() => {
                    record.rendering = false;
                    slot.classList.remove('loading');
                    slot.innerHTML = '';
                    slot.appendChild(canvas);
                }).catch(err => {
                    if (err.name !== 'RenderingCancelledException') {
                        console.error('Render error on page ' + pageNum, err);
                    }
                    renderedPages.delete(pageNum);
                });
            }).catch(err => {
                console.error('GetPage error on page ' + pageNum, err);
                renderedPages.delete(pageNum);
            });
        }

        /**
         * 対象ページのキャンバス解放（VRAMとDOMの省メモリ化）
         */
        function unrenderPage(pageNum, slot) {
            if (!renderedPages.has(pageNum)) return;

            const record = renderedPages.get(pageNum);
            if (record.renderTask) {
                record.renderTask.cancel();
            }
            if (record.canvas) {
                record.canvas.width = 0;
                record.canvas.height = 0;
                if (record.canvas.parentNode) {
                    record.canvas.remove();
                }
            }
            slot.innerHTML = '';
            slot.classList.add('loading');
            renderedPages.delete(pageNum);
        }

        /**
         * 全ページの再描画（ズーム変更時など）
         */
        function reRenderAllVisible() {
            updateSlotDimensions();
            // 現在レンダリング済みのページを一旦破棄して再描画
            const pagesToReload = Array.from(renderedPages.keys());
            pagesToReload.forEach(p => {
                const slot = document.getElementById('page-slot-' + p);
                if (slot) {
                    unrenderPage(p, slot);
                }
            });

            // 画面内にあるスロットを再描画
            document.querySelectorAll('.page-slot').forEach(slot => {
                const rect = slot.getBoundingClientRect();
                const vHeight = window.innerHeight;
                if (rect.bottom > -200 && rect.top < vHeight + 200) {
                    const p = parseInt(slot.getAttribute('data-page-num'), 10);
                    renderPage(p, slot);
                }
            });
        }

        /**
         * スクロール位置に基づく現在ページ更新と遅延レンダリング
         */
        function setupIntersectionObserver() {
            if (observer) observer.disconnect();

            observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    const slot = entry.target;
                    const pageNum = parseInt(slot.getAttribute('data-page-num'), 10);

                    if (entry.isIntersecting) {
                        // 画面内（前後400px含む）に入ったらオンデマンド描画
                        renderPage(pageNum, slot);
                    } else {
                        // 画面から大きく離れたらメモリ節約のためにアンロード
                        unrenderPage(pageNum, slot);
                    }

                    // 画面中央付近にあるページを現在ページとしてツールバーに反映
                    if (entry.intersectionRatio > 0.4) {
                        currentPage = pageNum;
                        pageNumSpan.textContent = currentPage;
                        prevPageBtn.disabled = (currentPage <= 1);
                        nextPageBtn.disabled = (currentPage >= pdfDoc.numPages);
                    }
                });
            }, {
                root: viewerBody,
                rootMargin: '400px 0px 400px 0px', // 上下400pxの余裕を持って先行描画
                threshold: [0, 0.4, 0.8]
            });

            document.querySelectorAll('.page-slot').forEach(slot => observer.observe(slot));
        }

        function scrollToPage(num) {
            const slot = document.getElementById('page-slot-' + num);
            if (slot) {
                const targetTop = slot.offsetTop - 16;
                viewerBody.scrollTo({ top: Math.max(0, targetTop), behavior: 'smooth' });
            }
        }

        prevPageBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                scrollToPage(currentPage - 1);
            }
        });

        nextPageBtn.addEventListener('click', () => {
            if (currentPage < pdfDoc.numPages) {
                scrollToPage(currentPage + 1);
            }
        });

        zoomInBtn.addEventListener('click', () => {
            if (scale >= 3.0) return;
            fitToWidth = false;
            scale = Math.min(scale + 0.2, 3.0);
            reRenderAllVisible();
        });

        zoomOutBtn.addEventListener('click', () => {
            if (scale <= 0.4) return;
            fitToWidth = false;
            scale = Math.max(scale - 0.2, 0.4);
            reRenderAllVisible();
        });

        zoomFitBtn.addEventListener('click', () => {
            fitToWidth = true;
            reRenderAllVisible();
        });

        // ウィンドウリサイズ対応（幅に合わせるモード時）
        let resizeTimer = null;
        window.addEventListener('resize', () => {
            if (!fitToWidth) return;
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                reRenderAllVisible();
            }, 200);
        });

        // キーボード操作対応
        window.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (window.parent && window.parent !== window && window.parent.closePreviewModal) {
                    window.parent.closePreviewModal();
                } else {
                    window.close();
                    if (!window.closed) history.back();
                }
                e.preventDefault();
            } else if (e.key === 'ArrowUp' || e.key === 'PageUp') {
                if (currentPage > 1) {
                    scrollToPage(currentPage - 1);
                    e.preventDefault();
                }
            } else if (e.key === 'ArrowDown' || e.key === 'PageDown' || e.key === ' ') {
                if (currentPage < pdfDoc.numPages) {
                    scrollToPage(currentPage + 1);
                    e.preventDefault();
                }
            }
        });

        // PDFの読み込み開始
        // 未最適化の巨大PDFによるサーバー負荷（細かいRangeリクエスト乱れ撃ち）を防ぐため、
        // 最適化済みPDFのみストリームを有効化し、未最適化の場合は一括ダウンロード(安全モード)にフォールバックする
        const loadingTask = pdfjsLib.getDocument({
            url: serveUrl,
            rangeChunkSize: 65536 * 2, // 128KBずつ取得してチャンク効率を上げる
            disableAutoFetch: !isLinearized,
            disableStream: !isLinearized
        });

        loadingTask.promise.then(async pdf => {
            pdfDoc = pdf;
            pageCountSpan.textContent = pdf.numPages;

            // 1ページ目から基本比率・サイズを検出
            try {
                const firstPage = await pdf.getPage(1);
                const defaultViewport = firstPage.getViewport({ scale: 1.0 });
                defaultBaseWidth = defaultViewport.width;
                defaultAspectRatio = defaultViewport.height / defaultViewport.width;
                pageDimensions.set(1, {
                    width: defaultBaseWidth,
                    height: defaultViewport.height,
                    aspectRatio: defaultAspectRatio
                });
            } catch (e) {
                console.warn('Could not read page 1 viewport, using fallback aspect ratio.', e);
            }

            // 全ページ分のスロットを一括生成（DOM構築は軽量なdivのみ）
            const fragment = document.createDocumentFragment();
            for (let i = 1; i <= pdf.numPages; i++) {
                const slot = document.createElement('div');
                slot.className = 'page-slot loading';
                slot.id = 'page-slot-' + i;
                slot.setAttribute('data-page-num', i);
                fragment.appendChild(slot);
            }
            pagesContainer.appendChild(fragment);

            // スロットの寸法を設定
            updateSlotDimensions();

            // 交差監視（スクロール遅延描画）の開始
            setupIntersectionObserver();

            // 初期ローディングオーバーレイの非表示
            loadingOverlay.classList.add('hidden');

            // バックグラウンドで各ページのメタデータを軽量事前取得（スクロール時のレイアウトシフト防止）
            (async () => {
                for (let i = 2; i <= pdf.numPages; i++) {
                    if (pageDimensions.has(i)) continue;
                    try {
                        const p = await pdf.getPage(i);
                        const vp = p.getViewport({ scale: 1.0 });
                        pageDimensions.set(i, {
                            width: vp.width,
                            height: vp.height,
                            aspectRatio: vp.height / vp.width
                        });
                        const slot = document.getElementById('page-slot-' + i);
                        if (slot) {
                            updateSingleSlotDimension(slot, i);
                        }
                    } catch (err) {
                        // 失敗時はフォールバックのまま
                    }
                }
            })();
        }).catch(err => {
            showError('PDFの読み込みに失敗しました: ' + err.message);
        });
    </script>
</body>
</html>
