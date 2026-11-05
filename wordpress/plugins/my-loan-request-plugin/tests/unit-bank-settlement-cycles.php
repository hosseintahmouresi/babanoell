<?php
/** Isolated handler regression: scope, all-or-nothing writes, independent bookkeeping. */
define('ABSPATH', '/tmp/');
function add_action($hook, $callback, $priority = 10) {}
function absint($n) { return abs((int)$n); }
function get_option($key) { return '1.0'; }
function check_ajax_referer($name, $field) { return true; }
function mlr_get_current_bank_expert_or_error() { return (object)['ID' => 44]; }
function wp_unslash($value) { return $value; }
function sanitize_text_field($value) { return trim((string)$value); }
function sanitize_textarea_field($value) { return trim((string)$value); }
function mlr_normalize_digits($v) { return strtr($v, ['۰'=>'0','۱'=>'1','۲'=>'2','۳'=>'3','۴'=>'4','۵'=>'5','۶'=>'6','۷'=>'7','۸'=>'8','۹'=>'9']); }
function mlr_jalali_to_gregorian($v) { return $v === '1405/07/22' ? '2026-10-14' : false; }
function mlr_gregorian_to_jalali($v, $format='Y/m/d') { return strpos($v, '2026-10-14') === 0 ? '1405/07/22' : false; }
function mlr_table_supports_transactions($name) { return true; }
function mlr_normalize_status($v) { return $v; }
function current_time($fmt) { return '2026-10-14 12:00:00'; }
class BankTestResult extends Exception { public $ok; public $data; function __construct($ok, $data) { parent::__construct(); $this->ok=$ok; $this->data=$data; } }
function wp_send_json_error($data, $code=null) { throw new BankTestResult(false,$data); }
function wp_send_json_success($data) { throw new BankTestResult(true,$data); }
class BankTestWpdb {
    public $prefix = 'test_';
    public $insert_id = 0;
    public $last_error = '';
    public $requests = [];
    public $cycles = [];
    public $items = [];
    public $snap = null;
    public $fail_on_update = 0;
    public $update_calls = 0;
    public $queries = [];
    function __construct() { $this->reset(); }
    function reset() {
        $this->requests = [
            11 => (object)['id'=>11,'assigned_bank_user_id'=>44,'status'=>'اعطا شده','bank_settlement_status'=>'','full_name'=>'آزمایش الف','national_code'=>'001','loan_amount'=>'2000000'],
            12 => (object)['id'=>12,'assigned_bank_user_id'=>44,'status'=>'اعطا شده','bank_settlement_status'=>'','full_name'=>'آزمایش ب','national_code'=>'002','loan_amount'=>'3000000'],
            13 => (object)['id'=>13,'assigned_bank_user_id'=>45,'status'=>'اعطا شده','bank_settlement_status'=>'','full_name'=>'مغایر','national_code'=>'003','loan_amount'=>'3000000'],
        ];
        $this->cycles=[]; $this->items=[]; $this->snap=null; $this->fail_on_update=0; $this->update_calls=0;
    }
    function esc_like($v) { return addcslashes($v, '_%\\'); }
    function prepare($q, $args) {
        $args = is_array($args) ? $args : array_slice(func_get_args(),1);
        foreach ($args as $arg) {
            $p = strpos($q, '%');
            if ($p === false) break;
            $next = substr($q,$p+1,1);
            $q = substr_replace($q, $next==='d' ? (string)(int)$arg : "'".str_replace("'","''",(string)$arg)."'", $p, 2);
        }
        return $q;
    }
    function get_var($q) {
        if (strpos($q,'SHOW TABLES LIKE')!==false) {
            if (strpos(str_replace('\\', '', $q),'mlr_bank_settlement_cycle_items')!==false) return 'test_mlr_bank_settlement_cycle_items';
            return 'test_mlr_bank_settlement_cycles';
        }
        if (strpos($q,'COUNT(*)')!==false) {
            preg_match_all('/\b\d+\b/', $q, $m);
            foreach ($this->items as $item) if (in_array($item['request_id'], array_map('intval',$m[0]),true)) return 1;
            return 0;
        }
        throw new Exception('Unexpected SQL (get_var): '.$q);
    }
    function query($q) {
        $this->queries[]=$q;
        if ($q==='START TRANSACTION') { $this->snap=unserialize(serialize([$this->requests,$this->cycles,$this->items])); return 1; }
        if ($q==='COMMIT') { $this->snap=null; return 1; }
        if ($q==='ROLLBACK') { [$this->requests,$this->cycles,$this->items]=$this->snap; $this->snap=null; return 1; }
        throw new Exception('Unexpected SQL (query): '.$q);
    }
    function get_results($q) {
        preg_match('/WHERE id IN \(([^)]+)\)/',$q,$m);
        $ids=isset($m[1])?array_map('intval',explode(',', $m[1])):[];
        return array_values(array_intersect_key($this->requests,array_flip($ids)));
    }
    function insert($table,$data) {
        if (strpos($table,'_items')!==false) {
            foreach ($this->items as $existing) if ($existing['request_id']===$data['request_id']) return false;
            $this->items[]=$data; return 1;
        }
        $this->insert_id=count($this->cycles)+1;
        $this->cycles[]=$data; return 1;
    }
    function update($table,$data,$where) {
        $this->update_calls++;
        if ($this->fail_on_update===$this->update_calls) return false;
        $id=$where['id'];
        if (!isset($this->requests[$id]) || $this->requests[$id]->assigned_bank_user_id!==$where['assigned_bank_user_id']) return 0;
        foreach ($data as $k=>$v) $this->requests[$id]->$k=$v;
        return 1;
    }
}
require dirname(__DIR__) . '/includes/bank-settlement-cycles.php';
$wpdb=new BankTestWpdb();
function check_case($cond, $label) { if (!$cond) { echo "FAIL: {$label}\n"; exit(1); } echo "PASS: {$label}\n"; }
function call_handler($ids) {
    $_POST=['request_ids'=>$ids,'settlement_date'=>'۱۴۰۵/۰۷/۲۲','notes'=>'تست سیکل'];
    try { mlr_create_bank_settlement_cycle(); } catch(BankTestResult $ex) {return $ex;}
    throw new Exception('Handler did not return');
}
$r=call_handler([11,12]);
check_case($r->ok && $r->data['cycle_id']===1 && count($wpdb->items)===2, 'two bank-assigned cases are batched atomically');
check_case($wpdb->requests[11]->bank_settlement_status==='انجام شده' && $wpdb->requests[12]->bank_settled_at==='2026-10-14 00:00:00', 'bank-only tracking is updated');
check_case(!isset($wpdb->requests[11]->settlement_status) && !isset($wpdb->requests[11]->settlement_cycle_id), 'merchant settlement fields remain untouched');
$r=call_handler([11]);
check_case(!$r->ok && count($wpdb->cycles)===1, 'previously settled case is not added again');
$wpdb->reset();
$r=call_handler([11,13]);
check_case(!$r->ok && count($wpdb->cycles)===0 && count($wpdb->items)===0, 'foreign bank case is refused');
$wpdb->reset(); $wpdb->fail_on_update=2;
$r=call_handler([11,12]);
check_case(!$r->ok && !$wpdb->cycles && !$wpdb->items && $wpdb->requests[11]->bank_settlement_status==='', 'mid-batch database failure rolls back all writes');
echo "ALL BANK CYCLE UNIT TESTS PASSED\n";
