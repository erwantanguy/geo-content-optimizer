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

    /**
     * Détecte si le contenu touche à un sujet YMYL (Your Money Your Life).
     *
     * @param string $content
     * @param string $title
     * @return array ['score' => int, 'categories' => string[]]
     */
    public function detect_ymyl_topic($content, $title = '') {
        $text = mb_strtolower($content . ' ' . $title, 'UTF-8');

        $categories = [
            'health' => [
                'keywords' => ['santé', 'maladie', 'symptôme', 'traitement', 'médicament', 'docteur', 'médecin', 'hôpital', 'clinique', 'diagnostic', 'thérapie', 'vaccin', 'nutrition', 'régime', 'bien-être', 'mental', 'psychologie', 'dépression', 'anxiété', 'cancer', 'diabète', 'allergie', 'douleur', 'grossesse', 'bébé', 'enfant malade'],
                'label' => 'Santé',
            ],
            'finance' => [
                'keywords' => ['argent', 'investissement', 'banque', 'crédit', 'prêt', 'assurance', 'impôt', 'fiscalité', 'retraite', 'bourse', 'crypto', 'bitcoin', 'epargne', 'compte bancaire', 'hypothèque', 'immobilier financier', 'placements', 'revenus', 'dettes'],
                'label' => 'Finance',
            ],
            'legal' => [
                'keywords' => ['avocat', 'notaire', 'juge', 'tribunal', 'justice', 'loi', 'règlement', 'droit', 'contrat', 'procès', 'infraction', 'pénal', 'civil', 'légal', 'juridique', 'succession', 'divorce', 'custodie', 'plainte'],
                'label' => 'Juridique',
            ],
            'safety' => [
                'keywords' => ['sécurité', 'danger', 'risque', 'accident', 'protection', 'prévention', 'incendie', 'secours', 'urgence', 'sauvetage', 'equipement de protection', 'ehpad', 'handicap', 'accessibilité', 'sécurité routière'],
                'label' => 'Sécurité',
            ],
        ];

        $detected = [];
        $score = 0;

        foreach ($categories as $key => $category) {
            $matches = 0;
            foreach ($category['keywords'] as $keyword) {
                if (mb_strpos($text, $keyword) !== false) {
                    $matches++;
                }
            }

            if ($matches >= 2) {
                $detected[] = $category['label'];
                $score += 30;
            } elseif ($matches === 1) {
                $score += 10;
            }
        }

        return [
            'score' => min(100, $score),
            'categories' => $detected,
            'is_ymyl' => $score >= 30,
        ];
    }

    /**
     * Évalue le score EEAT (Experience, Expertise, Authoritativeness, Trustworthiness).
     *
     * @param string $content
     * @param string $title
     * @return int
     */
    public function calculate_eeat($content, $title = '') {
        $score = 40;
        $text = mb_strtolower($content, 'UTF-8');
        $first_part = mb_substr($text, 0, 600);

        // Réponse directe en début de contenu
        if ($this->has_direct_answer($content)) {
            $score += 15;
        }

        // Présence d'auteur / expertise
        $author_signals = [
            'auteur', 'rédigé par', 'écrit par', 'par ', 'expert', 'spécialiste',
            'docteur', 'dr ', 'médecin', 'avocat', 'notaire', 'ingénieur',
            'certifié', 'diplômé', 'expérience de ', 'depuis ', 'années d\'expérience',
        ];
        $has_author = false;
        foreach ($author_signals as $signal) {
            if (mb_strpos($text, $signal) !== false) {
                $has_author = true;
                break;
            }
        }
        if ($has_author) {
            $score += 15;
        }

        // Sources externes / citations
        if (preg_match('/https?:\/\//', $content)) {
            $score += 10;
        }
        if (preg_match('/(selon|d\'après|source|étude|rapport|recherche)/i', $content)) {
            $score += 10;
        }

        // Fraîcheur / date de mise à jour
        if (preg_match('/(mis à jour|actualisé|publié le|dernière mise à jour|updated|reviewed)/i', $content)) {
            $score += 10;
        }
        if (preg_match('/\b20[1-9][0-9]\b/', $content)) {
            $score += 5;
        }

        // Avis d'expert / témoignage
        $expert_signals = [
            'témoignage', 'avis d\'expert', 'expert recommande', 'selon les experts',
            'étude clinique', 'preuve', 'démontré', 'validé', 'certifié',
        ];
        foreach ($expert_signals as $signal) {
            if (mb_strpos($text, $signal) !== false) {
                $score += 5;
                break;
            }
        }

        // Contact / transparence
        $trust_signals = [
            'contact', 'à propos', 'mentions légales', 'politique de confidentialité',
            'qui sommes-nous', 'notre équipe',
        ];
        foreach ($trust_signals as $signal) {
            if (mb_strpos($text, $signal) !== false) {
                $score += 5;
                break;
            }
        }

        return max(0, min(100, $score));
    }

    /**
     * Détecte si le contenu commence par une réponse directe.
     *
     * @param string $content
     * @return bool
     */
    public function has_direct_answer($content) {
        $text = wp_strip_all_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $first_part = mb_substr(mb_strtolower($text, 'UTF-8'), 0, 400);

        // Phrases indiquant une réponse directe
        $patterns = [
            '/^(oui|non|environ|généralement|c\'est|il s\'agit|il est)/u',
            '/^\w+\s+(est|sont|peut|peuvent|doit|doivent|vaut|valent|coûte|coûtent)/u',
            '/^la réponse est/u',
            '/^pour résumer/u',
            '/^en quelques mots/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $first_part)) {
                return true;
            }
        }

        // Première phrase courte et factuelle (moins de 30 mots, contient un chiffre ou une date)
        if (preg_match('/^[^.!?]{10,150}[.!?]/u', $first_part, $match)) {
            $first_sentence = $match[0];
            $word_count = str_word_count($first_sentence, 0, 'àâäéèêëïîôùûüÿçœæ');
            if ($word_count <= 30 && (preg_match('/\d/', $first_sentence) || preg_match('/\b20[1-9][0-9]\b/', $first_sentence))) {
                return true;
            }
        }

        return false;
    }
}
