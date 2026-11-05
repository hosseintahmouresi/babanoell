<?php
// Lightweight integration test for the real admin-post duplication handler.
// It executes the production function with a fake wpdb and validates the write + redirect on shutdown.

$mode = $argv[1] ?? 'success';
$GLOBALS['test_mode'] = $mode;
$GLOBALS['test_options'] = [];
$GLOBALS['test_redirect'] = '';
$GLOBALS['test_failures'] = [];
$GLOBALS['test_passes'] = [];

define('ARRAY_A', 'ARRAY_A');
function add_action(...$args) {}
function current_user_can($cap) { return $cap === 'manage_options'; }
function wp_die($msg) { throw new RuntimeException($msg); }
function get_current_user_id() { return 77; }
function sanitize_key($v) { return preg_replace('/[^a-z0-9_\-]/', '', strtolower((string)$v)); }
function absint($v) { return abs((int)$v); }
function get_option($k, $default=false) { return array_key_exists($k,$GLOBALS['test_options']) ? $GLOBALS['test_options'][$k] : $default; }
function add_option($k,$v,$deprecated='',$autoload='yes') { if (array_key_exists($k,$GLOBALS['test_options'])) return false; $GLOBALS['test_options'][$k]=$v; return true; }
function delete_option($k) { unset($GLOBALS['test_options'][$k]); return true; }
function admin_url($path='') { return 'https://example.test/wp-admin/' . ltrim($path,'/'); }
function add_query_arg($args,$url) { $sep = strpos($url,'?')===false ? '?' : '&'; return $url . $sep . http_build_query($args); }
function wp_safe_redirect($url) { $GLOBALS['test_redirect'] = $url; return true; }
function check_admin_referer($action) { return 1; }
function sanitize_text_field($v) { return trim(strip_tags((string)$v)); }
function wp_date($format) { return '20260909-120000'; }
function current_time($type) { return $type === 'mysql' ? '2026-09-09 12:00:00' : time(); }
function wp_unslash($v) { return $v; }
function esc_url($v) { return $v; }
function wp_verify_nonce($a,$b) { return true; }
function mlr_log(...$args) {}

class FakeWpdb {
    public $prefix = 'wp_';
    public $insert_id = 0;
    public $last_error = '';
    public $source;
    public $inserted = null;
    public $deleted = [];
    public function __construct() {
        $this->source = [
            'id' => '44',
            'name' => 'طرح ویژه',
            'type' => 'leasing',
            'is_active' => '1',
            'display_order' => '30',
            'settings' => 'a:2:{s:4:"rate";d:12.5;s:30:"required_documents_by_occupation";a:1:{s:8:"employee";a:1:{i:0;s:7:"payslip";}}}',
            'display_fields' => 'a:2:{i:0;s:4:"rate";i:1;s:6:"amount";}',
            'display_fields_context' => 'a:1:{s:19:"mlr_loan_calculator";a:1:{i:0;s:4:"rate";}}',
            'required_documents' => 'a:2:{i:0;s:2:"id";i:1;s:7:"payslip";}',
            'created_at' => '2026-01-01 10:00:00',
        ];
    }
    public function prepare($sql,...$args) {
        foreach ($args as $arg) {
            $rep = is_numeric($arg) ? (string)(int)$arg : "'" . str_replace("'","''",(string)$arg) . "'";
            $sql = preg_replace('/%[dsf]/', $rep, $sql, 1);
        }
        return $sql;
    }
    public function get_row($sql,$output=null) {
        if (preg_match('/WHERE `id` = (\d+)/',$sql,$m)) {
            $id=(int)$m[1];
            if ($id===44) return $this->source;
            if ($this->inserted && $id===$this->insert_id) {
                $row=['id'=>(string)$this->insert_id]+array_map('strval',$this->inserted);
                if ($GLOBALS['test_mode']==='verify_fail') $row['settings']='TAMPERED';
                return $row;
            }
        }
        return null;
    }
    public function get_var($sql) {
        if (stripos($sql,'COUNT(*)')!==false && stripos($sql,'`name`')!==false) return 0;
        if (stripos($sql,'MAX(`display_order`)')!==false) return 30;
        return 0;
    }
    public function insert($table,$data) {
        if ($GLOBALS['test_mode']==='db_fail') { $this->last_error='synthetic insert failure'; return false; }
        $this->inserted=$data;
        $this->insert_id=101;
        return 1;
    }
    public function delete($table,$where,$formats=null) {
        $this->deleted[]=$where;
        if (($where['id']??0)===$this->insert_id) $this->inserted=null;
        return 1;
    }
}
$GLOBALS['wpdb'] = new FakeWpdb();
$_GET = ['plan_id' => '44', '_wpnonce' => 'valid'];
if ($mode === 'busy') {
    $GLOBALS['test_options']['mlr_plan_duplicate_lock_44'] = time();
}

function t($name,$ok) {
    if ($ok) $GLOBALS['test_passes'][]=$name; else $GLOBALS['test_failures'][]=$name;
}

register_shutdown_function(function() use ($mode) {
    $db=$GLOBALS['wpdb'];
    $redirect=$GLOBALS['test_redirect'];
    if ($mode==='success') {
        t('real handler inserts one row', is_array($db->inserted));
        t('real handler drops source identity via new insert id', $db->insert_id===101);
        t('real handler keeps copy inactive', (int)($db->inserted['is_active']??1)===0);
        t('real handler preserves settings byte-for-byte', ($db->inserted['settings']??null)===$db->source['settings']);
        t('real handler preserves occupation/document payload', ($db->inserted['required_documents']??null)===$db->source['required_documents']);
        t('real handler appends display order', (int)($db->inserted['display_order']??0)===40);
        t('real handler assigns copy name', ($db->inserted['name']??'')==='طرح ویژه — کپی');
        t('real handler redirects to success without action replay', str_contains($redirect,'mlr_duplicate_success=1') && str_contains($redirect,'duplicated_plan_id=101') && !str_contains($redirect,'action=mlr_duplicate_installment_plan'));
        t('real handler releases duplicate lock', !array_key_exists('mlr_plan_duplicate_lock_44',$GLOBALS['test_options']));
    } elseif ($mode==='busy') {
        t('busy duplicate does not insert', $db->inserted===null);
        t('busy duplicate redirects safely', str_contains($redirect,'mlr_plan_action_error=duplicate_busy'));
    } elseif ($mode==='verify_fail') {
        t('verification mismatch removes the bad copy', $db->inserted===null && count($db->deleted)===1);
        t('verification mismatch does not report success', str_contains($redirect,'mlr_plan_action_error=duplicate_verify') && !str_contains($redirect,'mlr_duplicate_success=1'));
        t('verification mismatch releases lock', !array_key_exists('mlr_plan_duplicate_lock_44',$GLOBALS['test_options']));
    } elseif ($mode==='db_fail') {
        t('DB failure creates no copy', $db->inserted===null);
        t('DB failure returns controlled error', str_contains($redirect,'mlr_plan_action_error=duplicate_db'));
        t('DB failure releases lock', !array_key_exists('mlr_plan_duplicate_lock_44',$GLOBALS['test_options']));
    }
    foreach ($GLOBALS['test_passes'] as $n) echo "PASS: $n\n";
    foreach ($GLOBALS['test_failures'] as $n) echo "FAIL: $n\n";
    echo 'RESULT: '.count($GLOBALS['test_passes']).' passed, '.count($GLOBALS['test_failures'])." failed\n";
    if ($GLOBALS['test_failures']) {
        // Cannot change exit status from shutdown after exit, so emit sentinel consumed by run-tests.sh.
        echo "INTEGRATION_TEST_FAILED\n";
    }
});

require_once __DIR__ . '/../includes/admin-pages/installment-plans.php';
mlr_handle_duplicate_installment_plan();
