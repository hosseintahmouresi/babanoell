<?php
declare(strict_types=1);
require dirname(__DIR__).'/src/Reports/DailyReport.php';
use Babanoell\Finance\Reports\DailyReport;
$checks=0;
$check=static function(bool $condition,string $message)use(&$checks):void{$checks++;if(!$condition)throw new RuntimeException($message);};
try{DailyReport::dates('2026-02-30','2026-03-01');throw new RuntimeException('invalid date accepted');}catch(InvalidArgumentException){$checks++;}
try{DailyReport::dates('2026-08-01','2026-01-01');throw new RuntimeException('reverse date accepted');}catch(InvalidArgumentException){$checks++;}
try{DailyReport::dates('2025-01-01','2026-10-01');throw new RuntimeException('too wide accepted');}catch(InvalidArgumentException){$checks++;}
$range=DailyReport::dates('2026-10-20','2026-10-21');
$check($range===['2026-10-20 00:00:00','2026-10-22 00:00:00'],'inclusive end date');
// A PDO fake tests SQL query assembly and parameterized time boundaries without a MySQL instance.
class FakeReportStmt extends PDOStatement {
    public array $parameters=[];
    public function __construct(){}
    public function execute(?array $params=null):bool{$this->parameters=$params??[];return true;}
    public function fetchAll(int $mode=PDO::FETCH_DEFAULT,...$args):array{return [['report_day'=>'2026-10-20','total'=>'2']];}
}
class FakeReportDb extends PDO {
    public array $queries=[];public array $stmts=[];
    public function __construct(){}
    public function prepare(string $query,array $options=[]):PDOStatement|false{$this->queries[]=$query;$st=new FakeReportStmt();$this->stmts[]=$st;return $st;}
}
$pdo=new FakeReportDb();$result=DailyReport::collect($pdo,'2026-10-20','2026-10-21');
$check($result['from']==='2026-10-20'&&$result['to']==='2026-10-21','range echoed');
$check(count($result['series'])===4,'series count');
foreach($pdo->stmts as $stmt)$check($stmt->parameters===[':start'=>'2026-10-20 00:00:00',':until'=>'2026-10-22 00:00:00'],'parameter binding');
foreach($pdo->queries as $sql)$check(!str_contains($sql,'2026-10-20')&&str_contains($sql,':start')&&str_contains($sql,':until'),'safe bound inputs');
echo 'Phase10C finance report tests: '.$checks.' passed'.PHP_EOL;
