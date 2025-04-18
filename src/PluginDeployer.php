<?php

namespace MilBar\HelloPackGitHubSync;

use Symfony\Component\Filesystem\Filesystem;

/**
 * Class PluginDeployer
 *
 * @since 1.0.0
 */
class PluginDeployer
{
    private string $pluginSlug;
    private GitService $git;
    private HelloApiService $hello;
    private string $pluginPath;

    /**
     * PluginDeployer constructor.
     *
     * @param string $pluginSlug
     * @param GitService $gitHub
     * @param HelloApiService $hello
     * @since 1.0.0
     */
    public function __construct(string $pluginSlug, GitService $gitHub, HelloApiService $hello)
    {
        $this->pluginSlug = $pluginSlug;
        $this->git = $gitHub;
        $this->hello = $hello;
        $this->pluginPath = Helpers::rootPath('plugins' . DIRECTORY_SEPARATOR . $pluginSlug);
    }

    /**
     * Run the plugin deployment process.
     *
     * @since 1.0.0
     */
    public function run(): void
    {
        Helpers::message("Processing plugin: $this->pluginSlug");
        $pluginMeta = $this->hello->getPluginMeta($this->pluginSlug);

        if (!$pluginMeta) {
            Helpers::message("No plugin meta found for $this->pluginSlug", 'error');
            return;
        }

        $version = $pluginMeta['version'] ?? 'none';
        $downloadUrl = $this->hello->getPluginDownloadUrl($pluginMeta['id']);
        $repoSlug = Helpers::getRepoName($this->pluginSlug);
        $pluginName = $pluginMeta['name'];
        $description = "A mirror repository from HelloPack repository for $pluginName.";
        $description .= $pluginMeta['description'] ? ' - ' . $pluginMeta['description'] : '';

        if ($this->git->repoExists($repoSlug)) {
            $latestTag = $this->git->getLatestTag($repoSlug) ?? 'none';
            Helpers::message("HelloPack Version: $version");
            Helpers::message("GitHub Version: $latestTag");
            if ($latestTag && version_compare($version, $latestTag, '<=')) {
                Helpers::message("Already up-to-date (version {$latestTag}). Skipping...");
                return;
            }
        } else {
            Helpers::message("Creating GitHub repo...");
            $this->git->createRepo($repoSlug, $description);
        }

        if (!$this->git->isGitInitialized($this->pluginPath)) {
            Helpers::message("The git repository not initialized");
            $this->createPluginPath($this->pluginPath);
            $this->git->config($this->pluginPath, $this->pluginSlug, $repoSlug);
        }

        $this->git->pull($this->pluginPath);
        $this->cleanupDirectory($this->pluginPath);
        $this->downloadAndExtract($downloadUrl);
        $this->generateComposerJson($pluginMeta);
        $this->copyGitignoreTemplate($this->pluginPath);
        $this->git->push($version, $this->pluginPath);
        $this->cleanupDirectory($this->pluginPath);
    }

    /**
     * Download and extract the plugin from the given URL.
     *
     * @param string $url
     * @since 1.0.0
     */
    private function downloadAndExtract(string $url): void
    {
        $fs = new Filesystem();

        $zipPath = tempnam(sys_get_temp_dir(), 'plugin_') . '.zip';
        file_put_contents($zipPath, file_get_contents($url));

        $tempExtractPath = sys_get_temp_dir() . '/plugin_extract_' . uniqid();
        mkdir($tempExtractPath);

        $zip = new \ZipArchive();
        if ($zip->open($zipPath) === true) {
            $zip->extractTo($tempExtractPath);  // Extract to temporary location
            $zip->close();
        } else {
            throw new \RuntimeException("Failed to extract ZIP: $zipPath");
        }

        unlink($zipPath);  // Clean up the zip file

        // Move contents of the extracted folder to the plugin base path
        $extractedFolders = scandir($tempExtractPath);
        $validDirs = array_filter($extractedFolders, fn($d) => $d !== '.' && $d !== '..');
        $source = $tempExtractPath . '/' . reset($validDirs);

        $fs->mkdir($this->pluginPath);
        $fs->mirror($source, $this->pluginPath);  // Copy extracted files into the plugin path
        $fs->remove($tempExtractPath);  // Clean up the temporary extraction folder
    }

    /**
     * Generate the composer.json file for the plugin.
     *
     * @param array $pluginMeta
     * @since 1.0.0
     */
    private function generateComposerJson(array $pluginMeta): void
    {
        $composer = [
            'name' => $this->git->getComposerPackageName($this->pluginSlug),
            'description' => $pluginMeta['description'],
            'author' => $pluginMeta['author'],
            'version' => $pluginMeta['version'],
            'type' => 'wordpress-plugin',
        ];

        file_put_contents($this->pluginPath . '/composer.json', json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Clean up the specified directory by removing non-Git files.
     *
     * @param string $directory
     * @since 1.0.0
     */
    private function cleanupDirectory(string $directory): void
    {
        $fs = new Filesystem();
        if (!$fs->exists($directory)) return;

        $files = scandir($directory);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || $file === '.git') continue;
            $filePath = $directory . DIRECTORY_SEPARATOR . $file;
            is_dir($filePath) ? $fs->remove($filePath) : unlink($filePath);
        }

        Helpers::message("Cleaned up all non-Git files in {$directory}.");
    }

    /**
     * Copy the .gitignore template to the destination directory.
     *
     * @param string $destinationDir
     * @since 1.0.0
     */
    private function copyGitignoreTemplate(string $destinationDir): void
    {
        $fs = new Filesystem();
        $source = Helpers::rootPath('templates/gitignore.txt'); // adjust if needed
        $target = rtrim($destinationDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . '.gitignore';

        if (!$fs->exists($source)) {
            Helpers::message("Gitignore template not found: $source", 'error');
            return;
        }

        try {
            $fs->copy($source, $target, true); // overwrite = true
            Helpers::message("Copied .gitignore to $destinationDir", 'info');
        } catch (\Exception $e) {
            Helpers::message("Failed to copy .gitignore: " . $e->getMessage(), 'error');
        }
    }

    /**
     * Create the plugin path if it does not exist.
     *
     * @param string $pluginPath
     * @return bool
     * @since 1.0.0
     */
    private function createPluginPath(string $pluginPath): bool
    {
        $fs = new Filesystem();

        if (!$fs->exists($pluginPath)) {
            Helpers::message("Creating plugin directory \"$pluginPath\"...");

            try {
                $fs->mkdir($pluginPath, 0777);
                Helpers::message("Plugin directory successfully created.");
                return true;
            } catch (\Exception $e) {
                Helpers::message("Failed to create plugin directory: " . $e->getMessage(), 'error');
                return false;
            }
        }

        return false;
    }
}
