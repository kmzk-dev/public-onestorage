# ONE STORAGE - 開発者向けドキュメント (DEVELOPMENT)

本書は、ONE STORAGE のアーキテクチャ、ディレクトリ構成、各ファイルの責務、セキュリティモデル、およびローカル開発環境（Docker）について解説する開発者向けドキュメントです。

---

## 1. 設計思想とアーキテクチャ

ONE STORAGE は、**「共用レンタルサーバー等の低リソース環境における高い移植性・安定動作」** と **「厳格なプライバシー・セキュリティ保護」** を両立するために、以下の原則に基づいて設計されています。

1. **ゼロ・外部依存（Pure PHP & Native Tools）**
   - Composer や外部パッケージマネージャーを一切使用せず、PHP 8.0+ 標準機能とネイティブ拡張（PDO SQLite, OpenSSL, ZipArchive, cURL）のみで完結します。
2. **`src/` 集約によるクリーンな配布アーキテクチャ**
   - リポジトリルート（開発環境・ドキュメント・CI/CD・復号ツール）と、サーバー配布物（`src/` 配下）を完全に分離。
   - 配布用リリース ZIP は `src/` ディレクトリをそのままパッケージングするだけで、不要な設定ファイルやソースコード管理ファイルが混入しない安全な構造を実現しています。
3. **SQLite 高速キャッシュ層（DBレス運用の進化）**
   - 外部 RDBMS サーバー（MySQL/PostgreSQL等）のセットアップは不要です。各データディレクトリ内に自動生成される SQLite データベースファイル（`.storage.db` / `.share.db`）をキャッシュ・インデックス層として利用し、ミリ秒単位のファイル名検索や階層走査を実現します。
4. **常時ストリーム暗号化（AES-256-CBC）**
   - サーバー上のファイルはすべて管理者メールアドレスから派生した鍵でストリーム暗号化されて保存されます。メモリ消費を抑えるため、大容量ファイルも一定バッファサイズでストリーム暗号化/復号処理されます。
5. **多層防御セキュリティ & インデックス完全遮断（Defense in Depth）**
   - `robots.txt`、全レスポンスヘッダーへの `X-Robots-Tag: noindex, nofollow, noarchive` 送出、全画面 HTML `meta` タグによる三層防御で検索エンジンの巡回・キャッシュを遮断。
   - `.htaccess` によるアクセス拒否に加え、データ・設定フォルダ内にアクセス防止用 `index.html` を自動生成。Nginx 向け推奨設定スニペットも管理画面で提供。
   - すべての内部 PHP ロジックは実行コンテキスト定数（`ONESTORAGE_RUNNING`）により直接呼び出しを遮断。

---

## 2. ディレクトリ構成とファイル群の役割

```text
onestorage/ (リポジトリルート)
├── README.md                      # メイン説明・導入ガイド
├── README-security.md             # セキュリティ仕様・多層防御
├── README-development.md          # 本書（開発者向けドキュメント）
├── README-decrypted.md            # 復号ツールドキュメント
├── robots.txt                     # 検索エンジン巡回拒否設定（ルート）
├── Dockerfile                     # 開発・テスト用 Dockerfile
├── docker-compose.yml             # 開発・テスト用 Compose 設定（./src をマウント）
│
├── .github/                       # CI/CD ワークフロー
│   └── workflows/
│       ├── release.yml            # リリース時: src/ 配下を自動 ZIP 化・GitHub Releases 公開
│       └── sync_public.yml        # パブリックリポジトリ同期
│
├── decrypt_tool/                  # ローカルファイル復号ツール（手元保管用）
│   ├── decrypt.html               # ブラウザ完結型復号ツール（Web Crypto API）
│   ├── decrypt.py                 # Python スクリプト版一括復号ツール
│   └── decrypt.zip                # Windows ネイティブアプリ版一括復号ツール（decrypt.exe）
│
└── src/                           # ★ Webアプリケーション本体（サーバー配信用ルート）
    ├── .htaccess.invalid          # Apache用サンプル設定（常時SSL・インデックス遮断）
    ├── robots.txt                 # 検索エンジン巡回拒否設定（Web公開ルート）
    ├── admin.php                  # 管理者画面（容量設定、MFA管理、セキュリティ診断）
    ├── index.php                  # メインファイルブラウザ画面
    ├── installer.php              # ワンファイル自動インストーラー
    ├── login.php                  # パスワードログイン画面
    ├── logout.php                 # ログアウト処理
    ├── memory.php                 # メモリ使用状況チェック/デバッグ用
    ├── mfa_login.php              # 二要素認証（TOTP）画面
    ├── path.php                   # パス解決ヘルパー
    ├── setting.php                # 初回セットアップ画面
    ├── share.php                  # 共有リンクファイルアクセス・ダウンロード画面
    │
    ├── config/                    # 設定ファイル格納ディレクトリ（初回セットアップで自動生成）
    │   ├── .htaccess              # 設定ディレクトリ直接アクセス遮断
    │   ├── index.html             # ディレクトリリスティング防止
    │   ├── accept.json            # アップロード許可拡張子一覧
    │   ├── auth.php               # 管理者認証情報（ハッシュ化パスワード・メールアドレス）
    │   ├── config.php             # コア設定（データルートパス、暗号化フラグ、容量上限GB等）
    │   ├── cookie_key.php         # HMAC署名・Cookie暗号化用ソルト
    │   ├── mfa_secret.php         # MFA秘密鍵・有効化フラグ（enabled）
    │   └── share_config.php       # 共有リンク（トークン、有効期限、DL制限等）
    │
    ├── functions/                 # バックエンド処理ロジック群
    │   ├── admin_api.php          # 管理者API（容量変更、MFAトグル、セキュリティ診断、セルフアップデータ）
    │   ├── api_delete_preview_cache.php # プレビュー一時キャッシュ削除API
    │   ├── auth.php               # 認証・セッション・Cookie検証・アクセス制御
    │   ├── chunk_upload.php       # チャンク分割アップロード・結合・暗号化・容量/上限チェック
    │   ├── cookie.php             # HMAC署名付き安全なCookie暗号化・復号
    │   ├── db.php                 # SQLite（.storage.db / .share.db）接続・CRUD・インデックス管理
    │   ├── handler_get_method.php # GETリクエスト処理（一覧取得、ファイルダウンロード、ストリーム復号）
    │   ├── handler_post_method.php # POSTリクエスト処理（フォルダ作成、移動、名前変更、削除、★）
    │   ├── helpers.php            # 汎用ヘルパー（AES暗号化/復号、サイズ計算、パス正規化等）
    │   ├── init.php               # コア初期化・定数定義・エラーハンドリング
    │   ├── mfa.php                # TOTP二要素認証（RFC 6238準拠・QRコード生成・コード検証）
    │   ├── pdf_preview.php        # PDFプレビュー用ストリーム配信
    │   ├── recent_api.php         # 最近使ったファイルの除外設定API
    │   ├── search.php             # 高速インクリメンタルファイル検索API（SQLite利用）
    │   ├── serve_preview.php      # 画像ファイルプレビュー用ストリーム配信
    │   ├── setting.php            # 初期セットアップ実行API
    │   └── star.php               # スター（お気に入り）登録・解除API
    │
    └── static/                    # フロントエンドUI・アセット群
        ├── asset_index.js         # メインJS（非同期CRUD、D&Dアップロード、モーダル制御、画像ビューアー制御）
        ├── img_logo.PNG           # システムロゴ
        ├── preview_modal.php      # PDFプレビュー用モーダル
        ├── template_head.php      # 共通HTMLヘッダー・CSSスタイル
        ├── template_nav.php       # ナビゲーションバー（容量ゲージ、検索バー、設定リンク）
        ├── component_create_folder_modal.php     # フォルダ作成モーダル
        ├── component_create_share_link_modal.php # 共有リンク作成モーダル
        ├── component_image_viewer_modal.php      # 画像専用全画面ビューアーモーダル（黒背景・キー操作対応）
        ├── component_move_item_modal.php         # 階層移動モーダル（ドリルダウンナビ）
        ├── component_move_to_inbox_modal.php     # INBOX移動モーダル
        ├── component_move_to_sharebox_modal.php  # SHARE BOX移動モーダル
        ├── component_recent_exclusions_modal.php # 除外設定モーダル
        ├── component_rename_item_modal.php       # 名前変更モーダル
        ├── component_search.php                  # ファイル名検索モーダル
        ├── component_toast_container.php         # トースト通知コンテナ
        └── component_upload_file_modal.php       # アップロード進捗モーダル
```

---

## 3. 主要サブシステムと処理フロー

### ① チャンク分割アップロードと常時暗号化 (`functions/chunk_upload.php`, `helpers.php`)
- クライアント側（`asset_index.js`）でファイルを 1MB 単位のチャンクに分割し、非同期並行送信。
- サーバー側の一時ディレクトリ（`.temp_chunks/`）に保存し、最終チャンク受信時にストリーム結合。
- 結合と同時に `helpers.php` の `encrypt_file()` が **AES-256-CBC** で暗号化し、ヘッダーに 16 バイトの IV を付与した暗号化ファイルを生成。
- アップロード前および結合時に、**ストレージ最大容量（`storage_limit_gb`）** と **フォルダ内アイテム上限（`MAX_FOLDER_ITEMS = 200`）** を検証し、超過時は即座にブロック・一時ファイルをクリーンアップ。

### ② SQLite インデックスキャッシュ (`functions/db.php`, `.storage.db`)
- 物理データディレクトリごとに `.storage.db`（SQLite データベース）を自動生成。
- ファイルやフォルダの追加・名前変更・移動・削除のたびに、SQLite レコードを同期更新。
- 全件検索（`functions/search.php`）は SQLite の `LIKE` 検索でミリ秒レベルで応答。

### ③ 認証・MFA・セッションセキュリティ (`functions/auth.php`, `cookie.php`, `mfa.php`)
- パスワードは `password_hash()`（Argon2id または Bcrypt）で安全に保存。
- ログインセッションおよび Cookie は `config/cookie_key.php` のソルトを用いた HMAC 署名検証付きトークンで改ざんを防止。
- 二要素認証（MFA）は RFC 6238 準拠の TOTP アルゴリズムを `functions/mfa.php` で独自実装（外部ライブラリ不要）。

### ④ フォルダ 200 件上限と UI バッジ
- システムの安定性とレスポンス維持のため、1つのフォルダに格納できるアイテム数を最大 200 件（`MAX_FOLDER_ITEMS = 200`）に制限。
- メイン画面（`index.php`）の一覧ヘッダーに `(X / 200)` の件数バッジを表示。
- フォルダ作成・ファイルアップロード・ファイル移動の各操作時にバックエンドで厳格にチェック。

### ⑤ SHARE BOX（一時共有リンク）
- 外部ユーザーに特定のファイルのみを一時共有可能。
- `config/share_config.php` にトークン、有効期限、最大ダウンロード回数を記録。
- `share.php` 経由でパスワード保護・有効期限検証・ストリーム復号ダウンロードを提供。

### ⑥ セルフアップデータ機能 (`functions/admin_api.php`, `admin.php`)
- **API 手動トリガー & リリース取得 (`check_releases`):** GitHub Releases API (`https://api.github.com/repos/kmzk-dev/public-onestorage/releases`) から最新リリースを取得し、現在の `APP_VERSION` より新しいバージョンのみを抽出して管理画面のセレクトボックスへ返却。
- **タイムアウト・中断対策:** アップデート適用処理（`apply_update`）の冒頭で `@set_time_limit(120)` および `ignore_user_abort(true)` を宣言し、サーバー制限による途中切断や不整合事故を防止。
- **一時ディレクトリでの安全展開:** ZIP を直接ルートに解凍するのではなく、サーバーの一時領域（`sys_get_temp_dir()`）へダウンロード・完全展開・検証した上で上書きコピーを実施。
- **厳格なデータ・設定保護ガード (`updater_recursive_copy`):**
  - `config/` ディレクトリ配下の全ファイル（`auth.php`, `config.php`, `cookie_key.php`, `mfa_secret.php`, `accept.json`, `share_config.php` 等）を完全保護（上書き禁止）。
  - 実データ領域（`data*`）および共有領域（`share*`）の難読化フォルダを完全にコピー・上書き対象から除外。
  - SQLite データベース実体（`.storage.db`, `.share.db`）およびジャーナルファイルの完全保護。
  - 残骸となりうる `installer.php` や `.installer_done` のコピー除外。
- **DB スキーマ自動追記との親和性:** アプリケーション更新後の初回アクセス時に、既存の `init_db_schema()` / `get_db()` を通じて `CREATE TABLE IF NOT EXISTS` やマイグレーション処理が自動実行される構造を維持。

### ⑦ オンデマンド型全画面画像ビューアー (`component_image_viewer_modal.php`, `asset_index.js`)
- **API追加呼び出しゼロ（DOMからの動的リスト抽出）:** 画像一覧を取得する専用APIは設けず、`index.php` 描画時に付与された `.image-preview-trigger` クラス要素からJavaScriptがクライアント側で動的に画像配列とインデックスを特定。
- **オンデマンド復号ストリーム:** 単一の `<img>` 要素の `src` を既存の配信エンドポイント（`?action=view&path=...`）にセットし、閲覧要求があった画像のみをサーバー上で復号してストリーム配信。
- **サーバー負荷・過剰復号防止（200ms デバウンス）:** ナビゲーションキー（←/→）やボタンが素早く連打された場合、中間の画像への不要な復号リクエストを抑制し、最終確定した画像のみストリーム取得を要求。
- **先読み（Preload）の最小化:** 画像ロード完了後に前後各1枚のみ（`preloadPrev`, `preloadNext`）をプリロードし、ブラウザメモリへのImageオブジェクトの無限蓄積を防止。
- **厳格なメモリ解放・多重登録防止:** モーダル非表示時（`hidden.bs.modal`）に `img.src = ''` を実行してデコード済み画像リソースの参照を解放。`keydown` リスナーは初期化時に1度だけ登録し、モーダル展開時のみ判定して実行。

---

## 4. 命名規則とコーディング規約

- **`functions/` 配下**: 
  - ファイル名は小文字スネークケースとし、役割を端的に表す（例: `chunk_upload.php`, `admin_api.php`）。
  - すべての処理ファイルの先頭に実行コンテキストチェック `defined('ONESTORAGE_RUNNING') or die(...)` を記述。
- **`static/` 配下**:
  - `[カテゴリ]_[用途].[拡張子]` の命名規則を採用。
    - `template_*`: ページの共通レイアウト枠組み
    - `component_*`: 再利用可能な UI モーダルやコンポーネント
    - `asset_*`: JavaScript や CSS リソース
    - `img_*`: 画像ファイル

---

## 5. 開発・テスト環境（Docker）

ローカル環境での動作検証および UI・機能テスト用として、Docker による開発環境を提供しています。

### 1. 特徴・仕様
- **ベースイメージ:** `php:8.2-apache`（`mod_rewrite`, `pdo_sqlite` 有効化）
- **マウント先:** `./src:/var/www/html`（Web アプリケーション本体のみを公開ルートにマウント）
- **公開ポート:** `8080`（アクセス先: `http://localhost:8080/`）
- **認証スキップ機構 (`SKIP_AUTH=1`):** `docker-compose.yml` 内で `SKIP_AUTH=1` が指定されている場合、ログインおよび MFA を自動スキップして直接ストレージ画面を検証可能。

### 2. 起動とビルド
リポジトリ直下で以下のコマンドを実行します：

```bash
docker compose up -d --build
```

ブラウザでアクセス：
👉 **http://localhost:8080/index.php**

### 3. ログの確認
```bash
docker compose logs -f web
```

### 4. 環境の停止と破棄
- **一時停止:** `docker compose stop`（再開: `docker compose start`）
- **コンテナ削除:** `docker compose down`
- **完全初期化（イメージ・ボリューム削除）:**
  ```bash
  docker compose down --rmi local -v
  ```

> [!IMPORTANT]
> **本番反映時の注意事項**
> `src/functions/auth.php` の `SKIP_AUTH` 設定等は開発・テスト専用です。本番環境へのリリース前には不要なテスト用コードやデバッグ用コードが含まれていないことを必ず確認してください。
