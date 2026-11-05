<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class Raavi_Seller_Search_Widget extends \Elementor\Widget_Base {
    public function get_name() {
        return 'raavi_seller_ajax_search';
    }

    public function get_title() {
        return 'جستجوی AJAX فروشگاه‌ها';
    }

    public function get_icon() {
        return 'eicon-search';
    }

    public function get_categories() {
        return array( 'general' );
    }

    public function get_keywords() {
        return array( 'seller', 'search', 'ajax', 'فروشگاه', 'جستجو', 'پذیرنده' );
    }

    public function get_style_depends() {
        return array( 'raavi-seller-ajax-search' );
    }

    public function get_script_depends() {
        return array( 'raavi-seller-ajax-search' );
    }

    protected function register_controls() {
        $this->start_controls_section(
            'content_section',
            array( 'label' => 'تنظیمات جستجو' )
        );

        $this->add_control(
            'placeholder',
            array(
                'label' => 'متن داخل فیلد',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'نام فروشگاه',
                'placeholder' => 'نام فروشگاه',
            )
        );

        $this->add_control(
            'target_selector',
            array(
                'label' => 'Selector لیست نتایج',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => '.raavi-seller-results',
                'description' => 'این کلاس را در Advanced → CSS Classes روی Loop Grid فروشگاه‌ها قرار دهید.',
            )
        );

        $this->add_control(
            'live_search',
            array(
                'label' => 'جستجو هنگام تایپ',
                'type' => \Elementor\Controls_Manager::SWITCHER,
                'label_on' => 'فعال',
                'label_off' => 'غیرفعال',
                'return_value' => 'yes',
                'default' => 'yes',
            )
        );

        $this->add_control(
            'min_chars',
            array(
                'label' => 'حداقل تعداد کاراکتر',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 1,
                'max' => 10,
                'step' => 1,
                'default' => 1,
            )
        );

        $this->add_control(
            'debounce',
            array(
                'label' => 'تاخیر جستجو هنگام تایپ (ms)',
                'type' => \Elementor\Controls_Manager::NUMBER,
                'min' => 150,
                'max' => 1500,
                'step' => 50,
                'default' => 350,
                'condition' => array( 'live_search' => 'yes' ),
            )
        );

        $this->add_control(
            'no_results_text',
            array(
                'label' => 'پیام بدون نتیجه',
                'type' => \Elementor\Controls_Manager::TEXT,
                'default' => 'فروشگاهی با این نام پیدا نشد.',
            )
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'style_section',
            array(
                'label' => 'استایل',
                'tab' => \Elementor\Controls_Manager::TAB_STYLE,
            )
        );

        $this->add_responsive_control(
            'height',
            array(
                'label' => 'ارتفاع',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range' => array( 'px' => array( 'min' => 42, 'max' => 100 ) ),
                'default' => array( 'unit' => 'px', 'size' => 58 ),
                'selectors' => array( '{{WRAPPER}} .raavi-seller-search__field' => 'height: {{SIZE}}{{UNIT}};' ),
            )
        );

        $this->add_responsive_control(
            'button_width',
            array(
                'label' => 'عرض دکمه',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range' => array( 'px' => array( 'min' => 48, 'max' => 120 ) ),
                'default' => array( 'unit' => 'px', 'size' => 64 ),
                'selectors' => array( '{{WRAPPER}} .raavi-seller-search__button' => 'width: {{SIZE}}{{UNIT}}; flex-basis: {{SIZE}}{{UNIT}};' ),
            )
        );

        $this->add_control(
            'background_color',
            array(
                'label' => 'رنگ زمینه فیلد',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#FFFFFF',
                'selectors' => array( '{{WRAPPER}} .raavi-seller-search__field, {{WRAPPER}} .raavi-seller-search__input' => 'background-color: {{VALUE}};' ),
            )
        );

        $this->add_control(
            'border_color',
            array(
                'label' => 'رنگ کادر',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#D1D1D1',
                'selectors' => array( '{{WRAPPER}} .raavi-seller-search__field' => 'border-color: {{VALUE}};' ),
            )
        );

        $this->add_control(
            'button_color',
            array(
                'label' => 'رنگ دکمه',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#1D3558',
                'selectors' => array( '{{WRAPPER}} .raavi-seller-search__button' => 'background-color: {{VALUE}};' ),
            )
        );

        $this->add_control(
            'text_color',
            array(
                'label' => 'رنگ متن',
                'type' => \Elementor\Controls_Manager::COLOR,
                'default' => '#52698A',
                'selectors' => array(
                    '{{WRAPPER}} .raavi-seller-search__input' => 'color: {{VALUE}};',
                    '{{WRAPPER}} .raavi-seller-search__input::placeholder' => 'color: {{VALUE}};',
                ),
            )
        );

        $this->add_control(
            'radius',
            array(
                'label' => 'گردی گوشه‌ها',
                'type' => \Elementor\Controls_Manager::SLIDER,
                'size_units' => array( 'px' ),
                'range' => array( 'px' => array( 'min' => 0, 'max' => 50 ) ),
                'default' => array( 'unit' => 'px', 'size' => 16 ),
                'selectors' => array(
                    '{{WRAPPER}} .raavi-seller-search__field' => 'border-radius: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .raavi-seller-search__button' => 'border-radius: {{SIZE}}{{UNIT}} 0 0 {{SIZE}}{{UNIT}};',
                ),
            )
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            array(
                'name' => 'typography',
                'selector' => '{{WRAPPER}} .raavi-seller-search__input',
                'fields_options' => array(
                    'font_size' => array( 'default' => array( 'unit' => 'px', 'size' => 15 ) ),
                    'font_weight' => array( 'default' => '400' ),
                ),
            )
        );

        $this->end_controls_section();
    }

    protected function render() {
        $settings = $this->get_settings_for_display();

        $target = isset( $settings['target_selector'] ) ? trim( (string) $settings['target_selector'] ) : '.raavi-seller-results';
        if ( '' === $target ) {
            $target = '.raavi-seller-results';
        }

        $this->add_render_attribute(
            'wrapper',
            array(
                'class' => 'raavi-seller-search',
                'data-target' => $target,
                'data-live' => ( isset( $settings['live_search'] ) && 'yes' === $settings['live_search'] ) ? '1' : '0',
                'data-min-chars' => max( 1, absint( isset( $settings['min_chars'] ) ? $settings['min_chars'] : 1 ) ),
                'data-debounce' => max( 150, absint( isset( $settings['debounce'] ) ? $settings['debounce'] : 350 ) ),
                'data-no-results' => isset( $settings['no_results_text'] ) ? $settings['no_results_text'] : 'فروشگاهی با این نام پیدا نشد.',
            )
        );
        ?>
        <div <?php echo $this->get_render_attribute_string( 'wrapper' ); ?>>
            <div class="raavi-seller-search__field" role="search">
                <input
                    class="raavi-seller-search__input"
                    type="search"
                    inputmode="search"
                    autocomplete="off"
                    spellcheck="false"
                    aria-label="<?php echo esc_attr( isset( $settings['placeholder'] ) ? $settings['placeholder'] : 'نام فروشگاه' ); ?>"
                    placeholder="<?php echo esc_attr( isset( $settings['placeholder'] ) ? $settings['placeholder'] : 'نام فروشگاه' ); ?>"
                />
                <button class="raavi-seller-search__button" type="button" aria-label="جستجو">
                    <span class="raavi-seller-search__spinner" aria-hidden="true"></span>
                    <svg class="raavi-seller-search__icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                        <circle cx="11" cy="11" r="7.2"></circle>
                        <path d="M16.4 16.4L21 21"></path>
                    </svg>
                </button>
            </div>
            <div class="raavi-seller-search__status" aria-live="polite"></div>
        </div>
        <?php
    }
}
