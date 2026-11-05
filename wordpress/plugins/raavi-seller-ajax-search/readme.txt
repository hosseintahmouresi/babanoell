Raavi Seller AJAX Search for Elementor
======================================

کاربرد
-----
یک المان اختصاصی Elementor برای جستجوی AJAX در post type با نام seller.
این نسخه Loop Grid را دوباره از سرور رندر نمی‌کند؛ فقط ID پست‌های مطابق را با AJAX دریافت می‌کند و آیتم‌های همان Loop Grid موجود را نمایش/مخفی می‌کند. به همین دلیل با Template خود Loop Grid، Dynamic Tags و ظاهر فعلی کارت‌ها تداخل ندارد.

نصب و استفاده
-------------
1) افزونه را نصب و فعال کنید.
2) در Elementor المان «جستجوی AJAX فروشگاه‌ها» را اضافه کنید.
3) روی Loop Grid فروشگاه‌ها در Advanced > CSS Classes کلاس زیر را بگذارید:
   raavi-seller-results
4) در خود المان Search، Target Selector به صورت پیش‌فرض همین است:
   .raavi-seller-results
5) Loop Grid باید آیتم‌های مورد نیاز را در DOM داشته باشد. در فایل فعلی شما posts_per_page برابر 9999 است، بنابراین این روش با ساختار فعلی سازگار است.

ویژگی‌ها
--------
- سرچ فقط در عنوان sellerها
- AJAX واقعی از admin-ajax.php بدون Reload صفحه
- دکمه type=button و جلوگیری از submit/redirect
- جستجو هنگام تایپ با debounce و AbortController
- پشتیبانی از Enter و Escape
- سازگاری ی/ي و ک/ك
- عدم دستکاری Query اصلی Elementor
- عدم رندر مجدد Loop Template
- حفظ فیلترهای Taxonomy موجود: فقط کلاس مخفی‌کننده اختصاصی خود افزونه اضافه/حذف می‌شود
- MutationObserver برای اعمال دوباره سرچ اگر Elementor Loop را بعد از فیلتر taxonomy بازسازی کند

نکته
----
اگر نسخه قبلی Seller AJAX Search for Elementor نصب است، برای جلوگیری از هر نوع تداخل آن را غیرفعال کنید. نسخه جدید به Query ID seller_ajax نیاز ندارد.
