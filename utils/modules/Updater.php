<?php

class Updater {
    private $repoOwner = 'koshacha';
    private $repoName = 'spectro-utils';
    private $currentVersion;
    private $directory;
    private $backupDirectory;

    public function __construct($directory, $currentVersion) {
        $this->directory = rtrim($directory, '/');
        $this->currentVersion = $currentVersion;
        $this->backupDirectory = $this->directory . '/backup';

        if (!is_dir($this->backupDirectory)) {
            mkdir($this->backupDirectory, 0777, true);
        }
    }

    public function hasUpdates() {
        $latestVersion = $this->getLatestVersion();
        if ($latestVersion === null) {
            return false;
        }
        return version_compare($this->currentVersion, $latestVersion, '<');
    }

    private function getLatestVersion() {
        $apiUrl = "https://api.github.com/repos/{$this->repoOwner}/{$this->repoName}/releases/latest";
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: PHP-Updater\r\n"
            ]
        ]);

        $response = @file_get_contents($apiUrl, false, $context);
        if ($response === false) {
            return null;
        }

        $data = json_decode($response, true);

        return isset($data['tag_name']) ? ltrim($data['tag_name'], 'v') : null;
    }

    public function update() {
        if (!$this->hasUpdates()) {
            return ['success' => true, 'message' => 'Already at the latest version.'];
        }

        $latestVersion = $this->getLatestVersion();
        $zipUrl = "https://github.com/{$this->repoOwner}/{$this->repoName}/zipball/v{$latestVersion}";

        $tempZipFile = $this->backupDirectory . '/update.zip';
        $tempExtractDir = $this->backupDirectory . '/extract';

        if (file_exists($tempZipFile)) unlink($tempZipFile);
        if (is_dir($tempExtractDir)) $this->rrmdir($tempExtractDir);

        $context = stream_context_create(['http' => ['header' => "User-Agent: PHP-Updater\r\n"]]);
        if (!@file_put_contents($tempZipFile, fopen($zipUrl, 'r', false, $context))) {
            return ['success' => false, 'message' => 'Failed to download update archive.'];
        }

        $zip = new ZipArchive;
        if ($zip->open($tempZipFile) !== TRUE) {
            unlink($tempZipFile);
            return ['success' => false, 'message' => 'Failed to open update archive.'];
        }
        mkdir($tempExtractDir, 0777, true);
        $zip->extractTo($tempExtractDir);
        $zip->close();
        unlink($tempZipFile);

        $extractedSubDir = glob($tempExtractDir . '/*')[0] ?? null;
        if (!$extractedSubDir || !is_dir($extractedSubDir)) {
            $this->rrmdir($tempExtractDir);
            return ['success' => false, 'message' => 'Failed to find content in extracted archive.'];
        }

        $this->backup();

        try {
            $this->recurseCopy($extractedSubDir, $this->directory);
        } catch (\Exception $e) {
            $this->rollback();
            $this->rrmdir($tempExtractDir);
            return ['success' => false, 'message' => 'Error during update. Rolled back to previous version. Details: ' . $e->getMessage()];
        }
        
        $this->rrmdir($tempExtractDir);

        return ['success' => true, 'message' => "Successfully updated to version {$latestVersion}."];
    }

    private function backup() {
        $backupTargetDir = $this->backupDirectory . '/previous_version';
        if (is_dir($backupTargetDir)) {
            $this->rrmdir($backupTargetDir);
        }
        mkdir($backupTargetDir, 0777, true);

        $items = scandir($this->directory);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;

            $sourcePath = $this->directory . '/' . $item;
            if (realpath($sourcePath) === realpath($this->backupDirectory)) {
                continue;
            }

            $destPath = $backupTargetDir . '/' . $item;
            if (is_dir($sourcePath)) {
                $this->recurseCopy($sourcePath, $destPath);
            } else {
                copy($sourcePath, $destPath);
            }
        }
    }

    public function rollback() {
        $backupSourceDir = $this->backupDirectory . '/previous_version';
        if (!is_dir($backupSourceDir) || count(scandir($backupSourceDir)) <= 2) {
            return ['success' => false, 'message' => 'No backup found to roll back to.'];
        }

        $items = scandir($this->directory);
        foreach ($items as $item) {
            if ($item == '.' || $item == '..') continue;
            $itemPath = $this->directory . '/' . $item;
            if (realpath($itemPath) === realpath($this->backupDirectory)) {
                continue;
            }
            if (is_dir($itemPath)) {
                $this->rrmdir($itemPath);
            } else {
                unlink($itemPath);
            }
        }

        $this->recurseCopy($backupSourceDir, $this->directory);

        return ['success' => true, 'message' => 'Successfully rolled back to the backed up version.'];
    }

    private function recurseCopy($src, $dst) {
        if (!is_dir($src)) return;
        $dir = opendir($src);
        @mkdir($dst, 0777, true);
        while(false !== ( $file = readdir($dir)) ) {
            if (( $file != '.' ) && ( $file != '..' )) {
                if ( is_dir($src . '/' . $file) ) {
                    $this->recurseCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        closedir($dir);
    }

    private function rrmdir(string $dir) {
        if (!is_dir($dir)) return;
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (is_dir($dir . DIRECTORY_SEPARATOR . $object))
                    $this->rrmdir($dir . DIRECTORY_SEPARATOR . $object);
                else
                    unlink($dir . DIRECTORY_SEPARATOR . $object);
            }
        }
        rmdir($dir);
    }

    public function isLatestVersion() {
        $latestVersion = $this->getLatestVersion();
        if ($latestVersion === null) {
            return true;
        }
        return version_compare($this->currentVersion, $latestVersion, '>=');
    }
}
