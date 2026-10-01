<?php
// component_move_to_inbox_modal.php: INBOXへのファイル移動モーダル
if (!defined('ONESTORAGE_RUNNING')) {
    exit;
}
?>
<div class="modal fade" id="moveToInboxModal" tabindex="-1" aria-labelledby="moveToInboxModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center" id="moveToInboxModalTitle">
                    <i class="bi bi-inbox-fill text-info me-2"></i>
                    <span id="moveToInboxTitleText">INBOXへ移動</span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="post" id="moveToInboxForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="move_items">
                    <input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="items_json" id="moveToInboxItemsJson" value="[]">
                    <input type="hidden" name="item_name" id="moveToInboxItemName" value="">
                    <input type="hidden" name="destination" value="inbox">

                    <div id="moveToInboxTargetNotice" class="text-muted small mb-3"></div>

                    <div class="alert alert-info d-flex align-items-start mb-0">
                        <i class="bi bi-info-circle-fill fs-5 me-2 flex-shrink-0 text-info"></i>
                        <div>
                            <div class="fw-bold mb-1">INBOX（未整理・一時ファイル保管庫）へ移動</div>
                            <div class="small">選択したファイルをINBOXへ移動します。INBOX内のファイルは後から任意のフォルダに再分類できます。</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    <button type="submit" class="btn btn-info text-white" id="moveToInboxSubmitBtn">
                        <i class="bi bi-inbox-fill me-1"></i>INBOXへ移動
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('moveToInboxModal');
    if (!modalEl) return;

    const form = document.getElementById('moveToInboxForm');
    const submitBtn = document.getElementById('moveToInboxSubmitBtn');
    const titleText = document.getElementById('moveToInboxTitleText');
    const targetNotice = document.getElementById('moveToInboxTargetNotice');
    const itemNameInput = document.getElementById('moveToInboxItemName');
    const itemsJsonInput = document.getElementById('moveToInboxItemsJson');

    function syncModalTarget(btn) {
        if (btn && btn.hasAttribute('data-bs-item-name')) {
            const itemName = btn.getAttribute('data-bs-item-name');
            if (itemNameInput) itemNameInput.value = itemName;
            if (itemsJsonInput) itemsJsonInput.value = JSON.stringify([itemName]);
            if (titleText) titleText.textContent = `'${itemName}' をINBOXへ移動`;
            if (targetNotice) targetNotice.textContent = `選択中のファイル: ${itemName}`;
            if (submitBtn) submitBtn.disabled = false;
        } else {
            if (itemNameInput) itemNameInput.value = '';
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            const fileItems = Array.from(checkedBoxes).filter(cb => cb.getAttribute('data-is-dir') !== '1').map(cb => cb.value);
            if (itemsJsonInput) itemsJsonInput.value = JSON.stringify(fileItems);
            if (titleText) titleText.textContent = '選択項目をINBOXへ移動';
            
            if (fileItems.length === 0) {
                if (targetNotice) targetNotice.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>移動可能なファイルが選択されていません（フォルダは移動できません）。</span>';
                if (submitBtn) submitBtn.disabled = true;
            } else {
                if (targetNotice) targetNotice.textContent = `選択中のファイル数: ${fileItems.length}件`;
                if (submitBtn) submitBtn.disabled = false;
            }
        }
    }

    // ドロップダウン内の単体ボタンまたは一括移動ボタンクリック時に即時同期
    document.addEventListener('click', function(event) {
        const btn = event.target.closest('.single-move-inbox-btn, [data-bs-target="#moveToInboxModal"]');
        if (!btn) return;
        syncModalTarget(btn);
    });

    // モーダル表示時の初期化処理
    modalEl.addEventListener('show.bs.modal', function(event) {
        const button = event.relatedTarget;
        const triggerBtn = button ? (button.closest('[data-bs-item-name]') || button) : null;
        syncModalTarget(triggerBtn);
    });

    // 送信処理 (Ajax)
    if (form) {
        form.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!submitBtn) return;
            const originalBtnHtml = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>移動中...';

            try {
                const formData = new FormData(form);
                formData.append('ajax', '1');
                const response = await fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();

                const modalInst = bootstrap.Modal.getInstance(modalEl);
                if (modalInst) modalInst.hide();

                if (data.type === 'danger' || data.type === 'warning') {
                    showToast(data.type, data.text);
                } else {
                    showToast('success', data.text);
                    setTimeout(() => { location.reload(); }, 600);
                }
            } catch (err) {
                showToast('danger', '通信エラーが発生しました: ' + err.message);
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalBtnHtml;
            }
        });
    }
});
</script>
