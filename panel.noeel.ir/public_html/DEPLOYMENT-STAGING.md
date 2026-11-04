# راه‌اندازی Staging برای پایان فاز 1M

پیشنهاد: ابتدا یک کپی staging مستقل از Identity و Panel بسازید؛ production را فعال نکنید.

## Identity staging

در `config/local.env.php` نسخه staging:

```php
'APP_ENV' => 'staging',
'AUTH_ENVIRONMENT' => 'staging',
'AUTH_ALLOW_HTTP_LOGIN_IN_STAGING' => 'true',
'AUTH_LOGIN_ENABLED' => 'true',
'AUTH_SESSION_ISSUING_ENABLED' => 'true',
'AUTH_REFRESH_ENABLED' => 'true',
```

`AUTH_TOKEN_PEPPER` باید مقدار قوی نصب‌شده خود staging باشد و تغییر نکند.

## Panel staging

در installer:

- Environment: `staging`
- Identity URL: آدرس همان Identity staging

سپس `/healthz` Panel باید `identity: ok` بدهد و صفحه ورود قابل تست است.
