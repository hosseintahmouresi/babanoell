# Babanoell Identity 1.2.4

## UI asset architecture

- همهٔ CSS از `install.php` و `update.php` خارج و در `public/assets/babanoell-ui.css` متمرکز شد.
- JavaScript رابط از PHP خارج و در `public/assets/babanoell-ui.js` نگهداری می‌شود.
- لوگوی رابط از فایل جداگانهٔ `public/assets/brand-mark.svg` خوانده می‌شود.
- `public/ui-asset.php` به‌عنوان درگاه فقط‌خواندنی و allow-list شده برای سرو CSS/JS/SVG اضافه شد تا هر دو چیدمان cPanel (docroot روی ریشه سرویس یا روی `public/`) بدون وابستگی به rewrite فایل‌های static کار کنند.
- هرگونه `style=` و `<style>` از صفحات مدیریتی حذف شد و modifierهای لازم به CSS مرکزی منتقل شدند.
- assetها با ETag، cache نسخه‌دار و `nosniff` سرو می‌شوند.

پالت: `#1D3559` / `#D0E2F2` / `#EDA900`.
