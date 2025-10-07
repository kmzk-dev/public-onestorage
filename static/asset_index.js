//assetindex.php:メイン画面index.phpのJSロジック,PHPで定義されたグローバル定数に依存します
/**
 * トースト通知を表示する
 * @param {string} type - 通知のタイプ ('success', 'danger', 'warning', 'info')
 * @param {string} message - 表示するメッセージ
 */
function showToast(type, message) {
    const toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) return;
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
    const toastHtml = `<div class="toast align-items-center ${bgColor} border-0" role="alert" aria-live="assertive" aria-atomic="true" data-bs-delay="3000"><div class="d-flex"><div class="toast-body d-flex align-items-center">${iconHtml}<span>${message}</span></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div></div>`;
    const fragment = document.createRange().createContextualFragment(toastHtml);
    const toastEl = fragment.querySelector('.toast');
    toastContainer.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl);
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

            uploadQueue = Array.from(filesInput.files);
            processUploadQueue();
        });
        /**
         * アップロードキューのファイルがなくなるまで順次処理する
         */
        async function processUploadQueue() {
            if (uploadQueue.length === 0) {
                isUploading = false;
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
                showToast('warning', `[${file.name}] はドットで始まるためスキップされました。`);
                processUploadQueue();
                return;
            }
            await uploadFileInChunks(file);
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
                    uploadQueue = [];
                    return;
                }
            }
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
        const selectedItems = Array.from(itemCheckboxes).filter(cb => cb.checked);
        const count = selectedItems.length;
        if (count > 0) {
            breadcrumbContainer.classList.add('d-none');
            tableActionsContainer.classList.remove('d-none');
            selectionCountSpan.textContent = count;
            if (moveItemsJsonInput) {
                moveItemsJsonInput.value = JSON.stringify(selectedItems.map(cb => cb.value));
            }
            // 削除用JSONも更新
            if (deleteItemsJsonInput) {
                deleteItemsJsonInput.value = JSON.stringify(selectedItems.map(cb => cb.value));
            }
        } else {
            breadcrumbContainer.classList.remove('d-none');
            tableActionsContainer.classList.add('d-none');
        }
    }

    // --- チェックボックスイベントリスナー ---
    if (selectAllCheckbox) {
        // 全選択チェックボックス
        selectAllCheckbox.addEventListener('change', (e) => {
            itemCheckboxes.forEach(checkbox => {
                checkbox.checked = e.target.checked;
            });
            updateActionHeader();
        });
    }
    // 個別アイテムのチェックボックス
    itemCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            if (!checkbox.checked) {
                selectAllCheckbox.checked = false;
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
                // フォームを送信
                batchDeleteForm.submit();
            }
        });
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
});