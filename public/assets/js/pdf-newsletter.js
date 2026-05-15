/**
 * PDF Download + Newsletter
 * Extracted from index.php — PDF generation (window.print) + newsletter subscription bar.
 *
 * Dependencies:
 *   - BASE_API global
 *   - DOM: #pdfOverlay, #pdfEmail, #btnPdf, #newsletterBar, #nlEmail, #nlBtn
 *   - localStorage: bk_follow_email, bk_pdf_email, bk_nl_done, bk_nl_closed
 *   - API: /subscribe.php, /send_profile_email.php, /athlete.php
 */
(function() {
    var _pdfAthleteId = null;
    var _pdfAthleteName = '';

    // ========== PDF ==========
    window.downloadPdf = function(athleteId, athleteName) {
        _pdfAthleteId = athleteId;
        _pdfAthleteName = athleteName;
        document.getElementById('pdfOverlay').classList.add('active');
        var inp = document.getElementById('pdfEmail');
        inp.value = localStorage.getItem('bk_follow_email') || localStorage.getItem('bk_pdf_email') || '';
        inp.focus();
    };

    window.confirmPdf = function() {
        var email = document.getElementById('pdfEmail').value.trim();
        if (!email || email.indexOf('@') === -1 || email.indexOf('.') === -1) {
            document.getElementById('pdfEmail').style.borderColor = '#f85149';
            return;
        }
        localStorage.setItem('bk_pdf_email', email);
        localStorage.setItem('bk_follow_email', email);
        closePdfModal();
        if (_pdfAthleteId) _generatePdf(_pdfAthleteId, _pdfAthleteName, email);
    };

    window.closePdfModal = function() {
        document.getElementById('pdfOverlay').classList.remove('active');
    };

    document.getElementById('pdfOverlay').addEventListener('click', function(e) {
        if (e.target === this) closePdfModal();
    });
    document.getElementById('pdfEmail').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') confirmPdf();
    });

    function _generatePdf(athleteId, athleteName, email) {
        // Enregistrer l'email
        fetch(BASE_API + '/subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, source: 'pdf', detail: athleteName + ' (id:' + athleteId + ')' })
        }).catch(function(){});

        // Envoyer la fiche par email
        fetch(BASE_API + '/send_profile_email.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, athlete_id: athleteId })
        }).catch(function(){});

        // Generer le PDF cote client
        var btn = document.getElementById('btnPdf');
        if (btn) btn.textContent = '...';

        fetch(BASE_API + '/athlete.php?id=' + athleteId)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data || !data.success) { if (btn) btn.innerHTML = '&#128196; PDF'; return; }
            _buildPdf(data, athleteName);
            if (btn) btn.innerHTML = '&#128196; PDF';
        })
        .catch(function() { if (btn) btn.innerHTML = '&#128196; PDF'; });
    }

    function _buildPdf(data, name) {
        var i = data.identite || {};
        var records = data.records || [];
        var clubs = data.clubs || [];
        var progressions = data.progressions || [];
        var medailles = data.medailles || [];

        var html = '';
        html += '<div style="font-family:Arial,sans-serif;max-width:800px;margin:0 auto;padding:40px;color:#222;">';
        html += '<h1 style="text-align:center;color:#1a1a2e;border-bottom:3px solid #ec4899;padding-bottom:12px;">' + (i.nom_complet || name) + '</h1>';

        // Identite
        html += '<table style="width:100%;margin:20px 0;border-collapse:collapse;">';
        if (i.sexe) html += '<tr><td style="padding:6px;font-weight:bold;width:150px;">Sexe</td><td style="padding:6px;">' + (i.sexe === 'M' ? 'Homme' : 'Femme') + '</td></tr>';
        if (i.categorie) html += '<tr><td style="padding:6px;font-weight:bold;">Categorie</td><td style="padding:6px;">' + i.categorie + '</td></tr>';
        if (i.nationalite) html += '<tr><td style="padding:6px;font-weight:bold;">Nationalite</td><td style="padding:6px;">' + i.nationalite + '</td></tr>';
        if (i.date_naissance) html += '<tr><td style="padding:6px;font-weight:bold;">Naissance</td><td style="padding:6px;">' + i.date_naissance + '</td></tr>';
        if (i.lieu_naissance) html += '<tr><td style="padding:6px;font-weight:bold;">Lieu</td><td style="padding:6px;">' + i.lieu_naissance + '</td></tr>';
        if (i.taille_cm) html += '<tr><td style="padding:6px;font-weight:bold;">Taille</td><td style="padding:6px;">' + i.taille_cm + ' cm</td></tr>';
        if (i.poids_kg) html += '<tr><td style="padding:6px;font-weight:bold;">Poids</td><td style="padding:6px;">' + i.poids_kg + ' kg</td></tr>';
        if (i.licence) html += '<tr><td style="padding:6px;font-weight:bold;">Licence</td><td style="padding:6px;">' + i.licence + '</td></tr>';
        html += '</table>';

        // Clubs
        if (clubs.length) {
            html += '<h2 style="color:#8b5cf6;margin-top:30px;">Clubs</h2>';
            html += '<ul>';
            clubs.forEach(function(c) { html += '<li>' + c.nom_club + ' (' + c.annee_debut + '-' + c.annee_fin + ')</li>'; });
            html += '</ul>';
        }

        // Medailles
        if (medailles.length) {
            html += '<h2 style="color:#f59e0b;margin-top:30px;">Medailles (' + medailles.length + ')</h2>';
            html += '<table style="width:100%;border-collapse:collapse;"><tr style="background:#f0f0f0;"><th style="padding:6px;text-align:left;">Type</th><th style="padding:6px;text-align:left;">Epreuve</th><th style="padding:6px;text-align:left;">Competition</th><th style="padding:6px;text-align:left;">Annee</th></tr>';
            medailles.forEach(function(m) {
                html += '<tr><td style="padding:6px;border-bottom:1px solid #eee;">' + m.type + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (m.epreuve||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (m.competition||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (m.annee||'') + '</td></tr>';
            });
            html += '</table>';
        }

        // Records
        if (records.length) {
            html += '<h2 style="color:#ec4899;margin-top:30px;">Records personnels (' + records.length + ')</h2>';
            html += '<table style="width:100%;border-collapse:collapse;"><tr style="background:#f0f0f0;"><th style="padding:6px;text-align:left;">Epreuve</th><th style="padding:6px;text-align:left;">Performance</th><th style="padding:6px;text-align:left;">Date</th><th style="padding:6px;text-align:left;">Lieu</th></tr>';
            records.forEach(function(r) {
                html += '<tr><td style="padding:6px;border-bottom:1px solid #eee;">' + r.epreuve + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (r.performance_brut||r.performance||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (r.date||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (r.lieu||'') + '</td></tr>';
            });
            html += '</table>';
        }

        // Progressions (top 20)
        if (progressions.length) {
            var top = progressions.slice(0, 20);
            html += '<h2 style="color:#3b82f6;margin-top:30px;">Progressions (top ' + top.length + '/' + progressions.length + ')</h2>';
            html += '<table style="width:100%;border-collapse:collapse;"><tr style="background:#f0f0f0;"><th style="padding:6px;text-align:left;">Epreuve</th><th style="padding:6px;text-align:left;">Performance</th><th style="padding:6px;text-align:left;">Annee</th><th style="padding:6px;text-align:left;">Lieu</th></tr>';
            top.forEach(function(p) {
                html += '<tr><td style="padding:6px;border-bottom:1px solid #eee;">' + p.epreuve + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (p.performance_brut||'') + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + p.annee + '</td><td style="padding:6px;border-bottom:1px solid #eee;">' + (p.lieu||'') + '</td></tr>';
            });
            html += '</table>';
        }

        html += '<p style="text-align:center;color:#999;margin-top:40px;font-size:12px;">Genere par Bokonzi — bokonzi.com</p>';
        html += '</div>';

        // Ouvrir dans une nouvelle fenetre pour imprimer en PDF
        var w = window.open('', '_blank');
        w.document.write('<!DOCTYPE html><html><head><title>' + (i.nom_complet || name) + ' — Bokonzi</title></head><body>' + html + '<script>setTimeout(function(){window.print();},500);<\/script></body></html>');
        w.document.close();
    }

    // ========== NEWSLETTER ==========
    window.subscribeNewsletter = function() {
        var email = document.getElementById('nlEmail').value.trim();
        if (!email || email.indexOf('@') === -1 || email.indexOf('.') === -1) {
            document.getElementById('nlEmail').style.borderColor = '#f85149';
            return;
        }
        localStorage.setItem('bk_follow_email', email);
        localStorage.setItem('bk_nl_done', '1');

        var btn = document.getElementById('nlBtn');
        btn.textContent = '...';

        fetch(BASE_API + '/subscribe.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email: email, source: 'newsletter' })
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            document.getElementById('nlEmail').style.display = 'none';
            btn.style.display = 'none';
            var bar = document.getElementById('newsletterBar');
            var ok = document.createElement('span');
            ok.className = 'nl-ok';
            ok.textContent = 'Inscrit ! Merci.';
            bar.insertBefore(ok, bar.querySelector('.nl-close'));
            setTimeout(function() { closeNewsletter(); }, 3000);
        })
        .catch(function() { btn.textContent = "S'inscrire"; });
    };

    window.closeNewsletter = function() {
        document.getElementById('newsletterBar').classList.remove('active');
        localStorage.setItem('bk_nl_closed', '1');
    };

    document.getElementById('nlEmail').addEventListener('keydown', function(e) {
        if (e.key === 'Enter') subscribeNewsletter();
    });

    // Afficher la banniere apres 30s OU scroll 50%, sauf si deja fermee/inscrit
    if (!localStorage.getItem('bk_nl_closed') && !localStorage.getItem('bk_nl_done')) {
        var _nlShown = false;
        function _showNl() {
            if (_nlShown) return;
            _nlShown = true;
            document.getElementById('newsletterBar').classList.add('active');
        }
        setTimeout(_showNl, 30000);
        window.addEventListener('scroll', function() {
            var pct = (window.scrollY + window.innerHeight) / document.documentElement.scrollHeight;
            if (pct > 0.5) _showNl();
        }, { passive: true });
    }
})();
