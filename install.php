<?php

// Скрипт установки spectro-utils

error_reporting(E_ALL);
ini_set('display_errors', 1);

// --- Конфигурация ---
$repoOwner = 'koshacha';
$repoName = 'spectro-utils';
$installDir = __DIR__;
$tempExtractDir = null;
$tempZipFile = null;

// --- Основная логика ---
try {
    if (is_dir($installDir . '/utils')) {
        throw new Exception("Директория 'utils' уже существует. Установка прервана.");
    }

    echo "Начинаем установку...\n";

    // Получение информации о последнем релизе
    echo "Получение информации о последнем релизе...\n";
    $apiUrl = "https://api.github.com/repos/{$repoOwner}/{$repoName}/releases/latest";
    $context = stream_context_create(['http' => ['header' => "User-Agent: PHP-Installer\r\n"]]);
    $response = @file_get_contents($apiUrl, false, $context);
    if ($response === false) {
        throw new Exception("Не удалось получить информацию о релизе с GitHub.");
    }
    $releaseData = json_decode($response, true);
    if (!isset($releaseData['zipball_url'])) {
        throw new Exception("Не удалось найти URL для скачивания в ответе GitHub API.");
    }
    $zipUrl = $releaseData['zipball_url'];
    echo "Найден последний релиз: {$releaseData['tag_name']}\n";

    // Скачивание архива
    $tempZipFile = $installDir . '/spectro-utils-latest.zip';
    echo "Скачивание архива из {$zipUrl}...\n";
    if (!@file_put_contents($tempZipFile, fopen($zipUrl, 'r', false, $context))) {
        throw new Exception("Не удалось скачать архив с релизом.");
    }
    echo "Скачивание завершено.\n";

    // Распаковка
    $zip = new ZipArchive;
    if ($zip->open($tempZipFile) !== TRUE) {
        throw new Exception("Не удалось открыть скачанный архив.");
    }
    $tempExtractDir = $installDir . '/spectro-utils-extract';
    rrmdir($tempExtractDir);
    mkdir($tempExtractDir, 0777, true);
    echo "Распаковка файлов...\n";
    $zip->extractTo($tempExtractDir);
    $zip->close();

    // Перемещение файлов
    $extractedSubDir = glob($tempExtractDir . '/*')[0] ?? null;
    if (!$extractedSubDir || !is_dir($extractedSubDir)) {
        throw new Exception("Не удалось найти контент в распакованном архиве.");
    }
    echo "Перемещение файлов в директорию установки...\n";
    recurse_copy($extractedSubDir, $installDir);

    echo "Установка успешно завершена!\n";

} catch (Exception $e) {
    echo "\nОшибка установки: " . $e->getMessage() . "\n";
    exit(1);
} finally {
    // Очистка
    if ($tempZipFile && file_exists($tempZipFile)) {
        unlink($tempZipFile);
    }
    if ($tempExtractDir && is_dir($tempExtractDir)) {
        rrmdir($tempExtractDir);
    }
}

// --- Вспомогательные функции ---

function recurse_copy($src, $dst) {
    if (!is_dir($src)) return;
    $dir = opendir($src);
    @mkdir($dst, 0777, true);
    while (false !== ($file = readdir($dir))) {
        if (($file != '.') && ($file != '..')) {
            $srcPath = $src . '/' . $file;
            $dstPath = $dst . '/' . $file;
            if (is_dir($srcPath)) {
                recurse_copy($srcPath, $dstPath);
            } else {
                if (basename($dstPath) === 'install.php') {
                    continue;
                }
                copy($srcPath, $dstPath);
            }
        }
    }
    closedir($dir);
}

function rrmdir(string $dir) {
    if (!is_dir($dir)) return;
    $objects = scandir($dir);
    foreach ($objects as $object) {
        if ($object != "." && $object != "..") {
            $path = $dir . DIRECTORY_SEPARATOR . $object;
            if (is_dir($path)) {
                rrmdir($path);
            } else {
                unlink($path);
            }
        }
    }
    rmdir($dir);
}

?>