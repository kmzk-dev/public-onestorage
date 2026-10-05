<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONE STORAGE</title>
    <meta name="robots" content="noindex, nofollow, noarchive">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
            --primary-subtle: #eff6ff;
            --surface-bg: #f8fafc;
            --surface-hover: #f1f5f9;
            --border-color: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --sidebar-width: 250px;
            --navbar-height: 56px;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", "Hiragino Kaku Gothic ProN", "Yu Gothic", sans-serif;
            color: var(--text-main);
            background-color: #ffffff;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        /* ナビゲーションバー */
        .navbar {
            background-color: #0f172a !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px -1px rgba(0, 0, 0, 0.1);
            min-height: var(--navbar-height);
            z-index: 1030;
        }

        .navbar-brand {
            font-size: 1.05rem;
            letter-spacing: 0.02em;
        }

        /* ヘッダーアイコンボタン（枠線なし、ホバー背景のみ） */
        .header-icon-btn {
            width: 38px;
            height: 38px;
            padding: 0;
            border: none !important;
            background: transparent;
            color: rgba(255, 255, 255, 0.85) !important;
            border-radius: 0.375rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: background-color 0.15s ease, color 0.15s ease;
            text-decoration: none;
        }

        .header-icon-btn:hover:not(:disabled) {
            background-color: rgba(255, 255, 255, 0.12) !important;
            color: #ffffff !important;
        }

        .header-icon-btn:active:not(:disabled) {
            background-color: rgba(255, 255, 255, 0.2) !important;
        }

        .header-icon-btn:disabled, .header-icon-btn.disabled {
            opacity: 0.35 !important;
            pointer-events: none;
        }

        /* サイドバー共通 */
        #sidebarMenu {
            background-color: var(--surface-bg) !important;
            border-right: 1px solid var(--border-color);
        }

        .sidebar-sticky {
            overflow-y: auto;
        }

        .sidebar-sticky::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar-sticky::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .sidebar-sticky::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        .sidebar .nav-link {
            padding: 0.45rem 0.75rem;
            border-radius: 0.5rem;
            margin: 0.1rem 0.5rem;
            color: #475569 !important;
            transition: all 0.15s ease;
            font-size: 0.9rem;
            font-weight: 500;
            min-width: 0;
            overflow: hidden;
        }

        .sidebar .nav-link:hover {
            background-color: var(--surface-hover);
            color: var(--text-main) !important;
        }

        .sidebar .nav-link.active,
        .sidebar .nav-item-folder .nav-link.is-active {
            color: #ffffff !important;
            background-color: var(--primary-color) !important;
            font-weight: 600;
            box-shadow: 0 1px 3px rgba(37, 99, 235, 0.25);
        }

        .sidebar .nav-item-folder .nav-link.is-active .bi-folder,
        .sidebar .nav-link.active i {
            color: #ffffff !important;
        }

        .toggle-icon {
            display: inline-block;
            width: 1rem;
            text-align: center;
            font-weight: bold;
            color: var(--text-muted);
            user-select: none;
        }

        .toggle-icon:hover {
            color: var(--text-main);
        }

        .sidebar .nav-item-folder {
            position: relative;
        }

        .sidebar .nav-item-folder.is-active-parent > .nav-link {
            font-weight: 600;
            color: var(--text-main) !important;
        }

        /* サイドバー 特殊BOXESトグル（アイコンのみ、文字なし） */
        #boxesCollapseToggle {
            border-radius: 0.375rem;
            color: var(--text-muted);
            transition: background-color 0.15s ease, color 0.15s ease;
            height: 24px;
            margin: 0.15rem 0.5rem;
            cursor: pointer;
            user-select: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        #boxesCollapseToggle:hover {
            background-color: var(--surface-hover);
            color: var(--text-main);
        }

        #boxesCollapseToggle .toggle-icon-closed {
            display: inline-block;
        }

        #boxesCollapseToggle .toggle-icon-opened {
            display: none;
        }

        #boxesCollapseToggle[aria-expanded="true"] .toggle-icon-closed {
            display: none;
        }

        #boxesCollapseToggle[aria-expanded="true"] .toggle-icon-opened {
            display: inline-block;
        }

        /* PC (>= 768px): 固定サイドバー & Offcanvas上書き */
        @media (min-width: 768px) {
            #sidebarMenu.offcanvas-start {
                position: fixed !important;
                top: var(--navbar-height) !important;
                bottom: 0 !important;
                left: 0 !important;
                width: var(--sidebar-width) !important;
                transform: none !important;
                visibility: visible !important;
                z-index: 100 !important;
                box-shadow: inset -1px 0 0 var(--border-color) !important;
                border-top: none !important;
                border-bottom: none !important;
                border-left: none !important;
                transition: none !important;
            }

            .main-content {
                margin-left: var(--sidebar-width) !important;
                width: calc(100% - var(--sidebar-width)) !important;
                min-height: calc(100vh - var(--navbar-height));
            }
        }

        /* モバイル (< 768px): Offcanvas スタイル */
        @media (max-width: 767.98px) {
            #sidebarMenu.offcanvas-start {
                width: 280px;
                max-width: 85vw;
                box-shadow: 4px 0 16px rgba(0, 0, 0, 0.15);
            }

            .main-content {
                width: 100% !important;
                margin-left: 0 !important;
                padding-left: 1rem !important;
                padding-right: 1rem !important;
            }
        }

        /* メインコンテンツエリア */
        .main-content {
            background-color: #ffffff;
            padding-bottom: 3rem;
        }

        /* パンくずリスト & 複数選択操作バーの固定ヘッダー */
        .main-action-bar {
            position: sticky;
            top: var(--navbar-height);
            z-index: 95;
            background-color: #ffffff;
            margin-left: -1rem;
            margin-right: -1rem;
            padding-left: 1rem;
            padding-right: 1rem;
        }

        @media (min-width: 768px) {
            .main-action-bar {
                margin-left: -1.5rem;
                margin-right: -1.5rem;
                padding-left: 1.5rem;
                padding-right: 1.5rem;
            }
        }

        .breadcrumb-item + .breadcrumb-item::before {
            color: #94a3b8;
        }

        .breadcrumb-item a {
            transition: color 0.15s ease;
        }

        .breadcrumb-item a:hover {
            color: var(--primary-color) !important;
        }

        /* ファイルリスト コンテナ */
        .file-list {
            border: none;
            border-radius: 0;
            box-shadow: none;
            background-color: #ffffff;
            position: relative;
        }

        /* ヘッダー行 */
        .file-list-header {
            background-color: var(--surface-bg);
            font-size: 0.78rem;
            letter-spacing: 0.04em;
            color: var(--text-muted);
            border-bottom: 1px solid var(--border-color) !important;
            padding-top: 0.65rem !important;
            padding-bottom: 0.65rem !important;
            border-radius: 0;
        }

        /* ファイル行 */
        .file-list .file-row {
            min-height: 50px;
            background-color: #ffffff;
            transition: background-color 0.15s ease;
            border-bottom: 1px solid var(--border-color);
            border-radius: 0;
        }

        .file-list .file-row:hover {
            background-color: var(--primary-subtle);
        }

        .file-list a {
            text-decoration: none;
            color: var(--text-main);
            transition: color 0.15s ease;
        }

        .file-list .file-row:hover a span {
            color: var(--primary-color);
        }

        /* チェックボックスの垂直配置とサイズ */
        .form-check-input {
            cursor: pointer;
            width: 1.1rem;
            height: 1.1rem;
            border-color: #cbd5e1;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        /* フォルダなど選択不可（disabled）チェックボックスの塗りつぶしスタイル */
        .form-check-input:disabled {
            background-color: #e2e8f0 !important;
            border-color: #94a3b8 !important;
            opacity: 1 !important;
            pointer-events: auto !important;
            cursor: not-allowed !important;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20'%3e%3cpath fill='none' stroke='%2364748b' stroke-linecap='round' stroke-linejoin='round' stroke-width='3' d='M6 10h8'/%3e%3c/svg%3e") !important;
            background-position: center !important;
            background-repeat: no-repeat !important;
            background-size: contain !important;
        }

        /* スターボタンスタイル */
        .file-row .star-toggle-btn {
            border: none;
            background-color: transparent;
            padding: 4px;
            border-radius: 4px;
            font-size: 1.15rem;
            line-height: 1;
            transition: transform 0.15s ease, background-color 0.15s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .file-row .star-toggle-btn:hover {
            transform: scale(1.15);
            background-color: rgba(0, 0, 0, 0.05);
        }

        /* 操作用ドロップダウン */
        .file-row .dropdown .btn {
            background-color: transparent !important;
            border: none;
            border-radius: 0.375rem;
            padding: 0.25rem 0.5rem;
            color: var(--text-muted);
            transition: all 0.15s ease;
        }

        .file-row .dropdown .btn:hover,
        .file-row .dropdown .btn.show {
            background-color: rgba(0, 0, 0, 0.06) !important;
            color: var(--text-main);
        }

        .dropdown-menu {
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
            border: 1px solid var(--border-color);
            padding: 0.35rem;
        }

        .dropdown-item {
            border-radius: 0.375rem;
            padding: 0.4rem 0.75rem;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
        }

        .dropdown-item:hover {
            background-color: var(--surface-hover);
        }

        /* D&D ドラッグオーバーレイ */
        #dragDropOverlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            z-index: 2000;
            display: none;
            justify-content: center;
            align-items: center;
            pointer-events: none;
        }

        #dragDropOverlay.is-active {
            display: flex;
        }

        .drag-drop-card {
            border: 2px dashed #60a5fa;
            border-radius: 1rem;
            padding: 3rem 4rem;
            text-align: center;
            background: rgba(30, 41, 59, 0.85);
            color: #ffffff;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.3);
            transform: scale(0.95);
            transition: transform 0.2s ease;
        }

        #dragDropOverlay.is-active .drag-drop-card {
            transform: scale(1);
        }

        /* トースト通知 */
        .toast-container {
            position: fixed;
            bottom: 1.5rem;
            right: 1.5rem;
            z-index: 1090;
        }

        .toast {
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -4px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>