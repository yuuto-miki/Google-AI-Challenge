# WALK QUEST

東京・渋谷の街を歩きながら、写真の場所を探す散歩ゲーム。現代は街頭写真の撮影地点、昭和は街頭写真と同じ方向に交差点を望める現在の歩道を探します。現地探索には端末の位置情報を使います。渋谷デモでは地図をタップして現在地を動かせます。

## ローカル起動

既存の Docker Compose 構成を使います。初回はリポジトリのルートで次を実行してください。

```sh
cp -n src/.env.example src/.env
docker compose up -d web
docker compose exec --user "$(id -u):$(id -g)" app composer install
docker compose exec --user "$(id -u):$(id -g)" app php artisan key:generate
docker compose exec app find storage/framework storage/logs bootstrap/cache -type d -exec chgrp www-data '{}' +
docker compose exec app find storage/framework storage/logs bootstrap/cache -type d -exec chmod 2775 '{}' +
```

既存の環境で APP_KEY を設定済みの場合、key:generate は省略してください。2回目以降は `docker compose up -d web` で起動します。[localhost](http://localhost/) を開き、「渋谷デモを試す」で一連の操作を試せます。ゲームはデータベースのマイグレーションやフロントエンドのビルドを必要としません。地図タイルの表示にはインターネット接続が必要です。

## 同じ Wi-Fi のスマートフォンで開く

PC で Docker を起動した状態で、スマートフォンを同じ Wi-Fi に接続します。Windows の Wi-Fi の IPv4 アドレスが 192.168.3.9 の場合、デモは `http://192.168.3.9/` で遊べます。

GPS を使う場合は WSL のリポジトリ直下で `sh docker/mobile/start.sh 192.168.3.9` を実行します。Wi-Fi の IP が変わった場合は新しいアドレスで再実行してください。初回または証明書再発行後は `src/public/walk/mobile-ca.cer` をスマートフォンに渡して CA 証明書としてインストールします。Wi-Fi 経由で取得する場合は `http://192.168.3.9/walk/mobile-ca.cer` を開き、証明書の SHA-256 指紋が起動時の出力と一致することを確認してください。iPhone は「設定 → 一般 → 情報 → 証明書信頼設定」で「WALK QUEST local Wi-Fi CA」を信頼します。Android は「暗号化と認証情報」から CA 証明書をインストールします。

スマートフォンで `https://192.168.3.9:8443/` を開き、位置情報を許可してください。この URL が使える範囲は PC と通信できるローカルネットワーク内です。

## 動作

- 往復30・60・90分と時代を選択。徒歩70 m/分、道路の迂回係数1.3として、片道時間に収まる直線距離の候補からランダムに選びます。実際の経路所要時間は道路や信号で変わります。
- 現在地の更新ごとにサーバーで残り距離を計算します。写真・確認済みの段階ヒントを提示し、正解座標と名称は回答確定後に返します。
- 回答ピンの60 m以内にいることを確認し、現地モードでは測位精度80 m以内、取得から60秒以内の位置を要求します。スマートフォンの実地利用には HTTPS と位置情報の許可が必要です。散歩時間による減点はありません。
- 誤差10・30・50・100 m以内で順に100・90・70・40点、それ以上は0点です。
- 目的地と写真の出典は `src/config/walk_spots.php` に、出題画像は `src/storage/app/walk-photos/` に格納しています。

## テスト

```sh
docker compose exec app php artisan test --filter=WalkQuestTest
```
