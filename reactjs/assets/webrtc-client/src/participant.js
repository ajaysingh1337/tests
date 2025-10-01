class Participant {
    constructor(params) {
        var { participant } = params;
        
        this.id = '';
        this.user = {};
        this.is_video_on = false;
        this.is_audio_on = false;
        this.is_sharing = false;
        this.is_recording = false;
        this.is_hand_raised = false;
        this.is_speaking = false;
        this.is_disconnected = false;

        this.loadParticipant({ participant });

        return this;
    }

    onAudioSwitched(params) {
        var { is_audio_on } = params;
        
        this.is_audio_on = is_audio_on;
    }

    onVideoSwitched(params) {
        var { is_video_on } = params;
        
        this.is_video_on = is_video_on;
    }

    onSpeaking() {
        this.is_speaking = true;
    }

    onStoppedSpeaking() {
        this.is_speaking = false;
    }

    onHandRaised() {
        this.is_hand_raised = true;
    }

    onHandLowered() {
        this.is_hand_raised = false;
    }

    onStream(params) {
        var { stream } = params;

        this.is_video_on = stream.getVideoTracks().length ? true : false;
        this.is_audio_on = stream.getAudioTracks().length ? true : false;
    }

    onScreenShared() {
        this.is_sharing = new Date().toJSON().slice(0, 19).replace('T', ' ');
    }

    onShareScreenStopped() {
        this.is_sharing = false;
    }

    onRecordingStarted() {
        this.is_recording = true;
    }

    onRecordingStopped() {
        this.is_recording = false;
    }

    onReconnecting() {
        this.is_disconnected = true;
    }

    onReconnected() {
        this.is_disconnected = false;
    }

    loadParticipant(params) {
        var { participant } = params;

        Object.keys(participant).forEach((key) => {
            if(!this.hasOwnProperty(key)) return;
            
            this[key] = participant[key]; 
        });
    }
}

export default Participant;