<?php

/**
 * Run the HelloPack GitHub Sync process.
 *
 * @since 1.0.0
 */

const ROOT_PATH = __DIR__;

require ROOT_PATH . '/vendor/autoload.php';

use Dotenv\Dotenv;
use MilBar\HelloPackGitHubSync\GitService;
use MilBar\HelloPackGitHubSync\HelloApiService;
use MilBar\HelloPackGitHubSync\Helpers;
use MilBar\HelloPackGitHubSync\PluginDeployer;

// Load environment variables
$dotenv = Dotenv::createImmutable(ROOT_PATH);
$dotenv->load();

/**
 * Auth check for Hello API.
 *
 * @since 1.0.0
 */
$helloApi = new HelloApiService(
    $_ENV['HELLO_API_TOKEN'],
    $_ENV['HELLO_API_USER'],
    $_ENV['HELLO_API_ENDPOINT']
);

if (!$helloApi->checkAccess()) {
    Helpers::message('Auth to Hello API failed.', 'error');
    exit(1);
}

if (Helpers::shouldUpdatePluginList()) {
    Helpers::updatePluginList($helloApi);  // Update the plugin list
}

$plugins = Helpers::getAllowedPlugins();
if (!$plugins) {
    Helpers::message('Plugin configuration is empty or missing. Please check config.json.', 'error');
    exit(1);
}

// Init GitHub
$gitHub = new GitService(
    $_ENV['GITHUB_TOKEN'],
    $_ENV['GITHUB_ORGANIZATION'],
    $_ENV['GITHUB_REPO_PREFIX'] ?? ''
);

/**
 * Process each enabled plugin.
 *
 * @since 1.0.0
 */
foreach ($plugins as $plugin => $_) {
    $pluginDeployer = new PluginDeployer($plugin, $gitHub, $helloApi);
    $pluginDeployer->run();
}

Helpers::message('All Done!');
