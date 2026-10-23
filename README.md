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
