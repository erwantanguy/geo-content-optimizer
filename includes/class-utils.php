<?php
if (!defined('ABSPATH')) {
    exit;
}

class GCO_Utils {

    public static function score_to_grade($score) {
        if ($score >= 90) return 'A+';
        if ($score >= 80) return 'A';
        if ($score >= 70) return 'B';
        if ($score >= 60) return 'C';
        if ($score >= 50) return 'D';
        return 'F';
    }

    public static function split_sentences($text) {
        $text = preg_replace('/\s+/', ' ', trim($text));
        $sentences = preg_split('/(?<=[.!?])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($sentences, function($s) {
            return mb_strlen(trim($s)) > 10;
        });
    }

    public static function split_paragraphs($text) {
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);
        return array_filter($paragraphs, function($p) {
            return mb_strlen(trim($p)) > 20;
        });
    }

    public static function count_words($text) {
        return str_word_count($text, 0, 'àâäéèêëïîôùûüÿçœæÀÂÄÉÈÊËÏÎÔÙÛÜŸÇŒÆ');
    }

    public static function find_best_sentences($sentences, $scores, $limit = 3) {
        $combined = [];
        foreach ($sentences as $i => $sentence) {
            if (isset($scores[$i])) {
                $combined[] = ['sentence' => $sentence, 'score' => $scores[$i]['score']];
            }
        }
        usort($combined, function($a, $b) { return $b['score'] - $a['score']; });
        return array_slice($combined, 0, $limit);
    }

    public static function find_weak_sentences($sentences, $scores, $threshold = 50, $limit = 5) {
        $combined = [];
        foreach ($sentences as $i => $sentence) {
            if (isset($scores[$i]) && $scores[$i]['score'] < $threshold) {
                $combined[] = [
                    'sentence' => $sentence,
                    'score' => $scores[$i]['score'],
                    'issues' => $scores[$i]['issues'] ?? [],
                ];
            }
        }
        usort($combined, function($a, $b) { return $a['score'] - $b['score']; });
        return array_slice($combined, 0, $limit);
    }

    public static function extract_json($text) {
        $decoded = json_decode($text, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return $decoded;
        }
        
        $start = strpos($text, '{');
        if ($start === false) {
            return null;
        }
        
        $len = strlen($text);
        $depth = 0;
        $in_string = false;
        $escape = false;
        $end = $start;
        
        for ($i = $start; $i < $len; $i++) {
            $char = $text[$i];
            
            if ($escape) {
                $escape = false;
                continue;
            }
            
            if ($char === '\\' && $in_string) {
                $escape = true;
                continue;
            }
            
            if ($char === '"') {
                $in_string = !$in_string;
                continue;
            }
            
            if ($in_string) {
                continue;
            }
            
            if ($char === '{') {
                $depth++;
            } elseif ($char === '}') {
                $depth--;
                if ($depth === 0) {
                    $end = $i;
                    break;
                }
            }
        }
        
        if ($depth !== 0) {
            return null;
        }
        
        $json_str = substr($text, $start, $end - $start + 1);
        $result = json_decode($json_str, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $result;
    }

    private static $allowed_detail_tags = [
        'strong' => [],
        'em' => [],
        'code' => [],
        'br' => [],
        'b' => [],
        'i' => [],
    ];

    public static function validate_suggestion($suggestion) {
        if (!is_array($suggestion)) {
            return null;
        }
        
        $valid_types = ['citability', 'clarity', 'structure', 'factuality', 'title', 'sentence', 'content'];
        $valid_priorities = ['high', 'medium', 'low'];
        
        return [
            'type' => in_array($suggestion['type'] ?? '', $valid_types) ? $suggestion['type'] : 'content',
            'priority' => in_array($suggestion['priority'] ?? '', $valid_priorities) ? $suggestion['priority'] : 'medium',
            'message' => isset($suggestion['message']) ? sanitize_text_field($suggestion['message']) : '',
            'detail' => isset($suggestion['detail']) ? wp_kses($suggestion['detail'], self::$allowed_detail_tags) : '',
        ];
    }

    public static function validate_suggestions_array($suggestions) {
        if (!is_array($suggestions)) {
            return [];
        }
        
        $validated = [];
        foreach ($suggestions as $suggestion) {
            $valid = self::validate_suggestion($suggestion);
            if ($valid && !empty($valid['message'])) {
                $validated[] = $valid;
            }
        }
        
        return $validated;
    }
}
