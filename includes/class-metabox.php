<?php
if (!defined('ABSPATH')) {
    exit;
}

class GCO_Metabox {

    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_metabox']);
    }

    public function add_metabox() {
        $settings = get_option('gco_settings', []);
        $post_types = $settings['post_types'] ?? ['post', 'page'];
        
        foreach ($post_types as $post_type) {
            add_meta_box(
                'gco_analysis',
                __('GEO Content Optimizer', 'geo-content-optimizer'),
                [$this, 'render_metabox'],
                $post_type,
                'side',
                'high'
            );
        }
    }

    public function render_metabox($post) {
        $score = get_post_meta($post->ID, '_gco_score', true);
        $analysis = get_post_meta($post->ID, '_gco_analysis', true);
        $last_analysis = get_post_meta($post->ID, '_gco_last_analysis', true);
        
        ?>
        <div class="gco-metabox">
            <div class="gco-score-container">
                <div class="gco-score-circle <?php echo $this->get_score_class($score); ?>">
                    <span class="gco-score-value"><?php echo $score !== '' ? esc_html($score) : '-'; ?></span>
                    <span class="gco-score-label"><?php esc_html_e('Score', 'geo-content-optimizer'); ?></span>
                </div>
                <?php if ($analysis && isset($analysis['grade'])): ?>
                    <div class="gco-grade">
                        <?php esc_html_e('Note :', 'geo-content-optimizer'); ?>
                        <strong><?php echo esc_html($analysis['grade']); ?></strong>
                    </div>
                <?php endif; ?>
            </div>
            
            <?php if ($analysis && isset($analysis['subscores'])): ?>
                <div class="gco-subscores">
                    <div class="gco-subscore">
                        <span class="gco-subscore-label"><?php esc_html_e('Citabilité', 'geo-content-optimizer'); ?></span>
                        <div class="gco-progress">
                            <div class="gco-progress-bar <?php echo $this->get_score_class($analysis['subscores']['citability']); ?>" 
                                 style="width: <?php echo esc_attr($analysis['subscores']['citability']); ?>%"></div>
                        </div>
                        <span class="gco-subscore-value"><?php echo esc_html($analysis['subscores']['citability']); ?></span>
                    </div>
                    <div class="gco-subscore">
                        <span class="gco-subscore-label"><?php esc_html_e('Clarté', 'geo-content-optimizer'); ?></span>
                        <div class="gco-progress">
                            <div class="gco-progress-bar <?php echo $this->get_score_class($analysis['subscores']['clarity']); ?>" 
                                 style="width: <?php echo esc_attr($analysis['subscores']['clarity']); ?>%"></div>
                        </div>
                        <span class="gco-subscore-value"><?php echo esc_html($analysis['subscores']['clarity']); ?></span>
                    </div>
                    <div class="gco-subscore">
                        <span class="gco-subscore-label"><?php esc_html_e('Structure', 'geo-content-optimizer'); ?></span>
                        <div class="gco-progress">
                            <div class="gco-progress-bar <?php echo $this->get_score_class($analysis['subscores']['structure']); ?>" 
                                 style="width: <?php echo esc_attr($analysis['subscores']['structure']); ?>%"></div>
                        </div>
                        <span class="gco-subscore-value"><?php echo esc_html($analysis['subscores']['structure']); ?></span>
                    </div>
                    <div class="gco-subscore">
                        <span class="gco-subscore-label"><?php esc_html_e('Factualité', 'geo-content-optimizer'); ?></span>
                        <div class="gco-progress">
                            <div class="gco-progress-bar <?php echo $this->get_score_class($analysis['subscores']['factuality']); ?>" 
                                 style="width: <?php echo esc_attr($analysis['subscores']['factuality']); ?>%"></div>
                        </div>
                        <span class="gco-subscore-value"><?php echo esc_html($analysis['subscores']['factuality']); ?></span>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($analysis && !empty($analysis['suggestions'])): ?>
                <div class="gco-suggestions">
                    <h4><?php esc_html_e('Suggestions', 'geo-content-optimizer'); ?></h4>
                    <ul>
                        <?php foreach (array_slice($analysis['suggestions'], 0, 3) as $suggestion): ?>
                            <li class="gco-suggestion gco-suggestion-<?php echo esc_attr($suggestion['priority']); ?>">
                                <span class="gco-suggestion-icon"></span>
                                <span class="gco-suggestion-text"><?php echo esc_html($suggestion['message']); ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php if (count($analysis['suggestions']) > 3): ?>
                        <a href="<?php echo esc_url(admin_url('admin.php?page=geo-content-optimizer&post_id=' . $post->ID)); ?>" class="gco-view-all">
                            <?php esc_html_e('Voir toutes les suggestions', 'geo-content-optimizer'); ?> →
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="gco-actions">
                <button type="button" class="button button-primary gco-analyze-btn" data-post-id="<?php echo esc_attr($post->ID); ?>">
                    <?php esc_html_e('Analyser maintenant', 'geo-content-optimizer'); ?>
                </button>
            </div>
            
            <?php if ($last_analysis): ?>
                <p class="gco-last-analysis">
                    <?php 
                    printf(
                        esc_html__('Dernière analyse : il y a %s', 'geo-content-optimizer'),
                        esc_html(human_time_diff(strtotime($last_analysis), current_time('timestamp')))
                    );
                    ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    private function get_score_class($score) {
        if ($score === '' || $score === null) return 'gco-score-none';
        if ($score >= 80) return 'gco-score-excellent';
        if ($score >= 60) return 'gco-score-good';
        if ($score >= 40) return 'gco-score-fair';
        return 'gco-score-poor';
    }
}
