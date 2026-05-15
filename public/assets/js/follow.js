/**
 * follow.js — Athlete + Club follow/unfollow system
 *
 * Extracted from index.php (lines ~9447-9634).
 *
 * Athlete follow:
 *   toggleFollow(id), confirmFollow(), openFollowModal(), closeFollowModal(),
 *   _checkFollowStatus()
 *
 * Club follow:
 *   toggleFollowClub(id, suffix), confirmFollowClub() (via confirmFollow hook),
 *   _checkClubFollowStatus(clubId, suffix)
 *
 * Dependencies:
 *   - Global BASE_API variable
 *   - DOM elements: #followOverlay, #followEmail, #followModalTitle,
 *     #followModalDesc, #followConfirmBtn, #btnFollow, #btnFollowClub{suffix}
 *   - localStorage key: bk_follow_email
 *   - API endpoint: /api/follow.php (POST=toggle, GET=status)
 */

/* ── Athlete Follow ─────────────────────────────────────────── */
(function() {
    var _followAthleteId = null;

    window.toggleFollow = function(athleteId) {
        _followAthleteId = athleteId;
        var email = localStorage.getItem('bk_follow_email') || '';
        if (!email) {
            document.getElementById('followModalTitle').innerHTML = '\u2661 Suivre cet athlete';
            document.getElementById('followModalDesc').textContent = 'Entrez votre email pour etre notifie des nouveaux resultats.';
            document.getElementById('followOverlay').removeAttribute('data-mode');
            document.getElementById('followOverlay').classList.add('active');
            var inp = document.getElementById('followEmail');
            inp.value = '';
            inp.focus();
            return;
        }
        _doFollow(athleteId, email);
    };

    window.confirmFollow = function() {
        var email = document.getElementById('followEmail').value.trim();
        if (!email || email.indexOf('@') === -1 || email.indexOf('.') === -1) {
            document.getElementById('followEmail').style.borderColor = '#f85149';
            return;
        }
        localStorage.setItem('bk_follow_email', email);
        closeFollowModal();
        if (_followAthleteId) _doFollow(_followAthleteId, email);
    };

    window.closeFollowModal = function() {
        var overlay = document.getElementById('followOverlay');
        overlay.classList.remove('active');
        overlay.removeAttribute('data-mode');
    };

    // Fermer modal avec Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeFollowModal();
    });
    // Fermer modal click exterieur
    document.getElementById('followOverlay').addEventListener('click', function(e) {
        if (e.target === this) closeFollowModal();
    });
    // Enter dans le champ email
    document.getElementById('followEmail').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') confirmFollow();
    });

    function _doFollow(athleteId, email) {
        var btn = document.getElementById('btnFollow');
        if (btn) btn.textContent = '...';
        fetch(BASE_API + '/follow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ athlete_id: athleteId, email: email })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { if (btn) btn.textContent = '\u2661 Suivre'; return; }
            _updateFollowBtn(data.following, data.count);
        })
        .catch(function() { if (btn) btn.textContent = '\u2661 Suivre'; });
    }

    function _updateFollowBtn(following, count) {
        var btn = document.getElementById('btnFollow');
        if (!btn) return;
        if (following) {
            btn.className = 'btn-follow following';
            btn.innerHTML = '\u2665 Suivi' + (count > 0 ? ' <span class="follow-count">' + count + '</span>' : '');
        } else {
            btn.className = 'btn-follow';
            btn.innerHTML = '\u2661 Suivre' + (count > 0 ? ' <span class="follow-count">' + count + '</span>' : '');
        }
    }

    // Init : verifier etat au chargement du profil
    function _checkFollowStatus() {
        var btn = document.getElementById('btnFollow');
        if (!btn) return;
        var athleteId = btn.getAttribute('onclick');
        if (!athleteId) return;
        var m = athleteId.match(/toggleFollow\((\d+)\)/);
        if (!m) return;
        var id = m[1];
        var email = localStorage.getItem('bk_follow_email') || '';
        var url = BASE_API + '/follow.php?athlete_id=' + id;
        if (email) url += '&email=' + encodeURIComponent(email);
        fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) _updateFollowBtn(data.following, data.count);
        })
        .catch(function() {});
    }
    _checkFollowStatus();
})();

/* ── Club Follow ────────────────────────────────────────────── */
(function() {
    var _clubFollowPending = null;

    window.toggleFollowClub = function(clubId, suffix) {
        _clubFollowPending = { clubId: clubId, suffix: suffix || '' };
        var email = localStorage.getItem('bk_follow_email') || '';
        if (!email) {
            document.getElementById('followModalTitle').innerHTML = '\u2661 Suivre ce club';
            document.getElementById('followModalDesc').textContent = 'Entrez votre email pour etre notifie des nouveaux resultats du club.';
            document.getElementById('followOverlay').classList.add('active');
            document.getElementById('followOverlay').setAttribute('data-mode', 'club');
            var inp = document.getElementById('followEmail');
            inp.value = '';
            inp.focus();
            return;
        }
        _doFollowClub(clubId, email, suffix || '');
    };

    // Hook into existing confirmFollow to support club mode
    var _origConfirmFollow = window.confirmFollow;
    window.confirmFollow = function() {
        var overlay = document.getElementById('followOverlay');
        if (overlay.getAttribute('data-mode') === 'club' && _clubFollowPending) {
            var email = document.getElementById('followEmail').value.trim();
            if (!email || email.indexOf('@') === -1 || email.indexOf('.') === -1) {
                document.getElementById('followEmail').style.borderColor = '#f85149';
                return;
            }
            localStorage.setItem('bk_follow_email', email);
            closeFollowModal();
            overlay.removeAttribute('data-mode');
            _doFollowClub(_clubFollowPending.clubId, email, _clubFollowPending.suffix);
            _clubFollowPending = null;
            return;
        }
        _origConfirmFollow();
    };

    function _doFollowClub(clubId, email, suffix) {
        var s = suffix || '';
        var btn = document.getElementById('btnFollowClub' + s);
        if (btn) btn.textContent = '...';
        fetch(BASE_API + '/follow.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            credentials: 'same-origin',
            body: JSON.stringify({ club_id: clubId, email: email })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) { if (btn) btn.innerHTML = '\u2661 Suivre'; return; }
            _updateClubFollowBtn(data.following, data.count, s);
        })
        .catch(function() { if (btn) btn.innerHTML = '\u2661 Suivre'; });
    }

    function _updateClubFollowBtn(following, count, suffix) {
        var s = suffix || '';
        var btn = document.getElementById('btnFollowClub' + s);
        if (!btn) return;
        if (following) {
            btn.className = 'btn-follow btn-follow-club following';
            btn.innerHTML = '\u2665 Suivi' + (count > 0 ? ' <span class="follow-count">' + count + '</span>' : '');
        } else {
            btn.className = 'btn-follow btn-follow-club';
            btn.innerHTML = '\u2661 Suivre' + (count > 0 ? ' <span class="follow-count">' + count + '</span>' : '');
        }
    }

    window._checkClubFollowStatus = function(clubId, suffix) {
        var s = suffix || '';
        var email = localStorage.getItem('bk_follow_email') || '';
        var url = BASE_API + '/follow.php?club_id=' + clubId;
        if (email) url += '&email=' + encodeURIComponent(email);
        fetch(url, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) _updateClubFollowBtn(data.following, data.count, s);
        })
        .catch(function() {});
    };
})();
