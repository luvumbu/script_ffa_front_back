<?php
/**
 * core/demo_mode.php — Démo Platine de 5 minutes, self-service, une seule fois.
 *
 * N'importe quel MEMBRE CONNECTÉ (cookie bk_token) peut déclencher, UNE SEULE
 * FOIS (définitif), un accès Platine complet de 5 minutes, pour découvrir tout
 * ce que l'abonnement débloque.
 *
 * Anti-abus : l'état est stocké CÔTÉ SERVEUR par id_user (logs/.demo_used.php).
 * Vider ses cookies ne redonne donc PAS droit à une nouvelle démo.
 *
 * Branché — comme le « mode test » super admin — dans :
 *   - core/subscription.php (getUserSubscription → abonnement Platine simulé)
 *   - core/paywall.php      (bkIsSubscriber → contenu premium débloqué)
 *   - core/search_limit.php (bkSearchLimit → recherches illimitées)
 * Bannière compte à rebours pendant la démo : bkDemoBanner().
 * Bannière d'invitation (démo disponible) : bkDemoPromoBanner().
 */

if (!defined('BK_DEMO_DURATION')) define('BK_DEMO_DURATION', 300);     // 5 minutes
if (!defined('BK_DEMO_PLAN'))     define('BK_DEMO_PLAN', 'platine');   // offre simulée

if (!function_exists('bkDemoFile')) {

    /** Fichier registre des démos (JSON protégé par die()). */
    function bkDemoFile() { return __DIR__ . '/../logs/.demo_used.php'; }

    /** Lit le registre { "u<id>": { "start": ts, "ip": "..." }, ... }. */
    function bkDemoReadAll() {
        $f = bkDemoFile();
        if (!file_exists($f)) return [];
        $raw = file_get_contents($f);
        $pos = strpos($raw, "\n");
        if ($pos === false) return [];
        return json_decode(substr($raw, $pos + 1), true) ?: [];
    }

    /** Écrit le registre (en-tête die() + JSON). */
    function bkDemoWriteAll($data) {
        @file_put_contents(
            bkDemoFile(),
            "<?php die('Acces interdit'); ?>\n" . json_encode($data, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }

    /** id_user du membre connecté (0 si anonyme / session invalide). */
    function bkDemoCurrentUid($conn) {
        static $uid = null;
        if ($uid !== null) return $uid;
        $uid = 0;
        if (!empty($_COOKIE['bk_token']) && function_exists('getCurrentUser')) {
            $u = getCurrentUser($conn);
            $uid = (int)($u['id_user'] ?? 0);
        }
        return $uid;
    }

    /** Timestamp de départ de la démo de ce membre (0 = jamais lancée). */
    function bkDemoStartedAt($conn, $uid = null) {
        if ($uid === null) $uid = bkDemoCurrentUid($conn);
        if ($uid <= 0) return 0;
        $all = bkDemoReadAll();
        return (int)($all['u' . $uid]['start'] ?? 0);
    }

    /** Le membre a-t-il DÉJÀ consommé sa démo (active ou expirée) ? */
    function bkDemoUsed($conn, $uid = null) {
        return bkDemoStartedAt($conn, $uid) > 0;
    }

    /** Secondes restantes de la démo en cours (0 si aucune / expirée). */
    function bkDemoRemaining($conn, $uid = null) {
        $start = bkDemoStartedAt($conn, $uid);
        if ($start <= 0) return 0;
        $left = BK_DEMO_DURATION - (time() - $start);
        return $left > 0 ? $left : 0;
    }

    /** La démo Platine est-elle ACTIVE maintenant pour le membre courant ? */
    function bkDemoActive($conn) {
        static $cache = null;
        if ($cache !== null) return $cache;
        $cache = (bkDemoCurrentUid($conn) > 0) && (bkDemoRemaining($conn) > 0);
        return $cache;
    }

    /**
     * La démo est-elle DISPONIBLE (proposable) au membre courant ?
     * = connecté + pas déjà abonné + jamais utilisée.
     */
    function bkDemoAvailable($conn) {
        $uid = bkDemoCurrentUid($conn);
        if ($uid <= 0) return false;
        if (bkDemoUsed($conn, $uid)) return false;
        if (function_exists('hasActiveSubscription') && hasActiveSubscription($conn, $uid)) return false;
        return true;
    }

    /**
     * Démarre la démo pour le membre courant.
     * @return array { ok:bool, reason:string, remaining:int }
     *   reason : 'not_logged' | 'already_subscriber' | 'already_used' | ''
     */
    function bkDemoStart($conn) {
        $uid = bkDemoCurrentUid($conn);
        if ($uid <= 0) {
            return ['ok' => false, 'reason' => 'not_logged', 'remaining' => 0];
        }
        if (function_exists('hasActiveSubscription') && hasActiveSubscription($conn, $uid)) {
            return ['ok' => false, 'reason' => 'already_subscriber', 'remaining' => 0];
        }
        $all = bkDemoReadAll();
        if (!empty($all['u' . $uid]['start'])) {
            return ['ok' => false, 'reason' => 'already_used', 'remaining' => 0];
        }
        $now = time();
        $all['u' . $uid] = ['start' => $now, 'ip' => $_SERVER['REMOTE_ADDR'] ?? ''];
        bkDemoWriteAll($all);
        // Aucun cookie : l'état (départ + « déjà utilisée ») vit UNIQUEMENT dans le
        // fichier serveur logs/.demo_used.php. Le compte à rebours JS lit le temps
        // restant calculé côté serveur (attribut data-left de la bannière).
        return ['ok' => true, 'reason' => '', 'remaining' => BK_DEMO_DURATION];
    }

    /** Bannière compte à rebours, affichée en bas pendant la démo active. */
    function bkDemoBanner($conn) {
        if (!bkDemoActive($conn)) return '';
        $left   = bkDemoRemaining($conn);
        $tarifs = function_exists('BK_URL') ? BK_URL('/tarifs') : '/tarifs';
        $clock  = sprintf('%d:%02d', intdiv($left, 60), $left % 60);
        return '<div id="bkDemoBanner" data-left="' . (int)$left . '" '
            . 'style="position:fixed;left:0;right:0;bottom:0;z-index:2147483600;'
            . 'background:linear-gradient(135deg,#8b7cf0,#6d28d9);color:#fff;font-family:Arial,Helvetica,sans-serif;'
            . 'font-size:13px;font-weight:700;padding:9px 16px;display:flex;align-items:center;justify-content:center;'
            . 'gap:14px;flex-wrap:wrap;box-shadow:0 -2px 14px rgba(0,0,0,.35);">'
            . '<span>&#127881; Démo <b>Platine</b> en cours &mdash; vous avez accès à tout. '
            . 'Il vous reste <b id="bkDemoTime">' . $clock . '</b></span>'
            . '<button type="button" onclick="(function(){var u=new URL(location.href);u.searchParams.set(\'demo_guide\',\'1\');location.href=u.toString();})();" '
            . 'style="background:rgba(255,255,255,.16);color:#fff;border:1px solid rgba(255,255,255,.35);'
            . 'border-radius:6px;padding:6px 12px;font-weight:700;cursor:pointer;font-size:13px;">Que faire&nbsp;?</button>'
            . '<a href="' . htmlspecialchars($tarifs) . '" style="background:#fff;color:#6d28d9;'
            . 'border-radius:6px;padding:6px 14px;font-weight:800;text-decoration:none;font-size:13px;">'
            . 'Conserver cet accès &rarr;</a>'
            . '<script>(function(){var b=document.getElementById("bkDemoBanner"),t=document.getElementById("bkDemoTime");'
            . 'if(!b||!t)return;var left=parseInt(b.getAttribute("data-left"),10)||0;'
            . 'var iv=setInterval(function(){left--;if(left<=0){clearInterval(iv);'
            . 'try{var u=new URL(location.href);u.searchParams.set("demo_ended","1");location.href=u.toString();}'
            . 'catch(e){location.reload();}return;}'
            . 't.textContent=Math.floor(left/60)+":"+("0"+(left%60)).slice(-2);},1000);})();</script>'
            . '</div>';
    }

    /**
     * Écran de fin de démo (overlay), affiché une fois après expiration.
     * À appeler dans index.php quand ?demo_ended=1 est présent.
     */
    function bkDemoEndedScreen($conn) {
        $uid = bkDemoCurrentUid($conn);
        // Seulement si la démo a bien été consommée ET qu'elle est terminée.
        if ($uid <= 0 || !bkDemoUsed($conn, $uid) || bkDemoActive($conn)) return '';
        $tarifs = function_exists('BK_URL') ? BK_URL('/tarifs') : '/tarifs';
        return '<div id="bkDemoEnded" style="position:fixed;inset:0;z-index:2147483640;'
            . 'background:rgba(8,12,20,.92);backdrop-filter:blur(6px);display:flex;align-items:center;'
            . 'justify-content:center;padding:24px;font-family:Inter,system-ui,sans-serif;">'
            . '<div style="max-width:480px;width:100%;background:linear-gradient(150deg,#131a28,#0d1117);'
            . 'border:1px solid #2a2350;border-radius:18px;padding:36px 30px;text-align:center;box-shadow:0 24px 60px rgba(0,0,0,.6);">'
            . '<div style="font-size:46px;margin-bottom:10px;">&#127881;</div>'
            . '<h2 style="color:#fff;font-size:21px;font-weight:800;margin:0 0 12px;border:none;">Votre démo Platine est terminée</h2>'
            . '<p style="color:#8b949e;font-size:14px;line-height:1.6;margin:0 0 10px;">Pendant 5 minutes, vous aviez accès à <b style="color:#c9d1d9;">tout BOKONZI</b> : '
            . 'recherches illimitées, fiches sans minuteur, biographies complètes, comparateur, exports et l\'Espace Club.</p>'
            . '<p style="color:#8b949e;font-size:14px;line-height:1.6;margin:0 0 20px;">Conservez cet accès dès <b style="color:#c9d1d9;">1,99&nbsp;€/mois</b> — sans engagement.</p>'
            . '<div style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;">'
            . '<a href="' . htmlspecialchars($tarifs) . '" style="display:inline-block;padding:12px 22px;border-radius:11px;'
            . 'font-size:14px;font-weight:700;text-decoration:none;background:linear-gradient(135deg,#8b7cf0,#6d28d9);color:#fff;">Voir les offres &rarr;</a>'
            . '<button type="button" onclick="document.getElementById(\'bkDemoEnded\').remove();" '
            . 'style="padding:12px 22px;border-radius:11px;font-size:14px;font-weight:700;cursor:pointer;'
            . 'border:1.5px solid #1e2a3a;background:transparent;color:#c9d1d9;">Plus tard</button>'
            . '</div></div>'
            . '<script>(function(){try{var u=new URL(location.href);u.searchParams.delete("demo_ended");'
            . 'history.replaceState(null,"",u.toString());}catch(e){}})();</script>'
            . '</div>';
    }

    /**
     * Tour guidé PAS-À-PAS (Suivant / Précédent), affiché au lancement de la démo.
     * Chaque étape explique une fonctionnalité Platine + un bouton « Essayer »
     * (nouvel onglet, pour ne pas perdre le tour). À appeler quand ?demo_started=1
     * (posé au lancement) ou ?demo_guide=1 (lien « Que faire ? » de la bannière).
     */
    function bkDemoWelcomeScreen($conn) {
        if (!bkDemoActive($conn)) return ''; // sécurité : seulement pendant la démo
        $hasUrl = function_exists('BK_URL');
        $url = function ($p) use ($hasUrl) { return $hasUrl ? BK_URL($p) : $p; };
        $left = bkDemoRemaining($conn);
        $clock = sprintf('%d:%02d', intdiv($left, 60), $left % 60);

        // Étapes du tour : icône, titre, description, lien « Essayer » (vide = pas de bouton), libellé.
        $steps = [
            ['icon' => '&#127881;', 'title' => 'Bienvenue en Platine !', 'desc' => 'Vous avez <b>5 minutes</b> d\'accès complet à tout BOKONZI. Je vous montre l\'essentiel, étape par étape — cliquez sur <b>Suivant</b>.', 'href' => '', 'cta' => ''],
            ['icon' => '&#128269;', 'title' => 'Recherche illimitée', 'desc' => 'Fini le quota : cherchez autant d\'athlètes, clubs, épreuves et villes que vous voulez, sans minuteur.', 'href' => $url('/recherche'), 'cta' => 'Lancer une recherche'],
            ['icon' => '&#128202;', 'title' => 'Comparateur d\'athlètes', 'desc' => 'Mettez deux athlètes (ou deux clubs) côte à côte : records, progressions, médailles, niveaux.', 'href' => $url('/comparer'), 'cta' => 'Comparer'],
            ['icon' => '&#128100;', 'title' => 'Fiches & bios complètes', 'desc' => 'Plus de minuteur de 2 min ni de description coupée : la biographie et tout le palmarès, en entier.', 'href' => $url('/athletes'), 'cta' => 'Ouvrir une fiche'],
            ['icon' => '&#128196;', 'title' => 'Export PDF', 'desc' => 'Téléchargez la fiche complète d\'un athlète en PDF, depuis le bouton présent sur chaque profil.', 'href' => $url('/athletes'), 'cta' => 'Choisir un athlète'],
            ['icon' => '&#127942;', 'title' => 'Espace Club Pro', 'desc' => 'L\'effectif complet d\'un club, les niveaux, le palmarès et l\'export CSV — l\'outil pensé pour les structures.', 'href' => $url('/clubs'), 'cta' => 'Explorer un club'],
            ['icon' => '&#128081;', 'title' => 'Tops & statistiques', 'desc' => 'Classements, tops consultés et statistiques avancées, sans aucune restriction.', 'href' => $url('/accueil'), 'cta' => 'Voir les tops'],
            ['icon' => '&#128640;', 'title' => 'À vous de jouer !', 'desc' => 'Explorez librement, le compteur tourne. Pour revoir ce guide à tout moment, cliquez sur <b>« Que faire ? »</b> dans la bannière en bas.', 'href' => '', 'cta' => ''],
        ];
        $stepsJson = json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);

        return '<div id="bkDemoWelcome" style="position:fixed;inset:0;z-index:2147483630;'
            . 'background:rgba(8,12,20,.92);backdrop-filter:blur(6px);display:flex;align-items:center;'
            . 'justify-content:center;padding:28px 18px;overflow-y:auto;font-family:Inter,system-ui,sans-serif;">'
            . '<div style="max-width:520px;width:100%;background:linear-gradient(160deg,#161b22,#0d1117);'
            . 'border:1px solid #2a2350;border-radius:18px;padding:30px 28px 22px;box-shadow:0 30px 70px rgba(0,0,0,.6);position:relative;text-align:center;">'
            . '<button type="button" onclick="document.getElementById(\'bkDemoWelcome\').remove();" '
            . 'style="position:absolute;top:12px;right:16px;background:transparent;border:none;color:#8b949e;font-size:26px;cursor:pointer;line-height:1;">&times;</button>'
            . '<div id="bkTourIcon" style="font-size:46px;margin-bottom:10px;">&#127881;</div>'
            . '<h2 id="bkTourTitle" style="color:#fff;font-size:21px;font-weight:800;margin:0 0 10px;border:none;"></h2>'
            . '<p id="bkTourDesc" style="color:#8b949e;font-size:14px;line-height:1.6;margin:0 auto 16px;max-width:420px;min-height:64px;"></p>'
            . '<a id="bkTourTry" href="#" target="_blank" rel="noopener" '
            . 'style="display:none;margin:0 auto 18px;padding:10px 20px;border-radius:10px;background:linear-gradient(135deg,#8b7cf0,#6d28d9);color:#fff;text-decoration:none;font-weight:700;font-size:13px;"></a>'
            . '<div id="bkTourDots" style="display:flex;gap:7px;justify-content:center;margin:6px 0 16px;"></div>'
            . '<div style="display:flex;align-items:center;justify-content:space-between;gap:10px;">'
            . '<button type="button" id="bkTourPrev" onclick="bkTour(-1)" style="padding:10px 16px;border-radius:9px;border:1.5px solid #1e2a3a;background:transparent;color:#c9d1d9;font-weight:700;font-size:13px;cursor:pointer;">&larr; Précédent</button>'
            . '<span id="bkTourStep" style="color:#5a6580;font-size:12px;font-weight:700;"></span>'
            . '<button type="button" id="bkTourNext" onclick="bkTour(1)" style="padding:10px 18px;border-radius:9px;border:none;background:linear-gradient(135deg,#8b7cf0,#6d28d9);color:#fff;font-weight:800;font-size:13px;cursor:pointer;">Suivant &rarr;</button>'
            . '</div>'
            . '<p style="color:#5a6580;font-size:12px;margin:16px 0 0;">Démo Platine — il reste <b id="bkDemoGuideTime" style="color:#a78bfa;">' . $clock . '</b></p>'
            . '</div>'
            . '<script>(function(){'
            . 'try{var u=new URL(location.href);u.searchParams.delete("demo_started");u.searchParams.delete("demo_guide");history.replaceState(null,"",u.toString());}catch(e){}'
            . 'var STEPS=' . $stepsJson . ',idx=0;'
            . 'var dots=document.getElementById("bkTourDots");'
            . 'for(var k=0;k<STEPS.length;k++){(function(kk){var b=document.createElement("button");'
            . 'b.style.cssText="width:8px;height:8px;border-radius:50%;border:none;padding:0;cursor:pointer;background:#2a3550;";'
            . 'b.onclick=function(){idx=kk;render();};dots.appendChild(b);})(k);}'
            . 'function render(){var s=STEPS[idx];'
            . 'document.getElementById("bkTourIcon").innerHTML=s.icon;'
            . 'document.getElementById("bkTourTitle").textContent=s.title;'
            . 'document.getElementById("bkTourDesc").innerHTML=s.desc;'
            . 'var tr=document.getElementById("bkTourTry");'
            . 'if(s.href){tr.style.display="inline-block";tr.href=s.href;tr.innerHTML=s.cta+" &rarr;";}else{tr.style.display="none";}'
            . 'document.getElementById("bkTourStep").textContent=(idx+1)+" / "+STEPS.length;'
            . 'document.getElementById("bkTourPrev").style.visibility=idx===0?"hidden":"visible";'
            . 'document.getElementById("bkTourNext").innerHTML=(idx===STEPS.length-1)?"Explorer &#10003;":"Suivant &rarr;";'
            . 'for(var k=0;k<dots.children.length;k++){dots.children[k].style.background=k===idx?"#a78bfa":"#2a3550";}}'
            . 'window.bkTour=function(dir){if(dir>0&&idx===STEPS.length-1){var e=document.getElementById("bkDemoWelcome");if(e)e.remove();return;}'
            . 'idx=Math.max(0,Math.min(STEPS.length-1,idx+dir));render();};'
            . 'render();'
            . 'var left=' . (int)$left . ',t=document.getElementById("bkDemoGuideTime");'
            . 'if(t){var iv=setInterval(function(){left--;if(left<=0){clearInterval(iv);return;}'
            . 't.textContent=Math.floor(left/60)+":"+("0"+(left%60)).slice(-2);},1000);}'
            . '})();</script>'
            . '</div>';
    }

    /** Réinitialise la démo d'un membre (lui en redonne le droit). Admin. */
    function bkDemoResetUid($uid) {
        $uid = (int)$uid;
        if ($uid <= 0) return false;
        $all = bkDemoReadAll();
        if (!isset($all['u' . $uid])) return false;
        unset($all['u' . $uid]);
        bkDemoWriteAll($all);
        return true;
    }

    /**
     * Liste enrichie des démos lancées (mesure de conversion). Admin.
     * @return array { total, converted, conversion_rate, rows:[{id_user, email,
     *   started_at, ip, subscribed, plan}] }
     */
    function bkDemoListAll($conn) {
        $all  = bkDemoReadAll();
        $rows = [];
        foreach ($all as $key => $rec) {
            if (strpos($key, 'u') !== 0) continue;
            $uid = (int)substr($key, 1);
            if ($uid <= 0) continue;
            $start = (int)($rec['start'] ?? 0);
            $rows[$uid] = [
                'id_user'    => $uid,
                'email'      => null,
                'started_at' => $start ? date('Y-m-d H:i:s', $start) : null,
                'start'      => $start,
                'ip'         => $rec['ip'] ?? '',
                'subscribed' => false,
                'plan'       => null,
            ];
        }
        if ($rows && $conn) {
            $ids = implode(',', array_map('intval', array_keys($rows)));
            if ($res = @$conn->query("SELECT id_user, email FROM users WHERE id_user IN ($ids)")) {
                while ($u = $res->fetch_assoc()) $rows[(int)$u['id_user']]['email'] = $u['email'];
            }
            global $BK_ACTIVE_STATUSES;
            $act = $BK_ACTIVE_STATUSES ?: ['active', 'trialing', 'past_due'];
            if ($res = @$conn->query("SELECT id_user, plan, status FROM subscriptions WHERE id_user IN ($ids)")) {
                while ($s = $res->fetch_assoc()) {
                    if (in_array($s['status'], $act, true)) {
                        $rows[(int)$s['id_user']]['subscribed'] = true;
                        $rows[(int)$s['id_user']]['plan']       = $s['plan'];
                    }
                }
            }
        }
        usort($rows, function ($a, $b) { return $b['start'] - $a['start']; });
        $rows      = array_values($rows);
        $total     = count($rows);
        $converted = count(array_filter($rows, function ($r) { return $r['subscribed']; }));
        return [
            'total'           => $total,
            'converted'       => $converted,
            'conversion_rate' => $total ? round($converted * 100 / $total, 1) : 0,
            'rows'            => $rows,
        ];
    }

    /** Bannière d'invitation (démo disponible, pas encore lancée). */
    function bkDemoPromoBanner($conn) {
        if (!bkDemoAvailable($conn)) return '';            // pas proposable
        if (bkDemoActive($conn))     return '';            // déjà en cours
        return '<div id="bkDemoPromo" '
            . 'style="position:fixed;left:0;right:0;bottom:0;z-index:2147483590;'
            . 'background:linear-gradient(135deg,#6c5ce7,#ec4899);color:#fff;font-family:Arial,Helvetica,sans-serif;'
            . 'font-size:13px;font-weight:700;padding:9px 16px;display:flex;align-items:center;justify-content:center;'
            . 'gap:14px;flex-wrap:wrap;box-shadow:0 -2px 14px rgba(0,0,0,.35);">'
            . '<span>&#127881; <b>Découvrez l\'offre Platine gratuitement</b> &mdash; 5 minutes d\'accès complet, une seule fois.</span>'
            . '<button type="button" onclick="bkStartDemo(this)" style="background:#fff;color:#6d28d9;border:none;'
            . 'border-radius:6px;padding:6px 16px;font-weight:800;cursor:pointer;font-size:13px;">Démarrer ma démo</button>'
            . '<button type="button" aria-label="Fermer" '
            . 'onclick="try{sessionStorage.setItem(\'bk_demo_promo_x\',\'1\')}catch(e){};this.parentNode.remove();" '
            . 'style="background:transparent;color:#fff;border:none;font-size:20px;cursor:pointer;line-height:1;">&times;</button>'
            . '<script>(function(){try{if(sessionStorage.getItem("bk_demo_promo_x")){var e=document.getElementById("bkDemoPromo");if(e)e.remove();}}catch(e){}})();</script>'
            . '</div>';
    }
}
