<?php
// component_move_to_sharebox_modal.php: SHARE BOXへのファイル移動モーダル
if (!defined('ONESTORAGE_RUNNING')) {
    exit;
}
$sharebox_available_folders = is_sharebox_enabled() ? get_sharebox_folders() : [];
$selectable_folders = array_values(array_filter($sharebox_available_folders, fn($f) => $f['path'] !== $web_path));
$initial_dest = !empty($selectable_folders) ? $selectable_folders[0]['path'] : '';
$is_in_sharebox_folder = str_starts_with($web_path, 'sharebox/');
?>
<div class="modal fade" id="moveToShareboxModal" tabindex="-1" aria-labelledby="moveToShareboxModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title d-flex align-items-center" id="moveToShareboxModalTitle">
                    <i class="bi bi-share-fill text-success me-2"></i>
                    <span id="moveToShareboxTitleText"><?= $is_in_sharebox_folder ? '他の共有フォルダへ移動' : 'SHARE BOXへ移動' ?></span>
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="post" id="moveToShareboxForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="move_items">
                    <input type="hidden" name="path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="items_json" id="moveToShareboxItemsJson" value="[]">
                    <input type="hidden" name="item_name" id="moveToShareboxItemName" value="">
                    <input type="hidden" name="destination" id="moveToShareboxDestination" value="<?= htmlspecialchars($initial_dest, ENT_QUOTES, 'UTF-8') ?>">

                    <div id="moveToShareboxTargetNotice" class="text-muted small mb-3"></div>

                    <?php if (empty($sharebox_available_folders)): ?>
                        <div class="alert alert-warning d-flex align-items-start mb-0">
                            <i class="bi bi-exclamation-triangle-fill fs-5 me-2 flex-shrink-0 text-warning"></i>
                            <div>
                                <div class="fw-bold mb-1">共有用フォルダがありません</div>
                                <div class="small">先に <a href="?path=sharebox" class="alert-link text-decoration-underline">SHARE BOX</a> に移動し、共有用のフォルダを作成してください。</div>
                            </div>
                        </div>
                    <?php elseif (empty($selectable_folders)): ?>
                        <div class="alert alert-info d-flex align-items-start mb-0">
                            <i class="bi bi-info-circle fs-5 me-2 flex-shrink-0 text-info"></i>
                            <div>
                                <div class="fw-bold mb-1">移動可能な他の共有用フォルダがありません</div>
                                <div class="small">先に <a href="?path=sharebox" class="alert-link text-decoration-underline">SHARE BOX</a> に移動し、別の共有用フォルダを作成してください。</div>
                            </div>
                        </div>
                    <?php else: ?>
                        <label class="form-label fw-bold small text-muted">移動先の共有フォルダを選択</label>
                        <div class="list-group border rounded" style="max-height: 260px; overflow-y: auto;">
                            <?php foreach ($sharebox_available_folders as $sbf): 
                                $is_current = ($sbf['path'] === $web_path);
                            ?>
                                <?php if ($is_current): ?>
                                    <label class="list-group-item list-group-item-light d-flex align-items-center justify-content-between py-2 mb-0 opacity-50" style="cursor: not-allowed;">
                                        <div class="d-flex align-items-center text-truncate me-2">
                                            <input class="form-check-input me-3 flex-shrink-0 sharebox-dest-radio" type="radio" name="sharebox_dest_choice" value="<?= htmlspecialchars($sbf['path'], ENT_QUOTES, 'UTF-8') ?>" disabled>
                                            <i class="bi bi-folder-fill text-muted fs-5 me-2 flex-shrink-0"></i>
                                            <span class="text-truncate fw-semibold text-muted"><?= htmlspecialchars($sbf['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <span class="badge bg-secondary-subtle text-secondary border flex-shrink-0 small">現在のフォルダ</span>
                                    </label>
                                <?php else: ?>
                                    <label class="list-group-item list-group-item-action d-flex align-items-center justify-content-between py-2 mb-0" style="cursor: pointer;">
                                        <div class="d-flex align-items-center text-truncate me-2">
                                            <input class="form-check-input me-3 flex-shrink-0 sharebox-dest-radio" type="radio" name="sharebox_dest_choice" value="<?= htmlspecialchars($sbf['path'], ENT_QUOTES, 'UTF-8') ?>" <?= $sbf['path'] === $initial_dest ? 'checked' : '' ?>>
                                            <i class="bi bi-folder-fill text-success fs-5 me-2 flex-shrink-0"></i>
                                            <span class="text-truncate fw-semibold text-dark"><?= htmlspecialchars($sbf['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                        <?php if ($sbf['share_info']): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle flex-shrink-0 small">共有中</span>
                                        <?php endif; ?>
                                    </label>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
                    <button type="submit" class="btn btn-success" id="moveToShareboxSubmitBtn" <?= empty($selectable_folders) ? 'disabled' : '' ?>>
                        <i class="bi bi-share-fill me-1"></i>移動する
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const modalEl = document.getElementById('moveToShareboxModal');
    if (!modalEl) return;

    const form = document.getElementById('moveToShareboxForm');
    const submitBtn = document.getElementById('moveToShareboxSubmitBtn');
    const destInput = document.getElementById('moveToShareboxDestination');
    const titleText = document.getElementById('moveToShareboxTitleText');
    const targetNotice = document.getElementById('moveToShareboxTargetNotice');
    const itemNameInput = document.getElementById('moveToShareboxItemName');
    const itemsJsonInput = document.getElementById('moveToShareboxItemsJson');
    const radios = modalEl.querySelectorAll('.sharebox-dest-radio');
    const isShareboxFolder = <?= $is_in_sharebox_folder ? 'true' : 'false' ?>;
    const selectableCount = <?= count($selectable_folders) ?>;

    // ラジオボタン選択変更時にdestinationを更新
    radios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.checked && destInput) {
                destInput.value = this.value;
            }
        });
    });

    function syncModalTarget(btn) {
        // ラジオボタンの選択初期化
        const validRadios = Array.from(radios).filter(r => !r.disabled);
        if (validRadios.length > 0) {
            let checkedRadio = modalEl.querySelector('.sharebox-dest-radio:checked:not(:disabled)');
            if (!checkedRadio) {
                validRadios[0].checked = true;
                checkedRadio = validRadios[0];
            }
            if (destInput) destInput.value = checkedRadio.value;
        }

        if (btn && btn.hasAttribute('data-bs-item-name')) {
            const itemName = btn.getAttribute('data-bs-item-name');
            if (itemNameInput) itemNameInput.value = itemName;
            if (itemsJsonInput) itemsJsonInput.value = JSON.stringify([itemName]);
            if (titleText) titleText.textContent = isShareboxFolder ? `'${itemName}' を他の共有フォルダへ移動` : `'${itemName}' をSHARE BOXへ移動`;
            if (targetNotice) targetNotice.textContent = `選択中のファイル: ${itemName}`;
            if (submitBtn) submitBtn.disabled = (selectableCount === 0);
        } else {
            if (itemNameInput) itemNameInput.value = '';
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            const fileItems = Array.from(checkedBoxes).filter(cb => cb.getAttribute('data-is-dir') !== '1').map(cb => cb.value);
            if (itemsJsonInput) itemsJsonInput.value = JSON.stringify(fileItems);
            if (titleText) titleText.textContent = isShareboxFolder ? '選択項目を他の共有フォルダへ移動' : '選択項目をSHARE BOXへ移動';
            
            if (fileItems.length === 0) {
                if (targetNotice) targetNotice.innerHTML = '<span class="text-danger"><i class="bi bi-exclamation-circle me-1"></i>移動可能なファイルが選択されていません（フォルダは移動できません）。</span>';
                if (submitBtn) submitBtn.disabled = true;
            } else {
                if (targetNotice) targetNotice.textContent = `選択中のファイル数: ${fileItems.length}件`;
                if (submitBtn) submitBtn.disabled = (selectableCount === 0);
            }
        }
    }

    // ドロップダウン内の単体ボタンまたは一括移動ボタンクリック時に即時同期
    document.addEventListener('click', function(event) {
        const btn = event.target.closest('.single-move-sharebox-btn, [data-bs-target="#moveToShareboxModal"]');
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
