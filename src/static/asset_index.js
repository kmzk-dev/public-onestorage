//assetindex.php:メイン画面index.phpのJSロジック,PHPで定義されたグローバル定数に依存します
/**
 * トースト通知を表示する
 * @param {string} type - 通知のタイプ ('success', 'danger', 'warning', 'info')
 * @param {string} message - 表示するメッセージ
 * @param {boolean|null} autoHide - 自動消去するかどうか (null時はtypeに基づき自動判定: 失敗・警告動作時はfalse、成功・情報はtrue)
 */
function showToast(type, message, autoHide = null) {
    const toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) return;
    const isFailure = (type === 'danger' || type === 'warning');
    const shouldAutoHide = (autoHide !== null) ? Boolean(autoHide) : !isFailure;

    const bgColor = {
        'success': 'text-bg-success',
        'danger': 'text-bg-danger',
        'warning': 'text-bg-warning',
        'info': 'text-bg-primary'
    } [type] || 'text-bg-primary';
    const iconHtml = {
        'success': '<i class="bi bi-check-circle-fill me-2"></i>',
        'danger': '<i class="bi bi-x-octagon-fill me-2"></i>',
        'warning': '<i class="bi bi-exclamation-triangle-fill me-2"></i>',
        'info': '<i class="bi bi-info-circle-fill me-2"></i>'
    } [type] || '<i class="bi bi-info-circle-fill me-2"></i>';
    const closeBtnClass = (type === 'warning') ? 'btn-close me-2 m-auto' : 'btn-close btn-close-white me-2 m-auto';
    const autohideAttr = shouldAutoHide ? 'data-bs-autohide="true" data-bs-delay="4000"' : 'data-bs-autohide="false"';

    const toastHtml = `<div class="toast align-items-center ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" ${autohideAttr}><div class="d-flex"><div class="toast-body d-flex align-items-center">${iconHtml}<span>${message}</span></div><button type="button" class="${closeBtnClass}" data-bs-dismiss="toast" aria-label="Close"></button></div></div>`;
    const fragment = document.createRange().createContextualFragment(toastHtml);
    const toastEl = fragment.querySelector('.toast');
    toastContainer.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, {
        autohide: shouldAutoHide,
        delay: 4000
    });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => {
        toastEl.remove();
    });
}
/**
 * 名前変更モーダルの表示時に、ファイル名と拡張子を分離して入力フィールドを初期化する
 */
const renameItemModal = document.getElementById('renameItemModal');
if (renameItemModal) {
    renameItemModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const itemName = button.getAttribute('data-bs-item-name');
        const isDir = button.getAttribute('data-bs-is-dir') === '1';
        const modalTitle = renameItemModal.querySelector('.modal-title');
        const oldNameInput = renameItemModal.querySelector('#rename_old_name');
        const newNameInput = renameItemModal.querySelector('#rename_new_name');
        const extensionSpan = renameItemModal.querySelector('#rename_extension');
        const inputGroupDiv = extensionSpan.parentElement;
        modalTitle.textContent = `'${itemName}' の名前を変更`;
        oldNameInput.value = itemName;
        if (isDir) {
            newNameInput.value = itemName;
            inputGroupDiv.classList.remove('input-group');
            extensionSpan.style.display = 'none';
        } else {
            inputGroupDiv.classList.add('input-group');
            const lastDotIndex = itemName.lastIndexOf('.');
            if (lastDotIndex > 0 && lastDotIndex < itemName.length - 1) {
                newNameInput.value = itemName.substring(0, lastDotIndex);
                extensionSpan.textContent = itemName.substring(lastDotIndex);
                extensionSpan.style.display = 'inline-block';
                inputGroupDiv.classList.add('input-group');
            } else {
                newNameInput.value = itemName;
                extensionSpan.style.display = 'none';
                inputGroupDiv.classList.remove('input-group');
            }
        }
    });
}
/**
 * アイテム移動モーダルの表示時に、単体移動か一括移動かを判定して初期化する
 */
const moveItemsModal = document.getElementById('moveItemsModal');
if (moveItemsModal) {
    moveItemsModal.addEventListener('show.bs.modal', event => {
        const button = event.relatedTarget;
        const triggerBtn = button ? (button.closest('[data-bs-item-name]') || button) : null;
        const modalTitle = moveItemsModal.querySelector('.modal-title');
        const moveItemNameInput = moveItemsModal.querySelector('#move_item_name');
        const moveItemsJsonInput = moveItemsModal.querySelector('#move_items_json');

        if (triggerBtn && triggerBtn.hasAttribute('data-bs-item-name')) {
            const itemName = triggerBtn.getAttribute('data-bs-item-name');
            if (moveItemNameInput) {
                moveItemNameInput.value = itemName;
            }
            if (moveItemsJsonInput) {
                moveItemsJsonInput.value = JSON.stringify([itemName]);
            }
            if (modalTitle) {
                modalTitle.textContent = `'${itemName}' の移動`;
            }
        } else {
            // 一括移動
            if (moveItemNameInput) {
                moveItemNameInput.value = '';
            }
            const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
            const fileItems = Array.from(checkedBoxes).filter(cb => cb.getAttribute('data-is-dir') !== '1').map(cb => cb.value);
            if (moveItemsJsonInput) {
                moveItemsJsonInput.value = JSON.stringify(fileItems);
            }
            if (modalTitle) {
                modalTitle.textContent = 'アイテムの移動';
            }
        }
    });

    moveItemsModal.addEventListener('hidden.bs.modal', () => {
        const modalTitle = moveItemsModal.querySelector('.modal-title');
        const moveItemNameInput = moveItemsModal.querySelector('#move_item_name');
        const moveItemsJsonInput = moveItemsModal.querySelector('#move_items_json');
        if (moveItemNameInput) {
            moveItemNameInput.value = '';
        }
        const checkedBoxes = document.querySelectorAll('.item-checkbox:checked');
        const fileItems = Array.from(checkedBoxes).filter(cb => cb.getAttribute('data-is-dir') !== '1').map(cb => cb.value);
        if (moveItemsJsonInput) {
            moveItemsJsonInput.value = JSON.stringify(fileItems);
        }
        if (modalTitle) {
            modalTitle.textContent = 'アイテムの移動';
        }
    });
}

// ドロップダウン内の単体移動ボタンがクリックされた瞬間にデータを即時同期
document.addEventListener('click', (event) => {
    const btn = event.target.closest('.single-move-btn, [data-bs-target="#moveItemsModal"]');
    if (btn && btn.hasAttribute('data-bs-item-name')) {
        const itemName = btn.getAttribute('data-bs-item-name');
        const moveItemNameInput = document.getElementById('move_item_name');
        const moveItemsJsonInput = document.getElementById('move_items_json');
        if (moveItemNameInput) moveItemNameInput.value = itemName;
        if (moveItemsJsonInput) moveItemsJsonInput.value = JSON.stringify([itemName]);
    }
});
/**
 * DOMContentLoaded後に実行されるメインロジック。
 * トースト表示、レイアウト調整、アップロード処理、スター機能、一括操作処理を初期化する
 */
document.addEventListener('DOMContentLoaded', () => {
    // ページロード時のPHPメッセージ表示
    if (phpMessage && phpMessage.type && phpMessage.text) {
        showToast(phpMessage.type, phpMessage.text);
    }
    /**
     * サイドバーのヘッダーナビゲーションに合わせた絶対高さを計算し、スクロール可能な領域を設定する
     */
    function adjustLayout() {
        const header = document.querySelector('nav.navbar');
        const sidebar = document.getElementById('sidebarMenu');
        if (!header || !sidebar) return;

        const headerHeight = header.offsetHeight;
        sidebar.style.top = headerHeight + 'px';
        const isMobile = window.innerWidth < 768;
        const sidebarSticky = document.getElementById('sidebarScrollable');

        if (isMobile) {
            if (sidebarSticky) {
                sidebarSticky.style.height = '';
            }
            return;
        }

        // --- デスクトップ表示での絶対高さ計算 ---
        const topFixed = document.getElementById('sidebarTopFixed');
        const bottomFixed = document.getElementById('sidebarBottomFixed');
        const topFixedHeight = topFixed ? topFixed.getBoundingClientRect().height : 0;
        const bottomFixedHeight = bottomFixed ? bottomFixed.getBoundingClientRect().height : 0;
        const sidebarActualHeight = sidebar.getBoundingClientRect().height;
        const requiredHeight = sidebarActualHeight - topFixedHeight - bottomFixedHeight;

        if (sidebarSticky) {
            sidebarSticky.style.height = requiredHeight + 'px';
        }
    }

    // レイアウト調整の初期実行とリサイズ時のリスナー設定
    adjustLayout();
    window.addEventListener('resize', adjustLayout);

    const uploadFileForm = document.getElementById('uploadFileForm');
    if (uploadFileForm) {
        const uploadModalEl = document.getElementById('uploadFileModal');
        const uploadModal = new bootstrap.Modal(uploadModalEl);
        const submitBtn = document.getElementById('uploadSubmitBtn');
        const filesInput = document.getElementById('files');
        const progressContainer = document.getElementById('uploadProgressContainer');
        const progressBar = document.getElementById('uploadProgressBar');
        const uploadFileName = document.getElementById('uploadFileName');
        const uploadStatusText = document.getElementById('uploadStatusText');
        const closeBtnFooter = document.getElementById('uploadModalFooterCloseBtn');

        let isUploading = false;
        let uploadQueue = [];
        let uploadHasError = false;
        let uploadSuccessCount = 0;

        // アップロード中にモーダルを閉じようとした場合、警告してキャンセルする
        uploadModalEl.addEventListener('hide.bs.modal', function(event) {
            if (isUploading) {
                event.preventDefault();
                showToast('warning', 'アップロード処理が完了するまでモーダルを閉じることはできません。');
            }
        });

        // アップロードフォームの送信処理 (キューイング開始)
        uploadFileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            if (isUploading) return;

            if (filesInput.files.length === 0) {
                showToast('warning', 'ファイルが選択されていません。');
                return;
            }

            uploadHasError = false;
            uploadSuccessCount = 0;
            uploadQueue = Array.from(filesInput.files);
            processUploadQueue();
        });
        /**
         * アップロードキューのファイルがなくなるまで順次処理する
         */
        async function processUploadQueue() {
            if (uploadQueue.length === 0) {
                isUploading = false;
                setUploadUiState(false);
                if (uploadHasError) {
                    // 失敗が発生した場合は自動リロードせず、エラー用トーストを表示したままにする
                    if (uploadSuccessCount > 0) {
                        showToast('warning', `一部のファイルアップロードに失敗しました。(${uploadSuccessCount}件成功)`);
                    }
                    return;
                }
                showToast('success', 'すべてのファイルのアップロードが完了しました。');
                setTimeout(() => {
                    location.reload();
                }, 800);
                return;
            }

            isUploading = true;
            setUploadUiState(true);
            const file = uploadQueue.shift();
            if (file.name.startsWith('.')) {
                uploadHasError = true;
                showToast('warning', `[${file.name}] はドットで始まるためスキップされました。`);
                processUploadQueue();
                return;
            }
            const success = await uploadFileInChunks(file);
            if (success) {
                uploadSuccessCount++;
            } else {
                uploadHasError = true;
            }
            processUploadQueue();
        }
        /**
         * 単一のファイルをチャンクに分割し、サーバーにアップロードする
         */
        async function uploadFileInChunks(file) {
            const CHUNK_SIZE = 5 * 1024 * 1024; // 5MB per chunk
            const totalChunks = Math.ceil(file.size / CHUNK_SIZE);
            let chunkIndex = 0;

            updateProgress(0, file.name, `(1/${totalChunks})`);

            for (chunkIndex = 0; chunkIndex < totalChunks; chunkIndex++) {
                const start = chunkIndex * CHUNK_SIZE;
                const end = Math.min(start + CHUNK_SIZE, file.size);
                const chunk = file.slice(start, end);

                const formData = new FormData();
                formData.append('action', 'upload_chunk');
                formData.append('path', document.getElementById('upload_path').value);
                formData.append('chunk', chunk, file.name);
                formData.append('original_name', file.name);
                formData.append('chunk_index', chunkIndex);
                formData.append('total_chunks', totalChunks);
                formData.append('total_size', file.size);

                try {
                    const response = await fetch('index.php', {
                        method: 'POST',
                        body: formData
                    });

                    if (!response.ok) {
                        throw new Error('サーバーエラーが発生しました。');
                    }

                    const data = await response.json();

                    if (data.type === 'danger' || data.type === 'warning') {
                        throw new Error(data.text);
                    }

                    if (data.type === 'success') {
                        updateProgress(100, file.name, '完了');
                    } else {
                        const progress = Math.round(((chunkIndex + 1) / totalChunks) * 100);
                        updateProgress(progress, file.name, `(${chunkIndex + 2 > totalChunks ? totalChunks : chunkIndex + 2}/${totalChunks})`);
                    }

                } catch (error) {
                    showToast('danger', `[${file.name}] のアップロードに失敗しました: ${error.message}`);
                    setUploadUiState(false);
                    isUploading = false;
                    uploadQueue = [];
                    uploadHasError = true;
                    return false;
                }
            }
            return true;
        }
        /**
         * アップロード中のUI要素 (ボタン、プログレスバー) の状態を切り替える
         */
        function setUploadUiState(uploading) {
            isUploading = uploading;
            submitBtn.disabled = uploading;
            closeBtnFooter.disabled = uploading;
            filesInput.disabled = uploading;

            if (uploading) {
                progressContainer.classList.remove('d-none');
                submitBtn.innerHTML = `<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> アップロード中...`;
            } else {
                progressContainer.classList.add('d-none');
                submitBtn.innerHTML = 'アップロード';
                uploadFileForm.reset();
            }
        }
        /**
         * プログレスバーの表示を更新する
         */
        function updateProgress(percentage, name, status) {
            uploadFileName.textContent = name;
            progressBar.style.width = percentage + '%';
            progressBar.setAttribute('aria-valuenow', percentage);
            progressBar.textContent = percentage + '%';
        }

    // --- ドラッグ＆ドロップ アップロード処理 ---
    const dragOverlay = document.getElementById('dragDropOverlay');
    const headerUploadBtn = document.getElementById('headerUploadBtn');
    let dragCounter = 0;

    window.addEventListener('dragenter', (e) => {
        // ファイルドラッグ時のみ反応
        if (e.dataTransfer && e.dataTransfer.types && Array.from(e.dataTransfer.types).includes('Files')) {
            dragCounter++;
            if (dragOverlay) dragOverlay.classList.add('is-active');
        }
    });

    window.addEventListener('dragleave', (e) => {
        dragCounter--;
        if (dragCounter <= 0) {
            dragCounter = 0;
            if (dragOverlay) dragOverlay.classList.remove('is-active');
        }
    });

    window.addEventListener('dragover', (e) => {
        e.preventDefault();
    });

    window.addEventListener('drop', (e) => {
        e.preventDefault();
        dragCounter = 0;
        if (dragOverlay) dragOverlay.classList.remove('is-active');

        // アップロード不可画面のチェック
        if (headerUploadBtn && headerUploadBtn.disabled) {
            showToast('warning', '現在の画面ではアップロードできません。フォルダを開いてからアップロードしてください。');
            return;
        }

        const droppedFiles = e.dataTransfer ? e.dataTransfer.files : null;
        if (droppedFiles && droppedFiles.length > 0) {
            const uploadModalEl = document.getElementById('uploadFileModal');
            if (uploadModalEl) {
                const modalInstance = bootstrap.Modal.getOrCreateInstance(uploadModalEl);
                modalInstance.show();

                uploadHasError = false;
                uploadSuccessCount = 0;
                uploadQueue = Array.from(droppedFiles);
                processUploadQueue();
            }
        }
    });
    }
    /**
     * スターボタンのクリックイベントを処理し、スターAPIを呼び出す
     */
    const starToggleBtns = document.querySelectorAll('.star-toggle-btn');
    starToggleBtns.forEach(button => {
        button.addEventListener('click', async (e) => {
            e.preventDefault();
            const webPath = button.getAttribute('data-web-path');
            const itemName = button.getAttribute('data-item-name');
            const isDir = button.getAttribute('data-is-dir') === '1';
            const icon = button.querySelector('i');

            const originalIconClass = icon.className;
            const originalTitle = button.title;
            icon.className = 'bi bi-arrow-repeat spin-animation text-info';
            button.disabled = true;

            try {
                const response = await fetch(STAR_API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        action: 'toggle_star',
                        data: {
                            web_path: webPath,
                            item_name: itemName,
                            is_dir: isDir
                        }
                    })
                });

                const data = await response.json();

                if (data.success) {
                    showToast('success', data.message);

                    // UIを更新
                    if (data.action === 'added') {
                        icon.className = 'bi bi-star-fill text-warning';
                        button.title = 'スターを解除';
                    } else if (data.action === 'removed') {
                        icon.className = 'bi bi-star text-muted';
                        button.title = 'スターに登録';

                        // スタービューの場合はアイテムをリストから削除し、ページをリロードしてリストを更新
                        if (isStarView) {
                            const row = button.closest('.file-row');
                            if (row) {
                                row.remove();
                            }
                            // リストが空になったら再読み込みして「ファイルがありません」を表示
                            if (document.querySelectorAll('.file-row').length === 0) {
                                location.reload();
                            }
                        }
                    }
                } else {
                    showToast('danger', data.message);
                    icon.className = originalIconClass;
                    button.title = originalTitle;
                }
            } catch (error) {
                showToast('danger', `スター操作中にエラーが発生しました: ${error.message}`);
                icon.className = originalIconClass;
                button.title = originalTitle;
            } finally {
                button.disabled = false;
                if (icon.className.includes('spin-animation')) {
                    icon.className = icon.className.replace(' spin-animation', '');
                }
            }
        });
    });

    // --- ローディングアニメーション ---
    const style = document.createElement('style');
    style.textContent = `
    .spin-animation {
        animation: spin 1s linear infinite;
    }
    @keyframes spin {
        from { transform: rotate(0deg); }
        to { transform: rotate(360deg); }
    }
`;
    document.head.appendChild(style);
    /**
     * アイテムの選択状態に応じて、パンくずリストと一括操作アクションヘッダーの表示を切り替える
     */
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const itemCheckboxes = document.querySelectorAll('.item-checkbox');
    const breadcrumbContainer = document.getElementById('breadcrumbContainer');
    const tableActionsContainer = document.getElementById('tableActionsContainer');
    const selectionCountSpan = document.getElementById('selectionCount');
    const moveItemsJsonInput = document.getElementById('move_items_json');
    const batchDeleteBtn = document.getElementById('batchDeleteBtn');
    const batchDeleteForm = document.getElementById('batchDeleteForm');
    const deleteItemsJsonInput = document.getElementById('delete_items_json');
    function updateActionHeader() {
        const selectedItems = Array.from(itemCheckboxes).filter(cb => cb.checked && !cb.disabled);
        const count = selectedItems.length;
        if (count > 0) {
            breadcrumbContainer.classList.add('d-none');
            tableActionsContainer.classList.remove('d-none');
            selectionCountSpan.textContent = count;
            // フォルダは移動対象外とし、ファイルのみを移動用JSONに設定
            const fileOnlyItems = selectedItems.filter(cb => cb.getAttribute('data-is-dir') !== '1');
            if (moveItemsJsonInput) {
                moveItemsJsonInput.value = JSON.stringify(fileOnlyItems.map(cb => cb.value));
            }
            // 削除用JSONも更新
            if (deleteItemsJsonInput) {
                deleteItemsJsonInput.value = JSON.stringify(selectedItems.map(cb => cb.value));
            }
        } else {
            breadcrumbContainer.classList.remove('d-none');
            tableActionsContainer.classList.add('d-none');
            if (moveItemsJsonInput) {
                moveItemsJsonInput.value = '[]';
            }
            if (deleteItemsJsonInput) {
                deleteItemsJsonInput.value = '[]';
            }
        }
    }

    // --- チェックボックスイベントリスナー ---
    let lastCheckedCheckbox = null;
    const activeCheckboxes = Array.from(itemCheckboxes).filter(cb => !cb.disabled);

    if (selectAllCheckbox) {
        if (activeCheckboxes.length === 0) {
            selectAllCheckbox.disabled = true;
        }

        // 全選択チェックボックス
        selectAllCheckbox.addEventListener('change', (e) => {
            itemCheckboxes.forEach(checkbox => {
                if (!checkbox.disabled) {
                    checkbox.checked = e.target.checked;
                }
            });
            lastCheckedCheckbox = null;
            updateActionHeader();
        });
    }

    // 個別アイテムのチェックボックス（Shiftクリックによる範囲選択対応）
    const checkboxesArray = Array.from(itemCheckboxes);
    checkboxesArray.forEach(checkbox => {
        checkbox.addEventListener('click', (e) => {
            if (e.shiftKey && lastCheckedCheckbox && lastCheckedCheckbox !== checkbox) {
                const startIndex = checkboxesArray.indexOf(lastCheckedCheckbox);
                const endIndex = checkboxesArray.indexOf(checkbox);

                const minIndex = Math.min(startIndex, endIndex);
                const maxIndex = Math.max(startIndex, endIndex);

                const targetState = checkbox.checked;

                for (let i = minIndex; i <= maxIndex; i++) {
                    if (!checkboxesArray[i].disabled) {
                        checkboxesArray[i].checked = targetState;
                    }
                }

                // Shiftクリック時のブラウザによる余計なテキスト選択をクリア
                if (window.getSelection) {
                    window.getSelection().removeAllRanges();
                }
            }

            lastCheckedCheckbox = checkbox;

            if (selectAllCheckbox) {
                const currentActive = checkboxesArray.filter(cb => !cb.disabled);
                const allChecked = currentActive.length > 0 && currentActive.every(cb => cb.checked);
                selectAllCheckbox.checked = allChecked;
            }

            updateActionHeader();
        });
    });
    
    // --- 一括削除ボタンのイベントリスナー ---
    if (batchDeleteBtn) {
        batchDeleteBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const selectedItems = Array.from(itemCheckboxes).filter(cb => cb.checked).map(cb => cb.value);
            const count = selectedItems.length;

            if (count === 0) {
                showToast('warning', '削除するアイテムを選択してください。');
                return;
            }

            const confirmMessage = `本当に選択された ${count} 個のアイテムを削除しますか？\nこの操作は元に戻せません。`;

            if (confirm(confirmMessage)) {
                deleteItemsJsonInput.value = JSON.stringify(selectedItems);
                const formData = new FormData(batchDeleteForm);
                formData.append('ajax', '1');
                fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(res => res.json()).then(data => {
                    if (data.type === 'danger' || data.type === 'warning') {
                        // 失敗・警告時は再読み込みせず、トーストを手動消去まで表示
                        showToast(data.type, data.text);
                    } else {
                        showToast('success', data.text);
                        setTimeout(() => location.reload(), 600);
                    }
                }).catch(err => {
                    showToast('danger', '一括削除処理中に通信エラーが発生しました: ' + err.message);
                });
            }
        });
    }

    // --- 単体削除フォームのAJAX送信 (失敗時はリロードしない) ---
    document.addEventListener('submit', function(e) {
        const form = e.target;
        const actionInput = form.querySelector('input[name="action"]');
        if (actionInput && (actionInput.value === 'delete_item' || actionInput.value === 'delete_sharebox_folder')) {
            e.preventDefault();
            const formData = new FormData(form);
            formData.append('ajax', '1');
            fetch('index.php', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(res => res.json()).then(data => {
                if (data.type === 'danger' || data.type === 'warning') {
                    showToast(data.type, data.text);
                } else {
                    showToast('success', data.text);
                    setTimeout(() => location.reload(), 600);
                }
            }).catch(err => {
                showToast('danger', '削除処理中に通信エラーが発生しました: ' + err.message);
            });
        }
    });

    // --- フォルダ作成モーダルのAJAX送信 (失敗時はリロードしない) ---
    const createFolderModalEl = document.getElementById('createFolderModal');
    if (createFolderModalEl) {
        const createForm = createFolderModalEl.querySelector('form');
        if (createForm) {
            createForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(createForm);
                formData.append('ajax', '1');
                fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(res => res.json()).then(data => {
                    const modalInst = bootstrap.Modal.getInstance(createFolderModalEl);
                    if (modalInst) modalInst.hide();

                    if (data.type === 'danger' || data.type === 'warning') {
                        showToast(data.type, data.text);
                    } else {
                        showToast('success', data.text);
                        setTimeout(() => location.reload(), 600);
                    }
                }).catch(err => {
                    showToast('danger', 'フォルダ作成中に通信エラーが発生しました: ' + err.message);
                });
            });
        }
    }

    // --- 名前変更モーダルのAJAX送信 (失敗時はリロードしない) ---
    const renameModalEl = document.getElementById('renameItemModal');
    if (renameModalEl) {
        const renameForm = renameModalEl.querySelector('form');
        if (renameForm) {
            renameForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(renameForm);
                formData.append('ajax', '1');
                fetch('index.php', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(res => res.json()).then(data => {
                    const modalInst = bootstrap.Modal.getInstance(renameModalEl);
                    if (modalInst) modalInst.hide();

                    if (data.type === 'danger' || data.type === 'warning') {
                        showToast(data.type, data.text);
                    } else {
                        showToast('success', data.text);
                        setTimeout(() => location.reload(), 600);
                    }
                }).catch(err => {
                    showToast('danger', '名前変更中に通信エラーが発生しました: ' + err.message);
                });
            });
        }
    }
    
    // --- サイドバーのフォルダツリー開閉トグル ---
    const sidebarNav = document.getElementById('sidebarMenu');
    /**
     * フォルダツリーの開閉アイコンを切り替える (BootstrapのCollapseイベントに連動)
     */
    if (sidebarNav) {
        sidebarNav.querySelectorAll('.toggle-icon').forEach(icon => {
            const targetCollapse = document.querySelector(icon.getAttribute('data-bs-target'));
            if (targetCollapse) {
                targetCollapse.addEventListener('show.bs.collapse', () => {
                    icon.textContent = '▾';
                });
                targetCollapse.addEventListener('hide.bs.collapse', () => {
                    icon.textContent = '▸';
                });
            }
        });
    }

    // --- サイドバー 特殊BOXES (INBOX / SHARE BOX) アコーディオンの開閉状態永続化 ---
    const boxesCollapse = document.getElementById('boxesCollapse');
    const boxesCollapseToggle = document.getElementById('boxesCollapseToggle');
    if (boxesCollapse && boxesCollapseToggle) {
        const isBoxesActive = boxesCollapse.classList.contains('show');
        if (!isBoxesActive) {
            const savedState = localStorage.getItem('onestorage_boxes_accordion_open');
            if (savedState === 'true') {
                boxesCollapse.classList.add('show');
                boxesCollapseToggle.setAttribute('aria-expanded', 'true');
            } else if (savedState === 'false') {
                boxesCollapse.classList.remove('show');
                boxesCollapseToggle.setAttribute('aria-expanded', 'false');
            }
            adjustLayout();
        } else {
            localStorage.setItem('onestorage_boxes_accordion_open', 'true');
        }

        boxesCollapse.addEventListener('shown.bs.collapse', () => {
            localStorage.setItem('onestorage_boxes_accordion_open', 'true');
            boxesCollapseToggle.setAttribute('aria-expanded', 'true');
            adjustLayout();
        });

        boxesCollapse.addEventListener('hidden.bs.collapse', () => {
            localStorage.setItem('onestorage_boxes_accordion_open', 'false');
            boxesCollapseToggle.setAttribute('aria-expanded', 'false');
            adjustLayout();
        });
    }

    // --- RECENTビュー: 除外および復元処理 ---
    if (typeof isRecentView !== 'undefined' && isRecentView) {
        // 各アイテムの「RECENTから除外」ボタン
        document.querySelectorAll('.recent-exclude-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                const webPath = this.getAttribute('data-web-path') || '';
                const itemName = this.getAttribute('data-item-name') || '';

                if (!itemName) return;

                fetch(RECENT_API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'exclude',
                        data: { web_path: webPath, item_name: itemName }
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message || 'RECENTから除外しました。');
                        setTimeout(() => location.reload(), 400);
                    } else {
                        showToast('danger', data.message || '除外処理に失敗しました。');
                    }
                })
                .catch(err => {
                    showToast('danger', '通信エラーが発生しました: ' + err.message);
                });
            });
        });

        // モーダル内の復元状態追跡フラグ
        let hasRestoredItems = false;
        const exclusionsModalEl = document.getElementById('recentExclusionsModal');

        if (exclusionsModalEl) {
            // モーダルが閉じられた時に、変更があれば画面をリロードして反映
            exclusionsModalEl.addEventListener('hidden.bs.modal', function() {
                if (hasRestoredItems) {
                    location.reload();
                }
            });
        }

        // モーダル内の「元に戻す」ボタン
        const exclusionsContainer = document.getElementById('recentExclusionsListContainer');
        if (exclusionsContainer) {
            exclusionsContainer.addEventListener('click', function(e) {
                const restoreBtn = e.target.closest('.restore-exclusion-btn');
                if (!restoreBtn || restoreBtn.disabled) return;

                const webPath = restoreBtn.getAttribute('data-web-path') || '';
                const itemName = restoreBtn.getAttribute('data-item-name') || '';

                if (!itemName) return;

                const row = restoreBtn.closest('.exclusion-item-row');
                restoreBtn.disabled = true;
                restoreBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>解除中...';

                fetch(RECENT_API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'restore',
                        data: { web_path: webPath, item_name: itemName }
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        hasRestoredItems = true;
                        showToast('success', data.message || '除外を解除しました。');
                        
                        // 行をアニメーション付きで削除
                        if (row) {
                            row.style.transition = 'opacity 0.2s ease, transform 0.2s ease';
                            row.style.opacity = '0';
                            row.style.transform = 'translateX(20px)';
                            setTimeout(() => {
                                row.remove();
                                // 残りの行数チェック
                                const remainingRows = document.querySelectorAll('.exclusion-item-row');
                                if (remainingRows.length === 0) {
                                    const listGroup = document.getElementById('recentExclusionsList');
                                    if (listGroup) listGroup.remove();
                                    exclusionsContainer.innerHTML = `
                                        <div class="h-100 d-flex flex-column justify-content-center align-items-center text-muted py-5" id="emptyExclusionsMessage">
                                            <i class="bi bi-check2-circle display-4 d-block mb-3 text-success opacity-50"></i>
                                            <span class="fs-6">現在、除外されたファイルはありません</span>
                                        </div>
                                    `;
                                    const clearAllBtn = document.getElementById('clearAllExclusionsBtn');
                                    if (clearAllBtn) clearAllBtn.classList.add('d-none');
                                }
                            }, 200);
                        }

                        // バッジ件数を更新
                        const badge = document.getElementById('recentExclusionBadge');
                        if (badge && typeof data.exclusion_count !== 'undefined') {
                            badge.textContent = data.exclusion_count;
                        }
                    } else {
                        restoreBtn.disabled = false;
                        restoreBtn.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i>元に戻す';
                        showToast('danger', data.message || '除外解除処理に失敗しました。');
                    }
                })
                .catch(err => {
                    restoreBtn.disabled = false;
                    restoreBtn.innerHTML = '<i class="bi bi-arrow-counterclockwise me-1"></i>元に戻す';
                    showToast('danger', '通信エラーが発生しました: ' + err.message);
                });
            });
        }

        // モーダル内の「すべて元に戻す」ボタン
        const clearAllBtn = document.getElementById('clearAllExclusionsBtn');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function() {
                if (!confirm('除外したすべてのファイルを元に戻しますか？')) return;

                clearAllBtn.disabled = true;

                fetch(RECENT_API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'clear_all' })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        hasRestoredItems = true;
                        showToast('success', data.message || 'すべての除外を解除しました。');
                        
                        const listGroup = document.getElementById('recentExclusionsList');
                        if (listGroup) listGroup.remove();
                        if (exclusionsContainer) {
                            exclusionsContainer.innerHTML = `
                                <div class="h-100 d-flex flex-column justify-content-center align-items-center text-muted py-5" id="emptyExclusionsMessage">
                                    <i class="bi bi-check2-circle display-4 d-block mb-3 text-success opacity-50"></i>
                                    <span class="fs-6">現在、除外されたファイルはありません</span>
                                </div>
                            `;
                        }
                        clearAllBtn.classList.add('d-none');

                        // バッジ件数を更新
                        const badge = document.getElementById('recentExclusionBadge');
                        if (badge) {
                            badge.textContent = '0';
                        }
                    } else {
                        clearAllBtn.disabled = false;
                        showToast('danger', data.message || '全解除処理に失敗しました。');
                    }
                })
                .catch(err => {
                    clearAllBtn.disabled = false;
                    showToast('danger', '通信エラーが発生しました: ' + err.message);
                });
            });
        }

        // 複数選択時の一括除外ボタン
        const batchExcludeBtn = document.getElementById('batchExcludeBtn');
        if (batchExcludeBtn) {
            batchExcludeBtn.addEventListener('click', function(e) {
                e.preventDefault();
                const checkedBoxes = Array.from(document.querySelectorAll('.item-checkbox:checked'));
                if (checkedBoxes.length === 0) {
                    showToast('warning', '除外するアイテムを選択してください。');
                    return;
                }

                const items = checkedBoxes.map(cb => ({
                    web_path: cb.getAttribute('data-web-path') || '',
                    item_name: cb.value || ''
                })).filter(it => it.item_name !== '');

                if (items.length === 0) {
                    showToast('warning', '有効なアイテムが選択されていません。');
                    return;
                }

                if (!confirm(`選択された ${items.length} 個のアイテムをRECENTから除外（非表示化）しますか？\n（※実ファイルは削除されず、後からいつでも元に戻せます）`)) {
                    return;
                }

                fetch(RECENT_API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'batch_exclude',
                        data: { items: items }
                    })
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        showToast('success', data.message || `${items.length}件のアイテムを除外しました。`);
                        setTimeout(() => location.reload(), 400);
                    } else {
                        showToast('danger', data.message || '一括除外処理に失敗しました。');
                    }
                })
                .catch(err => {
                    showToast('danger', '通信エラーが発生しました: ' + err.message);
                });
            });
        }
    }

    // --- 画像ビューアーロジック ---
    const imageModalEl = document.getElementById('imageViewerModal');
    if (imageModalEl) {
        // スタッキングコンテキスト対策（body直下に配置）
        if (imageModalEl.parentNode !== document.body) {
            document.body.appendChild(imageModalEl);
        }

        const imageModalInstance = bootstrap.Modal.getOrCreateInstance(imageModalEl, {
            backdrop: true,
            keyboard: true
        });

        const imgEl = document.getElementById('imageViewerImg');
        const spinnerEl = document.getElementById('imageViewerSpinner');
        const fileNameEl = document.getElementById('imageViewerFileName');
        const prevBtn = document.getElementById('imageViewerPrevBtn');
        const nextBtn = document.getElementById('imageViewerNextBtn');
        const closeBtn = document.getElementById('imageViewerCloseBtn');

        let imageList = [];
        let currentIndex = -1;
        let debounceTimer = null;
        let preloadPrev = null;
        let preloadNext = null;

        // プリロードの破棄
        const clearPreloads = () => {
            if (preloadPrev) {
                preloadPrev.onload = null;
                preloadPrev.onerror = null;
                preloadPrev.src = '';
                preloadPrev = null;
            }
            if (preloadNext) {
                preloadNext.onload = null;
                preloadNext.onerror = null;
                preloadNext.src = '';
                preloadNext = null;
            }
        };

        // 前後1枚のみ先読み
        const updatePreload = (index) => {
            clearPreloads();
            if (index > 0 && imageList[index - 1]) {
                preloadPrev = new Image();
                preloadPrev.src = imageList[index - 1].url;
            }
            if (index < imageList.length - 1 && imageList[index + 1]) {
                preloadNext = new Image();
                preloadNext.src = imageList[index + 1].url;
            }
        };

        // UI（ボタン表示・ファイル名）の即時更新
        const updateNavUi = (index) => {
            if (index < 0 || index >= imageList.length) return;
            const item = imageList[index];
            if (fileNameEl) {
                fileNameEl.textContent = item.name;
                fileNameEl.title = item.name;
            }
            if (prevBtn) {
                prevBtn.style.display = index > 0 ? 'block' : 'none';
            }
            if (nextBtn) {
                nextBtn.style.display = index < imageList.length - 1 ? 'block' : 'none';
            }
        };

        // 実際の画像読み込みリクエスト処理
        const loadImage = (index) => {
            if (index < 0 || index >= imageList.length) return;
            const item = imageList[index];

            if (spinnerEl) spinnerEl.classList.remove('d-none');
            if (imgEl) {
                imgEl.classList.add('d-none');
                imgEl.onload = () => {
                    if (spinnerEl) spinnerEl.classList.add('d-none');
                    imgEl.classList.remove('d-none');
                    // ロード完了後に前後1枚を先読み
                    updatePreload(index);
                };
                imgEl.onerror = () => {
                    if (spinnerEl) spinnerEl.classList.add('d-none');
                    if (fileNameEl) {
                        fileNameEl.textContent = `${item.name} (画像の読み込みに失敗しました)`;
                    }
                };
                imgEl.src = item.url;
            }
        };

        // 画像切り替え（デバウンスによる連打・過剰リクエスト抑制）
        const navigateTo = (index, immediate = false) => {
            if (index < 0 || index >= imageList.length) return;
            currentIndex = index;
            updateNavUi(currentIndex);

            // デバウンスタイマーのクリア
            if (debounceTimer) {
                clearTimeout(debounceTimer);
                debounceTimer = null;
            }

            if (spinnerEl) spinnerEl.classList.remove('d-none');
            if (imgEl) imgEl.classList.add('d-none');

            if (immediate) {
                loadImage(currentIndex);
            } else {
                debounceTimer = setTimeout(() => {
                    loadImage(currentIndex);
                }, 200); // 200msのデバウンスで連打時のサーバー復号負荷を防止
            }
        };

        // 画像リンクのクリックハンドラ (Event Delegation)
        document.addEventListener('click', (e) => {
            const trigger = e.target.closest('a.image-preview-trigger');
            if (!trigger) return;

            // Ctrl/Cmd/Shiftキー押下時は通常リンク動作（別タブ表示）を許可
            if (e.ctrlKey || e.metaKey || e.shiftKey) return;

            e.preventDefault();

            // 他のモーダル（検索等）が開いていたら閉じる
            const activeModal = trigger.closest('.modal');
            if (activeModal && activeModal.id !== 'imageViewerModal') {
                const activeModalInstance = bootstrap.Modal.getInstance(activeModal);
                if (activeModalInstance) {
                    activeModalInstance.hide();
                }
            }

            // DOM上にレンダリングされている画像トリガー要素からリストを動的抽出
            const triggerEls = Array.from(document.querySelectorAll('a.image-preview-trigger'));
            imageList = triggerEls.map(el => ({
                url: el.getAttribute('href'),
                name: el.getAttribute('data-image-name') || el.getAttribute('title') || ''
            })).filter(item => item.url);

            const clickedUrl = trigger.getAttribute('href');
            currentIndex = imageList.findIndex(item => item.url === clickedUrl);

            if (currentIndex !== -1) {
                imageModalInstance.show();
                navigateTo(currentIndex, true);
            }
        });

        // ナビゲーションボタン操作
        if (prevBtn) {
            prevBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (currentIndex > 0) {
                    navigateTo(currentIndex - 1);
                }
            });
        }
        if (nextBtn) {
            nextBtn.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                if (currentIndex < imageList.length - 1) {
                    navigateTo(currentIndex + 1);
                }
            });
        }
        if (closeBtn) {
            closeBtn.addEventListener('click', (e) => {
                e.preventDefault();
                imageModalInstance.hide();
            });
        }

        // キーボード操作（初期化時に1度だけ登録、モーダル展開時のみ判定して動作）
        document.addEventListener('keydown', (e) => {
            if (!imageModalEl.classList.contains('show')) return;

            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                if (currentIndex > 0) {
                    navigateTo(currentIndex - 1);
                }
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                if (currentIndex < imageList.length - 1) {
                    navigateTo(currentIndex + 1);
                }
            }
        });

        // モーダルが閉じた際のメモリ解放処理
        imageModalEl.addEventListener('hidden.bs.modal', () => {
            if (debounceTimer) {
                clearTimeout(debounceTimer);
                debounceTimer = null;
            }
            clearPreloads();
            if (imgEl) {
                imgEl.onload = null;
                imgEl.onerror = null;
                imgEl.src = ''; // デコードされた画像リソースの参照を解放
                imgEl.classList.add('d-none');
            }
            if (spinnerEl) spinnerEl.classList.add('d-none');
            if (fileNameEl) fileNameEl.textContent = '';
            currentIndex = -1;
            imageList = [];
        });

        // アクセシビリティ対応：非表示開始時にフォーカス解除
        imageModalEl.addEventListener('hide.bs.modal', () => {
            if (document.activeElement && imageModalEl.contains(document.activeElement)) {
                document.activeElement.blur();
            }
        });
    }

});