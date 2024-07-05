apt-get update && apt-get install -y --no-install-recommends --force-yes wget git
wget https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions -O "/usr/local/bin/install-php-extensions"
chmod +x /usr/local/bin/install-php-extensions
/usr/local/bin/install-php-extensions @composer-2
mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"
composer install --no-interaction
mkdir -p $CI_PROJECT_DIR/logs
mkdir -p $CI_PROJECT_DIR/screenshots
# Validate dependencies are running
wget --server-response --spider http://selenium:4444
cp $CI_PROJECT_DIR/behat.yml.dist $CI_PROJECT_DIR/behat.yml
if [ -z "$BEHAT_TAG" ]; then CURRENT_BEHAT_TAG="~@skip"; else CURRENT_BEHAT_TAG="~@skip&&$BEHAT_TAG"; fi
CURRENT_BEHAT_TAG_QUOTED=`printf '%q' $CURRENT_BEHAT_TAG`
sed -i "s|__BEHAT_TAG__|$CURRENT_BEHAT_TAG_QUOTED|g" $CI_PROJECT_DIR/behat.yml
sed -i "s|__BASE_URL__|$URL|g" $CI_PROJECT_DIR/behat.yml
sed -i "s|__SELENIUM_URL__|http://selenium:4444/wd/hub|g" $CI_PROJECT_DIR/behat.yml
sed -i "s|__BACKEND_URL__|$ADMIN_URL|g" $CI_PROJECT_DIR/behat.yml
sed -i "s|__BACKEND_USERNAME__|$ADMIN_USERNAME|g" $CI_PROJECT_DIR/behat.yml
sed -i "s|__BACKEND_PASSWORD__|$ADMIN_PASSWORD|g" $CI_PROJECT_DIR/behat.yml
sed -i "s|__SCREENSHOT_DIRECTORY__|$CI_PROJECT_DIR/screenshots|g" $CI_PROJECT_DIR/behat.yml
sed -i "s|__CMS_DIRECTORY__|$CI_PROJECT_DIR/src|g" $CI_PROJECT_DIR/behat.yml
cat $CI_PROJECT_DIR/behat.yml
php vendor/bin/behat -vvv -c behat.yml