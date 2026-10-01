<?php
// static/preview_modal.php: ファイルプレビュー用フルスクリーンモーダル（PC・モバイル両対応）
?>
<!-- プレビューモーダル -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-labelledby="previewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen m-0 p-0">
        <div class="modal-content bg-dark border-0 rounded-0 h-100 position-relative">
            <!-- プレビュー表示用iframe -->
            <iframe id="previewModalIframe" src="about:blank" style="width: 100%; height: 100%; border: none; display: block;" allowfullscreen></iframe>
        </div>
    </div>
</div>

<script>
(() => {
    let previewModalInstance = null;

    /**
     * プレビューモーダルを開く
     * @param {string} url - プレビュー用URL
     */
    window.openPreviewModal = function(url) {
        const modalEl = document.getElementById('previewModal');
        const iframe = document.getElementById('previewModalIframe');
        if (!modalEl || !iframe) return;

        // モーダル要素を body 直下に配置（スタッキングコンテキストのグレーアウト防止）
        if (modalEl.parentNode !== document.body) {
            document.body.appendChild(modalEl);
        }

        if (!previewModalInstance) {
            previewModalInstance = new bootstrap.Modal(modalEl, {
                backdrop: true,
                keyboard: true
            });
        }

        iframe.src = url;
        previewModalInstance.show();
    };

    /**
     * プレビューモーダルを閉じる
     */
    window.closePreviewModal = function() {
        if (previewModalInstance) {
            previewModalInstance.hide();
        }
    };

    let currentPreviewToken = null;

    // iframeからのトークン通知を受信
    window.addEventListener('message', (e) => {
        if (e.data && e.data.type === 'preview_token' && e.data.token) {
            currentPreviewToken = e.data.token;
        }
    });

    document.addEventListener('DOMContentLoaded', () => {
        const modalEl = document.getElementById('previewModal');
        const iframe = document.getElementById('previewModalIframe');
        if (!modalEl || !iframe) return;

        // モーダルが閉じ始める瞬間にフォーカス解除とキャッシュ削除を実行
        modalEl.addEventListener('hide.bs.modal', () => {
            // モーダル内の要素（閉じるボタン等）がフォーカスを持ったまま aria-hidden="true" になると
            // ブラウザのアクセシビリティ警告（Blocked aria-hidden on an element because its descendant retained focus）が出るため blur() で解除
            if (document.activeElement && modalEl.contains(document.activeElement)) {
                document.activeElement.blur();
            }

            // 1. iframe内のクリーンアップ関数を実行
            try {
                if (iframe.contentWindow && typeof iframe.contentWindow.cleanup === 'function') {
                    iframe.contentWindow.cleanup();
                }
            } catch (err) {}

            // 2. 親ウィンドウからもトークンを指定して確実に削除APIを呼ぶ（二重の保険）
            if (currentPreviewToken) {
                const tokenToDelete = currentPreviewToken;
                currentPreviewToken = null;
                try {
                    const formData = new FormData();
                    formData.append('token', tokenToDelete);
                    if (navigator.sendBeacon) {
                        navigator.sendBeacon('index.php?action=delete_preview_cache', formData);
                    } else {
                        fetch('index.php?action=delete_preview_cache', {
                            method: 'POST',
                            body: formData,
                            keepalive: true
                        });
                    }
                } catch (err) {}
            }
        });

        // モーダルが完全に閉じた後にiframeを解放
        modalEl.addEventListener('hidden.bs.modal', () => {
            iframe.src = 'about:blank';
        });

        // プレビューリンクのクリックを拾ってモーダルを開く (Event Delegation)
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a.preview-trigger, a[data-preview-url], a[href*="action=pdf_preview"]');
            if (!link) return;

            // CtrlキーやCmdキー押下時はブラウザ本来の「別タブで開く」を許可
            if (e.ctrlKey || e.metaKey || e.shiftKey) return;

            e.preventDefault();

            // もし他のモーダル（検索モーダル等）が開いていたら閉じる
            const activeModal = link.closest('.modal');
            if (activeModal && activeModal.id !== 'previewModal') {
                const activeModalInstance = bootstrap.Modal.getInstance(activeModal);
                if (activeModalInstance) {
                    activeModalInstance.hide();
                }
            }

            const previewUrl = link.getAttribute('data-preview-url') || link.getAttribute('href');
            if (previewUrl && previewUrl !== '#' && previewUrl !== 'about:blank') {
                window.openPreviewModal(previewUrl);
            }
        });
    });
})();
</script>
