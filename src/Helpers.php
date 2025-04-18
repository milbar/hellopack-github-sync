<?php

namespace MilBar\HelloPackGitHubSync;

use Symfony\Component\Filesystem\Filesystem;

class Helpers
{
    /**
     * Get the path from the root directory for a given path.
     *
     * @param string $path The path to append to the root.
     * @return string The full root path.
     * @since 1.0.0
     */
    public static function rootPath(string $path = ''): string
    {
        $cleaned = ltrim($path, '/\\'); // Remove any leading slashes
        return ROOT_PATH . DIRECTORY_SEPARATOR . $cleaned;
    }

    /**
     * Log a message with a specific type.
     *
     * @param string $message The message to log.
     * @param string $type The type of message (info, warning, error).
     * @return void
     * @since 1.0.0
     */
    public static function message(string $message, string $type = 'info'): void
    {
        $prefix = match ($type) {
            'e', 'err', 'error' => 'ERROR',
            'w', 'warn', 'warning' => 'WARN',
            default => 'INFO'
        };

        $timestamp = date('Y-m-d H:i:s');
        echo "$timestamp [$prefix]: $message\n";
    }

    /**
     * Get the path to the plugin list.
     *
     * @return string The path to the plugin list.
     * @since 1.0.0
     */
    private static function getPluginListPath(): string
    {
        return self::rootPath('plugin-list.json');
    }

    /**
     * Get the age threshold for the plugin list.
     *
     * @return int The age threshold in seconds.
     * @since 1.0.0
     */
    private static function getPluginListAgeThreshold(): int
    {
        $threshold = $_ENV['PLUGIN_CONFIG_AGE_THRESHOLD'] ?? 86400;
        return intval($threshold);
    }

    /**
     * Get the list of allowed plugins.
     *
     * @return array The list of allowed plugins.
     * @since 1.0.0
     */
    public static function getAllowedPlugins(): array
    {
        $config = json_decode(file_get_contents(Helpers::rootPath('/config.json')), true);
        return array_filter($config['plugins'], fn($enabled) => $enabled === true);
    }

    /**
     * Convert a string into a URL-friendly slug.
     *
     * @param string $text The text to slugify.
     * @return string The slugified text.
     * @since 1.0.0
     */
    public static function slugify(string $text): string
    {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text) ?: 'n-a';
    }

    /**
     * Get the repository name based on the plugin slug.
     *
     * @param string $pluginSlug The slug of the plugin.
     * @return string The repository name.
     * @since 1.0.0
     */
    public static function getRepoName(string $pluginSlug): string
    {
        $prefix = $_ENV['GITHUB_REPO_PREFIX'] ?? '';
        return $prefix ? "{$prefix}-{$pluginSlug}" : $pluginSlug;
    }

    /**
     * Get the bot name.
     *
     * @return string The bot name.
     * @since 1.0.0
     */
    public static function getBotName(): string
    {
        return $_ENV['GITHUB_BOT_NAME'] ?? 'hellopack-bot';
    }

    /**
     * Get the bot email.
     *
     * @return string The bot email.
     * @since 1.0.0
     */
    public static function getBotEmail(): string
    {
        return $_ENV['GITHUB_BOT_EMAIL'] ?? 'bot@example.com';
    }

    /**
     * Update the plugin list from the Hello API.
     *
     * @param HelloApiService $helloApi The Hello API service instance.
     * @return void
     * @since 1.0.0
     */
    public static function updatePluginList(HelloApiService $helloApi): void
    {
        self::message('Updating plugin list...');
        $file = self::getPluginListPath();
        $plugins = $helloApi->getAllPlugins();
        $pluginList = ['plugins' => []];

        foreach ($plugins as $plugin) {
            $pluginName = $plugin['item']['wordpress_plugin_metadata']['plugin_name'];
            $pluginSlug = self::slugify($pluginName);
            $pluginList['plugins'][$pluginSlug] = false;
        }

        ksort($pluginList['plugins']);
        file_put_contents($file, json_encode($pluginList, JSON_PRETTY_PRINT));
        self::message("Plugin list updated: {$file}");
    }

    /**
     * Determine if the plugin list should be updated.
     *
     * @return bool True if the plugin list should be updated, false otherwise.
     * @since 1.0.0
     */
    public static function shouldUpdatePluginList(): bool
    {
        $filePath = self::getPluginListPath();

        if (!file_exists($filePath)) {
            return true;
        }

        $fileTime = (time() - filemtime($filePath)) / (60 * 60 * 24);
        return $fileTime > self::getPluginListAgeThreshold();
    }
}
