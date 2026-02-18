<?php
if (!defined('ABSPATH')) {
    exit;
}

class GCO_API_Analyzer {

    private $provider;
    private $api_key;
    private $timeout;

    public function __construct($provider, $api_key, $timeout = null) {
        $this->provider = $provider;
        $this->api_key = $api_key;
        
        if ($timeout === null) {
            $timeout = GCO_Settings::DEFAULT_API_TIMEOUT;
        }
        $this->timeout = max(
            GCO_Settings::MIN_API_TIMEOUT, 
            min(GCO_Settings::MAX_API_TIMEOUT, (int) $timeout)
        );
    }

    public function analyze($content, $title = '') {
        if ($this->provider === 'openai') {
            return $this->analyze_openai($content, $title);
        } elseif ($this->provider === 'anthropic') {
            return $this->analyze_anthropic($content, $title);
        }
        
        return $this->fallback_local($content, $title);
    }

    private function analyze_openai($content, $title) {
        $prompt = $this->build_prompt($content, $title);
        
        $response = wp_remote_post('https://api.openai.com/v1/chat/completions', [
            'timeout' => $this->timeout,
            'headers' => [
                'Authorization' => 'Bearer ' . $this->api_key,
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'gpt-4-turbo-preview',
                'messages' => [
                    [
                        'role' => 'system',
                        'content' => 'Tu es un expert en optimisation de contenu pour les moteurs de recherche génératifs (GEO). Analyse le contenu fourni et retourne un JSON structuré avec les scores et suggestions.',
                    ],
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
                'response_format' => ['type' => 'json_object'],
                'max_tokens' => 2000,
            ]),
        ]);
        
        if (is_wp_error($response)) {
            return $this->fallback_local($content, $title);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return $this->fallback_local($content, $title);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['choices'][0]['message']['content'])) {
            return $this->fallback_local($content, $title);
        }
        
        $result = json_decode($data['choices'][0]['message']['content'], true);
        $result = $this->validate_and_normalize_result($result);
        
        if (!$result) {
            return $this->fallback_local($content, $title);
        }
        
        $result['analysis_mode'] = 'api_openai';
        return $result;
    }

    private function analyze_anthropic($content, $title) {
        $prompt = $this->build_prompt($content, $title);
        
        $response = wp_remote_post('https://api.anthropic.com/v1/messages', [
            'timeout' => $this->timeout,
            'headers' => [
                'x-api-key' => $this->api_key,
                'anthropic-version' => '2023-06-01',
                'Content-Type' => 'application/json',
            ],
            'body' => wp_json_encode([
                'model' => 'claude-3-sonnet-20240229',
                'max_tokens' => 2000,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => $prompt,
                    ],
                ],
            ]),
        ]);
        
        if (is_wp_error($response)) {
            return $this->fallback_local($content, $title);
        }
        
        $http_code = wp_remote_retrieve_response_code($response);
        if ($http_code !== 200) {
            return $this->fallback_local($content, $title);
        }
        
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);
        
        if (!isset($data['content'][0]['text'])) {
            return $this->fallback_local($content, $title);
        }
        
        $result = GCO_Utils::extract_json($data['content'][0]['text']);
        
        if (!$result) {
            return $this->fallback_local($content, $title);
        }
        
        $result = $this->validate_and_normalize_result($result);
        
        if (!$result) {
            return $this->fallback_local($content, $title);
        }
        
        $result['analysis_mode'] = 'api_anthropic';
        return $result;
    }

    private function validate_and_normalize_result($result) {
        if (!is_array($result) || !isset($result['score'])) {
            return null;
        }
        
        $result['score'] = max(0, min(100, (int) $result['score']));
        
        if (!isset($result['grade']) || !in_array($result['grade'], ['A+', 'A', 'B', 'C', 'D', 'F'])) {
            $result['grade'] = GCO_Utils::score_to_grade($result['score']);
        }
        
        $default_subscores = ['citability' => 50, 'clarity' => 50, 'structure' => 50, 'factuality' => 50];
        if (!isset($result['subscores']) || !is_array($result['subscores'])) {
            $result['subscores'] = $default_subscores;
        } else {
            foreach ($default_subscores as $key => $default) {
                if (!isset($result['subscores'][$key])) {
                    $result['subscores'][$key] = $default;
                } else {
                    $result['subscores'][$key] = max(0, min(100, (int) $result['subscores'][$key]));
                }
            }
        }
        
        $default_metrics = ['total_words' => 0, 'total_sentences' => 0, 'total_paragraphs' => 0, 'avg_sentence_length' => 0];
        if (!isset($result['metrics']) || !is_array($result['metrics'])) {
            $result['metrics'] = $default_metrics;
        } else {
            foreach ($default_metrics as $key => $default) {
                if (!isset($result['metrics'][$key])) {
                    $result['metrics'][$key] = $default;
                }
            }
        }
        
        $result['suggestions'] = GCO_Utils::validate_suggestions_array($result['suggestions'] ?? []);
        
        if (!isset($result['best_sentences']) || !is_array($result['best_sentences'])) {
            $result['best_sentences'] = [];
        }
        
        if (!isset($result['weak_sentences']) || !is_array($result['weak_sentences'])) {
            $result['weak_sentences'] = [];
        }
        
        return $result;
    }

    private function build_prompt($content, $title) {
        $content_preview = mb_substr($content, 0, 8000);
        
        return "Analyse ce contenu pour son potentiel à être cité par les IA (ChatGPT, Claude, Perplexity).

TITRE: $title

CONTENU:
$content_preview

Retourne un JSON avec cette structure exacte:
{
    \"score\": [0-100],
    \"grade\": [A+/A/B/C/D/F],
    \"subscores\": {
        \"citability\": [0-100],
        \"clarity\": [0-100],
        \"structure\": [0-100],
        \"factuality\": [0-100]
    },
    \"metrics\": {
        \"total_words\": [int],
        \"total_sentences\": [int],
        \"total_paragraphs\": [int],
        \"avg_sentence_length\": [float]
    },
    \"suggestions\": [
        {
            \"type\": \"citability|clarity|structure|factuality|title|sentence\",
            \"priority\": \"high|medium|low\",
            \"message\": \"[suggestion courte]\",
            \"detail\": \"[explication détaillée]\"
        }
    ],
    \"best_sentences\": [
        {\"sentence\": \"[phrase]\", \"score\": [0-100]}
    ],
    \"weak_sentences\": [
        {\"sentence\": \"[phrase]\", \"score\": [0-100], \"issues\": [\"issue1\", \"issue2\"]}
    ]
}

Critères d'évaluation:
- Citabilité: phrases claires, factuelles, 15-25 mots, pas de langage vague
- Clarté: lisibilité, longueur des phrases et paragraphes
- Structure: sous-titres, listes, transitions
- Factualité: dates, chiffres, sources, noms propres";
    }

    private function fallback_local($content, $title) {
        $scorer = new GCO_Citability_Scorer();
        $suggestions_gen = new GCO_Suggestions();
        
        $sentences = GCO_Utils::split_sentences($content);
        $paragraphs = GCO_Utils::split_paragraphs($content);
        $words = GCO_Utils::count_words($content);
        
        $sentence_count = max(count($sentences), 1);
        $paragraph_count = max(count($paragraphs), 1);
        
        $metrics = [
            'total_words' => $words,
            'total_sentences' => count($sentences),
            'total_paragraphs' => count($paragraphs),
            'avg_sentence_length' => $words / $sentence_count,
            'avg_paragraph_length' => count($sentences) / $paragraph_count,
        ];
        
        $sentence_scores = [];
        foreach ($sentences as $sentence) {
            $sentence_scores[] = $scorer->score_sentence($sentence);
        }
        
        $citability = $scorer->calculate_citability($content, $metrics);
        $clarity = $scorer->calculate_clarity($metrics);
        $structure = $scorer->calculate_structure($content, $paragraphs);
        $factuality = $scorer->calculate_factuality($content);
        
        $score = round(
            ($citability * 0.35) +
            ($clarity * 0.25) +
            ($structure * 0.20) +
            ($factuality * 0.20)
        );
        
        $analysis_data = [
            'citability' => $citability,
            'clarity' => $clarity,
            'structure' => $structure,
            'factuality' => $factuality,
            'metrics' => $metrics,
            'sentence_scores' => $sentence_scores,
        ];
        
        $suggestions = $suggestions_gen->generate($content, $title, $analysis_data);
        
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
            'metrics' => $metrics,
            'suggestions' => $suggestions,
            'best_sentences' => $best_sentences,
            'weak_sentences' => $weak_sentences,
            'analysis_mode' => 'local_fallback',
        ];
    }
}
