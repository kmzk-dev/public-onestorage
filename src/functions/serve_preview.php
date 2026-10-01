<?php
// functions/serve_preview.php: PDFストリーミング配信用ハンドラー（Rangeリクエスト＆AES-CBCランダムアクセス復号対応）
if (!defined('ONESTORAGE_RUNNING')) {
    die('Access Denied: Invalid execution context.');
}

$token = preg_replace('/[^a-f0-9]/', '', $_GET['token'] ?? '');
if (empty($token)) {
    http_response_code(400);
    die('Bad Request: Invalid token.');
}

// 1. 最優先: セッションロックの即時解除
// 後続の並列Rangeリクエストがセッションファイルでブロックされるのを完全に防止する
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// 2. 出力バッファリングと自動圧縮の無効化
while (ob_get_level()) {
    ob_end_clean();
}
ini_set('zlib.output_compression', '0');
ignore_user_abort(false);

$cache_dir = DATA_ROOT . DIRECTORY_SEPARATOR . PREVIEW_CACHE_DIR_NAME;

// UUID.json 参照情報が存在するか確認
$ref_json = realpath($cache_dir . DIRECTORY_SEPARATOR . $token . '.json');
if (!$ref_json || strpos($ref_json, $cache_dir) !== 0 || !is_file($ref_json)) {
    http_response_code(404);
    die('Not Found: Preview file not found or expired.');
}

$ref_content = @file_get_contents($ref_json);
$ref_data = json_decode($ref_content, true);
if (!is_array($ref_data) || empty($ref_data['path'])) {
    http_response_code(500);
    die('Server Error: Invalid reference data.');
}

$original = realpath($ref_data['path']);
// オリジナルファイルが DATA_ROOT 配下に存在することを厳格に検証
if (!$original || strpos($original, DATA_ROOT) !== 0 || !is_file($original)) {
    http_response_code(404);
    die('Not Found: Original file not found.');
}
$serve_path = $original;

$file_size = filesize($serve_path);
if ($file_size === false) {
    http_response_code(500);
    die('Server Error: Unable to determine file size.');
}

$cipher = 'aes-256-cbc';
$key = get_encryption_key();
if ($key === false) {
    http_response_code(500);
    die('Server Error: Encryption key not found.');
}

// 平文の実際のサイズを計算するために、最後のブロックを復号してパディング長を取得する
$fp = @fopen($serve_path, 'rb');
if ($fp === false) {
    http_response_code(500);
    die('Server Error: Cannot open file.');
}

if ($file_size > 32) {
    fseek($fp, -32, SEEK_END);
    $last_iv = fread($fp, 16);
    $last_block = fread($fp, 16);
    $decrypted_last_block = openssl_decrypt($last_block, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $last_iv);
    $pad_len = ord(substr($decrypted_last_block, -1));
    if ($pad_len > 0 && $pad_len <= 16) {
        $true_file_size = $file_size - 16 - $pad_len;
    } else {
        // パディング異常時は暗号文長 - IV長を仮サイズとする
        $true_file_size = $file_size - 16;
    }
} else {
    $true_file_size = 0;
}
fclose($fp);

// 共通レスポンスヘッダー
header('Content-Type: application/pdf');
header('Accept-Ranges: bytes');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// 3. Rangeリクエストの判定（bytes=start-end, bytes=start-, bytes=-suffix に完全対応）
$http_range = $_SERVER['HTTP_RANGE'] ?? '';
$range_requested = false;
$start = 0;
$end = $true_file_size - 1;

if (!empty($http_range)) {
    if (preg_match('/bytes=(\d+)-(\d+)?/', $http_range, $matches)) {
        $start = (int)$matches[1];
        $end = (isset($matches[2]) && $matches[2] !== '') ? (int)$matches[2] : ($true_file_size - 1);
        $range_requested = true;
    } elseif (preg_match('/bytes=-(\d+)/', $http_range, $matches)) {
        // Suffix byte range: 末尾Nバイトの取得 (PDF.jsがxrefテーブル検索時に使用)
        $suffix_len = (int)$matches[1];
        $start = max(0, $true_file_size - $suffix_len);
        $end = $true_file_size - 1;
        $range_requested = true;
    }
}

if ($range_requested) {
    $end = min($end, $true_file_size - 1);

    if ($start > $end || $start >= $true_file_size) {
        http_response_code(416); // Range Not Satisfiable
        header("Content-Range: bytes */{$true_file_size}");
        exit;
    }

    $length = $end - $start + 1;

    http_response_code(206); // Partial Content
    header("Content-Range: bytes {$start}-{$end}/{$true_file_size}");
    header("Content-Length: {$length}");

    $fp = @fopen($serve_path, 'rb');
    if ($fp === false) exit;

    // --- AES-CBC ランダムアクセス復号 ---
    $start_block_index = floor($start / 16);
    $end_block_index = floor($end / 16);
    
    // IVの位置（対象ブロックの直前16バイト）
    $iv_offset = $start_block_index * 16;
    fseek($fp, $iv_offset);
    $range_iv = fread($fp, 16);
    
    // 読み込むブロック数
    $blocks_to_read = $end_block_index - $start_block_index + 1;
    
    // メモリ制約を考慮し、64KB単位でチャンク処理
    $chunk_blocks = (64 * 1024) / 16;
    $blocks_read = 0;
    
    $current_iv = $range_iv;
    $skip = $start - ($start_block_index * 16);
    $bytes_left_to_output = $length;
    
    while ($blocks_read < $blocks_to_read && !feof($fp) && $bytes_left_to_output > 0) {
        if (connection_aborted()) break;

        $blocks_to_read_now = min($chunk_blocks, $blocks_to_read - $blocks_read);
        $cipher_data = fread($fp, $blocks_to_read_now * 16);
        if ($cipher_data === false || $cipher_data === '') break;
        
        $decrypted = openssl_decrypt($cipher_data, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $current_iv);
        if ($decrypted === false) break;

        // 次のチャンクのためのIVを保持（現暗号データの末尾16バイト）
        $current_iv = substr($cipher_data, -16);
        
        // 最初のチャンクの場合、端数オフセット（skip）を除去
        if ($blocks_read === 0 && $skip > 0) {
            $decrypted = substr($decrypted, $skip);
        }
        
        // 要求長を超えないよう切り詰め
        if (strlen($decrypted) > $bytes_left_to_output) {
            $decrypted = substr($decrypted, 0, $bytes_left_to_output);
        }
        
        echo $decrypted;
        flush();
        
        $bytes_left_to_output -= strlen($decrypted);
        $blocks_read += $blocks_to_read_now;
    }

    fclose($fp);
} else {
    // 4. Rangeヘッダーなしの初期リクエスト
    http_response_code(200);
    header("Content-Length: {$true_file_size}");
    
    $fp = @fopen($serve_path, 'rb');
    if ($fp === false) exit;
    
    fseek($fp, 0);
    $iv = fread($fp, 16);
    $chunk_size = 64 * 1024;
    $bytes_left = $true_file_size;
    
    while (!feof($fp) && $bytes_left > 0) {
        if (connection_aborted()) break;

        $cipher_data = fread($fp, $chunk_size);
        if ($cipher_data === false || $cipher_data === '') break;
        
        $len = strlen($cipher_data);
        $valid_len = $len - ($len % 16);
        if ($valid_len > 0) {
            $to_dec = substr($cipher_data, 0, $valid_len);
            $decrypted = openssl_decrypt($to_dec, $cipher, $key, OPENSSL_RAW_DATA | OPENSSL_NO_PADDING, $iv);
            if ($decrypted === false) break;
            $iv = substr($to_dec, -16);
            
            if (strlen($decrypted) > $bytes_left) {
                $decrypted = substr($decrypted, 0, $bytes_left);
            }
            echo $decrypted;
            flush();
            $bytes_left -= strlen($decrypted);
        }
    }
    
    fclose($fp);
}
exit;
