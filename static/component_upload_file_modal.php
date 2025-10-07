<div class="modal fade" id="uploadFileModal" tabindex="-1" data-bs-backdrop="true" data-bs-keyboard="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header bg-dark text-light">
                <h5 class="modal-title">ファイルのアップロード</h5>
            </div>
            <form id="uploadFileForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <span class="fw-bold">ファイルアップロード先:</span>
                        <p class="mb-1 text-break">
                            <i class="bi bi-folder-fill me-1"></i>
                            home/<?= htmlspecialchars(empty($web_path) ? '' : $web_path, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    </div>
                    <input type="hidden" name="path" id="upload_path" value="<?= htmlspecialchars($web_path, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <input class="form-control" type="file" id="files" name="files[]" multiple required accept="<?= htmlspecialchars($accept_attribute, ENT_QUOTES, 'UTF-8') ?>">
                    </div>

                    <div id="uploadProgressContainer" class="d-none">
                        <p class="mb-1 small" id="uploadFileName"></p>
                        <div class="progress" style="height: 20px;">
                            <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100">0%</div>
                        </div>
                        <p class="text-muted text-end small mb-0" id="uploadStatusText"></p>
                    </div>

                    <div class="form-text mt-3">
                        <span class="fw-bold">許可する拡張子:</br></span>
                        <div class="alert alert-light" role="alert">
                            <?= !empty($file_config['allowed_extensions']) ? '' . htmlspecialchars(implode(', ', $file_config['allowed_extensions']), ENT_QUOTES, 'UTF-8') . '<br>' : '' ?>
                        </div>
                        <ul>
                            <li>ファイルサイズ上限: </span><?= htmlspecialchars($file_config['max_file_size_mb'], ENT_QUOTES, 'UTF-8') ?> MB</li>
                            <li> . で始まる名前のファイルはアップロードできません</li>
                            <li>フォルダはアップロードできません</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" id="uploadModalFooterCloseBtn">閉じる</button>
                    <button type="submit" id="uploadSubmitBtn" class="btn btn-primary">アップロード</button>
                </div>
            </form>
        </div>
    </div>
</div>