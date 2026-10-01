<?php
// component_recent_exclusions_modal.php: RECENT除外ファイル管理モーダル
if (!defined('ONESTORAGE_RUNNING')) {
    exit;
}
?>
<div class="modal fade" id="recentExclusionsModal" tabindex="-1" aria-labelledby="recentExclusionsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content" style="height: 560px; max-height: 85vh;">
            <div class="modal-header flex-shrink-0">
                <h5 class="modal-title fw-bold" id="recentExclusionsModalLabel">
                    <i class="bi bi-eye-slash me-2 text-secondary"></i>RECENTから除外されたファイル
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0 d-flex flex-column overflow-hidden">
                <div class="p-3 bg-light border-bottom d-flex justify-content-between align-items-center flex-shrink-0">
                    <span class="small text-muted">
                        確認済み等でRECENT一覧から非表示にしたファイルです。元に戻すと、再びRECENT一覧（更新日時の位置）に表示されます。
                    </span>
                    <?php if (!empty($recent_exclusions)): ?>
                        <button type="button" class="btn btn-outline-danger btn-sm flex-shrink-0 ms-2" id="clearAllExclusionsBtn">
                            <i class="bi bi-arrow-counterclockwise me-1"></i>すべて元に戻す
                        </button>
                    <?php endif; ?>
                </div>

                <div id="recentExclusionsListContainer" class="flex-grow-1 overflow-auto">
                    <?php if (empty($recent_exclusions)): ?>
                        <div class="h-100 d-flex flex-column justify-content-center align-items-center text-muted py-5" id="emptyExclusionsMessage">
                            <i class="bi bi-check2-circle display-4 d-block mb-3 text-success opacity-50"></i>
                            <span class="fs-6">現在、除外されたファイルはありません</span>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush" id="recentExclusionsList">
                            <?php foreach ($recent_exclusions as $exc): ?>
                                <div class="list-group-item d-flex justify-content-between align-items-center py-3 exclusion-item-row" data-hash="<?= htmlspecialchars($exc['hash'], ENT_QUOTES, 'UTF-8') ?>">
                                    <div class="text-truncate me-3">
                                        <div class="fw-bold text-truncate text-dark">
                                            <i class="bi bi-file-earmark me-1 text-secondary"></i>
                                            <?= htmlspecialchars($exc['name'], ENT_QUOTES, 'UTF-8') ?>
                                        </div>
                                        <div class="small text-muted text-truncate">
                                            <span class="badge bg-light text-secondary border me-2">パス: /<?= htmlspecialchars($exc['path'], ENT_QUOTES, 'UTF-8') ?></span>
                                            <span>除外日時: <?= date('Y/m/d H:i', $exc['created_at']) ?></span>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-outline-primary btn-sm restore-exclusion-btn flex-shrink-0"
                                        data-web-path="<?= htmlspecialchars($exc['path'], ENT_QUOTES, 'UTF-8') ?>"
                                        data-item-name="<?= htmlspecialchars($exc['name'], ENT_QUOTES, 'UTF-8') ?>">
                                        <i class="bi bi-arrow-counterclockwise me-1"></i>元に戻す
                                    </button>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="modal-footer flex-shrink-0">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">閉じる</button>
            </div>
        </div>
    </div>
</div>
