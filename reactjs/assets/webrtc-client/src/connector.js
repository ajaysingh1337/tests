class Connector {
    constructor({ rtc_client }) {
        this.rtc_client = rtc_client;
        this.connections = {};
        this.connections_wrt_user = {};
        this.type = '';
    }
    
    async initiate() {
        this.addAllStreams();
    }

    async getUserTrack(params) {
        var { user_id, track_kind } = params;

        params.track_type = 'user';

        var is_me = (user_id == this.rtc_client.user.id);
        var participant = this.rtc_client.participants[user_id];
        
        console.log(
            'connector getUserTrack', 
            'user_id', user_id,
            'is_me', is_me,
            'track_kind', track_kind, 
            'participant', participant,
            'is_video_on', participant && participant.is_video_on
        );

        if(!participant) return;

        if(is_me) {
            this.onRemoteStream({ 
                stream: this.rtc_client.stream, 
                stream_type: 'user', 
                user_id 
            });
            
            return;
        }

        if(track_kind == 'video' && !participant.is_video_on) return;

        return this.getTrack(params);
    }

    addStream({ stream, stream_type, track_kind }) {
        console.log('dddd addStream', stream, stream_type, track_kind);
        if(!stream) return;

        stream.getTracks().forEach(track => {
            if(track_kind && track.kind != track_kind) return;
            this.addTrack({ track, track_type: stream_type, stream });
        });
    }

    addAllStreams() {
        this.addStream({ stream: this.rtc_client.stream, stream_type: 'user' });
        this.addStream({ stream: this.rtc_client.screen_stream, stream_type: 'screen' });
    }

    pauseTrack() {}
    resumeTrack() {}

    async getRoundTripTime() {
        var total_rtt = 0;

        for(var connection_id in this.connections) {
            var rtt = await this.connections[connection_id].getRoundTripTime();
            rtt = rtt > -1 ? rtt : 0.2;

            total_rtt += rtt;
        }

        var avg_rtt = total_rtt / Object.keys(this.connections).length;
        avg_rtt = avg_rtt >= 0 ? avg_rtt : 0;

        return avg_rtt;
    }

    close() {
        for (var connection_id in this.connections) {
            this.connections[connection_id] && this.connections[connection_id].close();
        }
    }

    closeConnection(params) {
        var { user_id } = params;

        var connection_ids = this.connections_wrt_user[user_id];
        if(!connection_ids) return;

        connection_ids.forEach(connection_id => {

            this.connections[connection_id] && this.connections[connection_id].close();
            delete this.connections[connection_id];
        });
        
        delete this.connections_wrt_user[user_id];
    }

    onTrackEnded(params) {
        var { track } = params;

        var connection = this.connections[track.track_info.connection_id];
        connection && connection.onTrackEnded(params);
    }

    onLocalTrackEnded() {}

    onRemoteStream(params) {
        var { stream, stream_type, user_id } = params;

        console.log('tttttt onRemoteStream', params);

        stream = this.rtc_client.prepareStream(params);

        //screen stream
        if(stream_type == 'screen'){
            
            this.rtc_client.callCallback({
                callback_name: 'onScreenStream',
                data: {
                    stream, 
                    user_id
                }
            });
        }

        //camera stream
        if(stream_type == 'user'){
            this.onUserStream({ 
                stream, 
                user_id 
            });
        }

        return stream;
    }

    onUserStream(params) {
        var { stream, user_id } = params;
        
        stream = stream || {};

        if(user_id != this.rtc_client.user.id) {
            this.rtc_client.registerSpeechEvents(params);
        }

        var audio_track = stream.getAudioTracks && stream.getAudioTracks()[0];
        var video_track = stream.getVideoTracks && stream.getVideoTracks()[0];
        
        console.log('dddd _onUserStream audio_track', audio_track, 'video_track', video_track, 'user_id', user_id);
        
        if(audio_track) {
            this.rtc_client.callCallback({
                callback_name: 'onUserAudioStream',
                data: params
            });
        }

        if(video_track){
            this.rtc_client.callCallback({
                callback_name: 'onUserVideoStream',
                data: params
            });
        }
    }

    onRemoteStreamEnded(params) {
        var { stream } = params;

        console.log('tttttt onRemoteStreamEnded', params);

        var stream_info = stream.getStreamInfo();

        var user_id = stream_info.user_id;
        var stream_type = stream_info.type;
        var stream_kind = stream_info.kind;

        if(stream_type == 'user'){
            var stream_kind_capital = stream_kind.charAt(0).toUpperCase() + stream_kind.slice(1);

            // onUserVideoStreamEnded, onUserAudioStreamEnded
            var callback_name = `onUser${stream_kind_capital}StreamEnded`;

            this.rtc_client.callCallback({
                callback_name,
                data: { stream, user_id }
            });
        
        }else if(stream_type == 'screen'){
            this.rtc_client.callCallback({
                callback_name: 'onScreenStreamEnded',
                data: { stream, user_id }
            });
        }
    }

    onConnectionClosed(params) {
        var { connection } = params;

        delete this.connections[connection.id];
    }
}

export default Connector