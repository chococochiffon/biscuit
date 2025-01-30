# biscuit
<p>
<img src="https://img.shields.io/badge/-Laravel-E74430.svg?logo=laravel&style=plastic" alt="">
<img src="https://img.shields.io/badge/-Php-777BB4.svg?logo=php&style=plastic" alt="">
<img src="https://img.shields.io/badge/-Mysql-4479A1.svg?logo=mysql&style=plastic" alt="">
<img src="https://img.shields.io/badge/-Docker-1488C6.svg?logo=docker&style=plastic" alt="">
</p>

# laravel コマンド
## インストール
```
❯ composer create-project --prefer-dist laravel/laravel .
```

ターミナルへ入り以下実行

```
❯ chmod -R 775 /var/www/app/storage
```
```
❯ chown -R www-data:www-data /var/www/app/storage
```
```
❯ chmod -R guo+w storage
```
```
❯ chown -R www-data:www-data /var/www/app/storage
```
```
❯ php artisan storage:link
```


## 実行
```
❯ docker compose up -d
```

ターミナルへ入り以下実行

```
❯ docker compose up -d
```
```
❯ composer install
```
```
❯ npm install
```

## コントローラー
### API
```
❯ php artisan make:controller API/CcccccController --resource
```
### WEB
```
❯ php artisan make:controller CcccccController --resource
```
### DB
```
❯ php artisan make:model Mmmmm -mfs
```
```
php artisan make:migration create_ttttt_table --table=ttttt
```

## マイグレーション
### 実行
```
❯ php artisan migrate
```
### リフレッシュ
```
❯ php artisan migrate:refresh --seed
```

https://qiita.com/hitotch/items/2e816bc1423d00562dc2

https://qiita.com/hitotch/items/2e816bc1423d00562dc2