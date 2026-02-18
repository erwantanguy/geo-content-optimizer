<?php
/**
 * Plugin Name: GEO Content Optimizer
 * Description: Analyse et optimise le contenu pour maximiser la citabilité par les IA (ChatGPT, Claude, Perplexity...)
 * Version: 1.1.0
 * Author: Erwan Tanguy
 * Text Domain: geo-content-optimizer
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if (!defined('ABSPATH')) {
    exit;
}

define('GCO_VERSION', '1.1.0');
define('GCO_PATH', plugin_dir_path(__FILE__));
define('GCO_URL', plugin_dir_url(__FILE__));

require_once GCO_PATH . 'includes/class-utils.php';
require_once GCO_PATH . 'includes/class-settings.php';
require_once GCO_PATH . 'includes/class-citability-scorer.php';
require_once GCO_PATH . 'includes/class-suggestions.php';
require_once GCO_PATH . 'includes/class-api-analyzer.php';
require_once GCO_PATH . 'includes/class-analyzer.php';
require_once GCO_PATH . 'includes/class-metabox.php';
require_once GCO_PATH . 'includes/class-admin-page.php';

register_activation_hook(__FILE__, 'gco_activate');

function gco_activate() {
    add_option('gco_settings', GCO_Settings::get_defaults());
    add_option('gco_version', GCO_VERSION);
}

add_action('plugins_loaded', 'gco_maybe_upgrade', 5);

function gco_maybe_upgrade() {
    $stored_version = get_option('gco_version', '0.0.0');
    
    if (version_compare($stored_version, GCO_VERSION, '<')) {
        $current_settings = get_option('gco_settings', []);
        $defaults = GCO_Settings::get_defaults();
        
        // Fusionne les paramètres existants avec les défauts, puis supprime les clés obsolètes
        $merged = array_intersect_key(
            wp_parse_args($current_settings, $defaults),
            $defaults
        );
        
        /**
         * Permet aux extensions de modifier les paramètres lors d'une mise à jour.
         *
         * @param array $merged           Paramètres nettoyés (clés obsolètes supprimées)
         * @param array $current_settings Paramètres avant migration
         * @param array $defaults         Valeurs par défaut actuelles
         */
        $merged = apply_filters('gco_upgrade_settings', $merged, $current_settings, $defaults);
        
        update_option('gco_settings', $merged);
        update_option('gco_version', GCO_VERSION);
    }
}

add_action('init', function() {
    new GCO_Metabox();
    new GCO_Admin_Page();
    new GCO_Settings();
});

add_action('admin_enqueue_scripts', function($hook) {
    global $post;
    
    $settings = get_option('gco_settings', []);
    $post_types = $settings['post_types'] ?? ['post', 'page'];
    
    $is_editor = in_array($hook, ['post.php', 'post-new.php']) && 
                 isset($post) && 
                 in_array($post->post_type, $post_types);
    
    $is_gco_page = strpos($hook, 'geo-content-optimizer') !== false;
    
    if (!$is_editor && !$is_gco_page) {
        return;
    }
    
    wp_enqueue_style(
        'gco-admin',
        GCO_URL . 'assets/css/admin.css',
        [],
        GCO_VERSION
    );
    
    wp_enqueue_script(
        'gco-admin',
        GCO_URL . 'assets/js/admin.js',
        ['jquery'],
        GCO_VERSION,
        true
    );
    
    wp_localize_script('gco-admin', 'gcoSettings', [
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gco_analyze'),
        'analyzing' => __('Analyse en cours...', 'geo-content-optimizer'),
        'error' => __('Erreur lors de l\'analyse', 'geo-content-optimizer'),
    ]);
});

add_action('wp_ajax_gco_analyze_content', function() {
    check_ajax_referer('gco_analyze', 'nonce');
    
    if (!current_user_can('edit_posts')) {
        wp_send_json_error(['message' => 'Permission refusée']);
    }
    
    $post_id = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0;
    $content = isset($_POST['content']) ? wp_kses_post(wp_unslash($_POST['content'])) : '';
    $title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
    
    if (empty($content)) {
        wp_send_json_error(['message' => 'Contenu vide']);
    }
    
    $analyzer = new GCO_Analyzer();
    $results = $analyzer->analyze($content, $title);
    
    if ($post_id > 0) {
        update_post_meta($post_id, '_gco_score', $results['score']);
        update_post_meta($post_id, '_gco_analysis', $results);
        update_post_meta($post_id, '_gco_last_analysis', current_time('mysql'));
    }
    
    wp_send_json_success($results);
});

add_action('save_post', function($post_id, $post, $update) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (wp_is_post_revision($post_id) || wp_is_post_autosave($post_id)) {
        return;
    }
    
    $settings = get_option('gco_settings', []);
    $post_types = $settings['post_types'] ?? ['post', 'page'];
    $auto_analyze = $settings['auto_analyze'] ?? true;
    
    if (!in_array($post->post_type, $post_types) || !$auto_analyze) {
        return;
    }
    
    if ($post->post_status !== 'publish') {
        return;
    }
    
    $analyzer = new GCO_Analyzer();
    $results = $analyzer->analyze($post->post_content, $post->post_title);
    
    update_post_meta($post_id, '_gco_score', $results['score']);
    update_post_meta($post_id, '_gco_analysis', $results);
    update_post_meta($post_id, '_gco_last_analysis', current_time('mysql'));
}, 10, 3);
