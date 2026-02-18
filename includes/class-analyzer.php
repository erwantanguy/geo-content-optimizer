<?php
if (!defined('ABSPATH')) {
    exit;
}

class GCO_Analyzer {

    private $scorer;
    private $suggestions;
    private $api_analyzer;

    public function __construct() {
        $this->scorer = new GCO_Citability_Scorer();
        $this->suggestions = new GCO_Suggestions();
        
        $settings = get_option('gco_settings', []);
        if (($settings['analysis_mode'] ?? 'local') === 'api' && !empty($settings['api_key'])) {
            $timeout = $settings['api_timeout'] ?? null;
            $this->api_analyzer = new GCO_API_Analyzer($settings['api_provider'], $settings['api_key'], $timeout);
        }
    }

    public function analyze($content, $title = '') {
        $clean_content = wp_strip_all_tags($content);
        $clean_content = html_entity_decode($clean_content, ENT_QUOTES, 'UTF-8');
        
        if ($this->api_analyzer) {
            $result = $this->api_analyzer->analyze($clean_content, $title);
            $result = $this->add_geo_blocks_info($result, $content);
            return $result;
        }
        
        return $this->analyze_local($clean_content, $title, $content);
    }

    private function analyze_local($content, $title, $raw_content = '') {
        $sentences = GCO_Utils::split_sentences($content);
        $paragraphs = GCO_Utils::split_paragraphs($content);
        $words = GCO_Utils::count_words($content);
        
        if (empty($sentences)) {
            return $this->empty_analysis_result();
        }
        
        $sentence_scores = [];
        foreach ($sentences as $sentence) {
            $sentence_scores[] = $this->scorer->score_sentence($sentence);
        }
        
        $sentence_count = max(count($sentences), 1);
        $paragraph_count = max(count($paragraphs), 1);
        
        $metrics = [
            'total_words' => $words,
            'total_sentences' => count($sentences),
            'total_paragraphs' => count($paragraphs),
            'avg_sentence_length' => $words / $sentence_count,
            'avg_paragraph_length' => count($sentences) / $paragraph_count,
        ];
        
        $citability = $this->scorer->calculate_citability($content, $metrics);
        $clarity = $this->scorer->calculate_clarity($metrics);
        $structure = $this->scorer->calculate_structure($content, $paragraphs);
        $factuality = $this->scorer->calculate_factuality($content);
        
        $base_score = round(
            ($citability * 0.35) +
            ($clarity * 0.25) +
            ($structure * 0.20) +
            ($factuality * 0.20)
        );
        
        $geo_blocks_bonus = 0;
        $geo_blocks_summary = [];
        if (!empty($raw_content)) {
            $geo_blocks_bonus = $this->scorer->calculate_geo_blocks_bonus($raw_content);
            $geo_blocks_summary = $this->scorer->get_geo_blocks_summary($raw_content);
        }
        
        $score = min(100, $base_score + $geo_blocks_bonus);
        
        $raw_suggestions = $this->suggestions->generate($content, $title, [
            'citability' => $citability,
            'clarity' => $clarity,
            'structure' => $structure,
            'factuality' => $factuality,
            'metrics' => $metrics,
            'sentence_scores' => $sentence_scores,
        ]);
        $suggestions = GCO_Utils::validate_suggestions_array($raw_suggestions);
        
        if (empty($geo_blocks_summary)) {
            $suggestions[] = [
                'type' => 'geo_blocks',
                'priority' => 'medium',
                'message' => 'Ajoutez des blocs GEO (TL;DR, How-To, FAQ...) pour ameliorer le score',
            ];
        }
        
        $best_sentences = GCO_Utils::find_best_sentences($sentences, $sentence_scores);
        $weak_sentences = GCO_Utils::find_weak_sentences($sentences, $sentence_scores);
        
        return [
            'score' => $score,
            'grade' => GCO_Utils::score_to_grade($score),
            'subscores' => [
                'citability' => $citability,
                'clarity' => $clarity,
                'structure' => $structure,
                'factuality' => $factuality,
            ],
            'geo_blocks_bonus' => $geo_blocks_bonus,
            'geo_blocks' => $geo_blocks_summary,
            'metrics' => $metrics,
            'suggestions' => $suggestions,
            'best_sentences' => $best_sentences,
            'weak_sentences' => $weak_sentences,
            'analysis_mode' => 'local',
        ];
    }

    private function add_geo_blocks_info($result, $raw_content) {
        if (!is_array($result)) {
            return $result;
        }
        
        $geo_blocks_bonus = $this->scorer->calculate_geo_blocks_bonus($raw_content);
        $geo_blocks_summary = $this->scorer->get_geo_blocks_summary($raw_content);
        
        $result['geo_blocks_bonus'] = $geo_blocks_bonus;
        $result['geo_blocks'] = $geo_blocks_summary;
        
        if (isset($result['score'])) {
            $result['score'] = min(100, $result['score'] + $geo_blocks_bonus);
            $result['grade'] = GCO_Utils::score_to_grade($result['score']);
        }
        
        return $result;
    }

    private function empty_analysis_result() {
        return [
            'score' => 0,
            'grade' => 'F',
            'subscores' => [
                'citability' => 0,
                'clarity' => 0,
                'structure' => 0,
                'factuality' => 0,
            ],
            'metrics' => [
                'total_words' => 0,
                'total_sentences' => 0,
                'total_paragraphs' => 0,
                'avg_sentence_length' => 0,
                'avg_paragraph_length' => 0,
            ],
            'suggestions' => [
                [
                    'type' => 'content',
                    'priority' => 'high',
                    'message' => __('Contenu insuffisant pour l\'analyse.', 'geo-content-optimizer'),
                    'detail' => __('Ajoutez du texte avec des phrases complètes pour obtenir une analyse.', 'geo-content-optimizer'),
                ],
            ],
            'best_sentences' => [],
            'weak_sentences' => [],
            'analysis_mode' => 'local',
        ];
    }
}
