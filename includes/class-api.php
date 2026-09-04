<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * API REST de GEO Content Optimizer
 *
 * Permet aux applications externes (Guide decision, etc.) de recuperer
 * les scores de citabilite, EEAT et YMYL des contenus analyses.
 */
class GCO_API {

    private $namespace = 'geo-content-optimizer/v1';

    public function __construct() {
        add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes() {
        register_rest_route($this->namespace, '/ping', [
            'methods' => 'GET',
            'callback' => [$this, 'ping'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route($this->namespace, '/scores', [
            'methods' => 'GET',
            'callback' => [$this, 'get_scores'],
            'permission_callback' => [$this, 'check_api_key'],
            'args' => [
                'urls' => [
                    'description' => __('Liste d\'URLs separees par des virgules', 'geo-content-optimizer'),
                    'type' => 'string',
                    'validate_callback' => function ($param) {
                        return is_string($param);
                    },
                ],
                'limit' => [
                    'description' => __('Nombre maximum de resultats', 'geo-content-optimizer'),
                    'type' => 'integer',
                    'default' => 100,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param >= 1 && $param <= 500;
                    },
                ],
                'min_score' => [
                    'description' => __('Score minimum (filtrage)', 'geo-content-optimizer'),
                    'type' => 'integer',
                    'default' => 0,
                    'validate_callback' => function ($param) {
                        return is_numeric($param) && $param >= 0 && $param <= 100;
                    },
                ],
            ],
        ]);

        register_rest_route($this->namespace, '/score-by-url', [
            'methods' => 'GET',
            'callback' => [$this, 'get_score_by_url'],
            'permission_callback' => [$this, 'check_api_key'],
            'args' => [
                'url' => [
                    'description' => __('URL du contenu a analyser', 'geo-content-optimizer'),
                    'type' => 'string',
                    'required' => true,
                    'validate_callback' => function ($param) {
                        return is_string($param) && !empty($param);
                    },
                ],
            ],
        ]);
    }

    /**
     * Verifie la cle API passee dans l'en-tete X-GEO-Content-API-Key.
     */
    public function check_api_key($request) {
        $api_key = $request->get_header('X-GEO-Content-API-Key');
        $stored_key = get_option('geo_content_optimizer_api_key', '');

        if (empty($stored_key)) {
            return new WP_Error(
                'api_key_not_configured',
                __('Cle API non configuree.', 'geo-content-optimizer'),
                ['status' => 503]
            );
        }

        if (empty($api_key) || !hash_equals($stored_key, $api_key)) {
            return new WP_Error(
                'invalid_api_key',
                __('Cle API invalide.', 'geo-content-optimizer'),
                ['status' => 401]
            );
        }

        return true;
    }

    public function ping() {
        return rest_ensure_response([
            'plugin' => 'GEO Content Optimizer',
            'version' => GCO_VERSION,
            'status' => 'ok',
        ]);
    }

    /**
     * Recupere les scores pour une liste d'URLs ou tous les contenus analyses.
     */
    public function get_scores($request) {
        global $wpdb;

        $urls_param = $request->get_param('urls');
        $limit = (int) $request->get_param('limit');
        $min_score = (int) $request->get_param('min_score');

        $post_ids = [];

        if (!empty($urls_param)) {
            $urls = array_map('trim', explode(',', $urls_param));
            $urls = array_filter($urls);

            foreach ($urls as $url) {
                $post_id = url_to_postid($url);
                if ($post_id > 0) {
                    $post_ids[] = $post_id;
                }
            }

            $post_ids = array_unique(array_map('intval', $post_ids));

            if (empty($post_ids)) {
                return rest_ensure_response([
                    'total' => 0,
                    'scores' => [],
                ]);
            }
        } else {
            // Recupere tous les contenus ayant un score
            $post_ids = $wpdb->get_col($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta}
                 WHERE meta_key = '_gco_score'
                 AND meta_value >= %d
                 ORDER BY CAST(meta_value AS UNSIGNED) DESC
                 LIMIT %d",
                $min_score,
                $limit
            ));
        }

        $scores = [];
        foreach ($post_ids as $post_id) {
            $post = get_post($post_id);
            if (!$post || $post->post_status !== 'publish') {
                continue;
            }

            $score = (int) get_post_meta($post_id, '_gco_score', true);
            if ($score < $min_score) {
                continue;
            }

            $analysis = get_post_meta($post_id, '_gco_analysis', true);
            $last_analysis = get_post_meta($post_id, '_gco_last_analysis', true);

            $scores[] = [
                'post_id' => $post_id,
                'url' => get_permalink($post_id),
                'title' => get_the_title($post_id),
                'post_type' => $post->post_type,
                'score' => $score,
                'grade' => GCO_Utils::score_to_grade($score),
                'subscores' => $analysis['subscores'] ?? [
                    'citability' => 0,
                    'clarity' => 0,
                    'structure' => 0,
                    'factuality' => 0,
                    'eeat' => 0,
                ],
                'ymyl' => $analysis['ymyl'] ?? [
                    'score' => 0,
                    'categories' => [],
                    'is_ymyl' => false,
                ],
                'suggestions_count' => count($analysis['suggestions'] ?? []),
                'last_analysis' => $last_analysis,
            ];
        }

        return rest_ensure_response([
            'total' => count($scores),
            'scores' => $scores,
        ]);
    }

    /**
     * Recupere le score pour une URL specifique.
     */
    public function get_score_by_url($request) {
        $url = $request->get_param('url');
        $post_id = url_to_postid($url);

        if ($post_id <= 0) {
            return new WP_Error(
                'url_not_found',
                __('Aucun contenu trouve pour cette URL.', 'geo-content-optimizer'),
                ['status' => 404]
            );
        }

        $score = (int) get_post_meta($post_id, '_gco_score', true);
        $analysis = get_post_meta($post_id, '_gco_analysis', true);
        $last_analysis = get_post_meta($post_id, '_gco_last_analysis', true);

        return rest_ensure_response([
            'post_id' => $post_id,
            'url' => get_permalink($post_id),
            'title' => get_the_title($post_id),
            'score' => $score,
            'grade' => GCO_Utils::score_to_grade($score),
            'subscores' => $analysis['subscores'] ?? [],
            'ymyl' => $analysis['ymyl'] ?? [],
            'suggestions' => $analysis['suggestions'] ?? [],
            'last_analysis' => $last_analysis,
        ]);
    }
}

new GCO_API();
