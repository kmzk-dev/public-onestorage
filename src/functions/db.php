<?php
// db.php: SQLiteデータベース接続および初期化ユーティリティ
if (!defined('ONESTORAGE_RUNNING')) {
    // スクリプト単体実行やマイグレーションからの直接読み込みを許可するため、最低限必要な定義があれば許容
}

require_once __DIR__ . '/../path.php';

/**
 * SQLiteデータベースファイルの絶対パスを取得
 */
function get_sqlite_db_path(): string {
    if (!defined('DATA_ROOT') || !defined('SQLITE_DB_FILENAME')) {
        error_log('DATA_ROOT or SQLITE_DB_FILENAME is not defined.');
        return '';
    }
    return DATA_ROOT . DIRECTORY_SEPARATOR . SQLITE_DB_FILENAME;
}

/**
 * SQLiteデータベース接続（PDO）を取得（シングルトン）
 */
function get_db(): ?PDO {
    static $db = null;

    if ($db !== null) {
        return $db;
    }

    $db_path = get_sqlite_db_path();
    if (empty($db_path)) {
        return null;
    }

    $is_new = !file_exists($db_path);

    try {
        $dsn = 'sqlite:' . $db_path;
        $db = new PDO($dsn, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT => 5, // 5秒タイムアウト
        ]);

        // パフォーマンス・整合性設定 (NFS/共有サーバー対応)
        $db->exec('PRAGMA journal_mode = TRUNCATE;');
        $db->exec('PRAGMA synchronous = NORMAL;');
        $db->exec('PRAGMA busy_timeout = 5000;');
        $db->exec('PRAGMA foreign_keys = ON;');

        init_db_schema($db);
        @chmod($db_path, 0666);

        return $db;
    } catch (PDOException $e) {
        error_log('SQLite Connection Error: ' . $e->getMessage());
        return null;
    }
}

/**
 * テーブルおよびインデックスの初期化
 */
function init_db_schema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS directories (
            id        INTEGER PRIMARY KEY AUTOINCREMENT,
            path      TEXT NOT NULL UNIQUE,
            name      TEXT NOT NULL,
            parent    TEXT NOT NULL DEFAULT ''
        );
        CREATE INDEX IF NOT EXISTS idx_directories_parent ON directories (parent);

        CREATE TABLE IF NOT EXISTS files (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            name           TEXT NOT NULL,
            path           TEXT NOT NULL,
            size           INTEGER NOT NULL,
            formatted_size TEXT NOT NULL,
            mtime          INTEGER NOT NULL,
            ext            TEXT NOT NULL,
            UNIQUE(name, path)
        );
        CREATE INDEX IF NOT EXISTS idx_files_path ON files (path);
        CREATE INDEX IF NOT EXISTS idx_files_name ON files (name);
        CREATE INDEX IF NOT EXISTS idx_files_ext  ON files (ext);

        CREATE TABLE IF NOT EXISTS stars (
            id      INTEGER PRIMARY KEY AUTOINCREMENT,
            hash    TEXT NOT NULL UNIQUE,
            path    TEXT NOT NULL,
            name    TEXT NOT NULL,
            is_dir  INTEGER NOT NULL,
            size    INTEGER NOT NULL
        );

        CREATE TABLE IF NOT EXISTS recent_exclusions (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            hash       TEXT NOT NULL UNIQUE,
            path       TEXT NOT NULL,
            name       TEXT NOT NULL,
            created_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_recent_exclusions_path_name ON recent_exclusions (path, name);
    ");
}

/**
 * SHARE BOX専用のSQLite接続を取得（SHARE_ROOT/.share.db）
 */
function get_share_db(): ?PDO {
    static $share_db = null;
    if ($share_db !== null) return $share_db;
    if (!defined('SHARE_ROOT')) return null;

    $db_path = SHARE_ROOT . DIRECTORY_SEPARATOR . '.share.db';
    $is_new  = !file_exists($db_path);
    try {
        $share_db = new PDO('sqlite:' . $db_path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_TIMEOUT            => 5,
        ]);
        $share_db->exec('PRAGMA journal_mode = TRUNCATE;');
        $share_db->exec('PRAGMA synchronous = NORMAL;');
        $share_db->exec('PRAGMA busy_timeout = 5000;');
        $share_db->exec('PRAGMA foreign_keys = ON;');
        if ($is_new) {
            init_share_db_schema($share_db);
            @chmod($db_path, 0666);
        } else {
            @chmod($db_path, 0666);
        }
        return $share_db;
    } catch (PDOException $e) {
        error_log('Share DB Error: ' . $e->getMessage());
        return null;
    }
}

function init_share_db_schema(PDO $db): void {
    $db->exec("
        CREATE TABLE IF NOT EXISTS shares (
            id             INTEGER PRIMARY KEY AUTOINCREMENT,
            token          TEXT NOT NULL UNIQUE,
            folder_name    TEXT NOT NULL,
            password_hash  TEXT DEFAULT NULL,
            expires_at     INTEGER NOT NULL,
            max_downloads  INTEGER DEFAULT 0,
            download_count INTEGER DEFAULT 0,
            created_at     INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_shares_token   ON shares (token);
        CREATE INDEX IF NOT EXISTS idx_shares_expires ON shares (expires_at);
    ");
}

