<?php
if (!defined('ABSPATH')) {
    exit;
}

class GCO_Suggestions {

    public function generate($content, $title, $analysis) {
        $suggestions = [];
        
        $suggestions = array_merge($suggestions, $this->check_title($title));
        $suggestions = array_merge($suggestions, $this->check_citability($analysis));
        $suggestions = array_merge($suggestions, $this->check_clarity($analysis));
        $suggestions = array_merge($suggestions, $this->check_structure($content, $analysis));
        $suggestions = array_merge($suggestions, $this->check_factuality($content, $analysis));
        $suggestions = array_merge($suggestions, $this->check_weak_sentences($analysis));
        $suggestions = array_merge($suggestions, $this->check_eeat($content, $title, $analysis));
        
        usort($suggestions, function($a, $b) {
            $priority_order = ['high' => 0, 'medium' => 1, 'low' => 2];
            return ($priority_order[$a['priority']] ?? 1) - ($priority_order[$b['priority']] ?? 1);
        });
        
        return array_slice($suggestions, 0, 10);
    }

    private function check_title($title) {
        $suggestions = [];
        
        if (empty($title)) {
            $suggestions[] = [
                'type' => 'title',
                'priority' => 'high',
                'message' => __('Ajoutez un titre à votre contenu.', 'geo-content-optimizer'),
                'detail' => __('Un titre clair aide les IA à comprendre le sujet principal.', 'geo-content-optimizer'),
            ];
            return $suggestions;
        }
        
        $word_count = str_word_count($title, 0, 'àâäéèêëïîôùûüÿçœæ');
        
        if ($word_count < 3) {
            $suggestions[] = [
                'type' => 'title',
                'priority' => 'medium',
                'message' => __('Votre titre est trop court.', 'geo-content-optimizer'),
                'detail' => __('Un titre de 5 à 10 mots est idéal pour être cité par les IA.', 'geo-content-optimizer'),
            ];
        } elseif ($word_count > 15) {
            $suggestions[] = [
                'type' => 'title',
                'priority' => 'medium',
                'message' => __('Votre titre est trop long.', 'geo-content-optimizer'),
                'detail' => __('Un titre concis (5-10 mots) a plus de chances d\'être repris par les IA.', 'geo-content-optimizer'),
            ];
        }
        
        if (!preg_match('/\d/', $title) && !preg_match('/[A-Z][a-zàâäéèêëïîôùûüÿçœæ]{2,}/', substr($title, 1))) {
            $suggestions[] = [
                'type' => 'title',
                'priority' => 'low',
                'message' => __('Enrichissez votre titre avec des éléments factuels.', 'geo-content-optimizer'),
                'detail' => __('Inclure une date, un chiffre ou un nom propre renforce la citabilité.', 'geo-content-optimizer'),
            ];
        }
        
        return $suggestions;
    }

    private function check_citability($analysis) {
        $suggestions = [];
        $score = $analysis['citability'] ?? 0;
        
        if ($score < 50) {
            $suggestions[] = [
                'type' => 'citability',
                'priority' => 'high',
                'message' => __('Votre contenu manque de phrases citables.', 'geo-content-optimizer'),
                'detail' => __('Privilégiez des phrases courtes (15-25 mots), factuelles et précises que les IA peuvent facilement reprendre.', 'geo-content-optimizer'),
            ];
        } elseif ($score < 70) {
            $suggestions[] = [
                'type' => 'citability',
                'priority' => 'medium',
                'message' => __('Améliorez la citabilité de votre contenu.', 'geo-content-optimizer'),
                'detail' => __('Ajoutez des chiffres, des dates ou des faits vérifiables pour renforcer vos affirmations.', 'geo-content-optimizer'),
            ];
        }
        
        return $suggestions;
    }

    private function check_clarity($analysis) {
        $suggestions = [];
        $metrics = $analysis['metrics'] ?? [];
        $score = $analysis['clarity'] ?? 0;
        
        $avg_sentence = $metrics['avg_sentence_length'] ?? 0;
        
        if ($avg_sentence > 35) {
            $suggestions[] = [
                'type' => 'clarity',
                'priority' => 'high',
                'message' => __('Vos phrases sont trop longues.', 'geo-content-optimizer'),
                'detail' => sprintf(
                    __('Moyenne actuelle : %d mots/phrase. Visez 15-25 mots pour une meilleure citabilité.', 'geo-content-optimizer'),
                    round($avg_sentence)
                ),
            ];
        } elseif ($avg_sentence > 25) {
            $suggestions[] = [
                'type' => 'clarity',
                'priority' => 'medium',
                'message' => __('Raccourcissez certaines phrases.', 'geo-content-optimizer'),
                'detail' => __('Les phrases de 15 à 25 mots sont idéales pour être citées par les IA.', 'geo-content-optimizer'),
            ];
        }
        
        $avg_para = $metrics['avg_paragraph_length'] ?? 0;
        if ($avg_para > 8) {
            $suggestions[] = [
                'type' => 'clarity',
                'priority' => 'medium',
                'message' => __('Découpez vos paragraphes.', 'geo-content-optimizer'),
                'detail' => __('Des paragraphes de 3 à 5 phrases facilitent la lecture et l\'extraction par les IA.', 'geo-content-optimizer'),
            ];
        }
        
        return $suggestions;
    }

    private function check_structure($content, $analysis) {
        $suggestions = [];
        $score = $analysis['structure'] ?? 0;
        $metrics = $analysis['metrics'] ?? [];
        
        if ($metrics['total_paragraphs'] < 3 && $metrics['total_words'] > 200) {
            $suggestions[] = [
                'type' => 'structure',
                'priority' => 'high',
                'message' => __('Structurez votre contenu en paragraphes.', 'geo-content-optimizer'),
                'detail' => __('Un contenu bien structuré est plus facilement analysé et cité par les IA.', 'geo-content-optimizer'),
            ];
        }
        
        if (!preg_match('/^#+\s/m', $content) && !preg_match('/<h[2-6]/i', $content) && $metrics['total_words'] > 300) {
            $suggestions[] = [
                'type' => 'structure',
                'priority' => 'medium',
                'message' => __('Ajoutez des sous-titres.', 'geo-content-optimizer'),
                'detail' => __('Les sous-titres (H2, H3) aident les IA à comprendre la hiérarchie de votre contenu.', 'geo-content-optimizer'),
            ];
        }
        
        $has_list = preg_match('/^[\-\*•]\s/m', $content) || preg_match('/^\d+\.\s/m', $content);
        if (!$has_list && $metrics['total_words'] > 400) {
            $suggestions[] = [
                'type' => 'structure',
                'priority' => 'low',
                'message' => __('Utilisez des listes à puces.', 'geo-content-optimizer'),
                'detail' => __('Les listes sont facilement extraites et citées par les IA pour des réponses structurées.', 'geo-content-optimizer'),
            ];
        }
        
        return $suggestions;
    }

    private function check_factuality($content, $analysis) {
        $suggestions = [];
        $score = $analysis['factuality'] ?? 0;
        
        if ($score < 50) {
            $suggestions[] = [
                'type' => 'factuality',
                'priority' => 'high',
                'message' => __('Ajoutez des éléments factuels.', 'geo-content-optimizer'),
                'detail' => __('Incluez des dates, des chiffres, des statistiques ou des sources pour renforcer la crédibilité.', 'geo-content-optimizer'),
            ];
        }
        
        if (!preg_match('/\d{4}/', $content)) {
            $suggestions[] = [
                'type' => 'factuality',
                'priority' => 'low',
                'message' => __('Ajoutez des références temporelles.', 'geo-content-optimizer'),
                'detail' => __('Les dates et années permettent aux IA de contextualiser l\'information.', 'geo-content-optimizer'),
            ];
        }
        
        if (!preg_match('/(selon|d\'après|source)/i', $content) && $analysis['metrics']['total_words'] > 300) {
            $suggestions[] = [
                'type' => 'factuality',
                'priority' => 'medium',
                'message' => __('Citez vos sources.', 'geo-content-optimizer'),
                'detail' => __('Les affirmations sourcées sont plus susceptibles d\'être reprises par les IA.', 'geo-content-optimizer'),
            ];
        }
        
        return $suggestions;
    }

    private function check_weak_sentences($analysis) {
        $suggestions = [];
        $weak = $analysis['weak_sentences'] ?? [];
        
        if (count($weak) > 3) {
            $issues_summary = [];
            foreach ($weak as $w) {
                foreach ($w['issues'] ?? [] as $issue) {
                    $issues_summary[$issue] = ($issues_summary[$issue] ?? 0) + 1;
                }
            }
            
            if (!empty($issues_summary['too_long'])) {
                $suggestions[] = [
                    'type' => 'sentence',
                    'priority' => 'high',
                    'message' => sprintf(
                        __('%d phrases sont trop longues.', 'geo-content-optimizer'),
                        $issues_summary['too_long']
                    ),
                    'detail' => __('Découpez ces phrases en plusieurs phrases plus courtes et percutantes.', 'geo-content-optimizer'),
                ];
            }
            
            if (!empty($issues_summary['vague_language'])) {
                $suggestions[] = [
                    'type' => 'sentence',
                    'priority' => 'medium',
                    'message' => sprintf(
                        __('%d phrases contiennent un langage vague.', 'geo-content-optimizer'),
                        $issues_summary['vague_language']
                    ),
                    'detail' => __('Remplacez les termes vagues (très, beaucoup, plusieurs...) par des données précises.', 'geo-content-optimizer'),
                ];
            }
            
            if (!empty($issues_summary['passive_voice'])) {
                $suggestions[] = [
                    'type' => 'sentence',
                    'priority' => 'low',
                    'message' => __('Évitez la voix passive.', 'geo-content-optimizer'),
                    'detail' => __('La voix active rend vos phrases plus directes et citables.', 'geo-content-optimizer'),
                ];
            }
        }
        
        return $suggestions;
    }

    private function check_eeat($content, $title, $analysis) {
        $suggestions = [];
        $eeat = $analysis['eeat'] ?? 0;
        $ymyl = $analysis['ymyl'] ?? ['is_ymyl' => false, 'categories' => []];
        $metrics = $analysis['metrics'] ?? [];
        $text = mb_strtolower($content, 'UTF-8');

        // Alerte YMYL
        if (!empty($ymyl['is_ymyl'])) {
            $categories = implode(', ', $ymyl['categories']);
            $suggestions[] = [
                'type' => 'eeat',
                'priority' => 'high',
                'message' => sprintf(__('Contenu YMYL détecté (%s).', 'geo-content-optimizer'), $categories),
                'detail' => __('Ce sujet touche à l\'argent, la santé, la justice ou la sécurité. Renforcez impérativement l\'auteur, les sources et la date de mise à jour.', 'geo-content-optimizer'),
            ];
        }

        // Score EEAT faible
        if ($eeat < 40) {
            $suggestions[] = [
                'type' => 'eeat',
                'priority' => 'high',
                'message' => __('Score EEAT très faible.', 'geo-content-optimizer'),
                'detail' => __('Ajoutez un auteur identifié, une date de mise à jour, des sources crédibles et une réponse directe en début de contenu.', 'geo-content-optimizer'),
            ];
        } elseif ($eeat < 60) {
            $suggestions[] = [
                'type' => 'eeat',
                'priority' => 'medium',
                'message' => __('Améliorez les signaux EEAT.', 'geo-content-optimizer'),
                'detail' => __('Les IA et Google privilégient les contenus avec auteur, sources et fraîcheur clairement indiquées.', 'geo-content-optimizer'),
            ];
        }

        // Réponse directe manquante
        if (empty($analysis['has_direct_answer']) && !empty($ymyl['is_ymyl'])) {
            $suggestions[] = [
                'type' => 'eeat',
                'priority' => 'medium',
                'message' => __('Ajoutez une réponse directe en introduction.', 'geo-content-optimizer'),
                'detail' => __('Pour les sujets YMYL et les réponses IA, une réponse franche et concise dès le début augmente la confiance et la citabilité.', 'geo-content-optimizer'),
            ];
        }

        // Auteur manquant
        $author_signals = ['auteur', 'rédigé par', 'écrit par', 'par ', 'expert', 'spécialiste'];
        $has_author = false;
        foreach ($author_signals as $signal) {
            if (mb_strpos($text, $signal) !== false) {
                $has_author = true;
                break;
            }
        }
        if (!$has_author && $metrics['total_words'] > 300) {
            $priority = !empty($ymyl['is_ymyl']) ? 'high' : 'low';
            $suggestions[] = [
                'type' => 'eeat',
                'priority' => $priority,
                'message' => __('Identifiez l\'auteur ou l\'expert du contenu.', 'geo-content-optimizer'),
                'detail' => __('Un contenu signé renforce l\'Expertise et l\'Autorité, surtout pour les sujets sensibles.', 'geo-content-optimizer'),
            ];
        }

        // Sources manquantes
        if (!preg_match('/(selon|d\'après|source|étude|rapport|recherche)/i', $content) && $metrics['total_words'] > 300) {
            $priority = !empty($ymyl['is_ymyl']) ? 'high' : 'medium';
            $suggestions[] = [
                'type' => 'eeat',
                'priority' => $priority,
                'message' => __('Citez des sources crédibles.', 'geo-content-optimizer'),
                'detail' => __('Les affirmations sourcées améliorent la confiance et les chances d\'être cité par les IA.', 'geo-content-optimizer'),
            ];
        }

        // Fraîcheur non indiquée
        if (!preg_match('/(mis à jour|actualisé|publié le|dernière mise à jour|updated|reviewed)/i', $content) && $metrics['total_words'] > 300) {
            $priority = !empty($ymyl['is_ymyl']) ? 'medium' : 'low';
            $suggestions[] = [
                'type' => 'eeat',
                'priority' => $priority,
                'message' => __('Indiquez la date de mise à jour.', 'geo-content-optimizer'),
                'detail' => __('Google et les IA valorisent les contenus frais, particulièrement sur les sujets sensibles.', 'geo-content-optimizer'),
            ];
        }

        return $suggestions;
    }
}
