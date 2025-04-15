<?php

namespace MilBar\HelloPackGitHubSync;

use Symfony\Component\Filesystem\Filesystem;

class PluginDeployer
{
    private string $pluginSlug;
    private GitHubService $gitHub;
    private HelloApiService $hello;
    private string $basePath;

    public function __construct(string $pluginSlug, GitHubService $gitHub, HelloApiService $hello)
    {
        $this->pluginSlug = $pluginSlug;
        $this->gitHub = $gitHub;
        $this->hello = $hello;
        $this->basePath = __DIR__ . '/../plugins/' . $pluginSlug;
    }

    public function run(): void
    {
        echo "🔍 Processing {$this->pluginSlug}...\n";

        $pluginMeta = $this->getPluginMeta();
        $version = $pluginMeta['version'];
        $downloadUrl = $this->hello->getPluginDownloadUrl($pluginMeta['id']);

        $repoName = "hellopack-{$this->pluginSlug}";
        $exists = $this->gitHub->repoExists($repoName);

        if ($exists) {
            $latestTag = $this->gitHub->getLatestTag($repoName);
            echo "🔖 GitHub latest tag: " . ($latestTag ?? 'none') . "\n";
            if ($latestTag && version_compare($version, $latestTag, '<=')) {
                echo "✅ Already up-to-date (version {$latestTag}). Skipping...\n";
                return;
            }
        } else {
            echo "📦 Creating GitHub repo...\n";
            $this->gitHub->createRepo($repoName);
        }

        echo "⬇️ Downloading plugin zip...\n";
        $this->downloadAndExtract($downloadUrl);

        echo "🧩 Generating composer.json...\n";
        $this->generateComposerJson($version);

        echo "🚀 Pushing to GitHub...\n";
        $this->gitHub->pushToGitHub($repoName, $version, $this->basePath);

        echo "✅ {$this->pluginSlug} deployed.\n";

        (new \Symfony\Component\Filesystem\Filesystem())->remove($this->basePath);

        echo "✅ {$this->pluginSlug} folder cleaned up.\n";
    }

    private function getPluginMeta(): array
    {
        $all = $this->hello->getAllPlugins();
        foreach ($all as $plugin) {
            $meta = $plugin['item']['wordpress_plugin_metadata'];
            $slug = Helpers::slugify($meta['plugin_name']);
            if ($slug === $this->pluginSlug) {
                return [
                    'id' => $plugin['item']['id'],
                    'version' => $meta['version'],
                ];
            }
        }

        throw new \RuntimeException("Plugin metadata not found for slug: {$this->pluginSlug}");
    }

    private function downloadAndExtract(string $url): void
    {
        $fs = new Filesystem();

        // Remove existing directory
        if ($fs->exists($this->basePath)) {
            $fs->remove($this->basePath);
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'plugin_') . '.zip';
        file_put_contents($zipPath, file_get_contents($url));

        $tempExtractPath = sys_get_temp_dir() . '/plugin_extract_' . uniqid();
        mkdir($tempExtractPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tempExtractPath);
            $zip->close();
        } else {
            throw new \RuntimeException("Failed to extract ZIP: $zipPath");
        }

        unlink($zipPath);

        // Move contents of inner folder if needed
        $extractedFolders = scandir($tempExtractPath);
        $validDirs = array_filter($extractedFolders, fn($d) => $d !== '.' && $d !== '..');
        $source = $tempExtractPath . '/' . reset($validDirs);

        $fs->mkdir($this->basePath);
        $fs->mirror($source, $this->basePath);
        $fs->remove($tempExtractPath);
    }

    private function generateComposerJson(string $version): void
    {
        $name = $this->gitHub->getComposerPackageName($this->pluginSlug);
        $composer = [
            'name' => $name,
            'type' => 'wordpress-plugin',
            'version' => $version,
            'require' => new \stdClass(),
        ];

        file_put_contents($this->basePath . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
