import Track from './track';

function Stream(params) {
    var { stream } = params;

    stream.is_custom = true;

    stream.prepareStream = () => {
        console.log('tttttt stream prepareStream tracks', stream.getTracks());
        
        stream.getTracks().forEach(track => {
            stream.prepareTrack({ track });
        });
    }

    stream.prepareTrack = ({ track }) => {
        console.log('tttttt stream prepareTrack is_prepared', track.is_prepared);

        if(track.is_prepared) return;

        track = new Track({ track });
        track.is_prepared = true;

        track.onended = () => {
            stream.onTrackEnded && stream.onTrackEnded({ track });

            if(!stream.getActiveTracks().length){
                stream.onStreamEnded && stream.onStreamEnded();
            }
        }
    }

    stream.addNewTrack = ({ track }) => {
        console.log('tttttt stream addNewTrack', track);

        stream.addTrack(track);
        stream.prepareTrack({ track });
        stream.onTrackAdded && stream.onTrackAdded({ track });
    };
    
    stream.stopStream = () => {
        if(!stream.getActiveTracks().length) return;
        
        stream.getTracks().forEach(track => {
            track.stopTrack();
        });
    }

    stream.getActiveTracks = (params = {}) => {
        var { kind = '' } = params;

        return stream.getTracks().filter(function(track) {
            
            if((!kind || track.kind == kind) && track.readyState == 'live')
                return true;

            return false;
        });
    };

    stream.stopAudioTracks = () => {
        return stream.getAudioTracks().forEach(track => {
            track.stopTrack();
        });
    };

    stream.stopVideoTracks = () => {
        return stream.getVideoTracks().forEach(track => {
            track.stopTrack();
        });
    };

    stream.removeVideoTracks = () => {
        stream.getVideoTracks().forEach(track => {
            stream.removeTrack(track);
        });
    }

    stream.removeAudioTracks = () => {
        stream.getAudioTracks().forEach(track => {
            stream.removeTrack(track);
        });
    }

    stream.pauseAudioTracks = () => {
        return stream.getAudioTracks().forEach(track => {
            track.enabled = false;
        });
    };

    stream.resumeAudioTracks = () => {
        return stream.getAudioTracks().forEach(track => {
            track.enabled = true;
        });
    };

    stream.getStreamInfo = () => {
        var track = stream.getTracks()[0];
        return track && track.track_info ? track.track_info : {}
    }

    stream.getTrackByKind = ({ kind }) => {
        return stream.getTracks().find(track => {
            return track.kind == kind;
        });
    }

    stream.prepareStream();

    return stream;
}

export default Stream