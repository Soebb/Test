<?php

namespace App\Services;

use danog\MadelineProto\API;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
use danog\MadelineProto\WrappedFuture;
use danog\MadelineProto\Stream\MTProtoTransport\FullStream;
use danog\MadelineProto\Stream\MTProtoTransport\HttpsStream;
use Illuminate\Support\Facades\File;
use RuntimeException;

class MadelineTelegram
{
    public function sessionPath(): string
    {
        $directory = storage_path('app/private/madeline');
        File::ensureDirectoryExists($directory);

        return $directory.DIRECTORY_SEPARATOR.'session.madeline';
    }

    public function makeClient(int $apiId, string $apiHash, bool $httpsTransport = false): API
    {
        if ($apiId <= 0 || trim($apiHash) === '') {
            throw new RuntimeException('Enter a valid Telegram API ID and API Hash.');
        }

        $settings = new Settings();
        $settings->getAppInfo()
            ->setApiId($apiId)
            ->setApiHash(trim($apiHash));
        // Login and session checks use Telegram's native TCP transport.
        // MadelineProto's built-in DC IP table avoids DNS lookups for login.
        // File uploads explicitly opt into HTTPS.
        $settings->getConnection()
            ->setProtocol($httpsTransport ? HttpsStream::class : FullStream::class)
            ->setUseDoH(false);

        // NativePHP's Android process may have a read-only working directory.
        // Never let MadelineProto fall back to ./MadelineProto.log.
        $logDirectory = storage_path('app/private/madeline');
        File::ensureDirectoryExists($logDirectory);
        $settings->getLogger()
            ->setType(Logger::FILE_LOGGER)
            ->setExtra($logDirectory.DIRECTORY_SEPARATOR.'MadelineProto.log');

        // PrimeModule's Python fallback copies helper scripts into getcwd().
        // NativePHP may start with "/" as its process working directory, which
        // is read-only on Android. Temporarily use the app's writable private
        // directory while MadelineProto initializes its authorization key.
        $runtimeDirectory = $logDirectory.DIRECTORY_SEPARATOR.'runtime';
        File::ensureDirectoryExists($runtimeDirectory);
        $previousDirectory = getcwd();

        if (! @chdir($runtimeDirectory)) {
            throw new RuntimeException('Could not switch to MadelineProto writable runtime directory.');
        }

        try {
            return new API($this->sessionPath(), $settings);
        } finally {
            if ($previousDirectory !== false) {
                @chdir($previousDirectory);
            }
        }
    }

    /**
     * Await MadelineProto Amp futures inside the NativePHP action so network
     * errors are caught instead of surfacing as UnhandledFutureError on destroy.
     */
    private function awaitIfNeeded(mixed $result): mixed
    {
        if ($result instanceof WrappedFuture || $result instanceof \Amp\Future) {
            return $result->await();
        }

        return $result;
    }

    public function requestCode(int $apiId, string $apiHash, string $phone): void
    {
        $api = $this->makeClient($apiId, $apiHash, false);
        $this->awaitIfNeeded($api->phoneLogin(trim($phone)));
    }

    public function completeCode(int $apiId, string $apiHash, string $code): array
    {
        $api = $this->makeClient($apiId, $apiHash);
        $authorization = $this->awaitIfNeeded($api->completePhoneLogin(trim($code)));

        if (($authorization['_'] ?? null) === 'account.password') {
            return ['authorized' => false, 'needs_2fa' => true];
        }

        if (($authorization['_'] ?? null) === 'account.needSignup') {
            return ['authorized' => false, 'needs_signup' => true];
        }

        return ['authorized' => true, 'needs_2fa' => false];
    }

    public function isAuthorized(int $apiId, string $apiHash): bool
    {
        try {
            $api = $this->makeClient($apiId, $apiHash);
            $this->awaitIfNeeded($api->getSelf());
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function upload(
        int $apiId,
        string $apiHash,
        string $peer,
        string $filePath,
        string $caption,
        callable $progress
    ): void {
        if (! is_file($filePath) || ! is_readable($filePath)) {
            throw new RuntimeException('The selected file is not available to the app.');
        }

        $api = $this->makeClient($apiId, $apiHash);
        $this->awaitIfNeeded($api->sendDocument(
            peer: trim($peer),
            file: new LocalFile($filePath),
            caption: $caption,
            callback: $progress,
            fileName: basename($filePath),
        ));
    }
}
