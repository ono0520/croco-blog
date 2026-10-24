# croco-blog

croco.blog（WordPress）の自作部分だけを管理する。WordPress本体・配布プラグイン・アップロード画像・データベースは入れない。

## 構成
- `wp-content/themes/cocoon-child-master/` … Cocoonの子テーマ（ロリポップの `/croco.blog/wp-content/themes/cocoon-child-master/`）
- `croco-private/` … データベースのバックアップ用スクリプト（ロリポップの `/croco-private/`、公開フォルダの外）

## 入れないもの
- WordPress本体、`wp-config.php`
- 配布物のテーマ・プラグイン（cocoon-master、akismet、contact-form-7 など。管理画面から入れ直せる）
- `wp-content/uploads/`（画像）、`wp-content/database/`、`*.sqlite`（記事・コメントのデータ）
- バックアップのスナップショットと実行レポート

## サーバーへの反映
GitHubの Actions →「ロリポップに反映」→ Run workflow。最初は dry_run にチェックを入れたまま実行して差分を確認し、問題なければ外して main から実行する。子テーマと croco-private だけを送り、サーバーにしかないファイル（WordPress本体・画像・データベース・スナップショット）は消さない。
必要なSecrets（Settings → Secrets and variables → Actions）: `FTP_SERVER` / `FTP_USERNAME` / `FTP_PASSWORD`（SENDAIPLUSと同じ値）
