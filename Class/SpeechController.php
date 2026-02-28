<?php
/**
 * SpeechController.php — Controleur de synthese vocale / Speech synthesis controller
 * FR: Classe JavaScript pour controler la synthese vocale avec selection de voix, lecture, pause et arret
 * EN: JavaScript class to control speech synthesis with voice selection, play, pause and stop
 */
?>
<script>
    class SpeechController {
    constructor(text, options = {}) {
        this.text = text;
        this.lang = options.lang || 'fr-FR';
        this.voiceSelect = document.getElementById(options.voiceSelectId);
        this.playBtn = document.getElementById(options.playBtnId);
        this.pauseBtn = document.getElementById(options.pauseBtnId);
        this.stopBtn = document.getElementById(options.stopBtnId);
        this.isPaused = false;
        this.utterance = null;

        this._loadVoices();
        this._setupEvents();
    }

    _loadVoices() {
        const load = () => {
            let voices = speechSynthesis.getVoices();
            if (!voices.length) return setTimeout(load, 50); // attendre les voix
            this.voiceSelect.innerHTML = '';
            voices.forEach(voice => {
                const option = document.createElement('option');
                option.value = voice.name;
                option.textContent = `${voice.name} (${voice.lang})`;
                if (voice.lang.startsWith('fr')) option.textContent += ' 🇫🇷';
                this.voiceSelect.appendChild(option);
            });
            const defaultVoice = voices.find(v => v.lang.startsWith('fr')) || voices[0];
            if (defaultVoice) this.voiceSelect.value = defaultVoice.name;
        };
        load();
        speechSynthesis.onvoiceschanged = load;
    }

    _setupEvents() {
        this.playBtn.addEventListener('click', () => this.play());
        this.pauseBtn.addEventListener('click', () => this.pause());
        this.stopBtn.addEventListener('click', () => this.stop());
        this.voiceSelect.addEventListener('change', () => this.changeVoice());
    }

    play() {
        if (this.isPaused) {
            speechSynthesis.resume();
            this.isPaused = false;
            return;
        }
        speechSynthesis.cancel();
        this.utterance = new SpeechSynthesisUtterance(this.text);
        this.utterance.lang = this.lang;
        const voices = speechSynthesis.getVoices();
        let selectedVoice = voices.find(v => v.name === this.voiceSelect.value) || voices[0];
        this.utterance.voice = selectedVoice;
        speechSynthesis.speak(this.utterance);
    }

    pause() {
        if (speechSynthesis.speaking && !speechSynthesis.paused) {
            speechSynthesis.pause();
            this.isPaused = true;
        }
    }

    stop() {
        speechSynthesis.cancel();
        this.isPaused = false;
    }

    changeVoice() {
        if (speechSynthesis.speaking || speechSynthesis.paused) {
            this.stop();
            this.play();
        }
    }
}
</script>