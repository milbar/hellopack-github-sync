<?php

require __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use MilBar\HelloPackGitHubSync\GitHubService;
use MilBar\HelloPackGitHubSync\HelloApiService;
use MilBar\HelloPackGitHubSync\Helpers;
use MilBar\HelloPackGitHubSync\PluginDeployer;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Auth check
$helloApi = new HelloApiService(
    $_ENV['HELLO_API_TOKEN'],
    $_ENV['HELLO_API_USER'],
    $_ENV['HELLO_API_ENDPOINT']
);

$pluginListFileName = 'plugin-list.json';

if (!$helloApi->checkAccess()) {
    echo "❌ Auth to Hello API failed.\n";
    exit(1);
}

// Function to update the plugin list if needed
function updatePluginList(HelloApiService $helloApi): void
{
    global $pluginListFileName;
    // Fetch all plugins using the HelloApiService
    $plugins = $helloApi->getAllPlugins();  // Use getAllPlugins() from HelloApiService

    // Initialize the plugin list array
    $pluginList = ['plugins' => []];

    // Loop through each plugin and save its name with a default value of false
    foreach ($plugins as $plugin) {
        $pluginName = $plugin['item']['wordpress_plugin_metadata']['plugin_name'];
        $pluginSlug = Helpers::slugify($pluginName);
        $pluginList['plugins'][$pluginSlug] = false;  // Set default value as false
    }

    // Sort the plugin list alphabetically by key (plugin slug)
    ksort($pluginList['plugins']);

    // Encode the plugin list as JSON
    $jsonData = json_encode($pluginList, JSON_PRETTY_PRINT);

    // Save the data to the specified file
    file_put_contents($pluginListFileName, $jsonData);

    echo "Plugin list has been updated successfully.\n";
}

// Function to check if the plugin list needs to be updated based on file age
function shouldUpdatePluginList($filePath, $ageThresholdInDays)
{
    // Check if file exists
    if (!file_exists($filePath)) {
        return true;  // If the file doesn't exist, update it
    }

    // Get the last modification time of the file
    $fileModificationTime = filemtime($filePath);

    // Get the current time
    $currentTime = time();

    // Calculate the difference in days
    $fileAgeInDays = ($currentTime - $fileModificationTime) / (60 * 60 * 24);

    // If the file is older than the threshold, return true
    return $fileAgeInDays > $ageThresholdInDays;
}

// Check plugin list file age before processing
$pluginListFilePath = __DIR__ . '/' . $pluginListFileName;
$ageThreshold = intval($_ENV['PLUGIN_CONFIG_AGE_THRESHOLD']);  // Get age threshold from .env file

if (shouldUpdatePluginList($pluginListFilePath, $ageThreshold)) {
    updatePluginList($helloApi);  // Update the plugin list
} else {
    echo "Plugin list is up to date.\n";
}

// Load config.json to process enabled plugins
$config = json_decode(file_get_contents(__DIR__ . '/config.json'), true);
$plugins = array_filter($config['plugins'], fn($enabled) => $enabled === true);

// Init GitHub
$gitHub = new GitHubService(
    $_ENV['GITHUB_TOKEN'],
    $_ENV['GITHUB_ORGANIZATION'],
    $_ENV['GITHUB_REPO_PREFIX'] ?? ''
);

// Process each enabled plugin
foreach ($plugins as $plugin => $_) {
    echo "🔄 Processing plugin: {$plugin}\n";
    $pluginDeployer = new PluginDeployer($plugin, $gitHub, $helloApi);
    $pluginDeployer->run();
}

echo "✅ All done.\n";
