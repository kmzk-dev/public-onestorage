<?php
// static/component_image_viewer_modal.php: 画像プレビュー用フルスクリーンモーダル
?>
<!-- 画像プレビューモーダル -->
<div class="modal fade" id="imageViewerModal" tabindex="-1" aria-labelledby="imageViewerModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-fullscreen m-0 p-0">
        <div class="modal-content bg-dark border-0 rounded-0 h-100 position-relative text-white user-select-none" data-bs-theme="dark" style="background-color: rgba(0, 0, 0, 0.95) !important;">
            
            <style>
                .image-viewer-btn-hover:hover {
                    opacity: 1 !important;
                    transform: scale(1.15);
                    color: #ffffff !important;
                }
                .image-viewer-nav-btn:hover {
                    opacity: 1 !important;
                    transform: translateY(-50%) scale(1.15) !important;
                    color: #ffffff !important;
                }
            </style>

            <!-- 上部バー（ファイル名・閉じるボタン） -->
            <div class="position-absolute top-0 start-0 w-100 d-flex justify-content-between align-items-center px-3 px-md-4 py-3" style="z-index: 1065; background: linear-gradient(to bottom, rgba(0,0,0,0.85) 0%, rgba(0,0,0,0) 100%); pointer-events: auto;">
                <div class="text-truncate pe-3 d-flex align-items-center" style="max-width: 85%;">
                    <i class="bi bi-image me-2 text-info fs-5 flex-shrink-0"></i>
                    <span id="imageViewerFileName" class="text-truncate fw-semibold" style="text-shadow: 1px 1px 3px rgba(0,0,0,0.8); font-size: 1.05rem;"></span>
                </div>
                <button type="button" class="btn btn-link text-white p-2 fs-3 text-decoration-none border-0 lh-1 d-flex align-items-center justify-content-center image-viewer-btn-hover" data-bs-dismiss="modal" aria-label="閉じる" id="imageViewerCloseBtn" style="filter: drop-shadow(0px 0px 4px rgba(0,0,0,0.9)); opacity: 0.85; transition: opacity 0.15s, transform 0.15s; cursor: pointer;">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>

            <!-- 画像コンテナ（画面中央配置） -->
            <div class="d-flex justify-content-center align-items-center h-100 w-100 position-relative overflow-hidden p-2 p-md-4">
                <!-- ローディングスピナー -->
                <div id="imageViewerSpinner" class="position-absolute spinner-border text-light d-none" role="status" style="width: 3.5rem; height: 3.5rem; z-index: 1055;">
                    <span class="visually-hidden">読み込み中...</span>
                </div>
                
                <!-- メイン画像表示 -->
                <img id="imageViewerImg" src="" alt="" class="img-fluid d-none" style="max-height: 92vh; max-width: 95vw; object-fit: contain; box-shadow: 0 0 25px rgba(0, 0, 0, 0.5); transition: opacity 0.15s ease-in-out;">
            </div>

            <!-- 前後ナビゲーションボタン -->
            <button class="btn btn-link text-white position-absolute top-50 start-0 translate-middle-y p-3 fs-1 text-decoration-none border-0 image-viewer-nav-btn" id="imageViewerPrevBtn" type="button" aria-label="前の画像" style="z-index: 1065; text-shadow: 0px 0px 10px rgba(0,0,0,0.8); opacity: 0.75; transition: opacity 0.2s, transform 0.2s;">
                <i class="bi bi-chevron-left"></i>
            </button>
            <button class="btn btn-link text-white position-absolute top-50 end-0 translate-middle-y p-3 fs-1 text-decoration-none border-0 image-viewer-nav-btn" id="imageViewerNextBtn" type="button" aria-label="次の画像" style="z-index: 1065; text-shadow: 0px 0px 10px rgba(0,0,0,0.8); opacity: 0.75; transition: opacity 0.2s, transform 0.2s;">
                <i class="bi bi-chevron-right"></i>
            </button>
            
        </div>
    </div>
</div>
