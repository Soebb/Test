<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

use danog\\MadelineProto\\API;

$apiFile = (new ReflectionClass(API::class))->getFileName();
$mtprotoFile = (new ReflectionClass(danog\\MadelineProto\\MTProto::class))->getFileName();

if (! is_string($apiFile) || ! is_file($apiFile) || ! is_string($mtprotoFile) || ! is_file($mtprotoFile)) {
    throw new RuntimeException('Could not locate MadelineProto source files for the NativePHP compatibility patch.');
}

/**
 * Replace one exact source block. Fail closed if the upstream implementation changes.
 */
$replaceOnce = static function (string $path, string $needle, string $replacement, string $label): void {
    $source = file_get_contents($path);

    if (! is_string($source) || substr_count($source, $needle) !== 1) {
        throw new RuntimeException("Expected exactly one match for {$label}; refusing to apply an unsafe MadelineProto patch.");
    }

    $updated = str_replace($needle, $replacement, $source, $count);

    if ($count !== 1 || file_put_contents($path, $updated) === false) {
        throw new RuntimeException("Could not apply MadelineProto patch: {$label}.");
    }
};

$apiNeedle = <<<'SOURCE'
        $selfRestart = !$forceFull && isset($_GET['MadelineSelfRestart']) && !Magic::$altervista;
SOURCE;

$apiReplacement = <<<'SOURCE'
        // NativePHP embeds PHP in the Android app process and cannot launch a separate IPC worker.
        // Force full in-process session loading; MTProto's IPC listener is disabled by the companion patch.
        if (\defined('NATIVEPHP_EMBEDDED_RUNTIME') && NATIVEPHP_EMBEDDED_RUNTIME === true) {
            $forceFull = true;
        }
        $selfRestart = !$forceFull && isset($_GET['MadelineSelfRestart']) && !Magic::$altervista;
SOURCE;

$replaceOnce($apiFile, $apiNeedle, $apiReplacement, 'API in-process session loading');

$ipcNeedle = <<<'SOURCE'
        // Start IPC server
        if (!$this->ipcServer) {
            $this->ipcServer = new Server($this);
            $this->ipcServer->setIpcPath($this->wrapper->getSession());
        }
        $this->ipcServer->start();
SOURCE;

$ipcReplacement = <<<'SOURCE'
        // NativePHP keeps this MTProto instance in its embedded PHP runtime.
        // Do not start MadelineProto's external IPC server in that environment.
        if (!\defined('NATIVEPHP_EMBEDDED_RUNTIME') || NATIVEPHP_EMBEDDED_RUNTIME !== true) {
            // Start IPC server
            if (!$this->ipcServer) {
                $this->ipcServer = new Server($this);
                $this->ipcServer->setIpcPath($this->wrapper->getSession());
            }
            $this->ipcServer->start();
        }
SOURCE;

$replaceOnce($mtprotoFile, $ipcNeedle, $ipcReplacement, 'MTProto IPC server guard');

echo 'Applied NativePHP in-process compatibility patch to MadelineProto '.API::RELEASE.PHP_EOL;
