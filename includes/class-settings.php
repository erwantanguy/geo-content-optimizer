<?php
if (!defined('ABSPATH')) {
    exit;
}

class GCO_Settings {

    const DEFAULT_API_TIMEOUT = 90;
    const MIN_API_TIMEOUT = 30;
    const MAX_API_TIMEOUT = 180;

    private static $defaults = [
        'analysis_mode' => 'local',
        'api_provider' => '',
        'api_key' => '',
        'api_timeout' => self::DEFAULT_API_TIMEOUT,
        'auto_analyze' => true,
        'post_types' => ['post', 'page'],
    ];

    public static function get_defaults() {
        return self::$defaults;
    }

    public function __construct() {
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_init', [$this, 'maybe_generate_external_api_key']);
    }

    public function maybe_generate_external_api_key() {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (isset($_GET['gco_generate_api_key']) && wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['gco_generate_api_key'])), 'gco_generate_api_key')) {
            $new_key = wp_generate_password(32, false, false);
            update_option('geo_content_optimizer_api_key', $new_key);
            wp_redirect(admin_url('admin.php?page=geo-content-optimizer-settings'));
            exit;
        }
    }

    public function register_settings() {
        register_setting('gco_settings_group', 'gco_settings', [
            'type' => 'array',
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    public function sanitize_settings($input) {
        if (!current_user_can('manage_options')) {
            return get_option('gco_settings', []);
        }
        
        $sanitized = [];
        
        $sanitized['analysis_mode'] = in_array($input['analysis_mode'] ?? '', ['local', 'api']) 
            ? $input['analysis_mode'] 
            : 'local';
        
        $sanitized['api_provider'] = in_array($input['api_provider'] ?? '', ['openai', 'anthropic', '']) 
            ? $input['api_provider'] 
            : '';
        
        $sanitized['api_key'] = sanitize_text_field($input['api_key'] ?? '');
        
        $sanitized['api_timeout'] = max(
            self::MIN_API_TIMEOUT, 
            min(self::MAX_API_TIMEOUT, absint($input['api_timeout'] ?? self::DEFAULT_API_TIMEOUT))
        );
        
        $sanitized['auto_analyze'] = !empty($input['auto_analyze']);
        
        $allowed_post_types = get_post_types(['public' => true], 'names');
        $sanitized['post_types'] = array_intersect($input['post_types'] ?? self::$defaults['post_types'], $allowed_post_types);
        
        if (empty($sanitized['post_types'])) {
            $sanitized['post_types'] = self::$defaults['post_types'];
        }
        
        return $sanitized;
    }

    public function render() {
        $settings = wp_parse_args(get_option('gco_settings', []), self::$defaults);
        
        $post_types = get_post_types(['public' => true], 'objects');
        
        ?>
        <div class="wrap gco-admin">
            <h1><?php esc_html_e('GEO Content Optimizer - Paramètres', 'geo-content-optimizer'); ?></h1>

            <div class="gco-settings-section" style="background: #f0f6fc; border-left: 4px solid #2271b1;">
                <h2><?php esc_html_e('Clé API externe', 'geo-content-optimizer'); ?></h2>
                <p class="description">
                    <?php esc_html_e('Cette clé permet à des applications externes (Guide decision, dashboards...) de récupérer les scores de citabilité et EEAT via l\'API REST.', 'geo-content-optimizer'); ?>
                </p>

                <?php
                $api_key = get_option('geo_content_optimizer_api_key', '');
                if (empty($api_key)) {
                    $api_key = __('Non générée', 'geo-content-optimizer');
                }
                ?>

                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="gco_external_api_key"><?php esc_html_e('Clé API', 'geo-content-optimizer'); ?></label>
                        </th>
                        <td>
                            <input type="text" id="gco_external_api_key" value="<?php echo esc_attr($api_key); ?>" class="regular-text" readonly>
                            <p class="description">
                                <?php esc_html_e('En-tête attendu : X-GEO-Content-API-Key', 'geo-content-optimizer'); ?>
                            </p>
                            <p>
                                <a href="<?php echo esc_url(wp_nonce_url(admin_url('admin.php?page=geo-content-optimizer-settings&gco_generate_api_key=1'), 'gco_generate_api_key', 'gco_generate_api_key')); ?>" class="button">
                                    <?php esc_html_e('Générer / Régénérer la clé', 'geo-content-optimizer'); ?>
                                </a>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <form method="post" action="options.php">
                <?php settings_fields('gco_settings_group'); ?>
                
                <div class="gco-settings-section">
                    <h2><?php esc_html_e('Mode d\'analyse', 'geo-content-optimizer'); ?></h2>
                    <p class="description">
                        <?php esc_html_e('Choisissez comment analyser votre contenu.', 'geo-content-optimizer'); ?>
                    </p>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Mode', 'geo-content-optimizer'); ?></th>
                            <td>
                                <fieldset>
                                    <label>
                                        <input type="radio" name="gco_settings[analysis_mode]" value="local" 
                                               <?php checked($settings['analysis_mode'], 'local'); ?>>
                                        <?php esc_html_e('Local (algorithmes PHP)', 'geo-content-optimizer'); ?>
                                        <p class="description">
                                            <?php esc_html_e('Analyse basée sur des règles. Gratuit, sans dépendance externe.', 'geo-content-optimizer'); ?>
                                        </p>
                                    </label>
                                    <br><br>
                                    <label>
                                        <input type="radio" name="gco_settings[analysis_mode]" value="api" 
                                               <?php checked($settings['analysis_mode'], 'api'); ?>>
                                        <?php esc_html_e('API IA (analyse avancée)', 'geo-content-optimizer'); ?>
                                        <p class="description">
                                            <?php esc_html_e('Analyse sémantique via OpenAI ou Claude. Plus précis, nécessite une clé API.', 'geo-content-optimizer'); ?>
                                        </p>
                                    </label>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="gco-settings-section gco-api-settings" style="<?php echo $settings['analysis_mode'] !== 'api' ? 'opacity: 0.5;' : ''; ?>">
                    <h2><?php esc_html_e('Configuration API', 'geo-content-optimizer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="api_provider"><?php esc_html_e('Fournisseur', 'geo-content-optimizer'); ?></label>
                            </th>
                            <td>
                                <select name="gco_settings[api_provider]" id="api_provider">
                                    <option value=""><?php esc_html_e('-- Sélectionner --', 'geo-content-optimizer'); ?></option>
                                    <option value="openai" <?php selected($settings['api_provider'], 'openai'); ?>>OpenAI (GPT-4)</option>
                                    <option value="anthropic" <?php selected($settings['api_provider'], 'anthropic'); ?>>Anthropic (Claude)</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api_key"><?php esc_html_e('Clé API', 'geo-content-optimizer'); ?></label>
                            </th>
                            <td>
                                <input type="password" name="gco_settings[api_key]" id="api_key" 
                                       value="<?php echo esc_attr($settings['api_key']); ?>" class="regular-text">
                                <p class="description">
                                    <?php esc_html_e('Votre clé API est stockée dans la base de données WordPress. Assurez-vous que votre site est sécurisé.', 'geo-content-optimizer'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="api_timeout"><?php esc_html_e('Timeout API (secondes)', 'geo-content-optimizer'); ?></label>
                            </th>
                            <td>
                                <input type="number" name="gco_settings[api_timeout]" id="api_timeout" 
                                       value="<?php echo esc_attr($settings['api_timeout']); ?>" 
                                       min="<?php echo esc_attr(self::MIN_API_TIMEOUT); ?>" 
                                       max="<?php echo esc_attr(self::MAX_API_TIMEOUT); ?>" 
                                       step="10" class="small-text">
                                <p class="description">
                                    <?php 
                                    printf(
                                        esc_html__('Temps d\'attente maximum pour l\'analyse API. Augmentez pour les contenus longs (%d-%ds, défaut : %ds).', 'geo-content-optimizer'),
                                        self::MIN_API_TIMEOUT,
                                        self::MAX_API_TIMEOUT,
                                        self::DEFAULT_API_TIMEOUT
                                    );
                                    ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <div class="gco-settings-section">
                    <h2><?php esc_html_e('Options générales', 'geo-content-optimizer'); ?></h2>
                    
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php esc_html_e('Analyse automatique', 'geo-content-optimizer'); ?></th>
                            <td>
                                <label>
                                    <input type="checkbox" name="gco_settings[auto_analyze]" value="1" 
                                           <?php checked($settings['auto_analyze'], true); ?>>
                                    <?php esc_html_e('Analyser automatiquement lors de la publication', 'geo-content-optimizer'); ?>
                                </label>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row"><?php esc_html_e('Types de contenu', 'geo-content-optimizer'); ?></th>
                            <td>
                                <fieldset>
                                    <?php foreach ($post_types as $post_type): ?>
                                        <label>
                                            <input type="checkbox" name="gco_settings[post_types][]" 
                                                   value="<?php echo esc_attr($post_type->name); ?>"
                                                   <?php checked(in_array($post_type->name, $settings['post_types'])); ?>>
                                            <?php echo esc_html($post_type->labels->name); ?>
                                        </label><br>
                                    <?php endforeach; ?>
                                </fieldset>
                            </td>
                        </tr>
                    </table>
                </div>
                
                <?php submit_button(); ?>
            </form>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('input[name="gco_settings[analysis_mode]"]').on('change', function() {
                var isApi = $(this).val() === 'api';
                $('.gco-api-settings').css('opacity', isApi ? '1' : '0.5');
            });
        });
        </script>
        <?php
    }
}
