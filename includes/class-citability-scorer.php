<?php
if (!defined('ABSPATH')) {
    exit;
}

class GCO_Citability_Scorer {

    private $ideal_sentence_length = [15, 25];
    private $max_sentence_length = 40;

    public function score_sentence($sentence) {
        $score = 100;
        $issues = [];
        
        $words = str_word_count($sentence, 0, 'àâäéèêëïîôùûüÿçœæ');
        
        if ($words > $this->max_sentence_length) {
            $penalty = min(40, ($words - $this->max_sentence_length) * 2);
            $score -= $penalty;
            $issues[] = 'too_long';
        } elseif ($words < 5) {
            $score -= 20;
            $issues[] = 'too_short';
        } elseif ($words < $this->ideal_sentence_length[0] || $words > $this->ideal_sentence_length[1]) {
            $score -= 10;
        }
        
        if (preg_match('/^\d/', $sentence) || preg_match('/\d+/', $sentence)) {
            $score += 5;
        }
        
        if (preg_match('/\d{4}/', $sentence)) {
            $score += 5;
        }
        
        $vague_words = [
            'très', 'beaucoup', 'plusieurs', 'quelques', 'certains',
            'souvent', 'parfois', 'généralement', 'habituellement',
            'assez', 'plutôt', 'environ', 'à peu près',
            'choses', 'trucs', 'etc', 'et cetera',
        ];
        
        $sentence_lower = mb_strtolower($sentence);
        $vague_count = 0;
        foreach ($vague_words as $word) {
            if (mb_strpos($sentence_lower, $word) !== false) {
                $vague_count++;
            }
        }
        
        if ($vague_count > 0) {
            $score -= min(25, $vague_count * 8);
            $issues[] = 'vague_language';
        }
        
        if (preg_match('/[A-Z][a-zàâäéèêëïîôùûüÿçœæ]+/', $sentence)) {
            $score += 5;
        }
        
        $passive_patterns = [
            '/est\s+\w+é[es]?\s+par/',
            '/sont\s+\w+é[es]?\s+par/',
            '/a\s+été\s+\w+é/',
            '/ont\s+été\s+\w+é/',
        ];
        
        foreach ($passive_patterns as $pattern) {
            if (preg_match($pattern, $sentence_lower)) {
                $score -= 10;
                $issues[] = 'passive_voice';
                break;
            }
        }
        
        $filler_phrases = [
            'il est important de noter que',
            'il convient de souligner que',
            'force est de constater que',
            'il va sans dire que',
            'comme on le sait',
            'il est évident que',
            'on peut dire que',
            'il faut savoir que',
        ];
        
        foreach ($filler_phrases as $filler) {
            if (mb_strpos($sentence_lower, $filler) !== false) {
                $score -= 15;
                $issues[] = 'filler_phrase';
                break;
            }
        }
        
        return [
            'score' => max(0, min(100, $score)),
            'issues' => $issues,
            'word_count' => $words,
        ];
    }

    public function calculate_citability($content, $metrics) {
        $score = 70;
        
        $avg_length = $metrics['avg_sentence_length'];
        if ($avg_length >= $this->ideal_sentence_length[0] && $avg_length <= $this->ideal_sentence_length[1]) {
            $score += 15;
        } elseif ($avg_length > $this->max_sentence_length) {
            $score -= 20;
        } elseif ($avg_length > $this->ideal_sentence_length[1]) {
            $score -= 10;
        }
        
        $number_density = preg_match_all('/\d+/', $content) / max($metrics['total_words'], 1) * 100;
        if ($number_density >= 1 && $number_density <= 5) {
            $score += 10;
        } elseif ($number_density > 0) {
            $score += 5;
        }
        
        $quote_count = preg_match_all('/[«"].*?[»"]/', $content);
        if ($quote_count > 0) {
            $score += min(10, $quote_count * 3);
        }
        
        $list_patterns = ['- ', '• ', '* ', '/^\d+\.\s/m'];
        $has_lists = false;
        foreach ($list_patterns as $pattern) {
            if (is_string($pattern) && strpos($content, $pattern) !== false) {
                $has_lists = true;
                break;
            }
            if (is_string($pattern) && $pattern[0] === '/' && preg_match($pattern, $content)) {
                $has_lists = true;
                break;
            }
        }
        if ($has_lists) {
            $score += 5;
        }
        
        return max(0, min(100, $score));
    }

    public function calculate_clarity($metrics) {
        $score = 70;
        
        $avg_sentence = $metrics['avg_sentence_length'];
        if ($avg_sentence >= 10 && $avg_sentence <= 20) {
            $score += 20;
        } elseif ($avg_sentence >= 8 && $avg_sentence <= 25) {
            $score += 10;
        } elseif ($avg_sentence > 35) {
            $score -= 20;
        }
        
        $avg_para = $metrics['avg_paragraph_length'];
        if ($avg_para >= 3 && $avg_para <= 6) {
            $score += 10;
        } elseif ($avg_para > 10) {
            $score -= 10;
        }
        
        return max(0, min(100, $score));
    }

    public function calculate_structure($content, $paragraphs) {
        $score = 60;
        
        $para_count = count($paragraphs);
        if ($para_count >= 3) {
            $score += 10;
        }
        if ($para_count >= 5) {
            $score += 5;
        }
        
        if (preg_match('/^#+\s/m', $content) || preg_match('/<h[1-6]/i', $content)) {
            $score += 15;
        }
        
        $transition_words = [
            'premièrement', 'deuxièmement', 'ensuite', 'enfin',
            'de plus', 'en outre', 'par ailleurs', 'cependant',
            'toutefois', 'néanmoins', 'en revanche', 'par conséquent',
            'ainsi', 'donc', 'en effet', 'car', 'parce que',
            'c\'est pourquoi', 'en conclusion', 'pour conclure',
        ];
        
        $content_lower = mb_strtolower($content);
        $transition_count = 0;
        foreach ($transition_words as $word) {
            if (mb_strpos($content_lower, $word) !== false) {
                $transition_count++;
            }
        }
        
        if ($transition_count >= 3) {
            $score += 10;
        } elseif ($transition_count >= 1) {
            $score += 5;
        }
        
        return max(0, min(100, $score));
    }

    public function calculate_factuality($content) {
        $score = 50;
        
        if (preg_match('/\d{4}/', $content)) {
            $score += 15;
        }
        
        if (preg_match('/\d+\s*(%|pour\s*cent|euros?|€|\$|km|m²|habitants?)/', $content)) {
            $score += 15;
        }
        
        $proper_nouns = preg_match_all('/[A-Z][a-zàâäéèêëïîôùûüÿçœæ]{2,}/', $content);
        if ($proper_nouns >= 3) {
            $score += 10;
        } elseif ($proper_nouns >= 1) {
            $score += 5;
        }
        
        if (preg_match('/(selon|d\'après|source\s*:)/i', $content)) {
            $score += 10;
        }
        
        $opinion_markers = [
            'je pense', 'je crois', 'à mon avis', 'selon moi',
            'il me semble', 'personnellement', 'j\'estime',
        ];
        
        $content_lower = mb_strtolower($content);
        foreach ($opinion_markers as $marker) {
            if (mb_strpos($content_lower, $marker) !== false) {
                $score -= 10;
                break;
            }
        }
        
        return max(0, min(100, $score));
    }

    public function detect_geo_blocks($content) {
        $blocks = [
            'tldr' => false,
            'howto' => false,
            'howto_steps' => 0,
            'definition' => 0,
            'faq' => 0,
            'proscons' => false,
            'stats' => 0,
            'author' => false,
            'blockquote' => 0,
        ];

        if (preg_match('/data-geo-tldr="true"|class="[^"]*geo-tldr[^"]*"/i', $content)) {
            $blocks['tldr'] = true;
        }

        if (preg_match('/data-geo-howto="true"|class="[^"]*geo-howto[^"]*"/i', $content)) {
            $blocks['howto'] = true;
            preg_match_all('/class="[^"]*geo-howto-step[^"]*"/i', $content, $steps);
            $blocks['howto_steps'] = count($steps[0]);
        }

        preg_match_all('/data-geo-definition="true"|class="[^"]*geo-definition[^"]*"/i', $content, $defs);
        $blocks['definition'] = count($defs[0]);

        preg_match_all('/class="[^"]*geo-faq[^"]*"|data-geo-faq="true"/i', $content, $faqs);
        $blocks['faq'] = count($faqs[0]);

        if (preg_match('/data-geo-proscons="true"|class="[^"]*geo-proscons[^"]*"/i', $content)) {
            $blocks['proscons'] = true;
        }

        preg_match_all('/data-geo-stats="true"|class="[^"]*geo-stats[^"]*"/i', $content, $stats);
        $blocks['stats'] = count($stats[0]);

        if (preg_match('/data-geo-author="true"|class="[^"]*geo-author[^"]*"/i', $content)) {
            $blocks['author'] = true;
        }

        preg_match_all('/class="[^"]*geo-blockquote[^"]*"/i', $content, $quotes);
        $blocks['blockquote'] = count($quotes[0]);

        return $blocks;
    }

    public function calculate_geo_blocks_bonus($content) {
        $blocks = $this->detect_geo_blocks($content);
        $bonus = 0;

        if ($blocks['tldr']) {
            $bonus += 8;
        }

        if ($blocks['howto']) {
            $bonus += 10;
            if ($blocks['howto_steps'] >= 5) {
                $bonus += 5;
            }
        }

        if ($blocks['definition'] > 0) {
            $bonus += min(8, $blocks['definition'] * 3);
        }

        if ($blocks['faq'] > 0) {
            $bonus += 10;
        }

        if ($blocks['proscons']) {
            $bonus += 8;
        }

        if ($blocks['stats'] > 0) {
            $bonus += min(6, $blocks['stats'] * 2);
        }

        if ($blocks['author']) {
            $bonus += 6;
        }

        if ($blocks['blockquote'] > 0) {
            $bonus += min(6, $blocks['blockquote'] * 2);
        }

        return $bonus;
    }

    public function get_geo_blocks_summary($content) {
        $blocks = $this->detect_geo_blocks($content);
        $summary = [];

        if ($blocks['tldr']) {
            $summary[] = 'TL;DR ✓';
        }
        if ($blocks['howto']) {
            $summary[] = 'How-To (' . $blocks['howto_steps'] . ' étapes) ✓';
        }
        if ($blocks['definition'] > 0) {
            $summary[] = 'Définitions (' . $blocks['definition'] . ') ✓';
        }
        if ($blocks['faq'] > 0) {
            $summary[] = 'FAQ ✓';
        }
        if ($blocks['proscons']) {
            $summary[] = 'Pros/Cons ✓';
        }
        if ($blocks['stats'] > 0) {
            $summary[] = 'Stats (' . $blocks['stats'] . ') ✓';
        }
        if ($blocks['author']) {
            $summary[] = 'Author Box ✓';
        }
        if ($blocks['blockquote'] > 0) {
            $summary[] = 'Citations (' . $blocks['blockquote'] . ') ✓';
        }

        return $summary;
    }
}
