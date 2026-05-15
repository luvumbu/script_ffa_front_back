/**
 * basket.js — Comparison cart (panier comparaison)
 * Extracted from index.php lines 600-712.
 * Manages localStorage keys: bk_cmp_athletes, bk_cmp_clubs
 */

// ═══════════ PANIER COMPARAISON (localStorage) ═══════════
function getBasketAthletes() {
    try { return JSON.parse(localStorage.getItem('bk_cmp_athletes') || '[]'); } catch(e) { return []; }
}
function getBasketClubs() {
    try { return JSON.parse(localStorage.getItem('bk_cmp_clubs') || '[]'); } catch(e) { return []; }
}
function saveBasketAthletes(list) { localStorage.setItem('bk_cmp_athletes', JSON.stringify(list)); updateBasketBadge(); }
function saveBasketClubs(list) { localStorage.setItem('bk_cmp_clubs', JSON.stringify(list)); updateBasketBadge(); }

function addAthleteToBasket(id, name) {
    var list = getBasketAthletes();
    if (list.find(function(a) { return a.id === id; })) return false;
    if (list.length >= 6) { alert('Maximum 6 athlètes dans le comparateur'); return false; }
    list.push({ id: id, name: name });
    saveBasketAthletes(list);
    return true;
}
function removeAthleteFromBasket(id) {
    saveBasketAthletes(getBasketAthletes().filter(function(a) { return a.id !== id; }));
}
function addClubToBasket(id, name) {
    var list = getBasketClubs();
    if (list.find(function(c) { return c.id === id; })) return false;
    list.push({ id: id, name: name });
    saveBasketClubs(list);
    return true;
}
function removeClubFromBasket(id) {
    saveBasketClubs(getBasketClubs().filter(function(c) { return c.id !== id; }));
}
function clearBasket() {
    localStorage.removeItem('bk_cmp_athletes');
    localStorage.removeItem('bk_cmp_clubs');
    updateBasketBadge();
    updateAllCmpButtons();
}
function isAthleteInBasket(id) {
    return !!getBasketAthletes().find(function(a) { return a.id === id; });
}
function isClubInBasket(id) {
    return !!getBasketClubs().find(function(c) { return c.id === id; });
}

function updateBasketBadge() {
    var ath = getBasketAthletes();
    var clubs = getBasketClubs();
    var basket = document.getElementById('cmpBasket');
    var total = ath.length + clubs.length;
    basket.style.display = total > 0 ? 'flex' : 'none';
    var athEl = document.getElementById('basketAthCount');
    var clubEl = document.getElementById('basketClubCount');
    if (ath.length > 0) { athEl.style.display = 'flex'; document.getElementById('basketAthNum').textContent = ath.length; }
    else { athEl.style.display = 'none'; }
    if (clubs.length > 0) { clubEl.style.display = 'flex'; document.getElementById('basketClubNum').textContent = clubs.length; }
    else { clubEl.style.display = 'none'; }
}

function toggleAthleteBasket(btn, id, name) {
    if (isAthleteInBasket(id)) {
        removeAthleteFromBasket(id);
        btn.classList.remove('added');
        btn.textContent = '+';
        btn.title = 'Ajouter au comparateur';
    } else {
        if (!addAthleteToBasket(id, name)) return;
        btn.classList.add('added');
        btn.textContent = '\u2713';
        btn.title = 'Dans le comparateur';
    }
}
function toggleClubBasket(btn, id, name) {
    if (isClubInBasket(id)) {
        removeClubFromBasket(id);
        btn.classList.remove('added');
        btn.textContent = '+';
    } else {
        addClubToBasket(id, name);
        btn.classList.add('added');
        btn.textContent = '\u2713';
    }
}

function updateAllCmpButtons() {
    document.querySelectorAll('[data-cmp-ath]').forEach(function(btn) {
        var id = parseInt(btn.getAttribute('data-cmp-ath'));
        if (isAthleteInBasket(id)) { btn.classList.add('added'); btn.textContent = '\u2713'; }
        else { btn.classList.remove('added'); btn.textContent = '+'; }
    });
    document.querySelectorAll('[data-cmp-club]').forEach(function(btn) {
        var id = parseInt(btn.getAttribute('data-cmp-club'));
        if (isClubInBasket(id)) { btn.classList.add('added'); btn.textContent = '\u2713'; }
        else { btn.classList.remove('added'); btn.textContent = '+'; }
    });
}

// Init badge au chargement
updateBasketBadge();
document.addEventListener('DOMContentLoaded', updateAllCmpButtons);
