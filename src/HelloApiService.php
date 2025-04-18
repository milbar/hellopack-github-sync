<?php

namespace MilBar\HelloPackGitHubSync;

use Symfony\Component\HttpClient\HttpClient;

/**
 * Class HelloApiService
 * @since 1.0.0
 */
class HelloApiService
{
    private string $token;
    private string $userAgent;
    private string $baseUrl;

    /**
     * HelloApiService constructor.
     * @param string $token API token for the HelloPack
     * @param string $userAgent API User as the site domain https://yoursite.com/wp
     * @param string $baseUrl HelloAPI url
     * @since 1.0.0
     */
    public function __construct(string $token, string $userAgent, string $baseUrl)
    {
        $this->token = $token;
        $this->userAgent = $userAgent;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    /**
     * Get the headers for the API request.
     * @return array
     * @since 1.0.0
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => $this->userAgent,
        ];
    }

    /**
     * Check if access to the API is valid.
     * @return bool
     * @since 1.0.0
     */
    public function checkAccess(): bool
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $this->baseUrl . '/apicheck', [
            'headers' => $this->getHeaders(),
        ]);

        $data = $response->toArray(false);
        return !isset($data['error']);
    }

    /**
     * Get all plugins from the API.
     * @return array
     * @since 1.0.0
     */
    public function getAllPlugins(): array
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $this->baseUrl . '/wordpress-plugins', [
            'headers' => $this->getHeaders(),
        ]);

        $data = $response->toArray(false);
        return $data['results'] ?? [];
    }

    /**
     * Get the download URL for a specific plugin.
     * @param string $itemId
     * @return string
     * @throws \RuntimeException
     * @since 1.0.0
     */
    public function getPluginDownloadUrl(string $itemId): string
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $this->baseUrl . '/download?hp_item_id=' . $itemId, [
            'headers' => $this->getHeaders(),
        ]);

        $data = $response->toArray(false);
        return $data['wordpress_plugin'] ?? throw new \RuntimeException('No download URL found');
    }

    /**
     * Get metadata for a specific plugin by its slug.
     * @param string $pluginSlug
     * @return array
     * @since 1.0.0
     */
    public function getPluginMeta(string $pluginSlug): array
    {
        $plugins = $this->getAllPlugins();

        if (!is_array($plugins)) {
            return [];
        }

        foreach ($plugins as $plugin) {
            $meta = $plugin['item']['wordpress_plugin_metadata'];
            $slug = Helpers::slugify($meta['plugin_name']);
            if ($slug === $pluginSlug) {
                return [
                    'id' => $plugin['item']['id'],
                    'name' => $plugin['item']['name'],
                    'version' => $meta['version'],
                    'author' => $meta['author'],
                    'description' => $meta['description'],
                ];
            }
        }

        Helpers::message("Plugin metadata not found for slug: {$pluginSlug}", 'error');
        return [];
    }
}
