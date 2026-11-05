<?php
/** Legacy MyISAM note fallback integration smoke test. */
define('ABSPATH', '/tmp/');
function add_action($name, $func) {}
function check_ajax_referer($a, $b) {return true;}
function current_user_can($x) {return true;}
function absint($x) {return abs((int)$x);}
function sanitize_textarea_field($v) {return (string)$v;}
function wp_unslash($v) {return $v;}
function wp_json_encode($v,$flags=0) {return json_encode($v,$flags);}
function wp_get_current_user() {return (object)['ID'=>10, 'display_name'=>'کارشناس'];}
function current_time($x) {return '2026-10-21 14:00:00';}
function mlr_table_supports_transactions($name) {return false;}
function wp_send_json_error($v) {throw new RuntimeException('error: '.$v['message']);}
function wp_send_json_success($v) {throw new RuntimeException('success: '.$v['message']);}
function mlr_gregorian_to_jalali($a,$b) {return '1405/07/29';}
function esc_html($x) {return htmlspecialchars((string)$x);}
function nl2brtext($s) {return nl2br($s);}
class FakeDB {
    public $prefix='bnd_'; public $last_error=''; public $comments=null; public $attempts=0; public $interleaved=false;
    public function prepare($query, ...$params) {
        foreach ($params as $v) {
            $replacement = is_int($v) ? (string)$v : "'".str_replace("'","''",(string)$v)."'";
            $query = preg_replace('/%[sd]/', $replacement, $query, 1);
        }
        return $query;
    }
    public function get_var($sql) {
        $this->last_error='';
        if (strpos($sql,'SELECT id')!==false) return 123;
        return $this->comments;
    }
    public function query($sql) {
        $this->last_error='';
        if (strpos($sql,'UPDATE')===false) return false;
        $this->attempts++;
        if ($this->attempts === 1) {
            // Competing user saves a comment between SELECT and UPDATE.
            $this->comments = '[{"text":"other writer"}]';
            $this->interleaved=true;
            return 0;
        }
        $prefix="SET comments = '";
        $a=strpos($sql,$prefix);
        if ($a===false) return false;
        $a+=strlen($prefix);
        $b=strpos($sql,"' WHERE id",$a);
        if ($b===false) return false;
        $new=str_replace("''","'",substr($sql,$a,$b-$a));
        if (strpos($sql,'BINARY comments = BINARY')!==false && strpos($sql,"BINARY '".str_replace("'","''",$this->comments)."'")===false) return 0;
        $this->comments=$new;
        return 1;
    }
}
$wpdb = new FakeDB();
$_POST = ['request_id'=>123, 'comment'=>'پیگیری مالی', 'nonce'=>'test'];
require __DIR__.'/../includes/ajax/add-comment.php';
try { mlr_add_comment_callback(); } catch (RuntimeException $e) {
    if (strpos($e->getMessage(),'success:')!==0) {echo 'FAIL '.$e->getMessage()."\n"; exit(1);}
}
$merged=json_decode($wpdb->comments,true);
if (!$wpdb->interleaved || $wpdb->attempts!==2 || count($merged)!==2 || $merged[0]['text']!=='other writer' || $merged[1]['text']!=='پیگیری مالی') {
    echo "FAIL: CAS lost concurrent writer\n";exit(1);
}
echo "PASS: non-InnoDB note save retries and preserves simultaneous notes\n";
