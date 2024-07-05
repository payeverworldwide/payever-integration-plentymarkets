GITLAB_TAG=$(git describe --tags `git rev-list --tags --max-count=1`)
PLUGIN_VERSION=$(jq '.version' plugin.json --raw-output)
php -r "version_compare('$PLUGIN_VERSION', '$GITLAB_TAG', '<=') && print_r('Plugin version should be greater then latest tag') && exit(1);"
echo "Version to be released is $PLUGIN_VERSION"