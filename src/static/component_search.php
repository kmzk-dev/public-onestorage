<?php
// component_search.php: ファイル名検索UIコンポーネント（アイコンボタン & モーダル）
?>
<!-- 検索起動ボタン -->
<button class="header-icon-btn me-2" data-bs-toggle="modal" data-bs-target="#searchModal" title="検索">
    <i class="bi bi-search fs-5"></i>
</button>

<!-- 検索モーダル -->
<div class="modal fade" id="searchModal" tabindex="-1" aria-labelledby="searchModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-scrollable modal-lg">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom p-3">
                <div class="input-group">
                    <span class="input-group-text bg-light border-secondary-subtle text-secondary">
                        <i class="bi bi-search"></i>
                    </span>
                    <input type="text" id="globalSearchInput" class="form-control border-secondary-subtle shadow-none" placeholder="ファイル名を検索..." autocomplete="off" aria-label="ファイル名を検索">
                    <button class="btn btn-outline-secondary border-secondary-subtle" type="button" id="searchClearBtn" title="クリア" style="display: none;">
                        <i class="bi bi-x-lg"></i>
                    </button>
                    <span id="searchSpinner" class="input-group-text bg-light border-secondary-subtle text-secondary" style="display: none;">
                        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true" style="width: 1rem; height: 1rem;"></span>
                    </span>
                </div>
                <button type="button" class="btn-close ms-2" data-bs-dismiss="modal" aria-label="閉じる"></button>
            </div>
            <div class="modal-body p-0">
                <!-- 検索結果パネル -->
                <div id="searchResultsPanel" class="w-100" style="display: none;">
                    <div class="p-2 px-3 border-bottom bg-light d-flex justify-content-between align-items-center">
                        <span class="small fw-bold text-muted" id="searchSummaryText">検索結果</span>
                        <span class="badge bg-secondary-subtle text-secondary font-monospace small" id="searchResultCount">0 件</span>
                    </div>
                    <div id="searchResultsList" class="list-group list-group-flush small">
                        <!-- 検索結果がここに動的レンダリングされます -->
                    </div>
                </div>
                <div id="searchInitialGuide" class="p-4 text-center text-muted small">
                    <i class="bi bi-keyboard fs-3 d-block mb-2"></i>
                    探したいファイル名を入力してください
                </div>
            </div>
        </div>
    </div>
</div>

<style>
/* 検索モーダルのスタイリング */
#searchModal {
    z-index: 1055 !important;
}
/* 検索結果のスタイリング */
#searchResultsList {
    max-height: 480px;
    overflow-y: auto !important;
    overscroll-behavior: contain;
}
#searchResultsList::-webkit-scrollbar {
    width: 6px;
}
#searchResultsList::-webkit-scrollbar-track {
    background: #f8fafc;
}
#searchResultsList::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}
#searchResultsList::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}
.search-result-item {
    transition: background-color 0.15s ease-in-out;
}
.search-result-item:hover {
    background-color: #f1f5f9;
}
.search-result-item .action-btn {
    opacity: 0.75;
    transition: opacity 0.15s;
}
.search-result-item:hover .action-btn {
    opacity: 1;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const searchModalEl = document.getElementById('searchModal');
    const searchInput = document.getElementById('globalSearchInput');
    const searchPanel = document.getElementById('searchResultsPanel');
    const resultsList = document.getElementById('searchResultsList');
    const searchSpinner = document.getElementById('searchSpinner');
    const searchClearBtn = document.getElementById('searchClearBtn');
    const searchSummaryText = document.getElementById('searchSummaryText');
    const searchResultCount = document.getElementById('searchResultCount');
    const searchInitialGuide = document.getElementById('searchInitialGuide');

    if (!searchModalEl || !searchInput || !searchPanel || !resultsList) return;

    // navbar (sticky-top) によるスタッキングコンテキストのグレーアウト遮断を回避するため、
    // モーダル要素を document.body 直下に移動
    if (searchModalEl.parentNode !== document.body) {
        document.body.appendChild(searchModalEl);
    }

    let debounceTimer = null;
    let abortController = null;

    function showResults() {
        searchPanel.style.display = 'block';
        if (searchInitialGuide) searchInitialGuide.style.display = 'none';
    }

    function hideResults() {
        searchPanel.style.display = 'none';
        if (searchInitialGuide) searchInitialGuide.style.display = 'block';
    }

    // モーダル表示時に自動フォーカス
    searchModalEl.addEventListener('shown.bs.modal', () => {
        searchInput.focus();
        searchInput.select();
    });

    // モーダル閉じた時に入力クリア（任意だが次の検索をクリアにする場合はリセット）
    // 必要に応じて前回の検索語句を保持してもよいが、ガイド表示と連動
    searchModalEl.addEventListener('hidden.bs.modal', () => {
        if (abortController) {
            abortController.abort();
        }
    });

    // Enterキー押下で即時検索
    searchInput.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const query = searchInput.value.trim();
            if (query.length > 0) {
                clearTimeout(debounceTimer);
                performSearch(query);
            }
        }
    });

    // 検索入力イベント
    searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim();
        if (query.length > 0) {
            searchClearBtn.style.display = 'inline-block';
        } else {
            searchClearBtn.style.display = 'none';
            hideResults();
            return;
        }

        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => {
            performSearch(query);
        }, 250);
    });

    // クリアボタン
    searchClearBtn.addEventListener('click', () => {
        searchInput.value = '';
        searchClearBtn.style.display = 'none';
        hideResults();
        searchInput.focus();
    });

    // 検索リクエスト実行
    async function performSearch(query) {
        if (abortController) {
            abortController.abort();
        }
        abortController = new AbortController();

        searchSpinner.style.display = 'inline-flex';

        // index.php 経由（メイン）と functions/search.php 直接（フォールバック）の両方に対応
        const endpoints = [
            `index.php?action=search&q=${encodeURIComponent(query)}`,
            `functions/search.php?q=${encodeURIComponent(query)}`
        ];

        let successData = null;

        for (const endpoint of endpoints) {
            try {
                const response = await fetch(endpoint, {
                    signal: abortController.signal
                });

                if (response.ok) {
                    const data = await response.json();
                    if (data && data.success) {
                        successData = data;
                        break;
                    }
                }
            } catch (err) {
                if (err.name === 'AbortError') {
                    return;
                }
            }
        }

        searchSpinner.style.display = 'none';

        if (successData) {
            renderResults(successData);
        } else {
            resultsList.innerHTML = `<div class="p-3 text-center text-danger small">
                <i class="bi bi-exclamation-circle me-1"></i>検索に失敗しました。サーバーの通信または認証状態をご確認ください。
            </div>`;
            showResults();
        }
    }

    // 検索結果の描画
    function renderResults(data) {
        resultsList.innerHTML = '';
        const count = data.count || 0;
        searchResultCount.textContent = `${count} 件`;
        searchSummaryText.textContent = `"${escapeHtml(data.query)}" の検索結果`;

        if (count === 0) {
            resultsList.innerHTML = `
                <div class="p-4 text-center text-muted">
                    <i class="bi bi-search fs-3 d-block mb-2"></i>
                    一致するファイルが見つかりませんでした
                </div>`;
            showResults();
            return;
        }

        data.results.forEach(item => {
            const itemEl = document.createElement('div');
            itemEl.className = 'list-group-item list-group-item-action d-flex align-items-center justify-content-between p-2 px-3 search-result-item border-0 border-bottom';

            const folderDisplayName = (item.path === '' || item.path === '/') ? 'home' : item.path;
            const folderUrl = item.path === '' ? '?path=' : `?path=${encodeURIComponent(item.path)}`;
            const isPdf = (item.ext === 'pdf' || (item.name && item.name.toLowerCase().endsWith('.pdf')));
            const viewUrl = isPdf ? `?action=pdf_preview&path=${encodeURIComponent(item.view_path)}` : `?action=view&path=${encodeURIComponent(item.view_path)}`;
            const downloadUrl = `?action=download&path=${encodeURIComponent(item.view_path)}`;

            itemEl.innerHTML = `
                <div class="d-flex align-items-center text-truncate me-2" style="min-width: 0;">
                    <i class="bi ${item.icon || 'bi-file-earmark'} ${item.icon_color || 'text-secondary'} fs-5 me-2 flex-shrink-0"></i>
                    <div class="text-truncate">
                        <a href="${viewUrl}" target="_blank" class="fw-bold text-dark text-decoration-none d-block text-truncate text-break ${isPdf ? 'preview-trigger' : ''}" title="${escapeHtml(item.name)}">
                            ${escapeHtml(item.name)}
                        </a>
                        <div class="text-muted small d-flex align-items-center flex-wrap gap-2 mt-1">
                            <span><i class="bi bi-hdd me-1"></i>${item.formatted_size}</span>
                            <span><i class="bi bi-clock me-1"></i>${item.formatted_mtime}</span>
                            <span class="badge bg-secondary-subtle text-secondary text-truncate" style="max-width: 200px;">
                                <i class="bi bi-folder me-1"></i>${escapeHtml(folderDisplayName)}
                            </span>
                        </div>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                    <a href="${folderUrl}" class="btn btn-sm btn-outline-primary action-btn" title="親フォルダを開く">
                        <i class="bi bi-folder2-open"></i>
                        <span class="d-none d-lg-inline ms-1">フォルダ</span>
                    </a>
                    <a href="${downloadUrl}" class="btn btn-sm btn-outline-secondary action-btn" title="ダウンロード">
                        <i class="bi bi-download"></i>
                    </a>
                </div>
            `;
            resultsList.appendChild(itemEl);
        });

        showResults();
    }

    function escapeHtml(str) {
        if (!str) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }
});
</script>
