---
lastUpdated: "2026-10-02" # YYYY-MM-DD記述
---
## Overview & Scope

**[EN]**
This Privacy Policy describes how ONE STORAGE ("we", "our", or "the software") handles user information. ONE STORAGE is a self-hosted, lightweight, and secure private cloud storage web application. This policy applies strictly to the ONE STORAGE software and does not apply to third-party services or hosting providers you use to deploy it.

**[JP]**
本プライバシーポリシーは、ONE STORAGE（以下「本ソフトウェア」または「当方」）がユーザーの情報をどのように取り扱うかを説明するものです。ONE STORAGEは、軽量・高速・セキュアに特化したセルフホスティング型プライベートオンラインストレージです。本ポリシーは本ソフトウェア自体にのみ適用され、お客様が運用に利用する第三者のレンタルサーバーやホスティング事業者には適用されません。

## Handling of Personal Information

**[EN]**
ONE STORAGE is a 100% self-hosted application. The developers of ONE STORAGE do NOT collect, store, transmit, or process any Personally Identifiable Information (PII), credentials, files, or telemetry data. All data remains strictly within your self-hosted server environment.

**[JP]**
ONE STORAGEは完全なセルフホスティング型アプリケーションです。ONE STORAGEの開発者がユーザーの個人情報（氏名、メールアドレス、パスワード等）、アップロードファイル、アクセスログ、テレメトリデータを収集・蓄積・外部送信することは一切ありません。すべてのデータはお客様自身がホスティングするサーバー環境内にのみ保管されます。

## Scope and Collection of Information

**[EN]**
The software operates entirely on the server where you install it and interacts only with authorized users via your web browser:

- **Information Handled:** Uploaded files, administrator email (used for key derivation), hashed password, TOTP secret for MFA, and local SQLite metadata cache (`.storage.db`).
- **Method of Handling:** All information is stored directly on your server filesystem with AES-256-CBC stream encryption applied to uploaded files. No external telemetry or phone-home requests are performed.

**[JP]**
本ソフトウェアはお客様が設置したサーバー上でのみ動作し、ブラウザを介して認証されたユーザーとのみやり取りを行います。

- **取り扱う情報:** アップロードされたファイル、管理者メールアドレス（暗号鍵派生用）、ハッシュ化されたパスワード、二要素認証用シークレット、SQLiteによるメタデータキャッシュ（`.storage.db`）。
- **取り扱い方法:** すべての情報はお客様のサーバー上のファイルシステムに直接保存され、ファイル保存時にはAES-256-CBCによる常時ストリーム暗号化が施されます。外部へのデータ送信やテレメトリ通信は一切行われません。

## User Settings & Authentication

**[EN]**
- **Authentication Credentials:** Passwords are securely hashed using modern cryptographic algorithms (`password_hash`). Two-Factor Authentication (TOTP / RFC 6238) is supported.
- **Session Security:** Sessions are managed using HTTPOnly, Secure cookies signed with HMAC-SHA256 to prevent tampering and session hijacking.
- **Developer Access:** The developers have zero access to your installation, credentials, encryption keys, or stored content.

**[JP]**
- **認証情報:** パスワードは最新の安全なアルゴリズム（`password_hash`）でハッシュ化されて保存されます。標準規格（RFC 6238）に準拠した二要素認証（TOTP）に対応しています。
- **セッションセキュリティ:** HMAC-SHA256で署名されたセキュアクッキー（HTTPOnly / Secure）によりセッション管理を行い、改ざんやセッションハイジャックを防止します。
- **開発者の非アクセス:** 開発者がお客様のサーバー環境、認証情報、暗号鍵、保管ファイルにアクセスすることは構造上不可能です。

## Use of Collected Information

**[EN]**
All data handled by the software is used strictly and exclusively for providing core file storage and management capabilities:

- Secure file uploading, chunked streaming, downloading, and previewing.
- Fast incremental search and file indexing via local SQLite caching.
- Secure access control and authentication management.
- None of this information is ever used for tracking, advertising, behavioral analysis, or data monetization.

**[JP]**
本ソフトウェアが取り扱うデータは、プライベートストレージとしての基本機能を提供する目的のみに使用されます。

- 安全なファイルアップロード、チャンク分割転送、ストリーム復号ダウンロード、プレビュー表示。
- ローカルSQLiteキャッシュによる高速インクリメンタル検索およびファイル一覧表示。
- セキュアなアクセス制御および管理者認証管理。
- これらの情報が機能提供以外の目的（行動追跡、広告配信、分析、データ販売など）で使用されることは一切ありません。

## Server Permissions & Security

**[EN]**
ONE STORAGE requires standard PHP 8.0+ server capabilities:
- **Local File System Access:** Read and write permissions within the designated installation directory for encrypted file storage and configuration files.
- **Access Protection:** Uses `.htaccess` rules and internal execution context guards (`ONESTORAGE_RUNNING`) to block unauthorized direct file access.

**[JP]**
ONE STORAGEは標準的なPHP 8.0以上のサーバー環境で動作します。
- **ローカルファイルシステム権限:** 指定されたインストールディレクトリ内での暗号化ファイル保存および設定ファイルの読み書き権限。
- **多層防御:** `.htaccess` による直接アクセスの拒否、および実行コンテキスト制御（`ONESTORAGE_RUNNING`）により、不正なスクリプト直接実行を遮断します。

## Payments & Financial Information

**[EN]**
ONE STORAGE is provided as free / open-source software. It does not process payments, collect financial information, or connect to external billing systems.

**[JP]**
ONE STORAGEは無料で提供されるソフトウェアです。決済処理、クレジットカード情報等の金融情報の収集・保持、外部課金システムとの連携等は一切行われません。

## Data Storage, Deletion & Recovery

**[EN]**
- **Data Storage:** All files are stored encrypted (AES-256-CBC) under a randomly generated directory on your server.
- **Data Deletion:** Deleting files or resetting the storage permanently removes the files and associated SQLite records from your server.
- **Data Recovery Guarantee:** In case of server failure or application corruption, all encrypted files can be decrypted offline on your local PC using the provided standalone decryption tools (Web, Windows executable, or Python script) and your administrator email.

**[JP]**
- **データの保存:** すべてのファイルはAES-256-CBCで暗号化され、サーバー上の推測困難なランダムディレクトリ配下に保管されます。
- **データの削除:** ファイル削除やストレージのリセットを実行すると、サーバーから対象ファイルおよび関連するSQLiteレコードが完全に削除されます。
- **データ復旧保証:** サーバー停止や障害時でも、同梱のスタンドアロン復号ツール（Web版、Windows版、Python版）と管理者メールアドレスを用いて、ローカルPC上で安全に一括復号・データ復旧が可能です。

## Third-Party Sharing

**[EN]**
ONE STORAGE does NOT share, sell, disclose, or transmit any data to third-party services, analytics vendors, or cloud platforms.

**[JP]**
本ソフトウェアは、いかなる第三者企業、分析プロバイダー、クラウド事業者に対してもデータを送信、販売、共有することはありません。

## Disclaimer & Limitation of Liability

**[EN]**
ONE STORAGE is provided "as is", without warranty of any kind. Server failures, disk corruption, improper server configuration, or loss of encryption credentials (administrator email) may lead to data loss. Users are advised to maintain periodic backups of their server data. The developer assumes no liability for any data loss, server breach, or damages resulting from the use of this software.

**[JP]**
ONE STORAGEは現状有姿（As-Is）で提供され、明示または黙示を問わずいかなる保証も行いません。サーバーのハードウェア障害、ディスク破損、不適切なサーバー設定、暗号化シード（管理者メールアドレス）の紛失等によりデータが失われる可能性があります。定期的なバックアップを必ず実施してください。本ソフトウェアの利用または利用不能により生じたいかなる損害についても、開発者は一切の責任を負いません。

## Policy Changes

**[EN]**
This Privacy Policy may be updated periodically to reflect software improvements, new features, or updated security guidelines. The "Last Updated" date at the top indicates the latest revision.

**[JP]**
本プライバシーポリシーは、ソフトウェアの機能追加やセキュリティ指針の改定に伴い更新されることがあります。最新の改定日は本ページ先頭の「lastUpdated」に記載されます。

## Contact Information

**[EN]**
If you have any questions, feedback, or inquiries regarding this Privacy Policy or the software, please contact us via the dedicated support form or official contact page.

**[JP]**
本プライバシーポリシーまたは本ソフトウェアに関するご質問・お問い合わせは、公式Webサイトのお問い合わせフォームよりご連絡ください。
