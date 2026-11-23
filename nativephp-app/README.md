# Android app overlay

These files are copied over the official NativePHP Mobile v4 starter kit during the GitHub Actions build.

The PHP runtime and native Android shell are generated from NativePHP's public starter kit. MadelineProto runs locally inside the APK; no Telegram credentials or sessions are sent to a project-owned backend.

The first build intentionally uses phone-number + verification-code login only. QR login is not included.

The app stores its MadelineProto session and API credentials in Android app-private storage. Do not export or share the app's private data directory.
