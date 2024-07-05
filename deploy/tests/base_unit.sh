composer config -g secure-http false
composer install --no-interaction --optimize-autoloader
cp tests/unit/phpunit.xml.dist tests/unit/phpunit.xml
sed -i "s|__PLUGIN_SOURCE_PATH__|$CI_PROJECT_DIR|g" tests/unit/phpunit.xml
php -d xdebug.mode=coverage vendor/bin/phpunit -c tests/unit/phpunit.xml --coverage-clover clover.xml --coverage-text
wget $DEFAULT_PAYEV_CODCOV_SERVER$DEFAULT_PAYEV_CODCOV_PATH
chmod +x ./codecov
./codecov -u $DEFAULT_PAYEV_CODCOV_SERVER