# GEO Content Optimizer

**Version :** 1.1.0  
**Compatibilité :** WordPress 6.0+, PHP 7.4+  
**Auteur :** Erwan Tanguy - Ticoët  
**Licence :** GPL2+

Plugin WordPress d'analyse et d'optimisation de contenu pour maximiser la citabilité par les IA (ChatGPT, Claude, Perplexity, etc.).

## Description

GEO Content Optimizer analyse votre contenu et fournit un score de citabilité ainsi que des suggestions concrètes pour améliorer vos chances d'être cité dans les réponses des moteurs de recherche génératifs.

## Fonctionnalités

### Score de citabilité
- **Score global** (0-100) avec note (A+ à F)
- **4 sous-scores** : Citabilité, Clarté, Structure, Factualité
- Pondération : Citabilité 35%, Clarté 25%, Structure 20%, Factualité 20%
- **Bonus blocs GEO** (nouveau v1.1) : Points supplémentaires pour les blocs GEO Blocks Suite

### Analyse locale (mode par défaut)
- Détection des phrases trop longues (>40 mots)
- Identification du langage vague (très, beaucoup, plusieurs...)
- Détection de la voix passive
- Repérage des formules de remplissage
- Analyse de la présence de données factuelles (dates, chiffres, noms propres)

### Analyse API (optionnelle)
- Support OpenAI (GPT-4) et Anthropic (Claude)
- Analyse sémantique avancée
- Suggestions contextuelles plus précises

### Détection des blocs GEO (nouveau v1.1)

Le plugin détecte automatiquement les blocs du plugin **GEO Blocks Suite** et ajoute un bonus au score :

| Bloc | Bonus |
|------|-------|
| TL;DR GEO | +8 |
| How-To GEO | +10 (+5 si ≥5 étapes) |
| Définition GEO | +3/définition (max +8) |
| FAQ GEO | +10 |
| Pros/Cons GEO | +8 |
| Stats GEO | +2/stat (max +6) |
| Author Box GEO | +6 |
| Blockquote GEO | +2/citation (max +6) |

### Interface
- **Metabox** dans l'éditeur avec score et suggestions rapides
- **Affichage des blocs GEO détectés** (nouveau v1.1)
- **Page Vue d'ensemble** avec tous les contenus et leurs scores
- **Page détaillée** par contenu avec meilleures phrases et phrases à améliorer
- **Paramètres** pour configurer le mode d'analyse et les types de contenu

## Installation

1. Téléchargez le plugin
2. Uploadez dans `/wp-content/plugins/geo-content-optimizer/`
3. Activez le plugin dans WordPress
4. Accédez à **Content Optimizer** dans le menu admin

## Utilisation

### Analyse manuelle
1. Éditez un article ou une page
2. Dans la metabox "GEO Content Optimizer", cliquez sur **Analyser maintenant**
3. Consultez le score et les suggestions
4. Vérifiez les blocs GEO détectés (nouveau v1.1)

### Analyse automatique
Par défaut, le plugin analyse automatiquement le contenu lors de la publication. Cette option peut être désactivée dans les paramètres.

### Vue d'ensemble
Accédez à **Content Optimizer > Vue d'ensemble** pour voir tous vos contenus avec leur score de citabilité.

## Critères d'analyse

### Citabilité (35%)
- Longueur des phrases (idéal : 15-25 mots)
- Présence de données chiffrées
- Citations et guillemets
- Listes structurées

### Clarté (25%)
- Moyenne de mots par phrase
- Moyenne de phrases par paragraphe
- Absence de langage vague

### Structure (20%)
- Nombre de paragraphes
- Présence de sous-titres (H2, H3...)
- Utilisation de mots de transition

### Factualité (20%)
- Dates et années
- Statistiques et pourcentages
- Noms propres et entités
- Mentions de sources

### Bonus blocs GEO (nouveau v1.1)
- Détection via classes CSS (`.geo-tldr`, `.geo-howto`, etc.)
- Détection via attributs data (`data-geo-tldr`, `data-geo-howto`, etc.)
- Ajout jusqu'à +60 points bonus au score de base

## Configuration API (optionnel)

Pour une analyse plus poussée :

1. Allez dans **Content Optimizer > Paramètres**
2. Sélectionnez **API IA (analyse avancée)**
3. Choisissez le fournisseur (OpenAI ou Anthropic)
4. Entrez votre clé API

## Types de contenu supportés

Par défaut : Articles et Pages

Configurable dans les paramètres pour inclure d'autres types de contenu (produits, portfolio, etc.).

## Compatibilité

- **GEO Authority Suite** : Les scores sont affichés dans llms.txt et ai-sitemap.xml
- **GEO Blocks Suite** : Détection automatique des blocs GEO (v1.1)
- **Yoast SEO** : Compatible
- **Rank Math** : Compatible

## Prérequis

- WordPress 6.0+
- PHP 7.4+

## Changelog

### 1.1.0
- Détection des blocs GEO Blocks Suite
- Bonus de points pour les blocs TL;DR, How-To, Définitions, FAQ, Pros/Cons, Stats, Author Box
- Affichage des blocs détectés dans la metabox
- Suggestion d'ajout de blocs GEO si absents

### 1.0.0
- Version initiale
- Analyse locale avec score de citabilité
- Support API OpenAI et Anthropic
- Metabox et page d'administration
- Suggestions d'amélioration

## Auteur

Erwan Tanguy - Ticoët  
Site web : [ticoet.fr](https://www.ticoet.fr/)

## Licence

GPL v2 ou ultérieure
