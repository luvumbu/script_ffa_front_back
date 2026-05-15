            <div class="bar-fill" style="width:<?= $w ?>%;background:<?= $color ?>;"></div>
            <span class="bar-val"><?= number_format($count) ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>

    <!-- Devices -->
    <div class="section">
        <h2>Ecrans (resolutions)</h2>
        <table>
            <thead><tr><th>Resolution</th><th>Nb</th><th>Type</th></tr></thead>
            <tbody>
            <?php foreach ($devices as $d):
                $w = (int)explode('x', $d['screen'])[0];
                $type = $w <= 480 ? 'Mobile' : ($w <= 1024 ? 'Tablette' : 'Desktop');
                $typeColor = $w <= 480 ? '#f59e0b' : ($w <= 1024 ? '#8b5cf6' : '#10b981');
            ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($d['screen']) ?></td>
                <td><?= $d['c'] ?></td>
                <td><span class="badge" style="background:<?= $typeColor ?>25;color:<?= $typeColor ?>;"><?= $type ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($devices)): ?><tr><td colspan="3" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Langues + Referrers -->
    <div class="section">
        <h2>Langues</h2>
        <table>
            <thead><tr><th>Langue</th><th>Nb</th></tr></thead>
            <tbody>
            <?php foreach ($languages as $l): ?>
            <tr><td><?= htmlspecialchars($l['lang']) ?></td><td><?= $l['c'] ?></td></tr>
            <?php endforeach; ?>
            <?php if (empty($languages)): ?><tr><td colspan="2" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 5 : SOURCES DE TRAFIC -->
<!-- ============================================================ -->
<div class="cols-2">
    <div class="section">
        <h2>Sources de trafic (referrers externes)</h2>
        <table>
            <thead><tr><th>Referrer</th><th>Visites</th></tr></thead>
            <tbody>
            <?php foreach ($referrers as $ref): ?>
            <tr>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($ref['referrer']) ?>"><?= htmlspecialchars($ref['referrer']) ?></td>
                <td class="green"><?= $ref['c'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($referrers)): ?><tr><td colspan="2" class="dim" style="text-align:center;">Aucun referrer externe</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Top pages aujourd'hui</h2>
        <table>
            <thead><tr><th>Page</th><th>Vues</th></tr></thead>
            <tbody>
            <?php foreach ($topPagesToday as $p): ?>
            <tr>
                <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($p['page']) ?>"><?= htmlspecialchars($p['page']) ?></td>
                <td class="green"><?= $p['c'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topPagesToday)): ?><tr><td colspan="2" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 6 : HISTORIQUE 7 JOURS -->
<!-- ============================================================ -->
<div class="section">
    <h2>Historique 7 derniers jours</h2>
    <table>
        <thead><tr><th>Date</th><th>Events</th><th>IPs uniques</th><th>Sessions</th></tr></thead>
        <tbody>
        <?php foreach ($weeklyLogs as $w): ?>
        <tr>
            <td><?= $w['d'] ?></td>
            <td class="green"><?= number_format($w['c']) ?></td>
            <td><?= number_format($w['ips']) ?></td>
            <td><?= number_format($w['sessions']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- ============================================================ -->
<!-- SECTION 7 : TOP IPS + SECURITE -->
<!-- ============================================================ -->
<div class="cols-2">
    <div class="section">
        <h2>Top IPs aujourd'hui (avec User-Agent)</h2>
        <table>
            <thead><tr><th>#</th><th>IP</th><th>Req</th><th>Premier</th><th>Dernier</th><th>User Agent</th></tr></thead>
            <tbody>
            <?php $i = 0; foreach ($topIpsToday as $tip): $i++;
                $isBot = preg_match('/bot|crawl|spider|slurp/i', $tip['ua'] ?? '');
            ?>
            <tr style="<?= $isBot ? 'opacity:0.5;' : '' ?>">
                <td><?= $i ?></td>
                <td class="mono"><?= htmlspecialchars($tip['ip']) ?></td>
                <td class="green"><?= number_format($tip['c']) ?></td>
                <td class="time"><?= substr($tip['first_seen'], 11) ?></td>
                <td class="time"><?= substr($tip['last_seen'], 11) ?></td>
                <td style="max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($tip['ua'] ?? '') ?>">
                    <?= $isBot ? '<span class="badge" style="background:#ef444430;color:#ef4444;">BOT</span> ' : '' ?><?= htmlspecialchars(mb_substr($tip['ua'] ?? '', 0, 80)) ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($topIpsToday)): ?><tr><td colspan="6" class="dim" style="text-align:center;">Aucune activite</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ERREURS JS -->
    <div class="section">
        <h2>Erreurs JavaScript (aujourd'hui) — <?= count($jsErrors) ?></h2>
        <table>
            <thead><tr><th>Heure</th><th>IP</th><th>Erreur</th><th>Fichier</th></tr></thead>
            <tbody>
            <?php foreach ($jsErrors as $err): ?>
            <tr>
                <td class="time"><?= substr($err['ts'], 11) ?></td>
                <td class="mono"><?= htmlspecialchars($err['ip']) ?></td>
                <td style="color:#ef4444;max-width:200px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?= htmlspecialchars($err['detail']) ?>"><?= htmlspecialchars(mb_substr($err['detail'], 0, 80)) ?></td>
                <td class="dim" style="max-width:150px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= htmlspecialchars(mb_substr($err['value'], 0, 60)) ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($jsErrors)): ?><tr><td colspan="4" class="dim" style="text-align:center;">Aucune erreur</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 8 : ENGAGEMENT — FOLLOWS + EMAILS -->
<!-- ============================================================ -->
<div class="cols-3">
    <div class="section">
        <h2>Derniers follows athletes (<?= $stats['athlete_follows'] ?>)</h2>
        <table>
            <thead><tr><th>Email</th><th>Athlete</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($lastFollowsAth as $f): ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($f['email']) ?></td>
                <td><a href="../index.php?page=profil&id=<?= (int)$f['athlete_id_ext'] ?>" style="color:#55efc4;">#<?= $f['athlete_id_ext'] ?></a></td>
                <td class="dim"><?= $f['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lastFollowsAth)): ?><tr><td colspan="3" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Derniers follows clubs (<?= $stats['club_follows'] ?>)</h2>
        <table>
            <thead><tr><th>Email</th><th>Club</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($lastFollowsClub as $f): ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($f['email']) ?></td>
                <td><?= (int)$f['club_id'] ?></td>
                <td class="dim"><?= $f['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lastFollowsClub)): ?><tr><td colspan="3" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Emails collectes (<?= $stats['email_subscribers'] ?>)</h2>
        <table>
            <thead><tr><th>Email</th><th>Source</th><th>Detail</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($lastSubs as $s): ?>
            <tr>
                <td class="mono"><?= htmlspecialchars($s['email']) ?></td>
                <td><span class="badge" style="background:#6c5ce720;color:#a29bfe;"><?= htmlspecialchars($s['source']) ?></span></td>
                <td class="dim"><?= htmlspecialchars(mb_substr($s['detail'], 0, 30)) ?></td>
                <td class="dim"><?= $s['created_at'] ?></td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($lastSubs)): ?><tr><td colspan="4" class="dim" style="text-align:center;">-</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 9 : UTILISATEURS -->
<!-- ============================================================ -->
<div class="cols-2">
    <div class="section">
        <h2>Derniers inscrits (<?= $stats['users'] ?> total)</h2>
        <table>
            <thead><tr><th>#</th><th>Email</th><th>Nom</th><th>Role</th><th>Athlete</th></tr></thead>
            <tbody>
            <?php foreach ($lastUsers as $u): ?>
            <tr>
                <td><?= $u['id_user'] ?></td>
                <td class="mono"><?= htmlspecialchars($u['email']) ?></td>
                <td><?= htmlspecialchars(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? '')) ?></td>
                <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
                <td><?= $u['id_athlete'] ? '<a href="../index.php?page=profil&id=' . $u['id_athlete'] . '" style="color:#55efc4;">#' . $u['id_athlete'] . '</a>' : '-' ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Repartition par role</h2>
        <div class="bar-chart">
        <?php $maxRole = max(1, !empty($usersByRole) ? max($usersByRole) : 1);
        foreach ($usersByRole as $role => $count):
            $colors = ['admin' => '#e11d48', 'athlete' => '#10b981', 'coach' => '#6c5ce7', 'club' => '#f59e0b'];
            $color = $colors[$role] ?? '#5a6580';
            $w = round(($count / $maxRole) * 100);
        ?>
        <div class="bar-row">
            <span class="bar-label"><?= $role ?></span>
            <div class="bar-fill" style="width:<?= $w ?>%;background:<?= $color ?>;"></div>
            <span class="bar-val"><?= number_format($count) ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 9B : ACTIVITE PAR UTILISATEUR — INTERACTIF -->
<!-- ============================================================ -->
<div class="section">
    <h2 style="color:#a29bfe;font-size:16px;border-color:#a29bfe40;">&#128100; Activite par utilisateur (<?= count($allUsers) ?> users)</h2>

    <!-- Liste utilisateurs cliquable -->
    <div style="margin-bottom:12px;">
        <input type="text" id="userFilterInput" placeholder="Filtrer par email ou nom..."
            oninput="_filterUsers()" style="width:300px;padding:8px 12px;background:#0d1117;border:1px solid #1e2a3a;border-radius:6px;color:#c9d1d9;font-size:13px;">
    </div>

    <?php $panelAccessList = getPanelAccessList(); ?>
    <table>
        <thead><tr><th>#</th><th>User</th><th>Role</th><th>Derniere connexion</th><th>Req/jour</th><th>Athletes suivis</th><th>Clubs suivis</th><th>Session</th><th>Panel</th><th>Details</th></tr></thead>
        <tbody id="userListBody">
        <?php foreach ($allUsers as $idx => $u): ?>
        <tr class="user-row" data-filter="<?= htmlspecialchars(strtolower(($u['email'] ?? '') . ' ' . ($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?>">
            <td><?= $u['id_user'] ?></td>
            <td>
                <?php if (!empty($u['picture'])): ?><img src="<?= htmlspecialchars($u['picture']) ?>" style="width:20px;height:20px;border-radius:50%;vertical-align:middle;margin-right:4px;" referrerpolicy="no-referrer"><?php endif; ?>
                <span class="mono" style="font-size:12px;"><?= htmlspecialchars($u['email']) ?></span>
                <?php if ($u['prenom'] || $u['nom']): ?><br><span style="color:#8b949e;font-size:11px;"><?= htmlspecialchars(trim(($u['prenom'] ?? '') . ' ' . ($u['nom'] ?? ''))) ?></span><?php endif; ?>
            </td>
            <td><span class="badge badge-<?= $u['role'] ?>"><?= $u['role'] ?></span></td>
            <td style="font-size:12px;color:#8b949e;"><?= $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '-' ?></td>
            <td style="text-align:center;"><?= $u['req_today'] > 0 ? '<span style="color:#f59e0b;font-weight:700;">' . $u['req_today'] . '</span>' : '<span style="color:#484f58;">0</span>' ?></td>
            <td style="text-align:center;"><?= $u['nb_follows_ath'] > 0 ? '<span style="color:#a29bfe;font-weight:700;">' . $u['nb_follows_ath'] . '</span>' : '<span style="color:#484f58;">0</span>' ?></td>
            <td style="text-align:center;"><?= $u['nb_follows_club'] > 0 ? '<span style="color:#34d399;font-weight:700;">' . $u['nb_follows_club'] . '</span>' : '<span style="color:#484f58;">0</span>' ?></td>
            <td style="text-align:center;"><?= $u['sessions_active'] > 0 ? '<span style="color:#55efc4;">Active</span>' : '<span style="color:#484f58;">-</span>' ?></td>
            <td style="text-align:center;">
            <?php $hasAccess = isset($panelAccessList[strtolower($u['email'])]); ?>
            <?php if ($_isSA): ?>
                <?php if ($hasAccess): ?>
                    <form method="POST" style="display:inline;"><input type="hidden" name="panel_action" value="revoke"><input type="hidden" name="email" value="<?= htmlspecialchars($u['email']) ?>"><button type="submit" style="padding:3px 8px;background:#ef444420;border:1px solid #ef4444;border-radius:6px;color:#ef4444;font-size:10px;cursor:pointer;" title="Retirer l'acces">&#10003; Acces</button></form>
                <?php else: ?>
                    <form method="POST" style="display:inline;"><input type="hidden" name="panel_action" value="grant"><input type="hidden" name="email" value="<?= htmlspecialchars($u['email']) ?>"><button type="submit" style="padding:3px 8px;background:#1e2a3a;border:1px solid #30363d;border-radius:6px;color:#484f58;font-size:10px;cursor:pointer;" title="Donner acces au panel">Donner</button></form>
                <?php endif; ?>
            <?php else: ?>
                <?= $hasAccess ? '<span style="color:#55efc4;font-size:10px;">&#10003;</span>' : '' ?>
            <?php endif; ?>
            </td>
            <td><button onclick="_toggleUserDetail(<?= $u['id_user'] ?>)" style="padding:3px 10px;background:#6c5ce720;border:1px solid #6c5ce7;border-radius:6px;color:#a29bfe;font-size:11px;cursor:pointer;">Voir</button></td>
        </tr>
        <!-- Drawer detail cache -->
        <tr id="userDetail_<?= $u['id_user'] ?>" style="display:none;">
            <td colspan="10" style="padding:16px;background:#0d1117;">
                <div style="display:flex;gap:24px;flex-wrap:wrap;">
                    <!-- Infos -->
                    <div style="flex:1;min-width:200px;">
                        <h4 style="color:#f0f6fc;font-size:13px;margin-bottom:8px;">Infos</h4>
                        <div style="font-size:12px;color:#8b949e;line-height:1.8;">
                            Provider : <span style="color:#c9d1d9;"><?= $u['oauth_provider'] ?: 'email' ?></span><br>
                            Google ID : <span style="color:#c9d1d9;"><?= $u['google_id'] ? substr($u['google_id'], 0, 10) . '...' : '-' ?></span><br>
                            Locale : <span style="color:#c9d1d9;"><?= $u['locale'] ?: '-' ?></span><br>
                            Inscrit : <span style="color:#c9d1d9;"><?= $u['date_creation'] ? date('d/m/Y H:i', strtotime($u['date_creation'])) : '-' ?></span>
                        </div>
                    </div>

                    <!-- Athletes suivis -->
                    <div style="flex:1;min-width:250px;">
                        <h4 style="color:#a29bfe;font-size:13px;margin-bottom:8px;">&#9889; Athletes suivis (<?= count($userFollowsDetail[$u['id_user']]['athletes']) ?>)</h4>
                        <?php if (empty($userFollowsDetail[$u['id_user']]['athletes'])): ?>
                            <span style="color:#484f58;font-size:12px;">Aucun</span>
                        <?php else: ?>
                            <?php foreach ($userFollowsDetail[$u['id_user']]['athletes'] as $fa): ?>
                            <div style="font-size:12px;padding:3px 0;border-bottom:1px solid #1e2a3a;">
                                <a href="../index.php?page=profil&id=<?= (int)$fa['athlete_id_ext'] ?>" style="color:#58a6ff;text-decoration:none;"><?= htmlspecialchars($fa['nom_complet_athlete'] ?: '#' . $fa['athlete_id_ext']) ?></a>
                                <span style="color:#484f58;font-size:10px;margin-left:6px;"><?= $fa['created_at'] ? date('d/m', strtotime($fa['created_at'])) : '' ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Clubs suivis -->
                    <div style="flex:1;min-width:200px;">
                        <h4 style="color:#34d399;font-size:13px;margin-bottom:8px;">&#127965; Clubs suivis (<?= count($userFollowsDetail[$u['id_user']]['clubs']) ?>)</h4>
                        <?php if (empty($userFollowsDetail[$u['id_user']]['clubs'])): ?>
                            <span style="color:#484f58;font-size:12px;">Aucun</span>
                        <?php else: ?>
                            <?php foreach ($userFollowsDetail[$u['id_user']]['clubs'] as $fc): ?>
                            <div style="font-size:12px;padding:3px 0;border-bottom:1px solid #1e2a3a;">
                                <a href="../index.php?page=recherche&club=<?= urlencode(rtrim($fc['nom_club'] ?? '', '* ')) ?>" style="color:#58a6ff;text-decoration:none;"><?= htmlspecialchars($fc['nom_club'] ?: '#' . $fc['club_id']) ?></a>
                                <span style="color:#484f58;font-size:10px;margin-left:6px;"><?= $fc['created_at'] ? date('d/m', strtotime($fc['created_at'])) : '' ?></span>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Historique recherches -->
                    <div style="flex:2;min-width:350px;">
                        <h4 style="color:#f59e0b;font-size:13px;margin-bottom:8px;">&#128337; Historique recherches (<?= count($userSearchHistory[$u['id_user']]) ?>)</h4>
                        <?php if (empty($userSearchHistory[$u['id_user']])): ?>
                            <span style="color:#484f58;font-size:12px;">Aucun historique</span>
                        <?php else: ?>
                            <div style="max-height:250px;overflow-y:auto;">
                            <?php
                            $tbg = ['athlete'=>'#6c5ce720','club'=>'#34d39920','epreuve'=>'#f59e0b20','ville'=>'#3b82f620','general'=>'#8b949e20'];
                            $tcl = ['athlete'=>'#a29bfe','club'=>'#34d399','epreuve'=>'#f59e0b','ville'=>'#60a5fa','general'=>'#8b949e'];
                            foreach ($userSearchHistory[$u['id_user']] as $sh): ?>
                            <div style="font-size:11px;padding:4px 0;border-bottom:1px solid #1e2a3a10;display:flex;gap:8px;align-items:center;">
                                <span style="background:<?= $tbg[$sh['search_type']] ?? $tbg['general'] ?>;color:<?= $tcl[$sh['search_type']] ?? $tcl['general'] ?>;padding:1px 6px;border-radius:8px;font-size:10px;white-space:nowrap;"><?= $sh['search_type'] ?></span>
                                <span style="color:#c9d1d9;flex:1;"><?= htmlspecialchars($sh['entity_name'] ?: $sh['query_text'] ?: '-') ?></span>
                                <span style="color:#484f58;font-size:10px;white-space:nowrap;"><?= date('d/m H:i', strtotime($sh['created_at'])) ?></span>
                            </div>
                            <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($_isSA): ?>
    <!-- Recap acces panel -->
    <div id="panelAccess" style="margin-top:20px;padding:16px;background:#0d1117;border:1px solid #30363d;border-radius:8px;">
        <h3 style="color:#f59e0b;font-size:14px;margin-bottom:12px;">&#128273; Acces au panel (<?= count($panelAccessList) ?> emails autorises)</h3>
        <?php if (empty($panelAccessList)): ?>
            <p style="color:#484f58;font-size:12px;">Aucun email autorise. Utilisez les boutons "Donner" ci-dessus.</p>
        <?php else: ?>
            <?php foreach ($panelAccessList as $em => $info): ?>
            <div style="display:flex;align-items:center;gap:10px;padding:6px 0;border-bottom:1px solid #1e2a3a;">
                <span style="color:#c9d1d9;font-size:13px;flex:1;" class="mono"><?= htmlspecialchars($em) ?></span>
                <span style="color:#484f58;font-size:10px;">depuis <?= $info['added'] ?? '?' ?></span>
                <form method="POST" style="display:inline;"><input type="hidden" name="panel_action" value="revoke"><input type="hidden" name="email" value="<?= htmlspecialchars($em) ?>"><button type="submit" style="padding:2px 8px;background:#ef444420;border:1px solid #ef4444;border-radius:4px;color:#ef4444;font-size:10px;cursor:pointer;">Retirer</button></form>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Ajout manuel -->
        <form method="POST" style="margin-top:12px;display:flex;gap:8px;align-items:center;">
            <input type="hidden" name="panel_action" value="grant">
            <input type="email" name="email" placeholder="email@exemple.com" required style="flex:1;max-width:300px;padding:6px 10px;background:#161b22;border:1px solid #1e2a3a;border-radius:6px;color:#c9d1d9;font-size:12px;">
            <button type="submit" style="padding:6px 14px;background:#f59e0b20;border:1px solid #f59e0b;border-radius:6px;color:#f59e0b;font-size:12px;cursor:pointer;">Ajouter</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<script>
function _toggleUserDetail(uid) {
    var row = document.getElementById('userDetail_' + uid);
    if (!row) return;
    row.style.display = row.style.display === 'none' ? '' : 'none';
}
function _filterUsers() {
    var q = document.getElementById('userFilterInput').value.toLowerCase();
    var rows = document.querySelectorAll('.user-row');
    rows.forEach(function(r) {
        var match = r.getAttribute('data-filter').indexOf(q) !== -1;
        r.style.display = match ? '' : 'none';
        // Cacher aussi le drawer detail si filtre
        var next = r.nextElementSibling;
        if (next && next.id && next.id.startsWith('userDetail_') && !match) next.style.display = 'none';
    });
}
</script>

<!-- ============================================================ -->
<!-- SECTION 10 : BASE DE DONNEES + SERVEUR -->
<!-- ============================================================ -->
<div class="cols-2">
    <div class="section">
        <h2>Tables BDD — <?= fmtSize($dbSize) ?></h2>
        <table>
            <thead><tr><th>Table</th><th>Lignes</th><th>Taille</th></tr></thead>
            <tbody>
            <?php foreach ($tableSizes as $ts): ?>
            <tr>
                <td class="mono"><?= $ts['table_name'] ?></td>
                <td><?= number_format((int)$ts['table_rows']) ?></td>
                <td class="dim"><?= fmtSize((float)$ts['size']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Serveur</h2>
        <table>
            <tbody>
            <tr><td>PHP</td><td class="mono"><?= $phpVersion ?></td></tr>
            <tr><td>MySQL</td><td class="mono"><?= $mysqlVersion ?></td></tr>
            <tr><td>Disque libre</td><td class="mono"><?= $diskFree ? fmtSize($diskFree) : 'N/A' ?></td></tr>
            <tr><td>Disque total</td><td class="mono"><?= $diskTotal ? fmtSize($diskTotal) : 'N/A' ?></td></tr>
            <tr><td>Disque utilise</td><td class="mono"><?= ($diskTotal && $diskFree) ? round(($diskTotal - $diskFree) / $diskTotal * 100, 1) . '%' : 'N/A' ?></td></tr>
            <tr><td>Cache fichiers</td><td class="mono"><?= count($cacheFiles) ?> fichiers (<?= fmtSize($cacheSize) ?>)</td></tr>
            <tr><td>Cache plus ancien</td><td class="dim"><?= $oldestCache ? date('d/m/Y H:i', $oldestCache) : '-' ?></td></tr>
            <tr><td>Cache plus recent</td><td class="dim"><?= $newestCache ? date('d/m/Y H:i', $newestCache) : '-' ?></td></tr>
            <tr><td>IP Tracker mois dispo</td><td class="mono"><?= implode(', ', $ipLogMonths) ?: '-' ?></td></tr>
            <tr><td>Heure serveur</td><td class="mono"><?= date('Y-m-d H:i:s') ?></td></tr>
            </tbody>
        </table>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 11 : DONNEES ATHLETES -->
<!-- ============================================================ -->
<div class="section">
    <h2>Donnees athletes</h2>
    <div class="grid" style="padding:0;">
        <div class="card"><div class="num"><?= number_format($stats['athlete_records']) ?></div><div class="label">Records</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_resultats']) ?></div><div class="label">Resultats</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_medailles']) ?></div><div class="label">Medailles</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_podiums']) ?></div><div class="label">Podiums</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_selections']) ?></div><div class="label">Selections</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_progressions']) ?></div><div class="label">Progressions</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_niveaux']) ?></div><div class="label">Niveaux</div></div>
        <div class="card"><div class="num"><?= number_format($stats['athlete_clubs']) ?></div><div class="label">Clubs-Athletes</div></div>
    </div>
</div>

<!-- ============================================================ -->
<!-- SECTION 12 : ANALYTICS VUES (Profils & Clubs) — INTERACTIF -->
<!-- ============================================================ -->
<?php if ($hasVuesTables): ?>
<script>
var VUES_DATA = <?= json_encode([
    'totals' => [
        'athVues' => $totalVuesAthletes, 'clubVues' => $totalVuesClubs,
        'nbAth' => $nbAthVus, 'nbClubs' => $nbClubsVus,
        'ipsAth' => $uniqueVueIpsAth, 'ipsClub' => $uniqueVueIpsClub,
        'todayAth' => $vuesTodayAth, 'todayClub' => $vuesTodayClub
    ],
    'parJour' => $vuesParJour,
    'topAthletes' => $topVuesAthletes,
    'topClubs' => $topVuesClubs,
    'lastAth' => $lastVuesAthletes,
    'lastClub' => $lastVuesClubs,
    'topIps' => $topVuesIps,
    'rateLimited' => $rateLimitedIps,
    'rateLimitMax' => $rateLimitDailyLimit,
    'bannedIps' => $bannedIps
], JSON_UNESCAPED_UNICODE) ?>;
</script>

<!-- KPI Cards Vues (PHP statique) -->
<div class="section"><h2 style="color:#f59e0b;font-size:16px;border-color:#f59e0b40;">&#128065; Analytics Vues — Profils &amp; Clubs</h2></div>
<div class="grid">
    <div class="card" style="border-color:#f59e0b40;"><div class="num warn"><?= number_format($totalVuesAthletes) ?></div><div class="label">Vues profils (total)</div><div class="sub"><?= $nbAthVus ?> athletes</div></div>
    <div class="card" style="border-color:#8b5cf640;"><div class="num info"><?= number_format($totalVuesClubs) ?></div><div class="label">Vues clubs (total)</div><div class="sub"><?= $nbClubsVus ?> clubs</div></div>
    <div class="card" style="border-color:#10b98140;"><div class="num green"><?= number_format($uniqueVueIpsAth) ?></div><div class="label">IPs uniques (profils)</div></div>
    <div class="card" style="border-color:#10b98140;"><div class="num green"><?= number_format($uniqueVueIpsClub) ?></div><div class="label">IPs uniques (clubs)</div></div>
    <div class="card" style="border-color:#3b82f640;"><div class="num" style="color:#60a5fa;"><?= number_format($vuesTodayAth) ?></div><div class="label">Profils vus aujourd'hui</div></div>
    <div class="card" style="border-color:#3b82f640;"><div class="num" style="color:#60a5fa;"><?= number_format($vuesTodayClub) ?></div><div class="label">Clubs vus aujourd'hui</div></div>
    <div class="card" style="border-color:#ec489940;"><div class="num pink"><?= number_format($totalVuesAthletes + $totalVuesClubs) ?></div><div class="label">Total combiné</div></div>
    <div class="card" style="border-color:#ec489940;"><div class="num pink"><?= number_format($vuesTodayAth + $vuesTodayClub) ?></div><div class="label">Aujourd'hui (total)</div></div>
</div>

<!-- Chart 14 jours (cliquable) -->
<div class="section">
    <h2>Historique 14 jours <span style="font-size:11px;color:#5a6580;font-weight:400;">— cliquez sur une barre pour le detail</span></h2>
    <div style="height:200px;position:relative;"><canvas id="vueChart14"></canvas></div>
</div>

<!-- Onglets interactifs -->
<div class="vue-tabs" id="vueTabs">
    <div class="vue-tab active" onclick="_vueTab('athletes')">Athletes</div>
    <div class="vue-tab" onclick="_vueTab('clubs')">Clubs</div>
    <div class="vue-tab" onclick="_vueTab('ips')">IPs</div>
    <div class="vue-tab" onclick="_vueTab('live')"><span class="live-dot"></span>Temps reel</div>
    <div class="vue-tab" onclick="_vueTab('blocked')" style="color:#ef4444;">&#9888; Bloques</div>
</div>
<div class="vue-tab-body" id="vueTabBody"></div>

<!-- Drawer -->
<div class="vue-overlay" id="vueOverlay" onclick="_vueClose()"></div>
<div class="vue-drawer" id="vueDrawer">
    <div class="vue-drawer-head">
        <span class="vd-title" id="vueDrawerTitle"></span>
        <button class="vd-close" onclick="_vueClose()">&times;</button>
    </div>
    <div class="vd-body" id="vueDrawerBody"></div>
</div>

<script>
(function(){
var D = VUES_DATA;
var _curTab = 'athletes';
var _sortKey = 'vues', _sortDir = 'desc', _filter = '';

function _esc(s) { var d = document.createElement('div'); d.textContent = s||''; return d.innerHTML; }
function _fmtDate(s) { if (!s) return '-'; var d = new Date(s); return (d.getDate()<10?'0':'')+d.getDate()+'/'+(d.getMonth()<9?'0':'')+(d.getMonth()+1)+' '+(d.getHours()<10?'0':'')+d.getHours()+':'+(d.getMinutes()<10?'0':'')+d.getMinutes(); }
function _fmtDateFull(s) { if (!s) return '-'; var d = new Date(s); return (d.getDate()<10?'0':'')+d.getDate()+'/'+(d.getMonth()<9?'0':'')+(d.getMonth()+1)+'/'+d.getFullYear()+' '+(d.getHours()<10?'0':'')+d.getHours()+':'+(d.getMinutes()<10?'0':'')+d.getMinutes()+':'+(d.getSeconds()<10?'0':'')+d.getSeconds(); }

// === CHART 14 JOURS ===
function _vueRenderChart() {
    var pj = D.parJour.slice().reverse();
    var labels = pj.map(function(d) { return d.d.substring(5); });
    var athData = pj.map(function(d) { return parseInt(d.ath)||0; });
    var clubData = pj.map(function(d) { return parseInt(d.club)||0; });
    var ctx = document.getElementById('vueChart14');
    if (!ctx) return;
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                { label: 'Profils', data: athData, backgroundColor: '#f59e0b', borderRadius: 4 },
                { label: 'Clubs', data: clubData, backgroundColor: '#8b5cf6', borderRadius: 4 }
            ]
        },
        options: {
            responsive: true, maintainAspectRatio: false,
            scales: {
                x: { stacked: true, grid: { color: '#1e2a3a' }, ticks: { color: '#5a6580', font: { size: 10 } } },
                y: { stacked: true, grid: { color: '#1e2a3a' }, ticks: { color: '#5a6580' }, beginAtZero: true }
            },
            plugins: { legend: { labels: { color: '#8b949e', boxWidth: 12 } } },
            onClick: function(evt, elems) {
                if (elems.length > 0) {
                    var idx = elems[0].index;
                    _vueShowDay(pj[idx].d);
                }
            }
        }
    });
}

// === TABS ===
window._vueTab = function(tab) {
    _curTab = tab; _filter = ''; _sortKey = 'vues'; _sortDir = 'desc';
    var tabs = document.querySelectorAll('.vue-tab');
    tabs.forEach(function(t) { t.classList.remove('active'); });
    tabs[['athletes','clubs','ips','live','blocked'].indexOf(tab)].classList.add('active');
    _vueRenderTab();
};

function _vueRenderTab() {
    var el = document.getElementById('vueTabBody');
    if (_curTab === 'athletes') _vueRenderAthletes(el);
    else if (_curTab === 'clubs') _vueRenderClubs(el);
    else if (_curTab === 'ips') _vueRenderIps(el);
    else if (_curTab === 'blocked') _vueRenderBlocked(el);
    else _vueRenderLive(el);
}

function _sortHeader(key, label) {
    var cls = 'vue-sort' + (_sortKey === key ? ' ' + _sortDir : '');
    return '<th class="'+cls+'" onclick="_vueSortBy(\''+key+'\')">'+label+'</th>';
}
window._vueSortBy = function(key) {
    if (_sortKey === key) _sortDir = _sortDir === 'desc' ? 'asc' : 'desc';
    else { _sortKey = key; _sortDir = 'desc'; }
    _vueRenderTab();
};
window._vueFilter = function(q) { _filter = q.toLowerCase(); _vueRenderTab(); };

function _sortItems(arr, key) {
    return arr.slice().sort(function(a, b) {
        var va = key === 'nom' ? (a[key]||'').toLowerCase() : (parseFloat(a[key])||0);
        var vb = key === 'nom' ? (b[key]||'').toLowerCase() : (parseFloat(b[key])||0);
        if (typeof va === 'string') return _sortDir === 'asc' ? va.localeCompare(vb) : vb.localeCompare(va);
        return _sortDir === 'asc' ? va - vb : vb - va;
    });
}

// === ATHLETES TAB (simplifie : Nom + Club + Vues + IPs) ===
function _vueRenderAthletes(el) {
    var items = D.topAthletes;
    if (_filter) items = items.filter(function(a) {
        return (a.nom||'').toLowerCase().indexOf(_filter) >= 0 || (a.club||'').toLowerCase().indexOf(_filter) >= 0
            || (a.nationalite_athlete||'').toLowerCase().indexOf(_filter) >= 0;
    });
    items = _sortItems(items, _sortKey);
    var h = '<input type="text" class="vue-search" placeholder="&#128269; Rechercher un athlete, club, nationalite..." oninput="_vueFilter(this.value)" value="'+_esc(_filter)+'">';
    h += '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">'+items.length+' athlete'+(items.length>1?'s':'')+' — cliquez sur une ligne pour voir le detail</p>';
    h += '<div style="max-height:520px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr>'
        + '<th style="width:40px;">#</th>'
        + _sortHeader('nom','Nom Prenom')
        + '<th>Club</th>'
        + _sortHeader('vues','Vues')
        + _sortHeader('ips_uniques','IPs uniques')
        + '</tr></thead><tbody>';
    if (!items.length) h += '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune donnee</td></tr>';
    items.forEach(function(a, i) {
        h += '<tr class="vue-row" onclick="_vueShowAth('+i+')">'
            + '<td class="dim" style="font-size:12px;">'+(i+1)+'</td>'
            + '<td style="font-weight:700;color:#e2e8f0;font-size:14px;">'+_esc(a.nom)+'<br><span style="font-size:10px;color:#5a6580;font-weight:400;">'+(a.sexe_athlete||'')+' '+_esc(a.categorie_athlete)+' '+_esc(a.nationalite_athlete)+'</span></td>'
            + '<td style="color:#8b949e;font-size:12px;">'+_esc((a.club||'').replace(/\*\s*$/,''))+'</td>'
            + '<td style="text-align:center;"><span style="background:#f59e0b20;color:#f59e0b;font-weight:800;font-size:16px;padding:4px 12px;border-radius:8px;">'+a.vues+'</span></td>'
            + '<td style="text-align:center;"><span style="color:#55efc4;font-weight:700;font-size:14px;">'+(a.ips_uniques||0)+'</span></td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';
    el.innerHTML = h;
}

// === CLUBS TAB (simplifie : Nom + Vues + IPs) ===
function _vueRenderClubs(el) {
    var items = D.topClubs;
    if (_filter) items = items.filter(function(c) {
        return (c.nom_club||'').toLowerCase().indexOf(_filter) >= 0;
    });
    items = _sortItems(items, _sortKey === 'nom' ? 'nom_club' : _sortKey);
    var h = '<input type="text" class="vue-search" placeholder="&#128269; Rechercher un club..." oninput="_vueFilter(this.value)" value="'+_esc(_filter)+'">';
    h += '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">'+items.length+' club'+(items.length>1?'s':'')+' — cliquez sur une ligne pour voir le detail</p>';
    h += '<div style="max-height:520px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr>'
        + '<th style="width:40px;">#</th>'
        + _sortHeader('nom_club','Nom du Club')
        + _sortHeader('nb_athletes','Athletes')
        + _sortHeader('vues','Vues')
        + _sortHeader('ips_uniques','IPs uniques')
        + '</tr></thead><tbody>';
    if (!items.length) h += '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune donnee</td></tr>';
    items.forEach(function(c, i) {
        h += '<tr class="vue-row" onclick="_vueShowClub('+i+')">'
            + '<td class="dim" style="font-size:12px;">'+(i+1)+'</td>'
            + '<td style="font-weight:700;color:#e2e8f0;font-size:14px;">'+_esc(c.nom_club)+'</td>'
            + '<td style="color:#8b949e;text-align:center;">'+(c.nb_athletes||0)+'</td>'
            + '<td style="text-align:center;"><span style="background:#f59e0b20;color:#f59e0b;font-weight:800;font-size:16px;padding:4px 12px;border-radius:8px;">'+c.vues+'</span></td>'
            + '<td style="text-align:center;"><span style="color:#55efc4;font-weight:700;font-size:14px;">'+(c.ips_uniques||0)+'</span></td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';
    el.innerHTML = h;
}

// === IPS TAB (simplifie) ===
function _vueRenderIps(el) {
    var items = D.topIps;
    if (_filter) items = items.filter(function(ip) { return ip.ip.indexOf(_filter) >= 0; });
    var h = '<input type="text" class="vue-search" placeholder="&#128269; Rechercher une IP..." oninput="_vueFilter(this.value)" value="'+_esc(_filter)+'">';
    h += '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">'+items.length+' IP'+(items.length>1?'s':'')+' — cliquez pour voir la navigation complete</p>';
    h += '<div style="max-height:520px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr><th>Adresse IP</th><th style="text-align:center;">Profils</th><th style="text-align:center;">Clubs</th><th style="text-align:center;">Total</th><th>Derniere visite</th></tr></thead><tbody>';
    if (!items.length) h += '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune donnee</td></tr>';
    items.forEach(function(ip, i) {
        var total = (parseInt(ip.nb_profils)||0) + (parseInt(ip.nb_clubs)||0);
        h += '<tr class="vue-row" onclick="_vueShowIp('+i+')">'
            + '<td class="mono" style="font-weight:700;font-size:13px;">'+_esc(ip.ip)+'</td>'
            + '<td style="text-align:center;"><span style="background:#f59e0b20;color:#f59e0b;font-weight:700;padding:3px 10px;border-radius:6px;">'+(ip.nb_profils||0)+'</span></td>'
            + '<td style="text-align:center;"><span style="background:#8b5cf620;color:#a78bfa;font-weight:700;padding:3px 10px;border-radius:6px;">'+(ip.nb_clubs||0)+'</span></td>'
            + '<td style="text-align:center;"><span style="color:#55efc4;font-weight:800;font-size:16px;">'+total+'</span></td>'
            + '<td class="time" style="font-size:12px;">'+_fmtDate(ip.last_vue)+'</td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';
    el.innerHTML = h;
}

// === LIVE TAB (simplifie) ===
function _vueRenderLive(el) {
    var all = [];
    (D.lastAth||[]).forEach(function(v) { all.push({type:'athlete', ip:v.ip, nom:v.nom||'?', id:v.athlete_id_ext, date:v.created_at}); });
    (D.lastClub||[]).forEach(function(v) { all.push({type:'club', ip:v.ip, nom:v.nom_club||'?', id:v.club_id, date:v.created_at}); });
    all.sort(function(a,b) { return (b.date||'').localeCompare(a.date||''); });
    var h = '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">'+all.length+' dernieres vues — profils + clubs melanges</p>';
    h += '<div style="max-height:550px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr><th>Date/Heure</th><th>IP</th><th>Type</th><th>Nom</th></tr></thead><tbody>';
    if (!all.length) h += '<tr><td colspan="4" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune vue</td></tr>';
    all.forEach(function(v) {
        var typeColor = v.type === 'athlete' ? '#f59e0b' : '#8b5cf6';
        var typeBg = v.type === 'athlete' ? '#f59e0b20' : '#8b5cf620';
        var typeLabel = v.type === 'athlete' ? 'Profil' : 'Club';
        var link = v.type === 'athlete' ? '../index.php?page=profil&id='+v.id : '../index.php?page=recherche&club='+encodeURIComponent(v.nom);
        h += '<tr class="vue-row" onclick="window.open(\''+link+'\',\'_blank\')">'
            + '<td class="time" style="font-size:12px;">'+_fmtDateFull(v.date)+'</td>'
            + '<td class="mono" style="font-size:12px;">'+_esc(v.ip)+'</td>'
            + '<td><span style="background:'+typeBg+';color:'+typeColor+';font-weight:700;font-size:11px;padding:3px 10px;border-radius:6px;">'+typeLabel+'</span></td>'
            + '<td style="font-weight:700;color:#e2e8f0;font-size:14px;">'+_esc(v.nom)+'</td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';
    el.innerHTML = h;
}

// === BLOCKED TAB (IPs rate limitees) ===
function _vueRenderBlocked(el) {
    var items = D.rateLimited || [];
    var limit = D.rateLimitMax || 20;
    if (_filter) items = items.filter(function(r) { return r.ip.indexOf(_filter) >= 0 || r.date.indexOf(_filter) >= 0; });

    // Grouper par IP pour stats globales
    var byIp = {};
    items.forEach(function(r) {
        if (!byIp[r.ip]) byIp[r.ip] = { ip: r.ip, totalReq: 0, totalDep: 0, jours: 0, dates: [] };
        byIp[r.ip].totalReq += r.count;
        byIp[r.ip].totalDep += r.depassement;
        byIp[r.ip].jours++;
        byIp[r.ip].dates.push(r);
    });
    var uniqueIps = Object.keys(byIp).length;

    var h = '<input type="text" class="vue-search" placeholder="&#128269; Rechercher par IP ou date..." oninput="_vueFilter(this.value)" value="'+_esc(_filter)+'">';
    h += '<div style="display:flex;gap:12px;margin-bottom:14px;flex-wrap:wrap;">';
    h += '<div style="background:#ef444420;border:1px solid #ef444440;border-radius:10px;padding:10px 16px;flex:1;min-width:120px;text-align:center;">'
        + '<div style="color:#ef4444;font-weight:800;font-size:22px;">'+items.length+'</div>'
        + '<div style="color:#8b949e;font-size:11px;">Blocages (7j)</div></div>';
    h += '<div style="background:#f59e0b20;border:1px solid #f59e0b40;border-radius:10px;padding:10px 16px;flex:1;min-width:120px;text-align:center;">'
        + '<div style="color:#f59e0b;font-weight:800;font-size:22px;">'+uniqueIps+'</div>'
        + '<div style="color:#8b949e;font-size:11px;">IPs uniques bloquees</div></div>';
    h += '<div style="background:#8b5cf620;border:1px solid #8b5cf640;border-radius:10px;padding:10px 16px;flex:1;min-width:120px;text-align:center;">'
        + '<div style="color:#a78bfa;font-weight:800;font-size:22px;">'+limit+'</div>'
        + '<div style="color:#8b949e;font-size:11px;">Limite / jour</div></div>';
    h += '</div>';

    h += '<p style="color:#5a6580;font-size:11px;margin-bottom:10px;">IPs ayant depasse la limite de '+limit+' requetes/jour et ayant vu la page d\'inscription — cliquez pour le detail</p>';

    h += '<div style="max-height:520px;overflow-y:auto;"><table style="font-size:13px;"><thead><tr>'
        + '<th>Date</th>'
        + '<th>Adresse IP</th>'
        + '<th style="text-align:center;">Requetes</th>'
        + '<th style="text-align:center;">Depassement</th>'
        + '<th style="text-align:center;">Statut</th>'
        + '</tr></thead><tbody>';

    if (!items.length) h += '<tr><td colspan="5" style="text-align:center;color:#5a6580;padding:30px;font-size:14px;">Aucune IP bloquee ces 5 derniers jours</td></tr>';

    items.forEach(function(r, i) {
        var severity = r.depassement > 20 ? '#ef4444' : r.depassement > 5 ? '#f59e0b' : '#eab308';
        var severityBg = r.depassement > 20 ? '#ef444420' : r.depassement > 5 ? '#f59e0b20' : '#eab30820';
        var severityLabel = r.depassement > 20 ? 'Abusif' : r.depassement > 5 ? 'Excessif' : 'Limite';
        h += '<tr class="vue-row" onclick="_vueShowBlocked('+i+')">'
            + '<td class="time" style="font-size:12px;">'+_esc(r.date)+'</td>'
            + '<td class="mono" style="font-weight:700;font-size:13px;">'+_esc(r.ip)+'</td>'
            + '<td style="text-align:center;"><span style="background:#ef444420;color:#ef4444;font-weight:800;font-size:16px;padding:4px 12px;border-radius:8px;">'+r.count+'</span></td>'
            + '<td style="text-align:center;"><span style="color:#f59e0b;font-weight:700;">+'+r.depassement+'</span></td>'
            + '<td style="text-align:center;"><span style="background:'+severityBg+';color:'+severity+';font-weight:700;font-size:11px;padding:3px 10px;border-radius:6px;">'+severityLabel+'</span></td>'
            + '</tr>';
    });
    h += '</tbody></table></div>';

    // IPs bannies definitivement
    var banned = D.bannedIps || {};
    var bannedKeys = Object.keys(banned);
    if (bannedKeys.length > 0) {
        h += '<div style="margin-top:16px;"><h4 style="color:#ef4444;font-size:13px;margin-bottom:8px;">&#128274; IPs bannies definitivement ('+bannedKeys.length+')</h4>';
        h += '<table style="font-size:12px;"><thead><tr><th>IP</th><th>Date du ban</th></tr></thead><tbody>';
        bannedKeys.forEach(function(ip) {
            h += '<tr class="vue-row">'
                + '<td class="mono" style="font-weight:700;" onclick="_vueShowBlockedIp(\''+_esc(ip)+'\')">'+_esc(ip)+'</td>'
                + '<td><span style="background:#ef444420;color:#ef4444;font-weight:600;padding:3px 10px;border-radius:6px;font-size:11px;">'+_esc(banned[ip])+'</span></td>'
                + '<td><button onclick="_unbanIp(\''+_esc(ip)+'\',this)" style="background:#10b98120;border:1px solid #10b98140;color:#10b981;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Debannir</button></td>'
                + '</tr>';
        });
        h += '</tbody></table></div>';
    }

    el.innerHTML = h;
}

// Drawer: detail IP bloquee (une entree = 1 jour)
window._vueShowBlocked = function(idx) {
    var items = D.rateLimited || [];
    if (_filter) items = items.filter(function(r) { return r.ip.indexOf(_filter) >= 0 || r.date.indexOf(_filter) >= 0; });
    var r = items[idx]; if (!r) return;
    var limit = D.rateLimitMax || 20;

    var h = '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#ef4444;">'+r.count+'</div><div class="vd-lbl">Requetes ce jour</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">+'+r.depassement+'</div><div class="vd-lbl">Au-dela de la limite</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#8b949e;">'+limit+'</div><div class="vd-lbl">Limite / jour</div></div>'
        + '</div>';

    h += '<div class="vd-section"><h4>Informations</h4><table>'
        + '<tr><td class="dim">Adresse IP</td><td class="mono" style="font-weight:700;">'+_esc(r.ip)+'</td></tr>'
        + '<tr><td class="dim">Date</td><td>'+_esc(r.date)+'</td></tr>'
        + '<tr><td class="dim">Requetes effectuees</td><td style="color:#ef4444;font-weight:700;">'+r.count+'</td></tr>'
        + '<tr><td class="dim">Limite journaliere</td><td>'+limit+'</td></tr>'
        + '<tr><td class="dim">Depassement</td><td style="color:#f59e0b;font-weight:700;">+'+r.depassement+' requetes</td></tr>'
        + '</table></div>';

    // Verifier si cette IP a aussi consulte des profils/clubs
    var profils = (D.lastAth||[]).filter(function(v) { return v.ip === r.ip; });
    var clubs = (D.lastClub||[]).filter(function(v) { return v.ip === r.ip; });

    if (profils.length) {
        h += '<div class="vd-section"><h4>Profils consultes par cette IP ('+profils.length+')</h4>';
        h += '<table><thead><tr><th>Date</th><th>Athlete</th><th>ID</th></tr></thead><tbody>';
        profils.forEach(function(p) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=profil&id='+p.athlete_id_ext+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(p.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(p.nom||'?')+'</td>'
                + '<td class="mono">'+p.athlete_id_ext+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }

    if (clubs.length) {
        h += '<div class="vd-section"><h4>Clubs consultes par cette IP ('+clubs.length+')</h4>';
        h += '<table><thead><tr><th>Date</th><th>Club</th></tr></thead><tbody>';
        clubs.forEach(function(c) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=recherche&club='+encodeURIComponent(c.nom_club||'')+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(c.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(c.nom_club||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }

    if (!profils.length && !clubs.length) {
        h += '<div class="vd-section"><p class="dim">Cette IP n\'apparait pas dans les 50 dernieres vues de profils/clubs enregistrees.</p></div>';
    }

    h += '<div class="vd-section" style="margin-top:16px;padding:12px;background:#ef444410;border:1px solid #ef444430;border-radius:8px;">'
        + '<p style="color:#ef4444;font-size:12px;font-weight:600;margin-bottom:4px;">&#9888; Page d\'inscription affichee</p>'
        + '<p style="color:#8b949e;font-size:11px;">Cette IP a depasse la limite de '+limit+' requetes le '+_esc(r.date)+'. '
        + 'La page proposant de creer un compte ou de se connecter lui a ete presentee.</p></div>';

    _openDrawer('IP bloquee — ' + _esc(r.ip), h);
};

// Drawer: recap IP bloquee (toutes dates confondues)
window._vueShowBlockedIp = function(ip) {
    var items = (D.rateLimited||[]).filter(function(r) { return r.ip === ip; });
    if (!items.length) return;
    var limit = D.rateLimitMax || 20;
    var totalReq = 0, totalDep = 0;
    items.forEach(function(r) { totalReq += r.count; totalDep += r.depassement; });

    var h = '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#ef4444;">'+items.length+'</div><div class="vd-lbl">Jours bloques</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+totalReq+'</div><div class="vd-lbl">Total requetes</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#e2e8f0;">+'+totalDep+'</div><div class="vd-lbl">Total depassement</div></div>'
        + '</div>';

    h += '<div class="vd-section"><h4>Historique des blocages</h4>';
    h += '<table><thead><tr><th>Date</th><th style="text-align:center;">Requetes</th><th style="text-align:center;">Depassement</th></tr></thead><tbody>';
    items.forEach(function(r) {
        h += '<tr><td>'+_esc(r.date)+'</td>'
            + '<td style="text-align:center;"><span style="background:#ef444420;color:#ef4444;font-weight:700;padding:3px 10px;border-radius:6px;">'+r.count+'</span></td>'
            + '<td style="text-align:center;color:#f59e0b;font-weight:700;">+'+r.depassement+'</td></tr>';
    });
    h += '</tbody></table></div>';

    // Cross-reference with profils/clubs
    var profils = (D.lastAth||[]).filter(function(v) { return v.ip === ip; });
    var clubs = (D.lastClub||[]).filter(function(v) { return v.ip === ip; });

    if (profils.length) {
        h += '<div class="vd-section"><h4>Profils consultes ('+profils.length+')</h4>';
        h += '<table><thead><tr><th>Date</th><th>Athlete</th></tr></thead><tbody>';
        profils.forEach(function(p) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=profil&id='+p.athlete_id_ext+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(p.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(p.nom||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }

    if (clubs.length) {
        h += '<div class="vd-section"><h4>Clubs consultes ('+clubs.length+')</h4>';
        h += '<table><thead><tr><th>Date</th><th>Club</th></tr></thead><tbody>';
        clubs.forEach(function(c) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=recherche&club='+encodeURIComponent(c.nom_club||'')+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(c.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(c.nom_club||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }

    _openDrawer('IP bannie — ' + _esc(ip), h);
};

// === DRAWER ===
function _openDrawer(title, bodyHtml) {
    document.getElementById('vueDrawerTitle').textContent = title;
    document.getElementById('vueDrawerBody').innerHTML = bodyHtml;
    document.getElementById('vueDrawer').classList.add('open');
    document.getElementById('vueOverlay').classList.add('open');
}
window._vueClose = function() {
    document.getElementById('vueDrawer').classList.remove('open');
    document.getElementById('vueOverlay').classList.remove('open');
};

// Drawer: detail athlete
window._vueShowAth = function(idx) {
    var items = D.topAthletes;
    if (_filter) items = items.filter(function(a) {
        return (a.nom||'').toLowerCase().indexOf(_filter) >= 0 || (a.club||'').toLowerCase().indexOf(_filter) >= 0
            || (a.nationalite_athlete||'').toLowerCase().indexOf(_filter) >= 0 || (a.athlete_id_externe+'').indexOf(_filter) >= 0;
    });
    items = _sortItems(items, _sortKey);
    var a = items[idx]; if (!a) return;
    var h = '<div class="vd-section">'
        + '<a href="../index.php?page=profil&id='+a.athlete_id_externe+'" class="vd-link" target="_blank">Voir le profil de '+_esc(a.nom)+' &rarr;</a>'
        + '</div>';
    h += '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+a.vues+'</div><div class="vd-lbl">Vues totales</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#55efc4;">'+(a.ips_uniques||0)+'</div><div class="vd-lbl">IPs uniques</div></div>'
        + '</div>';
    h += '<div class="vd-section"><h4>Informations</h4><table>'
        + '<tr><td class="dim">ID</td><td class="mono">'+a.athlete_id_externe+'</td></tr>'
        + '<tr><td class="dim">Sexe</td><td>'+_esc(a.sexe_athlete)+'</td></tr>'
        + '<tr><td class="dim">Categorie</td><td>'+_esc(a.categorie_athlete)+'</td></tr>'
        + '<tr><td class="dim">Nationalite</td><td>'+_esc(a.nationalite_athlete)+'</td></tr>'
        + '<tr><td class="dim">Club</td><td>'+_esc((a.club||'').replace(/\*\s*$/,''))+'</td></tr>'
        + '<tr><td class="dim">Derniere vue</td><td class="time">'+_fmtDateFull(a.derniere_vue)+'</td></tr>'
        + '</table></div>';
    // IPs qui ont vu ce profil
    var ips = (D.lastAth||[]).filter(function(v) { return v.athlete_id_ext == a.athlete_id_externe; });
    h += '<div class="vd-section"><h4>IPs ayant consulte ce profil ('+ips.length+')</h4>';
    if (ips.length) {
        h += '<table><thead><tr><th>Date</th><th>IP</th></tr></thead><tbody>';
        ips.forEach(function(v) {
            h += '<tr><td class="time">'+_fmtDateFull(v.created_at)+'</td><td class="mono">'+_esc(v.ip)+'</td></tr>';
        });
        h += '</tbody></table>';
    } else h += '<p class="dim">Aucune IP enregistree dans les 50 dernieres vues</p>';
    h += '</div>';
    _openDrawer(_esc(a.nom) + ' (#' + a.athlete_id_externe + ')', h);
};

// Drawer: detail club
window._vueShowClub = function(idx) {
    var items = D.topClubs;
    if (_filter) items = items.filter(function(c) {
        return (c.nom_club||'').toLowerCase().indexOf(_filter) >= 0 || (c.id_club+'').indexOf(_filter) >= 0;
    });
    items = _sortItems(items, _sortKey === 'nom' ? 'nom_club' : _sortKey);
    var c = items[idx]; if (!c) return;
    var h = '<div class="vd-section">'
        + '<a href="../index.php?page=recherche&club='+encodeURIComponent(c.nom_club)+'" class="vd-link" target="_blank">Voir le club '+_esc(c.nom_club)+' &rarr;</a>'
        + '</div>';
    h += '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+c.vues+'</div><div class="vd-lbl">Vues totales</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#55efc4;">'+(c.ips_uniques||0)+'</div><div class="vd-lbl">IPs uniques</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#8b5cf6;">'+(c.nb_athletes||0)+'</div><div class="vd-lbl">Athletes</div></div>'
        + '</div>';
    h += '<div class="vd-section"><h4>Informations</h4><table>'
        + '<tr><td class="dim">ID Club</td><td class="mono">'+c.id_club+'</td></tr>'
        + '<tr><td class="dim">Derniere vue</td><td class="time">'+_fmtDateFull(c.derniere_vue)+'</td></tr>'
        + '</table></div>';
    var ips = (D.lastClub||[]).filter(function(v) { return v.club_id == c.id_club; });
    h += '<div class="vd-section"><h4>IPs ayant consulte ce club ('+ips.length+')</h4>';
    if (ips.length) {
        h += '<table><thead><tr><th>Date</th><th>IP</th></tr></thead><tbody>';
        ips.forEach(function(v) {
            h += '<tr><td class="time">'+_fmtDateFull(v.created_at)+'</td><td class="mono">'+_esc(v.ip)+'</td></tr>';
        });
        h += '</tbody></table>';
    } else h += '<p class="dim">Aucune IP dans les 50 dernieres vues</p>';
    h += '</div>';
    _openDrawer(_esc(c.nom_club) + ' (#' + c.id_club + ')', h);
};

// Drawer: detail IP
window._vueShowIp = function(idx) {
    var items = D.topIps;
    if (_filter) items = items.filter(function(ip) { return ip.ip.indexOf(_filter) >= 0; });
    var ip = items[idx]; if (!ip) return;
    var total = (parseInt(ip.nb_profils)||0) + (parseInt(ip.nb_clubs)||0);
    var h = '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+(ip.nb_profils||0)+'</div><div class="vd-lbl">Profils</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#8b5cf6;">'+(ip.nb_clubs||0)+'</div><div class="vd-lbl">Clubs</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#55efc4;">'+total+'</div><div class="vd-lbl">Total</div></div>'
        + '</div>';
    h += '<div class="vd-section"><h4>Periode</h4><table>'
        + '<tr><td class="dim">Premiere vue</td><td class="time">'+_fmtDateFull(ip.first_vue)+'</td></tr>'
        + '<tr><td class="dim">Derniere vue</td><td class="time">'+_fmtDateFull(ip.last_vue)+'</td></tr>'
        + '</table></div>';
    // Profils consultes
    var profils = ip.derniers_profils || [];
    h += '<div class="vd-section"><h4>Profils consultes ('+profils.length+')</h4>';
    if (profils.length) {
        h += '<table><thead><tr><th>Date</th><th>Athlete</th><th>ID</th></tr></thead><tbody>';
        profils.forEach(function(p) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=profil&id='+p.athlete_id_ext+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(p.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(p.nom||'?')+'</td>'
                + '<td class="mono">'+p.athlete_id_ext+'</td></tr>';
        });
        h += '</tbody></table>';
    } else h += '<p class="dim">Aucun profil</p>';
    h += '</div>';
    // Clubs consultes
    var clubs = ip.derniers_clubs || [];
    h += '<div class="vd-section"><h4>Clubs consultes ('+clubs.length+')</h4>';
    if (clubs.length) {
        h += '<table><thead><tr><th>Date</th><th>Club</th><th>ID</th></tr></thead><tbody>';
        clubs.forEach(function(c) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=recherche&club='+encodeURIComponent(c.nom_club||'')+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(c.created_at)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(c.nom_club||'?')+'</td>'
                + '<td class="mono">'+c.club_id+'</td></tr>';
        });
        h += '</tbody></table>';
    } else h += '<p class="dim">Aucun club</p>';
    h += '</div>';
    _openDrawer('IP ' + _esc(ip.ip), h);
};

// Drawer: detail jour
window._vueShowDay = function(dateStr) {
    var athDay = (D.lastAth||[]).filter(function(v) { return (v.created_at||'').substring(0,10) === dateStr; });
    var clubDay = (D.lastClub||[]).filter(function(v) { return (v.created_at||'').substring(0,10) === dateStr; });
    var h = '<div class="vd-kpi">'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#f59e0b;">'+athDay.length+'</div><div class="vd-lbl">Profils</div></div>'
        + '<div class="vd-kpi-item"><div class="vd-num" style="color:#8b5cf6;">'+clubDay.length+'</div><div class="vd-lbl">Clubs</div></div>'
        + '</div>';
    if (athDay.length) {
        h += '<div class="vd-section"><h4>Profils consultes</h4><table><thead><tr><th>Heure</th><th>IP</th><th>Athlete</th></tr></thead><tbody>';
        athDay.forEach(function(v) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=profil&id='+v.athlete_id_ext+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(v.created_at)+'</td>'
                + '<td class="mono">'+_esc(v.ip)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(v.nom||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }
    if (clubDay.length) {
        h += '<div class="vd-section"><h4>Clubs consultes</h4><table><thead><tr><th>Heure</th><th>IP</th><th>Club</th></tr></thead><tbody>';
        clubDay.forEach(function(v) {
            h += '<tr class="vue-row" onclick="window.open(\'../index.php?page=recherche&club='+encodeURIComponent(v.nom_club||'')+'\',\'_blank\')">'
                + '<td class="time">'+_fmtDateFull(v.created_at)+'</td>'
                + '<td class="mono">'+_esc(v.ip)+'</td>'
                + '<td style="color:#a29bfe;font-weight:600;">'+_esc(v.nom_club||'?')+'</td></tr>';
        });
        h += '</tbody></table></div>';
    }
    if (!athDay.length && !clubDay.length) h += '<p class="dim" style="text-align:center;padding:20px;">Aucune vue dans les 50 dernieres enregistrees pour ce jour</p>';
    _openDrawer('Vues du ' + dateStr, h);
};

// === INIT ===
_vueRenderChart();
_vueRenderTab();
})();
</script>

<?php endif; /* hasVuesTables */ ?>

<!-- ============================================================ -->
<!-- SECTION 13 : IPs RATE LIMITEES (bloquees) -->
<!-- ============================================================ -->
<div class="section"><h2 style="color:#ef4444;font-size:16px;border-color:#ef444440;">&#9888; IPs Bloquees &amp; Bannies</h2></div>

<div class="grid" style="grid-template-columns:repeat(4,1fr);">
    <div class="card" style="border-color:#ef444440;"><div class="num" style="color:#ef4444;"><?= count($bannedIps) ?></div><div class="label">IPs bannies</div></div>
    <div class="card" style="border-color:#f59e0b40;"><div class="num warn"><?= count($rateLimitedIps) ?></div><div class="label">Blocages (5 jours)</div></div>
    <div class="card" style="border-color:#8b5cf640;"><div class="num info"><?= count(array_unique(array_column($rateLimitedIps, 'ip'))) ?></div><div class="label">IPs uniques bloquees</div></div>
    <div class="card" style="border-color:#6c5ce740;"><div class="num" style="color:#8b949e;"><?= $rateLimitDailyLimit ?></div><div class="label">Limite / jour</div></div>
</div>

<!-- IPs bannies definitivement -->
<div class="section">
    <h4 style="color:#ef4444;font-size:14px;margin-bottom:8px;">&#128274; IPs bannies definitivement (<?= count($bannedIps) ?>)</h4>
    <p style="color:#8b949e;font-size:11px;margin-bottom:10px;">Ces IPs sont bloquees de maniere permanente — elles doivent s'inscrire pour acceder au site.</p>
    <?php if (empty($bannedIps)): ?>
    <p style="text-align:center;color:#5a6580;padding:20px;font-size:13px;">Aucune IP bannie pour le moment.</p>
    <?php else: ?>
    <div style="max-height:400px;overflow-y:auto;">
    <table style="font-size:12px;">
        <thead><tr><th>IP</th><th>Date du ban</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($bannedIps as $bIp => $bDate): ?>
        <tr class="vue-row ban-row">
            <td class="mono" style="font-weight:700;"><?= htmlspecialchars($bIp) ?></td>
            <td><span style="background:#ef444420;color:#ef4444;font-weight:600;padding:3px 10px;border-radius:6px;font-size:11px;"><?= htmlspecialchars($bDate) ?></span></td>
            <td><button onclick="_unbanIp('<?= htmlspecialchars($bIp, ENT_QUOTES) ?>',this)" style="background:#10b98120;border:1px solid #10b98140;color:#10b981;font-size:11px;padding:3px 10px;border-radius:6px;cursor:pointer;">Debannir</button></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<!-- Historique blocages journaliers -->
<?php if (!empty($rateLimitedIps)): ?>
<div class="section">
    <h4 style="color:#f59e0b;font-size:14px;margin-bottom:8px;">Historique blocages (5 derniers jours)</h4>
    <p style="color:#8b949e;font-size:11px;margin-bottom:10px;">IPs ayant depasse la limite de <?= $rateLimitDailyLimit ?> requetes/jour.</p>
    <input type="text" class="vue-search" id="rlSearch" placeholder="&#128269; Rechercher par IP ou date..." oninput="_rlFilter(this.value)" style="max-width:400px;margin-bottom:10px;">
    <div style="max-height:500px;overflow-y:auto;">
        <table id="rlTable" style="font-size:13px;">
            <thead><tr>
                <th>Date</th>
                <th>Adresse IP</th>
                <th style="text-align:center;">Requetes</th>
                <th style="text-align:center;">Depassement</th>
                <th style="text-align:center;">Severite</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rateLimitedIps as $rl):
                $dep = $rl['depassement'];
                if ($dep > 20) { $sevLabel = 'Abusif'; $sevColor = '#ef4444'; $sevBg = '#ef444420'; }
                elseif ($dep > 5) { $sevLabel = 'Excessif'; $sevColor = '#f59e0b'; $sevBg = '#f59e0b20'; }
                else { $sevLabel = 'Limite'; $sevColor = '#eab308'; $sevBg = '#eab30820'; }
            ?>
            <tr class="vue-row rl-row" data-ip="<?= htmlspecialchars($rl['ip']) ?>" data-date="<?= $rl['date'] ?>">
                <td class="time" style="font-size:12px;"><?= $rl['date'] ?></td>
                <td class="mono" style="font-weight:700;font-size:13px;"><?= htmlspecialchars($rl['ip']) ?></td>
                <td style="text-align:center;"><span style="background:#ef444420;color:#ef4444;font-weight:800;font-size:16px;padding:4px 12px;border-radius:8px;"><?= $rl['count'] ?></span></td>
                <td style="text-align:center;"><span style="color:#f59e0b;font-weight:700;">+<?= $dep ?></span></td>
                <td style="text-align:center;"><span style="background:<?= $sevBg ?>;color:<?= $sevColor ?>;font-weight:700;font-size:11px;padding:3px 10px;border-radius:6px;"><?= $sevLabel ?></span></td>
