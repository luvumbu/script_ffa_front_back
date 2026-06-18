<?php
/**
 * core/articles_feed.php — Moteur du feed « Fil BOKONZI » (partagé API + page)
 *
 * Classe les articles par PERTINENCE pour le visiteur : on lit ses dernières
 * recherches dans `search_tracking` (ce qu'il a cherché : villes, clubs,
 * épreuves) et on remonte en priorité les articles qui matchent (FULLTEXT).
 * Sans historique → repli sur les plus vus / récents.
 */

/** Construit la chaîne d'intérêts du visiteur depuis ses recherches récentes. */
function bkArticlesInterests($conn, $ip) {
    $terms = [];
    $ip = trim((string)$ip);
    if ($ip !== '') {
        $ipEsc = $conn->real_escape_string($ip);
        $res = $conn->query(
            "SELECT entity_name, query_text FROM search_tracking
             WHERE ip = '$ipEsc' ORDER BY created_at DESC LIMIT 40"
        );
        if ($res) {
            while ($r = $res->fetch_assoc()) {
                foreach ([$r['entity_name'], $r['query_text']] as $t) {
                    $t = trim((string)$t);
                    if ($t !== '' && mb_strlen($t) >= 3) $terms[] = $t;
                }
            }
        }
    }
    $terms = array_slice(array_values(array_unique($terms)), 0, 20);
    $str = mb_strtolower(implode(' ', $terms));
    // FULLTEXT NATURAL LANGUAGE : on retire les opérateurs booléens / caractères spéciaux
    $str = preg_replace('/[+\-><\(\)~*"@]+/', ' ', $str);
    return trim(preg_replace('/\s+/', ' ', $str));
}

/**
 * Récupère une page du feed.
 * @return array { personalized:bool, interests:string, page:int, has_more:bool, items:[] }
 */
function bkFetchArticles($conn, $page, $limit, $ip) {
    $page   = max(1, (int)$page);
    $limit  = max(1, min(30, (int)$limit));
    $offset = ($page - 1) * $limit;
    $fetch  = $limit + 1; // +1 pour savoir s'il reste des articles après

    $interests    = bkArticlesInterests($conn, $ip);
    $personalized = ($interests !== '');

    if ($personalized) {
        $q   = $conn->real_escape_string($interests);
        $sql = "SELECT id_article,type,title,slug,excerpt,cover,views,
                       MATCH(title,excerpt,tags) AGAINST('$q' IN NATURAL LANGUAGE MODE) AS rel
                FROM articles WHERE status='published'
                ORDER BY rel DESC, views DESC, id_article DESC
                LIMIT $fetch OFFSET $offset";
    } else {
        $sql = "SELECT id_article,type,title,slug,excerpt,cover,views, 0 AS rel
                FROM articles WHERE status='published'
                ORDER BY views DESC, created_at DESC, id_article DESC
                LIMIT $fetch OFFSET $offset";
    }

    $rows = [];
    if ($res = $conn->query($sql)) {
        while ($r = $res->fetch_assoc()) {
            $rows[] = [
                'id'      => (int)$r['id_article'],
                'type'    => $r['type'],
                'title'   => $r['title'],
                'slug'    => $r['slug'],
                'excerpt' => $r['excerpt'],
                'cover'   => json_decode($r['cover'] ?: '{}', true) ?: [],
                'views'   => (int)$r['views'],
            ];
        }
    }
    $hasMore = count($rows) > $limit;
    if ($hasMore) array_pop($rows);

    return [
        'personalized' => $personalized,
        'interests'    => $interests,
        'page'         => $page,
        'has_more'     => $hasMore,
        'items'        => $rows,
    ];
}

/** IP réelle du visiteur (CloudFlare / proxy / direct). */
function bkArticlesIp() {
    $ip = $_SERVER['HTTP_CF_CONNECTING_IP'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    return trim(explode(',', $ip)[0]);
}
