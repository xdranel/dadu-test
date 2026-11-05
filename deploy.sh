php artisan down || true

git pull origin develop

composer install

php artisan migrate --force

php artisan config:cache

php artisan route:cache

php artisan view:cache

php artisan event:cache

php artisan up

npm install --no-audit --prefer-offline --no-fund
npm run build

php artisan optimize:clear