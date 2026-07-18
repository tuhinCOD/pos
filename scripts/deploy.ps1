# $server="root@187.xxx.xxx.xxx"
# $path="/var/www/pos"

# rsync -avz `
# --delete `
# --exclude ".git" `
# --exclude "node_modules" `
# --exclude "vendor" `
# --exclude ".env" `
# ./ $server:$path

# ssh $server "
# cd $path &&
# composer install --no-dev --optimize-autoloader &&
# npm install &&
# npm run build &&
# php artisan migrate --force &&
# php artisan optimize &&
# php artisan queue:restart
# "