<?php
if (!defined('ABSPATH')) {
    exit;
}

class GCO_Admin_Page {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
    }

    public function add_menu() {
        add_menu_page(
            __('Content Optimizer', 'geo-content-optimizer'),
            __('Content Optimizer', 'geo-content-optimizer'),
            'edit_posts',
            'geo-content-optimizer',
            [$this, 'render_page'],
            'dashicons-chart-line',
            31
        );
        
        add_submenu_page(
            'geo-content-optimizer',
            __('Vue d\'ensemble', 'geo-content-optimizer'),
            __('Vue d\'ensemble', 'geo-content-optimizer'),
            'edit_posts',
            'geo-content-optimizer',
            [$this, 'render_page']
        );
        
        add_submenu_page(
            'geo-content-optimizer',
            __('Paramètres', 'geo-content-optimizer'),
            __('Paramètres', 'geo-content-optimizer'),
            'manage_options',
            'geo-content-optimizer-settings',
            [$this, 'render_settings']
        );
    }

    public function render_page() {
        $post_id = isset($_GET['post_id']) ? absint($_GET['post_id']) : 0;
        
        if ($post_id > 0) {
            $this->render_single_post($post_id);
            return;
        }
        
        $this->render_overview();
    }

    private function render_overview() {
        $settings = get_option('gco_settings', []);
        $post_types = $settings['post_types'] ?? ['post', 'page'];
        
        $args = [
            'post_type' => $post_types,
            'post_status' => 'publish',
            'posts_per_page' => 50,
            'orderby' => 'modified',
            'order' => 'DESC',
        ];
        
        $posts = get_posts($args);
        
        $total_posts = 0;
        $analyzed_posts = 0;
        $score_sum = 0;
        $score_distribution = ['excellent' => 0, 'good' => 0, 'fair' => 0, 'poor' => 0];
        
        foreach ($posts as $post) {
            $total_posts++;
            $score = get_post_meta($post->ID, '_gco_score', true);
            if ($score !== '') {
                $analyzed_posts++;
                $score_sum += (int) $score;
                
                if ($score >= 80) $score_distribution['excellent']++;
                elseif ($score >= 60) $score_distribution['good']++;
                elseif ($score >= 40) $score_distribution['fair']++;
                else $score_distribution['poor']++;
            }
        }
        
        $avg_score = $analyzed_posts > 0 ? round($score_sum / $analyzed_posts) : 0;
        
        ?>
        <div class="wrap gco-admin">
            <h1><?php esc_html_e('GEO Content Optimizer - Vue d\'ensemble', 'geo-content-optimizer'); ?></h1>
            
            <div class="gco-stats-grid">
                <div class="gco-stat-card">
                    <span class="gco-stat-value"><?php echo esc_html($avg_score); ?></span>
                    <span class="gco-stat-label"><?php esc_html_e('Score moyen', 'geo-content-optimizer'); ?></span>
                </div>
                <div class="gco-stat-card">
                    <span class="gco-stat-value"><?php echo esc_html($analyzed_posts); ?>/<?php echo esc_html($total_posts); ?></span>
                    <span class="gco-stat-label"><?php esc_html_e('Contenus analysés', 'geo-content-optimizer'); ?></span>
                </div>
                <div class="gco-stat-card gco-stat-excellent">
                    <span class="gco-stat-value"><?php echo esc_html($score_distribution['excellent']); ?></span>
                    <span class="gco-stat-label"><?php esc_html_e('Excellents (≥80)', 'geo-content-optimizer'); ?></span>
                </div>
                <div class="gco-stat-card gco-stat-poor">
                    <span class="gco-stat-value"><?php echo esc_html($score_distribution['poor']); ?></span>
                    <span class="gco-stat-label"><?php esc_html_e('À améliorer (<40)', 'geo-content-optimizer'); ?></span>
                </div>
            </div>
            
            <div class="gco-content-table-container">
                <h2><?php esc_html_e('Contenus récents', 'geo-content-optimizer'); ?></h2>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th><?php esc_html_e('Titre', 'geo-content-optimizer'); ?></th>
                            <th class="gco-col-type"><?php esc_html_e('Type', 'geo-content-optimizer'); ?></th>
                            <th class="gco-col-score"><?php esc_html_e('Score', 'geo-content-optimizer'); ?></th>
                            <th class="gco-col-citability"><?php esc_html_e('Citabilité', 'geo-content-optimizer'); ?></th>
                            <th class="gco-col-date"><?php esc_html_e('Modifié', 'geo-content-optimizer'); ?></th>
                            <th class="gco-col-actions"><?php esc_html_e('Actions', 'geo-content-optimizer'); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($posts as $post): 
                            $score = get_post_meta($post->ID, '_gco_score', true);
                            $analysis = get_post_meta($post->ID, '_gco_analysis', true);
                            $citability = $analysis['subscores']['citability'] ?? '-';
                        ?>
                            <tr>
                                <td>
                                    <strong>
                                        <a href="<?php echo esc_url(admin_url('admin.php?page=geo-content-optimizer&post_id=' . $post->ID)); ?>">
                                            <?php echo esc_html($post->post_title); ?>
                                        </a>
                                    </strong>
                                </td>
                                <td><?php echo esc_html(get_post_type_object($post->post_type)->labels->singular_name); ?></td>
                                <td>
                                    <?php if ($score !== ''): ?>
                                        <span class="gco-score-badge <?php echo $this->get_score_class($score); ?>" 
                                              aria-label="<?php echo esc_attr($this->get_score_label($score)); ?>">
                                            <?php echo esc_html($score); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="gco-score-badge gco-score-none" aria-label="<?php esc_attr_e('Non analysé', 'geo-content-optimizer'); ?>">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($citability !== '-'): ?>
                                        <span class="gco-score-badge <?php echo $this->get_score_class($citability); ?>"
                                              aria-label="<?php echo esc_attr($this->get_score_label($citability)); ?>">
                                            <?php echo esc_html($citability); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="gco-score-badge gco-score-none" aria-label="<?php esc_attr_e('Non analysé', 'geo-content-optimizer'); ?>">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo esc_html(human_time_diff(strtotime($post->post_modified), current_time('timestamp'))); ?></td>
                                <td>
                                    <a href="<?php echo esc_url(get_edit_post_link($post->ID)); ?>" class="button button-small">
                                        <?php esc_html_e('Éditer', 'geo-content-optimizer'); ?>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
    }

    private function render_single_post($post_id) {
        $post = get_post($post_id);
        if (!$post) {
            wp_die(__('Contenu non trouvé', 'geo-content-optimizer'));
        }
        
        $analysis = get_post_meta($post_id, '_gco_analysis', true);
        $last_analysis = get_post_meta($post_id, '_gco_last_analysis', true);
        
        ?>
        <div class="wrap gco-admin">
            <h1>
                <a href="<?php echo esc_url(admin_url('admin.php?page=geo-content-optimizer')); ?>">←</a>
                <?php echo esc_html($post->post_title); ?>
            </h1>
            
            <?php if (!$analysis): ?>
                <div class="notice notice-warning">
                    <p><?php esc_html_e('Ce contenu n\'a pas encore été analysé.', 'geo-content-optimizer'); ?></p>
                    <p>
                        <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" class="button button-primary">
                            <?php esc_html_e('Éditer et analyser', 'geo-content-optimizer'); ?>
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <div class="gco-detail-grid">
                    <div class="gco-detail-main">
                        <div class="gco-score-header">
                            <div class="gco-score-big <?php echo $this->get_score_class($analysis['score']); ?>">
                                <span class="gco-score-number"><?php echo esc_html($analysis['score']); ?></span>
                                <span class="gco-score-grade"><?php echo esc_html($analysis['grade']); ?></span>
                            </div>
                            <div class="gco-subscores-detail">
                                <?php foreach ($analysis['subscores'] as $key => $value): ?>
                                    <div class="gco-subscore-item">
                                        <span class="gco-subscore-name"><?php echo esc_html(ucfirst($key)); ?></span>
                                        <div class="gco-progress-large">
                                            <div class="gco-progress-bar <?php echo $this->get_score_class($value); ?>" 
                                                 style="width: <?php echo esc_attr($value); ?>%"></div>
                                        </div>
                                        <span class="gco-subscore-value"><?php echo esc_html($value); ?>/100</span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <?php if (!empty($analysis['suggestions'])): ?>
                            <div class="gco-suggestions-detail">
                                <h2><?php esc_html_e('Suggestions d\'amélioration', 'geo-content-optimizer'); ?></h2>
                                <div class="gco-suggestions-list">
                                    <?php foreach ($analysis['suggestions'] as $suggestion): ?>
                                        <div class="gco-suggestion-card gco-priority-<?php echo esc_attr($suggestion['priority']); ?>">
                                            <div class="gco-suggestion-header">
                                                <span class="gco-suggestion-type"><?php echo esc_html($suggestion['type']); ?></span>
                                                <span class="gco-suggestion-priority"><?php echo esc_html($suggestion['priority']); ?></span>
                                            </div>
                                            <p class="gco-suggestion-message"><?php echo esc_html($suggestion['message']); ?></p>
                                            <p class="gco-suggestion-detail"><?php echo esc_html($suggestion['detail']); ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($analysis['best_sentences'])): ?>
                            <div class="gco-sentences-section">
                                <h2><?php esc_html_e('Meilleures phrases citables', 'geo-content-optimizer'); ?></h2>
                                <ul class="gco-best-sentences">
                                    <?php foreach ($analysis['best_sentences'] as $item): ?>
                                        <li>
                                            <span class="gco-sentence-score"><?php echo esc_html($item['score']); ?></span>
                                            <q><?php echo esc_html(wp_trim_words($item['sentence'], 30)); ?></q>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($analysis['weak_sentences'])): ?>
                            <div class="gco-sentences-section">
                                <h2><?php esc_html_e('Phrases à améliorer', 'geo-content-optimizer'); ?></h2>
                                <ul class="gco-weak-sentences">
                                    <?php foreach ($analysis['weak_sentences'] as $item): ?>
                                        <li>
                                            <span class="gco-sentence-score gco-score-poor"><?php echo esc_html($item['score']); ?></span>
                                            <q><?php echo esc_html(wp_trim_words($item['sentence'], 30)); ?></q>
                                            <?php if (!empty($item['issues'])): ?>
                                                <div class="gco-sentence-issues">
                                                    <?php foreach ($item['issues'] as $issue): ?>
                                                        <span class="gco-issue-tag"><?php echo esc_html($issue); ?></span>
                                                    <?php endforeach; ?>
                                                </div>
                                            <?php endif; ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="gco-detail-sidebar">
                        <div class="gco-sidebar-card">
                            <h3><?php esc_html_e('Métriques', 'geo-content-optimizer'); ?></h3>
                            <dl class="gco-metrics">
                                <dt><?php esc_html_e('Mots', 'geo-content-optimizer'); ?></dt>
                                <dd><?php echo esc_html($analysis['metrics']['total_words']); ?></dd>
                                
                                <dt><?php esc_html_e('Phrases', 'geo-content-optimizer'); ?></dt>
                                <dd><?php echo esc_html($analysis['metrics']['total_sentences']); ?></dd>
                                
                                <dt><?php esc_html_e('Paragraphes', 'geo-content-optimizer'); ?></dt>
                                <dd><?php echo esc_html($analysis['metrics']['total_paragraphs']); ?></dd>
                                
                                <dt><?php esc_html_e('Mots/phrase (moy.)', 'geo-content-optimizer'); ?></dt>
                                <dd><?php echo esc_html(round($analysis['metrics']['avg_sentence_length'], 1)); ?></dd>
                            </dl>
                        </div>
                        
                        <div class="gco-sidebar-card">
                            <h3><?php esc_html_e('Actions', 'geo-content-optimizer'); ?></h3>
                            <a href="<?php echo esc_url(get_edit_post_link($post_id)); ?>" class="button button-primary button-large">
                                <?php esc_html_e('Éditer le contenu', 'geo-content-optimizer'); ?>
                            </a>
                            <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="button" target="_blank">
                                <?php esc_html_e('Voir la page', 'geo-content-optimizer'); ?>
                            </a>
                        </div>
                        
                        <?php if ($last_analysis): ?>
                            <p class="gco-last-analysis">
                                <?php 
                                printf(
                                    esc_html__('Analysé le %s', 'geo-content-optimizer'),
                                    esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($last_analysis)))
                                );
                                ?>
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    public function render_settings() {
        $settings_page = new GCO_Settings();
        $settings_page->render();
    }

    private function get_score_class($score) {
        if ($score === '' || $score === null || $score === '-') return 'gco-score-none';
        if ($score >= 80) return 'gco-score-excellent';
        if ($score >= 60) return 'gco-score-good';
        if ($score >= 40) return 'gco-score-fair';
        return 'gco-score-poor';
    }

    private function get_score_label($score) {
        if ($score === '' || $score === null || $score === '-') {
            return __('Non analysé', 'geo-content-optimizer');
        }
        if ($score >= 80) {
            return sprintf(__('Score excellent : %d sur 100', 'geo-content-optimizer'), $score);
        }
        if ($score >= 60) {
            return sprintf(__('Bon score : %d sur 100', 'geo-content-optimizer'), $score);
        }
        if ($score >= 40) {
            return sprintf(__('Score moyen : %d sur 100', 'geo-content-optimizer'), $score);
        }
        return sprintf(__('Score faible : %d sur 100', 'geo-content-optimizer'), $score);
    }
}
