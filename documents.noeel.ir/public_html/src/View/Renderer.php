<?php
declare(strict_types=1);
namespace Babanoell\Documents\View;
use Babanoell\Documents\Platform\Ui;
use Babanoell\Documents\Support\PersianDateTime;
final class Renderer {
 public static function e(string $value): string {return Ui::e($value);}
 public static function digits(string|int|float $value): string {return PersianDateTime::digits($value);}
 public static function page(string $title,string $content,string $tag='سرویس مدارک'): string {
  if(str_contains($content,'name="identifier"')&&!str_contains($content,'bn-login-grid'))$content=Ui::loginBody($content);
  return Ui::head($title,$tag).$content.Ui::end();
 }
}
