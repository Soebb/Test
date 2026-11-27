<?php

namespace App\NativeComponents;

use App\Services\MadelineTelegram;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Native\Mobile\Attributes\On;
use Native\Mobile\Edge\NativeComponent;
use Smronju\NativephpDocumentPicker\Events\DocumentPicked;
use Smronju\NativephpDocumentPicker\Facades\DocumentPicker;
use Throwable;

class Home extends NativeComponent
{
    public string $apiId = '';
    public string $apiHash = '';
    public string $phone = '';
    public string $code = '';
    public string $channel = '';
    public string $caption = '';
    public string $selectedName = '';
    public string $selectedPath = '';
    public string $status = 'Enter your Telegram API credentials to begin.';
    public string $logText = '';
    public int $uploadProgress = 0;
    public bool $codeRequested = false;
    public bool $loggedIn = false;
    public bool $uploading = false;

    public function mount(): void
    {
        $saved = $this->readSettings();
        $this->apiId = (string) ($saved['api_id'] ?? '');
        $this->apiHash = (string) ($saved['api_hash'] ?? '');
        $this->phone = (string) ($saved['phone'] ?? '');

        $requiredExtensions = ['xml', 'dom', 'fileinfo', 'json', 'filter', 'hash', 'zlib'];
        $missingExtensions = array_values(array_filter(
            $requiredExtensions,
            static fn (string $extension): bool => ! extension_loaded($extension)
        ));

        if ($missingExtensions !== []) {
            $this->status = 'Android PHP runtime is missing required extensions: '.implode(', ', $missingExtensions);
            $this->log($this->status);
            return;
        }

        if ($this->apiId !== '' && $this->apiHash !== '') {
            $this->loggedIn = app(MadelineTelegram::class)->isAuthorized((int) $this->apiId, $this->apiHash);
            if ($this->loggedIn) {
                $this->status = 'Telegram session restored.';
                $this->log('Existing MadelineProto session restored.');
            }
        }
    }

    public function requestCode(): void
    {
        try {
            $this->validateCredentials();
            if (trim($this->phone) === '') {
                throw new \RuntimeException('Enter your phone number in international format.');
            }

            $this->saveSettings();
            app(MadelineTelegram::class)->requestCode((int) $this->apiId, $this->apiHash, $this->phone);
            $this->codeRequested = true;
            $this->status = 'Login code request submitted. Check Telegram for the code.';
            $this->log('Phone login requested using HTTPS transport.');
        } catch (Throwable $exception) {
            $this->status = $exception->getMessage();
            $this->log('Login request failed: '.$exception->getMessage());
        }
    }

    public function verifyCode(): void
    {
        try {
            $this->validateCredentials();
            if (trim($this->code) === '') {
                throw new \RuntimeException('Enter the Telegram login code.');
            }

            $result = app(MadelineTelegram::class)->completeCode(
                (int) $this->apiId,
                $this->apiHash,
                $this->code
            );

            if ($result['needs_2fa'] ?? false) {
                $this->status = 'This account requires a two-step verification password. Password entry is not included in this first test build.';
                $this->log('Telegram requested the account 2FA password; this build does not collect it.');
                return;
            }

            if ($result['needs_signup'] ?? false) {
                $this->status = 'Telegram requires signup details. This test build only supports existing accounts.';
                $this->log('Telegram returned account.needSignup.');
                return;
            }

            $this->loggedIn = true;
            $this->codeRequested = false;
            $this->code = '';
            $this->saveSettings();
            $this->status = 'Successfully logged in. Session is saved on this device.';
            $this->log('Telegram authorization completed and session saved.');
        } catch (Throwable $exception) {
            $this->status = $exception->getMessage();
            $this->log('Code verification failed: '.$exception->getMessage());
        }
    }

    public function pickFile(): void
    {
        DocumentPicker::pick();
    }

    #[On(DocumentPicked::class)]
    public function onDocumentPicked(?string $path = null, ?string $name = null): void
    {
        if ($path === null || ! is_file($path)) {
            $this->log('File selection cancelled or unavailable.');
            return;
        }

        $safeName = basename((string) $name);
        $safeName = $safeName !== '' ? $safeName : basename($path);
        $targetDirectory = storage_path('app/private/outbox');
        File::ensureDirectoryExists($targetDirectory);
        $targetPath = $targetDirectory.DIRECTORY_SEPARATOR.uniqid('upload_', true).'_'.$safeName;

        if (! copy($path, $targetPath)) {
            $this->status = 'Could not copy the selected file into app storage.';
            return;
        }

        $this->selectedPath = $targetPath;
        $this->selectedName = $safeName;
        $this->log('Selected file: '.$safeName.' ('.number_format(filesize($targetPath)).' bytes).');
    }

    public function uploadFile(): void
    {
        if (! $this->loggedIn) {
            $this->status = 'Log in to Telegram before uploading.';
            return;
        }

        if (trim($this->channel) === '') {
            $this->status = 'Enter a public channel username or channel ID.';
            return;
        }

        if ($this->selectedPath === '' || ! is_file($this->selectedPath)) {
            $this->status = 'Choose a file first.';
            return;
        }

        try {
            $this->uploading = true;
            $this->uploadProgress = 0;
            $this->status = 'Uploading over HTTPS...';
            $this->log('Upload started: '.$this->selectedName);

            app(MadelineTelegram::class)->upload(
                (int) $this->apiId,
                $this->apiHash,
                $this->channel,
                $this->selectedPath,
                $this->caption,
                function (float $progress, float $speed = 0, float $time = 0): void {
                    $this->uploadProgress = max(0, min(100, (int) round($progress)));
                }
            );

            $this->uploadProgress = 100;
            $this->status = 'Upload completed successfully.';
            $this->log('Upload completed.');
        } catch (Throwable $exception) {
            $this->status = 'Upload failed: '.$exception->getMessage();
            $this->log('Upload failed: '.$exception->getMessage());
        } finally {
            $this->uploading = false;
        }
    }

    private function validateCredentials(): void
    {
        if (! ctype_digit($this->apiId) || (int) $this->apiId <= 0 || trim($this->apiHash) === '') {
            throw new \RuntimeException('Enter a valid Telegram API ID and API Hash.');
        }
    }

    private function settingsPath(): string
    {
        $directory = storage_path('app/private');
        File::ensureDirectoryExists($directory);

        return $directory.DIRECTORY_SEPARATOR.'telegram-settings.json';
    }

    private function readSettings(): array
    {
        $path = $this->settingsPath();
        if (! is_file($path)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($path), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function saveSettings(): void
    {
        file_put_contents($this->settingsPath(), json_encode([
            'api_id' => $this->apiId,
            'api_hash' => $this->apiHash,
            'phone' => $this->phone,
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        @chmod($this->settingsPath(), 0600);
    }

    private function log(string $message): void
    {
        $line = '['.date('H:i:s').'] '.$message;
        $this->logText = trim($this->logText."\n".$line);
        $this->logText = implode("\n", array_slice(explode("\n", $this->logText), -30));
    }

    public function render(): View
    {
        return view('native.home');
    }
}
