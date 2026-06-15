<?php
/**
 * core/athlete_purge.php — Suppression definitive d'un athlete + son compte user lie
 *
 * Quand un profil est masque (visible=0), on declenche en realite la suppression complete :
 *   - DELETE FROM athletes (CASCADE supprime tous les enfants : records, progressions,
 *     resultats, medailles, podiums, selections, niveaux, clubs, perfs_manuelles)
 *   - DELETE FROM users si un compte y est lie (CASCADE : sessions, password_resets,
 *     coach_athletes, perfs_manuelles auteur)
 *   - Tables sans FK (follows, vues_ip, scrape_log, hide_tokens, search_tracking) : nettoyees manuellement
 *   - DELETE FROM nom_et_liens (source du scraping) pour ne pas repasser dessus
 *   - INSERT dans `athlete_blacklist` pour qu'un futur discover ignore l'athlete
 *   - Vide les caches concernes + supprime src/{id}.php
 */

if (!function_exists('purgeAthleteByExternalId')) {
    function purgeAthleteByExternalId(mysqli $conn, int $athleteIdExt, string $reason = 'admin_purge'): array {
        $result = [
            'success'        => false,
            'athlete_id_ext' => $athleteIdExt,
            'athlete_id'     => null,
            'athlete_name'   => null,
            'user_id'        => null,
            'deleted'        => [],
        ];

        if ($athleteIdExt <= 0) {
            $result['error'] = 'invalid_id';
            return $result;
        }

        _ensureBlacklistTable($conn);

        // 1) Recup id_athlete + nom (avant tout DELETE)
        $stmt = $conn->prepare("SELECT id_athlete, nom_complet_athlete FROM athletes WHERE athlete_id_externe = ? LIMIT 1");
        $stmt->bind_param('i', $athleteIdExt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        $idAthlete = $row ? (int)$row['id_athlete'] : 0;
        $athleteName = $row ? (string)$row['nom_complet_athlete'] : '';
        $result['athlete_id']   = $idAthlete ?: null;
        $result['athlete_name'] = $athleteName;

        // 2) Blacklist en premier (idempotent, garantit le bloquage meme si la suite echoue)
        $stmtBl = $conn->prepare("INSERT INTO athlete_blacklist (athlete_id_ext, athlete_name, reason) VALUES (?, ?, ?)
                                  ON DUPLICATE KEY UPDATE reason = VALUES(reason), blacklisted_at = CURRENT_TIMESTAMP");
        $stmtBl->bind_param('iss', $athleteIdExt, $athleteName, $reason);
        $stmtBl->execute();
        $stmtBl->close();
        $result['deleted']['athlete_blacklist'] = 'inserted_or_updated';

        if ($idAthlete > 0) {
            // 3) Recup user lie AVANT DELETE athletes (FK athletes -> users est SET NULL,
            //    mais on veut supprimer le user complet)
            $stmt = $conn->prepare("SELECT id_user FROM users WHERE id_athlete = ? LIMIT 1");
            $stmt->bind_param('i', $idAthlete);
            $stmt->execute();
            $u = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($u) {
                $idUser = (int)$u['id_user'];
                $result['user_id'] = $idUser;
                $stmt = $conn->prepare("DELETE FROM users WHERE id_user = ?");
                $stmt->bind_param('i', $idUser);
                $stmt->execute();
                $result['deleted']['users'] = $stmt->affected_rows;
                $stmt->close();
            }

            // 4) Marquer suppression dans le progressions_store (fichier JSONL)
            //    Avant DELETE athletes pour que id_athlete soit encore valide
            $progStore = __DIR__ . '/progressions_store.php';
            if (file_exists($progStore)) {
                require_once $progStore;
                if (function_exists('progStoreEnabled') && progStoreEnabled() && function_exists('progStoreAppendBatch')) {
                    @progStoreAppendBatch($idAthlete, []); // delete marker
                    $result['deleted']['progressions_file'] = 'delete_marker_appended';
                }
            }

            // 5) DELETE athletes -> CASCADE sur 9 tables enfants
            $stmt = $conn->prepare("DELETE FROM athletes WHERE id_athlete = ?");
            $stmt->bind_param('i', $idAthlete);
            $stmt->execute();
            $result['deleted']['athletes'] = $stmt->affected_rows;
            $stmt->close();
        }

        // 6) Tables sans FK (existent peut-etre pas selon l'instance)
        $cleanup = [
            'athlete_follows'      => 'athlete_id_ext',
            'athlete_vues_ip'      => 'athlete_id_ext',
            'athlete_scrape_log'   => 'athlete_id_ext',
            'profile_hide_tokens'  => 'athlete_id_ext',
        ];
        foreach ($cleanup as $tbl => $col) {
            if (!_tableExists($conn, $tbl)) continue;
            $stmt = $conn->prepare("DELETE FROM `$tbl` WHERE `$col` = ?");
            $stmt->bind_param('i', $athleteIdExt);
            $stmt->execute();
            $result['deleted'][$tbl] = $stmt->affected_rows;
            $stmt->close();
        }

        // 7) search_tracking : nettoyer les consultations de cet athlete
        if (_tableExists($conn, 'search_tracking')) {
            $stmt = $conn->prepare("DELETE FROM search_tracking WHERE search_type = 'athlete' AND entity_id = ?");
            $stmt->bind_param('i', $athleteIdExt);
            $stmt->execute();
            $result['deleted']['search_tracking'] = $stmt->affected_rows;
            $stmt->close();
        }

        // 8) nom_et_liens (source scraping) — URLs contenant /athletes/{id}/
        if (_tableExists($conn, 'nom_et_liens')) {
            $stmt = $conn->prepare("DELETE FROM nom_et_liens WHERE url LIKE ?");
            $pattern = '%/athletes/' . $athleteIdExt . '/%';
            $stmt->bind_param('s', $pattern);
            $stmt->execute();
            $result['deleted']['nom_et_liens'] = $stmt->affected_rows;
            $stmt->close();
        }

        // 9) Vider caches concernes
        $cacheDir = __DIR__ . '/../cache';
        $cacheDeleted = 0;
        if (is_dir($cacheDir)) {
            // Cache athlete : par id explicite
            foreach (glob($cacheDir . '/athlete_*.json') ?: [] as $f) {
                $json = @file_get_contents($f);
                if ($json && (strpos($json, '"' . $athleteIdExt . '"') !== false || strpos($json, ':' . $athleteIdExt) !== false)) {
                    if (@unlink($f)) $cacheDeleted++;
                }
            }
            // Caches agrege (search/liste/stats/top) : vider tout, regenere a la prochaine requete
            foreach (['search_*.json', 'liste_*.json', 'stats_*.json', 'topsearched_*.json'] as $pat) {
                foreach (glob($cacheDir . '/' . $pat) ?: [] as $f) {
                    if (@unlink($f)) $cacheDeleted++;
                }
            }
        }
        $result['deleted']['cache_files'] = $cacheDeleted;

        // 10) Fichier src/{id}.php (JSON athlete pour pipeline scraping principal)
        $srcFile = __DIR__ . '/../src/' . $athleteIdExt . '.php';
        if (file_exists($srcFile) && @unlink($srcFile)) {
            $result['deleted']['src_file'] = 1;
        }

        $result['success'] = true;
        return $result;
    }
}

if (!function_exists('isAthleteBlacklisted')) {
    function isAthleteBlacklisted(mysqli $conn, int $athleteIdExt): bool {
        if ($athleteIdExt <= 0) return false;
        static $cache = [];
        if (array_key_exists($athleteIdExt, $cache)) return $cache[$athleteIdExt];

        if (!_tableExists($conn, 'athlete_blacklist')) return $cache[$athleteIdExt] = false;

        $stmt = $conn->prepare("SELECT 1 FROM athlete_blacklist WHERE athlete_id_ext = ? LIMIT 1");
        $stmt->bind_param('i', $athleteIdExt);
        $stmt->execute();
        $exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();
        return $cache[$athleteIdExt] = $exists;
    }
}

if (!function_exists('bkUserCanPurge')) {
    /**
     * Verifie si l'utilisateur courant a le droit de purger un profil.
     * - Cookie bk_sa_token valide (session super admin non expiree)
     * - OU email Google connecte present dans logs/.panel_access.php
     * - OU bk_key correct dans URL ou header
     */
    function bkUserCanPurge(mysqli $conn = null): bool {
        // 1. Super admin via cookie + session valide
        if (!empty($_COOKIE['bk_sa_token'])) {
            $saFile = __DIR__ . '/../logs/.sa_sessions.php';
            if (file_exists($saFile)) {
                $raw = @file_get_contents($saFile);
                if ($raw !== false) {
                    $pos = strpos($raw, "\n");
                    if ($pos !== false) {
                        $sessions = json_decode(substr($raw, $pos + 1), true) ?: [];
                        $tok = $_COOKIE['bk_sa_token'];
                        if (isset($sessions[$tok]) && ($sessions[$tok]['expires'] ?? 0) > time()) {
                            return true;
                        }
                    }
                }
            }
        }
        // 2. Google connecte + email dans panel_access
        if ($conn !== null && function_exists('getCurrentUser')) {
            $u = getCurrentUser($conn);
            if ($u && !empty($u['email'])) {
                $paFile = __DIR__ . '/../logs/.panel_access.php';
                if (file_exists($paFile)) {
                    $paRaw = @file_get_contents($paFile);
                    if ($paRaw !== false) {
                        $paPos = strpos($paRaw, "\n");
                        if ($paPos !== false) {
                            $paList = json_decode(substr($paRaw, $paPos + 1), true) ?: [];
                            if (isset($paList[strtolower($u['email'])])) {
                                return true;
                            }
                        }
                    }
                }
            }
        }
        // 3. bk_key debug
        if (($_GET['bk_key'] ?? '') === 'bk_s3cr3t_2026_xK9mP') return true;
        if (($_SERVER['HTTP_X_BK_KEY'] ?? '') === 'bk_s3cr3t_2026_xK9mP') return true;
        return false;
    }
}

if (!function_exists('_ensureBlacklistTable')) {
    function _ensureBlacklistTable(mysqli $conn): void {
        static $done = false;
        if ($done) return;
        $conn->query("CREATE TABLE IF NOT EXISTS `athlete_blacklist` (
            `athlete_id_ext` INT UNSIGNED PRIMARY KEY,
            `athlete_name`   VARCHAR(200) NOT NULL DEFAULT '',
            `reason`         VARCHAR(100) NOT NULL DEFAULT '',
            `blacklisted_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY `idx_bl_at` (`blacklisted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $done = true;
    }
}

if (!function_exists('_tableExists')) {
    function _tableExists(mysqli $conn, string $name): bool {
        static $cache = [];
        if (isset($cache[$name])) return $cache[$name];
        $safe = $conn->real_escape_string($name);
        $r = $conn->query("SHOW TABLES LIKE '$safe'");
        $exists = ($r && $r->num_rows > 0);
        if ($r) $r->free();
        return $cache[$name] = $exists;
    }
}
