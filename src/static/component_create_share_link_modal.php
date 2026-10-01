<!-- 共有リンク発行モーダル -->
<div class="modal fade" id="createShareLinkModal" tabindex="-1" aria-labelledby="createShareLinkModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="createShareLinkModalLabel">
                    <i class="bi bi-share-fill me-2 text-success"></i>共有リンクの発行
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="index.php" method="post" id="createShareLinkForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="create_share_link">
                    <input type="hidden" name="path" value="sharebox">
                    <input type="hidden" name="folder_name" id="shareLinkFolderName" value="">

                    <!-- 対象フォルダ表示 -->
                    <div class="alert alert-secondary py-2 mb-3 small d-flex align-items-center">
                        <i class="bi bi-folder-fill me-2 text-primary fs-5"></i>
                        <div>
                            対象フォルダ: <strong id="shareLinkFolderDisplay" class="text-dark"></strong>
                        </div>
                    </div>

                    <!-- 既存リンク警告 -->
                    <div id="existingShareAlert" class="alert alert-warning py-2 mb-3 small d-none">
                        <i class="bi bi-exclamation-triangle-fill me-1"></i>
                        このフォルダには既に有効な共有リンクが存在します。再発行すると古いリンクは即時無効化されます。
                    </div>

                    <!-- パスワード（任意） -->
                    <div class="mb-3">
                        <label class="form-label fw-bold small text-muted" for="sharePasswordInput">パスワード（任意）</label>
                        <div class="input-group input-group-sm">
                            <span class="input-group-text"><i class="bi bi-key-fill"></i></span>
                            <input type="password" class="form-control" name="share_password" id="sharePasswordInput" placeholder="設定しない場合は空欄のまま" autocomplete="new-password">
                            <button class="btn btn-outline-secondary" type="button" id="toggleModalPasswordBtn" title="パスワードを表示/非表示" aria-label="パスワードを表示/非表示">
                                <i class="bi bi-eye" id="toggleModalPasswordIcon"></i>
                            </button>
                        </div>
                        <div class="form-text small text-muted">
                            設定した場合、共有リンクを開く際にパスワードの入力が必須になります。<br>
                            <span class="text-secondary">※ アルファベット大文字・小文字・数字・特殊記号をご利用いただけます。</span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">キャンセル</button>
                    <button type="submit" class="btn btn-success btn-sm">
                        <i class="bi bi-link-45deg me-1"></i>リンクを発行
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const modalEl = document.getElementById('createShareLinkModal');
    if (!modalEl) return;

    const toggleBtn = document.getElementById('toggleModalPasswordBtn');
    const pwdInput = document.getElementById('sharePasswordInput');
    const pwdIcon = document.getElementById('toggleModalPasswordIcon');

    if (toggleBtn && pwdInput && pwdIcon) {
        toggleBtn.addEventListener('click', function () {
            if (pwdInput.type === 'password') {
                pwdInput.type = 'text';
                pwdIcon.classList.remove('bi-eye');
                pwdIcon.classList.add('bi-eye-slash');
            } else {
                pwdInput.type = 'password';
                pwdIcon.classList.remove('bi-eye-slash');
                pwdIcon.classList.add('bi-eye');
            }
        });
    }

    modalEl.addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        if (!button) return;

        const folderName = button.getAttribute('data-folder-name') || '';
        const hasExisting = button.getAttribute('data-has-existing') === '1';

        const nameInput = document.getElementById('shareLinkFolderName');
        const displaySpan = document.getElementById('shareLinkFolderDisplay');
        const alertDiv = document.getElementById('existingShareAlert');

        if (nameInput) nameInput.value = folderName;
        if (displaySpan) displaySpan.textContent = folderName;
        if (pwdInput) {
            pwdInput.value = '';
            pwdInput.type = 'password';
        }
        if (pwdIcon) {
            pwdIcon.classList.remove('bi-eye-slash');
            pwdIcon.classList.add('bi-eye');
        }
        if (alertDiv) {
            if (hasExisting) {
                alertDiv.classList.remove('d-none');
            } else {
                alertDiv.classList.add('d-none');
            }
        }
    });
});
</script>
