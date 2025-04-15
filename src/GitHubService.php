<?php

namespace MilBar\HelloPackGitHubSync;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GitHubService
{
    private HttpClientInterface $client;
    private string $token;
    private string $organization;
    private string $repoPrefix;

    public function __construct(string $token, string $organization, ?string $repoPrefix = null)
    {
        $this->token = $token;
        $this->organization = $organization;
        $this->repoPrefix = trim($repoPrefix ?? '');
        $this->client = HttpClient::create();
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'milbareu-hellopack-sync'
        ];
    }

    public function repoExists(string $repoName): bool
    {
        try {
            $response = $this->client->request('GET', "https://api.github.com/repos/{$this->organization}/{$repoName}", [
                'headers' => $this->getHeaders(),
            ]);
            return $response->getStatusCode() === 200;
        } catch (TransportExceptionInterface $e) {
            return false;
        }
    }

    public function createRepo(string $repoName, string $description = 'WordPress plugin repository'): void
    {
        $this->client->request('POST', "https://api.github.com/orgs/{$this->organization}/repos", [
            'headers' => $this->getHeaders(),
            'json' => [
                'name' => $repoName,
                'description' => $description,
                'private' => true,
                'auto_init' => false,
            ]
        ]);
    }

    public function getLatestTag(string $repoName): ?string
    {
        try {
            $response = $this->client->request('GET', "https://api.github.com/repos/{$this->organization}/{$repoName}/tags", [
                'headers' => $this->getHeaders(),
            ]);
            $tags = $response->toArray(false);
            return $tags[0]['name'] ?? null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function pushToGitHub(string $repoSlug, string $version, string $pluginPath): void
    {
        $remote = "git@github.com:{$this->organization}/$repoSlug.git";
        $versionSafe = escapeshellarg("Update plugin to version $version"); // idézőjelbe rakja biztonságosan

        $commands = [
            "cd $pluginPath",
            "git init",
            "git config user.name 'hellopack-bot'",
            "git config user.email 'bot@milbar.eu'",
            "git add .",
            "git commit -m $versionSafe",
            "git remote add origin $remote",
            "git branch -M main",
            "git push -u origin main",
            "git tag $version",
            "git push origin $version"
        ];

        $full = implode(' && ', $commands);
        shell_exec($full);
    }

    public function getOrganization(): string
    {
        return $this->organization;
    }

    public function getRepoName(string $pluginSlug): string
    {
        return $this->repoPrefix
            ? "{$this->repoPrefix}-{$pluginSlug}"
            : $pluginSlug;
    }

    public function getComposerPackageName(string $pluginSlug): string
    {
        return "{$this->organization}/{$this->getRepoName($pluginSlug)}";
    }
}
