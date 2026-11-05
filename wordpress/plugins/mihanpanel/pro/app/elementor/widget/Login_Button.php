<?php
namespace mihanpanel\pro\app\elementor\widget;

// Block direct access to the main plugin file.
defined('ABSPATH') or die('No script kiddies please!');

use Elementor\Widget_Base;
use Elementor\Controls_Manager;
use mihanpanel\app\panel;
use mihanpanel\pro\app\assets;

class Login_Button extends Widget_Base
{
    public function __enqueue_assets()
    {
        assets::enqueue_style('mpanel_elementor_login_element', assets::get_css_url('elementor/mihan_panel_login'));
    }

    public function get_name()
    {
        return 'mpanel_login_button';
    }

    public function get_title()
    {
        return esc_html__('Mihan Panel Login', 'mihanpanel');
    }

    public function get_icon() {
        return 'eicon-person';
    }

    public function get_categories() {
        return ['mpanel_elements'];
    }

    public function get_keywords()
    {
        return ['mihan_panel', 'mihanpanel', esc_html__('Mihan Panel Login' , 'mihanpanel')];
    }

    public function register_controls()
    {
        $this->start_controls_section(
            'before_section',
            [
                'label' => esc_html__( 'Before Login', 'mihanpanel' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'before_btn_preview',
            [
                'label' => esc_html__( 'Preview', 'mihanpanel' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Yes', 'mihanpanel' ),
                'label_off' => esc_html__( 'No', 'mihanpanel' ),
                'return_value' => 'yes',
                'default' => 'no',
            ]
        );

        $this->add_control(
            'login_btn_icon',
            [
                'label' => esc_html__( 'Icon', 'mihanpanel' ),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-user',
                    'library' => 'fa-solid',
                ],
            ]
        );

        $this->add_control(
            'show_login_btn_text',
            [
                'label' => esc_html__( 'Show Text', 'mihanpanel' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'mihanpanel' ),
                'label_off' => esc_html__( 'Hide', 'mihanpanel' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'login_btn_text',
            [
                'label' => esc_html__( 'Button Text', 'mihanpanel' ),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__( 'Login / Register', 'mihanpanel' ),
                'condition' => ['show_login_btn_text' => 'yes']
            ]
        );

        $this->add_control(
            'show_login_btn_title_in_mobile',
            [
                'label' => esc_html__( 'Title in Mobile', 'mihanpanel' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'mihanpanel' ),
                'label_off' => esc_html__( 'Hide', 'mihanpanel' ),
                'return_value' => 'yes',
                'default' => 'yes',
                'condition' => ['show_login_btn_text' => 'yes']
            ]
        );

        $this->end_controls_section();

        $this->start_controls_section(
            'after_section',
            [
                'label' => esc_html__( 'After Login', 'mihanpanel' ),
                'tab' => Controls_Manager::TAB_CONTENT,
            ]
        );

        $this->add_control(
            'preview_notice',
            [
                'type' => Controls_Manager::RAW_HTML,
                'raw' => __('Login button preview is enabled.', 'mihanpanel'),
                'content_classes' => 'elementor-panel-alert elementor-panel-alert-info',
                'condition' => ['before_btn_preview' => 'yes']
            ]
        );

        $this->add_control(
            'show_user_avatar',
            [
                'label' => esc_html__( 'User Avatar', 'mihanpanel' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'mihanpanel' ),
                'label_off' => esc_html__( 'Hide', 'mihanpanel' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_display_name',
            [
                'label' => esc_html__( 'User Display Name', 'mihanpanel' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'mihanpanel' ),
                'label_off' => esc_html__( 'Hide', 'mihanpanel' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $this->add_control(
            'show_dropdown',
            [
                'label' => esc_html__( 'Menu', 'mihanpanel' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Show', 'mihanpanel' ),
                'label_off' => esc_html__( 'Hide', 'mihanpanel' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $repeater = new \Elementor\Repeater();

        $repeater->add_control(
            'use_custom_tab',
            [
                'label' => esc_html__( 'Custom Tab', 'mihanpanel' ),
                'type' => Controls_Manager::SWITCHER,
                'label_on' => esc_html__( 'Yes', 'mihanpanel' ),
                'label_off' => esc_html__( 'No', 'mihanpanel' ),
                'return_value' => 'yes',
                'default' => 'yes',
            ]
        );

        $panelTabOptions = [];

        $panel_tabs = panel::get_tabs();

        if ($panel_tabs){
            foreach ($panel_tabs as $panel_tab){
                $panelTabOptions[$panel_tab->id] = $panel_tab->name;
            }
        }

        $repeater->add_control(
            'panel_tab',
            [
                'label' => esc_html__( 'Tab', 'mihanpanel' ),
                'label_block' => true,
                'type' => Controls_Manager::SELECT,
                'default' => $panelTabOptions ? key($panelTabOptions) : '',
                'options' => $panelTabOptions,
                'condition' => ['use_custom_tab!' => 'yes']
            ]
        );

        $repeater->add_control(
            'tab_icon',
            [
                'label' => esc_html__( 'Icon', 'mihanpanel' ),
                'type' => Controls_Manager::ICONS,
                'default' => [
                    'value' => 'fas fa-caret-left',
                    'library' => 'fa-solid',
                ],
                'condition' => ['use_custom_tab' => 'yes']
            ]
        );

        $repeater->add_control(
            'tab_title',
            [
                'label' => esc_html__( 'Title', 'mihanpanel' ),
                'type' => Controls_Manager::TEXT,
                'default' => esc_html__( 'Profile' , 'mihanpanel' ),
                'label_block' => true,
                'condition' => ['use_custom_tab' => 'yes']
            ]
        );

        $repeater->add_control(
            'tab_link',
            [
                'label' => esc_html__( 'Link', 'mihanpanel' ),
                'type' => Controls_Manager::URL,
                'options' => ['url', 'is_external'],
                'default' => [
                    'url' => '#',
                    'is_external' => false,
                ],
                'label_block' => true,
                'dynamic' => ['active' => true],
                'condition' => ['use_custom_tab' => 'yes']
            ]
        );

        $this->add_control(
            'tabs',
            [
                'label' => esc_html__( 'Tabs', 'mihanpanel' ),
                'type' => Controls_Manager::REPEATER,
                'fields' => $repeater->get_controls(),
                'default' => [
                    [
                        'tab_title' => esc_html__( 'Profile', 'mihanpanel' ),
                    ],
                    [
                        'tab_title' => esc_html__( 'Orders', 'mihanpanel' ),
                    ],
                ],
                'condition' => ['show_dropdown' => 'yes']
            ]
        );

        $this->end_controls_section();

        /**
         *
         *
         * Styles
         *
         *
         */
        $AlignmentOptions = [
            'space-between' => [
                'title' => esc_html__( 'Space Between', 'mihanpanel' ),
                'icon' => 'eicon-h-align-stretch',
            ],
            'end' => [
                'title' => esc_html__( 'Left', 'mihanpanel' ),
                'icon' => 'eicon-text-align-left',
            ],
            'center' => [
                'title' => esc_html__( 'Center', 'mihanpanel' ),
                'icon' => 'eicon-text-align-center',
            ],
            'start' => [
                'title' => esc_html__( 'Right', 'mihanpanel' ),
                'icon' => 'eicon-text-align-right',
            ],
        ];

        $this->start_controls_section(
            'login_styles',
            [
                'label' => esc_html__( 'Login Button', 'mihanpanel' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('login_style_tabs');
        $this->start_controls_tab('login_style_normal_tab', ['label' => esc_html__('Normal', 'mihanpanel')]);

        $this->add_responsive_control(
            'login_text_alignment',
            [
                'label' => esc_html__( 'Text alignment', 'mihanpanel' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => is_rtl() ? array_reverse($AlignmentOptions) : $AlignmentOptions,
                'default' => 'center',
                'toggle' => false,
                'selectors' => [
                    '{{WRAPPER}} .mpanel-before-login a' => 'justify-content: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'login_icon_size',
            [
                'label' => esc_html__( 'Icon Size', 'mihanpanel' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 400,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mpanel-before-login svg' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mpanel-before-login i' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'login_icon_color',
            [
                'label' => esc_html__( 'Icon Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#fff',
                'selectors' => [
                    '{{WRAPPER}} .mpanel-before-login svg' => 'fill: {{VALUE}}',
                    '{{WRAPPER}} .mpanel-before-login i' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'login_text_color',
            [
                'label' => esc_html__( 'Text Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#fff',
                'selectors' => [
                    '{{WRAPPER}} .mpanel-before-login a' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'login_text_typography',
                'selector' => '{{WRAPPER}} .mpanel-before-login a span',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'login_background',
                'types' => ['classic', 'gradient'],
                'exclude' => ['image'],
                'selector' => '{{WRAPPER}} .mpanel-before-login a',
                'fields_options' => [
                        'background' => ['default' => 'gradient'],
                        'color' => ['default' => '#8fa0ff'],
                        'color_b' => ['default' => '#354ac4'],
                        'gradient_type' => ['default' => 'linear'],
                        'color_b_stop' => ['default' => [
                            'unit' => '%',
                            'size' => 60,
                        ]],
                        'gradient_angle' => ['default' => [
                            'unit' => 'deg',
                            'size' => 190,
                        ]],
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'login_border',
                'selector' => '{{WRAPPER}} .mpanel-before-login a',
            ]
        );

        $this->add_control(
            'login_border_radius',
            [
                'label' => esc_html__( 'Border Radius', 'mihanpanel' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'default' => [
                    'top' => 7,
                    'right' => 7,
                    'bottom' => 7,
                    'left' => 7,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mpanel-before-login a' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'login_padding',
            [
                'label' => esc_html__( 'Padding', 'mihanpanel' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'default' => [
                    'top' => 10,
                    'right' => 10,
                    'bottom' => 10,
                    'left' => 10,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mpanel-before-login a' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'login_box_shadow',
                'selector' => '{{WRAPPER}} .mpanel-before-login a',
            ]
        );

        $this->end_controls_tab();
        $this->start_controls_tab('login_style_hover_tab', ['label' => esc_html__('Hover', 'mihanpanel')]);

        $this->add_control(
            'login_icon_color_hover',
            [
                'label' => esc_html__( 'Icon Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mpanel-before-login svg' => 'fill: {{VALUE}}',
                    '{{WRAPPER}} .mpanel-before-login i' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'login_text_color_hover',
            [
                'label' => esc_html__( 'Text Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mpanel-before-login a:hover' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'login_background_hover',
                'types' => ['classic', 'gradient'],
                'exclude' => ['image'],
                'selector' => '{{WRAPPER}} .mpanel-before-login a:hover',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'login_border_hover',
                'selector' => '{{WRAPPER}} .mpanel-before-login a:hover',
            ]
        );

        $this->add_control(
            'login_border_radius_hover',
            [
                'label' => esc_html__( 'Border Radius', 'mihanpanel' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'selectors' => [
                    '{{WRAPPER}} .mpanel-before-login a:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'login_box_shadow_hover',
                'selector' => '{{WRAPPER}} .mpanel-before-login a:hover',
            ]
        );

        $this->add_control(
            'login_hover_animation',
            [
                'label' => esc_html__( 'Hover Animation', 'mihanpanel' ),
                'type' => Controls_Manager::HOVER_ANIMATION,
                'default' => 'float'
            ]
        );

        $this->end_controls_tab();

        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section(
            'profile_styles',
            [
                'label' => esc_html__( 'Profile Button', 'mihanpanel' ),
                'tab' => Controls_Manager::TAB_STYLE,
            ]
        );

        $this->start_controls_tabs('profile_style_tabs');
        $this->start_controls_tab('profile_style_normal_tab', ['label' => esc_html__('Normal', 'mihanpanel')]);

        $this->add_responsive_control(
            'profile_text_alignment',
            [
                'label' => esc_html__( 'Text alignment', 'mihanpanel' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => is_rtl() ? array_reverse($AlignmentOptions) : $AlignmentOptions,
                'default' => 'space-between',
                'toggle' => false,
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button' => 'justify-content: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'profile_avatar_size',
            [
                'label' => esc_html__( 'Avatar Size', 'mihanpanel' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 300,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 25,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button img' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}};',
                ],
                'condition' => ['show_user_avatar' => 'yes']
            ]
        );

        $this->add_control(
            'profile_text_color',
            [
                'label' => esc_html__( 'Text Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#fff',
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'profile_text_typography',
                'selector' => '{{WRAPPER}} .mpanel-profile-button .pb-details',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'profile_background',
                'types' => ['classic', 'gradient'],
                'exclude' => ['image'],
                'selector' => '{{WRAPPER}} .mpanel-profile-button',
                'fields_options' => [
                    'background' => ['default' => 'gradient'],
                    'color' => ['default' => '#feb692'],
                    'color_b' => ['default' => '#ea5455'],
                    'gradient_type' => ['default' => 'linear'],
                    'color_b_stop' => ['default' => [
                        'unit' => '%',
                        'size' => 60,
                    ]],
                    'gradient_angle' => ['default' => [
                        'unit' => 'deg',
                        'size' => 190,
                    ]],
                ]
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'profile_border',
                'selector' => '{{WRAPPER}} .mpanel-profile-button',
            ]
        );

        $this->add_control(
            'profile_border_radius',
            [
                'label' => esc_html__( 'Border Radius', 'mihanpanel' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'default' => [
                    'top' => 7,
                    'right' => 7,
                    'bottom' => 7,
                    'left' => 7,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'profile_padding',
            [
                'label' => esc_html__( 'Padding', 'mihanpanel' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'default' => [
                    'top' => 7,
                    'right' => 10,
                    'bottom' => 7,
                    'left' => 10,
                    'unit' => 'px',
                    'isLinked' => true,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button' => 'padding: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'profile_box_shadow',
                'selector' => '{{WRAPPER}} .mpanel-profile-button',
            ]
        );

        $this->end_controls_tab();
        $this->start_controls_tab('profile_style_hover_tab', ['label' => esc_html__('Hover', 'mihanpanel')]);

        $this->add_control(
            'profile_text_color_hover',
            [
                'label' => esc_html__( 'Text Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button:hover' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'profile_background_hover',
                'types' => ['classic', 'gradient'],
                'exclude' => ['image'],
                'selector' => '{{WRAPPER}} .mpanel-profile-button:hover',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'profile_border_hover',
                'selector' => '{{WRAPPER}} .mpanel-profile-button:hover',
            ]
        );

        $this->add_control(
            'profile_border_radius_hover',
            [
                'label' => esc_html__( 'Border Radius', 'mihanpanel' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button:hover' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'profile_box_shadow_hover',
                'selector' => '{{WRAPPER}} .mpanel-profile-button:hover',
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();

        $this->start_controls_section(
            'profile_menu_styles',
            [
                'label' => esc_html__( 'Profile Menu', 'mihanpanel' ),
                'tab' => Controls_Manager::TAB_STYLE,
                'condition' => ['show_dropdown' => 'yes']
            ]
        );

        $this->add_control(
            'menu_box_position',
            [
                'label' => esc_html__( 'Position', 'mihanpanel' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => [
                    'left' => [
                        'title' => esc_html__( 'Left', 'mihanpanel' ),
                        'icon' => 'eicon-text-align-left',
                    ],
                    'right' => [
                        'title' => esc_html__( 'Right', 'mihanpanel' ),
                        'icon' => 'eicon-text-align-right',
                    ],
                ],
                'default' => is_rtl() ? 'left' : 'right',
                'toggle' => true,
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'menu_background',
                'types' => ['classic', 'gradient'],
                'exclude' => ['image'],
                'selector' => '{{WRAPPER}} .pb-dropdown-content',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Border::get_type(),
            [
                'name' => 'menu_border',
                'selector' => '{{WRAPPER}} .pb-dropdown-content',
            ]
        );

        $this->add_control(
            'menu_border_radius',
            [
                'label' => esc_html__( 'Border Radius', 'mihanpanel' ),
                'type' => Controls_Manager::DIMENSIONS,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'selectors' => [
                    '{{WRAPPER}} .pb-dropdown-content' => 'border-radius: {{TOP}}{{UNIT}} {{RIGHT}}{{UNIT}} {{BOTTOM}}{{UNIT}} {{LEFT}}{{UNIT}};',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Box_Shadow::get_type(),
            [
                'name' => 'menu_box_shadow',
                'selector' => '{{WRAPPER}} .pb-dropdown-content',
            ]
        );

        $this->add_control(
            'item_options',
            [
                'label' => esc_html__( 'Items', 'mihanpanel' ),
                'type' => Controls_Manager::HEADING,
                'separator' => 'before',
            ]
        );

        $this->start_controls_tabs('menu_items_style_tabs');
        $this->start_controls_tab('menu_items_style_normal_tab', ['label' => esc_html__('Normal', 'mihanpanel')]);

        $this->add_responsive_control(
            'menu_item_text_alignment',
            [
                'label' => esc_html__( 'Text alignment', 'mihanpanel' ),
                'type' => Controls_Manager::CHOOSE,
                'options' => is_rtl() ? array_reverse($AlignmentOptions) : $AlignmentOptions,
                'default' => 'start',
                'toggle' => false,
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a' => 'justify-content: {{VALUE}}',
                ],
            ]
        );

        $this->add_responsive_control(
            'menu_item_icon_size',
            [
                'label' => esc_html__( 'Icon Size', 'mihanpanel' ),
                'type' => Controls_Manager::SLIDER,
                'size_units' => [ 'px', '%', 'em', 'rem', 'custom' ],
                'range' => [
                    'px' => [
                        'min' => 0,
                        'max' => 400,
                    ],
                    '%' => [
                        'min' => 0,
                        'max' => 100,
                    ],
                ],
                'default' => [
                    'unit' => 'px',
                    'size' => 15,
                ],
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a :is(img, svg)' => 'width: {{SIZE}}{{UNIT}};height: {{SIZE}}{{UNIT}};',
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a i' => 'font-size: {{SIZE}}{{UNIT}};',
                ],
            ]
        );

        $this->add_control(
            'menu_item_icon_color',
            [
                'label' => esc_html__( 'Icon Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#333',
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a i' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a svg' => 'fill: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'menu_item_text_color',
            [
                'label' => esc_html__( 'Text Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'default' => '#333',
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Typography::get_type(),
            [
                'name' => 'menu_item_typography',
                'selector' => '{{WRAPPER}} .mpanel-profile-button-dropdown a',
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'menu_item_background',
                'types' => ['classic', 'gradient'],
                'exclude' => ['image'],
                'selector' => '{{WRAPPER}} .mpanel-profile-button-dropdown a',
            ]
        );

        $this->add_control(
            'menu_item_border_color',
            [
                'label' => esc_html__( 'Border Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a' => 'border-color: {{VALUE}}',
                ],
            ]
        );

        $this->end_controls_tab();
        $this->start_controls_tab('menu_items_style_hover_tab', ['label' => esc_html__('Hover', 'mihanpanel')]);

        $this->add_control(
            'menu_item_icon_color_hover',
            [
                'label' => esc_html__( 'Icon Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a:hover i' => 'color: {{VALUE}}',
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a:hover svg' => 'fill: {{VALUE}}',
                ],
            ]
        );

        $this->add_control(
            'menu_item_text_color_hover',
            [
                'label' => esc_html__( 'Text Color', 'mihanpanel' ),
                'type' => Controls_Manager::COLOR,
                'selectors' => [
                    '{{WRAPPER}} .mpanel-profile-button-dropdown a:hover' => 'color: {{VALUE}}',
                ],
            ]
        );

        $this->add_group_control(
            \Elementor\Group_Control_Background::get_type(),
            [
                'name' => 'menu_item_background_hover',
                'types' => ['classic', 'gradient'],
                'exclude' => ['image'],
                'selector' => '{{WRAPPER}} .mpanel-profile-button-dropdown a:hover',
            ]
        );

        $this->end_controls_tab();
        $this->end_controls_tabs();
        $this->end_controls_section();
    }

    public function get_login_url()
    {
        return \mihanpanel\app\options::getMihanPanelLoginUrl();
    }

    public function render()
    {
        $settings = $this->get_settings_for_display();
        $wid = $this->get_id();

        $show_dropdown = $settings['show_dropdown'] == 'yes';

        $beforeElementClass = 'mpanel-login-button';
        if ($settings['login_hover_animation']) {
            $beforeElementClass .= ' elementor-animation-' . $settings['login_hover_animation'];
        }
        $this->add_render_attribute('login_atts', 'class', $beforeElementClass);

        $panel_tabs = $settings['tabs'];
        ?>
        <div class="mpanel-login-element">
            <?php if (!is_user_logged_in() || (is_admin() && $settings['before_btn_preview'] == 'yes')): ?>
                <div class="mpanel-before-login title-in-mobile-<?php echo $settings['show_login_btn_title_in_mobile'] ?>">
                    <a href="<?php echo $this->get_login_url() ?>" <?php echo $this->get_render_attribute_string('login_atts'); ?>>
                        <?php if (!empty($settings['login_btn_icon']['value'])): ?>
                            <div class="btn-icon">
                                <?php \Elementor\Icons_Manager::render_icon( $settings['login_btn_icon'], [ 'aria-hidden' => 'true' ] ); ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($settings['show_login_btn_text'] == 'yes'): ?>
                            <span><?php echo $settings['login_btn_text'] ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            <?php
            else:
                $user = wp_get_current_user();
                ?>
                <div class="mpanel-after-login <?php echo !$show_dropdown ? 'without-dropdown' : '' ?>">
                    <div class="mpanel-profile-button-wrap">
                        <a href="<?php echo \mihanpanel\app\options::get_panel_url() ?>" class="mpanel-profile-button">
                            <?php if ($settings['show_user_avatar'] == 'yes'): ?>
                                <div class="pb-avatar">
                                    <img src="<?php echo get_avatar_url($user->ID) ?>" alt="avatar">
                                </div>
                            <?php endif; ?>
                            <?php if ($settings['show_display_name'] == 'yes'): ?>
                                <div class="pb-details">
                                    <div class="pb-name"><?php echo wp_trim_words($user->display_name, 2, '...'); ?></div>
                                </div>
                            <?php endif; ?>
                            <?php if ($show_dropdown): ?>
                            <div class="pb-icon">
                                <i class="fas fa-angle-down"></i>
                            </div>
                            <?php endif; ?>
                        </a>
                        <?php if ($show_dropdown): ?>
                        <div class="mpanel-profile-button-dropdown menu-position-<?php echo $settings['menu_box_position'] ?>">
                            <div class="pb-dropdown-content">
                                <?php if ($panel_tabs): ?>
                                <ul>
                                    <?php
                                    foreach ($panel_tabs as $tab):
                                        $itemData = $tab;
                                        $is_custom_tab = $itemData['use_custom_tab'] == 'yes';
                                        if($is_custom_tab){
                                            if (!empty($itemData['tab_link']['url'])) {
                                                $this->add_link_attributes('tab_link_' . $itemData['_id'], $itemData['tab_link']);
                                            }
                                        } else {
                                            $tab = panel::get_tab($itemData['panel_tab']);
                                            $tab->link = panel::get_tab_link($tab);
                                        }
                                        if (empty($tab))
                                            continue;
                                        ?>
                                    <li class="tab-item elementor-repeater-item-<?php echo $itemData['_id'] ?> <?php echo !$is_custom_tab ? ($tab->id == panel::get_current_tab() ? 'current' : '') : '' ?>">
                                        <a <?php echo $is_custom_tab ? $this->get_render_attribute_string('tab_link_' . $itemData['_id']) : "href='{$tab->link}'" ?>>
                                            <?php
                                            if($is_custom_tab){
                                                \Elementor\Icons_Manager::render_icon( $itemData['tab_icon'], [ 'aria-hidden' => 'true' ] );
                                            } else {
                                                if (filter_var($tab->icon, FILTER_VALIDATE_URL)){
                                                    echo "<img class='item-icon' src='{$tab->icon}'>";
                                                } else {
                                                    echo "<i class='{$tab->icon} item-icon'></i>";
                                                }
                                            }
                                            ?>
                                            <span><?php echo $is_custom_tab ? $itemData['tab_title'] : $tab->name ?></span>
                                        </a>
                                    </li>
                                    <?php endforeach; ?>
                                </ul>
                                <?php endif; ?>
                                <div class="pb-logout">
                                    <a href="<?php echo wp_logout_url(home_url()) ?>">
                                        <i class="fas fa-power-off"></i>
                                        <?php _e('Logout from account', 'mihanpanel') ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}