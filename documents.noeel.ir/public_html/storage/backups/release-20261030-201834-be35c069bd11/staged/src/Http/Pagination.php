<?php
declare(strict_types=1);
namespace Babanoell\Documents\Http;
final class Pagination
{
    /** @param array<string,mixed> $result @return array{page:int,per_page:int,total:int,total_pages:int} */
    public static function fromResult(array $result): array
    {
        $page=max(1,(int)($result['page']??1));
        $perPage=max(1,(int)($result['per_page']??25));
        $total=max(0,(int)($result['total']??0));
        return ['page'=>$page,'per_page'=>$perPage,'total'=>$total,'total_pages'=>$total===0?0:(int)ceil($total/$perPage)];
    }
}
