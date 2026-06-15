<?php
/**
 * pages/club.php — Espace Club (B2B, réservé à l'offre Platine)
 *
 * Tableau de bord d'un club : effectif complet (licenciés), niveaux, KPIs,
 * et export CSV. Réservé aux abonnés Platine (rang 4) + super admin.
 *
 * URL : pages/club.php?club=ES%20MASSY   ou   ?id=123
 */

require_once __DIR__ . '/../core/paths.php';
require_once __DIR__ . '/../core/db.php';            // $conn
require_once __DIR__ . '/../core/auth.php';          // getCurrentUser()
require_once __DIR__ . '/../core/subscription.php';  // getUserPlanRank(), getUserPlan(), bkTestRole()
require_once __DIR__ . '/../core/ip_logger.php';

// ── Endpoint d'autocomplétion (JSON) : ?ac=club|epreuve&q=… ──────────────
if (isset($_GET['ac'])) {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim($_GET['q'] ?? '');
    $out = [];
    if (mb_strlen($q) >= 2) {
        $escQ = $conn->real_escape_string($q);
        if ($_GET['ac'] === 'club') {
            $r = $conn->query("SELECT nom_club FROM clubs
                               WHERE nom_club LIKE '%$escQ%'
                               ORDER BY (nom_club LIKE '$escQ%') DESC, CHAR_LENGTH(nom_club) ASC LIMIT 10");
            if ($r) while ($row = $r->fetch_assoc()) $out[] = rtrim($row['nom_club'], '* ');
        } elseif ($_GET['ac'] === 'epreuve') {
            $r = $conn->query("SELECT nom_epreuve FROM epreuves
                               WHERE nom_epreuve LIKE '%$escQ%'
                               ORDER BY (nom_epreuve LIKE '$escQ%') DESC, CHAR_LENGTH(nom_epreuve) ASC LIMIT 10");
            if ($r) while ($row = $r->fetch_assoc()) $out[] = $row['nom_epreuve'];
        }
    }
    echo json_encode(array_values(array_unique($out)), JSON_UNESCAPED_UNICODE);
    exit;
}

logIp();

$BASE_API = BK_URL('/api');
function clubApiCall($url) {
    $ctx = stream_context_create(['http' => ['timeout' => 12]]);
    $json = @file_get_contents($url, false, $ctx);
    return $json ? json_decode($json, true) : null;
}

// ── Niveau : ordre + style (autonome) ────────────────────────────────────
function _clubNivOrder($c) {
    // Hiérarchie FFA : International > National > Inter-Régional (IR) > Régional (R) > Départemental (D)
    $o = ['IA'=>1,'IB'=>2,'IC'=>3,'IE'=>4,'IN'=>5,'I'=>6,
          'N1'=>10,'N2'=>11,'N3'=>12,'N4'=>13,
          'IR'=>14,'IR1'=>15,'IR2'=>16,'IR3'=>17,'IR4'=>18,'IR5'=>19,
          'R1'=>20,'R2'=>21,'R3'=>22,'R4'=>23,'R5'=>24,'R6'=>25,
          'D1'=>30,'D2'=>31,'D3'=>32,'D4'=>33,'D5'=>34,'D6'=>35,'D7'=>36,'D8'=>37];
    return $o[$c] ?? 99;
}
function _clubNivStyle($c) {
    if (strpos($c, 'IR') === 0) return 'background:#6366f120;border:1px solid #6366f140;color:#a5b4fc;'; // Inter-Régional
    $f = $c[0] ?? '';
    if ($f === 'I') return 'background:#c026d320;border:1px solid #c026d340;color:#e879f9;'; // International
    if ($f === 'N') return 'background:#e11d4820;border:1px solid #e11d4840;color:#fb7185;'; // National
    if ($f === 'R') return 'background:#0891b220;border:1px solid #0891b240;color:#22d3ee;'; // Régional
    if ($f === 'D') return 'background:#f9731620;border:1px solid #f9731640;color:#fb923c;'; // Départemental
    return 'background:#30363d;border:1px solid #6e7681;color:#c9d1d9;';
}

// ── Résolution du club ───────────────────────────────────────────────────
$clubName = trim($_GET['club'] ?? '');
$clubId   = (int)($_GET['id'] ?? 0);
if ($clubName === '' && $clubId > 0) {
    $r = $conn->query("SELECT nom_club FROM clubs WHERE id_club = " . $clubId . " LIMIT 1");
    if ($r && $row = $r->fetch_assoc()) $clubName = rtrim($row['nom_club'] ?? '', '* ');
}
$clubName = rtrim($clubName, '* ');

// Recherche avancée : filtrer l'effectif par épreuve (« qui a participé au 100m ? »)
// + seuil de performance optionnel (« … en moins de 11.50 »)
$epFilter   = trim($_GET['epreuve'] ?? '');
$perfFilter = trim($_GET['perf'] ?? '');

/** Convertit une perf saisie (11.50, 1:55.30, 6.20…) en entier comparable (centièmes / cm). */
function _clubPerfToInt($s) {
    $s = trim(str_replace(',', '.', (string)$s));
    if ($s === '') return 0;
    if (strpos($s, ':') !== false) { // formats m:ss(.cc) ou h:mm:ss(.cc)
        $p = explode(':', $s);
        $cc = 0; $sec = array_pop($p);
        if (strpos($sec, '.') !== false) { list($sec, $frac) = explode('.', $sec, 2); $cc = (int)str_pad(substr($frac, 0, 2), 2, '0'); }
        $tot = (int)$sec;
        if (!empty($p)) $tot += ((int)array_pop($p)) * 60;     // minutes
        if (!empty($p)) $tot += ((int)array_pop($p)) * 3600;   // heures
        return $tot * 100 + $cc;
    }
    require_once __DIR__ . '/../Class/AthleteScraper.php';
    return (int) AthleteScraper::performanceToInt($s);
}

// ── Contrôle d'accès : Platine (rang 4) ou super admin / mode test ───────
$tr   = function_exists('bkTestRole') ? bkTestRole() : '';
$user = getCurrentUser($conn);
$uid  = (int)($user['id_user'] ?? 0);
// Clé maître admin (param ?bk_key=... ou header) : déverrouille tout, prime sur le mode test.
$apiKeyOk = (($_GET['bk_key'] ?? '') === 'bk_s3cr3t_2026_xK9mP')
         || (($_SERVER['HTTP_X_BK_KEY'] ?? '') === 'bk_s3cr3t_2026_xK9mP');
if ($apiKeyOk) {
    $hasAccess = true;
} elseif ($tr !== '') {
    $hasAccess = ($tr === 'platine');           // le mode test prime
} else {
    $hasAccess = !empty($_COOKIE['bk_sa_token']) || ($uid && getUserPlanRank($conn, $uid) >= 4);
}
$tarifs = BK_URL('/tarifs');

// ── Données ──────────────────────────────────────────────────────────────
$stats = null;
$roster = [];
if ($clubName !== '') {
    $stats = clubApiCall($BASE_API . '/club_stats.php?nom=' . urlencode($clubName));

    if ($hasAccess) {
        // Effectif COMPLET (requête directe → pas de rate limit, tous les licenciés)
        $esc = $conn->real_escape_string($clubName);

        // Recherche avancée : ne garder que les participants à une épreuve
        $epIds = [];
        $epNamesById = [];
        if ($epFilter !== '') {
            $escEp = $conn->real_escape_string($epFilter);
            $eq = $conn->query("SELECT id_epreuve, nom_epreuve FROM epreuves
                                WHERE nom_epreuve LIKE '%$escEp%'
                                ORDER BY CHAR_LENGTH(nom_epreuve) ASC LIMIT 40");
            if ($eq) while ($e = $eq->fetch_assoc()) { $epIds[] = (int)$e['id_epreuve']; $epNamesById[(int)$e['id_epreuve']] = $e['nom_epreuve']; }
        }
        $epClause = '';
        $thrInt = 0; $perfOp = '<='; $isDistFilter = false;
        if ($epFilter !== '') {
            if (!empty($epIds)) {
                $epInList = implode(',', $epIds);

                // Seuil de perf : ≤ pour les courses (chrono), ≥ pour les concours (distance/points)
                if ($perfFilter !== '') {
                    $thrInt = _clubPerfToInt($perfFilter);
                    foreach ($epNamesById as $enm) {
                        if (preg_match('/poids|disque|javelot|marteau|hauteur|perche|longueur|triple|pentathlon|heptathlon|d[eé]cathlon/i', $enm)) { $isDistFilter = true; break; }
                    }
                    $perfOp = $isDistFilter ? '>=' : '<=';
                }

                if ($thrInt > 0) {
                    $epClause = " AND a.id_athlete IN (
                                    SELECT id_athlete FROM athlete_records      WHERE id_epreuve IN ($epInList) AND performance_record > 0      AND performance_record $perfOp $thrInt
                                    UNION SELECT id_athlete FROM athlete_resultats     WHERE id_epreuve IN ($epInList) AND performance_resultat > 0    AND performance_resultat $perfOp $thrInt
                                    UNION SELECT id_athlete FROM athlete_progressions  WHERE id_epreuve IN ($epInList) AND performance_progression > 0 AND performance_progression $perfOp $thrInt
                                  )";
                } else {
                    $epClause = " AND a.id_athlete IN (
                                    SELECT id_athlete FROM athlete_records  WHERE id_epreuve IN ($epInList)
                                    UNION
                                    SELECT id_athlete FROM athlete_resultats WHERE id_epreuve IN ($epInList)
                                  )";
                }
            } else {
                $epClause = " AND 1=0"; // épreuve introuvable → aucun résultat
            }
        }

        $sql = "SELECT a.id_athlete, a.athlete_id_externe, a.nom_complet_athlete,
                       a.categorie_athlete, a.sexe_athlete, a.nationalite_athlete,
                       a.annee_naissance_athlete,
                       MIN(ac.annee_debut) AS y_debut, MAX(ac.annee_fin) AS y_fin
                FROM athletes a
                JOIN athlete_clubs ac ON ac.id_athlete = a.id_athlete
                JOIN clubs c ON c.id_club = ac.id_club
                WHERE c.nom_club = '$esc' AND a.visible = 1 $epClause
                GROUP BY a.id_athlete
                ORDER BY a.nom_complet_athlete ASC
                LIMIT 10000";
        $res = $conn->query($sql);
        if ($res) while ($row = $res->fetch_assoc()) $roster[$row['id_athlete']] = $row;

        // Niveaux : on croise DEUX sources (qualifs formelles + niveaux des résultats),
        // comme la fiche athlète — sinon un N2 vu seulement dans les résultats n'apparaît pas.
        if (!empty($roster)) {
            $ids = implode(',', array_map('intval', array_keys($roster)));
            $bestMeta   = []; // aid => ['order','src'(niv|res),'niv_id','ep_id']
            $curNivYear = []; // aid => année la plus récente
            $curMeta    = []; // aid => détails du NIVEAU ACTUEL (perf/épreuve sur laquelle il a été obtenu)

            $apply = function ($aid, $code, $annee, $src, $nivId, $epId, $perf) use (&$roster, &$bestMeta, &$curNivYear, &$curMeta) {
                if ($code === '' || !isset($roster[$aid])) return;
                $o = _clubNivOrder($code);
                if (!isset($bestMeta[$aid]) || $o < $bestMeta[$aid]['order']) {
                    $roster[$aid]['best_niv'] = $code;
                    $bestMeta[$aid] = ['order' => $o, 'src' => $src, 'niv_id' => $nivId, 'ep_id' => $epId];
                }
                $prevY = $curNivYear[$aid] ?? -1;
                if ($annee > $prevY || ($annee === $prevY && $o < _clubNivOrder($roster[$aid]['cur_niv'] ?? 'ZZ'))) {
                    $curNivYear[$aid] = $annee;
                    $roster[$aid]['cur_niv'] = $code;
                    $curMeta[$aid] = ['src' => $src, 'niv_id' => $nivId, 'ep_id' => $epId, 'perf' => $perf];
                }
            };

            // Source 1 : qualifications (athlete_niveaux)
            $nr = $conn->query("SELECT id_niveau, id_athlete, code_niveau, annee_niveau FROM athlete_niveaux
                                WHERE id_athlete IN ($ids) AND code_niveau IS NOT NULL AND code_niveau != ''");
            if ($nr) while ($n = $nr->fetch_assoc()) {
                $apply((int)$n['id_athlete'], $n['code_niveau'], (int)$n['annee_niveau'], 'niv', (int)$n['id_niveau'], 0, '');
            }
            // Source 2 : niveau des résultats (athlete_resultats) — porte l'épreuve ET la perf
            $rn = $conn->query("SELECT id_athlete, niveau_resultat, annee_resultat, id_epreuve, performance_brut_resultat FROM athlete_resultats
                                WHERE id_athlete IN ($ids) AND niveau_resultat IS NOT NULL AND niveau_resultat != ''");
            if ($rn) while ($n = $rn->fetch_assoc()) {
                $apply((int)$n['id_athlete'], $n['niveau_resultat'], (int)$n['annee_resultat'], 'res', 0, (int)$n['id_epreuve'], $n['performance_brut_resultat'] ?? '');
            }

            // Perf + épreuve du NIVEAU ACTUEL (la perf sur laquelle ce 2e niveau a été obtenu)
            $curNivByNivId = []; // id_niveau => [aid]
            $curEpToName   = []; // id_epreuve => true (source résultat)
            foreach ($curMeta as $aid => $m) {
                if ($m['src'] === 'res') {
                    if ($m['perf'] !== '') $roster[$aid]['cur_niv_perf'] = $m['perf'];
                    if (!empty($m['ep_id'])) $curEpToName[$m['ep_id']] = true;
                } elseif ($m['src'] === 'niv' && $m['niv_id']) {
                    $curNivByNivId[$m['niv_id']][] = $aid;
                }
            }
            // source qualif : perf + épreuve via athlete_niv_perfs
            if (!empty($curNivByNivId)) {
                $cnIds = implode(',', array_map('intval', array_keys($curNivByNivId)));
                $cnData = [];
                $cq = $conn->query("SELECT np.id_niveau, np.performance_brut_niveau_perf, e.nom_epreuve
                                    FROM athlete_niv_perfs np JOIN epreuves e ON e.id_epreuve = np.id_epreuve
                                    WHERE np.id_niveau IN ($cnIds) AND e.nom_epreuve != ''");
                if ($cq) while ($r = $cq->fetch_assoc()) {
                    $nid = (int)$r['id_niveau'];
                    if (!isset($cnData[$nid])) $cnData[$nid] = ['ep' => $r['nom_epreuve'], 'perf' => $r['performance_brut_niveau_perf']];
                }
                foreach ($curNivByNivId as $nid => $aids) {
                    if (isset($cnData[$nid])) foreach ($aids as $aid) {
                        $roster[$aid]['cur_niv_ep']   = $cnData[$nid]['ep'];
                        if (($cnData[$nid]['perf'] ?? '') !== '') $roster[$aid]['cur_niv_perf'] = $cnData[$nid]['perf'];
                    }
                }
            }
            // source résultat : nom de l'épreuve
            if (!empty($curEpToName)) {
                $ceIds = implode(',', array_map('intval', array_keys($curEpToName)));
                $ceNames = [];
                $ce = $conn->query("SELECT id_epreuve, nom_epreuve FROM epreuves WHERE id_epreuve IN ($ceIds)");
                if ($ce) while ($e = $ce->fetch_assoc()) $ceNames[(int)$e['id_epreuve']] = $e['nom_epreuve'];
                foreach ($curMeta as $aid => $m) {
                    if ($m['src'] === 'res' && !empty($m['ep_id']) && !isset($roster[$aid]['cur_niv_ep']) && isset($ceNames[$m['ep_id']])) {
                        $roster[$aid]['cur_niv_ep'] = $ceNames[$m['ep_id']];
                    }
                }
            }

            if ($epFilter === '' || empty($epIds)) {
                // ── Mode normal : Spécialité = épreuve du MEILLEUR niveau (selon sa source) ──
                $nivToResolve = []; // id_niveau => [aid,...]
                $epToName     = []; // id_epreuve => true
                foreach ($bestMeta as $aid => $m) {
                    if ($m['src'] === 'res' && $m['ep_id']) {
                        $roster[$aid]['best_ep_id'] = $m['ep_id'];
                        $epToName[$m['ep_id']] = true;
                    } elseif ($m['src'] === 'niv' && $m['niv_id']) {
                        $nivToResolve[$m['niv_id']][] = $aid;
                    }
                }
                if (!empty($nivToResolve)) {
                    $nivIds = implode(',', array_map('intval', array_keys($nivToResolve)));
                    $er = $conn->query("SELECT np.id_niveau, np.id_epreuve, e.nom_epreuve
                                        FROM athlete_niv_perfs np JOIN epreuves e ON e.id_epreuve = np.id_epreuve
                                        WHERE np.id_niveau IN ($nivIds) AND e.nom_epreuve IS NOT NULL AND e.nom_epreuve != ''");
                    if ($er) while ($r = $er->fetch_assoc()) {
                        $nid = (int)$r['id_niveau'];
                        if (isset($nivToResolve[$nid])) foreach ($nivToResolve[$nid] as $aid) {
                            if (!isset($roster[$aid]['best_ep'])) {
                                $roster[$aid]['best_ep']    = $r['nom_epreuve'];
                                $roster[$aid]['best_ep_id'] = (int)$r['id_epreuve'];
                            }
                        }
                    }
                }
                if (!empty($epToName)) {
                    $epNameIds = implode(',', array_map('intval', array_keys($epToName)));
                    $enr = $conn->query("SELECT id_epreuve, nom_epreuve FROM epreuves WHERE id_epreuve IN ($epNameIds)");
                    $epNames = [];
                    if ($enr) while ($e = $enr->fetch_assoc()) $epNames[(int)$e['id_epreuve']] = $e['nom_epreuve'];
                    foreach ($bestMeta as $aid => $m) {
                        if ($m['src'] === 'res' && $m['ep_id'] && !isset($roster[$aid]['best_ep']) && isset($epNames[$m['ep_id']])) {
                            $roster[$aid]['best_ep'] = $epNames[$m['ep_id']];
                        }
                    }
                }

                // Fallback : niveau sans épreuve liée → épreuve PRINCIPALE (la plus pratiquée)
                // pour remplir spécialité + perfs même sans qualif détaillée.
                $noEp = [];
                foreach ($roster as $aid => $a) { if (empty($a['best_ep_id'])) $noEp[] = (int)$aid; }
                if (!empty($noEp)) {
                    $noEpIds = implode(',', $noEp);
                    $picked = [];
                    $fq = $conn->query("SELECT id_athlete, id_epreuve, COUNT(*) AS c
                                        FROM athlete_resultats
                                        WHERE id_athlete IN ($noEpIds) AND id_epreuve IS NOT NULL
                                        GROUP BY id_athlete, id_epreuve
                                        ORDER BY id_athlete, c DESC");
                    if ($fq) while ($r = $fq->fetch_assoc()) {
                        $aid = (int)$r['id_athlete'];
                        if (!isset($picked[$aid])) $picked[$aid] = (int)$r['id_epreuve'];
                    }
                    $still = [];
                    foreach ($noEp as $aid) if (!isset($picked[$aid])) $still[] = $aid;
                    if (!empty($still)) {
                        $stillIds = implode(',', $still);
                        $rq = $conn->query("SELECT id_athlete, id_epreuve FROM athlete_records WHERE id_athlete IN ($stillIds) AND id_epreuve IS NOT NULL");
                        if ($rq) while ($r = $rq->fetch_assoc()) { $aid = (int)$r['id_athlete']; if (!isset($picked[$aid])) $picked[$aid] = (int)$r['id_epreuve']; }
                    }
                    if (!empty($picked)) {
                        $pIds = implode(',', array_unique(array_map('intval', array_values($picked))));
                        $pNames = [];
                        $en = $conn->query("SELECT id_epreuve, nom_epreuve FROM epreuves WHERE id_epreuve IN ($pIds)");
                        if ($en) while ($e = $en->fetch_assoc()) $pNames[(int)$e['id_epreuve']] = $e['nom_epreuve'];
                        foreach ($picked as $aid => $eid) {
                            $roster[$aid]['best_ep_id'] = $eid;
                            if (!isset($roster[$aid]['best_ep'])) $roster[$aid]['best_ep'] = $pNames[$eid] ?? '';
                        }
                    }
                }
            } else {
                // ── Mode recherche épreuve : Spécialité + perfs = l'épreuve recherchée ──
                $epInList  = implode(',', array_map('intval', $epIds));
                $rosterIds = implode(',', array_map('intval', array_keys($roster)));
                if ($epInList !== '' && $rosterIds !== '') {
                    $pq = $conn->query("SELECT id_athlete, id_epreuve FROM athlete_records  WHERE id_athlete IN ($rosterIds) AND id_epreuve IN ($epInList)
                                        UNION
                                        SELECT id_athlete, id_epreuve FROM athlete_resultats WHERE id_athlete IN ($rosterIds) AND id_epreuve IN ($epInList)");
                    if ($pq) while ($p = $pq->fetch_assoc()) {
                        $aid = (int)$p['id_athlete']; $eid = (int)$p['id_epreuve'];
                        if (isset($roster[$aid]) && !isset($roster[$aid]['best_ep_id'])) {
                            $roster[$aid]['best_ep_id'] = $eid;
                            $roster[$aid]['best_ep']    = $epNamesById[$eid] ?? '';
                        }
                    }
                }
            }

            // Meilleure perf (record perso) + perf actuelle (dernier résultat) DANS la spécialité
            $pairs = [];
            foreach ($roster as $aid => $a) {
                if (!empty($a['best_ep_id'])) $pairs[] = '(' . (int)$aid . ',' . (int)$a['best_ep_id'] . ')';
            }
            if (!empty($pairs)) {
                $inPairs = implode(',', $pairs);
                // Meilleure perf = la MEILLEURE perf (record OU progression) dans la spécialité,
                // en tenant compte du sens : temps = plus petit meilleur, distance/points = plus grand meilleur.
                $bestPerfInt = []; // aid => meilleur perf_int retenu
                $rr = $conn->query("SELECT id_athlete, performance_record AS pi, performance_brut_record AS brut
                                    FROM athlete_records
                                    WHERE (id_athlete, id_epreuve) IN ($inPairs) AND performance_record IS NOT NULL AND performance_record > 0 AND performance_brut_record != ''
                                    UNION ALL
                                    SELECT id_athlete, performance_progression AS pi, performance_brut_progression AS brut
                                    FROM athlete_progressions
                                    WHERE (id_athlete, id_epreuve) IN ($inPairs) AND performance_progression IS NOT NULL AND performance_progression > 0 AND performance_brut_progression != ''");
                if ($rr) while ($r = $rr->fetch_assoc()) {
                    $aid = (int)$r['id_athlete'];
                    if (!isset($roster[$aid])) continue;
                    $pi = (int)$r['pi'];
                    $epName = $roster[$aid]['best_ep'] ?? '';
                    $isDist = preg_match('/poids|disque|javelot|marteau|hauteur|perche|longueur|triple|pentathlon|heptathlon|d[eé]cathlon/i', $epName);
                    if (!isset($bestPerfInt[$aid])) {
                        $bestPerfInt[$aid] = $pi;
                        $roster[$aid]['best_perf'] = $r['brut'];
                    } else {
                        $better = $isDist ? ($pi > $bestPerfInt[$aid]) : ($pi < $bestPerfInt[$aid]);
                        if ($better) { $bestPerfInt[$aid] = $pi; $roster[$aid]['best_perf'] = $r['brut']; }
                    }
                }
                // Perf actuelle = perf la plus récente dans la spécialité (résultats OU progressions)
                $cr = $conn->query("SELECT id_athlete, annee, perf FROM (
                                        SELECT id_athlete, annee_resultat AS annee, performance_brut_resultat AS perf, date_resultat AS d
                                        FROM athlete_resultats
                                        WHERE (id_athlete, id_epreuve) IN ($inPairs) AND performance_brut_resultat != ''
                                        UNION ALL
                                        SELECT id_athlete, annee_progression AS annee, performance_brut_progression AS perf, date_progression AS d
                                        FROM athlete_progressions
                                        WHERE (id_athlete, id_epreuve) IN ($inPairs) AND performance_brut_progression != ''
                                    ) u
                                    ORDER BY annee DESC, d DESC");
                if ($cr) while ($r = $cr->fetch_assoc()) {
                    $aid = (int)$r['id_athlete'];
                    if (isset($roster[$aid]) && !isset($roster[$aid]['cur_perf']) && ($r['perf'] ?? '') !== '') {
                        $roster[$aid]['cur_perf'] = $r['perf'];
                        $roster[$aid]['cur_year'] = (int)$r['annee'];
                    }
                }
            }
        }
        $roster = array_values($roster);
        // Tri : meilleur niveau d'abord (les plus forts en haut), puis nom
        usort($roster, function ($a, $b) {
            $oa = isset($a['best_niv']) ? _clubNivOrder($a['best_niv']) : 999;
            $ob = isset($b['best_niv']) ? _clubNivOrder($b['best_niv']) : 999;
            if ($oa !== $ob) return $oa - $ob;
            return strcmp($a['nom_complet_athlete'] ?? '', $b['nom_complet_athlete'] ?? '');
        });

        // Année de référence = dernière saison connue du club → sert au statut actif/inactif
        $currentYear = (int)date('Y');
        $refYear = 0;
        foreach ($roster as $a) { $f = (int)($a['y_fin'] ?? 0); if ($f > $refYear) $refYear = $f; }
        if ($refYear <= 0) $refYear = $currentYear;

        // Compte actifs / anciens
        $nbActive = 0; $nbInactive = 0;
        foreach ($roster as $a) {
            $yf = (int)($a['y_fin'] ?? 0);
            if ($yf <= 0 || $yf >= $refYear) $nbActive++; else $nbInactive++;
        }
    }
}
if (!isset($refYear))   $refYear   = (int)date('Y');
if (!isset($nbActive))  $nbActive  = 0;
if (!isset($nbInactive)) $nbInactive = 0;

$total  = $stats['total_athletes'] ?? 0;
$parS   = $stats['par_sexe'] ?? [];
$med    = $stats['medailles'] ?? [];
$nbMed  = is_array($med) ? array_sum(array_map('intval', $med)) : 0;
$nbRec  = $stats['total_records'] ?? 0;
$nbPod  = $stats['total_podiums'] ?? 0;
$nbEp   = $stats['total_epreuves'] ?? 0;
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex,nofollow">
<title><?= $clubName !== '' ? htmlspecialchars($clubName) . ' — ' : '' ?>Espace Club — Bokonzi</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0;}
  body{background:#0d1117;color:#c9d1d9;font-family:'Segoe UI',system-ui,Arial,sans-serif;padding:0 0 60px;}
  a{color:#a29bfe;text-decoration:none;}
  .wrap{width:95%;max-width:1800px;margin:0 auto;padding:0 16px;}
  .topbar{background:#111830;border-bottom:1px solid #1a2540;padding:14px 0;margin-bottom:24px;}
  .topbar .wrap{display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;}
  .brand{font-weight:800;font-size:18px;color:#fff;}
  .badge-pro{background:linear-gradient(135deg,#6c5ce7,#a855f7);color:#fff;font-size:11px;font-weight:800;padding:3px 10px;border-radius:100px;letter-spacing:.5px;}
  h1{color:#fff;font-size:26px;margin:0 0 4px;}
  .sub{color:#8b949e;font-size:14px;margin-bottom:22px;}
  .kpis{display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:24px;}
  .kpi{background:#161b22;border:1px solid #30363d;border-radius:12px;padding:18px;}
  .kpi .n{font-size:30px;font-weight:800;color:#fff;}
  .kpi .l{font-size:12px;color:#8b949e;text-transform:uppercase;letter-spacing:.5px;margin-top:2px;}
  .card{background:#161b22;border:1px solid #30363d;border-radius:14px;padding:20px 22px;margin-bottom:20px;}
  .card h2{color:#f0f6fc;font-size:18px;margin:0 0 14px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap;}
  table{width:100%;border-collapse:collapse;font-size:13px;}
  th,td{text-align:left;padding:9px 10px;border-bottom:1px solid #21262d;}
  th{color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:.5px;}
  .nivb{display:inline-block;padding:2px 9px;border-radius:6px;font-size:11px;font-weight:700;}
  .btn{background:#21262d;border:1px solid #30363d;color:#c9d1d9;border-radius:8px;padding:8px 14px;font-size:13px;font-weight:700;cursor:pointer;}
  .btn:hover{border-color:#58a6ff;color:#58a6ff;}
  .btn-pri{background:linear-gradient(135deg,#6c5ce7,#ec4899);color:#fff;border:none;}
  .search{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:24px;}
  .search input{flex:1;min-width:220px;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;border-radius:8px;padding:11px 14px;font-size:14px;}
  .lock{background:#161b22;border:1px solid #6c5ce755;border-radius:14px;padding:40px 28px;text-align:center;}
  .lock .ico{font-size:44px;}
  .muted{color:#6e7681;}
</style>
</head>
<body>
  <div class="topbar"><div class="wrap">
    <span class="brand">&#127967;&#65039; BOKONZI <span class="badge-pro">Espace Club</span></span>
    <a href="<?= BK_URL('/') ?>">&larr; Retour au site</a>
  </div></div>

  <div class="wrap">
    <!-- Sélecteur de club -->
    <form class="search" method="get" action="club.php">
      <?php if (!empty($_GET['bk_key'])): ?><input type="hidden" name="bk_key" value="<?= htmlspecialchars($_GET['bk_key']) ?>"><?php endif; ?>
      <input type="text" id="clubInput" name="club" autocomplete="off" placeholder="Nom du club (ex : ES MASSY)" value="<?= htmlspecialchars($clubName) ?>">
      <button class="btn btn-pri" type="submit">Ouvrir l'espace club</button>
    </form>

    <?php if ($clubName === ''): ?>
      <div class="card">
        <h2>&#128075; Bienvenue dans l'Espace Club</h2>
        <p class="sub" style="margin:0;">Saisis le nom d'un club ci-dessus pour accéder à son tableau de bord : effectif complet, niveaux, médailles et export.</p>
      </div>

    <?php elseif (!$stats || empty($stats['success'])): ?>
      <div class="card"><h2>Club introuvable</h2><p class="muted">Aucun club nommé « <?= htmlspecialchars($clubName) ?> ».</p></div>

    <?php else: ?>
      <h1>&#127967;&#65039; <?= htmlspecialchars($stats['club']['nom_club'] ?? $clubName) ?></h1>
      <p class="sub">Tableau de bord du club — effectif, niveaux et palmarès.</p>

      <!-- KPIs (visibles pour tous, teaser de valeur) -->
      <div class="kpis">
        <div class="kpi"><div class="n"><?= (int)$total ?></div><div class="l">Licenciés</div></div>
        <div class="kpi"><div class="n"><?= (int)$nbRec ?></div><div class="l">Records</div></div>
        <div class="kpi"><div class="n"><?= (int)$nbMed ?></div><div class="l">Médailles</div></div>
        <div class="kpi"><div class="n"><?= (int)$nbPod ?></div><div class="l">Podiums</div></div>
        <div class="kpi"><div class="n"><?= (int)$nbEp ?></div><div class="l">Épreuves</div></div>
      </div>

      <?php if (!$hasAccess): ?>
        <!-- Accès réservé Platine -->
        <div class="lock">
          <div class="ico">&#128274;</div>
          <h2 style="color:#fff;border:none;justify-content:center;margin:12px 0 8px;">Effectif réservé à l'offre Platine</h2>
          <p class="muted" style="max-width:480px;margin:0 auto 18px;">Débloque l'<b>effectif complet</b> de ce club (<?= (int)$total ?> licenciés), leurs niveaux, leur palmarès et l'<b>export CSV</b> — l'outil pensé pour les clubs &amp; structures.</p>
          <a class="btn btn-pri" style="display:inline-block;padding:12px 26px;" href="<?= htmlspecialchars($tarifs) ?>">Passer à Platine &rarr;</a>
        </div>

      <?php else: ?>
        <!-- Effectif complet -->
        <div class="card">
          <h2>
            <span><?php if ($epFilter !== ''): ?>&#127939; Participants à « <?= htmlspecialchars($epFilter) ?> »<?php if ($perfFilter !== '' && $thrInt > 0): ?> <?= $isDistFilter ? 'au-delà de' : 'en moins de' ?> <b><?= htmlspecialchars($perfFilter) ?></b><?php endif; ?> (<?= count($roster) ?>)<?php else: ?>&#128101; Tous les licenciés depuis le début (<?= count($roster) ?><?= count($roster) >= 10000 ? '+' : '' ?>) &middot; triés par meilleur niveau<?php endif; ?></span>
            <span style="display:flex;gap:8px;">
              <button class="btn" onclick="clubExportCSV()">&#11015;&#65039; Export CSV</button>
              <button class="btn" onclick="window.print()">&#128424;&#65039; Imprimer</button>
            </span>
          </h2>

          <!-- Recherche avancée par épreuve -->
          <form method="get" action="club.php" style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:12px;">
            <input type="hidden" name="club" value="<?= htmlspecialchars($clubName) ?>">
            <?php if (!empty($_GET['bk_key'])): ?><input type="hidden" name="bk_key" value="<?= htmlspecialchars($_GET['bk_key']) ?>"><?php endif; ?>
            <input type="text" id="epInput" name="epreuve" autocomplete="off" value="<?= htmlspecialchars($epFilter) ?>" placeholder="Qui a participé à… (ex : 100m, Longueur, 1500m)"
                   style="flex:1;min-width:200px;background:#0d1117;border:1px solid #6c5ce755;color:#c9d1d9;border-radius:8px;padding:10px 14px;font-size:14px;">
            <input type="text" name="perf" value="<?= htmlspecialchars($perfFilter) ?>" placeholder="Perf seuil (ex : 11.50, 1:55, 6.20)"
                   title="Courses : chrono en dessous duquel. Concours : distance au-delà de laquelle."
                   style="width:200px;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;border-radius:8px;padding:10px 14px;font-size:14px;">
            <button class="btn btn-pri" type="submit">&#128269; Rechercher</button>
            <?php if ($epFilter !== ''): ?>
              <a class="btn" style="display:inline-flex;align-items:center;" href="club.php?club=<?= urlencode($clubName) ?><?= !empty($_GET['bk_key']) ? '&bk_key=' . urlencode($_GET['bk_key']) : '' ?>">&#10005; Tout l'effectif</a>
            <?php endif; ?>
          </form>

          <input type="text" id="rosterFilter" placeholder="Filtrer par nom…" onkeyup="clubFilter(this.value)"
                 style="width:100%;background:#0d1117;border:1px solid #30363d;color:#c9d1d9;border-radius:8px;padding:10px 14px;font-size:14px;margin-bottom:10px;">
          <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:12px;font-size:13px;">
            <span style="padding:6px 12px;border-radius:8px;background:#34d39920;color:#34d399;font-weight:800;">&#9679; Actifs en <?= (int)$refYear ?> : <?= (int)$nbActive ?></span>
            <span style="padding:6px 12px;border-radius:8px;background:#fb718520;color:#fb7185;font-weight:800;">&#9679; Anciens : <?= (int)$nbInactive ?></span>
          </div>
          <div style="overflow-x:auto;">
          <table id="rosterTable">
            <thead><tr>
              <?php foreach (['#','Athlète','Cat.','Sexe','Nat.','Né(e)','Meilleur niv.','Niveau actuel','Perf niv. actuel','Spécialité','Meilleure perf','Perf actuelle','Période','Statut'] as $_cn): ?>
                <th onclick="clubSort(this)" style="cursor:pointer;user-select:none;white-space:nowrap;"><?= htmlspecialchars($_cn) ?><span class="sort-ind" style="color:#58a6ff;font-size:11px;"></span></th>
              <?php endforeach; ?>
            </tr></thead>
            <tbody>
            <?php if (empty($roster)): ?>
              <tr><td colspan="14" class="muted" style="padding:24px;text-align:center;">Aucun licencié trouvé pour ce club.</td></tr>
            <?php else: $i = 0; foreach ($roster as $a): $i++;
              $niv    = $a['best_niv'] ?? '';
              $nivCur = $a['cur_niv'] ?? '';
              $curNivPerf = $a['cur_niv_perf'] ?? '';
              $curNivEp   = $a['cur_niv_ep'] ?? '';
              $ep     = $a['best_ep'] ?? '';
              $bestPerf = $a['best_perf'] ?? '';
              $curPerf  = $a['cur_perf'] ?? '';
              $curYear  = (int)($a['cur_year'] ?? 0);
              $key = strtolower($a['nom_complet_athlete'] ?? '');
              $yd  = (int)($a['y_debut'] ?? 0);
              $yf  = (int)($a['y_fin'] ?? 0);
              $active  = ($yf <= 0) || ($yf >= $refYear);
              $col     = $active ? '#34d399' : '#fb7185';
              $effFin  = $yf > 0 ? $yf : $currentYear;
              $nbYears = ($yd > 0 && $effFin >= $yd) ? ($effFin - $yd + 1) : 0;
              $periode = ($yd > 0 ? $yd : '?') . ' – ' . ($yf > 0 ? $yf : 'présent');
              if ($nbYears > 0) $periode .= ' (' . $nbYears . ' an' . ($nbYears > 1 ? 's' : '') . ')';
            ?>
              <tr data-search="<?= htmlspecialchars($key) ?>" style="border-left:3px solid <?= $col ?>;">
                <td class="muted"><?= $i ?></td>
                <td><a href="<?= BK_URL('/index.php') ?>?page=profil&id=<?= (int)$a['athlete_id_externe'] ?>" target="_blank"><?= htmlspecialchars($a['nom_complet_athlete']) ?></a></td>
                <td><?= htmlspecialchars($a['categorie_athlete'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['sexe_athlete'] ?? '') ?></td>
                <td><?= htmlspecialchars($a['nationalite_athlete'] ?? '') ?></td>
                <td class="muted"><?= $a['annee_naissance_athlete'] ? (int)$a['annee_naissance_athlete'] : '—' ?></td>
                <td data-sort="<?= $niv ? _clubNivOrder($niv) : 999 ?>"><?php if ($niv): ?><span class="nivb" style="<?= _clubNivStyle($niv) ?>"><?= htmlspecialchars($niv) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td data-sort="<?= $nivCur ? _clubNivOrder($nivCur) : 999 ?>"><?php if ($nivCur): ?><span class="nivb" style="<?= _clubNivStyle($nivCur) ?>"><?= htmlspecialchars($nivCur) ?></span><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td><?php if ($curNivPerf !== ''): ?><b><?= htmlspecialchars($curNivPerf) ?></b><?php if ($curNivEp !== ''): ?> <span class="muted" style="font-size:11px;"><?= htmlspecialchars($curNivEp) ?></span><?php endif; ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td><?= $ep !== '' ? htmlspecialchars($ep) : '<span class="muted">—</span>' ?></td>
                <td><?= $bestPerf !== '' ? '<b>' . htmlspecialchars($bestPerf) . '</b>' : '<span class="muted">—</span>' ?></td>
                <td><?php if ($curPerf !== ''): ?><?= htmlspecialchars($curPerf) ?><?php if ($curYear > 0): ?> <span class="muted" style="font-size:11px;">(<?= $curYear ?>)</span><?php endif; ?><?php else: ?><span class="muted">—</span><?php endif; ?></td>
                <td class="muted"><?= htmlspecialchars($periode) ?></td>
                <td data-sort="<?= $active ? 0 : 1 ?>"><span style="display:inline-block;padding:2px 9px;border-radius:6px;font-size:11px;font-weight:700;background:<?= $col ?>20;color:<?= $col ?>;"><?= $active ? 'Actif' : 'Ancien' ?></span></td>
              </tr>
            <?php endforeach; endif; ?>
            </tbody>
          </table>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

<script>
function clubFilter(q){
    q = (q||'').toLowerCase().trim();
    document.querySelectorAll('#rosterTable tbody tr').forEach(function(tr){
        var k = tr.getAttribute('data-search') || '';
        tr.style.display = (!q || k.indexOf(q) !== -1) ? '' : 'none';
    });
}
function clubSort(th){
    var table = document.getElementById('rosterTable');
    var tbody = table.tBodies[0];
    var head  = th.parentNode;
    var idx   = Array.prototype.indexOf.call(head.children, th);
    var asc   = th.getAttribute('data-asc') !== 'true';
    // Réinitialise les indicateurs des autres colonnes
    Array.prototype.forEach.call(head.children, function(h){
        h.removeAttribute('data-asc');
        var s = h.querySelector('.sort-ind'); if (s) s.textContent = '';
    });
    th.setAttribute('data-asc', asc ? 'true' : 'false');
    var ind = th.querySelector('.sort-ind'); if (ind) ind.textContent = asc ? ' ▲' : ' ▼';

    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr')).filter(function(r){ return r.children.length > 1; });
    function val(row){
        var td = row.children[idx];
        if (!td) return '';
        var ds = td.getAttribute('data-sort');
        if (ds !== null && ds !== '') return ds;
        return (td.textContent || '').trim();
    }
    function num(v){ var m = String(v).replace(',', '.').match(/-?\d+(\.\d+)?/); return m ? parseFloat(m[0]) : null; }
    rows.sort(function(a, b){
        var va = val(a), vb = val(b), na = num(va), nb = num(vb), c;
        if (na !== null && nb !== null) c = na - nb;
        else c = String(va).localeCompare(String(vb), 'fr', { numeric: true });
        return asc ? c : -c;
    });
    rows.forEach(function(r){ tbody.appendChild(r); });
}
function clubExportCSV(){
    var rows = [['#','Athlete','Categorie','Sexe','Nationalite','Naissance','Meilleur niveau','Niveau actuel','Perf niv. actuel','Specialite','Meilleure perf','Perf actuelle','Periode','Statut']];
    document.querySelectorAll('#rosterTable tbody tr').forEach(function(tr){
        if (tr.style.display === 'none') return;
        var c = tr.querySelectorAll('td');
        if (!c.length) return;
        var line = [];
        c.forEach(function(td){ line.push('"' + (td.textContent || '').trim().replace(/"/g,'""') + '"'); });
        rows.push(line);
    });
    var csv = rows.map(function(r){ return r.join(','); }).join('\n');
    var blob = new Blob(["﻿" + csv], { type: 'text/csv;charset=utf-8;' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'effectif-club.csv';
    a.click();
}

// ── Autocomplétion des champs (club / épreuve) ──
function bkAttachAC(input, type){
    var box = document.createElement('div');
    box.style.cssText = 'position:fixed;z-index:99999;background:#161b22;border:1px solid #30363d;border-radius:8px;max-height:260px;overflow:auto;display:none;box-shadow:0 8px 26px rgba(0,0,0,.45);';
    document.body.appendChild(box);
    var timer, items = [], active = -1;
    function place(){ var r = input.getBoundingClientRect(); box.style.left = r.left+'px'; box.style.top = (r.bottom+3)+'px'; box.style.minWidth = r.width+'px'; }
    function hide(){ box.style.display = 'none'; active = -1; }
    function paint(){ Array.prototype.forEach.call(box.children, function(c,i){ c.style.background = (i===active) ? '#1f6feb33' : ''; }); }
    function choose(name){ input.value = name; hide(); if (input.form) input.form.submit(); }
    function render(){
        if (!items.length){ hide(); return; }
        box.innerHTML = '';
        items.forEach(function(name,i){
            var d = document.createElement('div');
            d.textContent = name;
            d.style.cssText = 'padding:9px 13px;cursor:pointer;font-size:14px;color:#c9d1d9;white-space:nowrap;';
            d.onmouseenter = function(){ active = i; paint(); };
            d.onmousedown  = function(e){ e.preventDefault(); choose(name); };
            box.appendChild(d);
        });
        place(); box.style.display = 'block';
    }
    input.addEventListener('input', function(){
        clearTimeout(timer);
        var q = input.value.trim();
        if (q.length < 2){ hide(); return; }
        timer = setTimeout(function(){
            fetch('club.php?ac=' + type + '&q=' + encodeURIComponent(q))
                .then(function(r){ return r.json(); })
                .then(function(list){ items = list || []; active = -1; render(); })
                .catch(function(){ hide(); });
        }, 200);
    });
    input.addEventListener('keydown', function(e){
        if (box.style.display === 'none') return;
        if (e.key === 'ArrowDown'){ active = Math.min(active+1, items.length-1); paint(); e.preventDefault(); }
        else if (e.key === 'ArrowUp'){ active = Math.max(active-1, 0); paint(); e.preventDefault(); }
        else if (e.key === 'Enter' && active >= 0){ choose(items[active]); e.preventDefault(); }
        else if (e.key === 'Escape'){ hide(); }
    });
    input.addEventListener('blur', function(){ setTimeout(hide, 150); });
    window.addEventListener('scroll', function(){ if (box.style.display !== 'none') place(); }, true);
}
document.addEventListener('DOMContentLoaded', function(){
    var ci = document.getElementById('clubInput');   if (ci) bkAttachAC(ci, 'club');
    var ei = document.getElementById('epInput');     if (ei) bkAttachAC(ei, 'epreuve');
});
</script>
</body>
</html>
