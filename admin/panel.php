<?php
/**
 * admin/panel.php — Dashboard Super Admin (version optimisee, ex panel2.php)
 *
 * L'ancien panel.php est conserve dans panel_old.php (sauvegarde).
 */
error_reporting(E_ALL);
ini_set('display_errors', 1);
require_once __DIR__ . '/../core/db.php';

// === AJAX endpoints : retourner JSON meme en cas d'erreur PHP ===
$_isAjax = isset($_GET['action']) && in_array($_GET['action'], ['niveaux','bareme_list','bareme_map_get','bareme_map_set','bareme_suggest','activity','preview_athletes'], true);
if ($_isAjax) {
    ini_set('display_errors', 0);
    ob_start();
    set_error_handler(function($errno, $errstr, $errfile, $errline) {
        if (!(error_reporting() & $errno)) return false;
        @ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'PHP: ' . $errstr, 'line' => $errline, 'file' => basename($errfile)]);
        exit;
    });
    set_exception_handler(function($e) {
        @ob_clean();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => false, 'error' => 'Exception: ' . $e->getMessage(), 'line' => $e->getLine()]);
        exit;
    });
    register_shutdown_function(function() {
        $err = error_get_last();
        if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            @ob_clean();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Fatal: ' . $err['message'], 'line' => $err['line']]);
        }
    });
}

// === VERIFIER SESSION SUPER ADMIN ===
function isSuperAdmin() {
    if (empty($_COOKIE['bk_sa_token'])) return false;
    $token = $_COOKIE['bk_sa_token'];
    $saFile = __DIR__ . '/../logs/.sa_sessions.php';
    if (!file_exists($saFile)) return false;
    $raw = file_get_contents($saFile);
    $pos = strpos($raw, "\n");
    if ($pos === false) return false;
    $sessions = json_decode(substr($raw, $pos + 1), true) ?: [];
    return isset($sessions[$token]) && ($sessions[$token]['expires'] ?? 0) > time();
}

// === FILTRES PAGE ATHLETES (public) ===
function getAthletesFilter() {
    $f = __DIR__ . '/../logs/.athletes_filter.php';
    $defaults = ['niveaux' => ['IA','IB'], 'annee' => (int)date('Y'), 'nb_hommes' => 50, 'nb_femmes' => 50, 'layout' => 'magazine', 'club_filter' => '', 'epreuve_filter' => '', 'filter_cible_enabled' => false, 'all_epreuves' => false];
    if (!file_exists($f)) return $defaults;
    $raw = file_get_contents($f);
    $pos = strpos($raw, "\n");
    if ($pos === false) return $defaults;
    $data = json_decode(substr($raw, $pos + 1), true) ?: [];
    $allowedLayouts = ['magazine','grid','list','flex'];
    return [
        'niveaux'              => is_array($data['niveaux'] ?? null) && !empty($data['niveaux']) ? $data['niveaux'] : $defaults['niveaux'],
        'annee'                => (int)($data['annee'] ?? $defaults['annee']),
        'nb_hommes'            => max(0, min(200, (int)($data['nb_hommes'] ?? 50))),
        'nb_femmes'            => max(0, min(200, (int)($data['nb_femmes'] ?? 50))),
        'layout'               => in_array(($data['layout'] ?? ''), $allowedLayouts, true) ? $data['layout'] : 'magazine',
        'club_filter'          => trim((string)($data['club_filter'] ?? '')),
        'epreuve_filter'       => trim((string)($data['epreuve_filter'] ?? '')),
        'filter_cible_enabled' => !empty($data['filter_cible_enabled']),
        'all_epreuves'         => !empty($data['all_epreuves']),
    ];
}
// Normalise un jeu de criteres en config canonique (sans ecrire). Reutilise par
// le filtre actif ET par les selections enregistrees (presets).
function normalizeAthletesConfig($niveaux, $annee, $nbH = 50, $nbF = 50, $layout = 'magazine', $clubFilter = '', $epreuveFilter = '', $filterCibleEnabled = false, $allEpreuves = false) {
    $allowedNiv = ['IA','IB','IE','IR','IR2','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    $cleanNiv = array_values(array_intersect((array)$niveaux, $allowedNiv));
    if (empty($cleanNiv)) $cleanNiv = ['IA','IB'];
    $year = (int)$annee;
    // 0 = toutes les annees (pas de filtre par annee)
    if ($year !== 0 && ($year < 2000 || $year > (int)date('Y') + 1)) $year = (int)date('Y');
    $nbH = max(0, min(200, (int)$nbH));
    $nbF = max(0, min(200, (int)$nbF));
    $allowedLayouts = ['magazine','grid','list','flex'];
    if (!in_array($layout, $allowedLayouts, true)) $layout = 'magazine';
    $normalizeMulti = function($s) {
        $s = trim((string)$s);
        if ($s === '') return '';
        $parts = array_map('trim', explode('|', $s));
        $parts = array_values(array_filter($parts, function($v) { return $v !== ''; }));
        // Dedupe (case-insensitive)
        $seen = [];
        $clean = [];
        foreach ($parts as $p) {
            $k = mb_strtolower($p);
            if (isset($seen[$k])) continue;
            $seen[$k] = true;
            $clean[] = $p;
        }
        if (count($clean) > 20) $clean = array_slice($clean, 0, 20);
        $out = implode('|', $clean);
        if (mb_strlen($out) > 1000) $out = mb_substr($out, 0, 1000);
        return $out;
    };
    return [
        'niveaux' => $cleanNiv,
        'annee' => $year,
        'nb_hommes' => $nbH,
        'nb_femmes' => $nbF,
        'layout' => $layout,
        'club_filter' => $normalizeMulti($clubFilter),
        'epreuve_filter' => $normalizeMulti($epreuveFilter),
        'filter_cible_enabled' => (bool)$filterCibleEnabled,
        'all_epreuves' => (bool)$allEpreuves,
    ];
}
// Ecrit une config canonique dans le filtre actif lu par /?page=athletes
function writeAthletesFilterConfig($config) {
    $data = $config;
    $data['updated_at'] = date('Y-m-d H:i:s');
    $f = __DIR__ . '/../logs/.athletes_filter.php';
    file_put_contents($f, "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE));
    return $data;
}
function setAthletesFilter($niveaux, $annee, $nbH = 50, $nbF = 50, $layout = 'magazine', $clubFilter = '', $epreuveFilter = '', $filterCibleEnabled = false, $allEpreuves = false) {
    return writeAthletesFilterConfig(
        normalizeAthletesConfig($niveaux, $annee, $nbH, $nbF, $layout, $clubFilter, $epreuveFilter, $filterCibleEnabled, $allEpreuves)
    );
}

// === SELECTIONS ENREGISTREES (presets) page Athletes ===
// Fichier logs/.athletes_presets.php : { active_id, presets:[{id,label,config,created_at}] }
function getAthletesPresets() {
    $f = __DIR__ . '/../logs/.athletes_presets.php';
    $out = ['active_id' => '', 'presets' => []];
    if (!file_exists($f)) return $out;
    $raw = file_get_contents($f);
    $pos = strpos($raw, "\n");
    if ($pos === false) return $out;
    $data = json_decode(substr($raw, $pos + 1), true) ?: [];
    $out['active_id'] = (string)($data['active_id'] ?? '');
    if (is_array($data['presets'] ?? null)) $out['presets'] = array_values($data['presets']);
    return $out;
}
function saveAthletesPresets($store) {
    $f = __DIR__ . '/../logs/.athletes_presets.php';
    $clean = ['active_id' => (string)($store['active_id'] ?? ''), 'presets' => array_values($store['presets'] ?? [])];
    file_put_contents($f, "<?php die(); ?>\n" . json_encode($clean, JSON_UNESCAPED_UNICODE));
    return $clean;
}
// Libelle lisible genere depuis une config
function athletesPresetLabel($config) {
    $nbH = (int)($config['nb_hommes'] ?? 0);
    $nbF = (int)($config['nb_femmes'] ?? 0);
    $parts = [];
    if ($nbH > 0 && $nbF > 0) $parts[] = 'H+F (' . $nbH . '/' . $nbF . ')';
    elseif ($nbH > 0) $parts[] = 'Hommes (' . $nbH . ')';
    elseif ($nbF > 0) $parts[] = 'Femmes (' . $nbF . ')';
    // Epreuve / portee
    if (!empty($config['filter_cible_enabled']) && trim((string)($config['epreuve_filter'] ?? '')) !== '') {
        $parts[] = str_replace('|', ' / ', $config['epreuve_filter']);
    } elseif (!empty($config['filter_cible_enabled']) && trim((string)($config['club_filter'] ?? '')) !== '') {
        $parts[] = '🏟 ' . str_replace('|', ' / ', $config['club_filter']);
    } elseif (!empty($config['all_epreuves'])) {
        $parts[] = 'Toutes epreuves';
    } else {
        $parts[] = 'Sprint/Sauts';
    }
    $parts[] = implode(',', (array)($config['niveaux'] ?? []));
    $parts[] = ((int)($config['annee'] ?? 0) === 0) ? 'Toutes annees' : (string)(int)$config['annee'];
    return implode(' · ', array_filter($parts, 'strlen'));
}

// === EMAILS AUTORISES AU PANEL ===
function getPanelAccessList() {
    $f = __DIR__ . '/../logs/.panel_access.php';
    if (!file_exists($f)) return [];
    $raw = file_get_contents($f);
    $pos = strpos($raw, "\n");
    if ($pos === false) return [];
    return json_decode(substr($raw, $pos + 1), true) ?: [];
}

// === STYLE DE LA PAGE PROFIL ATHLETE ===
// Valeurs : "tabs" (actuel, avec onglets) | "flat" (tout sans onglets)
function getProfileStyle() {
    $f = __DIR__ . '/../logs/.profile_settings.php';
    if (!file_exists($f)) return 'tabs';
    $raw = file_get_contents($f);
    $pos = strpos($raw, "\n");
    if ($pos === false) return 'tabs';
    $data = json_decode(substr($raw, $pos + 1), true) ?: [];
    $v = $data['profile_style'] ?? 'tabs';
    return ($v === 'flat') ? 'flat' : 'tabs';
}
function setProfileStyle($v) {
    $v = ($v === 'flat') ? 'flat' : 'tabs';
    $f = __DIR__ . '/../logs/.profile_settings.php';
    $data = ['profile_style' => $v, 'updated_at' => date('Y-m-d H:i:s')];
    file_put_contents($f, "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE));
    return $v;
}

// === ATHLETES MIS EN AVANT (page Athletes) ===
function getFeaturedAthletes() {
    $f = __DIR__ . '/../logs/.featured_athletes.php';
    $defaults = ['enabled' => false, 'title' => 'Athletes en lumiere', 'athletes' => []];
    if (!file_exists($f)) return $defaults;
    $raw = file_get_contents($f);
    $pos = strpos($raw, "\n");
    if ($pos === false) return $defaults;
    $data = json_decode(substr($raw, $pos + 1), true) ?: [];
    return [
        'enabled'  => !empty($data['enabled']),
        'title'    => trim((string)($data['title'] ?? 'Athletes en lumiere')) ?: 'Athletes en lumiere',
        'athletes' => is_array($data['athletes'] ?? null) ? array_values($data['athletes']) : [],
    ];
}
function setFeaturedAthletes($enabled, $title, $athletes) {
    $clean = [];
    if (is_array($athletes)) {
        $seen = [];
        foreach ($athletes as $a) {
            $id = (int)($a['id'] ?? 0);
            if ($id <= 0 || isset($seen[$id])) continue;
            $seen[$id] = true;
            $clean[] = [
                'id'   => $id,
                'name' => trim((string)($a['name'] ?? '')),
                'sexe' => in_array(($a['sexe'] ?? ''), ['M','F'], true) ? $a['sexe'] : '',
                'club' => trim((string)($a['club'] ?? '')),
                'added_at' => $a['added_at'] ?? date('Y-m-d H:i:s'),
            ];
        }
    }
    $title = trim((string)$title) ?: 'Athletes en lumiere';
    if (mb_strlen($title) > 80) $title = mb_substr($title, 0, 80);
    $data = [
        'enabled'    => (bool)$enabled,
        'title'      => $title,
        'athletes'   => $clean,
        'updated_at' => date('Y-m-d H:i:s'),
    ];
    $f = __DIR__ . '/../logs/.featured_athletes.php';
    file_put_contents($f, "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE));
    return $data;
}

$_isSA = isSuperAdmin();
$_isPanelUser = false;

if (!$_isSA) {
    require_once __DIR__ . '/../core/auth.php';
    $pUser = getCurrentUser($conn);
    if ($pUser) {
        $panelList = getPanelAccessList();
        if (isset($panelList[$pUser['email']])) {
            $_isPanelUser = true;
        }
    }
}

if (!$_isSA && !$_isPanelUser) {
    header('Location: ../login.php');
    exit;
}

// === DECONNEXION ===
if (isset($_GET['logout'])) {
    setcookie('bk_sa_token', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    header('Location: ../login.php');
    exit;
}

// === MAJ STYLE PAGE PROFIL ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['profile_style'])) {
    setProfileStyle($_POST['profile_style']);
    header('Location: panel.php?ps=ok#profilStyle');
    exit;
}
$currentProfileStyle = getProfileStyle();
$psSaved = isset($_GET['ps']) && $_GET['ps'] === 'ok';

// === MAJ FILTRES PAGE ATHLETES ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_athletes_filter'])) {
    // Lire l'ancien etat AVANT de sauvegarder
    $oldFilter = getAthletesFilter();

    // Si la case "Toutes les annees" est cochee, on force annee=0 (pas de filtre annee)
    $anneePost = !empty($_POST['annee_all']) ? 0 : ($_POST['annee'] ?? date('Y'));
    setAthletesFilter(
        $_POST['niveaux'] ?? [],
        $anneePost,
        $_POST['nb_hommes'] ?? 50,
        $_POST['nb_femmes'] ?? 50,
        $_POST['layout'] ?? 'magazine',
        $_POST['club_filter'] ?? '',
        $_POST['epreuve_filter'] ?? '',
        !empty($_POST['filter_cible_enabled']),
        !empty($_POST['all_epreuves'])
    );
    $newFilter = getAthletesFilter();

    // Edition manuelle du filtre actif -> aucune selection enregistree n'est "active"
    $__ps = getAthletesPresets();
    if ($__ps['active_id'] !== '') { $__ps['active_id'] = ''; saveAthletesPresets($__ps); }

    // Sauvegarde simultanee de la mise en avant (champs presents dans le meme formulaire)
    if (isset($_POST['feat_ids']) || isset($_POST['feat_title']) || isset($_POST['feat_enabled'])) {
        $featList = [];
        if (!empty($_POST['feat_ids']) && is_string($_POST['feat_ids'])) {
            $rows = json_decode($_POST['feat_ids'], true);
            if (is_array($rows)) $featList = $rows;
        }
        setFeaturedAthletes(
            !empty($_POST['feat_enabled']),
            (string)($_POST['feat_title'] ?? ''),
            $featList
        );
    }

    // Comparer les champs qui IMPACTENT les donnees (pas le layout qui est cosmetique)
    $oldData = [
        'niveaux'              => array_values((array)($oldFilter['niveaux'] ?? [])),
        'annee'                => (int)($oldFilter['annee'] ?? 0),
        'nb_hommes'            => (int)($oldFilter['nb_hommes'] ?? 0),
        'nb_femmes'            => (int)($oldFilter['nb_femmes'] ?? 0),
        'club_filter'          => (string)($oldFilter['club_filter'] ?? ''),
        'epreuve_filter'       => (string)($oldFilter['epreuve_filter'] ?? ''),
        'filter_cible_enabled' => (bool)($oldFilter['filter_cible_enabled'] ?? false),
        'all_epreuves'         => (bool)($oldFilter['all_epreuves'] ?? false),
    ];
    $newData = [
        'niveaux'              => array_values((array)($newFilter['niveaux'] ?? [])),
        'annee'                => (int)($newFilter['annee'] ?? 0),
        'nb_hommes'            => (int)($newFilter['nb_hommes'] ?? 0),
        'nb_femmes'            => (int)($newFilter['nb_femmes'] ?? 0),
        'club_filter'          => (string)($newFilter['club_filter'] ?? ''),
        'epreuve_filter'       => (string)($newFilter['epreuve_filter'] ?? ''),
        'filter_cible_enabled' => (bool)($newFilter['filter_cible_enabled'] ?? false),
        'all_epreuves'         => (bool)($newFilter['all_epreuves'] ?? false),
    ];
    sort($oldData['niveaux']); sort($newData['niveaux']);
    $dataChanged = ($oldData !== $newData);

    $cacheStatus = 'unchanged';
    if ($dataChanged) {
        // Vider le cache liste seulement si les filtres de DONNEES ont change
        $cacheDir = __DIR__ . '/../cache';
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/liste_*.json') as $f) @unlink($f);
        }
        $cacheStatus = 'cleared';
    }
    header('Location: panel.php?af=ok&cs=' . $cacheStatus . '#athletesFilter');
    exit;
}

// === Enregistrer la selection courante comme preset (depuis le formulaire filtre) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_athletes_preset'])) {
    $anneePost = !empty($_POST['annee_all']) ? 0 : ($_POST['annee'] ?? date('Y'));
    $config = normalizeAthletesConfig(
        $_POST['niveaux'] ?? [],
        $anneePost,
        $_POST['nb_hommes'] ?? 50,
        $_POST['nb_femmes'] ?? 50,
        $_POST['layout'] ?? 'magazine',
        $_POST['club_filter'] ?? '',
        $_POST['epreuve_filter'] ?? '',
        !empty($_POST['filter_cible_enabled']),
        !empty($_POST['all_epreuves'])
    );
    $store = getAthletesPresets();
    $label = trim((string)($_POST['preset_label'] ?? ''));
    if ($label === '') $label = athletesPresetLabel($config);
    if (mb_strlen($label) > 120) $label = mb_substr($label, 0, 120);
    $id = 'p' . time() . substr(md5(uniqid('', true)), 0, 5);
    array_unshift($store['presets'], [
        'id'         => $id,
        'label'      => $label,
        'config'     => $config,
        'created_at' => date('Y-m-d H:i:s'),
    ]);
    // Plafond : 40 selections max
    if (count($store['presets']) > 40) $store['presets'] = array_slice($store['presets'], 0, 40);
    // Enregistrer = activer aussitot cette selection (une seule active)
    $store['active_id'] = $id;
    saveAthletesPresets($store);
    writeAthletesFilterConfig($config);
    // Les donnees changent -> vider le cache liste
    $cacheDir = __DIR__ . '/../cache';
    if (is_dir($cacheDir)) { foreach (glob($cacheDir . '/liste_*.json') as $cf) @unlink($cf); }
    header('Location: panel.php?ap=saved#athletesPresets');
    exit;
}

// === Activer une selection enregistree (une seule active a la fois) ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['activate_athletes_preset'])) {
    $pid = (string)($_POST['preset_id'] ?? '');
    $store = getAthletesPresets();
    $found = null;
    foreach ($store['presets'] as $p) { if (($p['id'] ?? '') === $pid) { $found = $p; break; } }
    if ($found && is_array($found['config'] ?? null)) {
        $store['active_id'] = $pid;
        saveAthletesPresets($store);
        writeAthletesFilterConfig($found['config']);
        $cacheDir = __DIR__ . '/../cache';
        if (is_dir($cacheDir)) { foreach (glob($cacheDir . '/liste_*.json') as $cf) @unlink($cf); }
        header('Location: panel.php?ap=activated#athletesPresets');
    } else {
        header('Location: panel.php?ap=notfound#athletesPresets');
    }
    exit;
}

// === Supprimer une selection enregistree ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_athletes_preset'])) {
    $pid = (string)($_POST['preset_id'] ?? '');
    $store = getAthletesPresets();
    $store['presets'] = array_values(array_filter($store['presets'], function($p) use ($pid) {
        return ($p['id'] ?? '') !== $pid;
    }));
    if ($store['active_id'] === $pid) $store['active_id'] = '';
    saveAthletesPresets($store);
    header('Location: panel.php?ap=deleted#athletesPresets');
    exit;
}

$currentAthletesFilter = getAthletesFilter();
$athletesPresetsStore = getAthletesPresets();
$apMsg = $_GET['ap'] ?? '';
$afSaved = isset($_GET['af']) && $_GET['af'] === 'ok';
$afCacheStatus = $_GET['cs'] ?? '';

$currentFeatured = getFeaturedAthletes();
$ftSaved = isset($_GET['ft']) && $_GET['ft'] === 'ok';

// === AJAX — Recherche de clubs (autocomplete filtre cible) ===
if (isset($_GET['action']) && $_GET['action'] === 'search_clubs') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) { echo json_encode(['results' => []]); exit; }
    $qEsc = $conn->real_escape_string($q);
    $sql = "SELECT c.nom_club, COUNT(DISTINCT ac.id_athlete) as nb
            FROM clubs c
            LEFT JOIN athlete_clubs ac ON ac.id_club = c.id_club
            WHERE c.nom_club LIKE '%$qEsc%'
            GROUP BY c.id_club
            ORDER BY nb DESC, c.nom_club ASC
            LIMIT 12";
    $res = $conn->query($sql);
    $out = [];
    if ($res) while ($row = $res->fetch_assoc()) {
        $out[] = ['nom' => $row['nom_club'], 'nb' => (int)$row['nb']];
    }
    echo json_encode(['results' => $out]);
    exit;
}

// === AJAX — Recherche d'epreuves (autocomplete filtre cible) ===
if (isset($_GET['action']) && $_GET['action'] === 'search_epreuves') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) { echo json_encode(['results' => []]); exit; }
    $qEsc = $conn->real_escape_string($q);
    $sql = "SELECT ep.nom_epreuve, COUNT(ar.id_record) as nb
            FROM epreuves ep
            LEFT JOIN athlete_records ar ON ar.id_epreuve = ep.id_epreuve
            WHERE ep.nom_epreuve LIKE '%$qEsc%'
            GROUP BY ep.id_epreuve
            ORDER BY nb DESC, ep.nom_epreuve ASC
            LIMIT 15";
    $res = $conn->query($sql);
    $out = [];
    if ($res) while ($row = $res->fetch_assoc()) {
        $out[] = ['nom' => $row['nom_epreuve'], 'nb' => (int)$row['nb']];
    }
    echo json_encode(['results' => $out]);
    exit;
}

// === AJAX — Recherche d'athletes pour la mise en avant ===
// Comportement aligne sur api/search.php : multi-mots ordre libre sur nom_complet_athlete,
// admin voit aussi les athletes masques (visible=0), recherche sur TOUTE la BDD.
if (isset($_GET['action']) && $_GET['action'] === 'feat_search') {
    header('Content-Type: application/json; charset=utf-8');
    $q = trim((string)($_GET['q'] ?? ''));
    if (mb_strlen($q) < 2) { echo json_encode(['success' => true, 'results' => []]); exit; }

    $where = [];

    // Recherche par licence si la requete est purement numerique
    if (ctype_digit($q)) {
        $licEsc = $conn->real_escape_string($q);
        $where[] = "(a.licence_athlete LIKE '%$licEsc%' OR a.athlete_id_externe = '$licEsc')";
    } else {
        $words = preg_split('/\s+/', $q);
        $nomConds = [];
        foreach ($words as $w) {
            $w = trim($w);
            if ($w === '') continue;
            $wEsc = $conn->real_escape_string($w);
            $nomConds[] = "a.nom_complet_athlete LIKE '%$wEsc%'";
        }
        if (empty($nomConds)) { echo json_encode(['success' => true, 'results' => []]); exit; }
        $where[] = '(' . implode(' AND ', $nomConds) . ')';
    }

    $whereSql = implode(' AND ', $where);
    $sql = "SELECT a.athlete_id_externe, a.nom_complet_athlete, a.sexe_athlete, a.categorie_athlete, a.visible,
                   (SELECT c.nom_club FROM athlete_clubs ac JOIN clubs c ON c.id_club = ac.id_club
                    WHERE ac.id_athlete = a.id_athlete ORDER BY ac.annee_fin DESC, ac.annee_debut DESC LIMIT 1) AS club
            FROM athletes a
            WHERE $whereSql
            ORDER BY a.nom_complet_athlete ASC
            LIMIT 30";
    $res = $conn->query($sql);
    $out = [];
    if ($res) while ($row = $res->fetch_assoc()) {
        $out[] = [
            'id'        => (int)$row['athlete_id_externe'],
            'name'      => $row['nom_complet_athlete'],
            'sexe'      => $row['sexe_athlete'],
            'categorie' => $row['categorie_athlete'],
            'club'      => $row['club'] ?? '',
            'hidden'    => empty($row['visible']),
        ];
    }
    echo json_encode(['success' => true, 'results' => $out]);
    exit;
}

// L'apercu est genere cote client en AJAX vers api/liste.php
// (warm-up cache + temps reel + pas de loopback HTTP serveur)

// ============================================================
// AJAX ENDPOINT — Apercu athletes (sexe M ou F en parallele depuis JS)
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'preview_athletes') {
    header('Content-Type: application/json; charset=utf-8');
    $sx = strtoupper(trim($_GET['sexe'] ?? 'M'));
    if (!in_array($sx, ['M','F'], true)) $sx = 'M';
    $tStart = microtime(true);

    $f = getAthletesFilter();
    $afNiveaux = $f['niveaux'];
    $afYr = (int)$f['annee'];
    $afClubFilter = $f['club_filter'] ?? '';
    $afEpreuveFilter = $f['epreuve_filter'] ?? '';
    $afFilterCibleEnabled = !empty($f['filter_cible_enabled']);
    // Si toggle OFF, on ignore les valeurs club/epreuve
    if (!$afFilterCibleEnabled) {
        $afClubFilter = '';
        $afEpreuveFilter = '';
    }
    $afEpreuvesDefault = '100m|200m|400m Haies (76)|400m Haies (91)|110m Haies (91)|110m Haies (99)|110m Haies (106)|Longueur|Triple saut|Perche';
    // Si admin a defini une epreuve specifique → mode strict
    $strictMode = ($afEpreuveFilter !== '');
    $afEpreuves = $strictMode ? $afEpreuveFilter : $afEpreuvesDefault;

    $allowedNiv = ['IA','IB','IE','IR','IR1','IR2','IR3','IR4','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    $hierarchy = ['IA','IB','IE','IR','IR1','IR2','IR3','IR4','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    $hierarchyList = "'" . implode("','", $hierarchy) . "'";
    $filterRanks = [];
    $nivCodesQuoted = [];
    foreach ($afNiveaux as $code) {
        $idx = array_search($code, $hierarchy, true);
        if ($idx !== false) $filterRanks[] = $idx + 1;
        if (in_array($code, $allowedNiv, true)) $nivCodesQuoted[] = "'" . $conn->real_escape_string($code) . "'";
    }
    if (empty($filterRanks)) {
        echo json_encode(['success' => false, 'error' => 'Aucun niveau valide', 'sexe' => $sx]);
        exit;
    }
    $rankList = implode(',', $filterRanks);
    $nivCodesList = implode(',', $nivCodesQuoted);

    // Match exact (IN) pour mode normal, LIKE pour mode strict (tolere variantes "(91)", casse, etc.)
    $epNames = array_map(function($e) use ($conn) { return "'" . $conn->real_escape_string(trim($e)) . "'"; }, explode('|', $afEpreuves));
    $epList = implode(',', $epNames);
    // Pour le mode strict, on construit une clause LIKE sur chaque morceau d'epreuve
    $epLikeConds = [];
    foreach (explode('|', $afEpreuves) as $epPart) {
        $epPart = trim($epPart);
        if ($epPart === '') continue;
        $epLikeConds[] = "ep_strict.nom_epreuve LIKE '%" . $conn->real_escape_string($epPart) . "%'";
    }
    $epLikeWhere = !empty($epLikeConds) ? '(' . implode(' OR ', $epLikeConds) . ')' : '1=0';

    $latestYearSub = "GREATEST(
        COALESCE((SELECT MAX(YEAR(ar2.date_record)) FROM athlete_records ar2 WHERE ar2.id_athlete = a.id_athlete), 0),
        COALESCE((SELECT MAX(YEAR(ares2.date_resultat)) FROM athlete_resultats ares2 WHERE ares2.id_athlete = a.id_athlete), 0),
        COALESCE((SELECT MAX(aprog2.annee_progression) FROM athlete_progressions aprog2 WHERE aprog2.id_athlete = a.id_athlete), 0)
    )";

    // Filtre club optionnel — supporte plusieurs clubs separes par |
    $clubJoin = '';
    if ($afClubFilter !== '') {
        $clubParts = array_filter(array_map('trim', explode('|', $afClubFilter)), function($v){ return $v !== ''; });
        if (!empty($clubParts)) {
            $clubConds = [];
            foreach ($clubParts as $cp) {
                $clubConds[] = "cl_f.nom_club LIKE '%" . $conn->real_escape_string($cp) . "%'";
            }
            $clubJoin = "INNER JOIN athlete_clubs ac_f ON ac_f.id_athlete = a.id_athlete
                         INNER JOIN clubs cl_f ON cl_f.id_club = ac_f.id_club AND (" . implode(' OR ', $clubConds) . ")";
        }
    }

    if ($strictMode) {
        // MODE STRICT : niveau X SUR epreuve Y via code_perf_niveau (LIKE pour tolerance)
        // Le filtre annee s'applique aussi au niveau strict : annee_niveau >= afYr
        $yearNivClauseS = ($afYr > 0) ? " AND an_strict.annee_niveau >= $afYr" : '';
        $sql = "SELECT a.athlete_id_externe, a.nom_complet_athlete, a.categorie_athlete,
                       ($latestYearSub) as latest_year
                FROM athletes a
                INNER JOIN athlete_niveaux an_strict ON an_strict.id_athlete = a.id_athlete$yearNivClauseS
                INNER JOIN athlete_niv_perfs anp_strict ON anp_strict.id_niveau = an_strict.id_niveau AND anp_strict.code_perf_niveau IN ($nivCodesList)
                INNER JOIN epreuves ep_strict ON ep_strict.id_epreuve = anp_strict.id_epreuve AND $epLikeWhere
                $clubJoin
                WHERE a.visible = 1 AND a.sexe_athlete = '$sx'
                GROUP BY a.id_athlete
                HAVING latest_year >= $afYr
                ORDER BY (SELECT COUNT(*) FROM athlete_medailles am WHERE am.id_athlete = a.id_athlete) DESC
                LIMIT 50";
    } else {
        // MODE NORMAL : niveau global + records dans la liste d'epreuves hardcoded
        $sql = "SELECT a.athlete_id_externe, a.nom_complet_athlete, a.categorie_athlete,
                       ($latestYearSub) as latest_year
                FROM athletes a
                INNER JOIN (
                    SELECT id_athlete, MIN(FIELD(code_niveau, $hierarchyList)) as best_rank
                    FROM athlete_niveaux
                    WHERE FIELD(code_niveau, $hierarchyList) > 0
                    GROUP BY id_athlete
                ) an_f ON an_f.id_athlete = a.id_athlete AND an_f.best_rank IN ($rankList)
                INNER JOIN athlete_records ar_ep ON ar_ep.id_athlete = a.id_athlete
                INNER JOIN epreuves ep_f ON ep_f.id_epreuve = ar_ep.id_epreuve AND ep_f.nom_epreuve IN ($epList)
                $clubJoin
                WHERE a.visible = 1 AND a.sexe_athlete = '$sx'
                GROUP BY a.id_athlete
                HAVING latest_year >= $afYr
                ORDER BY (SELECT COUNT(*) FROM athlete_medailles am WHERE am.id_athlete = a.id_athlete) DESC
                LIMIT 50";
    }
    $res = $conn->query($sql);
    $athletes = [];
    if ($res) while ($row = $res->fetch_assoc()) {
        $athletes[] = [
            'athlete_id'  => (int)$row['athlete_id_externe'],
            'nom_complet' => $row['nom_complet_athlete'],
            'categorie'   => $row['categorie_athlete'],
            'latest_year' => (int)$row['latest_year'],
        ];
    }
    $elapsed = round((microtime(true) - $tStart) * 1000);
    echo json_encode([
        'success' => true,
        'sexe' => $sx,
        'count' => count($athletes),
        'athletes' => $athletes,
        'elapsed_ms' => $elapsed,
        'filters' => [
            'niveaux'    => $afNiveaux,
            'annee'      => $afYr,
            'club'       => $afClubFilter,
            'epreuve'    => $afEpreuveFilter,
            'strict'     => $strictMode,
        ],
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// AJAX ENDPOINTS — Mapping bareme custom (admin associe une epreuve BDD a un bareme)
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'bareme_list') {
    header('Content-Type: application/json; charset=utf-8');
    $cfg = @include(__DIR__ . '/../config/bareme_hommes.php');
    $names = array_keys($cfg['breakpoints'] ?? []);
    sort($names, SORT_NATURAL);
    echo json_encode(['success' => true, 'baremes' => $names], JSON_UNESCAPED_UNICODE);
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'bareme_map_get') {
    header('Content-Type: application/json; charset=utf-8');
    $f = __DIR__ . '/../logs/.bareme_user_mapping.php';
    $data = [];
    if (file_exists($f)) {
        $raw = @file_get_contents($f);
        $pos = $raw ? strpos($raw, "\n") : false;
        if ($pos !== false) $data = json_decode(substr($raw, $pos + 1), true) ?: [];
    }
    echo json_encode(['success' => true, 'mapping' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}
if (isset($_GET['action']) && $_GET['action'] === 'bareme_suggest' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = json_decode(file_get_contents('php://input'), true) ?: [];
    $epreuves = $body['epreuves'] ?? [];
    if (!is_array($epreuves)) $epreuves = [];

    $baremePath = __DIR__ . '/../config/bareme_hommes.php';
    if (!file_exists($baremePath)) {
        echo json_encode(['success' => false, 'error' => 'bareme_hommes.php introuvable a ' . $baremePath]);
        exit;
    }
    $cfg = @include($baremePath);
    if (!is_array($cfg) || empty($cfg['breakpoints'])) {
        echo json_encode(['success' => false, 'error' => 'bareme_hommes.php invalide (pas de breakpoints)']);
        exit;
    }
    $baremeNames = array_keys($cfg['breakpoints']);

    $bk_norm = function($s) {
        $s = (string)$s;
        $s = function_exists('mb_strtolower') ? mb_strtolower($s) : strtolower($s);
        $s = str_replace(['piste courte', 'piste'], '', $s);
        $s = str_replace(['salle'], '', $s);
        $s = str_replace(['course'], '', $s);
        $s = preg_replace('/[\s\-_()]+/', ' ', $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return trim((string)$s);
    };
    $bk_extract_meters = function($s) {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*m(?![a-z])/', $s, $m)) return str_replace(',', '.', $m[1]);
        return null;
    };
    $bk_extract_kg = function($s) {
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*kg/', $s, $m)) return str_replace(',', '.', $m[1]);
        return null;
    };
    $bk_extract_g = function($s) {
        if (preg_match('/(\d+)\s*g(?![a-z])/', $s, $m)) return $m[1];
        return null;
    };
    // Hauteur de haies — sur RAW string (avant norm), en cm
    // BDD : "Haies (106)" => 106 cm
    // Bareme : "Haies 1.06m" => 106 cm
    $bk_hurdle_height = function($raw) {
        $s = function_exists('mb_strtolower') ? mb_strtolower($raw) : strtolower($raw);
        if (strpos($s, 'haies') === false) return null;
        // BDD : (NN) ou (NNN)
        if (preg_match('/\(\s*(\d{2,3})\s*\)/', $s, $m)) return (int)$m[1];
        // Bareme : N.NN m ou 0.NN m
        if (preg_match('/(\d+\.\d+)\s*m/', $s, $m)) return (int)round((float)$m[1] * 100);
        return null;
    };
    $bk_score = function($a, $b) use ($bk_norm, $bk_extract_meters, $bk_extract_kg, $bk_extract_g, $bk_hurdle_height) {
        // Hauteur de haies sur RAW (avant norm qui supprime les parens)
        $ah = $bk_hurdle_height($a);
        $bh = $bk_hurdle_height($b);
        if ($ah !== null && $bh !== null && $ah !== $bh) return 0.0;

        $aN = $bk_norm($a);
        $bN = $bk_norm($b);
        if ($aN === $bN) return 100.0;

        // === REGLES STRICTES : distance/poids different => score 0 ===

        // Distance metres : si les 2 ont une distance et elles different => 0
        $am = $bk_extract_meters($aN);
        $bm = $bk_extract_meters($bN);
        if ($am !== null && $bm !== null && $am !== $bm) return 0.0;

        // Poids kg : si les 2 ont un kg et ils different => 0
        $akg = $bk_extract_kg($aN);
        $bkg = $bk_extract_kg($bN);
        if ($akg !== null && $bkg !== null && $akg !== $bkg) return 0.0;

        // Poids g (javelot) : si les 2 ont un g et ils different => 0
        $ag = $bk_extract_g($aN);
        $bg = $bk_extract_g($bN);
        if ($ag !== null && $bg !== null && $ag !== $bg) return 0.0;

        // Categorie d'epreuve : si l'une contient une categorie ET pas l'autre => 0
        // (ex : "Hauteur" ne doit pas matcher "Longueur")
        $cats = ['haies','steeple','marathon','perche','hauteur','longueur','triple','poids','disque','marteau','javelot','pentathlon','decathlon','heptathlon'];
        foreach ($cats as $c) {
            $aHas = strpos($aN, $c) !== false;
            $bHas = strpos($bN, $c) !== false;
            if ($aHas !== $bHas) return 0.0;
        }

        // === SCORING ===
        similar_text($aN, $bN, $pct);
        $score = (float)$pct;

        // Tokens communs
        $aT = array_filter(preg_split('/\s+/', $aN));
        $bT = array_filter(preg_split('/\s+/', $bN));
        $common = array_intersect($aT, $bT);
        $score += count($common) * 4;

        // Bonus categorie partagee
        foreach ($cats as $c) {
            if (strpos($aN, $c) !== false && strpos($bN, $c) !== false) { $score += 25; break; }
        }

        // Bonus distance/poids identique (encourage explicitement)
        if ($am !== null && $bm !== null && $am === $bm) $score += 30;
        if ($akg !== null && $bkg !== null && $akg === $bkg) $score += 35;
        if ($ag !== null && $bg !== null && $ag === $bg) $score += 35;

        return max(0.0, min(99.9, $score));
    };

    $results = [];
    $rejected = []; // epreuves sans aucun match (toutes a 0)
    foreach ($epreuves as $epBdd) {
        $epBdd = trim((string)$epBdd);
        if ($epBdd === '') continue;
        $candidates = [];
        foreach ($baremeNames as $bn) {
            $score = $bk_score($epBdd, $bn);
            // Filtrer : ignorer les 0%
            if ($score > 0) {
                $candidates[] = ['name' => $bn, 'score' => round($score, 1)];
            }
        }
        usort($candidates, function($a, $b) { return $b['score'] <=> $a['score']; });
        if (empty($candidates)) {
            $rejected[] = $epBdd;
            continue; // Pas de suggestion du tout
        }
        $top3 = array_slice($candidates, 0, 3);
        $results[] = [
            'epreuve_bdd'  => $epBdd,
            'best'         => $top3[0] ?? null,
            'alternatives' => array_slice($top3, 1),
        ];
    }

    // Sex distribution par epreuve problematique (info pour l'admin)
    if (!empty($epreuves)) {
        $epEsc = [];
        foreach ($epreuves as $ep) $epEsc[] = "'" . $conn->real_escape_string($ep) . "'";
        $epList = implode(',', $epEsc);
        $sexBy = [];
        $sql = "SELECT e.nom_epreuve, a.sexe_athlete, COUNT(DISTINCT a.id_athlete) AS nb
                FROM athletes a
                LEFT JOIN athlete_niveaux n ON n.id_athlete = a.id_athlete
                INNER JOIN athlete_records ar ON ar.id_athlete = a.id_athlete
                INNER JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
                WHERE a.visible = 1 AND n.id_athlete IS NULL
                  AND e.nom_epreuve IN ($epList)
                GROUP BY e.nom_epreuve, a.sexe_athlete";
        $r = $conn->query($sql);
        if ($r) while ($row = $r->fetch_assoc()) {
            $key = $row['nom_epreuve'];
            if (!isset($sexBy[$key])) $sexBy[$key] = ['M'=>0,'F'=>0];
            $sx = $row['sexe_athlete'] ?? '';
            if ($sx === 'M' || $sx === 'F') $sexBy[$key][$sx] = (int)$row['nb'];
        }
        foreach ($results as &$res) {
            $res['sex'] = $sexBy[$res['epreuve_bdd']] ?? ['M'=>0,'F'=>0];
        }
        unset($res);
    }

    echo json_encode([
        'success'     => true,
        'suggestions' => $results,
        'rejected'    => $rejected,
        'nb_rejected' => count($rejected),
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'bareme_map_set' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $body = file_get_contents('php://input');
    $payload = json_decode($body, true) ?: [];
    $epBdd = trim($payload['epreuve_bdd'] ?? '');
    $bremeName = trim($payload['bareme_name'] ?? '');
    $sexe = strtoupper(trim($payload['sexe'] ?? 'BOTH'));
    if (!in_array($sexe, ['M','F','BOTH'], true)) $sexe = 'BOTH';
    if ($epBdd === '') { echo json_encode(['success' => false, 'error' => 'epreuve_bdd vide']); exit; }

    // Charger mapping existant
    $f = __DIR__ . '/../logs/.bareme_user_mapping.php';
    $data = [];
    if (file_exists($f)) {
        $raw = @file_get_contents($f);
        $pos = $raw ? strpos($raw, "\n") : false;
        if ($pos !== false) $data = json_decode(substr($raw, $pos + 1), true) ?: [];
    }

    // Migration backward compat : ancien format (string) -> nouveau format ({M, F})
    foreach ($data as $k => $v) {
        if (is_string($v)) $data[$k] = ['M' => $v, 'F' => ''];
    }

    // Init si absent
    if (!isset($data[$epBdd]) || !is_array($data[$epBdd])) {
        $data[$epBdd] = ['M' => '', 'F' => ''];
    }

    // bareme_name vide = supprimer (le mapping pour ce sexe)
    if ($bremeName === '') {
        if ($sexe === 'BOTH') $data[$epBdd] = ['M' => '', 'F' => ''];
        else $data[$epBdd][$sexe] = '';
        // Si tout vide, supprimer la cle
        if (empty($data[$epBdd]['M']) && empty($data[$epBdd]['F'])) unset($data[$epBdd]);
    } else {
        // Verifier que le bareme existe
        $cfg = @include(__DIR__ . '/../config/bareme_hommes.php');
        if (!isset($cfg['breakpoints'][$bremeName])) {
            echo json_encode(['success' => false, 'error' => 'bareme inexistant']); exit;
        }
        if ($sexe === 'BOTH') {
            $data[$epBdd] = ['M' => $bremeName, 'F' => $bremeName];
        } else {
            $data[$epBdd][$sexe] = $bremeName;
        }
    }

    @file_put_contents($f, "<?php die(); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE));
    echo json_encode(['success' => true, 'mapping' => $data], JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// AJAX ENDPOINT — Repartition athletes par meilleur niveau (sans doublons)
// + fallback bareme FFA + listing epreuves problematiques
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'niveaux') {
    header('Content-Type: application/json; charset=utf-8');
    set_time_limit(120);
    @ini_set('memory_limit', '512M');

    $hierarchy = ['IA','IB','IE','IR','IR1','IR2','IR3','IR4','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    $bddCodes = ['IA','IB','IE','IR','IR2','N1','N2','N3','N4','R1','R2','R3','R4','R5','R6','D1','D2','D3','D4','D5','D6','D7','D8'];
    $bddFieldList = "'" . implode("','", $bddCodes) . "'";

    // 1. Total athletes visibles
    $resTotal = $conn->query("SELECT COUNT(*) AS c FROM athletes WHERE visible = 1");
    $totalAth = $resTotal ? (int)$resTotal->fetch_assoc()['c'] : 0;

    // 2. Niveaux depuis la BDD (athlete_niveaux)
    $sqlBdd = "SELECT best_level, COUNT(*) AS nb FROM (
                SELECT
                    n.id_athlete,
                    n.code_niveau AS best_level,
                    ROW_NUMBER() OVER (
                        PARTITION BY n.id_athlete
                        ORDER BY FIELD(n.code_niveau, $bddFieldList) ASC
                    ) AS rn
                FROM athlete_niveaux n
                INNER JOIN athletes a ON a.id_athlete = n.id_athlete
                WHERE a.visible = 1
                  AND n.code_niveau IN ($bddFieldList)
            ) t
            WHERE rn = 1
            GROUP BY best_level
            ORDER BY FIELD(best_level, $bddFieldList) ASC";
    $byLevelBdd = array_fill_keys($hierarchy, 0);
    $totalAvecNivBdd = 0;
    $res = $conn->query($sqlBdd);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $code = $r['best_level'];
            $nb = (int)$r['nb'];
            if (isset($byLevelBdd[$code])) $byLevelBdd[$code] = $nb;
            $totalAvecNivBdd += $nb;
        }
    }

    // 3. Bareme FFA — chargement
    $baremeConfig = @include(__DIR__ . '/../config/bareme_hommes.php');
    $bp_arr  = (is_array($baremeConfig) && isset($baremeConfig['breakpoints'])) ? $baremeConfig['breakpoints'] : [];
    $map_arr = (is_array($baremeConfig) && isset($baremeConfig['mapping']))     ? $baremeConfig['mapping']     : [];

    // 3b. Mapping utilisateur (admin) — prioritaire, format par sexe
    //     Ancien format : ['ep' => 'bareme_name'] (string)
    //     Nouveau format : ['ep' => ['M' => 'name', 'F' => 'name']] (array)
    $userMap = [];
    $userMapFile = __DIR__ . '/../logs/.bareme_user_mapping.php';
    if (file_exists($userMapFile)) {
        $raw = @file_get_contents($userMapFile);
        $pos = $raw ? strpos($raw, "\n") : false;
        if ($pos !== false) {
            $um = json_decode(substr($raw, $pos + 1), true);
            if (is_array($um)) {
                foreach ($um as $ep => $val) {
                    if (is_string($val)) {
                        // Ancien format : applique aux 2 sexes
                        $userMap[$ep] = ['M' => $val, 'F' => $val];
                    } elseif (is_array($val)) {
                        $userMap[$ep] = ['M' => (string)($val['M'] ?? ''), 'F' => (string)($val['F'] ?? '')];
                    }
                }
            }
        }
    }

    $isDistEvent = function($nom) {
        return (bool)preg_match('/(poids|disque|javelot|marteau|hauteur|perche|longueur|triple|decathlon|heptathlon|pentathlon)/i', $nom);
    };
    // perfToPoints : prend en compte le sexe de l'athlete pour piocher dans userMap
    $perfToPoints = function($perfInt, $epName, $sexe = 'M') use (&$bp_arr, &$map_arr, &$userMap, $isDistEvent) {
        if ($perfInt <= 0) return 0;
        // Priorite : userMap[ep][sexe] > map_arr[ep] (legacy string) > ep
        $bName = '';
        if (isset($userMap[$epName][$sexe]) && $userMap[$epName][$sexe] !== '') {
            $bName = $userMap[$epName][$sexe];
        } elseif (isset($map_arr[$epName]) && is_string($map_arr[$epName]) && $map_arr[$epName] !== '') {
            $bName = $map_arr[$epName];
        } else {
            $bName = $epName;
        }
        $bp = isset($bp_arr[$bName]) ? $bp_arr[$bName] : null;
        if (!$bp || !is_array($bp) || empty($bp)) return 0;
        $isDist = $isDistEvent($epName);
        $nbBp = count($bp);
        for ($i = 0; $i < $nbBp; $i++) {
            $pts  = $bp[$i][0];
            $perf = $bp[$i][1];
            if ($isDist) {
                if ($perfInt >= $perf) {
                    if ($i === 0) return $pts;
                    $prevPts  = $bp[$i-1][0];
                    $prevPerf = $bp[$i-1][1];
                    $ratio = ($perfInt - $perf) / max(1, $prevPerf - $perf);
                    return $pts + $ratio * ($prevPts - $pts);
                }
            } else {
                if ($perfInt <= $perf) {
                    if ($i === 0) return $pts;
                    $prevPts  = $bp[$i-1][0];
                    $prevPerf = $bp[$i-1][1];
                    $ratio = ($perf - $perfInt) / max(1, $perf - $prevPerf);
                    return $pts + $ratio * ($prevPts - $pts);
                }
            }
        }
        return 0;
    };
    $pointsToLevel = function($pts) {
        if ($pts >= 40) return 'IA';
        if ($pts >= 35) return 'IB';
        if ($pts >= 30) return 'N1';
        if ($pts >= 28) return 'N2';
        if ($pts >= 26) return 'N3';
        if ($pts >= 24) return 'N4';
        if ($pts >= 21) return 'IR1';
        if ($pts >= 20) return 'IR2';
        if ($pts >= 19) return 'IR3';
        if ($pts >= 18) return 'IR4';
        if ($pts >= 15) return 'R1';
        if ($pts >= 14) return 'R2';
        if ($pts >= 13) return 'R3';
        if ($pts >= 12) return 'R4';
        if ($pts >= 11) return 'R5';
        if ($pts >= 10) return 'R6';
        if ($pts >= 8)  return 'D1';
        if ($pts >= 7)  return 'D2';
        if ($pts >= 6)  return 'D3';
        if ($pts >= 5)  return 'D4';
        if ($pts >= 4)  return 'D5';
        return '';
    };

    // 4. Athletes sans niveau BDD : recuperer leurs records pour calcul bareme
    $sqlNoNiv = "SELECT a.id_athlete, a.sexe_athlete, e.nom_epreuve, ar.performance_record
                 FROM athletes a
                 LEFT JOIN athlete_niveaux n ON n.id_athlete = a.id_athlete
                 INNER JOIN athlete_records ar ON ar.id_athlete = a.id_athlete
                 INNER JOIN epreuves e ON e.id_epreuve = ar.id_epreuve
                 WHERE a.visible = 1
                   AND n.id_athlete IS NULL
                   AND ar.performance_record > 0";
    $athleteRecords = []; // aid => [['ep'=>name,'perf'=>int]]
    $athleteSexe = [];
    $res = $conn->query($sqlNoNiv);
    if ($res) {
        while ($r = $res->fetch_assoc()) {
            $aid = (int)$r['id_athlete'];
            $athleteSexe[$aid] = $r['sexe_athlete'];
            $athleteRecords[$aid][] = ['ep' => $r['nom_epreuve'], 'perf' => (int)$r['performance_record']];
        }
    }

    // Helper : verifie qu'un bareme existe pour une epreuve+sexe
    $hasBareme = function($epName, $sexe) use (&$bp_arr, &$map_arr, &$userMap) {
        $bName = '';
        if (isset($userMap[$epName][$sexe]) && $userMap[$epName][$sexe] !== '') {
            $bName = $userMap[$epName][$sexe];
        } elseif (isset($map_arr[$epName]) && is_string($map_arr[$epName]) && $map_arr[$epName] !== '') {
            $bName = $map_arr[$epName];
        } else {
            $bName = $epName;
        }
        return isset($bp_arr[$bName]) && is_array($bp_arr[$bName]) && !empty($bp_arr[$bName]);
    };

    // 5. Calcul niveau via bareme (selon sexe)
    $byLevelCalc = array_fill_keys($hierarchy, 0);
    $totalAvecNivCalc = 0;
    $epreuvesProblematiques = []; // ep_name => count athletes
    $athletesIncalculables = 0;
    $athletesFemmes = 0;
    $athletesSansBareme = 0;

    foreach ($athleteRecords as $aid => $records) {
        $sexe = ($athleteSexe[$aid] === 'F') ? 'F' : 'M';

        $bestPts = 0;
        $bestCode = '';
        $missingEps = [];
        foreach ($records as $rec) {
            $epName = $rec['ep'];
            $perfInt = (int)$rec['perf'];
            if (!$hasBareme($epName, $sexe)) {
                $missingEps[$epName] = true;
                continue;
            }
            $pts = $perfToPoints($perfInt, $epName, $sexe);
            if ($pts > $bestPts) {
                $bestPts = $pts;
                $code = $pointsToLevel($pts);
                if ($code !== '') $bestCode = $code;
            }
        }

        if ($bestCode !== '' && isset($byLevelCalc[$bestCode])) {
            $byLevelCalc[$bestCode]++;
            $totalAvecNivCalc++;
        } else {
            // Aucun record n'a pu etre converti
            $athletesIncalculables++;
            if ($sexe === 'F' && empty($missingEps)) {
                $athletesFemmes++; // F sans bareme adapte
            }
            if (!empty($missingEps)) $athletesSansBareme++;
            foreach ($missingEps as $ep => $_) {
                if (!isset($epreuvesProblematiques[$ep])) $epreuvesProblematiques[$ep] = 0;
                $epreuvesProblematiques[$ep]++;
            }
        }
    }

    // 6. Athletes sans niveau BDD ET sans aucun record
    $athletesAvecRecords = count($athleteRecords);
    $athletesSansNivBdd = $totalAth - $totalAvecNivBdd;
    $athletesAucuneDonnee = max(0, $athletesSansNivBdd - $athletesAvecRecords);
    $athletesIncalculables += $athletesAucuneDonnee;

    // 7. Total combine (BDD + calcule)
    $byLevelTotal = [];
    foreach ($hierarchy as $code) {
        $byLevelTotal[$code] = ($byLevelBdd[$code] ?? 0) + ($byLevelCalc[$code] ?? 0);
    }
    $totalAvecNiv = $totalAvecNivBdd + $totalAvecNivCalc;

    // 8. Top epreuves problematiques (tri desc)
    arsort($epreuvesProblematiques);
    $topEpProblems = [];
    $iEp = 0;
    foreach ($epreuvesProblematiques as $ep => $nb) {
        $topEpProblems[] = ['epreuve' => $ep, 'nb_athletes' => $nb];
        if (++$iEp >= 50) break;
    }

    $resultData = [
        'success'             => true,
        'total_athletes'      => $totalAth,
        'total_avec_niv'      => $totalAvecNiv,
        'total_avec_niv_bdd'  => $totalAvecNivBdd,
        'total_avec_niv_calc' => $totalAvecNivCalc,
        'sans_niveau'         => $athletesIncalculables,
        'sans_niveau_femmes'  => $athletesFemmes,
        'sans_niveau_records' => $athletesSansBareme,
        'sans_niveau_aucune'  => $athletesAucuneDonnee,
        'par_niveau'          => $byLevelTotal,
        'par_niveau_bdd'      => $byLevelBdd,
        'par_niveau_calc'     => $byLevelCalc,
        'epreuves_problemes'  => $topEpProblems,
        'nb_epreuves_probl'   => count($epreuvesProblematiques),
        'hierarchy'           => $hierarchy,
        'computed_at'         => date('Y-m-d H:i:s'),
    ];

    // === SAUVEGARDE pour affichage public sur page Athletes ===
    $statsFile = __DIR__ . '/../logs/.niveaux_stats.php';
    @file_put_contents($statsFile, "<?php die(); ?>\n" . json_encode($resultData, JSON_UNESCAPED_UNICODE));

    echo json_encode($resultData, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// AJAX ENDPOINT — Activite d'un user (lazy load au clic)
// ============================================================
if (isset($_GET['action']) && $_GET['action'] === 'activity') {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)($_GET['uid'] ?? 0);
    if ($uid <= 0) { echo json_encode(['error' => 'uid invalide']); exit; }

    $out = ['uid' => $uid, 'logs' => [], 'stats' => []];

    // Stats agregees
    $r = $conn->query("SELECT COUNT(*) AS total, MIN(ts) AS first_seen, MAX(ts) AS last_seen, COUNT(DISTINCT ip) AS nb_ips, COUNT(DISTINCT DATE(ts)) AS nb_days FROM logs WHERE uid = $uid");
    if ($r) $out['stats'] = $r->fetch_assoc() ?: [];

    // Repartition par action
    $r = $conn->query("SELECT action, COUNT(*) AS n FROM logs WHERE uid = $uid GROUP BY action ORDER BY n DESC LIMIT 20");
    $out['by_action'] = [];
    if ($r) while ($row = $r->fetch_assoc()) $out['by_action'][] = $row;

    // 200 derniers logs
    $r = $conn->query("SELECT ts, ip, action, page, detail, target, referrer, duration_ms FROM logs WHERE uid = $uid ORDER BY ts DESC LIMIT 200");
    if ($r) while ($row = $r->fetch_assoc()) $out['logs'][] = $row;

    // Resolution des noms d'athletes pour les URLs de profil (en bulk)
    $athleteIds = [];
    foreach ($out['logs'] as $l) {
        $pg = $l['page'] ?? '';
        if (!$pg) continue;
        // Capture id depuis ?page=profil&id=X ou ?id=X dans une URL
        if (preg_match('/[?&]page=profil[^#]*?[?&]id=(\d+)/', $pg, $m) || preg_match('/profil[^?]*\?(?:.*&)?id=(\d+)/', $pg, $m) || (strpos($pg, 'profil') !== false && preg_match('/[?&]id=(\d+)/', $pg, $m))) {
            $athleteIds[(int)$m[1]] = true;
        }
    }
    $out['athletes'] = [];
    if ($athleteIds) {
        $idsList = implode(',', array_map('intval', array_keys($athleteIds)));
        $rA = $conn->query("SELECT athlete_id_externe, nom_complet_athlete FROM athletes WHERE athlete_id_externe IN ($idsList)");
        if ($rA) {
            while ($row = $rA->fetch_assoc()) {
                $out['athletes'][(int)$row['athlete_id_externe']] = trim($row['nom_complet_athlete']);
            }
        }
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// === AJAX — Navigation detaillee d'un utilisateur (section "Suivi navigation") ===
if (isset($_GET['action']) && $_GET['action'] === 'usernav') {
    header('Content-Type: application/json; charset=utf-8');
    $uid = (int)($_GET['uid'] ?? 0);
    if ($uid <= 0) { echo json_encode(['error' => 'uid invalide']); exit; }

    $out = ['uid' => $uid, 'logs' => [], 'athletes' => [], 'stats' => []];

    // Stats agregees
    $r = $conn->query("SELECT COUNT(*) AS total, MIN(ts) AS first_seen, MAX(ts) AS last_seen,
                              COUNT(DISTINCT ip) AS nb_ips, COUNT(DISTINCT DATE(ts)) AS nb_days,
                              SUM(action = 'page_view') AS nb_pages
                       FROM logs WHERE uid = $uid");
    if ($r) $out['stats'] = $r->fetch_assoc() ?: [];

    // Navigation detaillee : toutes les actions, 1000 dernieres (page par page)
    $r = $conn->query("SELECT ts, ip, action, page, detail, target, referrer, duration_ms
                       FROM logs WHERE uid = $uid ORDER BY ts DESC LIMIT 1000");
    if ($r) while ($row = $r->fetch_assoc()) $out['logs'][] = $row;

    // Resolution des noms d'athletes pour les URLs de profil (en bulk)
    $athleteIds = [];
    foreach ($out['logs'] as $l) {
        $pg = $l['page'] ?? '';
        if (!$pg) continue;
        if (preg_match('/[?&]page=profil[^#]*?[?&]id=(\d+)/', $pg, $m) || preg_match('/profil[^?]*\?(?:.*&)?id=(\d+)/', $pg, $m) || (strpos($pg, 'profil') !== false && preg_match('/[?&]id=(\d+)/', $pg, $m))) {
            $athleteIds[(int)$m[1]] = true;
        }
    }
    if ($athleteIds) {
        $idsList = implode(',', array_map('intval', array_keys($athleteIds)));
        $rA = $conn->query("SELECT athlete_id_externe, nom_complet_athlete FROM athletes WHERE athlete_id_externe IN ($idsList)");
        if ($rA) while ($row = $rA->fetch_assoc()) $out['athletes'][(int)$row['athlete_id_externe']] = trim($row['nom_complet_athlete']);
    }

    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
}

// ============================================================
// DATA — Emails inscrits + nombre d'actions par user
// ============================================================

// 1. Compter les actions par user (1 requete groupee)
$actionCounts = [];
$resCnt = $conn->query("SELECT uid, COUNT(*) AS n FROM logs WHERE uid IS NOT NULL GROUP BY uid");
if ($resCnt) {
    while ($r = $resCnt->fetch_assoc()) {
        $actionCounts[(int)$r['uid']] = (int)$r['n'];
    }
}

// 2. Liste des users
$usersGoogle = [];
$usersEmail = [];
$res = $conn->query("SELECT id_user, email, role, oauth_provider, picture, nom, prenom, date_creation, last_login FROM users ORDER BY date_creation DESC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $row['nb_actions'] = $actionCounts[(int)$row['id_user']] ?? 0;
        if (strtolower((string)$row['oauth_provider']) === 'google') {
            $usersGoogle[] = $row;
        } else {
            $usersEmail[] = $row;
        }
    }
}
$totalUsers = count($usersGoogle) + count($usersEmail);
$totalGoogle = count($usersGoogle);
$totalEmail = count($usersEmail);

// Stats inscriptions par mois (12 derniers mois + tout l'historique)
$inscMonths = [];
$resIM = $conn->query("
    SELECT DATE_FORMAT(date_creation, '%Y-%m') AS ym,
           SUM(CASE WHEN LOWER(oauth_provider) = 'google' THEN 1 ELSE 0 END) AS nb_google,
           SUM(CASE WHEN LOWER(oauth_provider) = 'google' THEN 0 ELSE 1 END) AS nb_email,
           COUNT(*) AS nb_total
    FROM users
    WHERE date_creation IS NOT NULL
    GROUP BY ym
    ORDER BY ym DESC
");
if ($resIM) {
    while ($row = $resIM->fetch_assoc()) {
        $inscMonths[] = $row;
    }
}
$inscMaxMonth = 0;
foreach ($inscMonths as $m) {
    if ((int)$m['nb_total'] > $inscMaxMonth) $inscMaxMonth = (int)$m['nb_total'];
}
$moisFrLong = ['', 'Janvier','Fevrier','Mars','Avril','Mai','Juin','Juillet','Aout','Septembre','Octobre','Novembre','Decembre'];

// ============================================================
// DATA — Abonnements premium (statut par utilisateur + stats)
// ============================================================
require_once __DIR__ . '/../core/stripe_config.php'; // $BK_PLANS (noms + couleurs)
$subByUser = [];
$subStats  = ['active' => 0, 'total' => 0, 'manuel' => 0, 'stripe' => 0,
              'plans' => ['bronze' => 0, 'argent' => 0, 'or' => 0, 'platine' => 0]];
$_chkSub = $conn->query("SHOW TABLES LIKE 'subscriptions'");
if ($_chkSub && $_chkSub->num_rows > 0) {
    $resSub = $conn->query("SELECT id_user, plan, status, billing_period, current_period_end, updated_at FROM subscriptions");
    if ($resSub) {
        $nowTs = time();
        while ($s = $resSub->fetch_assoc()) {
            $isActive = in_array($s['status'], ['active', 'trialing', 'past_due'], true)
                && (empty($s['current_period_end']) || strtotime($s['current_period_end']) >= $nowTs);
            $s['actif'] = $isActive;
            $subByUser[(int)$s['id_user']] = $s;
            $subStats['total']++;
            if ($isActive) {
                $subStats['active']++;
                if (isset($subStats['plans'][$s['plan']])) $subStats['plans'][$s['plan']]++;
                if (($s['billing_period'] ?? '') === 'manuel') $subStats['manuel']++; else $subStats['stripe']++;
            }
        }
    }
}

// Liste de tous les inscrits triee par id_user DESC (plus recent en haut)
$moisFr = ['', 'janvier','fevrier','mars','avril','mai','juin','juillet','aout','septembre','octobre','novembre','decembre'];
$allRecent = array_merge($usersGoogle, $usersEmail);
usort($allRecent, function($a, $b) { return (int)$b['id_user'] - (int)$a['id_user']; });

// Format date FR par user
foreach ($allRecent as $i => $u) {
    $ts = strtotime($u['date_creation'] ?? '');
    if ($ts) {
        $allRecent[$i]['date_fr'] = (int)date('j', $ts) . ' ' . $moisFr[(int)date('n', $ts)] . ' ' . date('Y', $ts) . ' a ' . date('H', $ts) . 'h' . date('i', $ts);
    } else {
        $allRecent[$i]['date_fr'] = '-';
    }
}
$maxUid = $allRecent ? (int)$allRecent[0]['id_user'] : 0;

// ============================================================
// MAILS REÇUS — confirmes (contact_messages) + non confirmes (contact_confirm_tokens)
// ============================================================
$msgsConfirmed = [];
$msgsConfirmedUnread = [];
$msgsConfirmedRead = [];
$msgsUnconfirmed = [];
$totalUnread = 0;

$resM = $conn->query("SELECT id_msg, ip, nom, email, message, lu, created_at FROM contact_messages ORDER BY created_at DESC LIMIT 500");
if ($resM) {
    while ($row = $resM->fetch_assoc()) {
        $msgsConfirmed[] = $row;
        if ((int)$row['lu'] === 0) {
            $msgsConfirmedUnread[] = $row;
            $totalUnread++;
        } else {
            $msgsConfirmedRead[] = $row;
        }
    }
}
$totalRead = count($msgsConfirmedRead);

$resT = $conn->query("SELECT id, ip, nom, email, message, expires_at, created_at FROM contact_confirm_tokens WHERE used = 0 ORDER BY created_at DESC LIMIT 200");
if ($resT) {
    while ($row = $resT->fetch_assoc()) {
        $row['expired'] = strtotime($row['expires_at']) < time();
        $msgsUnconfirmed[] = $row;
    }
}
$totalConfirmed = count($msgsConfirmed);
$totalUnconfirmed = count($msgsUnconfirmed);
$totalMails = $totalConfirmed + $totalUnconfirmed;

// Historique des mails envoyes (max 500)
$sentEmails = [];
$totalSent = 0;
$resSE = @$conn->query("SELECT id_sent, to_email, to_name, subject, body, source, ref_id, sent_by, success, sent_at FROM sent_emails ORDER BY sent_at DESC LIMIT 500");
if ($resSE) {
    while ($row = $resSE->fetch_assoc()) {
        $sentEmails[] = $row;
    }
    $totalSent = count($sentEmails);
}
$sourceLabels = [
    'reply_message' => 'Reponse message',
    'send_to_user' => 'Mail a un inscrit',
    'reply_report' => 'Reponse signalement',
];

// ============================================================
// SIGNALEMENTS PROFIL
// ============================================================
$profileReports = [];
$resR = $conn->query("SELECT pr.*, COALESCE(a.visible, 1) as athlete_visible FROM profile_reports pr LEFT JOIN athletes a ON a.athlete_id_externe = pr.athlete_id_ext ORDER BY pr.created_at DESC LIMIT 500");
if ($resR) {
    while ($row = $resR->fetch_assoc()) $profileReports[] = $row;
}
$reportNew = array_values(array_filter($profileReports, function($r) { return $r['status'] === 'new'; }));
$reportRead = array_values(array_filter($profileReports, function($r) { return $r['status'] === 'read'; }));
$reportResolved = array_values(array_filter($profileReports, function($r) { return $r['status'] === 'resolved'; }));
$totalReports = count($profileReports);
$totalReportsNew = count($reportNew);

$reasonLabels = [
    'retrait' => 'Retrait profil',
    'donnees_incorrectes' => 'Donnees incorrectes',
    'usurpation' => 'Usurpation identite',
    'vie_privee' => 'Vie privee',
    'autre' => 'Autre'
];

// ============================================================
// VISITEURS — aujourd'hui vs hier (IPs uniques depuis logs)
// ============================================================
$visToday = 0;
$visYesterday = 0;
$resV = $conn->query("SELECT
    COUNT(DISTINCT CASE WHEN DATE(ts) = CURDATE() THEN ip END) AS today,
    COUNT(DISTINCT CASE WHEN DATE(ts) = CURDATE() - INTERVAL 1 DAY THEN ip END) AS yesterday
    FROM logs WHERE ts >= CURDATE() - INTERVAL 1 DAY AND ip <> ''");
if ($resV && $rowV = $resV->fetch_assoc()) {
    $visToday = (int)$rowV['today'];
    $visYesterday = (int)$rowV['yesterday'];
}
$visDiff = $visToday - $visYesterday;
if ($visYesterday > 0) {
    $visPct = ($visDiff / $visYesterday) * 100;
    $visPctTxt = ($visDiff >= 0 ? '+' : '') . number_format($visPct, 1, ',', ' ') . ' %';
    $visTrend = $visDiff > 0 ? 'up' : ($visDiff < 0 ? 'down' : 'flat');
} elseif ($visToday > 0) {
    $visPctTxt = 'Nouveau';
    $visTrend = 'up';
} else {
    $visPctTxt = '—';
    $visTrend = 'flat';
}
$visDiffAbs = abs($visDiff);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Panel 2 — Emails inscrits</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/svg+xml" href="../favicon.svg?v=2">
<link rel="shortcut icon" type="image/svg+xml" href="../favicon.svg?v=2">
<link rel="icon" type="image/png" sizes="32x32" href="../favicon-32x32.png?v=2">
<link rel="icon" type="image/png" sizes="16x16" href="../favicon-16x16.png?v=2">
<link rel="apple-touch-icon" href="../favicon.svg?v=2">
<style>
* { box-sizing: border-box; margin: 0; padding: 0; }
body {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background: #0d1117;
    color: #c9d1d9;
    padding: 24px;
    line-height: 1.5;
}
.wrap { max-width: 1100px; margin: 0 auto; }
header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding-bottom: 16px;
    border-bottom: 1px solid #30363d;
    margin-bottom: 24px;
}
h1 { font-size: 22px; color: #f0f6fc; }
h1 .badge {
    display: inline-block;
    background: #6c5ce7;
    color: #fff;
    font-size: 12px;
    padding: 2px 8px;
    border-radius: 10px;
    margin-left: 8px;
    vertical-align: middle;
}
.logout {
    color: #f85149;
    text-decoration: none;
    font-size: 14px;
    padding: 6px 12px;
    border: 1px solid #f85149;
    border-radius: 6px;
}
.logout:hover { background: #f85149; color: #fff; }

/* Notifications inscriptions en attente */
@keyframes notifBlink {
    0%, 100% { box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.6); border-color: #f59e0b; }
    50% { box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); border-color: #fbbf24; }
}
#notifBlock { margin-bottom: 22px; }
#notifBlock.all-read { display: none; }
.notif-header {
    display: flex; justify-content: space-between; align-items: center;
    background: #1c1606;
    border: 2px solid #f59e0b;
    border-radius: 10px 10px 0 0;
    padding: 12px 18px;
    border-bottom: none;
}
.notif-header-text { display: flex; align-items: center; gap: 10px; }
.notif-badge {
    display: inline-block;
    background: #f59e0b; color: #1c1606;
    font-weight: 800; font-size: 13px;
    min-width: 28px; height: 28px;
    border-radius: 14px;
    padding: 0 10px; line-height: 28px;
    text-align: center;
}
.notif-header-label {
    font-size: 13px; color: #fbbf24;
    font-weight: 600;
}
.notif-btn {
    background: #f59e0b; color: #1c1606;
    border: none; padding: 8px 14px;
    border-radius: 6px; cursor: pointer;
    font-weight: 700; font-size: 12px;
    white-space: nowrap;
    text-transform: uppercase;
}
.notif-btn:hover { background: #fbbf24; }

#notifList {
    border: 2px solid #f59e0b;
    border-top: none;
    border-radius: 0 0 10px 10px;
    max-height: 380px; overflow-y: auto;
    background: #100c02;
}
.notif {
    display: flex; align-items: center; gap: 14px;
    background: #1c1606;
    border-bottom: 1px solid #30363d;
    padding: 12px 18px;
    animation: notifBlink 1.8s ease-in-out infinite;
}
.notif:last-child { border-bottom: none; }
.notif.read { display: none; }
.notif-icon { font-size: 22px; }
.notif-body { flex: 1; }
.notif-title {
    font-size: 10px; font-weight: 700;
    color: #f59e0b; text-transform: uppercase;
    letter-spacing: 0.5px; margin-bottom: 2px;
}
.notif-content { font-size: 14px; color: #f0f6fc; }
.notif-meta { color: #8b949e; font-size: 13px; }

/* Onglets principaux */
.tabs-main {
    display: flex; gap: 4px; flex-wrap: wrap; align-items: center;
    border-bottom: 2px solid #30363d;
    margin-bottom: 20px;
}
/* Libellé de groupe + séparateur dans la barre d'onglets */
.tab-group-label {
    color: #6e7681; font-size: 10px; font-weight: 800; text-transform: uppercase;
    letter-spacing: 1px; padding: 0 8px 0 14px; align-self: center;
}
.tab-group-label:first-child { padding-left: 0; }
.tab-main {
    background: none; border: none;
    color: #8b949e; cursor: pointer;
    padding: 12px 18px; font-size: 14px; font-weight: 600;
    border-bottom: 2px solid transparent;
    margin-bottom: -2px;
    display: flex; align-items: center; gap: 8px;
}
.tab-main:hover { color: #c9d1d9; }
.tab-main.active { color: #58a6ff; border-bottom-color: #58a6ff; }
.tab-main .cnt {
    background: #21262d; color: #c9d1d9;
    padding: 2px 8px; border-radius: 10px;
    font-size: 11px; font-weight: 600;
}
.tab-main.active .cnt { background: #1f6feb33; color: #58a6ff; }

.tab-pane { display: none; }
.tab-pane.active { display: block; }

/* ── Accordéon : repli des grosses sections ───────────────────────── */
.bk-acc-head {
    cursor: pointer; position: relative; user-select: none;
}
.bk-acc-head .bk-chev {
    display: inline-block; width: 18px; height: 18px; line-height: 18px;
    text-align: center; margin-right: 8px; color: #8b949e;
    transition: transform .2s ease; font-size: 11px; flex: 0 0 auto;
}
.bk-acc.collapsed .bk-acc-head .bk-chev { transform: rotate(-90deg); }
.bk-acc.collapsed > *:not(.bk-acc-head) { display: none !important; }
.bk-acc.collapsed { padding-bottom: 14px !important; }
.bk-acc-head:hover .bk-chev { color: #58a6ff; }
/* Barre "tout replier / déplier" en haut de chaque onglet */
.bk-acc-toolbar { display: flex; justify-content: flex-end; margin-bottom: 12px; }
.bk-acc-toolbar button {
    background: #21262d; border: 1px solid #30363d; color: #c9d1d9;
    font-size: 12px; font-weight: 600; padding: 6px 12px; border-radius: 7px;
    cursor: pointer;
}
.bk-acc-toolbar button:hover { border-color: #58a6ff; color: #58a6ff; }

/* Sous-onglets */
.tabs-sub {
    display: flex; gap: 4px;
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 8px;
    padding: 4px;
    margin-bottom: 16px;
    width: fit-content;
}
.tab-sub {
    background: none; border: none;
    color: #8b949e; cursor: pointer;
    padding: 8px 14px; font-size: 13px; font-weight: 600;
    border-radius: 6px;
    display: flex; align-items: center; gap: 6px;
}
.tab-sub:hover { color: #c9d1d9; }
.tab-sub.active { background: #1f6feb33; color: #58a6ff; }
.tab-sub .cnt {
    background: #21262d; color: #c9d1d9;
    padding: 1px 7px; border-radius: 10px;
    font-size: 10px; font-weight: 600;
}

.sub-pane { display: none; }
.sub-pane.active { display: block; }

.search {
    width: 100%;
    padding: 10px 14px;
    background: #0d1117;
    border: 1px solid #30363d;
    border-radius: 6px;
    color: #c9d1d9;
    font-size: 14px;
    margin-bottom: 16px;
}
.search:focus { outline: none; border-color: #58a6ff; }

.userTable {
    width: 100%;
    border-collapse: collapse;
    background: #161b22;
    border-radius: 8px;
    overflow: hidden;
}
th, td {
    padding: 10px 14px;
    text-align: left;
    border-bottom: 1px solid #21262d;
    font-size: 14px;
}
th {
    background: #0d1117;
    color: #8b949e;
    font-weight: 600;
    font-size: 12px;
    text-transform: uppercase;
}
tr:hover td { background: #1c2128; }
.role {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}
.role-athlete { background: #1f6feb33; color: #58a6ff; }
.role-coach { background: #3fb95033; color: #3fb950; }
.role-club { background: #d2a8ff33; color: #d2a8ff; }
.role-admin { background: #f8514933; color: #f85149; }

.user-cell {
    display: flex; align-items: center; gap: 10px;
}
.user-avatar {
    width: 36px; height: 36px;
    border-radius: 50%;
    border: 1px solid #30363d;
    object-fit: cover;
    flex-shrink: 0;
    background: #21262d;
}
.user-avatar-fallback {
    display: flex; align-items: center; justify-content: center;
    background: linear-gradient(135deg, #6c5ce7, #5541d0);
    color: #fff;
    font-weight: 700;
    font-size: 14px;
}
.user-info { line-height: 1.3; }
.user-email { font-size: 13px; color: #c9d1d9; }
.user-name { font-size: 11px; color: #8b949e; margin-top: 1px; }

/* Compteur alert */
.cnt-alert {
    display: inline-block;
    background: #f85149; color: #fff;
    padding: 2px 8px; border-radius: 10px;
    font-size: 10px; font-weight: 700;
    margin-left: 4px;
}

/* Messages */
.msg-list { display: flex; flex-direction: column; gap: 12px; }
.msg-group-head {
    display: flex; align-items: center; gap: 8px;
    font-size: 13px; font-weight: 700; text-transform: uppercase;
    letter-spacing: 0.4px; margin-bottom: 12px; padding-bottom: 6px;
    border-bottom: 1px solid #30363d;
}
.msg-group-unread { color: #58a6ff; }
.msg-group-read { color: #8b949e; }
.msg-group-dot {
    width: 8px; height: 8px; border-radius: 50%;
    background: #58a6ff; box-shadow: 0 0 6px #58a6ff;
}
.msg-group-cnt {
    background: #21262d; color: inherit;
    padding: 1px 9px; border-radius: 10px;
    font-size: 11px; font-weight: 700;
}
.msg-group-unread .msg-group-cnt { background: #1f6feb33; }
.msg-card {
    background: #161b22; border: 1px solid #30363d;
    border-radius: 10px; padding: 14px 18px;
    transition: border-color 0.15s;
}
.msg-card.msg-unread { border-left: 3px solid #58a6ff; background: #1c2128; }
.msg-card.msg-unconfirmed { border-left: 3px solid #f59e0b; }
.msg-card.msg-expired { opacity: 0.65; }
.msg-head {
    display: flex; justify-content: space-between; align-items: center;
    margin-bottom: 8px; flex-wrap: wrap; gap: 8px;
}
.msg-from strong { color: #f0f6fc; font-size: 14px; }
.msg-from .muted { font-size: 13px; margin-left: 8px; }
.msg-tags { display: flex; gap: 4px; }
.msg-tag {
    padding: 2px 8px; border-radius: 10px;
    font-size: 10px; font-weight: 700; text-transform: uppercase;
}
.tag-blue { background: #1f6feb33; color: #58a6ff; }
.tag-gray { background: #21262d; color: #8b949e; }
.tag-orange { background: #f59e0b33; color: #f59e0b; }
.tag-red { background: #f8514933; color: #f85149; }
.tag-green { background: #3fb95033; color: #3fb950; }

/* Cartes de signalement */
.rep-card { background: #161b22; border: 1px solid #30363d; border-radius: 10px; padding: 14px 18px; }
.rep-card.rep-new { border-left: 3px solid #f85149; background: #1c2128; }
.rep-card.rep-read { border-left: 3px solid #f59e0b; }
.rep-card.rep-resolved { border-left: 3px solid #3fb950; opacity: 0.85; }
.rep-head { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px; margin-bottom: 8px; }
.rep-link { color: #d2a8ff; font-weight: 700; font-size: 14px; text-decoration: none; }
.rep-link:hover { text-decoration: underline; }
.rep-tags { display: flex; gap: 4px; flex-wrap: wrap; }
.btn-hide { color: #f59e0b; border-color: #f59e0b55; }
.btn-hide:hover { background: #f59e0b33; }
.btn-show { color: #58a6ff; border-color: #1f6feb55; }
.btn-show:hover { background: #1f6feb33; }
.msg-body {
    background: #0d1117; border: 1px solid #21262d;
    padding: 10px 14px; border-radius: 6px;
    font-size: 13px; color: #c9d1d9; line-height: 1.5;
    white-space: pre-wrap; word-break: break-word;
    margin-bottom: 8px;
}
.msg-meta {
    display: flex; gap: 14px; flex-wrap: wrap;
    font-size: 11px; color: #8b949e;
    margin-bottom: 10px;
}
.msg-actions { display: flex; gap: 6px; flex-wrap: wrap; }
.btn-msg {
    padding: 5px 12px; font-size: 12px; font-weight: 600;
    border: 1px solid #30363d;
    background: #21262d; color: #c9d1d9;
    border-radius: 6px; cursor: pointer;
    text-decoration: none;
    display: inline-block;
}
.btn-msg:hover { background: #30363d; }
.btn-read { color: #58a6ff; border-color: #1f6feb55; }
.btn-read:hover { background: #1f6feb33; }
.btn-unread { color: #8b949e; }
.btn-reply { color: #3fb950; border-color: #3fb95055; }
.btn-reply:hover { background: #3fb95033; }
.btn-del { color: #f85149; border-color: #f8514955; }
.btn-del:hover { background: #f8514933; }
.nb-act {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 10px;
    background: #21262d;
    color: #6e7681;
    font-weight: 600;
    font-size: 12px;
    font-variant-numeric: tabular-nums;
}
.nb-act.on { background: #1f6feb33; color: #58a6ff; }

/* Tri de colonnes */
.userTable th.sortable {
    cursor: pointer;
    user-select: none;
    transition: color 0.15s;
}
.userTable th.sortable:hover { color: #58a6ff; }
.userTable th.sortable .arr {
    display: inline-block;
    margin-left: 4px;
    font-size: 10px;
    opacity: 0.4;
}
.userTable th.sortable.active { color: #58a6ff; }
.userTable th.sortable.active .arr { opacity: 1; }
.oauth {
    font-size: 11px;
    color: #8b949e;
    background: #21262d;
    padding: 2px 6px;
    border-radius: 4px;
}
.muted { color: #6e7681; font-size: 12px; }

.btn-act {
    background: #21262d;
    color: #58a6ff;
    border: 1px solid #30363d;
    padding: 4px 12px;
    border-radius: 6px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
}
.btn-act:hover { background: #1f6feb33; border-color: #58a6ff; }

/* Drawer */
.act-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,0.5);
    display: none; z-index: 50;
}
.act-backdrop.open { display: block; }
.act-drawer {
    position: fixed; top: 0; right: 0;
    width: 720px; max-width: 95vw; height: 100vh;
    background: #161b22;
    border-left: 1px solid #30363d;
    transform: translateX(100%);
    transition: transform 0.25s ease;
    z-index: 60;
    display: flex; flex-direction: column;
}
.act-drawer.open { transform: translateX(0); }
.act-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 16px 20px;
    border-bottom: 1px solid #30363d;
}
.act-head h2 { font-size: 18px; color: #f0f6fc; }
.act-close {
    background: none; border: none;
    color: #8b949e; font-size: 28px;
    cursor: pointer; line-height: 1;
}
.act-close:hover { color: #f85149; }
.act-body { flex: 1; overflow-y: auto; padding: 16px 20px; }

.act-kpis { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; }
.act-kpi {
    background: #0d1117; border: 1px solid #30363d;
    padding: 12px; border-radius: 6px; text-align: center;
}
.act-kpi .v { font-size: 22px; font-weight: 700; color: #58a6ff; }
.act-kpi .l { font-size: 11px; color: #8b949e; text-transform: uppercase; }

.act-h3 {
    font-size: 13px; color: #8b949e; text-transform: uppercase;
    margin: 20px 0 10px; font-weight: 600;
}
.act-actions { display: flex; flex-wrap: wrap; gap: 6px; }
.act-tag {
    background: #1f6feb22; color: #58a6ff;
    padding: 4px 10px; border-radius: 12px; font-size: 12px;
}
.act-tag b { color: #f0f6fc; margin-left: 4px; }

.act-table { width: 100%; border-collapse: collapse; font-size: 12px; }
.act-table th {
    background: #0d1117; color: #8b949e;
    padding: 6px 8px; text-align: left;
    font-size: 10px; text-transform: uppercase;
}
.act-table td {
    padding: 6px 8px; border-bottom: 1px solid #21262d;
    vertical-align: top;
}
.act-act {
    background: #21262d; color: #d2a8ff;
    padding: 2px 6px; border-radius: 4px;
    font-size: 11px; font-family: monospace;
}
.act-page { color: #c9d1d9; word-break: break-all; }
.act-link {
    color: #58a6ff; text-decoration: none;
    font-weight: 600; font-size: 13px;
    display: inline-block;
}
.act-link:hover { text-decoration: underline; color: #79b8ff; }
.act-link::after { content: ' \2197'; font-size: 10px; opacity: 0.6; }
.act-url {
    color: #6e7681; font-size: 10px; margin-top: 2px;
    word-break: break-all; font-family: monospace;
}

/* Suivi navigation — section dediee (chaque email depliable) */
.nav-user { border: 1px solid #30363d; border-radius: 10px; margin-bottom: 8px; overflow: hidden; background: #0d1117; }
.nav-user-head {
    display: flex; align-items: center; gap: 12px;
    padding: 12px 14px; cursor: pointer; transition: background .15s;
}
.nav-user-head:hover { background: #161b22; }
.nav-user.open .nav-user-head { background: #161b22; border-bottom: 1px solid #30363d; }
.nav-user-caret { color: #8b949e; font-size: 12px; transition: transform .15s; flex-shrink: 0; }
.nav-user.open .nav-user-caret { transform: rotate(90deg); }
.nav-user-email {
    color: #f0f6fc; font-size: 14px; font-weight: 600;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.nav-user-badge {
    font-size: 11px; padding: 3px 8px; border-radius: 10px;
    background: #21262d; color: #8b949e; white-space: nowrap; flex-shrink: 0;
}
.nav-user-badge.on { background: #1f6feb33; color: #58a6ff; }
.nav-user-body { display: none; padding: 14px; }
.nav-user.open .nav-user-body { display: block; }
.nav-stats {
    display: flex; flex-wrap: wrap; gap: 14px; font-size: 12px; color: #8b949e;
    margin-bottom: 12px; padding-bottom: 10px; border-bottom: 1px solid #21262d;
}
.nav-stats b { color: #f0f6fc; }
.nav-day {
    font-size: 12px; font-weight: 700; color: #58a6ff;
    margin: 16px 0 6px; padding-bottom: 4px; border-bottom: 1px solid #21262d;
}
.nav-day:first-child { margin-top: 0; }

/* Reglages style profil */
.ps-block {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 10px;
    padding: 16px 18px;
    margin-bottom: 22px;
}
.ps-title {
    font-size: 13px;
    color: #f0f6fc;
    font-weight: 700;
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ps-desc { font-size: 12px; color: #8b949e; margin-bottom: 12px; }
.ps-options { display: flex; gap: 10px; flex-wrap: wrap; }
.ps-option {
    flex: 1; min-width: 220px;
    background: #0d1117;
    border: 2px solid #30363d;
    border-radius: 8px;
    padding: 12px 14px;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    display: flex;
    align-items: flex-start;
    gap: 10px;
}
.ps-option:hover { border-color: #484f58; }
.ps-option input[type="radio"] { margin-top: 3px; accent-color: #58a6ff; cursor: pointer; }
.ps-option.active { border-color: #58a6ff; background: #0d1f3a; }
.ps-option-name { font-size: 13px; font-weight: 700; color: #f0f6fc; }
.ps-option-sub { font-size: 11px; color: #8b949e; margin-top: 2px; line-height: 1.4; }
.ps-save {
    margin-top: 12px;
    background: #238636; color: #fff;
    border: none; padding: 8px 16px;
    border-radius: 6px; cursor: pointer;
    font-size: 13px; font-weight: 600;
}
.ps-save:hover { background: #2ea043; }
.ps-saved {
    display: inline-block; margin-left: 10px;
    color: #3fb950; font-size: 12px; font-weight: 600;
}

/* Visiteurs aujourd'hui vs hier */
.vis-stats {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 22px;
}
.vis-card {
    background: #161b22;
    border: 1px solid #30363d;
    border-radius: 10px;
    padding: 16px 18px;
    position: relative;
}
.vis-label {
    font-size: 11px;
    color: #8b949e;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    font-weight: 600;
    margin-bottom: 6px;
}
.vis-value {
    font-size: 30px;
    font-weight: 700;
    color: #f0f6fc;
    line-height: 1.1;
}
.vis-sub {
    font-size: 12px;
    color: #8b949e;
    margin-top: 4px;
}
.vis-card.trend-up { border-left: 3px solid #3fb950; }
.vis-card.trend-up .vis-value { color: #3fb950; }
.vis-card.trend-down { border-left: 3px solid #f85149; }
.vis-card.trend-down .vis-value { color: #f85149; }
.vis-card.trend-flat { border-left: 3px solid #8b949e; }
.vis-card.trend-flat .vis-value { color: #c9d1d9; }
.vis-arrow { font-size: 20px; margin-right: 6px; vertical-align: middle; }
@media (max-width: 700px) {
    .vis-stats { grid-template-columns: 1fr; }
}
</style>
</head>
<body>
<div class="wrap">
    <header>
        <h1>Panel 2 <span class="badge">v0.1 — Emails</span></h1>
        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
            <button type="button" id="bkCacheBtn" onclick="bkToggleCacheMenu(event)" style="padding:10px 20px;background:linear-gradient(135deg,#f97316,#ea580c);color:#fff;border:2px solid #fb923c;border-radius:10px;font-size:14px;font-weight:800;cursor:pointer;display:inline-flex;align-items:center;gap:8px;box-shadow:0 4px 14px rgba(249,115,22,0.4);text-transform:uppercase;letter-spacing:0.5px;transition:all 0.15s;position:relative;">
                &#128465; Vider le cache &#9662;
            </button>
            <a href="tools.php" style="padding:8px 16px;background:linear-gradient(135deg,#6c5ce7,#8b5cf6);color:#fff;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">&#9881; Outils admin</a>
            <a href="?logout=1" class="logout">Deconnexion</a>
        </div>
    </header>

    <!-- Menu deroulant cache (cache complet ou par categorie) -->
    <div id="bkCacheMenu" style="display:none;position:absolute;background:#161b22;border:2px solid #fb923c;border-radius:14px;padding:16px;box-shadow:0 20px 60px rgba(0,0,0,0.7);z-index:9999;min-width:460px;max-width:520px;">

        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;padding-bottom:10px;border-bottom:1px solid #30363d;">
            <div>
                <div style="font-size:14px;color:#fff;font-weight:800;">&#128465; Vider le cache</div>
                <div style="font-size:11px;color:#8b949e;margin-top:2px;">Force la regeneration des donnees au prochain affichage</div>
            </div>
            <button onclick="document.getElementById('bkCacheMenu').style.display='none';" style="background:transparent;border:none;color:#7a869a;font-size:18px;cursor:pointer;padding:4px 8px;line-height:1;">&times;</button>
        </div>

        <!-- ZONE 1 : Cache PROFILS (le plus utilise) -->
        <div style="background:linear-gradient(135deg,rgba(16,185,129,0.08),rgba(16,185,129,0.02));border:1px solid #10b981;border-radius:10px;padding:12px;margin-bottom:10px;">
            <div style="display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                <span style="font-size:18px;">&#127939;</span>
                <b style="color:#10b981;font-size:14px;">Profils athletes</b>
                <span style="background:#10b981;color:#000;font-size:9px;font-weight:800;padding:2px 7px;border-radius:10px;letter-spacing:0.5px;">RECOMMANDE APRES SCRAPE</span>
            </div>
            <div style="color:#8b949e;font-size:11.5px;line-height:1.5;margin-bottom:10px;">
                Vide les fiches profil mises en cache (24h). Apres un scrape, force tous les profils a se recharger avec les nouvelles donnees au prochain affichage.
            </div>
            <button onclick="bkClearCache('athlete', 'tous les profils athletes')" class="bk-cc-btn-primary">
                &#128293; Vider TOUS les profils athletes
            </button>
            <div style="margin-top:8px;display:flex;gap:6px;align-items:center;">
                <span style="font-size:11px;color:#7a869a;">Ou cibler 1 athlete :</span>
                <input type="number" id="bkCacheOneId" placeholder="ID athle.fr" style="flex:1;background:#0d1117;color:#fff;border:1px solid #30363d;padding:5px 8px;border-radius:5px;font-size:12px;min-width:0;">
                <button onclick="bkClearCacheOne()" style="background:#10b981;color:#000;border:none;padding:5px 12px;border-radius:5px;font-size:11px;font-weight:700;cursor:pointer;">OK</button>
            </div>
        </div>

        <!-- ZONE 2 : Autres caches -->
        <div style="font-size:10px;color:#7a869a;text-transform:uppercase;letter-spacing:0.7px;font-weight:700;margin:0 0 6px 4px;">Autres caches (par usage)</div>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:5px;">
            <button onclick="bkClearCache('search', 'cache des recherches')"     class="bk-cc-btn" title="api/search.php — resultats de recherche"><span>&#128270; Recherches</span><span class="bk-cc-sub">12 filtres</span></button>
            <button onclick="bkClearCache('clubstats', 'cache des clubs')"        class="bk-cc-btn" title="api/club_stats.php — fiche club detaillee"><span>&#127942; Clubs</span><span class="bk-cc-sub">fiches clubs</span></button>
            <button onclick="bkClearCache('villestats', 'cache des villes')"      class="bk-cc-btn" title="api/ville_stats.php — fiche ville"><span>&#127968; Villes</span><span class="bk-cc-sub">fiches villes</span></button>
            <button onclick="bkClearCache('ep', 'cache des epreuves')"            class="bk-cc-btn" title="api/epreuve_stats.php — fiche epreuve"><span>&#127942; Epreuves</span><span class="bk-cc-sub">fiches epreuves</span></button>
            <button onclick="bkClearCache('stats', 'stats globales accueil')"     class="bk-cc-btn" title="api/stats.php — KPIs accueil"><span>&#128202; Stats globales</span><span class="bk-cc-sub">accueil + top</span></button>
            <button onclick="bkClearCache('liste', 'liste athletes paginee')"     class="bk-cc-btn" title="api/liste.php — page Athletes"><span>&#128203; Liste athletes</span><span class="bk-cc-sub">page /athletes</span></button>
            <button onclick="bkClearCache('topsearched', 'top recherches accueil')" class="bk-cc-btn" title="api/top_searched.php — top clubs/athletes consultes"><span>&#128293; Top recherche</span><span class="bk-cc-sub">accueil consultes</span></button>
            <button onclick="bkClearCache('athlete_similar', 'profils similaires')" class="bk-cc-btn" title="api/similar.php — athletes similaires"><span>&#128101; Similaires</span><span class="bk-cc-sub">profil similar</span></button>
        </div>

        <!-- ZONE 3 : Tout vider -->
        <button onclick="bkClearCache('', 'TOUT le cache')" class="bk-cc-btn-all" style="margin-top:12px;width:100%;">
            &#9888; TOUT vider d'un coup (tous prefixes)
        </button>

        <div id="bkCacheStatus" style="margin-top:10px;padding:8px 10px;border-radius:6px;font-size:12px;font-family:'JetBrains Mono',monospace;min-height:0;display:none;"></div>
    </div>

    <style>
    #bkCacheBtn:hover { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(249,115,22,0.6); }
    .bk-cc-btn {
        background: #21262d; color: #d9e1ec; border: 1px solid #30363d;
        padding: 9px 10px; border-radius: 6px; font-size: 12px; font-weight: 600;
        cursor: pointer; text-align: left; transition: all 0.15s;
        font-family: inherit; display: flex; flex-direction: column; gap: 2px;
    }
    .bk-cc-btn:hover { background: #2d333b; border-color: #fb923c; color: #fff; transform: translateX(2px); }
    .bk-cc-btn .bk-cc-sub { font-size: 9.5px; color: #7a869a; font-weight: 400; text-transform: none; letter-spacing: 0; }
    .bk-cc-btn-primary {
        background: linear-gradient(135deg,#10b981,#059669); color: #000; border: none;
        padding: 10px 14px; border-radius: 8px; font-size: 13px; font-weight: 800;
        cursor: pointer; width: 100%; text-align: center; transition: all 0.15s;
        font-family: inherit; box-shadow: 0 4px 12px rgba(16,185,129,0.3);
        text-transform: uppercase; letter-spacing: 0.4px;
    }
    .bk-cc-btn-primary:hover { transform: translateY(-1px); box-shadow: 0 6px 16px rgba(16,185,129,0.5); }
    .bk-cc-btn-all {
        background: linear-gradient(135deg,#dc2626,#991b1b); color: #fff; border: 2px solid #ef4444;
        padding: 11px; border-radius: 8px; font-size: 13px; font-weight: 800;
        cursor: pointer; font-family: inherit; transition: all 0.15s;
        text-transform: uppercase; letter-spacing: 0.5px;
    }
    .bk-cc-btn-all:hover { transform: scale(1.02); box-shadow: 0 6px 18px rgba(239,68,68,0.6); }
    </style>

    <script>
    function bkToggleCacheMenu(e) {
        e.stopPropagation();
        var menu = document.getElementById('bkCacheMenu');
        var btn = document.getElementById('bkCacheBtn');
        if (menu.style.display === 'block') {
            menu.style.display = 'none';
            return;
        }
        var rect = btn.getBoundingClientRect();
        menu.style.top = (rect.bottom + window.scrollY + 6) + 'px';
        // Aligne le menu a droite du bouton pour ne pas deborder
        menu.style.right = (window.innerWidth - rect.right) + 'px';
        menu.style.left = 'auto';
        menu.style.display = 'block';
    }
    document.addEventListener('click', function(e) {
        var menu = document.getElementById('bkCacheMenu');
        if (menu && menu.style.display === 'block' && !menu.contains(e.target) && e.target.id !== 'bkCacheBtn' && e.target.closest && !e.target.closest('#bkCacheBtn')) {
            menu.style.display = 'none';
        }
    });

    async function bkClearCache(prefix, humanLabel) {
        if (!confirm('Vider ' + humanLabel + ' ?\n\nLes prochaines requetes regenereront les fichiers manquants automatiquement.\n\nAction irreversible mais sans danger.')) return;
        var statusEl = document.getElementById('bkCacheStatus');
        statusEl.style.display = 'block';
        statusEl.style.background = 'rgba(251,146,60,0.1)';
        statusEl.style.border = '1px solid #fb923c';
        statusEl.innerHTML = '<span style="color:#fb923c;">&#9203; Vidage en cours...</span>';
        var url = '/BK/admin/clear_cache.php' + (prefix ? '?prefix=' + encodeURIComponent(prefix) : '');
        if (window.location.hostname.indexOf('bokonzi') !== -1) {
            url = '/admin/clear_cache.php' + (prefix ? '?prefix=' + encodeURIComponent(prefix) : '');
        }
        try {
            var t0 = Date.now();
            var r = await fetch(url, { method: 'GET' });
            var d = null;
            try { d = await r.json(); } catch (e) {}
            var dur = ((Date.now() - t0) / 1000).toFixed(1);
            if (d && d.success) {
                statusEl.style.background = 'rgba(16,185,129,0.1)';
                statusEl.style.border = '1px solid #10b981';
                statusEl.innerHTML = '<b style="color:#10b981;">&#10003; Vidage OK</b><br>'
                    + '<span style="color:#d9e1ec;">' + d.deleted + ' fichier' + (d.deleted > 1 ? 's' : '') + ' supprime' + (d.deleted > 1 ? 's' : '') + '</span> '
                    + '<span style="color:#7a869a;">(prefixe : ' + (d.prefix || '*') + ' &mdash; ' + dur + 's)</span><br>'
                    + '<span style="color:#a78bfa;font-size:11px;">Recharge la page concernee pour voir les nouvelles donnees.</span>';
            } else {
                statusEl.style.background = 'rgba(239,68,68,0.1)';
                statusEl.style.border = '1px solid #ef4444';
                statusEl.innerHTML = '<b style="color:#ef4444;">&#9888; Erreur</b> ' + (d && d.error ? d.error : 'Reponse inattendue');
            }
            setTimeout(function() {
                statusEl.style.transition = 'opacity 0.5s';
                statusEl.style.opacity = '0';
                setTimeout(function() {
                    statusEl.style.display = 'none';
                    statusEl.style.opacity = '1';
                }, 500);
            }, 6000);
        } catch (err) {
            statusEl.style.background = 'rgba(239,68,68,0.1)';
            statusEl.style.border = '1px solid #ef4444';
            statusEl.innerHTML = '<b style="color:#ef4444;">&#9888; Erreur reseau :</b> ' + err.message;
        }
    }

    // Vider le cache d'UN seul athlete (par athlete_id_externe)
    async function bkClearCacheOne() {
        var id = document.getElementById('bkCacheOneId').value.trim();
        if (!id || !/^\d+$/.test(id)) {
            alert('Saisis un ID athle.fr valide (chiffres uniquement).\nExemple : 2569767');
            return;
        }
        // Le cache athlete.json est nomme avec un md5 → on ne peut pas cibler 1 athlete precis sans regenerer la liste.
        // Strategie : on appelle directement l'API athlete avec ?_all=1 qui bypass le cache ET recree l'entry.
        var statusEl = document.getElementById('bkCacheStatus');
        statusEl.style.display = 'block';
        statusEl.style.background = 'rgba(251,146,60,0.1)';
        statusEl.style.border = '1px solid #fb923c';
        statusEl.innerHTML = '<span style="color:#fb923c;">&#9203; Regeneration du profil ' + id + '...</span>';

        var base = window.location.hostname.indexOf('bokonzi') !== -1 ? '' : '/BK';
        try {
            var t0 = Date.now();
            var r = await fetch(base + '/api/athlete.php?id=' + encodeURIComponent(id) + '&_all=1&_t=' + Date.now());
            var d = await r.json();
            var dur = ((Date.now() - t0) / 1000).toFixed(1);
            if (d && d.success !== false && d.identite) {
                statusEl.style.background = 'rgba(16,185,129,0.1)';
                statusEl.style.border = '1px solid #10b981';
                var nom = d.identite.nom_complet || ('ID ' + id);
                statusEl.innerHTML = '<b style="color:#10b981;">&#10003; Profil regenere</b><br>'
                    + '<span style="color:#d9e1ec;">' + nom + '</span> '
                    + '<span style="color:#7a869a;">(' + dur + 's)</span><br>'
                    + '<a href="' + base + '/recherche?page=profil&id=' + id + '&_t=' + Date.now() + '" target="_blank" style="color:#a78bfa;font-size:11px;">Ouvrir le profil &rarr;</a>';
            } else {
                statusEl.style.background = 'rgba(239,68,68,0.1)';
                statusEl.style.border = '1px solid #ef4444';
                statusEl.innerHTML = '<b style="color:#ef4444;">&#9888; Athlete introuvable</b> (ID ' + id + ')';
            }
        } catch (err) {
            statusEl.style.background = 'rgba(239,68,68,0.1)';
            statusEl.style.border = '1px solid #ef4444';
            statusEl.innerHTML = '<b style="color:#ef4444;">&#9888; Erreur :</b> ' + err.message;
        }
    }
    </script>

    <!-- Overlay loader XL pour formulaire athletes -->
    <div id="afLoaderOverlay" style="display:none;position:fixed;inset:0;background:radial-gradient(circle at center,rgba(13,17,23,0.96),rgba(0,0,0,0.99));z-index:99999;align-items:center;justify-content:center;backdrop-filter:blur(8px);">
        <div style="background:linear-gradient(135deg,#161b22 0%,#0d1117 100%);border:2px solid #6c5ce7;border-radius:24px;padding:60px 70px;max-width:720px;width:92vw;text-align:center;box-shadow:0 40px 100px rgba(108,92,231,0.4),0 0 0 1px rgba(167,139,250,0.2),inset 0 1px 0 rgba(255,255,255,0.05);position:relative;overflow:hidden;">

            <!-- Glow effects -->
            <div style="position:absolute;top:-80px;right:-80px;width:300px;height:300px;background:radial-gradient(circle,rgba(167,139,250,0.25),transparent 70%);border-radius:50%;pointer-events:none;animation:afGlowPulse 3s ease-in-out infinite;"></div>
            <div style="position:absolute;bottom:-80px;left:-80px;width:280px;height:280px;background:radial-gradient(circle,rgba(236,72,153,0.2),transparent 70%);border-radius:50%;pointer-events:none;animation:afGlowPulse 3s ease-in-out infinite reverse;"></div>

            <!-- Spinner XL avec double anneau -->
            <div style="position:relative;width:140px;height:140px;margin:0 auto 32px;">
                <div style="position:absolute;inset:0;border:6px solid #30363d;border-top-color:#a78bfa;border-right-color:#a78bfa;border-radius:50%;animation:afSpin 1.2s cubic-bezier(.68,-0.55,.27,1.55) infinite;"></div>
                <div style="position:absolute;inset:18px;border:5px solid #21262d;border-bottom-color:#ec4899;border-left-color:#ec4899;border-radius:50%;animation:afSpin 0.9s linear infinite reverse;"></div>
                <div style="position:absolute;inset:42px;background:linear-gradient(135deg,#6c5ce7,#ec4899);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px;animation:afHeartbeat 1.4s ease-in-out infinite;">&#127939;</div>
            </div>

            <h3 style="color:#fff;font-size:28px;font-weight:900;margin:0 0 12px;letter-spacing:0.5px;text-shadow:0 2px 12px rgba(167,139,250,0.4);">Recherche en cours</h3>
            <p style="color:#a78bfa;font-size:15px;font-weight:600;margin:0 0 8px;">&#128202; Traitement de <strong style="color:#fff;">+300 000 athletes</strong> en BDD</p>
            <p style="color:#8b949e;font-size:13px;margin:0 0 28px;font-style:italic;">Le filtre cible peut prendre quelques secondes selon le volume de donnees...</p>

            <!-- Barre de progression -->
            <div style="background:#0d1117;border:1px solid #30363d;border-radius:12px;padding:6px;margin-bottom:24px;overflow:hidden;">
                <div id="afLoaderProgressBar" style="height:8px;background:linear-gradient(90deg,#6c5ce7,#a78bfa,#ec4899,#a78bfa,#6c5ce7);background-size:200% 100%;border-radius:8px;width:0%;transition:width 0.4s ease;animation:afShimmer 2s linear infinite;"></div>
            </div>

            <div id="afLoaderSteps" style="text-align:left;font-size:14px;line-height:2.2;background:#0d1117;border:1px solid #30363d;border-radius:12px;padding:18px 24px;">
                <div class="af-step" data-step="1" style="color:#8b949e;display:flex;align-items:center;gap:10px;"><span class="af-step-icon" style="font-size:18px;width:24px;text-align:center;">&#9711;</span><span>Sauvegarde des filtres</span></div>
                <div class="af-step" data-step="2" style="color:#8b949e;display:flex;align-items:center;gap:10px;"><span class="af-step-icon" style="font-size:18px;width:24px;text-align:center;">&#9711;</span><span>Vidage du cache</span></div>
                <div class="af-step" data-step="3" style="color:#8b949e;display:flex;align-items:center;gap:10px;"><span class="af-step-icon" style="font-size:18px;width:24px;text-align:center;">&#9711;</span><span>Requete athletes hommes</span></div>
                <div class="af-step" data-step="4" style="color:#8b949e;display:flex;align-items:center;gap:10px;"><span class="af-step-icon" style="font-size:18px;width:24px;text-align:center;">&#9711;</span><span>Requete athletes femmes</span></div>
                <div class="af-step" data-step="5" style="color:#8b949e;display:flex;align-items:center;gap:10px;"><span class="af-step-icon" style="font-size:18px;width:24px;text-align:center;">&#9711;</span><span>Generation de l'apercu</span></div>
            </div>

            <div style="display:flex;justify-content:space-between;align-items:center;margin-top:24px;padding-top:18px;border-top:1px solid #30363d;flex-wrap:wrap;gap:10px;">
                <p style="color:#5a6580;font-size:12px;margin:0;">&#9201; Temps ecoule : <strong id="afElapsed" style="color:#a78bfa;font-size:14px;">0</strong>s</p>
                <p style="color:#5a6580;font-size:11px;margin:0;font-style:italic;">Bokonzi.com &middot; Indexation FFA</p>
            </div>
        </div>
    </div>
    <style>
        @keyframes afSpin { to { transform: rotate(360deg); } }
        @keyframes afGlowPulse { 0%, 100% { transform: scale(1); opacity: 0.7; } 50% { transform: scale(1.15); opacity: 1; } }
        @keyframes afHeartbeat { 0%, 100% { transform: scale(1); box-shadow: 0 0 30px rgba(167,139,250,0.6); } 50% { transform: scale(1.1); box-shadow: 0 0 50px rgba(236,72,153,0.8); } }
        @keyframes afShimmer { 0% { background-position: 0% 0%; } 100% { background-position: 200% 0%; } }
        .af-step.done { color: #34d399 !important; }
        .af-step.done .af-step-icon { color: #34d399 !important; }
        .af-step.active { color: #a78bfa !important; font-weight: 700; }
        .af-step.active .af-step-icon { color: #a78bfa !important; animation: afStepPulse 1s ease-in-out infinite; }
        @keyframes afStepPulse { 0%, 100% { opacity: 1; transform: scale(1); } 50% { opacity: 0.4; transform: scale(1.3); } }
    </style>

    <!-- Section Page Athletes (onglet) -->
    <section class="tab-pane active" data-pane="athletes-filter">
    <?php
    $allLevels = [
        'Internationaux' => ['IA','IB','IE','IR','IR2'],
        'Nationaux' => ['N1','N2','N3','N4'],
        'Regionaux' => ['R1','R2','R3','R4','R5','R6'],
        'Departementaux' => ['D1','D2','D3','D4','D5','D6','D7','D8'],
    ];
    $afNiveaux = $currentAthletesFilter['niveaux'];
    $afAnnee = $currentAthletesFilter['annee'];
    $currentY = (int)date('Y');
    ?>
    <form id="athletesFilter" method="POST" action="panel.php" style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:20px 22px;margin-bottom:20px;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;flex-wrap:wrap;gap:10px;">
            <h2 style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0;">&#127939; Page Athletes — Filtre public</h2>
            <?php if ($afSaved):
                if ($afCacheStatus === 'cleared'): ?>
                    <span style="background:#f59e0b20;color:#fbbf24;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:700;">&#10003; Enregistre &middot; Cache regenere (filtres changes)</span>
                <?php else: ?>
                    <span style="background:#10b98120;color:#34d399;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:700;">&#10003; Enregistre &middot; Cache conserve (juste l'apparence)</span>
                <?php endif;
            endif; ?>
        </div>
        <p style="color:#8b949e;font-size:13px;margin-bottom:18px;">Choisis les niveaux et l'annee a afficher sur la page <code style="color:#a78bfa;">/?page=athletes</code>. Affichera uniquement les athletes ayant une activite dans l'annee selectionnee.</p>

        <!-- Niveaux par groupes -->
        <div style="margin-bottom:18px;">
            <label style="display:block;color:#c9d1d9;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Niveaux a afficher</label>
            <?php foreach ($allLevels as $groupName => $groupLevels): ?>
            <div style="margin-bottom:10px;">
                <div style="color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;"><?= $groupName ?></div>
                <div style="display:flex;flex-wrap:wrap;gap:6px;">
                <?php foreach ($groupLevels as $lvl):
                    $checked = in_array($lvl, $afNiveaux, true);
                    $col = $lvl[0] === 'I' ? '#c026d3' : ($lvl[0] === 'N' ? '#e11d48' : ($lvl[0] === 'R' ? '#0891b2' : '#f97316'));
                ?>
                    <label style="display:inline-flex;align-items:center;gap:6px;padding:6px 12px;background:<?= $checked ? $col.'30' : '#0d1117' ?>;border:1px solid <?= $checked ? $col : '#30363d' ?>;border-radius:6px;cursor:pointer;transition:all .15s;font-size:13px;font-weight:600;color:<?= $checked ? '#fff' : '#8b949e' ?>;">
                        <input type="checkbox" name="niveaux[]" value="<?= $lvl ?>" <?= $checked ? 'checked' : '' ?> style="cursor:pointer;">
                        <?= $lvl ?>
                    </label>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Toutes les epreuves -->
        <div style="margin-bottom:18px;">
            <?php $afAllEp = !empty($currentAthletesFilter['all_epreuves']); ?>
            <label style="display:inline-flex;align-items:center;gap:8px;padding:8px 14px;background:<?= $afAllEp ? '#22d3ee30' : '#0d1117' ?>;border:1px solid <?= $afAllEp ? '#22d3ee' : '#30363d' ?>;border-radius:8px;cursor:pointer;font-size:13px;font-weight:700;color:<?= $afAllEp ? '#fff' : '#8b949e' ?>;">
                <input type="checkbox" name="all_epreuves" id="afAllEpreuves" value="1" <?= $afAllEp ? 'checked' : '' ?> onchange="(function(cb){var l=cb.parentElement;var on=cb.checked;l.style.background=on?'#22d3ee30':'#0d1117';l.style.borderColor=on?'#22d3ee':'#30363d';l.style.color=on?'#fff':'#8b949e';})(this);" style="cursor:pointer;">
                &#127939; Toutes les epreuves
            </label>
            <p style="color:#5a6580;font-size:11px;margin:6px 0 0;font-style:italic;line-height:1.5;">Par defaut, la page <code style="color:#a78bfa;">/?page=athletes</code> ne liste que les athletes ayant un record en 100m, 200m, haies, Longueur, Triple saut ou Perche. Coche cette case pour inclure <strong style="color:#22d3ee;">toutes les epreuves</strong> (400m, 800m, lancers, hauteur...). Ignore si un filtre cible epreuve est actif ci-dessous.</p>
        </div>

        <!-- Annee + Nombres -->
        <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px;margin-bottom:18px;">
            <div>
                <label for="afAnnee" style="display:block;color:#c9d1d9;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">Annee d'activite</label>
                <select name="annee" id="afAnnee" style="width:100%;background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:10px 14px;color:#f0f6fc;font-size:14px;font-weight:600;cursor:pointer;<?= $afAnnee === 0 ? 'opacity:.45;pointer-events:none;' : '' ?>">
                    <?php for ($y = $currentY + 1; $y >= 2000; $y--): ?>
                        <option value="<?= $y ?>" <?= $afAnnee === $y ? 'selected' : '' ?>><?= $y ?><?= $y === $currentY ? ' (annee courante)' : '' ?></option>
                    <?php endfor; ?>
                </select>
                <label style="display:inline-flex;align-items:center;gap:8px;margin-top:8px;padding:6px 10px;background:<?= $afAnnee === 0 ? '#a78bfa30' : '#0d1117' ?>;border:1px solid <?= $afAnnee === 0 ? '#a78bfa' : '#30363d' ?>;border-radius:6px;cursor:pointer;font-size:12px;font-weight:600;color:<?= $afAnnee === 0 ? '#fff' : '#8b949e' ?>;">
                    <input type="checkbox" name="annee_all" id="afAnneeAll" value="1" <?= $afAnnee === 0 ? 'checked' : '' ?> onchange="(function(cb){var s=document.getElementById('afAnnee');if(cb.checked){s.style.opacity='.45';s.style.pointerEvents='none';cb.parentElement.style.background='#a78bfa30';cb.parentElement.style.borderColor='#a78bfa';cb.parentElement.style.color='#fff';}else{s.style.opacity='';s.style.pointerEvents='';cb.parentElement.style.background='#0d1117';cb.parentElement.style.borderColor='#30363d';cb.parentElement.style.color='#8b949e';}})(this);" style="cursor:pointer;">
                    Toutes les annees (pas de filtre)
                </label>
            </div>
            <div>
                <label for="afNbH" style="display:block;color:#3b82f6;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">&#9794; Nb hommes</label>
                <input type="number" name="nb_hommes" id="afNbH" min="0" max="200" value="<?= (int)$currentAthletesFilter['nb_hommes'] ?>" style="width:100%;background:#0d1117;border:1px solid #3b82f640;border-radius:8px;padding:10px 14px;color:#f0f6fc;font-size:14px;font-weight:600;box-sizing:border-box;">
            </div>
            <div>
                <label for="afNbF" style="display:block;color:#ec4899;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">&#9792; Nb femmes</label>
                <input type="number" name="nb_femmes" id="afNbF" min="0" max="200" value="<?= (int)$currentAthletesFilter['nb_femmes'] ?>" style="width:100%;background:#0d1117;border:1px solid #ec489940;border-radius:8px;padding:10px 14px;color:#f0f6fc;font-size:14px;font-weight:600;box-sizing:border-box;">
            </div>
        </div>

        <!-- ============================================================ -->
        <!-- MISE EN AVANT — Athletes en lumiere (juste apres Annee) -->
        <!-- ============================================================ -->
        <div id="featuredAthletes" style="background:linear-gradient(135deg,#7f1d1d25,#450a0a15);border:2px solid #dc2626;border-radius:12px;padding:18px 20px;margin-bottom:22px;box-shadow:0 4px 20px rgba(220,38,38,0.25);">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                <div style="display:flex;align-items:center;gap:10px;">
                    <span style="font-size:22px;">&#11088;</span>
                    <h3 style="color:#fca5a5;font-size:16px;font-weight:800;margin:0;letter-spacing:0.3px;text-shadow:0 1px 4px rgba(220,38,38,0.4);">Mise en avant — Athletes en lumiere</h3>
                </div>
                <?php if ($ftSaved): ?>
                <span style="background:#10b98120;color:#34d399;padding:4px 10px;border-radius:6px;font-size:11px;font-weight:700;">&#10003; Enregistre</span>
                <?php endif; ?>
            </div>
            <p style="color:#fca5a5;font-size:12px;margin:0 0 14px;line-height:1.5;">Selection editoriale affichee en haut de <code style="color:#f87171;">/?page=athletes</code>, au-dessus du listing filtre.</p>

            <!-- Toggle ON/OFF -->
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;padding:10px 14px;background:#0d1117;border:1px solid #30363d;border-radius:10px;">
                <label class="ft-toggle" style="position:relative;display:inline-block;width:44px;height:24px;flex-shrink:0;">
                    <input type="checkbox" name="feat_enabled" id="ftEnabled" value="1" <?= !empty($currentFeatured['enabled']) ? 'checked' : '' ?> style="opacity:0;width:0;height:0;">
                    <span class="ft-slider" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:<?= !empty($currentFeatured['enabled']) ? '#10b981' : '#30363d' ?>;border-radius:24px;transition:.3s;"></span>
                    <span class="ft-knob" style="position:absolute;height:18px;width:18px;left:<?= !empty($currentFeatured['enabled']) ? '23px' : '3px' ?>;bottom:3px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></span>
                </label>
                <div style="flex:1;min-width:0;">
                    <div id="ftToggleLabel" style="color:<?= !empty($currentFeatured['enabled']) ? '#34d399' : '#8b949e' ?>;font-size:13px;font-weight:700;">
                        <?= !empty($currentFeatured['enabled']) ? 'Mise en avant ACTIVE' : 'Mise en avant DESACTIVEE' ?>
                    </div>
                    <div style="color:#8b949e;font-size:10px;">Toggle pour afficher / masquer la section publique</div>
                </div>
            </div>

            <!-- Titre + Recherche live cote a cote -->
            <div style="display:grid;grid-template-columns:1fr 1.2fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label for="ftTitle" style="display:block;color:#c9d1d9;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Titre de la section</label>
                    <input type="text" name="feat_title" id="ftTitle" maxlength="80" value="<?= htmlspecialchars($currentFeatured['title']) ?>" placeholder="Athletes en lumiere" style="width:100%;background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:9px 12px;color:#f0f6fc;font-size:13px;font-weight:600;box-sizing:border-box;">
                </div>
                <div style="position:relative;">
                    <label for="ftSearch" style="display:block;color:#c9d1d9;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Ajouter un athlete</label>
                    <input type="text" id="ftSearch" autocomplete="off" placeholder="Tape un nom..." style="width:100%;background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:9px 12px 9px 32px;color:#f0f6fc;font-size:13px;box-sizing:border-box;">
                    <span style="position:absolute;left:10px;top:30px;color:#8b949e;font-size:13px;">&#128269;</span>
                    <div id="ftResults" style="position:absolute;top:calc(100% + 2px);left:0;right:0;background:#0d1117;border:1px solid #30363d;border-radius:8px;max-height:260px;overflow-y:auto;z-index:50;display:none;box-shadow:0 8px 24px rgba(0,0,0,0.5);"></div>
                </div>
            </div>

            <!-- Liste des athletes selectionnes -->
            <div>
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
                    <label style="color:#c9d1d9;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:1px;">Liste actuelle (<span id="ftCount"><?= count($currentFeatured['athletes']) ?></span>)</label>
                    <?php if (!empty($currentFeatured['athletes'])): ?>
                    <button type="button" id="ftClearAll" style="background:transparent;color:#f87171;border:1px solid #f8717140;border-radius:5px;padding:3px 8px;font-size:10px;font-weight:600;cursor:pointer;">Tout vider</button>
                    <?php endif; ?>
                </div>
                <div id="ftList" style="display:flex;flex-direction:column;gap:6px;min-height:50px;max-height:260px;overflow-y:auto;">
                    <?php if (empty($currentFeatured['athletes'])): ?>
                        <div id="ftEmpty" style="text-align:center;padding:18px;background:#0d1117;border:1px dashed #30363d;border-radius:8px;color:#8b949e;font-size:12px;">Aucun athlete selectionne. Tape un nom dans le champ "Ajouter un athlete".</div>
                    <?php else: ?>
                        <?php foreach ($currentFeatured['athletes'] as $a):
                            $sxIcon = ($a['sexe'] ?? '') === 'F' ? '&#9792;' : (($a['sexe'] ?? '') === 'M' ? '&#9794;' : '');
                            $sxCol = ($a['sexe'] ?? '') === 'F' ? '#ec4899' : '#3b82f6';
                        ?>
                        <div class="ft-item" data-id="<?= (int)$a['id'] ?>" style="display:flex;align-items:center;gap:10px;padding:8px 12px;background:#0d1117;border:1px solid #30363d;border-radius:7px;">
                            <span style="color:<?= $sxCol ?>;font-size:13px;width:14px;"><?= $sxIcon ?></span>
                            <div style="flex:1;min-width:0;">
                                <div style="color:#f0f6fc;font-size:12px;font-weight:700;"><?= htmlspecialchars($a['name']) ?></div>
                                <?php if (!empty($a['club'])): ?>
                                <div style="color:#8b949e;font-size:10px;"><?= htmlspecialchars($a['club']) ?></div>
                                <?php endif; ?>
                            </div>
                            <span style="color:#6b7280;font-size:9px;font-family:monospace;">#<?= (int)$a['id'] ?></span>
                            <button type="button" class="ft-rm" data-id="<?= (int)$a['id'] ?>" style="background:transparent;color:#f87171;border:none;font-size:16px;cursor:pointer;padding:0 6px;line-height:1;" title="Retirer">&times;</button>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <input type="hidden" name="feat_ids" id="ftIds" value="<?= htmlspecialchars(json_encode($currentFeatured['athletes'], JSON_UNESCAPED_UNICODE)) ?>">
        </div>

        <!-- Choix du layout - design premium -->
        <?php $afLayout = $currentAthletesFilter['layout']; ?>
        <div style="margin-bottom:22px;">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;flex-wrap:wrap;gap:8px;">
                <label style="color:#f0f6fc;font-size:14px;font-weight:800;text-transform:uppercase;letter-spacing:2px;background:linear-gradient(135deg,#a78bfa,#6c5ce7);-webkit-background-clip:text;-webkit-text-fill-color:transparent;background-clip:text;">&#128064; Type d'affichage public</label>
                <span style="color:#8b949e;font-size:11px;font-style:italic;">Choisis comment les athletes apparaitront sur la page publique</span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:14px;">
                <?php
                $layouts = [
                    'magazine' => [
                        'icon'  => '📰',
                        'label' => 'Magazine',
                        'desc'  => 'Article éditorial en 2 colonnes',
                        'tag'   => 'Élégant',
                        'tagColor' => '#a78bfa',
                        'gradient' => 'linear-gradient(135deg,#1e1b4b 0%,#0f0c2e 100%)',
                        'accent' => '#a78bfa',
                    ],
                    'grid' => [
                        'icon'  => '▦',
                        'label' => 'Grille',
                        'desc'  => '3 cartes par ligne, classique',
                        'tag'   => 'Compact',
                        'tagColor' => '#34d399',
                        'gradient' => 'linear-gradient(135deg,#064e3b 0%,#022c22 100%)',
                        'accent' => '#34d399',
                    ],
                    'list' => [
                        'icon'  => '☰',
                        'label' => 'Liste',
                        'desc'  => 'Une fiche par ligne, lisible',
                        'tag'   => 'Lisible',
                        'tagColor' => '#fbbf24',
                        'gradient' => 'linear-gradient(135deg,#451a03 0%,#1c0a02 100%)',
                        'accent' => '#fbbf24',
                    ],
                    'flex' => [
                        'icon'  => '⊞',
                        'label' => 'Flexbox',
                        'desc'  => 'Tailles variables, dynamique',
                        'tag'   => 'Moderne',
                        'tagColor' => '#f472b6',
                        'gradient' => 'linear-gradient(135deg,#500724 0%,#1a0210 100%)',
                        'accent' => '#f472b6',
                    ],
                ];
                foreach ($layouts as $key => $l):
                    $sel = ($afLayout === $key);
                ?>
                <label class="af-layout-opt<?= $sel ? ' af-layout-active' : '' ?>" data-layout="<?= $key ?>" data-accent="<?= $l['accent'] ?>" data-gradient="<?= htmlspecialchars($l['gradient']) ?>" style="position:relative;cursor:pointer;background:<?= $sel ? $l['gradient'] : '#0d1117' ?>;border:2px solid <?= $sel ? $l['accent'] : '#30363d' ?>;border-radius:14px;padding:16px;transition:all .25s cubic-bezier(.2,.8,.2,1);overflow:hidden;<?= $sel ? 'box-shadow:0 8px 24px '.$l['accent'].'30, inset 0 0 30px '.$l['accent'].'10;' : '' ?>">
                    <input type="radio" name="layout" value="<?= $key ?>" <?= $sel ? 'checked' : '' ?> style="display:none;">

                    <!-- Glow d'accent en arriere plan -->
                    <div style="position:absolute;top:-30px;right:-30px;width:80px;height:80px;background:radial-gradient(circle,<?= $l['accent'] ?>30 0%,transparent 70%);border-radius:50%;pointer-events:none;"></div>

                    <!-- Tag premium -->
                    <div style="position:absolute;top:10px;right:10px;background:<?= $l['tagColor'] ?>20;color:<?= $l['tagColor'] ?>;font-size:9px;font-weight:800;text-transform:uppercase;letter-spacing:1px;padding:3px 8px;border-radius:20px;border:1px solid <?= $l['tagColor'] ?>40;"><?= $l['tag'] ?></div>

                    <!-- Icone + nom -->
                    <div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;">
                        <div style="font-size:32px;line-height:1;filter:drop-shadow(0 2px 6px <?= $l['accent'] ?>50);"><?= $l['icon'] ?></div>
                        <div>
                            <div style="color:<?= $sel ? '#fff' : '#f0f6fc' ?>;font-size:16px;font-weight:800;margin-bottom:2px;letter-spacing:0.5px;"><?= $l['label'] ?></div>
                            <div style="color:#8b949e;font-size:11px;line-height:1.3;"><?= $l['desc'] ?></div>
                        </div>
                    </div>

                    <!-- Mini schema visuel premium -->
                    <div class="af-mini-schema" data-layout="<?= $key ?>" style="margin-top:14px;height:64px;background:#00000080;border:1px solid <?= $l['accent'] ?>30;border-radius:6px;padding:6px;display:flex;align-items:flex-start;justify-content:center;overflow:hidden;backdrop-filter:blur(4px);">
                        <?php if ($key === 'magazine'): ?>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:4px;width:100%;">
                                <div style="display:flex;flex-direction:column;gap:2px;">
                                    <div style="height:8px;background:linear-gradient(90deg,<?= $l['accent'] ?>,<?= $l['accent'] ?>80);border-radius:1px;"></div>
                                    <div style="height:3px;background:#555;border-radius:1px;width:90%;"></div>
                                    <div style="height:3px;background:#555;border-radius:1px;width:75%;"></div>
                                    <div style="height:3px;background:#555;border-radius:1px;width:85%;"></div>
                                    <div style="height:8px;background:linear-gradient(90deg,<?= $l['accent'] ?>,<?= $l['accent'] ?>80);border-radius:1px;margin-top:2px;"></div>
                                    <div style="height:3px;background:#555;border-radius:1px;width:80%;"></div>
                                </div>
                                <div style="display:flex;flex-direction:column;gap:2px;">
                                    <div style="height:8px;background:linear-gradient(90deg,<?= $l['accent'] ?>,<?= $l['accent'] ?>80);border-radius:1px;"></div>
                                    <div style="height:3px;background:#555;border-radius:1px;width:88%;"></div>
                                    <div style="height:3px;background:#555;border-radius:1px;width:70%;"></div>
                                    <div style="height:3px;background:#555;border-radius:1px;width:82%;"></div>
                                    <div style="height:8px;background:linear-gradient(90deg,<?= $l['accent'] ?>,<?= $l['accent'] ?>80);border-radius:1px;margin-top:2px;"></div>
                                    <div style="height:3px;background:#555;border-radius:1px;width:75%;"></div>
                                </div>
                            </div>
                        <?php elseif ($key === 'grid'): ?>
                            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:3px;width:100%;">
                                <?php for ($i=0;$i<6;$i++):
                                    $col = $i%2 ? '#3b82f6' : '#ec4899';
                                ?>
                                    <div style="height:24px;background:linear-gradient(135deg,<?= $col ?>,<?= $col ?>40);border-radius:3px;border:1px solid <?= $col ?>60;box-shadow:0 1px 3px <?= $col ?>30;"></div>
                                <?php endfor; ?>
                            </div>
                        <?php elseif ($key === 'list'): ?>
                            <div style="display:flex;flex-direction:column;gap:4px;width:100%;">
                                <?php for ($i=0;$i<5;$i++):
                                    $col = $i%2 ? '#3b82f6' : '#ec4899';
                                ?>
                                    <div style="height:7px;background:linear-gradient(90deg,<?= $col ?>,<?= $col ?>20);border-radius:2px;border-left:2px solid <?= $col ?>;"></div>
                                <?php endfor; ?>
                            </div>
                        <?php else: /* flex */ ?>
                            <div style="display:flex;flex-wrap:wrap;gap:3px;width:100%;align-content:flex-start;">
                                <div style="width:32%;height:18px;background:linear-gradient(135deg,#3b82f6,#3b82f640);border-radius:3px;border:1px solid #3b82f660;"></div>
                                <div style="width:48%;height:18px;background:linear-gradient(135deg,#ec4899,#ec489940);border-radius:3px;border:1px solid #ec489960;"></div>
                                <div style="width:18%;height:18px;background:linear-gradient(135deg,#3b82f6,#3b82f640);border-radius:3px;border:1px solid #3b82f660;"></div>
                                <div style="width:58%;height:18px;background:linear-gradient(135deg,#ec4899,#ec489940);border-radius:3px;border:1px solid #ec489960;"></div>
                                <div style="width:38%;height:18px;background:linear-gradient(135deg,#3b82f6,#3b82f640);border-radius:3px;border:1px solid #3b82f660;"></div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Indicateur selection -->
                    <?php if ($sel): ?>
                    <div style="position:absolute;bottom:8px;right:8px;width:22px;height:22px;background:<?= $l['accent'] ?>;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#000;font-size:14px;font-weight:900;box-shadow:0 0 12px <?= $l['accent'] ?>;animation:afCheckPulse 1.5s ease-in-out infinite;">&#10003;</div>
                    <?php endif; ?>
                </label>
                <?php endforeach; ?>
            </div>
        </div>
        <style>
            @keyframes afCheckPulse {
                0%, 100% { transform: scale(1); }
                50% { transform: scale(1.1); }
            }
            .af-layout-opt:hover {
                transform: translateY(-3px);
                border-color: rgba(167,139,250,0.6) !important;
                background: linear-gradient(135deg,#1a1a2e 0%,#0f0c2e 100%) !important;
            }
            .af-layout-opt.af-layout-active {
                transform: translateY(-2px);
            }
        </style>

        <!-- ============================================================ -->
        <!-- FILTRES AVANCES : Meme club / Meme epreuve -->
        <!-- ============================================================ -->
        <?php
        $afClubFilter = $currentAthletesFilter['club_filter'] ?? '';
        $afEpreuveFilter = $currentAthletesFilter['epreuve_filter'] ?? '';
        $afFilterCibleEnabled = !empty($currentAthletesFilter['filter_cible_enabled']);
        $advHasValues = ($afClubFilter !== '' || $afEpreuveFilter !== '');
        $advFiltersOn = ($afFilterCibleEnabled && $advHasValues);
        ?>
        <div style="background:linear-gradient(135deg,#0c4a6e15,#082f4908);border:1.5px solid #0891b2<?= $advFiltersOn ? '' : '40' ?>;border-radius:12px;padding:18px 20px;margin-bottom:22px;<?= $advFiltersOn ? 'box-shadow:0 4px 20px rgba(8,145,178,0.2);' : '' ?>">
            <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px;flex-wrap:wrap;">
                <span style="font-size:22px;">&#127919;</span>
                <h3 style="color:#22d3ee;font-size:16px;font-weight:800;margin:0;letter-spacing:0.3px;">Filtre cible — Meme club / Meme epreuve</h3>
                <?php if ($advFiltersOn): ?>
                <span style="background:#22d3ee20;color:#22d3ee;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:800;letter-spacing:0.5px;">ACTIF</span>
                <?php elseif ($afFilterCibleEnabled): ?>
                <span style="background:#f59e0b20;color:#f59e0b;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:800;letter-spacing:0.5px;">ACTIF SANS VALEUR</span>
                <?php elseif ($advHasValues): ?>
                <span style="background:#6e768120;color:#6e7681;padding:2px 8px;border-radius:6px;font-size:10px;font-weight:800;letter-spacing:0.5px;">DESACTIVE</span>
                <?php endif; ?>
            </div>

            <!-- Toggle activer / desactiver l'application du filtre -->
            <div style="display:flex;align-items:center;gap:14px;margin-bottom:14px;padding:10px 14px;background:#0d1117;border:1px solid <?= $afFilterCibleEnabled ? '#22d3ee60' : '#30363d' ?>;border-radius:10px;">
                <label class="fc-toggle" style="position:relative;display:inline-block;width:46px;height:24px;flex-shrink:0;">
                    <input type="checkbox" name="filter_cible_enabled" id="fcEnabled" value="1" <?= $afFilterCibleEnabled ? 'checked' : '' ?> style="opacity:0;width:0;height:0;">
                    <span class="fc-slider" style="position:absolute;cursor:pointer;top:0;left:0;right:0;bottom:0;background:<?= $afFilterCibleEnabled ? '#22d3ee' : '#30363d' ?>;border-radius:24px;transition:.3s;"></span>
                    <span class="fc-knob" style="position:absolute;height:18px;width:18px;left:<?= $afFilterCibleEnabled ? '25px' : '3px' ?>;bottom:3px;background:#fff;border-radius:50%;transition:.3s;box-shadow:0 1px 3px rgba(0,0,0,0.3);"></span>
                </label>
                <div style="flex:1;min-width:0;">
                    <div id="fcToggleLabel" style="color:<?= $afFilterCibleEnabled ? '#22d3ee' : '#8b949e' ?>;font-size:13px;font-weight:700;">
                        <?= $afFilterCibleEnabled ? 'Filtre cible APPLIQUE' : 'Filtre cible IGNORE (les valeurs ci-dessous sont sauvegardees mais non appliquees)' ?>
                    </div>
                </div>
            </div>

            <p style="color:#8b949e;font-size:12px;margin:0 0 14px;line-height:1.5;">Restreint le listing public a un seul club et/ou une seule epreuve. Le toggle ci-dessus controle si le filtre s'applique. Les filtres sont <strong style="color:#22d3ee;">combinables</strong> avec les niveaux/annee.</p>

            <script>
            (function(){
                var t = document.getElementById('fcEnabled');
                var s = document.querySelector('.fc-toggle .fc-slider');
                var k = document.querySelector('.fc-toggle .fc-knob');
                var lbl = document.getElementById('fcToggleLabel');
                if (!t) return;
                t.addEventListener('change', function(){
                    var on = t.checked;
                    s.style.background = on ? '#22d3ee' : '#30363d';
                    k.style.left = on ? '25px' : '3px';
                    lbl.style.color = on ? '#22d3ee' : '#8b949e';
                    lbl.textContent = on ? 'Filtre cible APPLIQUE' : 'Filtre cible IGNORE (les valeurs ci-dessous sont sauvegardees mais non appliquees)';
                });
            })();
            </script>

            <?php
            $afClubChips = array_values(array_filter(array_map('trim', explode('|', $afClubFilter)), 'strlen'));
            $afEpChips   = array_values(array_filter(array_map('trim', explode('|', $afEpreuveFilter)), 'strlen'));
            ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <!-- Clubs (multi-chips) -->
                <div style="position:relative;">
                    <label for="afClubFilter" style="display:block;color:#22d3ee;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">&#127963; Memes clubs <span style="color:#5a6580;font-weight:500;text-transform:none;letter-spacing:0;">(plusieurs possibles)</span></label>
                    <div id="afClubChipsWrap" style="background:#0d1117;border:1px solid #0891b240;border-radius:8px;padding:6px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:42px;cursor:text;">
                        <?php foreach ($afClubChips as $cn): ?>
                        <span class="af-chip" data-name="<?= htmlspecialchars($cn) ?>" style="display:inline-flex;align-items:center;gap:4px;background:#0891b220;color:#22d3ee;border:1px solid #0891b260;border-radius:14px;padding:3px 4px 3px 10px;font-size:12px;font-weight:600;"><?= htmlspecialchars($cn) ?><button type="button" class="af-chip-x" aria-label="Retirer" style="background:transparent;border:none;color:#22d3ee;cursor:pointer;font-size:14px;line-height:1;padding:0 5px;border-radius:50%;">&times;</button></span>
                        <?php endforeach; ?>
                        <input type="text" id="afClubFilter" autocomplete="off" placeholder="<?= empty($afClubChips) ? 'Tape pour ajouter un club...' : '+ Ajouter...' ?>" style="flex:1;min-width:140px;background:transparent;border:none;color:#f0f6fc;font-size:13px;font-weight:600;outline:none;padding:6px 4px;">
                    </div>
                    <input type="hidden" name="club_filter" id="afClubFilterHidden" value="<?= htmlspecialchars($afClubFilter) ?>">
                    <div id="afClubResults" style="position:absolute;top:calc(100% + 2px);left:0;right:0;background:#0d1117;border:1px solid #0891b260;border-radius:8px;max-height:240px;overflow-y:auto;z-index:50;display:none;box-shadow:0 8px 24px rgba(8,145,178,0.25);"></div>
                    <p style="color:#5a6580;font-size:10px;margin-top:5px;font-style:italic;">Tape 2+ caracteres puis clique un resultat (ou Entree pour ajouter brut). Plusieurs clubs combines en OR.</p>
                </div>

                <!-- Epreuves (multi-chips) -->
                <div style="position:relative;">
                    <label for="afEpreuveFilter" style="display:block;color:#22d3ee;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">&#127939; Memes epreuves <span style="color:#5a6580;font-weight:500;text-transform:none;letter-spacing:0;">(plusieurs possibles)</span></label>
                    <div id="afEpreuveChipsWrap" style="background:#0d1117;border:1px solid #0891b240;border-radius:8px;padding:6px;display:flex;flex-wrap:wrap;gap:6px;align-items:center;min-height:42px;cursor:text;">
                        <?php foreach ($afEpChips as $en): ?>
                        <span class="af-chip" data-name="<?= htmlspecialchars($en) ?>" style="display:inline-flex;align-items:center;gap:4px;background:#0891b220;color:#22d3ee;border:1px solid #0891b260;border-radius:14px;padding:3px 4px 3px 10px;font-size:12px;font-weight:600;"><?= htmlspecialchars($en) ?><button type="button" class="af-chip-x" aria-label="Retirer" style="background:transparent;border:none;color:#22d3ee;cursor:pointer;font-size:14px;line-height:1;padding:0 5px;border-radius:50%;">&times;</button></span>
                        <?php endforeach; ?>
                        <input type="text" id="afEpreuveFilter" autocomplete="off" placeholder="<?= empty($afEpChips) ? 'Tape pour ajouter une epreuve...' : '+ Ajouter...' ?>" style="flex:1;min-width:140px;background:transparent;border:none;color:#f0f6fc;font-size:13px;font-weight:600;outline:none;padding:6px 4px;">
                    </div>
                    <input type="hidden" name="epreuve_filter" id="afEpreuveFilterHidden" value="<?= htmlspecialchars($afEpreuveFilter) ?>">
                    <div id="afEpreuveResults" style="position:absolute;top:calc(100% + 2px);left:0;right:0;background:#0d1117;border:1px solid #0891b260;border-radius:8px;max-height:240px;overflow-y:auto;z-index:50;display:none;box-shadow:0 8px 24px rgba(8,145,178,0.25);"></div>
                    <p style="color:#5a6580;font-size:10px;margin-top:5px;font-style:italic;">Tape 2+ caracteres puis clique un resultat (ou Entree pour ajouter brut). Plusieurs epreuves combines en OR.</p>
                </div>
            </div>

            <?php if ($advFiltersOn): ?>
            <button type="button" id="afClearTargetBtn" style="margin-top:12px;background:transparent;color:#f87171;border:1px solid #f8717140;border-radius:6px;padding:6px 14px;font-size:11px;font-weight:600;cursor:pointer;">&times; Effacer tous les filtres cibles</button>
            <?php endif; ?>
        </div>

        <script>
        (function(){
            function escapeHtml(s){return String(s||'').replace(/[&<>"']/g,function(c){return{'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];});}

            function setupMulti(opts){
                var input    = document.getElementById(opts.inputId);
                var hidden   = document.getElementById(opts.hiddenId);
                var wrap     = document.getElementById(opts.wrapId);
                var results  = document.getElementById(opts.resultsId);
                if (!input || !hidden || !wrap || !results) return null;

                function readChips(){
                    return Array.prototype.map.call(wrap.querySelectorAll('.af-chip'), function(c){return c.dataset.name;});
                }
                function syncHidden(){
                    hidden.value = readChips().join('|');
                    var has = readChips().length > 0;
                    input.placeholder = has ? '+ Ajouter...' : opts.placeholderEmpty;
                }
                function addChip(name){
                    name = String(name||'').trim();
                    if (!name) return;
                    var chips = readChips();
                    for (var i=0; i<chips.length; i++) {
                        if (chips[i].toLowerCase() === name.toLowerCase()) return;
                    }
                    if (chips.length >= 20) return;
                    var span = document.createElement('span');
                    span.className = 'af-chip';
                    span.dataset.name = name;
                    span.style.cssText = 'display:inline-flex;align-items:center;gap:4px;background:#0891b220;color:#22d3ee;border:1px solid #0891b260;border-radius:14px;padding:3px 4px 3px 10px;font-size:12px;font-weight:600;';
                    span.innerHTML = escapeHtml(name) + '<button type="button" class="af-chip-x" aria-label="Retirer" style="background:transparent;border:none;color:#22d3ee;cursor:pointer;font-size:14px;line-height:1;padding:0 5px;border-radius:50%;">&times;</button>';
                    wrap.insertBefore(span, input);
                    syncHidden();
                }
                function removeChip(chip){ if (chip) { chip.remove(); syncHidden(); } }
                function clearAll(){
                    Array.prototype.forEach.call(wrap.querySelectorAll('.af-chip'), function(c){ c.remove(); });
                    input.value = '';
                    results.style.display = 'none';
                    syncHidden();
                }

                // Click X removes chip
                wrap.addEventListener('click', function(e){
                    var x = e.target.closest('.af-chip-x');
                    if (x) { removeChip(x.closest('.af-chip')); return; }
                    if (e.target === wrap) input.focus();
                });

                // Autocomplete
                var timer = null;
                input.addEventListener('input', function(){
                    clearTimeout(timer);
                    var q = input.value.trim();
                    if (q.length < 2) { results.style.display = 'none'; return; }
                    timer = setTimeout(function(){
                        fetch(opts.endpoint + '&q=' + encodeURIComponent(q))
                            .then(function(r){ return r.json(); })
                            .then(function(d){
                                if (!d.results || !d.results.length) { results.style.display = 'none'; return; }
                                results.innerHTML = d.results.map(function(c){
                                    return '<div class="af-res" data-name="' + escapeHtml(c.nom).replace(/"/g,'&quot;') + '" style="padding:8px 14px;cursor:pointer;border-bottom:1px solid #21262d;color:#f0f6fc;font-size:13px;"><strong>' + escapeHtml(c.nom) + '</strong> <span style="color:#8b949e;font-size:11px;">(' + c.nb + ' ' + opts.counterLabel + ')</span></div>';
                                }).join('');
                                results.style.display = 'block';
                            });
                    }, 250);
                });

                input.addEventListener('keydown', function(e){
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (input.value.trim() !== '') {
                            addChip(input.value);
                            input.value = '';
                            results.style.display = 'none';
                        }
                    } else if (e.key === 'Backspace' && input.value === '') {
                        var chips = wrap.querySelectorAll('.af-chip');
                        if (chips.length) removeChip(chips[chips.length - 1]);
                    }
                });

                results.addEventListener('click', function(e){
                    var row = e.target.closest('.af-res');
                    if (!row) return;
                    addChip(row.dataset.name);
                    input.value = '';
                    results.style.display = 'none';
                    input.focus();
                });

                // Hover effect on results
                results.addEventListener('mouseover', function(e){
                    var row = e.target.closest('.af-res');
                    if (row) row.style.background = '#161b22';
                });
                results.addEventListener('mouseout', function(e){
                    var row = e.target.closest('.af-res');
                    if (row) row.style.background = 'transparent';
                });

                document.addEventListener('click', function(e){
                    if (!input.contains(e.target) && !results.contains(e.target) && !wrap.contains(e.target)) {
                        results.style.display = 'none';
                    }
                });

                syncHidden();
                return { clear: clearAll };
            }

            var clubMulti = setupMulti({
                inputId: 'afClubFilter',
                hiddenId: 'afClubFilterHidden',
                wrapId: 'afClubChipsWrap',
                resultsId: 'afClubResults',
                endpoint: 'panel.php?action=search_clubs',
                counterLabel: 'athletes',
                placeholderEmpty: 'Tape pour ajouter un club...'
            });
            var epMulti = setupMulti({
                inputId: 'afEpreuveFilter',
                hiddenId: 'afEpreuveFilterHidden',
                wrapId: 'afEpreuveChipsWrap',
                resultsId: 'afEpreuveResults',
                endpoint: 'panel.php?action=search_epreuves',
                counterLabel: 'records',
                placeholderEmpty: 'Tape pour ajouter une epreuve...'
            });

            var clearBtn = document.getElementById('afClearTargetBtn');
            if (clearBtn) {
                clearBtn.addEventListener('click', function(){
                    if (clubMulti) clubMulti.clear();
                    if (epMulti)   epMulti.clear();
                });
            }
        })();
        </script>

        <!-- Schema visuel global (apercu mosaique H/F) -->
        <div style="background:#0d1117;border:1px solid #30363d;border-radius:10px;padding:16px;margin-bottom:18px;">
            <div style="color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:12px;font-weight:700;">Apercu repartition H/F</div>
            <div id="afSchema" style="display:flex;flex-direction:column;gap:10px;">
                <div style="background:linear-gradient(135deg,#a78bfa15,#a78bfa05);border:1px dashed #a78bfa60;border-radius:6px;padding:10px;text-align:center;font-size:11px;color:#a78bfa;">
                    📰 ÉLITE <span id="schemaYr"><?= $afAnnee === 0 ? 'TOUTES ANNEES' : $afAnnee ?></span> &middot; <span id="schemaNiv"><?= htmlspecialchars(implode(' & ', $afNiveaux)) ?></span>
                </div>
                <div id="schemaGrid" style="display:grid;grid-template-columns:repeat(10,1fr);gap:3px;"></div>
                <div style="display:flex;justify-content:space-between;font-size:11px;color:#8b949e;margin-top:4px;">
                    <span><span style="display:inline-block;width:9px;height:9px;background:#3b82f6;border-radius:2px;margin-right:4px;"></span>Hommes : <strong id="schemaNbH" style="color:#3b82f6;"><?= (int)$currentAthletesFilter['nb_hommes'] ?></strong></span>
                    <span><span style="display:inline-block;width:9px;height:9px;background:#ec4899;border-radius:2px;margin-right:4px;"></span>Femmes : <strong id="schemaNbF" style="color:#ec4899;"><?= (int)$currentAthletesFilter['nb_femmes'] ?></strong></span>
                    <span style="color:#a78bfa;">Total : <strong id="schemaNbT"><?= (int)$currentAthletesFilter['nb_hommes'] + (int)$currentAthletesFilter['nb_femmes'] ?></strong></span>
                </div>
            </div>
        </div>
        <style>
            .af-layout-opt:hover { border-color: #6c5ce7 !important; background: #6c5ce715 !important; }
            .af-layout-active { box-shadow: 0 0 0 1px #6c5ce740, 0 4px 12px rgba(108,92,231,0.2); }
        </style>

        <div style="display:flex;flex-wrap:wrap;align-items:center;gap:10px;">
            <button type="submit" name="save_athletes_filter" value="1" id="afSubmitBtn" style="background:#6c5ce7;color:#fff;border:none;padding:10px 22px;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(108,92,231,0.35);">Enregistrer &amp; Apercu</button>
            <span style="color:#5a6580;font-size:12px;">ou</span>
            <input type="text" name="preset_label" maxlength="120" placeholder="Nom de la selection (optionnel)" style="flex:1 1 220px;min-width:180px;background:#0d1117;border:1px solid #34d39940;border-radius:8px;padding:10px 14px;color:#f0f6fc;font-size:13px;box-sizing:border-box;">
            <button type="submit" name="save_athletes_preset" value="1" title="Enregistre la selection courante dans le tableau ci-dessous et l'active" style="background:#10b981;color:#fff;border:none;padding:10px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 3px 12px rgba(16,185,129,0.3);">&#128190; Enregistrer comme selection</button>
        </div>

        <?php if ($afSaved):
            $afEpreuvesDefault = '100m|200m|400m Haies (76)|400m Haies (91)|110m Haies (91)|110m Haies (99)|110m Haies (106)|Longueur|Triple saut|Perche';
            // Si l'admin a defini une epreuve cible ET que le toggle est actif → on l'utilise + mode strict
            $afStrictActive = !empty($currentAthletesFilter['filter_cible_enabled']) && trim((string)$currentAthletesFilter['epreuve_filter']) !== '';
            // Priorite : filtre cible epreuve > mode "toutes les epreuves" > liste figee par defaut
            if ($afStrictActive) {
                $afEpreuvesPub = trim((string)$currentAthletesFilter['epreuve_filter']);
            } elseif (!empty($currentAthletesFilter['all_epreuves'])) {
                $afEpreuvesPub = ''; // toutes les epreuves : aucun filtre epreuve
            } else {
                $afEpreuvesPub = $afEpreuvesDefault;
            }
            $afClubPub = !empty($currentAthletesFilter['filter_cible_enabled']) ? trim((string)$currentAthletesFilter['club_filter']) : '';
            $afNivStr = implode(',', $currentAthletesFilter['niveaux']);
            $afYr = $currentAthletesFilter['annee'];
        ?>
        <!-- Apercu AJAX en temps reel -->
        <div id="afPreviewWrap" style="margin-top:24px;padding-top:20px;border-top:1px solid #30363d;"
             data-niv="<?= htmlspecialchars($afNivStr) ?>"
             data-annee="<?= $afYr ?>"
             data-epreuves="<?= htmlspecialchars($afEpreuvesPub) ?>"
             data-club="<?= htmlspecialchars($afClubPub) ?>"
             data-strict="<?= $afStrictActive ? '1' : '0' ?>"
             data-nbh="<?= (int)$currentAthletesFilter['nb_hommes'] ?>"
             data-nbf="<?= (int)$currentAthletesFilter['nb_femmes'] ?>">
            <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                <h3 style="color:#f0f6fc;font-size:15px;font-weight:700;margin:0;">&#128269; Apercu en temps reel</h3>
                <span id="afPreviewMeta" style="font-size:11px;color:#8b949e;">Initialisation...</span>
            </div>

            <!-- Journal d'activite temps reel -->
            <div id="afLogPanel" style="background:#000;border:1px solid #30363d;border-radius:8px;padding:12px 14px;margin-bottom:14px;font-family:'SF Mono',Monaco,Consolas,monospace;font-size:11px;line-height:1.6;max-height:160px;overflow-y:auto;">
                <div id="afLogContent" style="color:#7d8590;"></div>
            </div>

            <!-- Compteurs temps reel -->
            <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:12px;margin-bottom:18px;">
                <div style="background:linear-gradient(135deg,#3b82f620,#3b82f608);border:1px solid #3b82f640;border-radius:10px;padding:14px;text-align:center;">
                    <div style="color:#60a5fa;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">&#9794; Hommes</div>
                    <div id="afCountM" style="color:#3b82f6;font-size:32px;font-weight:900;line-height:1;">—</div>
                    <div id="afTimeM" style="color:#8b949e;font-size:10px;margin-top:4px;">en attente</div>
                </div>
                <div style="background:linear-gradient(135deg,#ec489920,#ec489908);border:1px solid #ec489940;border-radius:10px;padding:14px;text-align:center;">
                    <div style="color:#f472b6;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">&#9792; Femmes</div>
                    <div id="afCountF" style="color:#ec4899;font-size:32px;font-weight:900;line-height:1;">—</div>
                    <div id="afTimeF" style="color:#8b949e;font-size:10px;margin-top:4px;">en attente</div>
                </div>
                <div style="background:linear-gradient(135deg,#a78bfa20,#a78bfa08);border:1px solid #a78bfa40;border-radius:10px;padding:14px;text-align:center;">
                    <div style="color:#c4b5fd;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;margin-bottom:4px;">&#127942; Total</div>
                    <div id="afCountTotal" style="color:#a78bfa;font-size:32px;font-weight:900;line-height:1;">0</div>
                    <div id="afTimeTotal" style="color:#8b949e;font-size:10px;margin-top:4px;">recherche...</div>
                </div>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
                <!-- Hommes -->
                <div id="afColM" style="background:#0d1117;border:1px solid #30363d;border-radius:10px;padding:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <strong style="color:#3b82f6;font-size:13px;">&#9794; Hommes</strong>
                        <span class="af-col-status" data-col="M" style="color:#8b949e;font-size:11px;">En attente...</span>
                    </div>
                    <div class="af-col-progress" data-col="M" style="height:3px;background:#21262d;border-radius:2px;overflow:hidden;margin-bottom:10px;">
                        <div class="af-bar" style="height:100%;width:0;background:linear-gradient(90deg,#3b82f6,#60a5fa);transition:width 0.3s ease;"></div>
                    </div>
                    <div class="af-col-body" data-col="M" style="max-height:280px;overflow-y:auto;color:#8b949e;font-size:12px;text-align:center;padding:14px;">Chargement...</div>
                </div>

                <!-- Femmes -->
                <div id="afColF" style="background:#0d1117;border:1px solid #30363d;border-radius:10px;padding:14px;">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
                        <strong style="color:#ec4899;font-size:13px;">&#9792; Femmes</strong>
                        <span class="af-col-status" data-col="F" style="color:#8b949e;font-size:11px;">En attente...</span>
                    </div>
                    <div class="af-col-progress" data-col="F" style="height:3px;background:#21262d;border-radius:2px;overflow:hidden;margin-bottom:10px;">
                        <div class="af-bar" style="height:100%;width:0;background:linear-gradient(90deg,#ec4899,#f472b6);transition:width 0.3s ease;"></div>
                    </div>
                    <div class="af-col-body" data-col="F" style="max-height:280px;overflow-y:auto;color:#8b949e;font-size:12px;text-align:center;padding:14px;">En attente fin hommes...</div>
                </div>
            </div>

            <div id="afPreviewFooter" style="text-align:center;margin-top:14px;font-size:12px;color:#8b949e;">
                <span id="afTotal">—</span>
            </div>
        </div>
        <?php endif; ?>
    </form>

    <!-- ============================================================ -->
    <!-- SELECTIONS ENREGISTREES (presets activables, 1 seule active) -->
    <!-- ============================================================ -->
    <div id="athletesPresets" style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:20px 22px;margin-bottom:20px;">
        <div style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;margin-bottom:6px;">
            <h2 style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0;">&#128190; Selections enregistrees</h2>
            <?php
            $apLabels = [
                'saved'     => ['#10b98120','#34d399','&#10003; Selection enregistree et activee'],
                'activated' => ['#10b98120','#34d399','&#10003; Selection activee — visible sur /athletes'],
                'deleted'   => ['#f59e0b20','#fbbf24','&#128465; Selection supprimee'],
                'notfound'  => ['#e11d4820','#fb7185','Selection introuvable'],
            ];
            if (isset($apLabels[$apMsg])): list($_bg,$_fg,$_txt) = $apLabels[$apMsg]; ?>
            <span style="background:<?= $_bg ?>;color:<?= $_fg ?>;padding:5px 12px;border-radius:6px;font-size:12px;font-weight:700;"><?= $_txt ?></span>
            <?php endif; ?>
        </div>
        <p style="color:#8b949e;font-size:13px;margin:0 0 16px;line-height:1.5;">
            Configure les criteres ci-dessus puis clique <strong style="color:#34d399;">Enregistrer comme selection</strong> pour la memoriser ici.
            Tu peux <strong style="color:#a78bfa;">activer</strong> n'importe quelle selection : elle pilote alors ce qui s'affiche sur
            <code style="color:#a78bfa;">/?page=athletes</code>. <strong>Une seule selection active a la fois.</strong>
        </p>

        <?php
        $presets = $athletesPresetsStore['presets'] ?? [];
        $activeId = $athletesPresetsStore['active_id'] ?? '';
        if (empty($presets)): ?>
            <div style="background:#0d1117;border:1px dashed #30363d;border-radius:10px;padding:22px;text-align:center;color:#6e7681;font-size:13px;">
                Aucune selection enregistree pour l'instant. Configure un filtre ci-dessus et clique &laquo;&nbsp;Enregistrer comme selection&nbsp;&raquo;.
            </div>
        <?php else:
            $nivCol = function($c){ $f=$c[0]??''; return $f==='I'?'#c026d3':($f==='N'?'#e11d48':($f==='R'?'#0891b2':'#f97316')); };
        ?>
        <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
            <thead>
                <tr style="text-align:left;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;">
                    <th style="padding:8px 10px;border-bottom:1px solid #30363d;">Statut</th>
                    <th style="padding:8px 10px;border-bottom:1px solid #30363d;">Selection</th>
                    <th style="padding:8px 10px;border-bottom:1px solid #30363d;">Criteres</th>
                    <th style="padding:8px 10px;border-bottom:1px solid #30363d;">Creee le</th>
                    <th style="padding:8px 10px;border-bottom:1px solid #30363d;text-align:right;">Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($presets as $p):
                $pid = (string)($p['id'] ?? '');
                $cfg = $p['config'] ?? [];
                $isActive = ($pid !== '' && $pid === $activeId);
                $nbH = (int)($cfg['nb_hommes'] ?? 0); $nbF = (int)($cfg['nb_femmes'] ?? 0);
                $sexeTxt = ($nbH>0 && $nbF>0) ? ('&#9794; '.$nbH.' / &#9792; '.$nbF) : ($nbH>0 ? ('&#9794; '.$nbH) : ($nbF>0 ? ('&#9792; '.$nbF) : '—'));
                if (!empty($cfg['filter_cible_enabled']) && trim((string)($cfg['epreuve_filter']??'')) !== '') $epTxt = htmlspecialchars(str_replace('|',' / ',$cfg['epreuve_filter']));
                elseif (!empty($cfg['filter_cible_enabled']) && trim((string)($cfg['club_filter']??'')) !== '') $epTxt = '&#127963; '.htmlspecialchars(str_replace('|',' / ',$cfg['club_filter']));
                elseif (!empty($cfg['all_epreuves'])) $epTxt = 'Toutes epreuves';
                else $epTxt = 'Sprint/Sauts';
                $yrTxt = ((int)($cfg['annee']??0)===0) ? 'Toutes annees' : (string)(int)$cfg['annee'];
            ?>
                <tr style="<?= $isActive ? 'background:#10b98112;' : '' ?>border-bottom:1px solid #21262d;">
                    <td style="padding:10px;">
                        <?php if ($isActive): ?>
                            <span style="display:inline-flex;align-items:center;gap:5px;background:#10b98120;color:#34d399;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:800;"><span style="width:7px;height:7px;background:#34d399;border-radius:50%;box-shadow:0 0 6px #34d399;"></span>ACTIVE</span>
                        <?php else: ?>
                            <span style="color:#6e7681;font-size:11px;">Inactive</span>
                        <?php endif; ?>
                    </td>
                    <td style="padding:10px;color:#f0f6fc;font-weight:600;max-width:240px;"><?= htmlspecialchars((string)($p['label'] ?? '')) ?></td>
                    <td style="padding:10px;">
                        <div style="display:flex;flex-wrap:wrap;gap:4px;align-items:center;">
                            <span style="color:#c9d1d9;"><?= $sexeTxt ?></span>
                            <span style="color:#3d444d;">|</span>
                            <span style="color:#22d3ee;"><?= $epTxt ?></span>
                            <span style="color:#3d444d;">|</span>
                            <?php foreach ((array)($cfg['niveaux'] ?? []) as $nv): ?>
                                <span style="background:<?= $nivCol($nv) ?>20;border:1px solid <?= $nivCol($nv) ?>60;color:<?= $nivCol($nv) ?>;padding:1px 7px;border-radius:5px;font-size:11px;font-weight:700;"><?= htmlspecialchars($nv) ?></span>
                            <?php endforeach; ?>
                            <span style="color:#3d444d;">|</span>
                            <span style="color:#8b949e;font-size:11px;"><?= $yrTxt ?></span>
                            <span style="color:#6e7681;font-size:10px;">(<?= htmlspecialchars((string)($cfg['layout'] ?? 'magazine')) ?>)</span>
                        </div>
                    </td>
                    <td style="padding:10px;color:#6e7681;font-size:11px;white-space:nowrap;"><?= htmlspecialchars((string)($p['created_at'] ?? '')) ?></td>
                    <td style="padding:10px;text-align:right;white-space:nowrap;">
                        <?php if (!$isActive): ?>
                        <form method="POST" action="panel.php" style="display:inline;">
                            <input type="hidden" name="preset_id" value="<?= htmlspecialchars($pid, ENT_QUOTES) ?>">
                            <button type="submit" name="activate_athletes_preset" value="1" style="background:#6c5ce7;color:#fff;border:none;padding:6px 14px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">Activer</button>
                        </form>
                        <?php else: ?>
                        <span style="color:#34d399;font-size:12px;font-weight:700;margin-right:6px;">&#10003; en ligne</span>
                        <?php endif; ?>
                        <form method="POST" action="panel.php" style="display:inline;" onsubmit="return confirm('Supprimer cette selection enregistree ?');">
                            <input type="hidden" name="preset_id" value="<?= htmlspecialchars($pid, ENT_QUOTES) ?>">
                            <button type="submit" name="delete_athletes_preset" value="1" title="Supprimer" style="background:transparent;color:#fb7185;border:1px solid #e11d4840;padding:6px 10px;border-radius:6px;font-size:12px;font-weight:700;cursor:pointer;">&#128465;</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php endif; ?>
    </div>

    <script>
    (function(){
        var input   = document.getElementById('ftSearch');
        var results = document.getElementById('ftResults');
        var listEl  = document.getElementById('ftList');
        var idsEl   = document.getElementById('ftIds');
        var countEl = document.getElementById('ftCount');
        var toggle  = document.getElementById('ftEnabled');
        var slider  = document.querySelector('.ft-toggle .ft-slider');
        var knob    = document.querySelector('.ft-toggle .ft-knob');
        var label   = document.getElementById('ftToggleLabel');
        if (!input) return;

        function readList() {
            try { return JSON.parse(idsEl.value) || []; } catch(e) { return []; }
        }
        function writeList(arr) {
            idsEl.value = JSON.stringify(arr);
            countEl.textContent = arr.length;
        }
        function renderEmpty() {
            if (!listEl.querySelector('.ft-item')) {
                listEl.innerHTML = '<div id="ftEmpty" style="text-align:center;padding:18px;background:#0d1117;border:1px dashed #30363d;border-radius:8px;color:#8b949e;font-size:12px;">Aucun athlete selectionne. Tape un nom dans le champ "Ajouter un athlete".</div>';
            }
        }
        function escapeHtml(s) { return String(s||'').replace(/[&<>"']/g, function(c){ return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]; }); }

        function addItem(a) {
            var arr = readList();
            if (arr.some(function(x){ return parseInt(x.id) === parseInt(a.id); })) return;
            arr.push({ id: parseInt(a.id), name: a.name, sexe: a.sexe || '', club: a.club || '', added_at: new Date().toISOString().slice(0,19).replace('T',' ') });
            writeList(arr);
            var empty = document.getElementById('ftEmpty');
            if (empty) empty.remove();
            var sxIcon = a.sexe === 'F' ? '♀' : (a.sexe === 'M' ? '♂' : '');
            var sxCol  = a.sexe === 'F' ? '#ec4899' : '#3b82f6';
            var div = document.createElement('div');
            div.className = 'ft-item';
            div.dataset.id = a.id;
            div.style.cssText = 'display:flex;align-items:center;gap:10px;padding:8px 12px;background:#0d1117;border:1px solid #30363d;border-radius:7px;';
            div.innerHTML = '<span style="color:'+sxCol+';font-size:13px;width:14px;">'+sxIcon+'</span>'
                + '<div style="flex:1;min-width:0;"><div style="color:#f0f6fc;font-size:12px;font-weight:700;">'+escapeHtml(a.name)+'</div>'
                + (a.club ? '<div style="color:#8b949e;font-size:10px;">'+escapeHtml(a.club)+'</div>' : '')
                + '</div>'
                + '<span style="color:#6b7280;font-size:9px;font-family:monospace;">#'+parseInt(a.id)+'</span>'
                + '<button type="button" class="ft-rm" data-id="'+parseInt(a.id)+'" style="background:transparent;color:#f87171;border:none;font-size:16px;cursor:pointer;padding:0 6px;line-height:1;" title="Retirer">&times;</button>';
            listEl.appendChild(div);
        }

        function removeItem(id) {
            id = parseInt(id);
            var arr = readList().filter(function(x){ return parseInt(x.id) !== id; });
            writeList(arr);
            var el = listEl.querySelector('.ft-item[data-id="'+id+'"]');
            if (el) el.remove();
            renderEmpty();
        }

        // Delegation suppression
        listEl.addEventListener('click', function(e){
            var btn = e.target.closest('.ft-rm');
            if (btn) removeItem(btn.dataset.id);
        });

        // Tout vider
        var clearBtn = document.getElementById('ftClearAll');
        if (clearBtn) clearBtn.addEventListener('click', function(){
            if (!confirm('Vider toute la liste des athletes mis en avant ?')) return;
            writeList([]);
            listEl.innerHTML = '';
            renderEmpty();
            clearBtn.remove();
        });

        // Recherche live
        var searchTimer = null;
        var lastQ = '';
        input.addEventListener('input', function(){
            clearTimeout(searchTimer);
            var q = input.value.trim();
            if (q.length < 2) { results.style.display = 'none'; return; }
            if (q === lastQ) return;
            searchTimer = setTimeout(function(){
                lastQ = q;
                fetch('panel.php?action=feat_search&q=' + encodeURIComponent(q))
                  .then(function(r){ return r.json(); })
                  .then(function(d){
                    if (!d.success || !d.results || !d.results.length) {
                        results.innerHTML = '<div style="padding:14px;text-align:center;color:#8b949e;font-size:12px;">Aucun resultat</div>';
                        results.style.display = 'block';
                        return;
                    }
                    var current = readList().map(function(x){ return parseInt(x.id); });
                    results.innerHTML = d.results.map(function(a){
                        var already = current.indexOf(parseInt(a.id)) !== -1;
                        var sxIcon = a.sexe === 'F' ? '♀' : (a.sexe === 'M' ? '♂' : '');
                        var sxCol  = a.sexe === 'F' ? '#ec4899' : '#3b82f6';
                        var hiddenBadge = a.hidden ? '<span style="background:#f8717125;color:#f87171;font-size:9px;font-weight:800;padding:1px 6px;border-radius:8px;margin-left:6px;letter-spacing:0.5px;">MASQUE</span>' : '';
                        return '<div class="ft-res" data-a=\''+escapeHtml(JSON.stringify(a))+'\' style="display:flex;align-items:center;gap:10px;padding:8px 14px;cursor:'+(already?'default':'pointer')+';opacity:'+(already?'0.4':'1')+';border-bottom:1px solid #21262d;">'
                            + '<span style="color:'+sxCol+';font-size:13px;width:16px;">'+sxIcon+'</span>'
                            + '<div style="flex:1;min-width:0;"><div style="color:#f0f6fc;font-size:13px;font-weight:600;">'+escapeHtml(a.name)+hiddenBadge+'</div>'
                            + (a.club ? '<div style="color:#8b949e;font-size:11px;">'+escapeHtml(a.club)+(a.categorie?' &middot; '+escapeHtml(a.categorie):'')+'</div>' : '')
                            + '</div>'
                            + (already ? '<span style="color:#10b981;font-size:11px;font-weight:700;">Deja ajoute</span>' : '<span style="color:#a78bfa;font-size:11px;font-weight:700;">+ Ajouter</span>')
                            + '</div>';
                    }).join('');
                    results.style.display = 'block';
                  });
            }, 250);
        });

        // Click sur un resultat
        results.addEventListener('click', function(e){
            var row = e.target.closest('.ft-res');
            if (!row) return;
            try {
                var a = JSON.parse(row.dataset.a);
                var current = readList().map(function(x){ return parseInt(x.id); });
                if (current.indexOf(parseInt(a.id)) !== -1) return;
                addItem(a);
                results.style.display = 'none';
                input.value = '';
                lastQ = '';
            } catch(e) {}
        });

        // Click hors zone -> fermer dropdown
        document.addEventListener('click', function(e){
            if (!input.contains(e.target) && !results.contains(e.target)) results.style.display = 'none';
        });

        // Toggle visuel
        if (toggle) {
            toggle.addEventListener('change', function(){
                var on = toggle.checked;
                slider.style.background = on ? '#10b981' : '#30363d';
                knob.style.left = on ? '23px' : '3px';
                label.textContent = on ? 'Mise en avant ACTIVE' : 'Mise en avant DESACTIVEE';
                label.style.color = on ? '#34d399' : '#8b949e';
            });
        }
    })();
    </script>
    </section>

    <!-- Visiteurs aujourd'hui vs hier -->
    <div class="vis-stats">
        <div class="vis-card">
            <div class="vis-label">Aujourd'hui</div>
            <div class="vis-value"><?= number_format($visToday, 0, ',', ' ') ?></div>
            <div class="vis-sub">visiteurs uniques (IPs)</div>
        </div>
        <div class="vis-card">
            <div class="vis-label">Hier</div>
            <div class="vis-value"><?= number_format($visYesterday, 0, ',', ' ') ?></div>
            <div class="vis-sub">visiteurs uniques (IPs)</div>
        </div>
        <div class="vis-card trend-<?= $visTrend ?>">
            <div class="vis-label">Evolution vs hier</div>
            <div class="vis-value">
                <span class="vis-arrow"><?= $visTrend === 'up' ? '↑' : ($visTrend === 'down' ? '↓' : '→') ?></span><?= htmlspecialchars($visPctTxt) ?>
            </div>
            <div class="vis-sub">
                <?php if ($visYesterday === 0 && $visToday === 0): ?>
                    aucune donnee
                <?php elseif ($visYesterday === 0): ?>
                    pas de visiteurs hier
                <?php elseif ($visDiff > 0): ?>
                    +<?= number_format($visDiffAbs, 0, ',', ' ') ?> visiteur<?= $visDiffAbs > 1 ? 's' : '' ?> de plus
                <?php elseif ($visDiff < 0): ?>
                    <?= number_format($visDiffAbs, 0, ',', ' ') ?> visiteur<?= $visDiffAbs > 1 ? 's' : '' ?> de moins
                <?php else: ?>
                    identique a hier
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (!empty($allRecent)): ?>
    <div id="notifBlock" data-max-uid="<?= $maxUid ?>">
        <div class="notif-header">
            <div class="notif-header-text">
                <span id="notifBadge" class="notif-badge">0</span>
                <span class="notif-header-label">inscription(s) en attente de validation</span>
            </div>
            <button id="notifMarkAll" class="notif-btn">Tout marquer comme lu</button>
        </div>
        <div id="notifList">
            <?php foreach ($allRecent as $u): ?>
            <div class="notif" data-uid="<?= (int)$u['id_user'] ?>">
                <div class="notif-icon">🔔</div>
                <div class="notif-body">
                    <div class="notif-title">Nouvelle inscription <?= htmlspecialchars(strtolower($u['oauth_provider'] ?: 'email')) === 'google' ? '— Google' : '— Email' ?></div>
                    <div class="notif-content">
                        <strong><?= htmlspecialchars($u['email']) ?></strong>
                        <span class="notif-meta">— inscrit le <?= htmlspecialchars($u['date_fr']) ?></span>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Onglets principaux (regroupés par domaine) -->
    <nav class="tabs-main">
        <span class="tab-group-label">Contenu</span>
        <button class="tab-main active" data-tab="athletes-filter">&#127939; Page Athletes</button>
        <button class="tab-main" data-tab="niveaux">&#128202; Niveaux</button>
        <button class="tab-main" data-tab="athlete-edit">&#9999;&#65039; Editer athlete</button>

        <span class="tab-group-label">Membres</span>
        <button class="tab-main" data-tab="inscription">Inscription <span class="cnt"><?= $totalUsers ?></span></button>
        <button class="tab-main" data-tab="abonnes">&#128142; Abonn&eacute;s <span class="cnt"><?= $subStats['active'] ?></span></button>
        <button class="tab-main" data-tab="usernav">&#128270; Navigation</button>

        <span class="tab-group-label">Messages</span>
        <button class="tab-main" data-tab="mails">Mails <span class="cnt"><?= $totalMails ?></span><?= $totalUnread > 0 ? '<span class="cnt-alert">' . $totalUnread . '</span>' : '' ?></button>
        <button class="tab-main" data-tab="reports">Signalements <span class="cnt"><?= $totalReports ?></span><?= $totalReportsNew > 0 ? '<span class="cnt-alert">' . $totalReportsNew . '</span>' : '' ?></button>

        <span class="tab-group-label">Système</span>
        <button class="tab-main" data-tab="tools">&#9881; Outils</button>
        <button class="tab-main" data-tab="paths">&#128193; Chemins</button>
        <button class="tab-main" data-tab="extract">&#128229; Extraction</button>
        <button class="tab-main" data-tab="routes">&#128279; Routes</button>
        <button class="tab-main" data-tab="style-global">&#127912; Style global</button>
    </nav>

    <!-- Section Niveaux athletes -->
    <section class="tab-pane" data-pane="niveaux">
        <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:24px;margin-bottom:20px;">
            <h2 style="color:#f0f6fc;font-size:20px;margin-bottom:14px;font-weight:700;">Repartition des athletes par niveau</h2>

            <div style="background:linear-gradient(135deg,#6c5ce720,#6c5ce508);border:1px solid #6c5ce780;border-left:5px solid #a78bfa;border-radius:10px;padding:20px 22px;margin-bottom:18px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">
                <div style="color:#ffffff;font-size:17px;font-weight:700;line-height:1.65;margin-bottom:12px;letter-spacing:0.2px;">
                    &#128202; Chaque athlete est compte <span style="color:#fbbf24;font-weight:900;background:#fbbf2415;padding:2px 8px;border-radius:5px;">une seule fois</span>, dans son <span style="color:#fbbf24;font-weight:900;background:#fbbf2415;padding:2px 8px;border-radius:5px;">niveau le plus eleve</span>.
                </div>
                <div style="color:#e6edf3;font-size:15px;font-weight:600;line-height:1.7;letter-spacing:0.3px;">
                    <strong style="color:#a78bfa;font-size:14px;text-transform:uppercase;letter-spacing:1.5px;">Hierarchie :</strong>&nbsp;
                    <span style="color:#e879f9;font-weight:800;">IA</span> &gt;
                    <span style="color:#e879f9;font-weight:800;">IB</span> &gt;
                    <span style="color:#e879f9;font-weight:800;">IE</span> &gt;
                    <span style="color:#e879f9;font-weight:800;">IR</span> &gt;
                    <span style="color:#e879f9;font-weight:800;">IR2</span> &gt;
                    <span style="color:#fb7185;font-weight:800;">N1-N4</span> &gt;
                    <span style="color:#22d3ee;font-weight:800;">R1-R6</span> &gt;
                    <span style="color:#fb923c;font-weight:800;">D1-D8</span>
                </div>
            </div>

            <button id="nivCalcBtn" type="button" style="background:#6c5ce7;color:#fff;border:none;padding:12px 28px;border-radius:8px;font-size:15px;font-weight:700;cursor:pointer;box-shadow:0 4px 14px rgba(108,92,231,0.35);">&#9889; Calculer la repartition</button>
            <span id="nivCalcStatus" style="margin-left:14px;font-size:14px;font-weight:600;color:#8b949e;"></span>
        </div>

        <div id="nivResults" style="display:none;">
            <!-- Stats globales -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;margin-bottom:20px;">
                <div style="background:#161b22;border:1px solid #30363d;border-radius:10px;padding:16px;">
                    <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Total athletes</div>
                    <div id="nivTotalAth" style="color:#f0f6fc;font-size:28px;font-weight:800;">—</div>
                </div>
                <div style="background:#161b22;border:1px solid #30363d;border-radius:10px;padding:16px;">
                    <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Avec niveau (BDD + calcule)</div>
                    <div id="nivAvecNiv" style="color:#10b981;font-size:28px;font-weight:800;">—</div>
                    <div id="nivAvecNivBreakdown" class="muted" style="font-size:11px;margin-top:4px;"></div>
                </div>
                <div style="background:#161b22;border:1px solid #30363d;border-radius:10px;padding:16px;">
                    <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Toujours sans niveau</div>
                    <div id="nivSansNiv" style="color:#f59e0b;font-size:28px;font-weight:800;">—</div>
                    <div id="nivSansNivBreakdown" class="muted" style="font-size:11px;margin-top:4px;"></div>
                </div>
            </div>

            <!-- Mise en evidence des niveaux cles -->
            <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:18px;margin-bottom:20px;">
                <h3 style="color:#f0f6fc;font-size:14px;margin-bottom:12px;text-transform:uppercase;letter-spacing:1px;">Niveaux cles (statistiques principales)</h3>
                <div id="nivKey" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;"></div>
            </div>

            <!-- Detail par niveau (toute la hierarchie) -->
            <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:18px;margin-bottom:20px;">
                <h3 style="color:#f0f6fc;font-size:14px;margin-bottom:12px;text-transform:uppercase;letter-spacing:1px;">Detail complet par niveau</h3>
                <div id="nivAll"></div>
                <p class="muted" style="font-size:11px;margin-top:12px;text-align:right;" id="nivComputed"></p>
            </div>

            <!-- Epreuves problematiques (sans bareme) -->
            <div id="nivEpProblems" style="background:#1f1408;border:1px solid #f59e0b40;border-left:4px solid #f59e0b;border-radius:12px;padding:18px;display:none;">
                <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:8px;">
                    <h3 style="color:#fbbf24;font-size:15px;font-weight:700;margin:0;">&#9888; Epreuves sans bareme FFA</h3>
                    <button id="bmSmartBtn" type="button" style="background:linear-gradient(135deg,#6c5ce7,#ec4899);color:#fff;border:none;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:700;cursor:pointer;box-shadow:0 3px 10px rgba(108,92,231,0.3);">&#129302; Match intelligent</button>
                </div>
                <p style="color:#e6edf3;font-size:13px;line-height:1.6;margin-bottom:14px;">
                    Ces epreuves n'ont pas pu etre converties en niveau.
                    <strong style="color:#fbbf24;">Clique sur une epreuve</strong> pour l'associer manuellement, ou <strong style="color:#a78bfa;">utilise "Match intelligent"</strong> pour des suggestions automatiques basees sur le nom et le sexe.
                </p>
                <div id="nivEpList"></div>
            </div>
        </div>
    </section>

    <!-- Modal Match intelligent -->
    <div id="bmSmartBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9997;"></div>
    <div id="bmSmartModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#161b22;border:1px solid #30363d;border-radius:14px;padding:24px;width:760px;max-width:94vw;max-height:88vh;overflow:auto;z-index:9998;box-shadow:0 24px 60px rgba(0,0,0,0.6);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h3 style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0;">&#129302; Suggestions automatiques</h3>
            <button id="bmSmartClose" style="background:transparent;border:none;color:#8b949e;font-size:24px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div id="bmSmartIntro" style="background:#0d1117;border:1px solid #30363d;border-left:3px solid #6c5ce7;border-radius:8px;padding:12px 14px;margin-bottom:14px;color:#e6edf3;font-size:13px;line-height:1.6;">
            Match base sur la <strong style="color:#a78bfa;">similarite des noms</strong> + <strong style="color:#a78bfa;">distance/poids identiques</strong> + <strong style="color:#a78bfa;">categorie d'epreuve</strong>. Score 0-100, vert &ge; 70.
        </div>
        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
            <button id="bmSmartApplyHigh" style="background:#10b981;color:#fff;border:none;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;">&#10003; Appliquer toutes les correspondances &ge; 70%</button>
            <button id="bmSmartApplyMid" style="background:#fbbf24;color:#000;border:none;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:700;cursor:pointer;">Appliquer aussi 50-70%</button>
        </div>
        <div id="bmSmartList"></div>
        <div id="bmSmartStatus" style="margin-top:14px;font-size:13px;text-align:center;font-weight:600;"></div>
    </div>

    <!-- Modal mapping bareme -->
    <div id="bmMapBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:9998;"></div>
    <div id="bmMapModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#161b22;border:1px solid #30363d;border-radius:14px;padding:24px;width:540px;max-width:92vw;max-height:80vh;overflow:auto;z-index:9999;box-shadow:0 24px 60px rgba(0,0,0,0.6);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:14px;">
            <h3 style="color:#f0f6fc;font-size:17px;font-weight:700;margin:0;">Associer a un bareme</h3>
            <button id="bmMapClose" style="background:transparent;border:none;color:#8b949e;font-size:24px;cursor:pointer;line-height:1;">&times;</button>
        </div>
        <div style="background:#0d1117;border:1px solid #30363d;border-left:3px solid #6c5ce7;border-radius:8px;padding:12px 14px;margin-bottom:14px;">
            <div class="muted" style="font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Epreuve BDD</div>
            <div id="bmMapEpName" style="color:#f0f6fc;font-size:15px;font-weight:700;"></div>
            <div id="bmMapCurrent" class="muted" style="font-size:12px;margin-top:6px;"></div>
        </div>
        <input type="text" id="bmMapSearch" placeholder="Rechercher un bareme..." style="width:100%;background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:10px 12px;color:#f0f6fc;font-size:14px;margin-bottom:10px;">
        <div id="bmMapList" style="max-height:300px;overflow-y:auto;border:1px solid #30363d;border-radius:8px;background:#0d1117;"></div>
        <div style="display:flex;gap:10px;justify-content:flex-end;margin-top:14px;">
            <button id="bmMapClear" style="background:transparent;border:1px solid #f85149;color:#f85149;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Supprimer le mapping</button>
            <button id="bmMapCancel" style="background:transparent;border:1px solid #30363d;color:#c9d1d9;padding:8px 16px;border-radius:6px;font-size:13px;font-weight:600;cursor:pointer;">Annuler</button>
        </div>
        <div id="bmMapStatus" style="margin-top:10px;font-size:12px;text-align:center;"></div>
    </div>

    <!-- Section Inscription -->
    <section class="tab-pane" data-pane="inscription">

        <!-- Stats par mois -->
        <?php if (!empty($inscMonths)): ?>
        <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:20px;margin-bottom:20px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:14px;">
                <h2 style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0;">&#128200; Inscriptions par mois</h2>
                <div style="display:flex;gap:14px;flex-wrap:wrap;font-size:12px;">
                    <span style="color:#8b949e;"><span style="display:inline-block;width:11px;height:11px;background:#4285f4;border-radius:2px;vertical-align:middle;margin-right:5px;"></span>Google</span>
                    <span style="color:#8b949e;"><span style="display:inline-block;width:11px;height:11px;background:#a78bfa;border-radius:2px;vertical-align:middle;margin-right:5px;"></span>Email</span>
                </div>
            </div>

            <!-- Graphique en barres -->
            <div style="display:flex;align-items:flex-end;gap:6px;height:200px;padding:10px 0;border-bottom:1px solid #30363d;overflow-x:auto;">
                <?php
                // Inverse pour afficher du plus ancien au plus recent
                $monthsAsc = array_reverse($inscMonths);
                foreach ($monthsAsc as $m):
                    $ymParts = explode('-', $m['ym']);
                    $monthNum = (int)($ymParts[1] ?? 0);
                    $year = (int)($ymParts[0] ?? 0);
                    $monthLabel = ($moisFrLong[$monthNum] ?? '') . ' ' . $year;
                    $monthShort = substr($moisFrLong[$monthNum] ?? '', 0, 3) . ' ' . substr($year, 2);
                    $nbG = (int)$m['nb_google'];
                    $nbE = (int)$m['nb_email'];
                    $nbT = (int)$m['nb_total'];
                    $hPct = $inscMaxMonth > 0 ? ($nbT / $inscMaxMonth * 100) : 0;
                    $hG = $nbT > 0 ? ($nbG / $nbT * $hPct) : 0;
                    $hE = $nbT > 0 ? ($nbE / $nbT * $hPct) : 0;
                ?>
                <div style="flex:0 0 44px;display:flex;flex-direction:column;align-items:center;height:100%;" title="<?= htmlspecialchars($monthLabel) ?> : <?= $nbT ?> (Google: <?= $nbG ?>, Email: <?= $nbE ?>)">
                    <div style="font-size:10px;color:#8b949e;margin-bottom:3px;font-weight:600;"><?= $nbT ?></div>
                    <div style="flex:1;width:28px;display:flex;flex-direction:column;justify-content:flex-end;">
                        <?php if ($nbE > 0): ?>
                            <div style="background:#a78bfa;height:<?= $hE ?>%;border-radius:2px 2px 0 0;"></div>
                        <?php endif; ?>
                        <?php if ($nbG > 0): ?>
                            <div style="background:#4285f4;height:<?= $hG ?>%;<?= $nbE === 0 ? 'border-radius:2px 2px 0 0;' : '' ?>"></div>
                        <?php endif; ?>
                    </div>
                    <div style="font-size:10px;color:#8b949e;margin-top:6px;text-align:center;line-height:1.2;white-space:nowrap;"><?= htmlspecialchars($monthShort) ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Tableau recap -->
            <div style="margin-top:16px;max-height:320px;overflow-y:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;">
                    <thead style="position:sticky;top:0;background:#161b22;">
                        <tr style="color:#8b949e;text-transform:uppercase;font-size:11px;letter-spacing:1px;">
                            <th style="text-align:left;padding:8px 10px;border-bottom:1px solid #30363d;">Mois</th>
                            <th style="text-align:right;padding:8px 10px;border-bottom:1px solid #30363d;">Total</th>
                            <th style="text-align:right;padding:8px 10px;border-bottom:1px solid #30363d;color:#4285f4;">Google</th>
                            <th style="text-align:right;padding:8px 10px;border-bottom:1px solid #30363d;color:#a78bfa;">Email</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($inscMonths as $m):
                        $ymParts = explode('-', $m['ym']);
                        $monthNum = (int)($ymParts[1] ?? 0);
                        $year = (int)($ymParts[0] ?? 0);
                        $monthLabel = ($moisFrLong[$monthNum] ?? '') . ' ' . $year;
                    ?>
                        <tr style="color:#e6edf3;">
                            <td style="padding:8px 10px;border-bottom:1px solid #21262d;font-weight:600;"><?= htmlspecialchars($monthLabel) ?></td>
                            <td style="text-align:right;padding:8px 10px;border-bottom:1px solid #21262d;font-weight:700;color:#f0f6fc;"><?= (int)$m['nb_total'] ?></td>
                            <td style="text-align:right;padding:8px 10px;border-bottom:1px solid #21262d;color:#4285f4;"><?= (int)$m['nb_google'] ?></td>
                            <td style="text-align:right;padding:8px 10px;border-bottom:1px solid #21262d;color:#a78bfa;"><?= (int)$m['nb_email'] ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Sous-onglets -->
        <nav class="tabs-sub">
            <button class="tab-sub active" data-sub="google">Inscription avec Google <span class="cnt"><?= $totalGoogle ?></span></button>
            <button class="tab-sub" data-sub="email">Adresse mail <span class="cnt"><?= $totalEmail ?></span></button>
        </nav>

        <input type="text" id="filter" class="search" placeholder="Filtrer par email, role...">

        <!-- Sous-pane Google -->
        <div class="sub-pane active" data-sub-pane="google">
            <?php if ($totalGoogle === 0): ?>
                <p class="muted" style="text-align:center; padding:30px;">Aucune inscription via Google.</p>
            <?php else: ?>
                <table class="userTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th class="sortable" data-sort="email" data-type="text">Email <span class="arr"></span></th>
                            <th class="sortable" data-sort="actions" data-type="num">Actions <span class="arr"></span></th>
                            <th class="sortable active asc-false" data-sort="date" data-type="text">Inscrit le <span class="arr">▼</span></th>
                            <th class="sortable" data-sort="last" data-type="text">Derniere connexion <span class="arr"></span></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usersGoogle as $i => $u): ?>
                        <tr data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>" data-actions="<?= (int)$u['nb_actions'] ?>" data-date="<?= htmlspecialchars($u['date_creation'] ?? '') ?>" data-last="<?= htmlspecialchars($u['last_login'] ?? '') ?>">
                            <td class="rownum muted"><?= $i + 1 ?></td>
                            <td>
                                <div class="user-cell">
                                    <?php if (!empty($u['picture'])): ?>
                                        <img src="<?= htmlspecialchars($u['picture']) ?>" alt="" class="user-avatar" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <div class="user-avatar user-avatar-fallback"><?= strtoupper(htmlspecialchars(mb_substr($u['prenom'] ?: $u['email'], 0, 1))) ?></div>
                                    <?php endif; ?>
                                    <div class="user-info">
                                        <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                                        <?php if (!empty($u['prenom']) || !empty($u['nom'])): ?>
                                            <div class="user-name"><?= htmlspecialchars(trim($u['prenom'] . ' ' . $u['nom'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="nb-act<?= $u['nb_actions'] > 0 ? ' on' : '' ?>"><?= number_format($u['nb_actions'], 0, ',', ' ') ?></span></td>
                            <td class="muted"><?= htmlspecialchars($u['date_creation'] ?? '-') ?></td>
                            <td class="muted"><?= htmlspecialchars($u['last_login'] ?? '-') ?></td>
                            <td>
                                <button class="btn-act" data-uid="<?= (int)$u['id_user'] ?>" data-email="<?= htmlspecialchars($u['email']) ?>">Voir</button>
                                <button class="btn-act btn-mail-user" data-uid="<?= (int)$u['id_user'] ?>" data-email="<?= htmlspecialchars($u['email']) ?>" data-name="<?= htmlspecialchars(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?>" style="background:#10b981;color:#fff;margin-left:6px;">Mail</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>

        <!-- Sous-pane Adresse mail -->
        <div class="sub-pane" data-sub-pane="email">
            <?php if ($totalEmail === 0): ?>
                <p class="muted" style="text-align:center; padding:30px;">Aucune inscription par email.</p>
            <?php else: ?>
                <table class="userTable">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th class="sortable" data-sort="email" data-type="text">Email <span class="arr"></span></th>
                            <th class="sortable" data-sort="actions" data-type="num">Actions <span class="arr"></span></th>
                            <th class="sortable active asc-false" data-sort="date" data-type="text">Inscrit le <span class="arr">▼</span></th>
                            <th class="sortable" data-sort="last" data-type="text">Derniere connexion <span class="arr"></span></th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usersEmail as $i => $u): ?>
                        <tr data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>" data-actions="<?= (int)$u['nb_actions'] ?>" data-date="<?= htmlspecialchars($u['date_creation'] ?? '') ?>" data-last="<?= htmlspecialchars($u['last_login'] ?? '') ?>">
                            <td class="rownum muted"><?= $i + 1 ?></td>
                            <td>
                                <div class="user-cell">
                                    <?php if (!empty($u['picture'])): ?>
                                        <img src="<?= htmlspecialchars($u['picture']) ?>" alt="" class="user-avatar" referrerpolicy="no-referrer">
                                    <?php else: ?>
                                        <div class="user-avatar user-avatar-fallback"><?= strtoupper(htmlspecialchars(mb_substr($u['prenom'] ?: $u['email'], 0, 1))) ?></div>
                                    <?php endif; ?>
                                    <div class="user-info">
                                        <div class="user-email"><?= htmlspecialchars($u['email']) ?></div>
                                        <?php if (!empty($u['prenom']) || !empty($u['nom'])): ?>
                                            <div class="user-name"><?= htmlspecialchars(trim($u['prenom'] . ' ' . $u['nom'])) ?></div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><span class="nb-act<?= $u['nb_actions'] > 0 ? ' on' : '' ?>"><?= number_format($u['nb_actions'], 0, ',', ' ') ?></span></td>
                            <td class="muted"><?= htmlspecialchars($u['date_creation'] ?? '-') ?></td>
                            <td class="muted"><?= htmlspecialchars($u['last_login'] ?? '-') ?></td>
                            <td>
                                <button class="btn-act" data-uid="<?= (int)$u['id_user'] ?>" data-email="<?= htmlspecialchars($u['email']) ?>">Voir</button>
                                <button class="btn-act btn-mail-user" data-uid="<?= (int)$u['id_user'] ?>" data-email="<?= htmlspecialchars($u['email']) ?>" data-name="<?= htmlspecialchars(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?>" style="background:#10b981;color:#fff;margin-left:6px;">Mail</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section Abonnés / Membres premium -->
    <section class="tab-pane" data-pane="abonnes">

        <!-- Mode test : aperçu en tant que… -->
        <div style="background:#161b22;border:1px solid #f59e0b55;border-radius:12px;padding:20px 22px;margin-bottom:20px;">
            <h2 style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0 0 6px;">&#129514; Mode test &mdash; voir le site en tant que&hellip;</h2>
            <p class="muted" style="margin:0 0 14px;font-size:13px;">Ouvre le site dans un nouvel onglet en simulant ce type d'utilisateur (paywall, limites de recherche, options premium). Une banni&egrave;re en bas permet de quitter le mode test.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <?php
                $_testRoles = [
                    'visitor' => ['Visiteur', '#8b949e'],
                    'free'    => ['Membre gratuit', '#58a6ff'],
                    'bronze'  => ['Bronze', $BK_PLANS['bronze']['color'] ?? '#cd7f32'],
                    'argent'  => ['Argent', $BK_PLANS['argent']['color'] ?? '#9ca3af'],
                    'or'      => ['Or', $BK_PLANS['or']['color'] ?? '#f59e0b'],
                    'platine' => ['Platine', $BK_PLANS['platine']['color'] ?? '#6c5ce7'],
                ];
                foreach ($_testRoles as $rk => $ri): ?>
                    <button type="button" onclick="bkSetTestRole('<?= $rk ?>')"
                        style="background:<?= $ri[1] ?>22;border:1px solid <?= $ri[1] ?>;color:<?= $ri[1] ?>;border-radius:8px;padding:10px 18px;font-size:13px;font-weight:700;cursor:pointer;">
                        <?= htmlspecialchars($ri[0]) ?>
                    </button>
                <?php endforeach; ?>
                <button type="button" onclick="bkClearTestRole()"
                    style="background:transparent;border:1px solid #30363d;color:#8b949e;border-radius:8px;padding:10px 18px;font-size:13px;font-weight:700;cursor:pointer;">
                    &#10005; D&eacute;sactiver
                </button>
            </div>
        </div>
        <script>
        function bkSetTestRole(r){
            // Timer de "première visite" remis à zéro → la découverte 60 s repart à neuf
            var now = Math.floor(Date.now() / 1000);
            document.cookie = 'bk_test_role=' + r + ';path=/;max-age=86400';
            document.cookie = 'bk_test_t0=' + now + ';path=/;max-age=86400';
            window.open('../', '_blank');
        }
        function bkClearTestRole(){
            document.cookie = 'bk_test_role=;path=/;max-age=0';
            document.cookie = 'bk_test_t0=;path=/;max-age=0';
            alert('Mode test désactivé. Recharge le site si un onglet est encore ouvert.');
        }
        </script>

        <?php
        // Index user par id pour enrichir les lignes d'abonnement
        $userById = [];
        foreach ($allRecent as $_u) $userById[(int)$_u['id_user']] = $_u;
        $subRows = [];
        foreach ($subByUser as $uid => $s) {
            $u = $userById[$uid] ?? null;
            $subRows[] = [
                'uid'    => $uid,
                'email'  => $u['email'] ?? ('compte #' . $uid),
                'nom'    => $u ? trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) : '',
                'plan'   => $s['plan'],
                'status' => $s['status'],
                'actif'  => $s['actif'],
                'source' => (($s['billing_period'] ?? '') === 'manuel') ? 'Manuel' : 'Stripe',
                'fin'    => $s['current_period_end'],
                'maj'    => $s['updated_at'] ?? null,
            ];
        }
        usort($subRows, function ($a, $b) {
            if ($a['actif'] !== $b['actif']) return ($b['actif'] ? 1 : 0) - ($a['actif'] ? 1 : 0);
            return strcmp((string)$b['maj'], (string)$a['maj']);
        });
        ?>

        <!-- KPI cards -->
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:14px;margin-bottom:20px;">
            <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:18px;">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:#8b949e;">Abonnés actifs</div>
                <div style="font-size:30px;font-weight:800;color:#10b981;margin-top:4px;"><?= $subStats['active'] ?></div>
                <div style="font-size:12px;color:#8b949e;margin-top:2px;"><?= $subStats['stripe'] ?> Stripe · <?= $subStats['manuel'] ?> manuel</div>
            </div>
            <?php foreach (['bronze','argent','or','platine'] as $pk):
                $pc = $BK_PLANS[$pk]['color'] ?? '#6c5ce7';
                $pn = $BK_PLANS[$pk]['name'] ?? ucfirst($pk);
            ?>
            <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:18px;">
                <div style="font-size:11px;text-transform:uppercase;letter-spacing:1px;color:<?= $pc ?>;">&#127941; <?= htmlspecialchars($pn) ?></div>
                <div style="font-size:30px;font-weight:800;color:#f0f6fc;margin-top:4px;"><?= (int)$subStats['plans'][$pk] ?></div>
                <div style="font-size:12px;color:#8b949e;margin-top:2px;">abonné(s) actif(s)</div>
            </div>
            <?php endforeach; ?>
        </div>

        <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:20px 22px;">
            <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;">
                <h2 style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0;">&#128142; Membres abonnés (<?= count($subRows) ?>)</h2>
                <a href="subscriptions.php" target="_blank" style="background:linear-gradient(135deg,#6c5ce7,#ec4899);color:#fff;text-decoration:none;font-size:13px;font-weight:700;padding:9px 16px;border-radius:8px;">Gérer les accès &rarr;</a>
            </div>
            <input type="text" id="subFilter" class="search" placeholder="Filtrer par email, nom, offre..." onkeyup="bkSubFilter(this.value)">

            <?php if (empty($subRows)): ?>
                <p class="muted" style="text-align:center;padding:30px;">Aucun abonnement pour le moment.</p>
            <?php else: ?>
            <div style="margin-top:14px;overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:13px;" id="subTable">
                    <thead>
                        <tr style="color:#8b949e;text-transform:uppercase;font-size:11px;letter-spacing:1px;">
                            <th style="text-align:left;padding:9px 10px;border-bottom:1px solid #30363d;">Membre</th>
                            <th style="text-align:left;padding:9px 10px;border-bottom:1px solid #30363d;">Offre</th>
                            <th style="text-align:left;padding:9px 10px;border-bottom:1px solid #30363d;">Statut</th>
                            <th style="text-align:left;padding:9px 10px;border-bottom:1px solid #30363d;">Source</th>
                            <th style="text-align:left;padding:9px 10px;border-bottom:1px solid #30363d;">Échéance</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($subRows as $r):
                        $pc = $BK_PLANS[$r['plan']]['color'] ?? '#6c5ce7';
                        $pn = $BK_PLANS[$r['plan']]['name'] ?? ucfirst((string)$r['plan']);
                        $finTxt = $r['fin'] ? date('d/m/Y', strtotime($r['fin'])) : 'illimité';
                        $searchKey = strtolower($r['email'] . ' ' . $r['nom'] . ' ' . $pn);
                    ?>
                        <tr data-search="<?= htmlspecialchars($searchKey) ?>" style="color:#e6edf3;">
                            <td style="padding:9px 10px;border-bottom:1px solid #21262d;">
                                <div style="font-weight:600;"><?= htmlspecialchars($r['nom'] ?: '—') ?></div>
                                <div class="muted" style="font-size:12px;"><?= htmlspecialchars($r['email']) ?></div>
                            </td>
                            <td style="padding:9px 10px;border-bottom:1px solid #21262d;">
                                <span style="display:inline-block;padding:3px 10px;border-radius:100px;font-size:12px;font-weight:700;background:<?= $pc ?>22;color:<?= $pc ?>;border:1px solid <?= $pc ?>55;"><?= htmlspecialchars($pn) ?></span>
                            </td>
                            <td style="padding:9px 10px;border-bottom:1px solid #21262d;">
                                <?php if ($r['actif']): ?>
                                    <span style="display:inline-block;padding:3px 10px;border-radius:100px;font-size:12px;font-weight:700;background:#10b98120;color:#34d399;">&#9679; Actif</span>
                                <?php else: ?>
                                    <span style="display:inline-block;padding:3px 10px;border-radius:100px;font-size:12px;font-weight:700;background:#f8514920;color:#ff7b72;"><?= htmlspecialchars($r['status'] ?: 'inactif') ?></span>
                                <?php endif; ?>
                            </td>
                            <td style="padding:9px 10px;border-bottom:1px solid #21262d;">
                                <span style="color:<?= $r['source'] === 'Manuel' ? '#a78bfa' : '#79c0ff' ?>;font-weight:600;"><?= $r['source'] ?></span>
                            </td>
                            <td style="padding:9px 10px;border-bottom:1px solid #21262d;" class="muted"><?= $finTxt ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <script>
        function bkSubFilter(q){
            q = (q||'').toLowerCase().trim();
            var rows = document.querySelectorAll('#subTable tbody tr');
            rows.forEach(function(tr){
                var k = tr.getAttribute('data-search') || '';
                tr.style.display = (q === '' || k.indexOf(q) !== -1) ? '' : 'none';
            });
        }
        </script>
    </section>

    <!-- Section Suivi navigation — detail par utilisateur -->
    <section class="tab-pane" data-pane="usernav">
        <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:20px 22px;margin-bottom:20px;">
            <h2 style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0 0 4px;">&#128270; Suivi navigation — detail par utilisateur</h2>
            <p class="muted" style="margin:0 0 14px;font-size:13px;">Chaque adresse mail : cliquez pour deplier la navigation complete, page par page (jusqu'a 1000 dernieres actions).</p>
            <input type="text" id="navFilter" class="search" placeholder="Filtrer par email...">
            <div id="navList" style="margin-top:12px;">
                <?php
                $navUsers = array_merge($usersGoogle, $usersEmail);
                usort($navUsers, function($a, $b) { return ($b['nb_actions'] ?? 0) <=> ($a['nb_actions'] ?? 0); });
                if (empty($navUsers)): ?>
                    <p class="muted" style="text-align:center;padding:30px;">Aucun utilisateur inscrit.</p>
                <?php else: foreach ($navUsers as $u): ?>
                    <div class="nav-user" data-email="<?= htmlspecialchars(strtolower($u['email'])) ?>">
                        <div class="nav-user-head" data-uid="<?= (int)$u['id_user'] ?>" data-email="<?= htmlspecialchars($u['email']) ?>">
                            <span class="nav-user-caret">&#9656;</span>
                            <?php if (!empty($u['picture'])): ?>
                                <img src="<?= htmlspecialchars($u['picture']) ?>" alt="" class="user-avatar" referrerpolicy="no-referrer">
                            <?php else: ?>
                                <div class="user-avatar user-avatar-fallback"><?= strtoupper(htmlspecialchars(mb_substr($u['prenom'] ?: $u['email'], 0, 1))) ?></div>
                            <?php endif; ?>
                            <div style="flex:1;min-width:0;">
                                <div class="nav-user-email"><?= htmlspecialchars($u['email']) ?></div>
                                <?php if (!empty($u['prenom']) || !empty($u['nom'])): ?>
                                    <div class="muted" style="font-size:12px;"><?= htmlspecialchars(trim($u['prenom'] . ' ' . $u['nom'])) ?></div>
                                <?php endif; ?>
                            </div>
                            <span class="nav-user-badge<?= ($u['nb_actions'] ?? 0) > 0 ? ' on' : '' ?>"><?= number_format($u['nb_actions'] ?? 0, 0, ',', ' ') ?> actions</span>
                            <span class="muted" style="font-size:11px;white-space:nowrap;">Derniere : <?= htmlspecialchars($u['last_login'] ?: '-') ?></span>
                        </div>
                        <div class="nav-user-body" data-loaded="0"></div>
                    </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </section>

    <!-- Section Signalements -->
    <section class="tab-pane" data-pane="reports">
        <nav class="tabs-sub">
            <button class="tab-sub active" data-sub-rep="new">Nouveaux <span class="cnt"><?= count($reportNew) ?></span></button>
            <button class="tab-sub" data-sub-rep="read">Lus <span class="cnt"><?= count($reportRead) ?></span></button>
            <button class="tab-sub" data-sub-rep="resolved">Resolus <span class="cnt"><?= count($reportResolved) ?></span></button>
        </nav>

        <?php
        $renderReport = function($rp, $reasonLabels) {
            $statusClass = $rp['status'] === 'new' ? 'rep-new' : ($rp['status'] === 'read' ? 'rep-read' : 'rep-resolved');
            $statusLabel = $rp['status'] === 'new' ? 'Nouveau' : ($rp['status'] === 'read' ? 'Lu' : 'Resolu');
            ?>
            <div class="rep-card <?= $statusClass ?>" data-id="<?= (int)$rp['id_report'] ?>" data-athlete="<?= (int)$rp['athlete_id_ext'] ?>">
                <div class="rep-head">
                    <div class="rep-from">
                        <a href="/?page=profil&id=<?= (int)$rp['athlete_id_ext'] ?>" target="_blank" class="rep-link"><?= htmlspecialchars($rp['athlete_name']) ?></a>
                        <span class="muted">#<?= (int)$rp['athlete_id_ext'] ?></span>
                    </div>
                    <div class="rep-tags">
                        <span class="msg-tag tag-red"><?= htmlspecialchars($reasonLabels[$rp['reason']] ?? $rp['reason']) ?></span>
                        <span class="msg-tag tag-<?= $rp['status'] === 'new' ? 'red' : ($rp['status'] === 'read' ? 'orange' : 'green') ?>"><?= $statusLabel ?></span>
                        <?php if ((int)$rp['athlete_visible'] === 0): ?>
                            <span class="msg-tag tag-orange">Profil masque</span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if (!empty($rp['message'])): ?>
                    <div class="msg-body"><?= htmlspecialchars($rp['message']) ?></div>
                <?php endif; ?>
                <div class="msg-meta">
                    <span>IP : <?= htmlspecialchars($rp['ip']) ?></span>
                    <?php if (!empty($rp['email'])): ?><span>Email : <?= htmlspecialchars($rp['email']) ?></span><?php endif; ?>
                    <span>Recu : <?= htmlspecialchars($rp['created_at']) ?></span>
                </div>
                <div class="msg-actions">
                    <?php if ($rp['status'] === 'new'): ?>
                        <button class="btn-msg btn-read" data-rep-act="mark_read" data-id="<?= (int)$rp['id_report'] ?>">Marquer lu</button>
                    <?php endif; ?>
                    <?php if ($rp['status'] !== 'resolved'): ?>
                        <button class="btn-msg btn-reply" data-rep-act="resolve" data-id="<?= (int)$rp['id_report'] ?>">Marquer resolu</button>
                    <?php endif; ?>
                    <?php if ((int)$rp['athlete_visible'] === 1): ?>
                        <button class="btn-msg btn-hide" data-rep-act="hide_athlete" data-athlete="<?= (int)$rp['athlete_id_ext'] ?>" style="background:#dc2626;border-color:#dc2626;">Supprimer definitivement</button>
                    <?php else: ?>
                        <button class="btn-msg btn-show" data-rep-act="show_athlete" data-athlete="<?= (int)$rp['athlete_id_ext'] ?>">Retirer de la blacklist</button>
                    <?php endif; ?>
                    <?php if (!empty($rp['email'])): ?>
                        <button class="btn-msg btn-reply" data-rep-reply="<?= (int)$rp['id_report'] ?>">Repondre</button>
                    <?php endif; ?>
                    <button class="btn-msg btn-del" data-rep-act="delete" data-id="<?= (int)$rp['id_report'] ?>">Supprimer</button>
                </div>
                <?php if (!empty($rp['email'])): ?>
                <div class="rep-reply-form" id="repReplyForm<?= (int)$rp['id_report'] ?>" style="display:none;background:#0d1117;border:1px solid #30363d;border-radius:10px;padding:16px;margin-top:12px;">
                    <div style="margin-bottom:10px;color:#8b949e;font-size:12px;">Reponse a <strong style="color:#e6edf3;"><?= htmlspecialchars($rp['email']) ?></strong></div>
                    <input type="text" class="rep-reply-subject" placeholder="Sujet" value="Re: votre signalement - Bokonzi" maxlength="200" style="width:100%;background:#161b22;border:1px solid #30363d;border-radius:6px;padding:9px 12px;color:#f0f6fc;font-size:13px;margin-bottom:10px;box-sizing:border-box;">
                    <textarea class="rep-reply-body" placeholder="Votre reponse..." rows="6" maxlength="10000" style="width:100%;background:#161b22;border:1px solid #30363d;border-radius:6px;padding:9px 12px;color:#f0f6fc;font-size:13px;line-height:1.5;resize:vertical;box-sizing:border-box;font-family:inherit;"></textarea>
                    <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px;align-items:center;">
                        <span class="rep-reply-status" style="font-size:12px;font-weight:600;margin-right:auto;"></span>
                        <button class="btn-msg btn-rep-reply-cancel" data-cancel="<?= (int)$rp['id_report'] ?>">Annuler</button>
                        <button class="btn-msg btn-rep-reply-send" data-send="<?= (int)$rp['id_report'] ?>" style="background:#10b981;color:#fff;">Envoyer</button>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        <?php }; ?>

        <div class="sub-pane active" data-sub-rep-pane="new">
            <?php if (empty($reportNew)): ?>
                <p class="muted" style="text-align:center; padding:30px;">Aucun nouveau signalement.</p>
            <?php else: ?>
                <div class="msg-list">
                    <?php foreach ($reportNew as $rp) $renderReport($rp, $reasonLabels); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="sub-pane" data-sub-rep-pane="read">
            <?php if (empty($reportRead)): ?>
                <p class="muted" style="text-align:center; padding:30px;">Aucun signalement lu en attente.</p>
            <?php else: ?>
                <div class="msg-list">
                    <?php foreach ($reportRead as $rp) $renderReport($rp, $reasonLabels); ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="sub-pane" data-sub-rep-pane="resolved">
            <?php if (empty($reportResolved)): ?>
                <p class="muted" style="text-align:center; padding:30px;">Aucun signalement resolu.</p>
            <?php else: ?>
                <div class="msg-list">
                    <?php foreach ($reportResolved as $rp) $renderReport($rp, $reasonLabels); ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Section Mails reçus -->
    <section class="tab-pane" data-pane="mails">
        <nav class="tabs-sub">
            <button class="tab-sub active" data-sub-mails="unconfirmed">Non confirme <span class="cnt"><?= $totalUnconfirmed ?></span></button>
            <button class="tab-sub" data-sub-mails="confirmed">Confirme <span class="cnt"><?= $totalConfirmed ?></span><?= $totalUnread > 0 ? '<span class="cnt-alert">' . $totalUnread . ' non lu</span>' : '' ?></button>
            <button class="tab-sub" data-sub-mails="sent">Envoyes <span class="cnt"><?= $totalSent ?></span></button>
        </nav>

        <!-- Sous-pane Non confirme -->
        <div class="sub-pane active" data-sub-mails-pane="unconfirmed">
            <?php if ($totalUnconfirmed === 0): ?>
                <p class="muted" style="text-align:center; padding:30px;">Aucun mail en attente de confirmation.</p>
            <?php else: ?>
                <p class="muted" style="margin-bottom:14px;">Ces personnes ont rempli le formulaire de contact mais n'ont pas encore valide le lien email. Le message ne sera transmis qu'apres confirmation.</p>
                <div class="msg-list">
                    <?php foreach ($msgsUnconfirmed as $m): ?>
                        <div class="msg-card msg-unconfirmed<?= $m['expired'] ? ' msg-expired' : '' ?>" data-id="<?= (int)$m['id'] ?>">
                            <div class="msg-head">
                                <div class="msg-from">
                                    <strong><?= htmlspecialchars($m['nom'] ?: '(sans nom)') ?></strong>
                                    <span class="muted"><?= htmlspecialchars($m['email']) ?></span>
                                </div>
                                <div class="msg-tags">
                                    <span class="msg-tag tag-orange">Non confirme</span>
                                    <?php if ($m['expired']): ?><span class="msg-tag tag-red">Expire</span><?php endif; ?>
                                </div>
                            </div>
                            <div class="msg-body"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                            <div class="msg-meta">
                                <span>IP : <?= htmlspecialchars($m['ip']) ?></span>
                                <span>Recu : <?= htmlspecialchars($m['created_at']) ?></span>
                                <span>Expire : <?= htmlspecialchars($m['expires_at']) ?></span>
                            </div>
                            <div class="msg-actions">
                                <button class="btn-msg btn-del" data-act="delete_token" data-id="<?= (int)$m['id'] ?>">Effacer</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Sous-pane Confirme -->
        <div class="sub-pane" data-sub-mails-pane="confirmed">
            <?php
            // Closure de rendu d'une carte message confirme (reutilisee pour les 2 groupes)
            $renderConfirmedCard = function($m) {
            ?>
                        <div class="msg-card<?= (int)$m['lu'] === 0 ? ' msg-unread' : '' ?>" data-id="<?= (int)$m['id_msg'] ?>">
                            <div class="msg-head">
                                <div class="msg-from">
                                    <strong><?= htmlspecialchars($m['nom'] ?: '(sans nom)') ?></strong>
                                    <span class="muted"><?= htmlspecialchars($m['email']) ?></span>
                                </div>
                                <div class="msg-tags">
                                    <?php if ((int)$m['lu'] === 0): ?>
                                        <span class="msg-tag tag-blue">Non lu</span>
                                    <?php else: ?>
                                        <span class="msg-tag tag-gray">Lu</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="msg-body"><?= nl2br(htmlspecialchars($m['message'])) ?></div>
                            <div class="msg-meta">
                                <span>IP : <?= htmlspecialchars($m['ip']) ?></span>
                                <span>Recu : <?= htmlspecialchars($m['created_at']) ?></span>
                            </div>
                            <div class="msg-actions">
                                <?php if ((int)$m['lu'] === 0): ?>
                                    <button class="btn-msg btn-read" data-act="mark_read" data-id="<?= (int)$m['id_msg'] ?>">Marquer comme lu</button>
                                <?php else: ?>
                                    <button class="btn-msg btn-unread" data-act="mark_unread" data-id="<?= (int)$m['id_msg'] ?>">Marquer non lu</button>
                                <?php endif; ?>
                                <button class="btn-msg btn-reply" data-act-reply="<?= (int)$m['id_msg'] ?>">Repondre</button>
                                <button class="btn-msg btn-del" data-act="delete" data-id="<?= (int)$m['id_msg'] ?>">Effacer</button>
                            </div>
                            <div class="reply-form" id="replyForm<?= (int)$m['id_msg'] ?>" style="display:none;background:#0d1117;border:1px solid #30363d;border-radius:10px;padding:16px;margin-top:12px;">
                                <div style="margin-bottom:10px;color:#8b949e;font-size:12px;">Reponse a <strong style="color:#e6edf3;"><?= htmlspecialchars($m['email']) ?></strong></div>
                                <input type="text" class="reply-subject" placeholder="Sujet" value="Re: votre message - Bokonzi" maxlength="200" style="width:100%;background:#161b22;border:1px solid #30363d;border-radius:6px;padding:9px 12px;color:#f0f6fc;font-size:13px;margin-bottom:10px;box-sizing:border-box;">
                                <textarea class="reply-body" placeholder="Votre reponse..." rows="6" maxlength="10000" style="width:100%;background:#161b22;border:1px solid #30363d;border-radius:6px;padding:9px 12px;color:#f0f6fc;font-size:13px;line-height:1.5;resize:vertical;box-sizing:border-box;font-family:inherit;"></textarea>
                                <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:10px;align-items:center;">
                                    <span class="reply-status" style="font-size:12px;font-weight:600;margin-right:auto;"></span>
                                    <button class="btn-msg btn-reply-cancel" data-cancel="<?= (int)$m['id_msg'] ?>">Annuler</button>
                                    <button class="btn-msg btn-reply-send" data-send="<?= (int)$m['id_msg'] ?>" style="background:#10b981;color:#fff;">Envoyer</button>
                                </div>
                            </div>
                        </div>
            <?php
            }; // fin closure
            ?>
            <?php if ($totalConfirmed === 0): ?>
                <p class="muted" style="text-align:center; padding:30px;">Aucun message confirme.</p>
            <?php else: ?>
                <!-- Groupe NON LUS -->
                <?php if ($totalUnread > 0): ?>
                    <div class="msg-group-head msg-group-unread">
                        <span class="msg-group-dot"></span>
                        Non lus <span class="msg-group-cnt"><?= $totalUnread ?></span>
                    </div>
                    <div class="msg-list">
                        <?php foreach ($msgsConfirmedUnread as $m) $renderConfirmedCard($m); ?>
                    </div>
                <?php endif; ?>

                <!-- Groupe LUS -->
                <?php if ($totalRead > 0): ?>
                    <div class="msg-group-head msg-group-read"<?= $totalUnread > 0 ? ' style="margin-top:26px;"' : '' ?>>
                        Lus <span class="msg-group-cnt"><?= $totalRead ?></span>
                    </div>
                    <div class="msg-list">
                        <?php foreach ($msgsConfirmedRead as $m) $renderConfirmedCard($m); ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Sous-pane Envoyes -->
        <div class="sub-pane" data-sub-mails-pane="sent">
            <?php if ($totalSent === 0): ?>
                <p class="muted" style="text-align:center; padding:30px;">Aucun mail envoye pour le moment.</p>
            <?php else: ?>
                <p class="muted" style="margin-bottom:14px;">Historique des <?= $totalSent ?> dernier(s) mail(s) envoye(s) depuis le panel.</p>
                <div class="msg-list">
                    <?php foreach ($sentEmails as $se):
                        $srcKey = $se['source'];
                        $srcLabel = $sourceLabels[$srcKey] ?? $srcKey;
                        $srcColor = $srcKey === 'reply_message' ? 'tag-blue' : ($srcKey === 'send_to_user' ? 'tag-green' : 'tag-orange');
                        $okBadge = (int)$se['success'] === 1 ? '<span class="msg-tag tag-green">Envoye</span>' : '<span class="msg-tag tag-red">Echec</span>';
                    ?>
                        <div class="msg-card" data-id="<?= (int)$se['id_sent'] ?>">
                            <div class="msg-head">
                                <div class="msg-from">
                                    <strong><?= htmlspecialchars($se['to_name'] ?: '(sans nom)') ?></strong>
                                    <span class="muted"><?= htmlspecialchars($se['to_email']) ?></span>
                                </div>
                                <div class="msg-tags">
                                    <span class="msg-tag <?= $srcColor ?>"><?= htmlspecialchars($srcLabel) ?></span>
                                    <?= $okBadge ?>
                                </div>
                            </div>
                            <div style="font-weight:700;color:#f0f6fc;margin:8px 0 6px;"><?= htmlspecialchars($se['subject']) ?></div>
                            <div class="msg-body" style="max-height:120px;overflow:hidden;position:relative;" data-full-body="<?= htmlspecialchars($se['body']) ?>"><?= nl2br(htmlspecialchars(mb_substr($se['body'], 0, 500))) ?><?= mb_strlen($se['body']) > 500 ? '...' : '' ?></div>
                            <?php if (mb_strlen($se['body']) > 500): ?>
                                <button class="btn-msg btn-show-full" style="margin-top:6px;font-size:11px;padding:4px 10px;">Voir tout</button>
                            <?php endif; ?>
                            <div class="msg-meta">
                                <span>Envoye le : <?= htmlspecialchars($se['sent_at']) ?></span>
                                <span>Par : <?= htmlspecialchars($se['sent_by'] ?: '-') ?></span>
                                <?php if ((int)$se['ref_id'] > 0): ?><span>Ref : #<?= (int)$se['ref_id'] ?></span><?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <?php
    // === Variables pour les onglets Outils/Chemins/Extraction/Routes ===
    require_once __DIR__ . '/../core/paths.php';
    $_toolsBase = (BK_IS_LOCAL ? 'http://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') : 'https://' . ($_SERVER['HTTP_HOST'] ?? 'bokonzi.com')) . BK_BASE;
    $_toolsKey = 'bk_s3cr3t_2026_xK9mP';
    ?>

    <style>
    .tools-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 14px; }
    .tools-card { background: #161b22; border: 1px solid #30363d; border-radius: 10px; padding: 16px; transition: all 0.2s; }
    .tools-card:hover { border-color: #6c5ce7; transform: translateY(-2px); }
    .tools-card h4 { margin: 0 0 6px; color: #f0f6fc; font-size: 14px; display: flex; align-items: center; gap: 8px; }
    .tools-card .desc { color: #8b949e; font-size: 12px; margin: 0 0 10px; line-height: 1.5; min-height: 32px; }
    .tools-card .url { font-family: 'JetBrains Mono', SF Mono, Consolas, monospace; font-size: 11px; color: #a5b4fc; background: #0d1117; padding: 6px 10px; border-radius: 6px; word-break: break-all; display: block; margin-bottom: 8px; }
    .tools-actions { display: flex; gap: 6px; flex-wrap: wrap; }
    .tools-btn { display: inline-flex; align-items: center; padding: 5px 10px; background: linear-gradient(135deg,#6c5ce7,#8b5cf6); color: #fff; border: 1px solid #6c5ce7; border-radius: 5px; font-size: 11px; font-weight: 600; cursor: pointer; text-decoration: none; transition: all 0.15s; }
    .tools-btn:hover { transform: translateY(-1px); }
    .tools-btn-copy { background: rgba(99,102,241,0.15); color: #a5b4fc; border-color: rgba(99,102,241,0.3); }
    .tools-tag { display: inline-block; padding: 1px 7px; background: rgba(99,102,241,0.15); color: #a5b4fc; border-radius: 10px; font-size: 10px; font-weight: 600; }
    .tools-tag.warn { background: rgba(245,158,11,0.15); color: #fcd34d; }
    .tools-tag.ok { background: rgba(16,185,129,0.15); color: #6ee7b7; }
    .path-row { padding: 8px 12px; background: #0d1117; border: 1px solid #30363d; border-radius: 6px; margin-bottom: 6px; font-family: 'JetBrains Mono', monospace; font-size: 12px; display: flex; justify-content: space-between; align-items: center; gap: 12px; }
    .path-row .lbl { color: #8b949e; }
    .path-row .pth { color: #f0f6fc; }
    .tools-section { background: #161b22; border: 1px solid #30363d; border-radius: 12px; padding: 24px; margin-bottom: 20px; }
    .tools-section h2 { color: #f0f6fc; font-size: 18px; font-weight: 700; margin: 0 0 16px; }
    .tools-section h4 { color: #f0f6fc; margin: 14px 0 8px; font-size: 13px; }
    </style>

    <!-- ════════════════════════════════════════════ -->
    <!-- TAB : Outils                                 -->
    <!-- ════════════════════════════════════════════ -->
    <section class="tab-pane" data-pane="tools">
        <div class="tools-section">
            <h2>&#9881; Outils administration</h2>
            <p style="color:#8b949e;font-size:12px;margin:0 0 16px">Cle API : <code style="background:#0d1117;padding:2px 6px;border-radius:3px;color:#a5b4fc"><?= htmlspecialchars($_toolsKey) ?></code></p>
            <div class="tools-grid">

                <div class="tools-card">
                    <h4>Archive Manager <span class="tools-tag ok">PRINCIPAL</span></h4>
                    <p class="desc">Export/Import tables BDD, bascule BDD/Fichier, install depuis archive.</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/db_archive.php?bk_key=<?= $_toolsKey ?>" target="_blank"><?= $_toolsBase ?>/admin/db_archive.php?bk_key=...</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/db_archive.php?bk_key=<?= $_toolsKey ?>" target="_blank">Ouvrir</a>
                        <button class="tools-btn tools-btn-copy" onclick="toolsCopy('<?= $_toolsBase ?>/admin/db_archive.php?bk_key=<?= $_toolsKey ?>', this)">Copier</button>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Verification doublons archives <span class="tools-tag ok">INTEGRITE</span></h4>
                    <p class="desc">Scanne tous les .jsonl et detecte les doublons par cle primaire. Rapport complet avec exemples.</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/check_jsonl_duplicates.php?bk_key=<?= $_toolsKey ?>" target="_blank"><?= $_toolsBase ?>/admin/check_jsonl_duplicates.php?bk_key=...</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/check_jsonl_duplicates.php?bk_key=<?= $_toolsKey ?>" target="_blank">Lancer scan</a>
                        <button class="tools-btn tools-btn-copy" onclick="toolsCopy('<?= $_toolsBase ?>/admin/check_jsonl_duplicates.php?bk_key=<?= $_toolsKey ?>', this)">Copier</button>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Diagnostic taille BDD <span class="tools-tag">DIAGNOSTIC</span></h4>
                    <p class="desc">Liste tables avec taille MB, lignes, % du total.</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/db_size.php?bk_key=<?= $_toolsKey ?>" target="_blank"><?= $_toolsBase ?>/admin/db_size.php?bk_key=...</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/db_size.php?bk_key=<?= $_toolsKey ?>" target="_blank">Ouvrir</a>
                        <button class="tools-btn tools-btn-copy" onclick="toolsCopy('<?= $_toolsBase ?>/admin/db_size.php?bk_key=<?= $_toolsKey ?>', this)">Copier</button>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Setup BDD complete <span class="tools-tag warn">SCHEMA</span></h4>
                    <p class="desc">Cree BDD + toutes les tables vides selon le schema.</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/setup_bdd.php" target="_blank"><?= $_toolsBase ?>/admin/setup_bdd.php</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/setup_bdd.php" target="_blank">Ouvrir</a>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Setup local + verif tables <span class="tools-tag">SETUP</span></h4>
                    <p class="desc">Verification tables + creation auto + auth super admin.</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/local_setup.php" target="_blank"><?= $_toolsBase ?>/admin/local_setup.php</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/local_setup.php" target="_blank">Ouvrir</a>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Visualisation Logs <span class="tools-tag">LOGS</span></h4>
                    <p class="desc">Logs de visite filtrables (date, IP, action, page).</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/logs.php" target="_blank"><?= $_toolsBase ?>/admin/logs.php</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/logs.php" target="_blank">Ouvrir</a>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Vider le cache <span class="tools-tag warn">CACHE</span></h4>
                    <p class="desc">Cache fichier JSON (tout ou par prefixe).</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/clear_cache.php" target="_blank"><?= $_toolsBase ?>/admin/clear_cache.php?prefix=X</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/clear_cache.php" target="_blank">Tout</a>
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/clear_cache.php?prefix=clubstats" target="_blank">Clubs</a>
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/clear_cache.php?prefix=search" target="_blank">Search</a>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Remote Check API <span class="tools-tag">API</span></h4>
                    <p class="desc">Endpoints JSON : count, users, sessions, query SQL.</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/remote_check.php?bk_key=<?= $_toolsKey ?>&action=count" target="_blank"><?= $_toolsBase ?>/admin/remote_check.php</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/remote_check.php?bk_key=<?= $_toolsKey ?>&action=count" target="_blank">count</a>
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/remote_check.php?bk_key=<?= $_toolsKey ?>&action=users" target="_blank">users</a>
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/remote_check.php?bk_key=<?= $_toolsKey ?>&action=ping" target="_blank">ping</a>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Fix performance INT <span class="tools-tag warn">FIX</span></h4>
                    <p class="desc">Correction des perfs INT (padding dixiemes). ?go pour executer.</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/fix_perf_int.php" target="_blank"><?= $_toolsBase ?>/admin/fix_perf_int.php?go</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/fix_perf_int.php" target="_blank">Dry run</a>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Abonnements <span class="tools-tag ok">PREMIUM</span></h4>
                    <p class="desc">Donner / retirer un acces premium a un utilisateur, verifier un paiement Stripe.</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/subscriptions.php" target="_blank"><?= $_toolsBase ?>/admin/subscriptions.php</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/subscriptions.php" target="_blank">Ouvrir</a>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>Setup abonnements <span class="tools-tag warn">SCHEMA</span></h4>
                    <p class="desc">Cree les tables subscriptions + stripe_events (a lancer 1 fois).</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/setup_subscriptions.php" target="_blank"><?= $_toolsBase ?>/admin/setup_subscriptions.php</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/setup_subscriptions.php" target="_blank">Ouvrir</a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ════════════════════════════════════════════ -->
    <!-- TAB : Chemins fichiers                       -->
    <!-- ════════════════════════════════════════════ -->
    <section class="tab-pane" data-pane="paths">
        <div class="tools-section">
            <h2>&#128193; Chemins des fichiers cles</h2>

            <h4>Configuration</h4>
            <div class="path-row"><span class="lbl">Credentials prod</span> <span class="pth">core/credentials.php</span></div>
            <div class="path-row"><span class="lbl">Credentials local</span> <span class="pth">core/credentials_local.php</span></div>
            <div class="path-row"><span class="lbl">OAuth Google</span> <span class="pth">core/oauth_config.php + oauth_credentials.php</span></div>
            <div class="path-row"><span class="lbl">Connexion BDD</span> <span class="pth">core/db.php</span></div>
            <div class="path-row"><span class="lbl">Helpers URLs</span> <span class="pth">core/paths.php</span></div>
            <div class="path-row"><span class="lbl">Source BDD/File</span> <span class="pth">config/data_source.json</span></div>

            <h4>Donnees / Archives</h4>
            <div class="path-row"><span class="lbl">Archives JSONL</span> <span class="pth">archives/*.jsonl</span></div>
            <div class="path-row"><span class="lbl">Cache JSON</span> <span class="pth">cache/*.json</span></div>
            <div class="path-row"><span class="lbl">Donnees scraping</span> <span class="pth">src/*.php</span></div>
            <div class="path-row"><span class="lbl">Schema BDD</span> <span class="pth">core/dbCheck_athle.php</span></div>

            <h4>Logs &amp; Sessions</h4>
            <div class="path-row"><span class="lbl">Log IP mensuel</span> <span class="pth">logs/ip_track_YYYY-MM.php</span></div>
            <div class="path-row"><span class="lbl">Sessions super admin</span> <span class="pth">logs/.sa_sessions.php</span></div>
            <div class="path-row"><span class="lbl">Tentatives login</span> <span class="pth">logs/.admin_attempts.php</span></div>
            <div class="path-row"><span class="lbl">Limites recherche</span> <span class="pth">logs/.search_limits.php</span></div>
            <div class="path-row"><span class="lbl">Limites pages</span> <span class="pth">logs/.page_limits.php</span></div>

            <h4>Code applicatif</h4>
            <div class="path-row"><span class="lbl">Page principale</span> <span class="pth">index.php</span></div>
            <div class="path-row"><span class="lbl">API REST</span> <span class="pth">api/*.php</span></div>
            <div class="path-row"><span class="lbl">Auth</span> <span class="pth">core/auth.php</span></div>
            <div class="path-row"><span class="lbl">Scraper</span> <span class="pth">Class/AthleteScraper.php</span></div>
        </div>
    </section>

    <!-- ════════════════════════════════════════════ -->
    <!-- TAB : Extraction                             -->
    <!-- ════════════════════════════════════════════ -->
    <section class="tab-pane" data-pane="extract">
        <div class="tools-section">
            <h2>&#128229; Extraire les donnees</h2>
            <p style="color:#8b949e;font-size:12px;margin:0 0 16px">3 methodes pour extraire les donnees.</p>
            <div class="tools-grid">

                <div class="tools-card">
                    <h4>1. Archive Manager <span class="tools-tag ok">RECOMMANDE</span></h4>
                    <p class="desc">Export table -&gt; .jsonl avec CREATE TABLE inclus (portable).</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/db_archive.php?bk_key=<?= $_toolsKey ?>" target="_blank"><?= $_toolsBase ?>/admin/db_archive.php</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/db_archive.php?bk_key=<?= $_toolsKey ?>" target="_blank">Ouvrir</a>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>2. API directe JSON</h4>
                    <p class="desc">Appel HTTP des endpoints API.</p>
                    <a class="url" href="<?= $_toolsBase ?>/api/" target="_blank"><?= $_toolsBase ?>/api/{endpoint}.php</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/api/stats.php?detail=1&top=30" target="_blank">stats</a>
                        <a class="tools-btn" href="<?= $_toolsBase ?>/api/clubs.php" target="_blank">clubs</a>
                        <a class="tools-btn" href="<?= $_toolsBase ?>/api/epreuves.php" target="_blank">epreuves</a>
                        <a class="tools-btn" href="<?= $_toolsBase ?>/api/villes.php" target="_blank">villes</a>
                    </div>
                </div>

                <div class="tools-card">
                    <h4>3. Remote Check SQL</h4>
                    <p class="desc">Execute SELECT en lecture seule via URL.</p>
                    <a class="url" href="<?= $_toolsBase ?>/admin/remote_check.php?bk_key=<?= $_toolsKey ?>&action=query&q=..." target="_blank">action=query&q=...</a>
                    <div class="tools-actions">
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/remote_check.php?bk_key=<?= $_toolsKey ?>&action=query&q=<?= urlencode('SELECT COUNT(*) FROM athletes') ?>" target="_blank">Count athletes</a>
                        <a class="tools-btn" href="<?= $_toolsBase ?>/admin/remote_check.php?bk_key=<?= $_toolsKey ?>&action=count" target="_blank">Count all</a>
                    </div>
                </div>

            </div>
        </div>
    </section>

    <!-- ════════════════════════════════════════════ -->
    <!-- TAB : Routes                                 -->
    <!-- ════════════════════════════════════════════ -->
    <section class="tab-pane" data-pane="routes">
        <div class="tools-section">
            <h2>&#128279; Routes principales du site</h2>
            <div class="tools-grid">
                <?php
                $_routes = [
                    ['', 'Accueil', 'Stats globales'],
                    ['/recherche', 'Recherche', '12 filtres combinables'],
                    ['/athletes', 'Athletes', 'Liste paginee'],
                    ['/clubs', 'Clubs', 'Tous les clubs'],
                    ['/epreuves', 'Epreuves', 'Toutes les disciplines'],
                    ['/villes', 'Villes', 'Stats par ville'],
                    ['/comparer', 'Comparer', 'Athletes/clubs'],
                    ['/tuto', 'Tutoriel', '8 sections'],
                    ['/profil/12345', 'Profil athlete', 'Fiche complete'],
                    ['/login.php', 'Connexion', 'Google + admin'],
                ];
                foreach ($_routes as $_r):
                    [$_p, $_l, $_d] = $_r;
                ?>
                <div class="tools-card">
                    <h4><?= htmlspecialchars($_l) ?></h4>
                    <p class="desc"><?= htmlspecialchars($_d) ?></p>
                    <a class="url" href="<?= $_toolsBase . $_p ?>" target="_blank"><?= htmlspecialchars($_toolsBase . $_p) ?></a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <script>
    function toolsCopy(url, btn) {
        navigator.clipboard.writeText(url).then(() => {
            const orig = btn.textContent;
            btn.textContent = 'Copie !';
            btn.style.background = 'rgba(16,185,129,0.25)';
            btn.style.color = '#6ee7b7';
            setTimeout(() => { btn.textContent = orig; btn.style.background = ''; btn.style.color = ''; }, 1500);
        });
    }
    </script>

    <!-- Section Editer athlete -->
    <section class="tab-pane" data-pane="athlete-edit">
        <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:24px;margin-bottom:20px;">
            <h2 style="color:#f0f6fc;font-size:20px;margin-bottom:8px;font-weight:700;">&#9999;&#65039; Editer un profil athlete</h2>
            <p style="color:#8b949e;font-size:13px;margin-bottom:16px;">
                Recherche par nom, prenom ou ID externe. Modifie nom, sexe, categorie, nationalite, lieu de naissance, visibilite,
                <strong style="color:#a78bfa;">bio personnalisee</strong> (remplace la bio auto-generee) et <strong style="color:#fbbf24;">note admin</strong> (bandeau public sur le profil).
            </p>
            <div style="display:flex;gap:10px;align-items:center;">
                <input type="text" id="aeSearch" placeholder="Nom, prenom ou ID externe..." autocomplete="off" style="flex:1;background:#0d1117;border:1px solid #30363d;border-radius:8px;padding:11px 14px;color:#f0f6fc;font-size:14px;">
                <button id="aeSearchBtn" class="btn-msg" style="background:#6c5ce7;color:#fff;border:none;padding:11px 18px;border-radius:8px;cursor:pointer;font-weight:600;">Rechercher</button>
            </div>
            <div id="aeResults" style="margin-top:14px;"></div>
        </div>

        <div id="aeFormCard" style="display:none;background:#161b22;border:1px solid #30363d;border-radius:12px;padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                <h3 id="aeFormTitle" style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0;">&#128100; Athlete</h3>
                <div style="display:flex;gap:8px;align-items:center;">
                    <a id="aeFormProfile" href="#" target="_blank" style="color:#58a6ff;font-size:13px;text-decoration:none;border:1px solid #30363d;padding:6px 12px;border-radius:6px;">Voir le profil &#8599;</a>
                    <button id="aeFormClose" style="background:transparent;border:none;color:#8b949e;font-size:26px;cursor:pointer;line-height:1;">&times;</button>
                </div>
            </div>

            <div id="aeFormStatus" style="display:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:14px;"></div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Nom complet (affiche)</label>
                    <input type="text" id="ae_nom_complet" class="ae-inp" maxlength="200">
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">ID externe (lecture seule)</label>
                    <input type="text" id="ae_id_ext" class="ae-inp" readonly style="opacity:0.55;">
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Nom 1</label>
                    <input type="text" id="ae_nom_1" class="ae-inp" maxlength="100">
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Nom 2</label>
                    <input type="text" id="ae_nom_2" class="ae-inp" maxlength="100">
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Nom 3</label>
                    <input type="text" id="ae_nom_3" class="ae-inp" maxlength="100">
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Nom 4</label>
                    <input type="text" id="ae_nom_4" class="ae-inp" maxlength="100">
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Sexe</label>
                    <select id="ae_sexe" class="ae-inp">
                        <option value="">-</option>
                        <option value="M">M (Homme)</option>
                        <option value="F">F (Femme)</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Categorie</label>
                    <select id="ae_categorie" class="ae-inp">
                        <option value="">-</option>
                        <option value="EA">EA - Eveil Athle</option>
                        <option value="PO">PO - Poussin</option>
                        <option value="BE">BE - Benjamin</option>
                        <option value="MI">MI - Minime</option>
                        <option value="CA">CA - Cadet</option>
                        <option value="JU">JU - Junior</option>
                        <option value="ES">ES - Espoir</option>
                        <option value="SE">SE - Senior</option>
                        <option value="V1">V1 - Veteran 1</option>
                        <option value="V2">V2 - Veteran 2</option>
                        <option value="V3">V3 - Veteran 3</option>
                        <option value="V4">V4 - Veteran 4</option>
                    </select>
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Nationalite (code 3 lettres)</label>
                    <input type="text" id="ae_nationalite" class="ae-inp" maxlength="3" placeholder="FRA, MAR, ...">
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Lieu de naissance</label>
                    <input type="text" id="ae_lieu_naissance" class="ae-inp" maxlength="100" placeholder="Nom de la ville">
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Taille (cm)</label>
                    <input type="number" id="ae_taille_cm" class="ae-inp" min="0" max="300">
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Poids (kg)</label>
                    <input type="number" id="ae_poids_kg" class="ae-inp" min="0" max="500">
                </div>
                <div style="grid-column:span 2;">
                    <label style="display:flex;align-items:center;gap:10px;color:#f0f6fc;font-size:14px;font-weight:600;cursor:pointer;padding:10px 14px;background:#0d1117;border:1px solid #30363d;border-radius:8px;">
                        <input type="checkbox" id="ae_visible" style="width:18px;height:18px;cursor:pointer;">
                        Profil visible publiquement (si decoche &#8594; bandeau "Profil masque")
                    </label>
                </div>

                <div style="grid-column:span 2;border-top:1px dashed #30363d;padding-top:14px;margin-top:6px;">
                    <label style="display:block;color:#fbbf24;font-size:12px;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:6px;font-weight:700;">&#128221; Note admin (bandeau public sur le profil)</label>
                    <p style="color:#8b949e;font-size:12px;margin:0 0 8px 0;">Visible par tous les visiteurs sous l'entete du profil. Laisser vide = pas de bandeau.</p>
                    <textarea id="ae_admin_note" rows="2" maxlength="5000" placeholder="Ex: Athlete double champion de France 2023, recordman national." class="ae-inp" style="resize:vertical;font-family:inherit;"></textarea>
                </div>

                <div style="grid-column:span 2;">
                    <label style="display:block;color:#a78bfa;font-size:12px;text-transform:uppercase;letter-spacing:1.2px;margin-bottom:6px;font-weight:700;">&#128196; Bio personnalisee (override)</label>
                    <p style="color:#8b949e;font-size:12px;margin:0 0 8px 0;">Si rempli, remplace la bio auto-generee sur le profil. Laisser vide pour utiliser la bio automatique.</p>
                    <textarea id="ae_bio_override" rows="10" maxlength="20000" placeholder="Texte de biographie personnalise..." class="ae-inp" style="resize:vertical;font-family:inherit;line-height:1.6;"></textarea>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;align-items:center;margin-top:18px;padding-top:16px;border-top:1px solid #30363d;">
                <button id="aeFormCancel" class="btn-msg">Annuler</button>
                <button id="aeFormSave" class="btn-msg" style="background:#10b981;color:#fff;border:none;padding:11px 22px;border-radius:8px;cursor:pointer;font-weight:700;">&#128190; Enregistrer</button>
            </div>
        </div>
    </section>

    <style>
    .ae-inp { width:100%; background:#0d1117; border:1px solid #30363d; border-radius:6px; padding:9px 11px; color:#f0f6fc; font-size:14px; box-sizing:border-box; }
    .ae-inp:focus { outline:none; border-color:#6c5ce7; box-shadow:0 0 0 2px rgba(108,92,231,0.18); }
    .ae-result-row { display:flex; align-items:center; gap:12px; padding:10px 14px; background:#0d1117; border:1px solid #30363d; border-radius:8px; margin-bottom:6px; cursor:pointer; transition:all 0.15s; }
    .ae-result-row:hover { border-color:#6c5ce7; background:#161b22; transform:translateX(2px); }
    .ae-result-name { color:#f0f6fc; font-weight:600; font-size:14px; }
    .ae-result-meta { color:#8b949e; font-size:12px; }
    .ae-badge { display:inline-block; padding:2px 8px; border-radius:4px; font-size:11px; font-weight:600; }
    </style>

    <script>
    (function(){
        var dq = function(q){ return document.querySelector(q); };
        var searchInp = dq('#aeSearch');
        var searchBtn = dq('#aeSearchBtn');
        var results = dq('#aeResults');
        var formCard = dq('#aeFormCard');
        var formTitle = dq('#aeFormTitle');
        var formStatus = dq('#aeFormStatus');
        var formProfile = dq('#aeFormProfile');
        if (!searchInp) return;

        var currentIdExt = 0;
        var searchTimer = null;

        function setStatus(msg, type) {
            if (!msg) { formStatus.style.display = 'none'; return; }
            formStatus.style.display = 'block';
            formStatus.textContent = msg;
            if (type === 'ok') {
                formStatus.style.background = 'rgba(16,185,129,0.12)';
                formStatus.style.color = '#6ee7b7';
                formStatus.style.border = '1px solid rgba(16,185,129,0.35)';
            } else if (type === 'err') {
                formStatus.style.background = 'rgba(239,68,68,0.12)';
                formStatus.style.color = '#fca5a5';
                formStatus.style.border = '1px solid rgba(239,68,68,0.35)';
            } else {
                formStatus.style.background = 'rgba(108,92,231,0.12)';
                formStatus.style.color = '#c4b5fd';
                formStatus.style.border = '1px solid rgba(108,92,231,0.35)';
            }
        }

        function runSearch() {
            var q = searchInp.value.trim();
            if (q === '') { results.innerHTML = ''; return; }
            results.innerHTML = '<div style="color:#8b949e;font-size:13px;padding:8px;">Recherche...</div>';

            // Si numerique = recherche directe par ID externe
            if (/^\d+$/.test(q)) {
                loadAthlete(parseInt(q, 10));
                return;
            }
            fetch('../api/search.php?nom=' + encodeURIComponent(q) + '&limit=20&bk_key=bk_s3cr3t_2026_xK9mP', { credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (!d || !d.success || !d.athletes || d.athletes.length === 0) {
                        results.innerHTML = '<div style="color:#8b949e;font-size:13px;padding:8px;">Aucun resultat.</div>';
                        return;
                    }
                    var html = '';
                    d.athletes.slice(0, 20).forEach(function(a){
                        var id = a.athlete_id || 0;
                        var name = (a.nom_complet || ((a.nom_athlete||'') + ' ' + (a.prenom_athlete||''))).trim();
                        var meta = [
                            a.categorie || '',
                            a.sexe ? (a.sexe === 'F' ? 'F' : 'M') : '',
                            a.nationalite || '',
                            a.club || ''
                        ].filter(Boolean).join(' &middot; ');
                        html += '<div class="ae-result-row" data-id="' + id + '">'
                             +    '<div style="flex:1;">'
                             +       '<div class="ae-result-name">' + escapeHtml(name) + '</div>'
                             +       '<div class="ae-result-meta">#' + id + ' &middot; ' + meta + '</div>'
                             +    '</div>'
                             +    '<span class="ae-badge" style="background:#6c5ce720;color:#a78bfa;">Editer &#8594;</span>'
                             + '</div>';
                    });
                    results.innerHTML = html;
                    results.querySelectorAll('.ae-result-row').forEach(function(row){
                        row.addEventListener('click', function(){
                            loadAthlete(parseInt(row.dataset.id, 10));
                        });
                    });
                })
                .catch(function(e){
                    results.innerHTML = '<div style="color:#fca5a5;font-size:13px;padding:8px;">Erreur de recherche.</div>';
                });
        }

        function escapeHtml(s) {
            return String(s||'').replace(/[&<>"']/g, function(c){
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
            });
        }

        function loadAthlete(idExt) {
            if (!idExt || idExt <= 0) return;
            setStatus('Chargement...', 'info');
            formCard.style.display = 'block';
            fetch('../api/admin_athlete.php?id_ext=' + idExt, { credentials: 'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(d){
                    if (!d || !d.success || !d.athlete) {
                        setStatus('Athlete introuvable.', 'err');
                        return;
                    }
                    var a = d.athlete;
                    currentIdExt = a.id_ext;
                    formTitle.innerHTML = '&#128100; ' + escapeHtml(a.nom_complet || (a.nom_1 + ' ' + a.nom_2));
                    formProfile.href = '../index.php?page=profil&id=' + a.id_ext;
                    dq('#ae_id_ext').value = a.id_ext;
                    dq('#ae_nom_complet').value = a.nom_complet || '';
                    dq('#ae_nom_1').value = a.nom_1 || '';
                    dq('#ae_nom_2').value = a.nom_2 || '';
                    dq('#ae_nom_3').value = a.nom_3 || '';
                    dq('#ae_nom_4').value = a.nom_4 || '';
                    dq('#ae_sexe').value = a.sexe || '';
                    dq('#ae_categorie').value = a.categorie || '';
                    dq('#ae_nationalite').value = a.nationalite || '';
                    dq('#ae_lieu_naissance').value = a.lieu_naissance || '';
                    dq('#ae_taille_cm').value = a.taille_cm || '';
                    dq('#ae_poids_kg').value = a.poids_kg || '';
                    dq('#ae_visible').checked = (a.visible === 1);
                    dq('#ae_admin_note').value = a.admin_note || '';
                    dq('#ae_bio_override').value = a.bio_override || '';
                    setStatus('', '');
                    formCard.scrollIntoView({ behavior:'smooth', block:'start' });
                })
                .catch(function(){ setStatus('Erreur de chargement.', 'err'); });
        }

        function saveAthlete() {
            if (!currentIdExt) return;
            var payload = {
                id_ext: currentIdExt,
                nom_complet: dq('#ae_nom_complet').value,
                nom_1: dq('#ae_nom_1').value,
                nom_2: dq('#ae_nom_2').value,
                nom_3: dq('#ae_nom_3').value,
                nom_4: dq('#ae_nom_4').value,
                sexe: dq('#ae_sexe').value,
                categorie: dq('#ae_categorie').value,
                nationalite: dq('#ae_nationalite').value,
                lieu_naissance: dq('#ae_lieu_naissance').value,
                taille_cm: dq('#ae_taille_cm').value,
                poids_kg: dq('#ae_poids_kg').value,
                visible: dq('#ae_visible').checked ? 1 : 0,
                admin_note: dq('#ae_admin_note').value,
                bio_override: dq('#ae_bio_override').value
            };
            setStatus('Enregistrement en cours...', 'info');
            fetch('../api/admin_athlete.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d && d.success) {
                    setStatus('&#10004; Profil enregistre. Cache vide.', 'ok');
                    setTimeout(function(){ setStatus('', ''); }, 4000);
                } else {
                    setStatus('Erreur : ' + (d && d.error ? d.error : 'inconnue'), 'err');
                }
            })
            .catch(function(){ setStatus('Erreur reseau.', 'err'); });
        }

        searchBtn.addEventListener('click', runSearch);
        searchInp.addEventListener('keydown', function(e){
            if (e.key === 'Enter') { e.preventDefault(); runSearch(); }
        });
        searchInp.addEventListener('input', function(){
            clearTimeout(searchTimer);
            var v = searchInp.value.trim();
            if (v.length < 2) { results.innerHTML = ''; return; }
            searchTimer = setTimeout(runSearch, 400);
        });
        dq('#aeFormSave').addEventListener('click', saveAthlete);
        dq('#aeFormCancel').addEventListener('click', function(){ formCard.style.display = 'none'; });
        dq('#aeFormClose').addEventListener('click', function(){ formCard.style.display = 'none'; });
    })();
    </script>

    <!-- Section Style global -->
    <section class="tab-pane" data-pane="style-global">
        <div style="background:#161b22;border:1px solid #30363d;border-radius:12px;padding:24px;margin-bottom:20px;">
            <h2 style="color:#f0f6fc;font-size:20px;margin-bottom:8px;font-weight:700;">&#127912; Style global du site</h2>
            <p style="color:#8b949e;font-size:13px;margin-bottom:8px;">
                Choisis un theme : police, couleurs, arrondis et entetes sont appliques sur tout le site (accueil, profils, listes...).
            </p>
            <div style="background:#0d1117;border:1px solid #f59e0b40;border-left:3px solid #f59e0b;padding:10px 14px;border-radius:8px;margin-bottom:12px;color:#fbbf24;font-size:12px;">
                &#9888;&#65039; Apres avoir choisi un theme, fais <b>Ctrl+F5</b> (hard refresh) sur le site pour voir le changement, sinon le navigateur garde l'ancien HTML en cache.
            </div>
            <div style="display:flex;gap:8px;margin-bottom:14px;">
                <a id="gsPreviewHome" href="../index.php" target="_blank" style="background:#10b981;color:#fff;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">&#128065; Apercu accueil (nouvelle fenetre)</a>
                <a id="gsPreviewProfil" href="#" target="_blank" style="background:#6c5ce7;color:#fff;padding:9px 16px;border-radius:8px;text-decoration:none;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:6px;">&#128100; Apercu profil exemple</a>
            </div>
            <div id="gsStatus" style="display:none;padding:10px 14px;border-radius:8px;font-size:13px;font-weight:600;margin-bottom:14px;"></div>
            <div id="gsGrid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:14px;margin-top:14px;">
                <div style="color:#8b949e;font-size:13px;padding:8px;">Chargement des themes...</div>
            </div>
        </div>

        <!-- Editeur Personnalise -->
        <div id="gsCustomEditor" style="display:none;background:#161b22;border:1px solid #30363d;border-radius:12px;padding:24px;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
                <h3 style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0;">&#127912; Theme personnalise &mdash; configuration detaillee</h3>
                <button id="gsCustomClose" style="background:transparent;border:none;color:#8b949e;font-size:26px;cursor:pointer;line-height:1;">&times;</button>
            </div>
            <p style="color:#8b949e;font-size:13px;margin:0 0 18px 0;">Active le mode personnalise pour modifier la police, les couleurs et les arrondis. Chaque champ a un apercu en temps reel ci-dessous.</p>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;">
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Police du texte (corps)</label>
                    <select id="gsBodyFont" class="ae-inp"></select>
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Police des titres (H1, H2, ...)</label>
                    <select id="gsHeadingFont" class="ae-inp"></select>
                </div>

                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Couleur principale (boutons, titres)</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="color" id="gsPrimary" value="#6c5ce7" style="width:60px;height:40px;border:1px solid #30363d;background:#0d1117;border-radius:6px;cursor:pointer;">
                        <input type="text" id="gsPrimaryHex" class="ae-inp" maxlength="7" placeholder="#6c5ce7" style="flex:1;font-family:monospace;">
                    </div>
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Couleur d'accent (liens, badges)</label>
                    <div style="display:flex;gap:8px;align-items:center;">
                        <input type="color" id="gsAccent" value="#a78bfa" style="width:60px;height:40px;border:1px solid #30363d;background:#0d1117;border-radius:6px;cursor:pointer;">
                        <input type="text" id="gsAccentHex" class="ae-inp" maxlength="7" placeholder="#a78bfa" style="flex:1;font-family:monospace;">
                    </div>
                </div>

                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Arrondi des cartes / boutons <span id="gsRadiusVal" style="color:#a78bfa;">10px</span></label>
                    <input type="range" id="gsRadius" min="0" max="40" value="10" style="width:100%;cursor:pointer;">
                    <div style="display:flex;justify-content:space-between;color:#5a6580;font-size:10px;margin-top:2px;"><span>Plat</span><span>Tres arrondi</span></div>
                </div>
                <div>
                    <label style="display:block;color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px;">Taille du texte <span id="gsBodySizeVal" style="color:#a78bfa;">14px</span></label>
                    <input type="range" id="gsBodySize" min="11" max="22" value="14" style="width:100%;cursor:pointer;">
                    <div style="display:flex;justify-content:space-between;color:#5a6580;font-size:10px;margin-top:2px;"><span>Petit</span><span>Grand</span></div>
                </div>
            </div>

            <!-- Apercu live -->
            <div id="gsCustomPreview" style="margin-top:20px;background:#0d1117;border:1px solid #30363d;border-radius:10px;padding:20px;transition:all .25s;">
                <div id="gsPrevTitle" style="font-size:26px;font-weight:700;color:#6c5ce7;margin-bottom:6px;">Bokonzi Athletisme</div>
                <div id="gsPrevSub" style="color:#8b949e;font-size:12px;margin-bottom:14px;letter-spacing:1px;text-transform:uppercase;">Apercu en temps reel</div>
                <p id="gsPrevBody" style="color:#c9d1d9;font-size:14px;line-height:1.65;margin:0 0 14px;">
                    L'athlete Jimmy Gressier a remporte 3 medailles d'or sur 5000m et 10000m en 2024. Son record personnel sur le 5000m est de 13'15"35, etabli aux championnats d'Europe.
                </p>
                <div style="display:flex;gap:10px;flex-wrap:wrap;">
                    <button id="gsPrevBtn" style="background:#6c5ce7;color:#fff;border:none;padding:10px 20px;border-radius:10px;font-size:13px;font-weight:600;cursor:pointer;">Bouton primary</button>
                    <span id="gsPrevBadge" style="background:#a78bfa20;color:#a78bfa;border:1px solid #a78bfa55;padding:6px 14px;border-radius:10px;font-size:12px;font-weight:600;">Senior</span>
                    <a id="gsPrevLink" href="#" onclick="return false;" style="color:#a78bfa;font-size:13px;font-weight:600;text-decoration:none;align-self:center;">Voir le profil &#8594;</a>
                </div>
            </div>

            <div style="display:flex;gap:10px;justify-content:flex-end;align-items:center;margin-top:18px;padding-top:16px;border-top:1px solid #30363d;">
                <button id="gsCustomCancel" class="btn-msg">Annuler</button>
                <button id="gsCustomSave" class="btn-msg" style="background:#10b981;color:#fff;border:none;padding:11px 22px;border-radius:8px;cursor:pointer;font-weight:700;">&#128190; Appliquer ce style</button>
            </div>
        </div>
    </section>

    <style>
    .gs-card { background:#0d1117; border:2px solid #30363d; border-radius:10px; padding:16px; cursor:pointer; transition:all 0.2s; position:relative; }
    .gs-card:hover { border-color:#6c5ce7; transform:translateY(-2px); box-shadow:0 8px 22px rgba(108,92,231,0.18); }
    .gs-card.active { border-color:#10b981; background:#0d2a1d; box-shadow:0 0 0 3px rgba(16,185,129,0.15); }
    .gs-card .gs-check { position:absolute; top:12px; right:12px; width:22px; height:22px; border-radius:50%; border:2px solid #30363d; background:#0d1117; display:flex; align-items:center; justify-content:center; }
    .gs-card.active .gs-check { background:#10b981; border-color:#10b981; }
    .gs-card.active .gs-check::after { content:'✓'; color:#fff; font-size:14px; font-weight:700; }
    .gs-name { font-size:16px; font-weight:700; margin-bottom:4px; }
    .gs-desc { color:#8b949e; font-size:12px; line-height:1.45; margin-bottom:12px; }
    .gs-preview { background:#161b22; border:1px solid #30363d; border-radius:6px; padding:12px; margin-top:10px; }
    .gs-preview-title { font-size:18px; font-weight:700; margin-bottom:6px; }
    .gs-preview-body { font-size:13px; color:#c9d1d9; line-height:1.55; }
    .gs-palette { display:flex; gap:6px; margin-top:8px; align-items:center; }
    .gs-swatch { width:22px; height:22px; border-radius:50%; border:2px solid #30363d; }
    .gs-pill { display:inline-block; padding:3px 10px; font-size:11px; font-weight:600; color:#fff; }
    </style>

    <script>
    (function(){
        var grid = document.getElementById('gsGrid');
        var status = document.getElementById('gsStatus');
        if (!grid) return;

        function setStatus(msg, type) {
            if (!msg) { status.style.display='none'; return; }
            status.style.display='block';
            status.innerHTML = msg;
            if (type === 'ok') {
                status.style.background='rgba(16,185,129,0.12)';
                status.style.color='#6ee7b7';
                status.style.border='1px solid rgba(16,185,129,0.35)';
            } else if (type === 'err') {
                status.style.background='rgba(239,68,68,0.12)';
                status.style.color='#fca5a5';
                status.style.border='1px solid rgba(239,68,68,0.35)';
            } else {
                status.style.background='rgba(108,92,231,0.12)';
                status.style.color='#c4b5fd';
                status.style.border='1px solid rgba(108,92,231,0.35)';
            }
        }

        function loadFont(url) {
            if (!url) return;
            if (document.querySelector('link[href="' + url + '"]')) return;
            var l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = url;
            document.head.appendChild(l);
        }

        var _fontsCatalog = [];
        var _customCfg = {};

        function render(data) {
            if (!data || !data.themes) { grid.innerHTML = '<div style="color:#fca5a5;">Erreur chargement</div>'; return; }
            var current = data.current || 'default';
            _fontsCatalog = data.fonts || [];
            _customCfg = data.custom || {};
            var html = '';
            data.themes.forEach(function(t){
                loadFont(t.font_google);
                var isActive = (t.id === current);
                html += '<div class="gs-card' + (isActive ? ' active' : '') + '" data-theme="' + t.id + '">'
                     +    '<div class="gs-check"></div>'
                     +    '<div class="gs-name" style="font-family:' + t.heading_family + ';color:' + t.primary + ';">' + escapeHtml(t.nom) + '</div>'
                     +    '<div class="gs-desc">' + escapeHtml(t.description) + '</div>'
                     +    '<div class="gs-palette">'
                     +       '<div class="gs-swatch" style="background:' + t.primary + ';"></div>'
                     +       '<div class="gs-swatch" style="background:' + t.accent + ';"></div>'
                     +       '<span class="gs-pill" style="background:' + t.primary + ';border-radius:' + t.radius + ';">' + t.id.toUpperCase() + '</span>'
                     +    '</div>'
                     +    '<div class="gs-preview" style="border-radius:' + t.radius + ';">'
                     +       '<div class="gs-preview-title" style="font-family:' + t.heading_family + ';color:' + t.primary + ';">Bokonzi Athletisme</div>'
                     +       '<div class="gs-preview-body" style="font-family:' + t.font_family + ';font-size:' + t.body_size + ';">L\'athlete a remporte 3 medailles d\'or sur 100m et 200m en 2024. Records nationaux egales en categorie senior.</div>'
                     +    '</div>'
                     + '</div>';
            });
            // Card "Personnalise"
            var isCustom = (current === 'custom');
            html += '<div class="gs-card gs-card-custom' + (isCustom ? ' active' : '') + '" data-theme="custom" style="border-style:dashed;background:linear-gradient(135deg,#1a1f2e,#0d1117);">'
                 +    '<div class="gs-check"></div>'
                 +    '<div class="gs-name" style="color:#fbbf24;">&#9999;&#65039; Personnalise</div>'
                 +    '<div class="gs-desc">Configure manuellement : police, taille, couleurs, arrondi. Apercu temps reel.</div>'
                 +    '<div class="gs-palette">'
                 +       '<div class="gs-swatch" style="background:#fbbf24;"></div>'
                 +       '<div class="gs-swatch" style="background:#a78bfa;"></div>'
                 +       '<span class="gs-pill" style="background:#fbbf24;color:#000;border-radius:8px;">CUSTOM</span>'
                 +    '</div>'
                 +    '<div class="gs-preview" style="border-radius:8px;">'
                 +       '<div class="gs-preview-title" style="color:#fbbf24;">A toi de jouer</div>'
                 +       '<div class="gs-preview-body">Choisis ta police, tes couleurs et tes arrondis exactement comme tu le souhaites.</div>'
                 +       '<button type="button" class="gs-custom-open-btn" style="margin-top:10px;background:#fbbf24;color:#000;border:none;padding:8px 16px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer;">&#9881;&#65039; Configurer / Editer</button>'
                 +    '</div>'
                 + '</div>';
            grid.innerHTML = html;
            grid.querySelectorAll('.gs-card').forEach(function(card){
                card.addEventListener('click', function(e){
                    var id = card.dataset.theme;
                    if (id === 'custom') {
                        openCustomEditor();
                    } else {
                        saveTheme(id);
                    }
                });
            });
        }

        // --- Editeur Personnalise ---
        var customEditor = document.getElementById('gsCustomEditor');

        function buildFontOptions(selectEl, selectedId) {
            selectEl.innerHTML = '';
            _fontsCatalog.forEach(function(f){
                var opt = document.createElement('option');
                opt.value = f.id;
                opt.textContent = f.label;
                opt.style.fontFamily = f.font_family;
                if (f.id === selectedId) opt.selected = true;
                selectEl.appendChild(opt);
            });
        }

        function openCustomEditor() {
            customEditor.style.display = 'block';
            customEditor.scrollIntoView({ behavior:'smooth', block:'start' });
            var bodyFontId    = _customCfg.body_font || 'inter';
            var headingFontId = _customCfg.heading_font || bodyFontId;
            var primary  = _customCfg.primary || '#6c5ce7';
            var accent   = _customCfg.accent  || '#a78bfa';
            var radius   = (_customCfg.radius !== undefined) ? _customCfg.radius : 10;
            var bodySize = (_customCfg.body_size !== undefined) ? _customCfg.body_size : 14;

            buildFontOptions(document.getElementById('gsBodyFont'), bodyFontId);
            buildFontOptions(document.getElementById('gsHeadingFont'), headingFontId);
            document.getElementById('gsPrimary').value    = primary;
            document.getElementById('gsPrimaryHex').value = primary;
            document.getElementById('gsAccent').value     = accent;
            document.getElementById('gsAccentHex').value  = accent;
            document.getElementById('gsRadius').value     = radius;
            document.getElementById('gsBodySize').value   = bodySize;

            // Precharger les fonts pour preview
            _fontsCatalog.forEach(function(f){ if (f.google) loadFont(f.google); });
            updateCustomPreview();
        }

        function updateCustomPreview() {
            var bodyFontId = document.getElementById('gsBodyFont').value;
            var headFontId = document.getElementById('gsHeadingFont').value;
            var bodyF = _fontsCatalog.find(function(f){ return f.id === bodyFontId; }) || _fontsCatalog[0];
            var headF = _fontsCatalog.find(function(f){ return f.id === headFontId; }) || bodyF;
            if (bodyF && bodyF.google) loadFont(bodyF.google);
            if (headF && headF.google) loadFont(headF.google);

            var pri = document.getElementById('gsPrimaryHex').value || '#6c5ce7';
            var acc = document.getElementById('gsAccentHex').value || '#a78bfa';
            var rad = document.getElementById('gsRadius').value + 'px';
            var bs  = document.getElementById('gsBodySize').value + 'px';
            document.getElementById('gsRadiusVal').textContent = rad;
            document.getElementById('gsBodySizeVal').textContent = bs;

            var prev   = document.getElementById('gsCustomPreview');
            var pTit   = document.getElementById('gsPrevTitle');
            var pBody  = document.getElementById('gsPrevBody');
            var pBtn   = document.getElementById('gsPrevBtn');
            var pBadge = document.getElementById('gsPrevBadge');
            var pLink  = document.getElementById('gsPrevLink');
            prev.style.borderRadius = rad;
            pTit.style.fontFamily   = headF.font_family;
            pTit.style.color        = pri;
            pBody.style.fontFamily  = bodyF.font_family;
            pBody.style.fontSize    = bs;
            pBtn.style.background   = pri;
            pBtn.style.borderRadius = rad;
            pBtn.style.fontFamily   = bodyF.font_family;
            pBadge.style.background = pri + '20';
            pBadge.style.color      = acc;
            pBadge.style.border     = '1px solid ' + pri + '55';
            pBadge.style.borderRadius = rad;
            pLink.style.color       = acc;
        }

        function syncColors() {
            var p = document.getElementById('gsPrimary');
            var ph = document.getElementById('gsPrimaryHex');
            var a = document.getElementById('gsAccent');
            var ah = document.getElementById('gsAccentHex');
            p.addEventListener('input', function(){ ph.value = p.value; updateCustomPreview(); });
            ph.addEventListener('input', function(){
                if (/^#[0-9a-fA-F]{6}$/.test(ph.value)) { p.value = ph.value; updateCustomPreview(); }
            });
            a.addEventListener('input', function(){ ah.value = a.value; updateCustomPreview(); });
            ah.addEventListener('input', function(){
                if (/^#[0-9a-fA-F]{6}$/.test(ah.value)) { a.value = ah.value; updateCustomPreview(); }
            });
        }
        syncColors();

        ['gsBodyFont','gsHeadingFont','gsRadius','gsBodySize'].forEach(function(id){
            var el = document.getElementById(id);
            if (el) el.addEventListener('input', updateCustomPreview);
            if (el) el.addEventListener('change', updateCustomPreview);
        });

        document.getElementById('gsCustomClose').addEventListener('click', function(){ customEditor.style.display='none'; });
        document.getElementById('gsCustomCancel').addEventListener('click', function(){ customEditor.style.display='none'; });
        document.getElementById('gsCustomSave').addEventListener('click', function(){
            var payload = {
                theme: 'custom',
                custom: {
                    body_font:    document.getElementById('gsBodyFont').value,
                    heading_font: document.getElementById('gsHeadingFont').value,
                    primary:      document.getElementById('gsPrimaryHex').value || '#6c5ce7',
                    accent:       document.getElementById('gsAccentHex').value || '#a78bfa',
                    radius:       parseInt(document.getElementById('gsRadius').value, 10),
                    body_size:    parseInt(document.getElementById('gsBodySize').value, 10)
                }
            };
            _customCfg = payload.custom;
            setStatus('Sauvegarde du style personnalise...', 'info');
            fetch('../api/admin_style.php', {
                method:'POST', credentials:'same-origin',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify(payload)
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d && d.success) {
                    var ts = Date.now();
                    var prevHome = document.getElementById('gsPreviewHome');
                    if (prevHome) prevHome.href = '../index.php?_t=' + ts;
                    setStatus('&#10004; Style personnalise applique. Clique <b>Apercu accueil</b> ou Ctrl+F5 sur le site.', 'ok');
                    grid.querySelectorAll('.gs-card').forEach(function(c){
                        c.classList.toggle('active', c.dataset.theme === 'custom');
                    });
                } else {
                    setStatus('Erreur : ' + (d && d.error ? d.error : 'inconnue'), 'err');
                }
            })
            .catch(function(){ setStatus('Erreur reseau.', 'err'); });
        });

        function escapeHtml(s) {
            return String(s||'').replace(/[&<>"']/g, function(c){
                return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];
            });
        }

        function saveTheme(id) {
            setStatus('Application du theme...', 'info');
            fetch('../api/admin_style.php', {
                method:'POST',
                credentials:'same-origin',
                headers:{'Content-Type':'application/json'},
                body: JSON.stringify({ theme: id })
            })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (d && d.success) {
                    var ts = Date.now();
                    var prevHome = document.getElementById('gsPreviewHome');
                    var prevProf = document.getElementById('gsPreviewProfil');
                    if (prevHome) prevHome.href = '../index.php?_t=' + ts;
                    if (prevProf) prevProf.href = '../index.php?page=athletes&_t=' + ts;
                    setStatus('&#10004; Theme <b>' + id + '</b> applique. Clique <b>Apercu accueil</b> ci-dessus, ou Ctrl+F5 sur le site pour voir partout.', 'ok');
                    grid.querySelectorAll('.gs-card').forEach(function(c){
                        c.classList.toggle('active', c.dataset.theme === id);
                    });
                } else {
                    setStatus('Erreur : ' + (d && d.error ? d.error : 'inconnue'), 'err');
                }
            })
            .catch(function(){ setStatus('Erreur reseau.', 'err'); });
        }

        function loadList() {
            fetch('../api/admin_style.php', { credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(render)
                .catch(function(){ grid.innerHTML = '<div style="color:#fca5a5;">Erreur reseau</div>'; });
        }

        // Charge la liste quand on clique sur l'onglet
        var tabBtn = document.querySelector('.tab-main[data-tab="style-global"]');
        if (tabBtn) {
            var loaded = false;
            tabBtn.addEventListener('click', function(){
                if (!loaded) { loaded = true; loadList(); }
            });
        }
    })();
    </script>

</div>

<!-- Modale envoi mail a un user -->
<div id="mailUserBackdrop" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:9998;"></div>
<div id="mailUserModal" style="display:none;position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);background:#161b22;border:1px solid #30363d;border-radius:14px;padding:24px;width:600px;max-width:94vw;max-height:88vh;overflow:auto;z-index:9999;box-shadow:0 24px 60px rgba(0,0,0,0.6);">
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="color:#f0f6fc;font-size:18px;font-weight:700;margin:0;">&#9993; Envoyer un mail</h3>
        <button id="mailUserClose" style="background:transparent;border:none;color:#8b949e;font-size:26px;cursor:pointer;line-height:1;">&times;</button>
    </div>
    <div style="background:#0d1117;border:1px solid #30363d;border-left:3px solid #10b981;border-radius:8px;padding:12px 14px;margin-bottom:14px;">
        <div style="color:#8b949e;font-size:11px;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">Destinataire</div>
        <div id="mailUserTo" style="color:#f0f6fc;font-size:15px;font-weight:600;"></div>
        <div id="mailUserName" style="color:#8b949e;font-size:13px;margin-top:2px;"></div>
    </div>
    <div style="margin-bottom:12px;">
        <label style="display:block;color:#8b949e;font-size:12px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Sujet</label>
        <input type="text" id="mailUserSubject" maxlength="200" placeholder="Sujet du message" style="width:100%;background:#0d1117;border:1px solid #30363d;border-radius:6px;padding:10px 12px;color:#f0f6fc;font-size:14px;box-sizing:border-box;">
    </div>
    <div style="margin-bottom:14px;">
        <label style="display:block;color:#8b949e;font-size:12px;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;">Message</label>
        <textarea id="mailUserBody" rows="10" maxlength="10000" placeholder="Votre message..." style="width:100%;background:#0d1117;border:1px solid #30363d;border-radius:6px;padding:10px 12px;color:#f0f6fc;font-size:14px;line-height:1.5;resize:vertical;box-sizing:border-box;font-family:inherit;"></textarea>
    </div>
    <div style="display:flex;gap:10px;justify-content:flex-end;align-items:center;">
        <span id="mailUserStatus" style="font-size:13px;font-weight:600;margin-right:auto;"></span>
        <button id="mailUserCancel" class="btn-msg">Annuler</button>
        <button id="mailUserSend" class="btn-msg" style="background:#10b981;color:#fff;">Envoyer</button>
    </div>
</div>

<!-- Drawer activite (lateral droit) -->
<div id="actBackdrop" class="act-backdrop"></div>
<aside id="actDrawer" class="act-drawer">
    <div class="act-head">
        <div>
            <h2 id="actTitle">Activite</h2>
            <div id="actEmail" class="muted"></div>
        </div>
        <button id="actClose" class="act-close">&times;</button>
    </div>
    <div id="actBody" class="act-body">
        <p class="muted" style="text-align:center; padding:40px;">Selectionnez un utilisateur</p>
    </div>
</aside>

<script>
// Niveaux athletes — calcul a la demande
(function() {
    var btn = document.getElementById('nivCalcBtn');
    if (!btn) return;
    var status = document.getElementById('nivCalcStatus');
    var results = document.getElementById('nivResults');

    var COLORS = {
        'IA': '#c026d3', 'IB': '#a21caf', 'IE': '#86198f',
        'IR': '#701a75', 'IR1': '#6b1d65', 'IR2': '#581c87', 'IR3': '#4a0e6b', 'IR4': '#3d0855',
        'N1': '#e11d48', 'N2': '#be123c', 'N3': '#9f1239', 'N4': '#881337',
        'R1': '#0891b2', 'R2': '#0e7490', 'R3': '#155e75', 'R4': '#164e63', 'R5': '#0c4a6e', 'R6': '#082f49',
        'D1': '#f97316', 'D2': '#ea580c', 'D3': '#c2410c', 'D4': '#9a3412', 'D5': '#7c2d12', 'D6': '#6b1d0e', 'D7': '#5a1808', 'D8': '#3f1106'
    };
    var KEY_LEVELS = ['IA','IB','N1','N2','N3'];

    function fmt(n){ return Number(n).toLocaleString('fr-FR'); }

    btn.addEventListener('click', function(){
        btn.disabled = true;
        btn.style.opacity = '0.6';
        btn.style.cursor = 'wait';
        status.textContent = 'Calcul en cours… (peut prendre quelques secondes)';
        status.style.color = '#fbbf24';

        fetch('panel.php?action=niveaux').then(function(r){ return r.json(); }).then(function(data){
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.cursor = 'pointer';

            if (!data || !data.success){
                var errMsg = (data && data.error) ? data.error : 'inconnue';
                if (data && data.line) errMsg += ' (ligne ' + data.line + (data.file ? ' / ' + data.file : '') + ')';
                status.textContent = 'Erreur : ' + errMsg;
                status.style.color = '#f85149';
                console.error('Niveaux endpoint error:', data);
                return;
            }
            status.textContent = 'Calcul termine.';
            status.style.color = '#10b981';
            window._lastNivData = data;

            var total = data.total_athletes;
            var avec = data.total_avec_niv;
            var sans = data.sans_niveau;
            var avecBdd = data.total_avec_niv_bdd || 0;
            var avecCalc = data.total_avec_niv_calc || 0;
            var sansFemmes = data.sans_niveau_femmes || 0;
            var sansBareme = data.sans_niveau_records || 0;
            var sansAucune = data.sans_niveau_aucune || 0;

            document.getElementById('nivTotalAth').textContent = fmt(total);
            document.getElementById('nivAvecNiv').textContent = fmt(avec) + ' (' + (total ? Math.round(avec/total*100) : 0) + ' %)';
            document.getElementById('nivAvecNivBreakdown').innerHTML =
                '<span style="color:#34d399;">' + fmt(avecBdd) + ' BDD</span> + ' +
                '<span style="color:#a78bfa;">' + fmt(avecCalc) + ' calcule via bareme</span>';
            document.getElementById('nivSansNiv').textContent = fmt(sans) + ' (' + (total ? Math.round(sans/total*100) : 0) + ' %)';
            var sansBreakdownParts = [];
            if (sansFemmes > 0)  sansBreakdownParts.push('<span style="color:#fb7185;">' + fmt(sansFemmes) + ' F (bareme indispo)</span>');
            if (sansBareme > 0)  sansBreakdownParts.push('<span style="color:#f59e0b;">' + fmt(sansBareme) + ' bareme epreuve manquant</span>');
            if (sansAucune > 0)  sansBreakdownParts.push('<span style="color:#8b949e;">' + fmt(sansAucune) + ' aucune donnee</span>');
            document.getElementById('nivSansNivBreakdown').innerHTML = sansBreakdownParts.join(' &middot; ');

            // Niveaux cles
            var keyHtml = '';
            KEY_LEVELS.forEach(function(code){
                var nb = data.par_niveau[code] || 0;
                var col = COLORS[code] || '#8b949e';
                var pct = total ? (nb/total*100).toFixed(2) : '0.00';
                keyHtml += '<div style="background:#0d1117;border:1px solid '+col+'40;border-left:3px solid '+col+';border-radius:8px;padding:12px;">';
                keyHtml += '<div style="color:'+col+';font-size:11px;font-weight:700;letter-spacing:1px;text-transform:uppercase;margin-bottom:4px;">'+code+'</div>';
                keyHtml += '<div style="color:#f0f6fc;font-size:22px;font-weight:800;line-height:1;">'+fmt(nb)+'</div>';
                keyHtml += '<div class="muted" style="font-size:11px;margin-top:4px;">'+pct+' %</div>';
                keyHtml += '</div>';
            });
            document.getElementById('nivKey').innerHTML = keyHtml;

            // Detail complet — barres horizontales
            var maxNb = 0;
            data.hierarchy.forEach(function(c){ if ((data.par_niveau[c]||0) > maxNb) maxNb = data.par_niveau[c] || 0; });

            var allHtml = '';
            data.hierarchy.forEach(function(code){
                var nb = data.par_niveau[code] || 0;
                var col = COLORS[code] || '#8b949e';
                var pct = total ? (nb/total*100).toFixed(2) : '0.00';
                var barW = maxNb ? (nb/maxNb*100) : 0;
                allHtml += '<div style="display:grid;grid-template-columns:55px 1fr 90px 70px;gap:10px;align-items:center;padding:6px 0;border-bottom:1px solid #21262d;">';
                allHtml += '<div style="color:'+col+';font-weight:700;font-size:13px;letter-spacing:0.5px;">'+code+'</div>';
                allHtml += '<div style="background:#0d1117;border-radius:4px;height:14px;overflow:hidden;"><div style="background:'+col+';height:100%;width:'+barW+'%;transition:width .4s;"></div></div>';
                allHtml += '<div style="color:#f0f6fc;font-weight:700;text-align:right;font-size:13px;">'+fmt(nb)+'</div>';
                allHtml += '<div class="muted" style="text-align:right;font-size:11px;">'+pct+' %</div>';
                allHtml += '</div>';
            });
            // Ligne sans niveau
            if (sans > 0){
                var pctSans = total ? (sans/total*100).toFixed(2) : '0.00';
                var barWSans = maxNb ? (sans/maxNb*100) : 0;
                allHtml += '<div style="display:grid;grid-template-columns:55px 1fr 90px 70px;gap:10px;align-items:center;padding:6px 0;border-top:2px solid #f59e0b30;margin-top:6px;">';
                allHtml += '<div style="color:#f59e0b;font-weight:700;font-size:13px;letter-spacing:0.5px;">N/A</div>';
                allHtml += '<div style="background:#0d1117;border-radius:4px;height:14px;overflow:hidden;"><div style="background:#f59e0b;height:100%;width:'+barWSans+'%;"></div></div>';
                allHtml += '<div style="color:#f0f6fc;font-weight:700;text-align:right;font-size:13px;">'+fmt(sans)+'</div>';
                allHtml += '<div class="muted" style="text-align:right;font-size:11px;">'+pctSans+' %</div>';
                allHtml += '</div>';
            }
            document.getElementById('nivAll').innerHTML = allHtml;
            document.getElementById('nivComputed').textContent = 'Calcule le ' + data.computed_at;

            // Epreuves problematiques (sans bareme) — cliquables pour mapping
            var epProblems = data.epreuves_problemes || [];
            var nbEp = data.nb_epreuves_probl || 0;
            var epBlock = document.getElementById('nivEpProblems');
            if (epProblems.length > 0){
                var epHtml = '<div style="display:grid;grid-template-columns:1fr;gap:6px;">';
                epProblems.forEach(function(item){
                    var safeEp = (item.epreuve||'').replace(/"/g, '&quot;').replace(/[<>]/g,'');
                    epHtml += '<div onclick="_bmOpenMap(\'' + safeEp.replace(/\'/g, "\\\\'") + '\')" style="display:grid;grid-template-columns:1fr 110px 30px;gap:10px;align-items:center;padding:10px 12px;background:#0d1117;border:1px solid #30363d;border-radius:6px;cursor:pointer;transition:all .15s;" onmouseover="this.style.background=\'#1c2129\';this.style.borderColor=\'#6c5ce7\'" onmouseout="this.style.background=\'#0d1117\';this.style.borderColor=\'#30363d\'">';
                    epHtml += '<div style="color:#f0f6fc;font-size:13px;font-weight:600;">' + safeEp + '</div>';
                    epHtml += '<div style="color:#fbbf24;font-size:12px;font-weight:700;text-align:right;">' + fmt(item.nb_athletes) + ' athletes</div>';
                    epHtml += '<div style="color:#6c5ce7;font-size:18px;text-align:center;">&rsaquo;</div>';
                    epHtml += '</div>';
                });
                epHtml += '</div>';
                if (nbEp > epProblems.length){
                    epHtml += '<p class="muted" style="font-size:11px;text-align:right;margin-top:8px;">Top ' + epProblems.length + ' / ' + fmt(nbEp) + ' epreuves au total</p>';
                }
                document.getElementById('nivEpList').innerHTML = epHtml;
                epBlock.style.display = 'block';
            } else {
                epBlock.style.display = 'none';
            }

            results.style.display = 'block';
        }).catch(function(e){
            btn.disabled = false;
            btn.style.opacity = '';
            btn.style.cursor = 'pointer';
            status.textContent = 'Erreur reseau : ' + (e.message || 'inconnue');
            status.style.color = '#f85149';
        });
    });

    // ===== MODAL : associer une epreuve a un bareme =====
    var bmList = [];        // liste des baremes dispo
    var bmMapping = {};     // mapping user actuel
    var bmCurrentEp = '';   // epreuve en cours d'edition

    function _bmCharge(){
        if (bmList.length > 0) return Promise.resolve();
        return Promise.all([
            fetch('panel.php?action=bareme_list').then(function(r){return r.json();}),
            fetch('panel.php?action=bareme_map_get').then(function(r){return r.json();})
        ]).then(function(arr){
            if (arr[0] && arr[0].success) bmList = arr[0].baremes || [];
            if (arr[1] && arr[1].success) bmMapping = arr[1].mapping || {};
        });
    }

    function _bmRender(filter){
        filter = (filter || '').toLowerCase();
        var html = '';
        var matches = bmList.filter(function(b){ return !filter || b.toLowerCase().indexOf(filter) >= 0; });
        if (matches.length === 0){
            html = '<div class="muted" style="padding:14px;text-align:center;font-size:12px;">Aucun bareme correspondant</div>';
        } else {
            matches.forEach(function(b){
                var safe = b.replace(/'/g, "\\'");
                html += '<div onclick="_bmSelect(\'' + safe + '\')" style="padding:10px 14px;border-bottom:1px solid #21262d;cursor:pointer;color:#c9d1d9;font-size:13px;transition:background .1s;" onmouseover="this.style.background=\'#1c2129\'" onmouseout="this.style.background=\'transparent\'">' + b + '</div>';
            });
        }
        document.getElementById('bmMapList').innerHTML = html;
    }

    window._bmOpenMap = function(epName){
        bmCurrentEp = epName;
        document.getElementById('bmMapEpName').textContent = epName;
        document.getElementById('bmMapStatus').textContent = '';
        document.getElementById('bmMapSearch').value = '';
        _bmCharge().then(function(){
            var current = bmMapping[epName] || '';
            var curEl = document.getElementById('bmMapCurrent');
            curEl.innerHTML = current ? ('Mapping actuel : <span style="color:#a78bfa;font-weight:700;">' + current + '</span>') : 'Aucun mapping defini';
            _bmRender('');
            document.getElementById('bmMapBackdrop').style.display = 'block';
            document.getElementById('bmMapModal').style.display = 'block';
        });
    };

    window._bmSelect = function(bremeName){
        var status = document.getElementById('bmMapStatus');
        status.textContent = 'Sauvegarde…';
        status.style.color = '#fbbf24';
        fetch('panel.php?action=bareme_map_set', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({epreuve_bdd: bmCurrentEp, bareme_name: bremeName})
        }).then(function(r){return r.json();}).then(function(d){
            if (d && d.success){
                bmMapping = d.mapping || {};
                status.textContent = '✓ Mapping sauvegarde — relance le calcul pour voir l\'effet';
                status.style.color = '#10b981';
                setTimeout(_bmClose, 1200);
            } else {
                status.textContent = 'Erreur : ' + (d && d.error || 'inconnue');
                status.style.color = '#f85149';
            }
        }).catch(function(){
            status.textContent = 'Erreur reseau';
            status.style.color = '#f85149';
        });
    };

    function _bmClose(){
        document.getElementById('bmMapBackdrop').style.display = 'none';
        document.getElementById('bmMapModal').style.display = 'none';
    }

    document.getElementById('bmMapClose').addEventListener('click', _bmClose);
    document.getElementById('bmMapCancel').addEventListener('click', _bmClose);
    document.getElementById('bmMapBackdrop').addEventListener('click', _bmClose);
    document.getElementById('bmMapClear').addEventListener('click', function(){
        if (!bmCurrentEp) return;
        if (!confirm('Supprimer le mapping pour "' + bmCurrentEp + '" ?')) return;
        _bmSelect('');
    });
    document.getElementById('bmMapSearch').addEventListener('input', function(e){
        _bmRender(e.target.value);
    });

    // ===== Match intelligent (suggestions) =====
    var bmSuggestions = [];

    function _bmSmartOpen(){
        var problemList = (window._lastNivData && window._lastNivData.epreuves_problemes) || [];
        if (problemList.length === 0){
            alert('Lance d\'abord le calcul de repartition.');
            return;
        }
        var status = document.getElementById('bmSmartStatus');
        var listEl = document.getElementById('bmSmartList');
        status.textContent = 'Analyse des noms en cours…';
        status.style.color = '#fbbf24';
        listEl.innerHTML = '';
        document.getElementById('bmSmartBackdrop').style.display = 'block';
        document.getElementById('bmSmartModal').style.display = 'block';

        var epreuves = problemList.map(function(p){ return p.epreuve; });
        fetch('panel.php?action=bareme_suggest', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({epreuves: epreuves})
        }).then(function(r){return r.json();}).then(function(d){
            if (!d || !d.success){
                status.textContent = 'Erreur ' + ((d && d.error) || 'inconnue');
                status.style.color = '#f85149';
                return;
            }
            bmSuggestions = d.suggestions || [];
            var rejected = d.rejected || [];
            _bmSmartRender();

            // Afficher les rejetees en bas (aucun match possible)
            if (rejected.length > 0){
                var rh = '<div style="margin-top:18px;padding:14px;background:#1f1408;border:1px solid #f8514940;border-left:3px solid #f85149;border-radius:8px;">';
                rh += '<div style="color:#f85149;font-size:13px;font-weight:700;margin-bottom:8px;">&#10060; ' + rejected.length + ' epreuve(s) sans correspondance possible (distance/poids/categorie incompatible avec tous les baremes)</div>';
                rh += '<div style="display:flex;flex-wrap:wrap;gap:6px;">';
                rejected.forEach(function(ep){
                    rh += '<span style="background:#0d1117;color:#c9d1d9;padding:4px 10px;border:1px solid #30363d;border-radius:5px;font-size:12px;">' + ep + '</span>';
                });
                rh += '</div></div>';
                document.getElementById('bmSmartList').insertAdjacentHTML('beforeend', rh);
            }

            status.textContent = bmSuggestions.length + ' suggestion(s) trouvee(s)' + (rejected.length > 0 ? ' — ' + rejected.length + ' rejetee(s)' : '');
            status.style.color = '#10b981';
        }).catch(function(){
            status.textContent = 'Erreur reseau';
            status.style.color = '#f85149';
        });
    }

    function _bmSmartRender(){
        var html = '';
        bmSuggestions.forEach(function(s, idx){
            var best = s.best || {name:'', score:0};
            var sex = s.sex || {M:0,F:0};
            var totalAth = (sex.M||0) + (sex.F||0);
            var col = best.score >= 70 ? '#10b981' : (best.score >= 50 ? '#fbbf24' : '#f85149');
            var badge = best.score >= 70 ? 'BON' : (best.score >= 50 ? 'MOYEN' : 'FAIBLE');
            var safeBn = (best.name||'').replace(/'/g, "\\'");
            var safeEp = s.epreuve_bdd.replace(/'/g, "\\'");

            html += '<div data-idx="'+idx+'" data-score="'+best.score+'" style="background:#0d1117;border:1px solid #30363d;border-left:4px solid '+col+';border-radius:8px;padding:12px 14px;margin-bottom:8px;">';
            html += '<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;flex-wrap:wrap;gap:8px;">';
            html += '<div style="color:#f0f6fc;font-size:14px;font-weight:700;">' + s.epreuve_bdd + '</div>';
            html += '<div style="display:flex;gap:6px;align-items:center;">';
            if (sex.M > 0) html += '<span style="background:#3b82f620;color:#60a5fa;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;">&#9794; '+sex.M+'</span>';
            if (sex.F > 0) html += '<span style="background:#ec489920;color:#f472b6;padding:2px 8px;border-radius:5px;font-size:11px;font-weight:700;">&#9792; '+sex.F+'</span>';
            html += '<span style="background:'+col+'20;color:'+col+';padding:2px 8px;border-radius:5px;font-size:11px;font-weight:800;">'+badge+' '+best.score.toFixed(0)+'</span>';
            html += '</div>';
            html += '</div>';
            html += '<div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">';
            html += '<span class="muted" style="font-size:12px;">&rarr; bareme :</span>';
            html += '<strong style="color:'+col+';font-size:13px;">' + (best.name || '(aucun)') + '</strong>';
            html += '<button onclick="_bmSmartApplyOne(' + idx + ')" id="bmApBtn'+idx+'" style="background:'+col+';color:'+(badge==='MOYEN'?'#000':'#fff')+';border:none;padding:5px 12px;border-radius:5px;font-size:11px;font-weight:700;cursor:pointer;margin-left:auto;">Appliquer</button>';
            html += '</div>';
            // Alternatives
            if (s.alternatives && s.alternatives.length > 0){
                html += '<div style="margin-top:8px;font-size:11px;color:#8b949e;">';
                html += 'Alternatives : ';
                s.alternatives.forEach(function(alt, i){
                    var sn = alt.name.replace(/'/g, "\\'");
                    html += (i>0?', ':'') + '<a href="#" onclick="_bmSmartApplyAlt('+idx+',\''+sn+'\');return false;" style="color:#a78bfa;text-decoration:none;">'+alt.name+' ('+alt.score.toFixed(0)+')</a>';
                });
                html += '</div>';
            }
            html += '</div>';
        });
        document.getElementById('bmSmartList').innerHTML = html;
    }

    window._bmSmartApplyOne = function(idx){
        var s = bmSuggestions[idx];
        if (!s || !s.best || !s.best.name) return;
        var btn = document.getElementById('bmApBtn'+idx);
        if (btn) { btn.disabled = true; btn.textContent = '…'; }
        fetch('panel.php?action=bareme_map_set', {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify({epreuve_bdd: s.epreuve_bdd, bareme_name: s.best.name})
        }).then(function(r){return r.json();}).then(function(d){
            if (d && d.success){
                if (btn) { btn.textContent = '✓ OK'; btn.style.opacity='0.6'; }
            } else {
                if (btn) { btn.disabled = false; btn.textContent = 'Appliquer'; }
            }
        });
    };

    window._bmSmartApplyAlt = function(idx, bremeName){
        var s = bmSuggestions[idx];
        if (!s) return;
        s.best = { name: bremeName, score: 100 };
        _bmSmartApplyOne(idx);
    };

    function _bmSmartApplyAll(minScore){
        var status = document.getElementById('bmSmartStatus');
        var toApply = bmSuggestions.filter(function(s){ return s.best && s.best.score >= minScore; });
        if (toApply.length === 0){
            status.textContent = 'Aucune suggestion >= ' + minScore + '%';
            status.style.color = '#fbbf24';
            return;
        }
        status.textContent = 'Application de ' + toApply.length + ' mappings…';
        status.style.color = '#fbbf24';
        var done = 0;
        toApply.forEach(function(s){
            fetch('panel.php?action=bareme_map_set', {
                method: 'POST',
                headers: {'Content-Type':'application/json'},
                body: JSON.stringify({epreuve_bdd: s.epreuve_bdd, bareme_name: s.best.name})
            }).then(function(r){return r.json();}).then(function(){
                done++;
                if (done === toApply.length){
                    status.textContent = '✓ ' + done + ' mappings appliques. Relance le calcul pour voir l\'effet.';
                    status.style.color = '#10b981';
                    // Disable les btn appliquer correspondants
                    bmSuggestions.forEach(function(ss, idx){
                        if (ss.best && ss.best.score >= minScore){
                            var btn = document.getElementById('bmApBtn'+idx);
                            if (btn) { btn.disabled = true; btn.textContent = '✓ OK'; btn.style.opacity='0.6'; }
                        }
                    });
                }
            });
        });
    }

    document.getElementById('bmSmartBtn').addEventListener('click', _bmSmartOpen);
    document.getElementById('bmSmartClose').addEventListener('click', function(){
        document.getElementById('bmSmartBackdrop').style.display = 'none';
        document.getElementById('bmSmartModal').style.display = 'none';
    });
    document.getElementById('bmSmartBackdrop').addEventListener('click', function(){
        document.getElementById('bmSmartBackdrop').style.display = 'none';
        document.getElementById('bmSmartModal').style.display = 'none';
    });
    document.getElementById('bmSmartApplyHigh').addEventListener('click', function(){ _bmSmartApplyAll(70); });
    document.getElementById('bmSmartApplyMid').addEventListener('click', function(){ _bmSmartApplyAll(50); });
})();

// Notifications inscriptions en attente
(function() {
    var block = document.getElementById('notifBlock');
    if (!block) return;
    var KEY = 'bk_panel2_seen_uid_max';
    var maxUid = parseInt(block.dataset.maxUid, 10) || 0;

    function refreshBadge() {
        var visible = block.querySelectorAll('.notif:not(.read)').length;
        var badge = document.getElementById('notifBadge');
        if (badge) badge.textContent = visible;
        if (visible === 0) block.classList.add('all-read');
        else block.classList.remove('all-read');
    }

    // Au chargement : masquer tous les <= seen_max
    var seen = parseInt(localStorage.getItem(KEY), 10) || 0;
    block.querySelectorAll('.notif').forEach(function(n) {
        var uid = parseInt(n.dataset.uid, 10) || 0;
        if (uid <= seen) n.classList.add('read');
    });
    refreshBadge();

    // Bouton "Tout marquer comme lu" → stocke le max courant
    var btn = document.getElementById('notifMarkAll');
    if (btn) btn.addEventListener('click', function() {
        localStorage.setItem(KEY, String(maxUid));
        block.querySelectorAll('.notif').forEach(function(n) { n.classList.add('read'); });
        refreshBadge();
    });
})();

// Onglets principaux
document.querySelectorAll('.tab-main').forEach(function(t) {
    t.addEventListener('click', function() {
        document.querySelectorAll('.tab-main').forEach(function(x) { x.classList.remove('active'); });
        document.querySelectorAll('.tab-pane').forEach(function(x) { x.classList.remove('active'); });
        t.classList.add('active');
        document.querySelector('.tab-pane[data-pane="' + t.dataset.tab + '"]').classList.add('active');
    });
});

// ── Accordéon : rend chaque grosse section repliable (avec mémorisation) ──
(function () {
    var STORE = 'bk_panel_acc';
    var state = {};
    try { state = JSON.parse(localStorage.getItem(STORE) || '{}') || {}; } catch (e) { state = {}; }
    function save() { try { localStorage.setItem(STORE, JSON.stringify(state)); } catch (e) {} }

    function isInteractive(el) {
        return !!(el.closest && el.closest('a, button, input, select, textarea, label'));
    }
    // Le "head" d'une carte = premier enfant qui est (ou contient) un h2/h3
    function findHead(card) {
        var kids = Array.prototype.slice.call(card.children);
        for (var i = 0; i < kids.length; i++) {
            var k = kids[i];
            if (/^H[23]$/.test(k.tagName) || (k.querySelector && k.querySelector('h2, h3'))) return k;
        }
        return null;
    }

    document.querySelectorAll('.tab-pane').forEach(function (pane) {
        var paneKey = pane.getAttribute('data-pane') || 'x';
        var cards = Array.prototype.slice.call(pane.children).filter(function (el) {
            return el.nodeType === 1 && /^(DIV|FORM|SECTION)$/.test(el.tagName) && findHead(el);
        });
        if (!cards.length) return;

        // Barre "tout replier / déplier"
        var bar = document.createElement('div');
        bar.className = 'bk-acc-toolbar';
        bar.innerHTML = '<button type="button" data-act="all">&#9650;&#9660; Tout replier / déplier</button>';
        pane.insertBefore(bar, pane.firstChild);

        cards.forEach(function (card, idx) {
            var head = findHead(card);
            if (!head) return;
            card.classList.add('bk-acc');
            head.classList.add('bk-acc-head');

            var chev = document.createElement('span');
            chev.className = 'bk-chev';
            chev.innerHTML = '&#9660;'; // ▼
            head.insertBefore(chev, head.firstChild);

            // Replié par défaut au 1er chargement (sections trop grandes),
            // puis on respecte le choix mémorisé de l'admin.
            var key = paneKey + ':' + idx;
            var collapsed = (key in state) ? state[key] : true;
            if (collapsed) card.classList.add('collapsed');

            head.addEventListener('click', function (ev) {
                if (isInteractive(ev.target) && ev.target !== chev) return; // ne pas replier en cliquant un bouton/lien
                var collapsed = card.classList.toggle('collapsed');
                state[key] = collapsed; save();
            });
        });

        bar.querySelector('[data-act="all"]').addEventListener('click', function () {
            // Si au moins une carte est ouverte → on replie tout, sinon on déplie tout
            var anyOpen = cards.some(function (c) { return !c.classList.contains('collapsed'); });
            cards.forEach(function (c, idx) {
                c.classList.toggle('collapsed', anyOpen);
                state[paneKey + ':' + idx] = anyOpen;
            });
            save();
        });
    });
})();

// Sous-onglets (Inscription : Google / Email)
document.querySelectorAll('.tab-sub[data-sub]').forEach(function(t) {
    t.addEventListener('click', function() {
        document.querySelectorAll('.tab-sub[data-sub]').forEach(function(x) { x.classList.remove('active'); });
        document.querySelectorAll('.sub-pane[data-sub-pane]').forEach(function(x) { x.classList.remove('active'); });
        t.classList.add('active');
        document.querySelector('.sub-pane[data-sub-pane="' + t.dataset.sub + '"]').classList.add('active');
        var f = document.getElementById('filter');
        if (f && f.value) { f.value = ''; applyFilter(); }
    });
});

// Sous-onglets (Mails recus : Non confirme / Confirme)
document.querySelectorAll('.tab-sub[data-sub-mails]').forEach(function(t) {
    t.addEventListener('click', function() {
        document.querySelectorAll('.tab-sub[data-sub-mails]').forEach(function(x) { x.classList.remove('active'); });
        document.querySelectorAll('.sub-pane[data-sub-mails-pane]').forEach(function(x) { x.classList.remove('active'); });
        t.classList.add('active');
        document.querySelector('.sub-pane[data-sub-mails-pane="' + t.dataset.subMails + '"]').classList.add('active');
    });
});

// Sous-onglets (Signalements : Nouveaux / Lus / Resolus)
document.querySelectorAll('.tab-sub[data-sub-rep]').forEach(function(t) {
    t.addEventListener('click', function() {
        document.querySelectorAll('.tab-sub[data-sub-rep]').forEach(function(x) { x.classList.remove('active'); });
        document.querySelectorAll('.sub-pane[data-sub-rep-pane]').forEach(function(x) { x.classList.remove('active'); });
        t.classList.add('active');
        document.querySelector('.sub-pane[data-sub-rep-pane="' + t.dataset.subRep + '"]').classList.add('active');
    });
});

// Actions sur les signalements
document.querySelectorAll('.btn-msg[data-rep-act]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var act = btn.dataset.repAct;
        var id = btn.dataset.id;
        var athleteId = btn.dataset.athlete;
        var card = btn.closest('.rep-card');

        var confirmMsg = '';
        if (act === 'delete') confirmMsg = 'Supprimer ce signalement ?';
        else if (act === 'hide_athlete') confirmMsg = '⚠ SUPPRESSION DEFINITIVE\n\nCette action va :\n- Supprimer l\'athlete et toutes ses donnees (records, progressions, resultats, medailles)\n- Supprimer son compte user lie si existe\n- Le blacklister pour empecher tout re-scraping\n\nIRREVERSIBLE. Continuer ?';
        else if (act === 'show_athlete') confirmMsg = 'Retirer cet athlete de la blacklist ?\nIl sera re-scrapé au prochain passage du pipeline.';
        if (confirmMsg && !confirm(confirmMsg)) return;

        var param;
        if (act === 'hide_athlete' || act === 'show_athlete') {
            param = act + '=' + encodeURIComponent(athleteId);
        } else {
            param = act + '=' + encodeURIComponent(id);
        }

        btn.disabled = true; btn.textContent = '...';
        fetch('../api/report.php?' + param, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (!d.success) { alert('Erreur'); return; }
                if (act === 'delete') {
                    if (card) card.remove();
                } else {
                    // Pour mark_read / resolve / hide / show → reload pour rafraichir compteurs et placement
                    location.reload();
                }
            })
            .catch(function() {
                btn.disabled = false;
                alert('Erreur reseau');
            });
    });
});

// Actions sur les messages (mark_read, mark_unread, delete, delete_token)
document.querySelectorAll('.btn-msg[data-act]').forEach(function(btn) {
    btn.addEventListener('click', function(e) {
        e.preventDefault();
        var act = btn.dataset.act;
        var id = btn.dataset.id;
        var card = btn.closest('.msg-card');
        var confirmMsg = '';
        if (act === 'delete') confirmMsg = 'Supprimer ce message ?';
        else if (act === 'delete_token') confirmMsg = 'Supprimer cette demande non confirmee ?';
        if (confirmMsg && !confirm(confirmMsg)) return;

        var param = act + '=' + encodeURIComponent(id);
        btn.disabled = true; btn.textContent = '...';
        fetch('../api/contact.php?' + param)
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (act === 'delete' || act === 'delete_token') {
                    if (card) card.remove();
                } else if (act === 'mark_read') {
                    if (card) {
                        card.classList.remove('msg-unread');
                        var tags = card.querySelector('.msg-tags');
                        if (tags) tags.innerHTML = '<span class="msg-tag tag-gray">Lu</span>';
                        btn.outerHTML = '<button class="btn-msg btn-unread" data-act="mark_unread" data-id="' + id + '">Marquer non lu</button>';
                        bkMoveConfirmedCard(card, true);
                        attachMsgActions();
                    }
                } else if (act === 'mark_unread') {
                    if (card) {
                        card.classList.add('msg-unread');
                        var tags = card.querySelector('.msg-tags');
                        if (tags) tags.innerHTML = '<span class="msg-tag tag-blue">Non lu</span>';
                        btn.outerHTML = '<button class="btn-msg btn-read" data-act="mark_read" data-id="' + id + '">Marquer comme lu</button>';
                        bkMoveConfirmedCard(card, false);
                        attachMsgActions();
                    }
                }
            })
            .catch(function() {
                btn.disabled = false;
                alert('Erreur lors de l\'action');
            });
    });
});
// Deplace une carte confirmee vers le groupe Lus / Non lus et rafraichit les compteurs
function bkMoveConfirmedCard(card, toRead) {
    var pane = document.querySelector('[data-sub-mails-pane="confirmed"]');
    if (!pane || !card) return;
    var headClass = toRead ? 'msg-group-read' : 'msg-group-unread';
    var head = pane.querySelector('.' + headClass);
    var list = head ? head.nextElementSibling : null;
    if (list && !list.classList.contains('msg-list')) list = null;
    if (!head || !list) {
        head = document.createElement('div');
        head.className = 'msg-group-head ' + headClass;
        head.innerHTML = toRead
            ? 'Lus <span class="msg-group-cnt">0</span>'
            : '<span class="msg-group-dot"></span>Non lus <span class="msg-group-cnt">0</span>';
        list = document.createElement('div');
        list.className = 'msg-list';
        if (toRead) { pane.appendChild(head); pane.appendChild(list); }
        else { pane.insertBefore(head, pane.firstChild); pane.insertBefore(list, head.nextSibling); }
    }
    list.appendChild(card);
    bkRefreshConfirmedGroups();
}
// Recalcule les compteurs de groupes + badges d'onglets, supprime les groupes vides
function bkRefreshConfirmedGroups() {
    var pane = document.querySelector('[data-sub-mails-pane="confirmed"]');
    if (!pane) return;
    var unreadHead = pane.querySelector('.msg-group-unread');
    var readHead = pane.querySelector('.msg-group-read');
    var nUnread = 0, nRead = 0;
    [unreadHead, readHead].forEach(function(head) {
        if (!head) return;
        var list = head.nextElementSibling;
        var n = (list && list.classList.contains('msg-list')) ? list.querySelectorAll('.msg-card').length : 0;
        var cnt = head.querySelector('.msg-group-cnt');
        if (cnt) cnt.textContent = n;
        if (head.classList.contains('msg-group-unread')) nUnread = n; else nRead = n;
        if (n === 0) { if (list) list.remove(); head.remove(); }
    });
    // Badge sous-onglet "Confirme"
    var subBtn = document.querySelector('[data-sub-mails="confirmed"]');
    if (subBtn) {
        var alert = subBtn.querySelector('.cnt-alert');
        if (nUnread > 0) {
            if (!alert) { alert = document.createElement('span'); alert.className = 'cnt-alert'; subBtn.appendChild(alert); }
            alert.textContent = nUnread + ' non lu';
        } else if (alert) { alert.remove(); }
    }
    // Badge onglet principal "Mails recus"
    var mainBtn = document.querySelector('[data-tab="mails"]');
    if (mainBtn) {
        var mAlert = mainBtn.querySelector('.cnt-alert');
        if (nUnread > 0) {
            if (!mAlert) { mAlert = document.createElement('span'); mAlert.className = 'cnt-alert'; mainBtn.appendChild(mAlert); }
            mAlert.textContent = nUnread;
        } else if (mAlert) { mAlert.remove(); }
    }
}
function attachMsgActions() {
    document.querySelectorAll('.btn-msg[data-act]:not([data-bound])').forEach(function(btn) {
        btn.dataset.bound = '1';
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var ev = new Event('click');
            // Re-trigger via the same handler
            var act = btn.dataset.act, id = btn.dataset.id;
            var card = btn.closest('.msg-card');
            var confirmMsg = '';
            if (act === 'delete') confirmMsg = 'Supprimer ce message ?';
            else if (act === 'delete_token') confirmMsg = 'Supprimer cette demande non confirmee ?';
            if (confirmMsg && !confirm(confirmMsg)) return;
            btn.disabled = true; btn.textContent = '...';
            fetch('../api/contact.php?' + act + '=' + encodeURIComponent(id))
                .then(function(r) { return r.json(); })
                .then(function() {
                    if (act === 'delete' || act === 'delete_token') { if (card) card.remove(); }
                    else if (act === 'mark_read') {
                        card.classList.remove('msg-unread');
                        var tags = card.querySelector('.msg-tags');
                        if (tags) tags.innerHTML = '<span class="msg-tag tag-gray">Lu</span>';
                        btn.outerHTML = '<button class="btn-msg btn-unread" data-act="mark_unread" data-id="' + id + '">Marquer non lu</button>';
                        bkMoveConfirmedCard(card, true);
                        attachMsgActions();
                    } else if (act === 'mark_unread') {
                        card.classList.add('msg-unread');
                        var tags = card.querySelector('.msg-tags');
                        if (tags) tags.innerHTML = '<span class="msg-tag tag-blue">Non lu</span>';
                        btn.outerHTML = '<button class="btn-msg btn-read" data-act="mark_read" data-id="' + id + '">Marquer comme lu</button>';
                        bkMoveConfirmedCard(card, false);
                        attachMsgActions();
                    }
                });
        });
    });
}

// "Voir tout" sur les mails envoyes (developper le body complet)
document.querySelectorAll('.btn-show-full').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var card = btn.closest('.msg-card');
        if (!card) return;
        var bodyEl = card.querySelector('.msg-body');
        if (!bodyEl) return;
        if (bodyEl.dataset.expanded === '1') {
            bodyEl.style.maxHeight = '120px';
            var full = bodyEl.dataset.fullBody || '';
            bodyEl.innerHTML = (full.length > 500 ? full.substring(0, 500).replace(/\n/g, '<br>') + '...' : full.replace(/\n/g, '<br>'));
            bodyEl.dataset.expanded = '0';
            btn.textContent = 'Voir tout';
        } else {
            bodyEl.style.maxHeight = 'none';
            var full = bodyEl.dataset.fullBody || '';
            bodyEl.innerHTML = full.replace(/\n/g, '<br>');
            bodyEl.dataset.expanded = '1';
            btn.textContent = 'Reduire';
        }
    });
});

// Repondre a un signalement — formulaire inline
document.querySelectorAll('.btn-msg[data-rep-reply]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.dataset.repReply;
        var form = document.getElementById('repReplyForm' + id);
        if (!form) return;
        if (form.style.display === 'none') {
            form.style.display = 'block';
            var ta = form.querySelector('.rep-reply-body');
            if (ta) ta.focus();
        } else {
            form.style.display = 'none';
        }
    });
});

document.querySelectorAll('.btn-rep-reply-cancel').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.dataset.cancel;
        var form = document.getElementById('repReplyForm' + id);
        if (form) {
            form.style.display = 'none';
            var b = form.querySelector('.rep-reply-body');
            var s = form.querySelector('.rep-reply-status');
            if (b) b.value = '';
            if (s) { s.textContent = ''; s.style.color = ''; }
        }
    });
});

document.querySelectorAll('.btn-rep-reply-send').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.dataset.send;
        var form = document.getElementById('repReplyForm' + id);
        if (!form) return;
        var subject = form.querySelector('.rep-reply-subject').value.trim();
        var body = form.querySelector('.rep-reply-body').value.trim();
        var status = form.querySelector('.rep-reply-status');
        if (!subject) { status.textContent = 'Sujet requis'; status.style.color = '#f85149'; return; }
        if (!body) { status.textContent = 'Message vide'; status.style.color = '#f85149'; return; }

        btn.disabled = true;
        var oldText = btn.textContent;
        btn.textContent = 'Envoi...';
        status.textContent = '';

        fetch('../api/report.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reply', id_report: parseInt(id, 10), subject: subject, body: body })
        })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
        .then(function(res) {
            btn.disabled = false;
            btn.textContent = oldText;
            if (res.ok && res.data.success) {
                status.textContent = 'Envoye a ' + (res.data.sent_to || '');
                status.style.color = '#10b981';
                // Marque le signalement comme lu visuellement
                var card = btn.closest('.rep-card');
                if (card && card.classList.contains('rep-new')) {
                    card.classList.remove('rep-new');
                    card.classList.add('rep-read');
                    var statusTag = card.querySelector('.rep-tags .msg-tag:last-child');
                    if (statusTag) {
                        statusTag.classList.remove('tag-red');
                        statusTag.classList.add('tag-orange');
                        statusTag.textContent = 'Lu';
                    }
                }
                setTimeout(function() {
                    form.style.display = 'none';
                    form.querySelector('.rep-reply-body').value = '';
                    status.textContent = '';
                }, 2000);
            } else {
                status.textContent = (res.data && res.data.error) ? res.data.error : 'Erreur d\'envoi';
                status.style.color = '#f85149';
            }
        })
        .catch(function() {
            btn.disabled = false;
            btn.textContent = oldText;
            status.textContent = 'Erreur reseau';
            status.style.color = '#f85149';
        });
    });
});

// Modale "Envoyer un mail" depuis la table d'inscriptions
(function() {
    var backdrop = document.getElementById('mailUserBackdrop');
    var modal = document.getElementById('mailUserModal');
    var elTo = document.getElementById('mailUserTo');
    var elName = document.getElementById('mailUserName');
    var elSubj = document.getElementById('mailUserSubject');
    var elBody = document.getElementById('mailUserBody');
    var elStatus = document.getElementById('mailUserStatus');
    var btnSend = document.getElementById('mailUserSend');
    var btnCancel = document.getElementById('mailUserCancel');
    var btnClose = document.getElementById('mailUserClose');
    var currentUid = 0;

    function openModal(uid, email, name) {
        currentUid = parseInt(uid, 10) || 0;
        elTo.textContent = email || '';
        elName.textContent = name && name.trim() !== '' ? name : '';
        elName.style.display = (name && name.trim() !== '') ? 'block' : 'none';
        elSubj.value = '';
        elBody.value = '';
        elStatus.textContent = '';
        backdrop.style.display = 'block';
        modal.style.display = 'block';
        setTimeout(function() { elSubj.focus(); }, 50);
    }

    function closeModal() {
        backdrop.style.display = 'none';
        modal.style.display = 'none';
        currentUid = 0;
    }

    document.querySelectorAll('.btn-mail-user').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            openModal(btn.dataset.uid, btn.dataset.email, btn.dataset.name || '');
        });
    });

    btnCancel.addEventListener('click', closeModal);
    btnClose.addEventListener('click', closeModal);
    backdrop.addEventListener('click', closeModal);

    btnSend.addEventListener('click', function() {
        var subject = elSubj.value.trim();
        var body = elBody.value.trim();
        if (!currentUid) { elStatus.textContent = 'Utilisateur invalide'; elStatus.style.color = '#f85149'; return; }
        if (!subject) { elStatus.textContent = 'Sujet requis'; elStatus.style.color = '#f85149'; elSubj.focus(); return; }
        if (!body) { elStatus.textContent = 'Message vide'; elStatus.style.color = '#f85149'; elBody.focus(); return; }

        btnSend.disabled = true;
        btnSend.textContent = 'Envoi...';
        elStatus.textContent = '';

        fetch('../api/contact.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'send_to_user', id_user: currentUid, subject: subject, body: body })
        })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
        .then(function(res) {
            btnSend.disabled = false;
            btnSend.textContent = 'Envoyer';
            if (res.ok && res.data.success) {
                elStatus.textContent = 'Envoye a ' + (res.data.sent_to || '');
                elStatus.style.color = '#10b981';
                setTimeout(closeModal, 1500);
            } else {
                elStatus.textContent = (res.data && res.data.error) ? res.data.error : 'Erreur d\'envoi';
                elStatus.style.color = '#f85149';
            }
        })
        .catch(function() {
            btnSend.disabled = false;
            btnSend.textContent = 'Envoyer';
            elStatus.textContent = 'Erreur reseau';
            elStatus.style.color = '#f85149';
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && modal.style.display === 'block') closeModal();
    });
})();

// Repondre a un message — formulaire inline
document.querySelectorAll('.btn-msg[data-act-reply]').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.dataset.actReply;
        var form = document.getElementById('replyForm' + id);
        if (!form) return;
        if (form.style.display === 'none') {
            form.style.display = 'block';
            var ta = form.querySelector('.reply-body');
            if (ta) ta.focus();
        } else {
            form.style.display = 'none';
        }
    });
});

document.querySelectorAll('.btn-reply-cancel').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.dataset.cancel;
        var form = document.getElementById('replyForm' + id);
        if (form) {
            form.style.display = 'none';
            var b = form.querySelector('.reply-body');
            var s = form.querySelector('.reply-status');
            if (b) b.value = '';
            if (s) { s.textContent = ''; s.style.color = ''; }
        }
    });
});

document.querySelectorAll('.btn-reply-send').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var id = btn.dataset.send;
        var form = document.getElementById('replyForm' + id);
        if (!form) return;
        var subject = form.querySelector('.reply-subject').value.trim();
        var body = form.querySelector('.reply-body').value.trim();
        var status = form.querySelector('.reply-status');
        if (!subject) { status.textContent = 'Sujet requis'; status.style.color = '#f85149'; return; }
        if (!body) { status.textContent = 'Message vide'; status.style.color = '#f85149'; return; }

        btn.disabled = true;
        var oldText = btn.textContent;
        btn.textContent = 'Envoi...';
        status.textContent = '';

        fetch('../api/contact.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'reply', id_msg: parseInt(id, 10), subject: subject, body: body })
        })
        .then(function(r) { return r.json().then(function(d) { return { ok: r.ok, data: d }; }); })
        .then(function(res) {
            if (res.ok && res.data.success) {
                status.textContent = 'Envoye a ' + (res.data.sent_to || '');
                status.style.color = '#10b981';
                btn.disabled = false;
                btn.textContent = oldText;
                // Marque le message comme lu visuellement
                var card = btn.closest('.msg-card');
                if (card) {
                    card.classList.remove('msg-unread');
                    var tags = card.querySelector('.msg-tags');
                    if (tags) tags.innerHTML = '<span class="msg-tag tag-gray">Lu</span>';
                    var readBtn = card.querySelector('[data-act="mark_read"]');
                    if (readBtn) readBtn.outerHTML = '<button class="btn-msg btn-unread" data-act="mark_unread" data-id="' + id + '">Marquer non lu</button>';
                }
                setTimeout(function() {
                    form.style.display = 'none';
                    form.querySelector('.reply-body').value = '';
                    status.textContent = '';
                }, 2000);
            } else {
                status.textContent = (res.data && res.data.error) ? res.data.error : 'Erreur d\'envoi';
                status.style.color = '#f85149';
                btn.disabled = false;
                btn.textContent = oldText;
            }
        })
        .catch(function() {
            status.textContent = 'Erreur reseau';
            status.style.color = '#f85149';
            btn.disabled = false;
            btn.textContent = oldText;
        });
    });
});

// Filtre live (sur le sous-pane actif uniquement)
function applyFilter() {
    var q = document.getElementById('filter').value.toLowerCase();
    var pane = document.querySelector('.sub-pane.active');
    if (!pane) return;
    pane.querySelectorAll('.userTable tbody tr').forEach(function(r) {
        r.style.display = r.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
    });
}
document.getElementById('filter').addEventListener('input', applyFilter);

// Tri des colonnes (par tableau)
function sortTable(table, key, type, asc) {
    var tbody = table.querySelector('tbody');
    var rows = Array.prototype.slice.call(tbody.querySelectorAll('tr'));
    rows.sort(function(a, b) {
        var va = a.dataset[key] || '';
        var vb = b.dataset[key] || '';
        if (type === 'num') {
            va = parseFloat(va) || 0;
            vb = parseFloat(vb) || 0;
            return asc ? va - vb : vb - va;
        }
        return asc ? va.localeCompare(vb) : vb.localeCompare(va);
    });
    rows.forEach(function(r, i) {
        tbody.appendChild(r);
        var num = r.querySelector('.rownum');
        if (num) num.textContent = i + 1;
    });
}

document.querySelectorAll('.userTable th.sortable').forEach(function(th) {
    th.addEventListener('click', function() {
        var table = th.closest('table');
        var key = th.dataset.sort;
        var type = th.dataset.type || 'text';

        // Toggle direction si meme colonne, sinon descendant par defaut
        var isActive = th.classList.contains('active');
        var asc = isActive ? !(th.dataset.asc === 'true') : false;

        // Reset tous les ths du tableau
        table.querySelectorAll('th.sortable').forEach(function(x) {
            x.classList.remove('active');
            x.dataset.asc = '';
            var arr = x.querySelector('.arr');
            if (arr) arr.textContent = '';
        });

        th.classList.add('active');
        th.dataset.asc = asc ? 'true' : 'false';
        var arr = th.querySelector('.arr');
        if (arr) arr.textContent = asc ? '▲' : '▼';

        sortTable(table, key, type, asc);
    });
});

// Drawer activite
var drawer = document.getElementById('actDrawer');
var backdrop = document.getElementById('actBackdrop');
var bodyEl = document.getElementById('actBody');
var emailEl = document.getElementById('actEmail');

function openDrawer() { drawer.classList.add('open'); backdrop.classList.add('open'); }
function closeDrawer() { drawer.classList.remove('open'); backdrop.classList.remove('open'); }
document.getElementById('actClose').onclick = closeDrawer;
backdrop.onclick = closeDrawer;

function esc(s) {
    if (s == null) return '';
    return String(s).replace(/[&<>"']/g, function(c) {
        return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
    });
}

// Construit une URL absolue propre (decodee) a partir de la valeur stockee en BDD
function cleanUrl(raw) {
    if (!raw) return '';
    var u = String(raw).trim();
    var base = 'https://bokonzi.com';
    if (/^https?:\/\//i.test(u)) {
        // deja absolue
    } else if (u.charAt(0) === '/') {
        u = base + u;
    } else {
        u = base + '/' + u;
    }
    // Decodage propre pour l'affichage (sans casser l'URL)
    try { return decodeURI(u); } catch (e) { return u; }
}

// Genere un libelle lisible depuis l'URL (page=X&...)
var PAGE_LABELS = {
    accueil: 'Accueil',
    athletes: 'Athletes',
    recherche: 'Recherche',
    profil: 'Profil athlete',
    clubs: 'Clubs',
    epreuves: 'Epreuves',
    villes: 'Villes',
    comparer: 'Comparateur',
    tuto: 'Tutoriel',
    espace: 'Mon Espace',
    classement: 'Classement',
    performances: 'Performances'
};
function prettyPage(raw, athletesMap) {
    if (!raw) return '(inconnu)';
    var u = String(raw);
    var qIdx = u.indexOf('?');
    var path = qIdx === -1 ? u : u.substring(0, qIdx);
    var qs = qIdx === -1 ? '' : u.substring(qIdx + 1);
    var params = {};
    qs.split('&').forEach(function(kv) {
        if (!kv) return;
        var eq = kv.indexOf('=');
        var k = eq === -1 ? kv : kv.substring(0, eq);
        var v = eq === -1 ? '' : kv.substring(eq + 1);
        try { params[decodeURIComponent(k)] = decodeURIComponent(v.replace(/\+/g, ' ')); }
        catch (e) { params[k] = v; }
    });

    // Page profil → resoudre nom + prenom de l'athlete
    var isProfil = (params.page === 'profil') || /\/pages\/profil\.php/i.test(path);
    if (isProfil && params.id) {
        var nom = athletesMap && athletesMap[params.id];
        if (nom) return nom;
        return 'Profil #' + params.id;
    }

    // Pages BK index.php?page=...
    if (params.page) {
        var label = PAGE_LABELS[params.page] || params.page;
        if (params.page === 'recherche') {
            var bits = [];
            if (params.club) bits.push('club: ' + params.club);
            if (params.nom) bits.push('nom: ' + params.nom);
            if (params.epreuve) bits.push('epreuve: ' + params.epreuve);
            if (params.ville) bits.push('ville: ' + params.ville);
            if (bits.length) label += ' — ' + bits.join(', ');
        } else if (params.page === 'villes' && params.open) label += ' — ' + params.open;
        else if (params.page === 'comparer') {
            if (params.ids) label += ' (athletes: ' + params.ids + ')';
            else if (params.clubs) label += ' (clubs: ' + params.clubs + ')';
        }
        return label;
    }

    // Fichiers .php sans param page
    var lastSeg = path.split('/').filter(Boolean).pop() || path;
    return lastSeg.replace(/\.php$/i, '');
}

document.querySelectorAll('.btn-act').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var uid = btn.dataset.uid;
        var email = btn.dataset.email;
        emailEl.textContent = email + ' (uid ' + uid + ')';
        bodyEl.innerHTML = '<p class="muted" style="text-align:center;padding:40px;">Chargement...</p>';
        openDrawer();

        fetch('panel.php?action=activity&uid=' + encodeURIComponent(uid))
            .then(function(r) { return r.json(); })
            .then(function(d) {
                if (d.error) {
                    bodyEl.innerHTML = '<p style="color:#f85149;padding:20px;">' + esc(d.error) + '</p>';
                    return;
                }
                var s = d.stats || {};
                var html = '';

                // KPIs
                html += '<div class="act-kpis">';
                html += '<div class="act-kpi"><div class="v">' + (s.total || 0) + '</div><div class="l">Actions</div></div>';
                html += '<div class="act-kpi"><div class="v">' + (s.nb_days || 0) + '</div><div class="l">Jours actifs</div></div>';
                html += '<div class="act-kpi"><div class="v">' + (s.nb_ips || 0) + '</div><div class="l">IPs</div></div>';
                html += '</div>';

                if (s.first_seen) {
                    html += '<div class="muted" style="margin:10px 0;font-size:12px;">';
                    html += 'Premiere : ' + esc(s.first_seen) + ' &nbsp;·&nbsp; Derniere : ' + esc(s.last_seen);
                    html += '</div>';
                }

                // Repartition par action
                if (d.by_action && d.by_action.length) {
                    html += '<h3 class="act-h3">Repartition par action</h3>';
                    html += '<div class="act-actions">';
                    d.by_action.forEach(function(a) {
                        html += '<span class="act-tag">' + esc(a.action) + ' <b>' + a.n + '</b></span>';
                    });
                    html += '</div>';
                }

                // Logs detail
                html += '<h3 class="act-h3">200 dernieres actions</h3>';
                if (!d.logs || !d.logs.length) {
                    html += '<p class="muted">Aucun log pour cet utilisateur.</p>';
                } else {
                    html += '<table class="act-table"><thead><tr><th>Date</th><th>Action</th><th>Page / Detail</th><th>IP</th></tr></thead><tbody>';
                    var athletesMap = d.athletes || {};
                    d.logs.forEach(function(l) {
                        var det = l.detail || l.target || '';
                        var raw = l.page || '';
                        var url = cleanUrl(raw);
                        var label = prettyPage(raw, athletesMap);
                        html += '<tr>';
                        html += '<td class="muted">' + esc(l.ts) + '</td>';
                        html += '<td><span class="act-act">' + esc(l.action) + '</span></td>';
                        html += '<td>';
                        if (url) {
                            html += '<a class="act-link" href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(label) + '</a>';
                            html += '<div class="act-url">' + esc(url) + '</div>';
                        } else {
                            html += '<span class="muted">(aucune page)</span>';
                        }
                        if (det) html += '<div class="muted" style="font-size:11px;margin-top:2px;">' + esc(det) + '</div>';
                        html += '</td>';
                        html += '<td class="muted">' + esc(l.ip) + '</td>';
                        html += '</tr>';
                    });
                    html += '</tbody></table>';
                }

                bodyEl.innerHTML = html;
            })
            .catch(function(e) {
                bodyEl.innerHTML = '<p style="color:#f85149;padding:20px;">Erreur : ' + esc(e.message) + '</p>';
            });
    });
});

// === Suivi navigation : depliage de la navigation detaillee par utilisateur ===
(function() {
    var list = document.getElementById('navList');
    if (!list) return;

    var filter = document.getElementById('navFilter');
    if (filter) {
        filter.addEventListener('input', function() {
            var q = filter.value.trim().toLowerCase();
            list.querySelectorAll('.nav-user').forEach(function(el) {
                el.style.display = (!q || (el.dataset.email || '').indexOf(q) !== -1) ? '' : 'none';
            });
        });
    }

    function renderUserNav(d) {
        var s = d.stats || {};
        var html = '<div class="nav-stats">';
        html += '<span><b>' + (s.total || 0) + '</b> actions</span>';
        html += '<span><b>' + (s.nb_pages || 0) + '</b> pages vues</span>';
        html += '<span><b>' + (s.nb_days || 0) + '</b> jours actifs</span>';
        html += '<span><b>' + (s.nb_ips || 0) + '</b> IP</span>';
        if (s.first_seen) html += '<span class="muted">du ' + esc(s.first_seen) + ' au ' + esc(s.last_seen) + '</span>';
        html += '</div>';

        if (!d.logs || !d.logs.length) {
            return html + '<p class="muted" style="padding:10px 0;">Aucune navigation enregistree pour cet utilisateur.</p>';
        }

        var athletesMap = d.athletes || {};
        var groups = {}, order = [];
        d.logs.forEach(function(l) {
            var day = String(l.ts || '').slice(0, 10) || '(date inconnue)';
            if (!groups[day]) { groups[day] = []; order.push(day); }
            groups[day].push(l);
        });

        order.forEach(function(day) {
            var rows = groups[day];
            html += '<div class="nav-day">' + esc(day) + ' <span class="muted" style="font-weight:400;">— ' + rows.length + ' action' + (rows.length > 1 ? 's' : '') + '</span></div>';
            html += '<table class="act-table"><thead><tr><th>Heure</th><th>Action</th><th>Page / Detail</th><th>Duree</th><th>IP</th></tr></thead><tbody>';
            rows.forEach(function(l) {
                var raw = l.page || '';
                var url = cleanUrl(raw);
                var label = prettyPage(raw, athletesMap);
                var det = l.detail || l.target || '';
                var dur = l.duration_ms ? (Math.round(l.duration_ms / 1000) + ' s') : '';
                html += '<tr>';
                html += '<td class="muted">' + esc(String(l.ts || '').slice(11, 19)) + '</td>';
                html += '<td><span class="act-act">' + esc(l.action || '') + '</span></td>';
                html += '<td>';
                if (url) {
                    html += '<a class="act-link" href="' + esc(url) + '" target="_blank" rel="noopener">' + esc(label) + '</a>';
                    html += '<div class="act-url">' + esc(url) + '</div>';
                } else {
                    html += '<span class="muted">(aucune page)</span>';
                }
                if (det) html += '<div class="muted" style="font-size:11px;margin-top:2px;">' + esc(det) + '</div>';
                html += '</td>';
                html += '<td class="muted">' + esc(dur) + '</td>';
                html += '<td class="muted">' + esc(l.ip || '') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
        });
        return html;
    }

    list.querySelectorAll('.nav-user-head').forEach(function(head) {
        head.addEventListener('click', function() {
            var wrap = head.parentElement;
            var body = wrap.querySelector('.nav-user-body');
            var open = wrap.classList.toggle('open');
            if (!open || body.dataset.loaded === '1') return;
            body.dataset.loaded = '1';
            body.innerHTML = '<p class="muted" style="padding:14px 0;">Chargement de la navigation...</p>';
            fetch('panel.php?action=usernav&uid=' + encodeURIComponent(head.dataset.uid))
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    if (d.error) { body.innerHTML = '<p style="color:#f85149;padding:14px 0;">' + esc(d.error) + '</p>'; return; }
                    body.innerHTML = renderUserNav(d);
                })
                .catch(function(e) {
                    body.dataset.loaded = '0';
                    body.innerHTML = '<p style="color:#f85149;padding:14px 0;">Erreur : ' + esc(e.message) + '</p>';
                });
        });
    });
})();

// === Selection visuelle du layout (radio) avec couleur d'accent ===
(function() {
    var opts = document.querySelectorAll('.af-layout-opt');
    if (!opts.length) return;
    opts.forEach(function(opt) {
        opt.addEventListener('click', function(e) {
            var radio = opt.querySelector('input[type="radio"]');
            if (!radio) return;
            radio.checked = true;

            // Reset les autres
            opts.forEach(function(o) {
                o.classList.remove('af-layout-active');
                o.style.background = '#0d1117';
                o.style.borderColor = '#30363d';
                o.style.boxShadow = 'none';
                o.style.transform = '';
                // Retirer le check existant
                var existingCheck = o.querySelector('.af-check-mark');
                if (existingCheck) existingCheck.remove();
            });

            // Active le clique avec son accent specifique
            var accent = opt.dataset.accent || '#6c5ce7';
            var gradient = opt.dataset.gradient || 'linear-gradient(135deg,#1e1b4b 0%,#0f0c2e 100%)';
            opt.classList.add('af-layout-active');
            opt.style.background = gradient;
            opt.style.borderColor = accent;
            opt.style.boxShadow = '0 8px 24px ' + accent + '30, inset 0 0 30px ' + accent + '10';
            opt.style.transform = 'translateY(-2px)';

            // Ajouter le check
            var check = document.createElement('div');
            check.className = 'af-check-mark';
            check.style.cssText = 'position:absolute;bottom:8px;right:8px;width:22px;height:22px;background:' + accent + ';border-radius:50%;display:flex;align-items:center;justify-content:center;color:#000;font-size:14px;font-weight:900;box-shadow:0 0 12px ' + accent + ';animation:afCheckPulse 1.5s ease-in-out infinite;';
            check.innerHTML = '&#10003;';
            opt.appendChild(check);
        });
    });
})();

// === Selection visuelle des niveaux (checkboxes) avec mise a jour des couleurs ===
(function() {
    var labels = document.querySelectorAll('label.ps-option, label[style*="cursor:pointer"]');
    document.querySelectorAll('input[name="niveaux[]"]').forEach(function(input) {
        var label = input.closest('label');
        if (!label) return;
        input.addEventListener('change', function() {
            var lvl = input.value;
            var col = lvl[0] === 'I' ? '#c026d3' : (lvl[0] === 'N' ? '#e11d48' : (lvl[0] === 'R' ? '#0891b2' : '#f97316'));
            if (input.checked) {
                label.style.background = col + '30';
                label.style.borderColor = col;
                label.style.color = '#fff';
            } else {
                label.style.background = '#0d1117';
                label.style.borderColor = '#30363d';
                label.style.color = '#8b949e';
            }
        });
    });
})();

// === Schema visuel : mise a jour live quand on change les inputs ===
(function() {
    var nbH = document.getElementById('afNbH');
    var nbF = document.getElementById('afNbF');
    var grid = document.getElementById('schemaGrid');
    var spanH = document.getElementById('schemaNbH');
    var spanF = document.getElementById('schemaNbF');
    var spanT = document.getElementById('schemaNbT');
    var spanYr = document.getElementById('schemaYr');
    var spanNiv = document.getElementById('schemaNiv');
    var afYear = document.getElementById('afAnnee');
    if (!grid) return;

    function renderSchema() {
        var h = parseInt(nbH.value) || 0;
        var f = parseInt(nbF.value) || 0;
        var total = h + f;
        spanH.textContent = h;
        spanF.textContent = f;
        spanT.textContent = total;
        // Liste alternee H/F (comme sur la page reelle) — on cap a 100 pour le schema
        var maxShow = Math.min(total, 100);
        grid.innerHTML = '';
        var arr = [];
        var hRem = h, fRem = f;
        // Alterner H/F tant que possible
        while (hRem > 0 || fRem > 0) {
            if (hRem > 0) { arr.push('M'); hRem--; }
            if (fRem > 0) { arr.push('F'); fRem--; }
        }
        for (var i = 0; i < Math.min(arr.length, 100); i++) {
            var c = document.createElement('div');
            c.style.aspectRatio = '1';
            c.style.borderRadius = '3px';
            c.style.background = arr[i] === 'M' ? 'linear-gradient(135deg,#3b82f6,#2563eb)' : 'linear-gradient(135deg,#ec4899,#db2777)';
            c.style.opacity = '0.8';
            c.title = arr[i] === 'M' ? 'Homme' : 'Femme';
            grid.appendChild(c);
        }
        if (total > 100) {
            var more = document.createElement('div');
            more.style.gridColumn = 'span 10';
            more.style.textAlign = 'center';
            more.style.color = '#8b949e';
            more.style.fontSize = '10px';
            more.style.padding = '4px';
            more.textContent = '+ ' + (total - 100) + ' non visualises';
            grid.appendChild(more);
        }
    }
    function updateNivLabel() {
        var checked = document.querySelectorAll('input[name="niveaux[]"]:checked');
        var labels = Array.prototype.map.call(checked, function(c) { return c.value; });
        if (spanNiv) spanNiv.textContent = labels.join(' & ') || '—';
    }
    function updateYr() {
        var afAll = document.getElementById('afAnneeAll');
        if (spanYr) {
            if (afAll && afAll.checked) spanYr.textContent = 'TOUTES ANNEES';
            else if (afYear) spanYr.textContent = afYear.value;
        }
    }
    nbH.addEventListener('input', renderSchema);
    nbF.addEventListener('input', renderSchema);
    if (afYear) afYear.addEventListener('change', updateYr);
    var afAllChk = document.getElementById('afAnneeAll');
    if (afAllChk) afAllChk.addEventListener('change', updateYr);
    document.querySelectorAll('input[name="niveaux[]"]').forEach(function(c) {
        c.addEventListener('change', updateNivLabel);
    });
    renderSchema();
})();

// === Submit form avec loader minimal (juste le temps de la sauvegarde + redirect) ===
(function() {
    var form = document.getElementById('athletesFilter');
    if (!form) return;
    var overlay = document.getElementById('afLoaderOverlay');

    form.addEventListener('submit', function() {
        overlay.style.display = 'flex';
        // Le vrai chargement (apercu) se fait apres le reload via AJAX
    });
})();

// === Apercu AJAX temps reel : appelle api/liste.php (cree le cache + retourne data) ===
(function() {
    var wrap = document.getElementById('afPreviewWrap');
    if (!wrap) return;

    var niveaux = wrap.dataset.niv;
    var annee = wrap.dataset.annee;
    var epreuves = wrap.dataset.epreuves;
    var clubCible = wrap.dataset.club || '';
    var strictMode = wrap.dataset.strict === '1';
    var nbH = parseInt(wrap.dataset.nbh, 10) || 0;
    var nbF = parseInt(wrap.dataset.nbf, 10) || 0;
    var nivList = niveaux.split(',').map(function(s){return s.trim();}).filter(Boolean);
    var perLvlH = nivList.length > 0 ? Math.ceil(nbH / nivList.length) : nbH;
    var perLvlF = nivList.length > 0 ? Math.ceil(nbF / nivList.length) : nbF;

    function buildUrl(sx, lim, niveau) {
        // Annee exacte (= au lieu de >=) — admin veut strictement cette annee la
        var url = '/api/liste.php?limit=' + lim + '&ordre=medailles&niveau=' + encodeURIComponent(niveau)
             + '&annee_exact=' + annee
             + '&epreuve=' + encodeURIComponent(epreuves)
             + '&sexe=' + sx;
        if (strictMode) url += '&niveau_strict_ep=1';
        if (clubCible) url += '&club=' + encodeURIComponent(clubCible);
        return url;
    }

    var meta = document.getElementById('afPreviewMeta');
    var totalEl = document.getElementById('afTotal');
    var logContent = document.getElementById('afLogContent');
    var globalStart = Date.now();
    var totalCount = { M: 0, F: 0 };

    // Journal d'activite : chaque entree = ligne timestampee
    function logMsg(msg, color) {
        if (!logContent) return;
        var t = ((Date.now() - globalStart) / 1000).toFixed(2);
        var line = document.createElement('div');
        line.style.color = color || '#7d8590';
        line.innerHTML = '<span style="color:#5a6580;">[' + t.padStart(6, ' ') + 's]</span> ' + msg;
        logContent.appendChild(line);
        // Auto-scroll
        var p = logContent.parentElement;
        if (p) p.scrollTop = p.scrollHeight;
    }
    function logHeartbeat(msg) {
        // Met a jour la derniere ligne en cours (sans en creer une nouvelle)
        if (!logContent) return;
        var lastLine = logContent.lastElementChild;
        if (lastLine && lastLine.dataset.heartbeat) {
            var t = ((Date.now() - globalStart) / 1000).toFixed(2);
            lastLine.innerHTML = '<span style="color:#5a6580;">[' + t.padStart(6, ' ') + 's]</span> <span style="color:#a78bfa;">⟳</span> ' + msg;
        } else {
            var line = document.createElement('div');
            line.dataset.heartbeat = '1';
            line.style.color = '#a78bfa';
            var t = ((Date.now() - globalStart) / 1000).toFixed(2);
            line.innerHTML = '<span style="color:#5a6580;">[' + t.padStart(6, ' ') + 's]</span> <span style="color:#a78bfa;">⟳</span> ' + msg;
            logContent.appendChild(line);
            var p = logContent.parentElement;
            if (p) p.scrollTop = p.scrollHeight;
        }
    }
    function clearHeartbeat() {
        if (!logContent) return;
        var lastLine = logContent.lastElementChild;
        if (lastLine && lastLine.dataset.heartbeat) {
            lastLine.removeAttribute('data-heartbeat');
        }
    }

    function setStatus(sx, txt, color) {
        var el = document.querySelector('.af-col-status[data-col="' + sx + '"]');
        if (el) {
            el.textContent = txt;
            if (color) el.style.color = color;
        }
    }
    function setBar(sx, pct) {
        var bar = document.querySelector('.af-col-progress[data-col="' + sx + '"] .af-bar');
        if (bar) bar.style.width = pct + '%';
    }
    function setBody(sx, html) {
        var b = document.querySelector('.af-col-body[data-col="' + sx + '"]');
        if (b) b.innerHTML = html;
    }
    function esc(s) { return String(s).replace(/[&<>"']/g, function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];}); }

    function renderList(athletes) {
        if (!athletes || athletes.length === 0) {
            return '<p style="color:#f59e0b;font-size:12px;padding:14px;">Aucun athlete avec ces filtres.</p>';
        }
        var html = '<div>';
        athletes.slice(0, 30).forEach(function(a, i) {
            html += '<div style="display:flex;align-items:center;gap:8px;padding:6px 8px;border-bottom:1px solid #21262d;font-size:12px;">'
                  + '<span style="color:#8b949e;width:24px;">#' + (i + 1) + '</span>'
                  + '<a href="/?page=profil&id=' + (a.athlete_id|0) + '" target="_blank" style="color:#e6edf3;text-decoration:none;flex:1;font-weight:600;">' + esc(a.nom_complet) + '</a>'
                  + '<span style="color:#8b949e;font-size:10px;">' + esc(a.categorie || '') + '</span>'
                  + (a.latest_year ? '<span style="color:#34d399;font-size:10px;font-weight:700;">' + a.latest_year + '</span>' : '')
                  + '</div>';
        });
        html += '</div>';
        if (athletes.length > 30) {
            html += '<p style="color:#8b949e;font-size:11px;text-align:center;margin-top:8px;">+ ' + (athletes.length - 30) + ' autres</p>';
        }
        return html;
    }

    var countMEl = document.getElementById('afCountM');
    var countFEl = document.getElementById('afCountF');
    var countTotalEl = document.getElementById('afCountTotal');
    var timeMEl = document.getElementById('afTimeM');
    var timeFEl = document.getElementById('afTimeF');
    var timeTotalEl = document.getElementById('afTimeTotal');

    function setCount(sx, n) {
        var el = sx === 'M' ? countMEl : countFEl;
        if (el) el.textContent = n;
        var total = (totalCount.M || 0) + (totalCount.F || 0);
        if (countTotalEl) countTotalEl.textContent = total;
    }
    function setTime(sx, txt, color) {
        var el = sx === 'M' ? timeMEl : timeFEl;
        if (el) {
            el.textContent = txt;
            if (color) el.style.color = color;
        }
    }

    function fetchSexe(sx) {
        var totalLim = sx === 'M' ? nbH : nbF;
        var perLvl = sx === 'M' ? perLvlH : perLvlF;
        var start = Date.now();
        if (totalLim <= 0) {
            setStatus(sx, 'Aucun demande (limite=0)', '#8b949e');
            setBar(sx, 100);
            setTime(sx, '— skip', '#8b949e');
            var countEl0 = sx === 'M' ? countMEl : countFEl;
            if (countEl0) { countEl0.textContent = '0'; countEl0.style.color = '#8b949e'; }
            setBody(sx, '<p style="color:#8b949e;text-align:center;font-size:12px;padding:14px;">Aucun ' + (sx === 'M' ? 'homme' : 'femme') + ' demande</p>');
            totalCount[sx] = 0;
            return Promise.resolve({ athletes: [] });
        }
        var label = nivList.length > 1
            ? 'Recherche... (' + nivList.length + ' niveaux × ' + perLvl + ')'
            : 'Recherche... (limite ' + totalLim + ')';
        setStatus(sx, label, '#a78bfa');
        setBar(sx, 20);
        var countEl = sx === 'M' ? countMEl : countFEl;
        if (countEl) {
            countEl.textContent = '...';
            countEl.style.color = '#a78bfa';
        }
        setTime(sx, 'recherche...', '#a78bfa');

        var elapsedTimer = setInterval(function() {
            var s = ((Date.now() - start) / 1000).toFixed(1);
            setTime(sx, s + 's en cours...', '#a78bfa');
            if (timeTotalEl) timeTotalEl.textContent = ((Date.now() - globalStart) / 1000).toFixed(1) + 's total';
        }, 100);

        // PARALLELE : tous les niveaux en meme temps (Promise.all)
        var fetchPromises = nivList.map(function(lvl, i) {
            return fetch(buildUrl(sx, perLvl, lvl))
                .then(function(r) { return r.json(); })
                .then(function(d) {
                    // Update progress bar incrementalement
                    var done = (i + 1) / nivList.length;
                    setBar(sx, 20 + done * 70);
                    return d.athletes || [];
                });
        });

        return Promise.all(fetchPromises).then(function(results) {
            clearInterval(elapsedTimer);
            setBar(sx, 100);
            var elapsed = ((Date.now() - start) / 1000).toFixed(2);
            var allAthletes = results.reduce(function(a, b) { return a.concat(b); }, []);
            // Dedup par athlete_id (mode any peut renvoyer le meme athlete sur plusieurs niveaux)
            var seenIds = {};
            allAthletes = allAthletes.filter(function(a) {
                var id = parseInt(a.athlete_id, 10) || 0;
                if (!id || seenIds[id]) return false;
                seenIds[id] = true;
                return true;
            });
            var trimmed = allAthletes.slice(0, totalLim);
            var n = trimmed.length;
            totalCount[sx] = n;

            // Detail par niveau (pour info)
            var perLevelCounts = {};
            trimmed.forEach(function(a) {
                var lv = a.meilleur_niveau || '?';
                perLevelCounts[lv] = (perLevelCounts[lv] || 0) + 1;
            });
            var detailStr = Object.keys(perLevelCounts).map(function(k) {
                return k + ':' + perLevelCounts[k];
            }).join(' ');

            if (countEl) {
                countEl.style.color = sx === 'M' ? '#3b82f6' : '#ec4899';
                var animStart = Date.now();
                var animDur = 600;
                var animTick = setInterval(function() {
                    var p = Math.min(1, (Date.now() - animStart) / animDur);
                    var v = Math.round(n * (1 - Math.pow(1 - p, 3)));
                    countEl.textContent = v;
                    var total = (sx === 'M' ? v : (totalCount.M || 0)) + (sx === 'F' ? v : (totalCount.F || 0));
                    if (countTotalEl) countTotalEl.textContent = total;
                    if (p >= 1) {
                        clearInterval(animTick);
                        countEl.textContent = n;
                        setCount(sx, n);
                    }
                }, 30);
            }
            setStatus(sx, n + ' trouve' + (n > 1 ? 's' : '') + ' (' + elapsed + 's) ' + (detailStr ? '— ' + detailStr : ''), '#34d399');
            setTime(sx, '✓ ' + elapsed + 's', '#34d399');
            setBody(sx, renderList(trimmed));
            return { athletes: trimmed };
        }).catch(function(err) {
            clearInterval(elapsedTimer);
            setStatus(sx, 'Erreur', '#f85149');
            setTime(sx, 'erreur', '#f85149');
            if (countEl) { countEl.textContent = '!'; countEl.style.color = '#f85149'; }
            setBody(sx, '<p style="color:#f85149;font-size:12px;padding:14px;">Erreur : ' + esc(err.message) + '</p>');
        });
    }

    // Garder l'overlay XL ouvert pendant le chargement AJAX
    var overlay = document.getElementById('afLoaderOverlay');
    var progressBar = document.getElementById('afLoaderProgressBar');
    var elapsedEl = document.getElementById('afElapsed');
    var stepEls = document.querySelectorAll('.af-step');
    if (overlay) overlay.style.display = 'flex';

    // Helpers loader
    function setStep(stepNum, status) {
        stepEls.forEach(function(el) {
            var step = parseInt(el.dataset.step);
            if (step === stepNum) {
                el.classList.remove('done', 'active');
                el.classList.add(status === 'done' ? 'done' : 'active');
                var icon = el.querySelector('.af-step-icon');
                if (icon) icon.innerHTML = status === 'done' ? '&#10003;' : (status === 'active' ? '&#9737;' : '&#9711;');
            } else if (step < stepNum) {
                el.classList.remove('active');
                el.classList.add('done');
                var ic = el.querySelector('.af-step-icon');
                if (ic) ic.innerHTML = '&#10003;';
            }
        });
    }
    function setProgress(pct) {
        if (progressBar) progressBar.style.width = Math.min(100, Math.max(0, pct)) + '%';
    }

    // Compteur temps ecoule
    var elapsedTimer = setInterval(function() {
        if (elapsedEl) elapsedEl.textContent = ((Date.now() - globalStart) / 1000).toFixed(1);
    }, 100);

    // Etapes 1-2 deja completees au point d'execution (sauvegarde + cache)
    setStep(1, 'done');
    setStep(2, 'done');
    setStep(3, 'active');
    setProgress(35);

    // PARALLELE : M et F en meme temps (gain x2 sur le temps total)
    meta.textContent = 'Lancement de la recherche...';
    var fetchM = fetchSexe('M').then(function(r){ setStep(3, 'done'); setStep(4, 'active'); setProgress(60); return r; });
    var fetchF = fetchSexe('F').then(function(r){ setStep(4, 'done'); setStep(5, 'active'); setProgress(85); return r; });

    Promise.all([fetchM, fetchF]).then(function() {
        setStep(5, 'done');
        setProgress(100);
        clearInterval(elapsedTimer);

        var totalElapsed = ((Date.now() - globalStart) / 1000).toFixed(2);
        var total = totalCount.M + totalCount.F;
        var cacheStatus = '<?= $afCacheStatus ?>';
        var cacheMsg = cacheStatus === 'cleared' ? 'cache regenere' : 'cache reutilise';
        meta.innerHTML = '<span style="color:#34d399;">&#10003;</span> Termine en ' + totalElapsed + 's &middot; ' + cacheMsg;
        if (timeTotalEl) { timeTotalEl.textContent = '✓ ' + totalElapsed + 's'; timeTotalEl.style.color = '#34d399'; }
        if (countTotalEl) { countTotalEl.style.color = '#34d399'; }
        totalEl.innerHTML = 'Total : <strong style="color:#a78bfa;">' + total + '</strong> athletes seront affiches sur '
                          + '<a href="/?page=athletes" target="_blank" style="color:#6c5ce7;font-weight:700;">/athletes</a> '
                          + '<span style="color:#34d399;">(cache pret, chargement instantane)</span>';

        // Fermer l'overlay apres une petite pause pour montrer "100% fait"
        setTimeout(function() { if (overlay) overlay.style.display = 'none'; }, 600);
    });
})();
</script>
</body>
</html>
