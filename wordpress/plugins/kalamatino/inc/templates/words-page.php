<?php
do_action( 'add_meta_boxes', 'klmtn_words_meta_box', 0 );
?>
<style>
    #post-body.kalamatino {
        display: block;
        width: 100%;
    }
    .kalamatino #postbox-container-2 {
        display: flex;
        flex-wrap: wrap;
        flex-direction: column;
        row-gap: 40px;
        justify-content: center;
        align-items: center;
    }
    .box-top-klmtn {
        display: flex;
        width: 96%;
        justify-content: center;
        background: #252525;
        border-radius: 30px;
        align-items: center;
        color: white !important;
        column-gap: 20px;
        min-height: 200px;
        flex-wrap: wrap;
        padding: 12px;
        margin: auto;
    }
    .box-top-klmtn #klmtn-save-words {
        display: flex;
        justify-content: center;
        column-gap: 30px;
        align-items: center;
    }
    .box-top-klmtn #klmtn-save-words h1 {
        color: #e4e4e4 !important;
    }
    .box-top-klmtn #klmtn-save-words label {
        display: flex;
        justify-content: center;
        column-gap: 10px;
        align-items: center;
    }
    .box-top-klmtn #klmtn-save-words label > p {
        font-size: 18px;
    }
    .submit-klmtn {
        padding: 8px 20px;
        font-size: 14px;
        background: #0096ff;
        border: none;
        border-radius: 7px;
        color: white;
        cursor: pointer;
    }
    .form-klmtn-add-words > p {
        font-size: 15px;
        margin: 0;
    }
    .lite-box-spinner-saving {
        position: fixed;
        width: 100%;
        display: none;
        justify-content: center;
        height: 100%;
        z-index: 99999999;
        background: #0000009c;
        top: 0;
        right: 0;
        flex-direction: column;
        align-items: center;
    }
    .kalamatino-icon-loader {
        position: fixed;
        width: 200px;
    }
    .table-klmtn {
        display: flex;
        width: 97% !important;
        justify-content: start;
        background: #e3e3e3 !important;
        border-radius: 30px;
        align-items: center;
        column-gap: 20px;
        flex-wrap: wrap;
        padding: 12px;
        margin: auto;
        flex-direction: column;
    }
    .table-head-klmtn {
        background: #0000009c;
        color: white;
        width: 100%;
        display: flex;
        justify-content: space-around;
        padding: 16px 0;
        border-radius: 15px;
        margin-bottom: 5px;
    }
    .box-table-head-klmtn {
        font-size: 15px;
        font-weight: 600;
    }
    .table-body-klmtn {
        display: flex;
        flex-direction: column;
        width: 100%;
        height: max-content;
    }
    .row-table-klmtn {
        display: flex;
        width: 100%;
        justify-content: space-around;
        align-items: center;
        background: #00000017;
        margin: 4px auto;
        border-radius: 11px;
        min-height: 46px;
    }
    .icon.icon-tabler.icon-tabler-arrow-left {
        position: absolute;
        right: 374px;
    }
</style>
<script>
    var $ = jQuery;
    $(document).ready(function(){$(".submit-klmtn").click(function(){$(".lite-box-spinner-saving").css({display:"flex"})})})
</script>
<div class="lite-box-spinner-saving">
    <svg aria-hidden="true" focusable="false" class="loader loading" viewBox="0 0 38 38" width="100px" height="100px">
        <g transform="translate(2 2)" stroke="currentColor" stroke-width="4" fill-rule="evenodd">
            <circle class="st0" stroke-opacity=".5" cx="17" cy="17" r="16.5"></circle>
            <path class="st1" d="M33.5 17C33.5 7.9 26.1.5 17 .5" transform="rotate(249.767 17 17)">
                <animateTransform accumulate="none" additive="replace" attributeName="transform" calcMode="linear" dur="1s" from="0 17 17" repeatCount="indefinite" restart="always" to="360 17 17" type="rotate"></animateTransform>
            </path>
        </g>
    </svg>
    <svg id="Layer_1" class="kalamatino-icon-loader" data-name="Layer 1" width="30" height="30" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1365.06 1918.19"><rect y="4.92" width="235.98" height="1913.27" rx="117.99" style="fill:#fff"/><path d="M1374.38,1864.83,842.77,1080.51a118,118,0,0,0-90.7-42.52H459.91a118,118,0,0,1-118-118h0a118,118,0,0,1,118-118h402.8a118,118,0,0,1,90.7,42.52l602.37,869.37a118,118,0,0,1-15.23,166.16h0A118,118,0,0,1,1374.38,1864.83Z" transform="translate(-223.93 -0.81)" style="fill:#fff"/><rect x="865.93" y="231.1" width="831.93" height="235.98" rx="117.99" transform="translate(-25.31 1117.45) rotate(-50.61)" style="fill:#fff"/></svg>
</div>
<div class="wrap">
    <h2><?php echo __('Words List','kalamatino'); ?></h2>
    <div class="fx-settings-meta-box-wrap">
        <div id="poststuff">
            <div id="post-body" class="metabox-holder columns-1 kalamatino">
                <div id="postbox-container-2" class="postbox-container">
                    <div class="box-top-klmtn">
                        <svg id="Layer_1" data-name="Layer 1" width="100" height="100" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1365.06 1918.19"><rect y="4.92" width="235.98" height="1913.27" rx="117.99" style="fill:#fff"/><path d="M1374.38,1864.83,842.77,1080.51a118,118,0,0,0-90.7-42.52H459.91a118,118,0,0,1-118-118h0a118,118,0,0,1,118-118h402.8a118,118,0,0,1,90.7,42.52l602.37,869.37a118,118,0,0,1-15.23,166.16h0A118,118,0,0,1,1374.38,1864.83Z" transform="translate(-223.93 -0.81)" style="fill:#fff"/><rect x="865.93" y="231.1" width="831.93" height="235.98" rx="117.99" transform="translate(-25.31 1117.45) rotate(-50.61)" style="fill:#fff"/></svg>
                        <div class="form-klmtn-add-words">
                            <form action="" method="POST" id="klmtn-save-words">
                                <label for="from-text">
                                    <p><?php echo __('from','kalamatino'); ?></p>
                                    <input type="text" name="from-text">
                                </label>
                                <label for="to-text">
                                    <p><?php echo __('to','kalamatino'); ?></p>
                                    <input type="text" name="to-text">
                                </label>
                                <button type="submit" class="submit-klmtn" name="savewords"><?php echo __('add to list','kalamatino'); ?></button>
                            </form>
                            <p><?php echo __('simply add the text (from) you want to change (to) . kalamatino works 99%','kalamatino'); ?></p>
                        </div>
                    </div>
                    <div class="table-klmtn">
                        <div class="table-head-klmtn">
                            <div class="box-table-head-klmtn"><?php echo __('From','kalamatino'); ?></div>
                            |
                            <div class="box-table-head-klmtn"><?php echo __('Replaced with','kalamatino'); ?></div>
                            |
                            <div class="box-table-head-klmtn"><?php echo __('Actions','kalamatino'); ?></div>
                        </div>
                        <div class="table-body-klmtn">
                            <?php
                            $from_text_table = get_option('klmtn_from_text','');
                            $to_text_table = get_option('klmtn_to_text','');
                            if (is_array($from_text_table)) {
                                $length = count($from_text_table);
                            }
                            for ($i = 0; $i < $length; $i++) { ?>
                                <div class="row-table-klmtn deleted-row-<?php echo esc_attr($i); ?>">
                                    <div class="table-from-klmtn"><?php print esc_attr($from_text_table[$i]); ?></div>
                                    <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-arrow-left" width="25" height="25" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                        <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                        <line x1="5" y1="12" x2="19" y2="12" />
                                        <line x1="5" y1="12" x2="11" y2="18" />
                                        <line x1="5" y1="12" x2="11" y2="6" />
                                    </svg>
                                    <div class="table-to-klmtn"><?php print esc_attr($to_text_table[$i]); ?></div>
                                    <div class="table-actions-klmtn">
                                        <form method="POST" action="" id="">
                                            <button name="delete-word<?php echo esc_attr($i); ?>" type="submit" class="button button-primary submit-klmtn">
                                                <?php echo __('delete this row','kalamatino'); ?>
                                            </button>
                                        </form>
                                        <?php
                                        if (isset($_POST['delete-word'.$i])) {
                                            $arr = $from_text_table;
                                            $val = $from_text_table[$i];
                                            $key = array_search($val, $arr, true);
                                            if ($key !== false) {
                                                array_splice($arr, $key, 1);
                                            }
                                            $newfrom_text_option = $arr;
                                            $empty_array_words = count($newfrom_text_option);
                                            if ($empty_array_words == 0) {
                                                delete_option('klmtn_from_text');
                                            } else {
                                                update_option('klmtn_from_text', $newfrom_text_option);
                                            }
                                            $arr = $to_text_table;
                                            $val = $to_text_table[$i];
                                            $key = array_search($val, $arr, true);
                                            if ($key !== false) {
                                                array_splice($arr, $key, 1);
                                            }
                                            $newto_text_option = $arr;
                                            $empty_array_words = count($newto_text_option);
                                            if ($empty_array_words == 0) {
                                                delete_option('klmtn_to_text');
                                            } else {
                                                update_option('klmtn_to_text', $newto_text_option);
                                            }
                                            echo "<meta http-equiv='refresh' content='0'>";
                                            echo "<style>.deleted-row-{$i} { display: none !important;}.lite-box-spinner-saving {display: flex !important;}";
                                        }
                                        ?>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
$from_text_for_array = sanitize_text_field($_POST['from-text']);
$to_text_for_array = sanitize_text_field($_POST['to-text']);
if (isset($_POST['savewords']) && !empty($_POST['from-text']) && !empty($_POST['to-text'])) {
    $from_text_option = get_option('klmtn_from_text','');
    if (empty($from_text_option)) {
        add_option('klmtn_from_text', array(sanitize_text_field($_POST['from-text'])));
    } else {
        $from_text_option = array_merge($from_text_option, array(sanitize_text_field($_POST['from-text'])));
        update_option('klmtn_from_text', $from_text_option);
    }
    $to_text_option = get_option('klmtn_to_text','');
    if (empty($to_text_option)) {
        add_option('klmtn_to_text', array(sanitize_text_field($_POST['to-text'])));
    } else {
        $to_text_option = array_merge($to_text_option, array(sanitize_text_field($_POST['to-text'])));
        update_option('klmtn_to_text', $to_text_option);
    }
}
if (isset($_POST['savewords']) && !empty($_POST['from-text']) && !empty($_POST['to-text'])) {
    ?>
    <style>
        .lite-box-spinner-saving {
            display: flex !important;
        }
    </style>
    <?php
    echo "<meta http-equiv='refresh' content='0'>";
}
?>
