# MadelineProto HTTPS Android Test

Standalone Android application for testing MadelineProto user-account login and file uploads over Telegram's HTTPS MTProto transport.

## Status

Initial project scaffold is being prepared. The Android app is built with NativePHP Mobile and runs MadelineProto inside the app; it does not use a remote Telegram backend.

## Planned first version

- Phone-number login and Telegram verification code
- API ID and API hash entered by the user
- HTTPS transport explicitly selected (`HttpsStream`)
- Persistent local MadelineProto session
- Android document picker
- Upload a selected file to a channel with progress and logs
- No QR login in this version

## Important

MadelineProto's documentation warns that Telegram may restrict accounts using unofficial clients. Review its current login guidance before using a personal account: https://docs.madelineproto.xyz/docs/LOGIN.html

Never commit API hashes, login codes, passwords, or session files.
