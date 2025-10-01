//var hark = require('hark/hark.js');
import hark from 'hark/hark.js';

class Helpers {
    wait(params) {
        var { duration_ms } = params;

        return new Promise((resolve) => {
            setTimeout(() => {
                resolve();
            
            }, duration_ms);
        });
    }

    registerSpeechEvents(params) {
        var { stream, on_speaking_callback, on_stopped_speaking_callback, hark_options = {} } = params;

        if(!stream || !stream.getAudioTracks().length) return;

        var speech_events = hark(stream, hark_options);
        
        speech_events.on('speaking', on_speaking_callback);
        speech_events.on('stopped_speaking', on_stopped_speaking_callback);
    }
}

export default Helpers