## ONESTORAGE - Public Repository
このリポジトリは、ONESTORAGE プロジェクトの完了した成果物を公開するためのものです。
プロジェクトの実際の開発はプライベートリポジトリで行われています。パブリックリポジトリへの反映は、GitHub Actionsによって自動化されています。

### 重要な作業ルール
プライベートリポジトリで作業を行う際は、必ず以下のルールを遵守してください。
mainへの直接プッシュは厳禁です。

- 作業開始前:必ずorigin/mainをpullして、新しいブランチで作業を開始してください。
- 作業完了後:mainへのPRを作成しmergeしてください。
- publicへの反映:mainへのpushがトリガーとなし、同期を開始します。
- publicへのmerge:publicでmerge作業をしてください。

## ワークフロー: パブリックリポジトリへの同期自動化
このGitHub Actionsワークフローは、プライベートリポジトリ（同期元）の main ブランチへのプッシュをトリガーとして、このパブリックリポジトリにファイル内容を同期するためのPRを自動で作成します。
これにより、安全かつ自動的に開発内容を公開環境へ反映させます。

ワークフロー名: Create Sync Pull Request
|項目|設定値|説明|
|-|-|-|
|ファイル名|sync_public.yml||
|トリガー||プライベートリポジトリの main ブランチへの push|
|同期元|ワークフローが設定されているプライベートリポジトリ (./)||
|同期先|kmzk-dev/public-onestorag||
|認証情報|secrets.CLONE_REPO|同期先へのチェックアウト,PR作成|

### プロセスフロー (実行ステップの詳細)
1. 仮想環境の立ち上げ:クリーンな仮想環境を作成
2. 同期元・同期先のリポジトリのチェックアウトして、一つの仮想環境で双方のコードベースを扱います。
   1. 開発元であるプライベートリポジトリの最新内容を仮想環境のルートディレクトリ (./) に取得します。
   2. 同期先であるパブリックリポジトリの現在の内容を、public-onestorage という専用ディレクトリに取得します。
3. ファイル同期:rsyncを参照
4. public-onestorageをブランチとすぃて、同期先リポジトリに対してPRが自動作成されます。
   1. コミットメッセージ:chore: automated sync from private repository
   2. PRタイトル:[Automated] Sync from private repository
   3. PRマージ後:delete-branch: true

```Bash
rsync -av --delete --exclude='public-onestorage' --exclude='.github/' --exclude='.git/' ./ public-onestorage/

同期元 -> 同期先	プライベートリポジトリ (./) の内容をパブリックリポジトリのディレクトリ (public-onestorage/) へ上書き同期します。
public-onestorage/ ディレクトリ内が、プライベートリポジトリの内容と完全に同一の状態になります。

-av	ファイル属性 (パーミッション、タイムスタンプなど) を保持して転送します。
--delete	同期元に存在しないファイルは、同期先から削除します
--exclude	リポジトリ管理に必要なディレクトリ (.github/, .git/) や、同期先リポジトリのローカルディレクトリ自体 (public-onestorage) を同期対象から除外します。
```