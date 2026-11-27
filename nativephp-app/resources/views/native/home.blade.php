<native:scroll-view class="safe-area bg-theme-background px-5 py-5">
    <native:column class="w-full gap-4">
        <native:column class="gap-1">
            <native:text class="text-2xl font-bold text-theme-on-surface">MadelineProto HTTPS Test</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">Personal Telegram account · HTTPS transport</native:text>
        </native:column>

        <native:column class="rounded-2xl bg-theme-surface p-4 gap-3">
            <native:text class="text-lg font-semibold text-theme-on-surface">Telegram login</native:text>
            <native:outlined-text-input label="API ID" keyboard="number" native:model.blur="apiId" />
            <native:outlined-text-input label="API Hash" secure native:model.blur="apiHash" />
            <native:outlined-text-input label="Phone number" placeholder="+12345678900" keyboard="text" native:model.debounce.150ms="phone" />

            @if (! $loggedIn)
                @if ($sessionAvailable)
                    <native:button class="w-full" @press="checkConnection">Check saved Telegram session</native:button>
                @endif
                <native:button class="w-full" @press="requestCode">Request login code</native:button>
                @if ($codeRequested)
                    <native:outlined-text-input label="Telegram login code" keyboard="number" native:model.blur="code" />
                    <native:button class="w-full" @press="verifyCode">Verify code</native:button>
                @endif
            @else
                <native:text class="text-sm font-medium text-theme-primary">Connected · session restored locally</native:text>
            @endif
        </native:column>

        <native:column class="rounded-2xl bg-theme-surface p-4 gap-3">
            <native:text class="text-lg font-semibold text-theme-on-surface">Upload to channel</native:text>
            <native:outlined-text-input label="Channel username or ID" placeholder="@yourchannel" native:model.blur="channel" />
            <native:outlined-text-input label="Caption (optional)" multiline max-lines="3" native:model.blur="caption" />
            <native:button class="w-full" @press="pickFile">Choose file</native:button>
            @if ($selectedName !== '')
                <native:text class="text-sm text-theme-on-surface-variant">Selected: {{ $selectedName }}</native:text>
            @endif
            <native:button class="w-full" :disabled="! $loggedIn || $uploading" @press="uploadFile">Upload file</native:button>
            <native:text class="text-sm text-theme-on-surface-variant">Progress: {{ $uploadProgress }}%</native:text>
        </native:column>

        <native:column class="rounded-2xl bg-theme-surface p-4 gap-2">
            <native:text class="font-semibold text-theme-on-surface">Status</native:text>
            <native:text class="text-sm text-theme-on-surface-variant">{{ $status }}</native:text>
            <native:divider />
            <native:text class="font-semibold text-theme-on-surface">Log</native:text>
            <native:text class="text-xs text-theme-on-surface-variant">{{ $logText !== '' ? $logText : 'No activity yet.' }}</native:text>
        </native:column>

        <native:text class="text-xs text-theme-on-surface-variant">This app uses MadelineProto with HTTPS transport. QR login is intentionally not included in this version.</native:text>
    </native:column>
</native:scroll-view>
