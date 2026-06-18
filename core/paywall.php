<?php
/**
 * core/paywall.php — Aperçu flouté + cadenas pour le contenu premium.
 *
 * Principe « value-first » : le contenu reste affiché mais FLOUTÉ pour les
 * non-abonnés, avec un cadenas + un bouton d'upgrade. L'utilisateur VOIT ce
 * qu'il rate → envie d'acheter.
 *
 * Utilisation :
 *   1) Inclure ce fichier après core/db.php + core/auth.php ($conn + session).
 *   2) Appeler bkPaywallAssets($conn) UNE fois dans le <head>.
 *   3) Sur n'importe quel bloc premium, ajouter simplement :
 *        class="bk-premium" data-lock-label="Biographie complète"
 *      (option : data-lock-plan="argent" — défaut Argent)
 *      → si le visiteur n'est pas abonné, le bloc est flouté automatiquement.
 *
 * Le texte reste dans le DOM (flou purement CSS) → aucun impact SEO.
 */

require_once __DIR__ . '/subscription.php'; // hasActiveSubscription(), getUserPlan()
require_once __DIR__ . '/paths.php';        // BK_URL()

if (!function_exists('bkIsSubscriber')) {
    /** L'utilisateur courant a-t-il un abonnement actif ? (mis en cache) */
    function bkIsSubscriber($conn) {
        static $cached = null;
        if ($cached !== null) return $cached;
        // Mode test super admin : prime sur la détection réelle.
        if (function_exists('bkTestRole') && bkTestRole() !== '') {
            $cached = bkTestIsPlan();
            return $cached;
        }
        // Démo Platine self-service en cours → débloqué comme un abonné.
        if (function_exists('bkDemoActive') && bkDemoActive($conn)) {
            $cached = true;
            return $cached;
        }
        $u = function_exists('getCurrentUser') ? getCurrentUser($conn) : null;
        $cached = ($u && !empty($u['id_user']) && hasActiveSubscription($conn, (int)$u['id_user']));
        return $cached;
    }
}

if (!function_exists('bkPaywallAssets')) {
    /** Injecte le CSS + JS du paywall (une seule fois). */
    function bkPaywallAssets($conn) {
        static $done = false;
        if ($done) return;
        $done = true;
        $isSub     = bkIsSubscriber($conn) ? 'true' : 'false';
        $isLogged  = (function_exists('getCurrentUser') && getCurrentUser($conn)) ? 'true' : 'false';
        $demoAvail = (function_exists('bkDemoAvailable') && bkDemoAvailable($conn)) ? 'true' : 'false';
        $tarifs    = BK_URL('/tarifs');
        $loginUrl  = BK_URL('/login.php');
        $demoUrl   = BK_URL('/api/demo_start.php');
        ?>
<style>
/* Le bloc : on montre le DÉBUT en clair (preuve de l'info), on coupe le reste. */
.bk-lock { position: relative; overflow: hidden; border-radius: 12px; max-height: 280px; }
.bk-lock-content { opacity: 1; }
/* La partie basse : floutée + assombrie en dégradé, avec le cadenas + le CTA.
   Le haut (jusqu'à ~44%) reste parfaitement lisible. */
.bk-lock-overlay {
    position: absolute; left: 0; right: 0; bottom: 0; top: 44%;
    display: flex; flex-direction: column; align-items: center; justify-content: flex-end;
    gap: 8px; text-align: center; padding: 18px 20px 20px;
    -webkit-backdrop-filter: blur(5px); backdrop-filter: blur(5px);
    background: linear-gradient(180deg, rgba(13,17,23,0) 0%, rgba(13,17,23,.55) 42%, rgba(13,17,23,.93) 100%);
}
.bk-lock-overlay .bk-lock-ico { font-size: 26px; line-height: 1; }
.bk-lock-overlay .bk-lock-title { color: #fff; font-weight: 800; font-size: 16px; }
.bk-lock-overlay .bk-lock-sub { color: #c9d1d9; font-size: 13px; max-width: 340px; line-height: 1.5; }
.bk-lock-cta {
    display: inline-block; margin-top: 4px; padding: 11px 24px; border-radius: 10px;
    background: linear-gradient(135deg, #9ca3af, #6c5ce7); color: #fff; text-decoration: none;
    font-weight: 800; font-size: 14px; box-shadow: 0 4px 16px rgba(108,92,231,.35);
}
.bk-lock-cta:hover { filter: brightness(1.08); }
/* Bouton secondaire « démo 5 min gratuites » (membre connecté) */
.bk-lock-demo {
    display: inline-block; margin-top: 6px; padding: 9px 18px; border-radius: 10px;
    background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.30);
    color: #fff; text-decoration: none; font-weight: 700; font-size: 13px; cursor: pointer;
    font-family: inherit;
}
.bk-lock-demo:hover { background: rgba(255,255,255,.18); }
.bk-lock-demo:disabled { opacity: .6; cursor: default; }

/* Masquage RÉEL d'un texte (la suite n'est pas envoyée : remplacée par des *) */
.bk-masked .bk-mask-stars { color: #6e7681; letter-spacing: 1px; }
.bk-mask-cta {
    margin-top: 14px; padding: 16px; border: 1px dashed rgba(108,92,231,.45);
    border-radius: 12px; background: rgba(108,92,231,.08); text-align: center;
}
.bk-mask-cta .bk-mask-lock { display: block; color: #c9d1d9; font-size: 13px; margin-bottom: 10px; }
</style>
<script>
window.BK_IS_SUB         = <?= $isSub ?>;
window.BK_IS_LOGGED      = <?= $isLogged ?>;
window.BK_DEMO_AVAILABLE = <?= $demoAvail ?>;
window.BK_TARIFS_URL     = <?= json_encode($tarifs) ?>;
window.BK_LOGIN_URL      = <?= json_encode($loginUrl) ?>;
window.BK_DEMO_START_URL = <?= json_encode($demoUrl) ?>;
window.BK_REVEAL_MS      = 30000; // visiteur non abonné : description lisible 30 s puis floutée
(function () {
    function cap(s) { s = String(s || ''); return s ? s.charAt(0).toUpperCase() + s.slice(1) : s; }

    // Démarre la démo Platine de 5 min (membre connecté, une seule fois).
    window.bkStartDemo = function (btn) {
        var prev = btn ? btn.textContent : '';
        if (btn) { btn.disabled = true; btn.textContent = 'Activation…'; }
        fetch(window.BK_DEMO_START_URL, { method: 'POST', credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d && d.ok) {
                    // Recharge en affichant le guide « que faire en Platine ».
                    try {
                        var u = new URL(location.href);
                        u.searchParams.set('demo_started', '1');
                        location.href = u.toString();
                    } catch (e) { location.reload(); }
                    return;
                }
                alert(d && d.message ? d.message : 'Démo indisponible.');
                if (btn) { btn.disabled = false; btn.textContent = prev; }
            })
            .catch(function () {
                alert('Connexion impossible. Réessayez.');
                if (btn) { btn.disabled = false; btn.textContent = prev; }
            });
    };

    // Temps de lecture gratuit restant (ms) avant floutage, pour un non-abonné.
    // Fenêtre de 30 s par chargement de page (Date.now du chargement du script).
    var BK_PAGE_T0 = Date.now();
    window.bkRevealRemaining = function () {
        if (window.BK_IS_SUB) return 0;
        return Math.max(0, (window.BK_REVEAL_MS || 30000) - (Date.now() - BK_PAGE_T0));
    };

    // Petit bouton « démo gratuite » à glisser dans un cadenas / encart premium.
    window.bkDemoButtonHTML = function () {
        if (window.BK_DEMO_AVAILABLE) {
            return '<button type="button" class="bk-lock-demo" onclick="bkStartDemo(this)">'
                 + '&#127881; Essayer Platine 5 min gratuites</button>';
        }
        if (!window.BK_IS_LOGGED) {
            return '<a class="bk-lock-demo" href="' + (window.BK_LOGIN_URL || '/login.php') + '">'
                 + '&#127881; Se connecter pour 5 min gratuites</a>';
        }
        return '';
    };

    window.bkLock = function (el, opts) {
        if (window.BK_IS_SUB || !el || el.dataset.bkLocked) return;
        opts = opts || {};
        el.dataset.bkLocked = '1';
        var label    = opts.label    || el.getAttribute('data-lock-label') || 'Contenu réservé aux abonnés';
        var planName = cap(opts.plan || el.getAttribute('data-lock-plan') || 'argent');

        var wrap = document.createElement('div');
        wrap.className = 'bk-lock';
        el.parentNode.insertBefore(wrap, el);
        wrap.appendChild(el);
        el.classList.add('bk-lock-content');

        var ov = document.createElement('div');
        ov.className = 'bk-lock-overlay';
        ov.innerHTML =
            '<div class="bk-lock-ico">&#128274;</div>' +
            '<div class="bk-lock-title">' + label + '</div>' +
            '<div class="bk-lock-sub">Réservé aux abonnés BOKONZI ' + planName +
                '. Débloquez tout le contenu : biographie complète, comparateur, statistiques et exports.</div>' +
            '<a class="bk-lock-cta" href="' + (window.BK_TARIFS_URL || '/tarifs') +
                '">Débloquer avec ' + planName + ' &rarr;</a>' +
            window.bkDemoButtonHTML();
        wrap.appendChild(ov);
    };

    window.bkLockInit = function (root) {
        if (window.BK_IS_SUB) return;
        var els = (root || document).querySelectorAll('.bk-premium');
        if (!els.length) return;
        var lockAll = function () { els.forEach(function (el) { window.bkLock(el); }); };
        // Non-abonné : on laisse lire le contenu en entier, puis on floute après 30 s.
        var wait = window.bkRevealRemaining();
        if (wait > 0) setTimeout(lockAll, wait); else lockAll();
    };

    /**
     * Masque RÉELLEMENT la suite d'un texte : on n'affiche que les `keep`
     * premiers caractères (preuve de l'info), le reste devient des « * »
     * (même nombre de caractères, espaces conservés). Le vrai texte n'est PAS
     * inséré dans le DOM → invisible même pour qui inspecte le code.
     * @returns {boolean} true si un masquage a eu lieu
     */
    // Applique RÉELLEMENT le masquage : ne garde que `keep` caractères lisibles,
    // remplace la suite par des « * » (le vrai texte n'est PAS inséré dans le DOM).
    function bkApplyMask(el, fullText, keep) {
        // Coupe proprement sur un espace pour ne pas tronquer un mot
        var cut = fullText.lastIndexOf(' ', keep);
        if (cut < keep * 0.6) cut = keep;
        var head  = fullText.slice(0, cut);
        var stars = fullText.slice(cut).replace(/[^\s]/g, '*'); // garde espaces/sauts de ligne

        el.textContent = '';
        el.appendChild(document.createTextNode(head + ' '));
        var span = document.createElement('span');
        span.className = 'bk-mask-stars';
        span.textContent = stars;
        el.appendChild(span);
        el.classList.add('bk-masked');

        if (el.parentNode && !el.parentNode.querySelector('.bk-mask-cta')) {
            var cta = document.createElement('div');
            cta.className = 'bk-mask-cta';
            // Seul le visiteur anonyme arrive ici : se connecter (gratuit) suffit
            // à lire toute la description.
            cta.innerHTML =
                '<span class="bk-mask-lock">&#128274; Connectez-vous gratuitement pour lire toute la description.</span>' +
                '<a class="bk-lock-cta" href="' + (window.BK_LOGIN_URL || '/login.php') +
                '">Se connecter gratuitement &rarr;</a>';
            el.parentNode.appendChild(cta);
        }
        return true;
    }

    // Affiche le texte intégral et retire tout masquage / CTA déjà posé.
    function bkShowFull(el, fullText) {
        el.textContent = fullText;
        el.classList.remove('bk-masked');
        var old = el.parentNode && el.parentNode.querySelector('.bk-mask-cta');
        if (old) old.remove();
    }

    window.bkMaskBio = function (el, fullText, keep) {
        if (!el) return false;
        fullText = String(fullText == null ? '' : fullText);
        // La bio est désormais entièrement lisible pour TOUT LE MONDE
        // (abonnés, membres gratuits ET visiteurs anonymes) : aucun floutage.
        if (el.__bkMaskTimer) { clearTimeout(el.__bkMaskTimer); el.__bkMaskTimer = null; }
        bkShowFull(el, fullText);
        return false;
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () { window.bkLockInit(); });
    } else {
        window.bkLockInit();
    }
})();
</script>
        <?php
    }
}
