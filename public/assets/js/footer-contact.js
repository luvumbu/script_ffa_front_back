/**
 * Footer Contact Form
 * Handles the footer contact form submission.
 * Reads values from fcNom, fcEmail, fcMsg input elements,
 * POSTs to /api/contact.php, and shows status in fcStatus element.
 *
 * Extracted from index.php lines 9875-9883.
 */
function _footerContact(){
    var msg=document.getElementById('fcMsg').value.trim();
    if(!msg){document.getElementById('fcStatus').innerHTML='<span style="color:#ef4444">Ecrivez un message.</span>';return;}
    var btn=event.target;btn.disabled=true;btn.textContent='Envoi...';
    fetch(BASE_API + '/contact.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({nom:document.getElementById('fcNom').value.trim(),email:document.getElementById('fcEmail').value.trim(),message:msg})}).then(function(r){return r.json()}).then(function(d){
        if(d.success){document.getElementById('footerContactForm').innerHTML='<p style="color:#10b981;font-size:13px;font-weight:600;margin-top:8px;">&#10003; Message envoye !</p>';}
        else{document.getElementById('fcStatus').innerHTML='<span style="color:#ef4444">'+(d.error||'Erreur')+'</span>';btn.disabled=false;btn.textContent='Envoyer';}
    }).catch(function(){document.getElementById('fcStatus').innerHTML='<span style="color:#ef4444">Erreur de connexion.</span>';btn.disabled=false;btn.textContent='Envoyer';});
}
