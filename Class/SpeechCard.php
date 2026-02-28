<?php
/**
 * SpeechCard.php — Composant de synthese vocale avec interface / Speech synthesis component with UI
 * FR: Classe JavaScript pour lire du texte a voix haute avec selection de voix, lecture, pause, reprise et arret
 * EN: JavaScript class for text-to-speech with voice selection, play, pause, resume and stop controls
 */
?>
<script>

    class SpeechCard {
  constructor(ids) {
    this.textarea = document.getElementById(ids.textarea);
    this.voiceSelect = document.getElementById(ids.voiceSelect);
    this.playBtn = document.getElementById(ids.playBtn);
    this.pauseBtn = document.getElementById(ids.pauseBtn);
    this.resumeBtn = document.getElementById(ids.resumeBtn);
    this.stopBtn = document.getElementById(ids.stopBtn);
    this.utterance = null;
    this.voices = [];

    this.initVoices();
    this.bindEvents();
  }

  initVoices() {
    const populate = () => {
      this.voices = speechSynthesis.getVoices();
      this.voiceSelect.innerHTML = '';
      let defaultIndex = 0;
      this.voices.forEach((v,i)=>{
        const option = document.createElement('option');
        option.value=i;
        option.textContent = `${v.name} (${v.lang})`;
        this.voiceSelect.appendChild(option);
        if(v.lang.startsWith("fr") && defaultIndex === 0) defaultIndex=i;
      });
      this.voiceSelect.value = defaultIndex;
    };
    speechSynthesis.onvoiceschanged = populate;
    populate();
  }

  bindEvents() {
    this.playBtn.onclick = ()=>this.play();
    this.pauseBtn.onclick = ()=>this.pause();
    this.resumeBtn.onclick = ()=>this.resume();
    this.stopBtn.onclick = ()=>this.stop();
  }

  play() {
    if(!this.textarea.value.trim()) return;
    if(!this.utterance) {
      this.utterance = new SpeechSynthesisUtterance(this.textarea.value);
      const idx = Number(this.voiceSelect.value);
      if(this.voices[idx]) this.utterance.voice = this.voices[idx];
      this.utterance.lang="fr-FR";
      this.utterance.onend = ()=>{ this.utterance=null; };
      speechSynthesis.speak(this.utterance);
    } else if(speechSynthesis.paused) {
      speechSynthesis.resume();
    }
  }
  pause(){ if(speechSynthesis.speaking && !speechSynthesis.paused) speechSynthesis.pause(); }
  resume(){ if(speechSynthesis.paused) speechSynthesis.resume(); }
  stop(){ if(speechSynthesis.speaking || speechSynthesis.paused){ speechSynthesis.cancel(); this.utterance=null; } }
}

/*
// exemple dutilisation 
// Initialisation : chaque carte a ses propres id uniques
new SpeechCard({
  textarea: "texte1",
  voiceSelect: "voiceSelect1",
  playBtn: "playBtn1",
  pauseBtn: "pauseBtn1",
  resumeBtn: "resumeBtn1",
  stopBtn: "stopBtn1"
});

*/
</script>

<style>
  .container{max-width:900px;margin:auto;}
.card_s{background:#fff;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,0.08);padding:18px;margin-bottom:20px;}
.controls{display:flex;gap:8px;flex-wrap:wrap;margin-top:12px;align-items:center;}
button,select{border-radius:8px;padding:6px 10px;font-weight:bold;cursor:pointer;}
button{background:#0f62fe;color:#fff;border:none;}
button.secondary{background:#e6eefc;color:#0f62fe;border:1px solid #cfe0ff;}
button.danger{background:#ff6b6b;}
textarea{width:100%;height:120px;margin-top:8px;padding:8px;border-radius:8px;border:1px solid #ccc;font-size:1rem;box-sizing:border-box;}
.images_container_all img{max-width:100%;border-radius:12px;margin-bottom:12px;}

</style>