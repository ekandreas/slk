<?php
namespace Deployer;

use Deployer\Task\Context;

date_default_timezone_set('Europe/Stockholm');

include_once 'vendor/deployer/deployer/recipe/composer.php';

host('skolporten.se')
    ->port(22)
    ->set('deploy_path','/mnt/persist/www/skolansledarkonvent.se')
    ->user('root')
    ->set('branch', 'master')
    ->stage('production')
    ->identityFile('~/.ssh/id_rsa');

set('repository', 'https://github.com/ekandreas/slk');

// Symlink the .env file for Bedrock
set('env', 'prod');
set('keep_releases', 10);
set('shared_dirs', ['web/app/uploads']);
set('shared_files', ['.env', 'web/.htaccess', 'web/robots.txt']);
set('env_vars', '/usr/bin/env');
set('writable_dirs', ['web/app/uploads']);

task('deploy:restart', function () {
    // Bladerunner example: 
    // run("rm -f web/app/uploads/.cache/*");
})->desc('Refresh cache');

task('pull', function () {

    $host = Context::get()->getHost();
    $user = $host->getUser();
    $hostname = $host->getHostname();
    $localHostname = "slk.app";

    $actions = [
        //"ssh {$user}@{$hostname} 'cd {{deploy_path}}/current && wp db export - --allow-root | gzip' > db.sql.gz",
        "ssh {$user}@{$hostname} 'cd {{deploy_path}}/current && mysqldump --skip-lock-tables --hex-blob --single-transaction slk | gzip' > db.sql.gz",
        "gzip -df db.sql.gz",
        "wp db import db.sql",
        "rm -f db.sql",
        "wp search-replace 'www.{$hostname}' '{$localHostname}'",
        "wp search-replace '{$hostname}' '{$localHostname}'",
        "wp search-replace 'https://{$localHostname}' 'http://{$localHostname}'",
        "rsync --exclude .cache -rve ssh " .
        "{$user}@{$hostname}:{{deploy_path}}/shared/web/app/uploads web/app",
        //"wp plugin activate query-monitor",
        "wp rewrite flush",
        "wp cache flush"
    ];

    foreach ($actions as $action) {
        writeln("{$action}");
        writeln(runLocally($action));
    }
});

task('deploy:restart', function () {
    $commands = [
        "service apache2 restart",
        "service redis restart",
        "php -r 'opcache_reset();'",
        "service varnish restart",
    ];
    foreach ($commands as $command) {
        writeln($command);
        writeln(run($command));
    }
})
    ->desc('Restarting apache2 and varnish');
after('deploy', 'deploy:restart');

