<?php
/**
 * core/progressions_store.php — Stockage file-based pour athlete_progressions.
 *
 * Pourquoi : la table BDD pesait 1.2 GB (43% de la BDD). On la deporte dans
 * un fichier append-only avec marker delete-and-replace pour gerer les
 * mises a jour sans doublons.
 *
 * Architecture :
 *  - Source de verite : archives/athlete_progressions_live.jsonl
 *  - Index sharde     : archives/.prog_idx/<shard 0-255>.json
 *    Format : { "<id_athlete>": [offset1, offset2, ...] }
 *  - Format JSONL :
 *    {"_op":"delete","id_athlete":X}          marker : oublier l'historique
 *    {"id_athlete":X,"id_epreuve":Y,...}      data row
 *
 * API :
 *  - progStoreEnabled()                              true si data_source = file
 *  - progStoreSourcePath()                           chemin du .jsonl source
 *  - progStoreLoadForAthlete(int $idAthlete) : array progressions deduplique
 *  - progStoreLoadForAthletes(array $ids)    : array[id => array]
 *  - progStoreAppendBatch(int $idAthlete, array $rows) : ecrit delete + append
 */

if (!function_exists('progStoreEnabled')) {

/** Repertoires + constantes. */
function _progPaths() {
    static $p = null;
    if ($p === null) {
        $base = dirname(__DIR__);
        $p = [
            'archives'  => $base . '/archives',
            'live'      => $base . '/archives/athlete_progressions_live.jsonl',
            'idxDir'    => $base . '/archives/.prog_idx',
            'meta'      => $base . '/archives/.prog_idx/_meta.json',
            'pointer'   => $base . '/archives/.prog_idx/source.txt', // pointe vers le fichier source en cours
        ];
        if (!is_dir($p['archives'])) @mkdir($p['archives'], 0755, true);
        if (!is_dir($p['idxDir']))   @mkdir($p['idxDir'], 0755, true);
    }
    return $p;
}

/** Renvoie true si data_source.athlete_progressions = "file". */
function progStoreEnabled(): bool {
    require_once __DIR__ . '/data_source.php';
    return dataSourceMode('athlete_progressions') === 'file';
}

/**
 * Chemin du fichier source .jsonl.
 * Ordre de priorite :
 *   1. Fichier indique dans .prog_idx/source.txt (verrouille apres init)
 *   2. archives/athlete_progressions_live.jsonl si existe
 *   3. Le fichier athlete_progressions_*.jsonl le plus recent
 */
function progStoreSourcePath(): string {
    $p = _progPaths();
    // 1) Pointer explicite (ecrit par progressions_init.php)
    if (file_exists($p['pointer'])) {
        $name = trim(@file_get_contents($p['pointer']) ?: '');
        if ($name !== '') {
            $full = $p['archives'] . '/' . basename($name);
            if (file_exists($full)) return $full;
        }
    }
    // 2) Fichier _live.jsonl s'il existe
    if (file_exists($p['live'])) return $p['live'];
    // 3) Le plus recent fichier athlete_progressions_*.jsonl
    $cands = glob($p['archives'] . '/athlete_progressions_*.jsonl');
    if (!empty($cands)) {
        $cands = array_filter($cands, function($f) { return strpos($f, '_live.jsonl') === false; });
        if (!empty($cands)) {
            usort($cands, function($a, $b) { return filemtime($b) - filemtime($a); });
            return $cands[0];
        }
    }
    return $p['live']; // fallback (n'existe pas, mais c'est ce qu'on creera)
}

/** Fichier shard pour un id_athlete (sharding mod 256). */
function _progShardPath(int $idAthlete): string {
    $shard = $idAthlete & 0xFF;
    return _progPaths()['idxDir'] . '/' . $shard . '.json';
}

/** Charge un shard d'index (cache en memoire pour le request). */
function _progLoadShard(int $shard): array {
    static $cache = [];
    if (isset($cache[$shard])) return $cache[$shard];
    $path = _progPaths()['idxDir'] . '/' . $shard . '.json';
    if (!file_exists($path)) { $cache[$shard] = []; return []; }
    $data = json_decode(@file_get_contents($path) ?: '{}', true);
    if (!is_array($data)) $data = [];
    $cache[$shard] = $data;
    return $data;
}

/** Ecrit un shard d'index. Atomic via tmp + rename. */
function _progWriteShard(int $shard, array $data): bool {
    $path = _progPaths()['idxDir'] . '/' . $shard . '.json';
    $tmp  = $path . '.tmp';
    $json = json_encode($data, JSON_UNESCAPED_UNICODE);
    if (@file_put_contents($tmp, $json, LOCK_EX) === false) return false;
    return @rename($tmp, $path);
}

/**
 * Charge les progressions d'un athlete depuis le fichier.
 * Applique la logique delete-and-replace : un marker {"_op":"delete"}
 * oublie tout ce qu'on a accumule avant.
 */
function progStoreLoadForAthlete(int $idAthlete): array {
    $src = progStoreSourcePath();
    if (!file_exists($src)) return [];

    $shard = _progLoadShard($idAthlete & 0xFF);
    $offsets = $shard[(string)$idAthlete] ?? null;
    if (!$offsets) return [];

    $fp = fopen($src, 'rb');
    if (!$fp) return [];

    $rows = [];
    foreach ($offsets as $off) {
        if (@fseek($fp, $off) !== 0) continue;
        $line = fgets($fp);
        if ($line === false) continue;
        $row = json_decode(trim($line), true);
        if (!is_array($row)) continue;
        // Marker delete : on oublie tout ce qu'on a deja accumule
        if (!empty($row['_op']) && $row['_op'] === 'delete') {
            $rows = [];
            continue;
        }
        // Securite : on confirme que la ligne concerne le bon athlete
        if ((int)($row['id_athlete'] ?? 0) !== $idAthlete) continue;
        $rows[] = $row;
    }
    fclose($fp);
    return $rows;
}

/** Idem en batch pour plusieurs athletes (regroupe les lectures par shard). */
function progStoreLoadForAthletes(array $ids): array {
    $out = [];
    foreach ($ids as $id) {
        $id = (int)$id;
        if ($id <= 0) continue;
        $out[$id] = progStoreLoadForAthlete($id);
    }
    return $out;
}

/**
 * Ecrit un batch de progressions pour UN athlete.
 * Pattern delete-and-replace : on append d'abord un marker delete,
 * puis les nouvelles lignes. La lecture deduplique automatiquement.
 */
function progStoreAppendBatch(int $idAthlete, array $rows): bool {
    $src = progStoreSourcePath();
    $fp = @fopen($src, 'ab');
    if (!$fp) return false;

    if (!flock($fp, LOCK_EX)) { fclose($fp); return false; }

    // Position avant ecriture pour calculer les offsets
    $newOffsets = [];

    // 1) Marker delete pour invalider l'historique existant de cet athlete
    $pos = ftell($fp);
    $marker = json_encode(['_op' => 'delete', 'id_athlete' => $idAthlete]) . "\n";
    fwrite($fp, $marker);
    $newOffsets[] = $pos;

    // 2) Append des nouvelles lignes
    foreach ($rows as $row) {
        $row['id_athlete'] = $idAthlete;
        $pos = ftell($fp);
        fwrite($fp, json_encode($row, JSON_UNESCAPED_UNICODE) . "\n");
        $newOffsets[] = $pos;
    }
    fflush($fp);
    flock($fp, LOCK_UN);
    fclose($fp);

    // 3) Mise a jour de l'index : on ECRASE les anciens offsets de cet athlete
    //    (parce que le marker delete les a invalides de toute facon)
    $shard = $idAthlete & 0xFF;
    $idxData = _progLoadShard($shard);
    $idxData[(string)$idAthlete] = $newOffsets;
    _progWriteShard($shard, $idxData);

    return true;
}

/**
 * Enrichit les rows du store avec les noms (epreuves, villes, etc.) via batch SQL.
 * Retourne le format attendu par api/athlete.php :
 *   [ {epreuve, annee, performance, performance_brut, vent, date, lieu, categorie, club, ligue_dept, niveaux:[]}, ... ]
 *
 * @param mysqli $conn
 * @param int $idAthlete  pour le filtre niveaux dans athlete_resultats
 * @param array $rawRows  rows brutes du store
 */
function progStoreEnrichForProfile(mysqli $conn, int $idAthlete, array $rawRows): array {
    if (empty($rawRows)) return [];

    // Collecte des IDs uniques pour batch lookup
    $epIds = $vIds = $catIds = $clubIds = [];
    foreach ($rawRows as $r) {
        if (!empty($r['id_epreuve']))   $epIds[(int)$r['id_epreuve']] = 1;
        if (!empty($r['id_ville']))     $vIds[(int)$r['id_ville']] = 1;
        if (!empty($r['id_categorie'])) $catIds[(int)$r['id_categorie']] = 1;
        if (!empty($r['id_club']))      $clubIds[(int)$r['id_club']] = 1;
    }

    $epNames = $vNames = $catNames = $clubNames = [];
    if ($epIds) {
        $ids = implode(',', array_keys($epIds));
        $res = $conn->query("SELECT id_epreuve, nom_epreuve FROM epreuves WHERE id_epreuve IN ($ids)");
        if ($res) while ($x = $res->fetch_assoc()) $epNames[(int)$x['id_epreuve']] = $x['nom_epreuve'];
    }
    if ($vIds) {
        $ids = implode(',', array_keys($vIds));
        $res = $conn->query("SELECT id_ville, nom_ville FROM villes WHERE id_ville IN ($ids)");
        if ($res) while ($x = $res->fetch_assoc()) $vNames[(int)$x['id_ville']] = $x['nom_ville'];
    }
    if ($catIds) {
        $ids = implode(',', array_keys($catIds));
        $res = $conn->query("SELECT id_categorie, code_categorie FROM categories WHERE id_categorie IN ($ids)");
        if ($res) while ($x = $res->fetch_assoc()) $catNames[(int)$x['id_categorie']] = $x['code_categorie'];
    }
    if ($clubIds) {
        $ids = implode(',', array_keys($clubIds));
        $res = $conn->query("SELECT id_club, nom_club FROM clubs WHERE id_club IN ($ids)");
        if ($res) while ($x = $res->fetch_assoc()) $clubNames[(int)$x['id_club']] = $x['nom_club'];
    }

    // Niveaux : 1 query batch sur athlete_resultats pour cet athlete
    $niveauxByKey = [];
    if ($epIds) {
        $epList = implode(',', array_keys($epIds));
        $res = $conn->query("
            SELECT id_epreuve, annee_resultat,
                   GROUP_CONCAT(DISTINCT niveau_resultat ORDER BY niveau_resultat SEPARATOR ',') as niv
            FROM athlete_resultats
            WHERE id_athlete = $idAthlete AND id_epreuve IN ($epList)
              AND niveau_resultat IS NOT NULL AND niveau_resultat != ''
            GROUP BY id_epreuve, annee_resultat
        ");
        if ($res) while ($x = $res->fetch_assoc()) {
            $niveauxByKey[(int)$x['id_epreuve'] . '_' . (int)$x['annee_resultat']] = $x['niv'];
        }
    }

    // Tri par annee DESC (comme l'ancienne SQL)
    usort($rawRows, function($a, $b) {
        return (int)($b['annee_progression'] ?? 0) - (int)($a['annee_progression'] ?? 0);
    });

    $out = [];
    foreach ($rawRows as $row) {
        $epId  = (int)($row['id_epreuve'] ?? 0);
        $vId   = (int)($row['id_ville'] ?? 0);
        $catId = (int)($row['id_categorie'] ?? 0);
        $clbId = (int)($row['id_club'] ?? 0);
        $key   = $epId . '_' . (int)($row['annee_progression'] ?? 0);
        $nivList = array_values(array_filter(explode(',', $niveauxByKey[$key] ?? '')));

        $out[] = [
            'epreuve'          => $epNames[$epId] ?? '',
            'annee'            => (int)($row['annee_progression'] ?? 0),
            'performance'      => isset($row['performance_progression']) && $row['performance_progression'] ? (int)$row['performance_progression'] : null,
            'performance_brut' => $row['performance_brut_progression'] ?? null,
            'vent'             => $row['vent_progression'] ?? null,
            'date'             => $row['date_progression'] ?? null,
            'lieu'             => $vNames[$vId] ?? '',
            'categorie'        => $catNames[$catId] ?? '',
            'club'             => $clubNames[$clbId] ?? '',
            'ligue_dept'       => $row['ligue_dept_progression'] ?? null,
            'niveaux'          => $nivList,
        ];
    }
    return $out;
}

} // fin function_exists
