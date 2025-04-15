<?php

namespace MilBar\HelloPackGitHubSync;

use Symfony\Component\HttpClient\HttpClient;

class HelloApiService
{
    private string $token;
    private string $userAgent;
    private string $baseUrl;

    public function __construct(string $token, string $userAgent, string $baseUrl)
    {
        $this->token = $token;
        $this->userAgent = $userAgent;
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'User-Agent' => $this->userAgent,
        ];
    }

    public function checkAccess(): bool
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $this->baseUrl . '/apicheck', [
            'headers' => $this->getHeaders(),
        ]);

        $data = $response->toArray(false);
        return !isset($data['error']);
    }

    public function getAllPlugins(): array
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $this->baseUrl . '/wordpress-plugins', [
            'headers' => $this->getHeaders(),
        ]);

        $data = $response->toArray(false);
        return $data['results'] ?? [];
    }

    public function getPluginDownloadUrl(string $itemId): string
    {
        $client = HttpClient::create();
        $response = $client->request('GET', $this->baseUrl . '/download?hp_item_id=' . $itemId, [
            'headers' => $this->getHeaders(),
        ]);

        $data = $response->toArray(false);
        return $data['wordpress_plugin'] ?? throw new \RuntimeException('No download URL found');
    }
}
