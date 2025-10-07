<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ONE STORAGE</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <style>
        .sidebar { 
            position: fixed; top: 0; bottom: 0; left: 0; z-index: 100; box-shadow: inset -1px 0 0 rgba(0, 0, 0, 1);
            background-color: #f8f9fa !important;
        }
        .sidebar-sticky { overflow-y: auto; }
        .nav-link.active { color: #000000ff !important; } 
        .sidebar .nav-link { padding-top: 0.25rem; padding-bottom: 0.25rem; }
        .toggle-icon { display: inline-block; width: 1rem; text-align: center; font-weight: bold; }
        .sidebar .nav-link:not(.active):not(.is-active) {color: #000000 !important;}
        .sidebar .nav-item-folder { position: relative; }
        .sidebar .nav-item-folder .nav-link.is-active { color: #fff !important; background-color: #0d6efd; font-weight: 600; }
        .sidebar .nav-item-folder .nav-link.is-active .bi-folder { color: #fff !important; }
        .sidebar .nav-item-folder.is-active-parent > .nav-link, .sidebar .nav-item-folder.is-active-parent > .nav-link .bi-folder { color: #000000ff !important; }
        .toast-container { position: fixed; bottom: 1rem; right: 1rem; z-index: 1090; }
        
        .file-list .file-row:hover { background-color: #f8f9fa; }
        .file-list a { text-decoration: none; color: inherit; }
        /*スターボタンのスタイル */
        .file-row .star-toggle-btn {
            border: none;
            background-color: transparent;
            padding: 0 5px;
            font-size: 1.25rem;
            line-height: 1;
        }
        .file-row .star-toggle-btn:hover {
            opacity: 0.7;
        }
    </style>
</head>