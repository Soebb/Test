<?php

namespace App\Services;

use danog\MadelineProto\API;
use danog\MadelineProto\LocalFile;
use danog\MadelineProto\Logger;
use danog\MadelineProto\Settings;
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

    public function makeClient(int $apiId, string $apiHash): API
    {
        if ($apiId <= 0 || trim($apiHash) === '') {
            throw new RuntimeException('Enter a valid Telegram API ID and API Hash.');
        }

        $settings = new Settings();
        $settings->getAppInfo()
            ->setApiId($apiId)
            ->setApiHash(trim($apiHash));
        $settings->getConnection()
            ->setProtocol(HttpsStream::class);

        // NativePHP's Android process may have a read-only working directory.
        // Never let MadelineProto fall back to ./MadelineProto.log.
        $logDirectory = storage_path('app/private/madeline');
        File::ensureDirectoryExists($logDirectory);
        $settings->getLogger()
            ->setType(Logger::FILE_LOGGER)
            ->setExtra($logDirectory.DIRECTORY_SEPARATOR.'MadelineProto.log');

        return new API($this->sessionPath(), $settings);
    }

    /**
     * Await MadelineProto Amp futures inside the NativePHP action so network
     * errors are caught instead of surfacing as UnhandledFutureError on destroy.
     */
    private function awaitIfNeeded(mixed $result): mixed
    {
        return $result instanceof \Amp\Future ? $result->await() : $result;
    }

    public function requestCode(int $apiId, string $apiHash, string $phone): void
    {
        $api = $this->makeClient($apiId, $apiHash);
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
