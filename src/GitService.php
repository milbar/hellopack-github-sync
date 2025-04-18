<?php

namespace MilBar\HelloPackGitHubSync;

use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

class GitService
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

    /**
     * Get the headers for the HTTP client.
     *
     * @return array The headers for the HTTP request.
     * @since 1.0.0
     */
    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->token,
            'Accept' => 'application/vnd.github+json',
            'User-Agent' => 'milbareu-hellopack-sync'
        ];
    }

    /**
     * Check if a repository exists.
     *
     * @param string $repoName The name of the repository.
     * @return bool True if the repository exists, false otherwise.
     * @since 1.0.0
     */
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

    /**
     * Create a new repository.
     *
     * @param string $repoName The name of the repository.
     * @param string $description A description of the repository.
     * @since 1.0.0
     */
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

    /**
     * Get the latest tag from the repository.
     *
     * @param string $repoName The name from the repository.
     * @return string|null The latest tag name or null if not found.
     * @since 1.0.0
     */
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

    /**
     * Configure the Git repository for a plugin.
     *
     * @param string $pluginPath The path to the plugin.
     * @param string $pluginSlug The slug of the plugin.
     * @param string $repoSlug The slug of the repository.
     * @since 1.0.0
     */
    public function config(string $pluginPath, string $pluginSlug, string $repoSlug): void
    {
        if (!is_dir($pluginPath)) {
            Helpers::message("Plugin path does not exist: {$pluginPath}", 'error');
            return;
        }

        $gitPath = $pluginPath . '/.git';
        if (!is_dir($gitPath)) {
            Helpers::message("Initializing Git repo in {$pluginSlug}...");
            $remote = "git@github.com:{$this->organization}/$repoSlug.git";
            $commands = [
                "cd " . escapeshellarg($pluginPath),
                "git init -b main",
                "git config user.name " . escapeshellarg(Helpers::getBotName()),
                "git config user.email " . escapeshellarg(Helpers::getBotEmail()),
                "git config core.safecrlf false",
                "git remote add origin " . escapeshellarg($remote) . " || echo 'Remote already added'",
            ];

            $output = shell_exec(implode(' && ', $commands) . ' 2>&1');
            echo $output;
        }
    }

    /**
     * Pull the latest changes from the Git repository.
     *
     * @param string $pluginPath The path to the plugin.
     * @since 1.0.0
     */
    public function pull(string $pluginPath): void
    {
        if (!is_dir($pluginPath)) {
            Helpers::message("Plugin path does not exist: {$pluginPath}", 'error');
            return;
        }

        $gitPath = $pluginPath . '/.git';
        if (is_dir($gitPath)) {
            Helpers::message("Pulling latest changes from git...");

            $commands = [
                "cd " . escapeshellarg($pluginPath),
                "git fetch origin --quiet",
                "git pull origin main --strategy=ours --quiet || echo 'ℹ️  No remote branch to pull'"
            ];

            $output = shell_exec(implode(' && ', $commands) . ' 2>&1');
            echo $output;
        }
    }

    /**
     * Push local changes to the Git repository.
     *
     * @param string $version The version to tag.
     * @param string $pluginPath The path to the plugin.
     * @since 1.0.0
     */
    public function push(string $version, string $pluginPath): void
    {
        $gitPath = $pluginPath . '/.git';
        if (is_dir($gitPath)) {
            Helpers::message("Pushing local changes to git...");
            $versionSafe = escapeshellarg("Update plugin to version $version");
            $commands = [
                "cd $pluginPath",
                "git add .",
                "git commit -m $versionSafe",
                "git push -u origin main",
                "git tag $version",
                "git push origin $version"
            ];

            $output = shell_exec(implode(' && ', $commands) . ' 2>&1');
            echo $output;
        }
    }

    /**
     * Get the repository name based on the plugin slug.
     *
     * @param string $pluginSlug The slug of the plugin.
     * @return string The repository name.
     * @since 1.0.0
     */
    public function getRepoName(string $pluginSlug): string
    {
        return $this->repoPrefix
            ? "{$this->repoPrefix}-{$pluginSlug}"
            : $pluginSlug;
    }

    /**
     * Get the Composer package name for the plugin.
     *
     * @param string $pluginSlug The slug of the plugin.
     * @return string The Composer package name.
     * @since 1.0.0
     */
    public function getComposerPackageName(string $pluginSlug): string
    {
        return "{$this->organization}/{$this->getRepoName($pluginSlug)}";
    }

    /**
     * Check if a Git repository is initialized at the given path.
     *
     * @param string $path The path to check.
     * @return bool True if Git is initialized, false otherwise.
     * @since 1.0.0
     */
    public function isGitInitialized(string $path): bool
    {
        $gitDir = $path . '/.git';
        return is_dir($path) && is_dir($gitDir) && is_file($gitDir . '/HEAD');
    }
}
