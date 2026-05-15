<?php
/**
 * Partial : Navigation interne du dashboard
 */
?>
<nav aria-label="Navigation principale">
    <a href="<?= $canonBase ?>/" class="logo">Bokonzi</a>
    <a href="<?= $canonBase ?>/?page=accueil" class="<?= $page === 'accueil' ? 'active' : '' ?>">Accueil</a>
    <a href="<?= $canonBase ?>/?page=athletes" class="<?= $page === 'athletes' ? 'active' : '' ?>">Athlètes</a>
    <a href="<?= $canonBase ?>/?page=recherche" class="<?= $page === 'recherche' ? 'active' : '' ?>">Recherche</a>
    <a href="<?= $canonBase ?>/?page=clubs" class="<?= $page === 'clubs' ? 'active' : '' ?>">Clubs</a>
    <a href="<?= $canonBase ?>/?page=epreuves" class="<?= $page === 'epreuves' ? 'active' : '' ?>">Épreuves</a>
    <a href="<?= $canonBase ?>/?page=villes" class="<?= $page === 'villes' ? 'active' : '' ?>">Villes</a>
    <a href="<?= $canonBase ?>/?page=comparer" class="<?= $page === 'comparer' ? 'active' : '' ?>" style="color:#f59e0b;">Comparer</a>
    <a href="<?= $canonBase ?>/?page=tuto" class="<?= $page === 'tuto' ? 'active' : '' ?>" style="color:#34d399;">Tuto</a>
</nav>
