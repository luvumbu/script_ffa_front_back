<?php
/**
 * app/Services/SeoService.php — Generation SEO dynamique
 *
 * Extrait de index.php lignes 175-359
 */

class SeoService
{
    private static $canonBase = 'https://bokonzi.com';

    /**
     * Construit les donnees SEO pour une page
     *
     * @return array ['title', 'desc', 'canonical', 'noIndex', 'breadcrumbs', 'profNom']
     */
    public static function build($page, $params = [], $conn = null)
    {
        $seoTitle = 'Bokonzi — Base de données Athlétisme français';
        $seoDesc = 'Bokonzi — Base de données complète d\'athlétisme français : athlètes, clubs, épreuves, records, classements.';
        $seoNoIndex = false;
        $profNom = '';

        // Titre et description selon la page
        if ($page === 'clubs') {
            $open = $params['open'] ?? '';
            if ($open) {
                $seoTitle = htmlspecialchars($open) . ' — Club Athlétisme | Bokonzi';
                $seoDesc = 'Fiche du club ' . $open . ' : athlètes, épreuves, records, nationalités, statistiques détaillées sur Bokonzi.';
            } else {
                $seoTitle = 'Clubs d\'athlétisme — Bokonzi';
                $seoDesc = 'Liste complète des clubs d\'athlétisme français : effectifs, niveaux, statistiques détaillées.';
            }
        } elseif ($page === 'epreuves') {
            $nom = $params['nom'] ?? '';
            if ($nom) {
                $seoTitle = htmlspecialchars($nom) . ' — Épreuve Athlétisme | Bokonzi';
                $seoDesc = 'Statistiques de l\'épreuve ' . $nom . ' : classement, records, performances, athlètes sur Bokonzi.';
            } else {
                $seoTitle = 'Épreuves d\'athlétisme — Bokonzi';
                $seoDesc = 'Toutes les épreuves d\'athlétisme : sprint, demi-fond, fond, sauts, lancers, épreuves combinées.';
            }
        } elseif ($page === 'villes') {
            $nom = $params['open'] ?? ($params['nom'] ?? '');
            if ($nom) {
                $seoTitle = htmlspecialchars($nom) . ' — Ville Athlétisme | Bokonzi';
                $seoDesc = 'Athlétisme à ' . $nom . ' : athlètes, compétitions, records, clubs sur Bokonzi.';
            } else {
                $seoTitle = 'Villes — Athlétisme France | Bokonzi';
                $seoDesc = 'Toutes les villes d\'athlétisme en France : compétitions, clubs, athlètes par ville.';
            }
        } elseif ($page === 'recherche') {
            $parts = [];
            if (!empty($params['club'])) $parts[] = htmlspecialchars($params['club']);
            if (!empty($params['nom'])) $parts[] = htmlspecialchars($params['nom']);
            if (!empty($params['epreuve'])) $parts[] = htmlspecialchars($params['epreuve']);
            if (!empty($params['nationalite'])) $parts[] = strtoupper($params['nationalite']);
            if (!empty($params['sexe'])) $parts[] = ($params['sexe'] === 'M' ? 'Hommes' : 'Femmes');
            if (!empty($params['categorie'])) $parts[] = htmlspecialchars($params['categorie']);
            $seoTitle = !empty($parts) ? implode(' · ', $parts) . ' — Bokonzi' : 'Recherche athlètes — Bokonzi';
            $seoDesc = 'Recherche avancée d\'athlètes : filtres par épreuve, club, ville, performance, catégorie.';
        } elseif ($page === 'athletes') {
            $seoTitle = 'Tous les athlètes — Bokonzi';
            $seoDesc = 'Liste complète des athlètes français d\'athlétisme avec records, clubs et statistiques.';
        } elseif ($page === 'profil' && !empty($params['id'])) {
            $id = intval($params['id']);
            if ($conn) {
                $res = $conn->query("SELECT nom_complet_athlete, categorie_athlete, sexe_athlete FROM athletes WHERE athlete_id_externe = $id LIMIT 1");
                if ($res && $row = $res->fetch_assoc()) {
                    $profNom = $row['nom_complet_athlete'];
                    $seoTitle = htmlspecialchars($profNom) . ' — Athlète | Bokonzi';
                    $seoDesc = 'Fiche de ' . $profNom . ' (' . $row['sexe_athlete'] . ', ' . $row['categorie_athlete'] . ') : records, progressions, résultats, clubs, médailles sur Bokonzi.';
                } else {
                    $seoTitle = 'Profil athlète — Bokonzi';
                    $seoDesc = 'Fiche complète de l\'athlète : records, progressions, résultats, clubs, médailles.';
                    $seoNoIndex = true;
                }
            }
        } elseif ($page === 'comparer') {
            $seoTitle = 'Comparateur athlètes & clubs — Bokonzi';
            $seoDesc = 'Comparez visuellement les performances d\'athlètes et clubs d\'athlétisme avec graphiques interactifs.';
            $seoNoIndex = true;
        } elseif ($page === 'tuto') {
            $seoTitle = 'Tutoriel — Comment utiliser Bokonzi';
            $seoDesc = 'Guide interactif étape par étape pour explorer les données d\'athlétisme sur Bokonzi.';
            $seoNoIndex = true;
        } elseif ($page === 'accueil') {
            $seoTitle = 'Bokonzi — Base de données Athlétisme français';
            $seoDesc = 'Statistiques globales, top athlètes, top clubs, répartitions par catégorie et nationalité.';
        }

        // URL canonique
        $seoCanonical = self::buildCanonical($page, $params);

        // Breadcrumbs
        $breadcrumbs = self::buildBreadcrumbs($page, $params, $profNom);

        return [
            'title'       => $seoTitle,
            'desc'        => $seoDesc,
            'canonical'   => $seoCanonical,
            'noIndex'     => $seoNoIndex,
            'breadcrumbs' => $breadcrumbs,
            'profNom'     => $profNom,
        ];
    }

    private static function buildCanonical($page, $params)
    {
        $base = self::$canonBase;
        if ($page === 'accueil') return $base . '/';
        if ($page === 'profil' && !empty($params['id'])) {
            return $base . '/index.php?page=profil&id=' . intval($params['id']);
        }
        $url = $base . '/index.php?page=' . urlencode($page);
        if ($page === 'clubs' && !empty($params['open'])) $url .= '&open=' . urlencode($params['open']);
        if ($page === 'epreuves' && !empty($params['nom'])) $url .= '&nom=' . urlencode($params['nom']);
        if ($page === 'villes' && !empty($params['open'])) $url .= '&open=' . urlencode($params['open']);
        return $url;
    }

    private static function buildBreadcrumbs($page, $params, $profNom)
    {
        $base = self::$canonBase;
        $items = [['name' => 'Accueil', 'url' => $base . '/']];

        if ($page === 'athletes') {
            $items[] = ['name' => 'Athlètes'];
        } elseif ($page === 'recherche') {
            $items[] = ['name' => 'Recherche'];
        } elseif ($page === 'clubs') {
            $items[] = ['name' => 'Clubs', 'url' => $base . '/index.php?page=clubs'];
            if (!empty($params['open'])) $items[] = ['name' => htmlspecialchars($params['open'])];
        } elseif ($page === 'epreuves') {
            $items[] = ['name' => 'Épreuves', 'url' => $base . '/index.php?page=epreuves'];
            if (!empty($params['nom'])) $items[] = ['name' => htmlspecialchars($params['nom'])];
        } elseif ($page === 'villes') {
            $items[] = ['name' => 'Villes', 'url' => $base . '/index.php?page=villes'];
            if (!empty($params['open'])) $items[] = ['name' => htmlspecialchars($params['open'])];
        } elseif ($page === 'profil' && !empty($params['id'])) {
            $items[] = ['name' => 'Athlètes', 'url' => $base . '/index.php?page=athletes'];
            $items[] = ['name' => $profNom ? htmlspecialchars($profNom) : 'Profil athlète'];
        }

        if (count($items) <= 1) return null;

        $list = [];
        foreach ($items as $pos => $bci) {
            $item = ['@type' => 'ListItem', 'position' => $pos + 1, 'name' => $bci['name']];
            if (isset($bci['url'])) $item['item'] = $bci['url'];
            $list[] = $item;
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $list,
        ];
    }

    public static function getCanonBase()
    {
        return self::$canonBase;
    }
}
