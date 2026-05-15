/**
 * utils.js — Utility functions extracted from index.php
 * Origin: C:\xampp\htdocs\BK\index.php (lines ~5321-5581)
 * These are shared helpers used across multiple pages.
 */

if (typeof BASE_API === 'undefined') {
    // Fallback : detecte automatiquement local vs prod (jamais hardcode bokonzi.com)
    var BASE_API = (location.hostname === 'localhost' || location.hostname === '127.0.0.1')
        ? (location.pathname.indexOf('/BK/') !== -1 ? '/BK/api' : '/api')
        : '/api';
}

function escapeHtml(str) {
    if (!str) return '';
    const d = document.createElement('div');
    d.textContent = str;
    return d.innerHTML;
}
function dateFR(d) {
    if (!d || d === '-') return '-';
    if (d.indexOf('0000') === 0) return '-';
    var m = d.match(/^(\d{4})-(\d{2})-(\d{2})/);
    return m ? m[3] + '/' + m[2] + '/' + m[1] : d;
}

function _nivBadge(code) {
    if (!code) return '-';
    var nc = code.charAt(0);
    var bg, bc, tc;
    if (nc === 'N') { bg = '#e11d4820'; bc = '#e11d48'; tc = '#fb7185'; }
    else if (nc === 'I') { bg = '#c026d320'; bc = '#c026d3'; tc = '#e879f9'; }
    else if (nc === 'R') { bg = '#0891b220'; bc = '#0891b2'; tc = '#22d3ee'; }
    else { bg = '#f9731620'; bc = '#f97316'; tc = '#fb923c'; }
    return '<span style="display:inline-block;padding:2px 7px;border-radius:5px;font-size:10px;margin:1px;background:' + bg + ';border:1px solid ' + bc + '40;color:' + tc + ';">' + escapeHtml(code) + '</span>';
}
function _nivBadges(arr) {
    if (!arr || !arr.length) return '-';
    return arr.map(function(n) { return _nivBadge(n); }).join('');
}
function _highestNiveau(arr) {
    if (!arr || !arr.length) return null;
    var order = {IE:100,IR:99};
    ['N','R','D'].forEach(function(p,pi) {
        for (var i=1;i<=8;i++) order[p+i] = (90-pi*10)-i;
    });
    var best=null, bestS=-1;
    arr.forEach(function(n) { var s=order[n]||0; if(s>bestS){bestS=s;best=n;} });
    return best;
}
