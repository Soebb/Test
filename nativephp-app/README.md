# Android app overlay

These files are copied over the official NativePHP Mobile v4 starter kit during the GitHub Actions build.

MadelineProto runs locally inside the APK; no Telegram credentials or sessions are sent to a project-owned backend.

## First version

- Phone-number + Telegram verification-code login
- API ID and API Hash entered by the user
- Explicit HTTPS transport (HttpsStream)
- Persistent local MadelineProto session
- Android system document picker
- Upload a selected file to a channel with progress and logs
- No QR login in this version
- No 2FA password field in this first test build

## Runtime requirements and limitations

MadelineProto requires PHP extensions mbstring, xml, json, fileinfo, gmp, openssl, iconv, and gd. The app checks these at startup and reports any missing extension. Android support is an experiment: MadelineProto documents Linux/UNIX support, but does not list Android as a supported platform. A successful APK build does not prove that its PHP runtime can execute MadelineProto correctly; on-device login and upload tests are still required.

Telegram controls whether a login code is delivered and by which method. MadelineProto's current documentation also warns that SMS authorization is disabled by default for new API IDs and that unofficial user-account clients may trigger account restrictions. Review the official guidance before signing in with a primary account: https://docs.madelineproto.xyz/docs/LOGIN.html

The app stores its MadelineProto session and API credentials in Android app-private storage. Do not export or share the app's private data directory.
