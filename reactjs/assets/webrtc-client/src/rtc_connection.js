class RtcConnection {
    constructor(params) {
        var { id, type, user = {}, stream } = params;
        
        this.id = id;
        this.type = type;
        this.user_id = user.id;
        this.user = user;
        // streams
        this.streams = {};
        // tracks
        this.tracks = {};
        this.tracks_underway = {};
        this.incoming_tracks = {};
        this.outgoing_tracks = {};
        this.tracks_wrt_name = {};

        this.reconnect_interval_id = false;
        this.is_reconnecting = false;

        this.max_reconnection_attempts = 10;
        this.current_reconnection_attempt = 0;

        this.max_track_retry_attempts = 5;
        this.retry_add_track_attempt = 0;
        this.retry_get_track_attempt = 0;

        this.rtc_client = params.rtc_client;
        this.connector = params.connector;

        return this;
    }

    onTrack(params) {
        var { track, track_type } = params;

        var stream = new MediaStream([ track ]);

        this.retry_get_track_attempt = 0;

        console.log('onTrack', track, stream, stream.id && stream.getTracks());

        this.streams[stream.id] = stream;
        
        this.connector.onRemoteStream({ 
            stream,
            stream_type: track_type,
            user_id: this.user_id
        });

        track.track_info.connection_id = this.id;

        var track_info = track.track_info;

        this.tracks[track.id] = track;
        this.incoming_tracks[track.id] = track.id;
        this.tracks_wrt_name[track_info.name] = track.id;
        
        delete this.tracks_underway[track_info.name];

        if(track_type == 'user') {
            this.stream = stream;
        }

        this.rtc_client.callCallback({
            callback: this.rtc_client.onStreamReconnected, 
            data: { stream_id: stream.id }
        });
    }

    onTrackEnded(params) {
        var { track } = params;

        console.log('onTrackEnded', 'reconnecting', this.is_reconnecting);

        var track_name = track.track_info.name;
        var stream_id = track.track_info.stream_id;

        delete this.tracks[track.id];
        delete this.incoming_tracks[track.id];
        delete this.outgoing_tracks[track.id];
        delete this.tracks_wrt_name[track_name];

        delete this.streams[stream_id];
    }

    async addStream(params) {
        var { stream, stream_type } = params;

        if(!stream) return;

        for (const track of stream.getActiveTracks()) {
            this.addTrack({ 
                track, 
                track_type: stream_type, 
                stream 
            });
        }
    }

    onTrackAdded(params) {
        var { track, stream } = params;

        var track_info = track.track_info;
        track_info.is_outgoing = true;
        track_info.connection_id = this.id;

        this.streams[stream.id] = stream;

        this.tracks[track.id] = track;
        this.outgoing_tracks[track.id] = track.id;
        this.tracks_wrt_name[track_info.name] = track.id;

        this.retry_get_track_attempt = 0;

        var track_type = track_info.type;
        var track_kind = track_info.kind;
        var track_type_capital = track_type.charAt(0).toUpperCase() + track_type.slice(1);
        var track_kind_capital = track_kind.charAt(0).toUpperCase() + track_kind.slice(1);

        var callback_name = `on${track_type_capital}${track_kind_capital}TrackAdded`;

        // onUserVideoTrackAdded, onUserAudioTrackAdded, onScreenVideoTrackAdded
        this.rtc_client.callCallback({
            callback: this[callback_name],
            data: { track }
        });
    }

    onTrackRemoved(params) {
        var { track } = params;

        delete this.tracks[track.id];
        delete this.outgoing_tracks[track.id];
        delete this.tracks_wrt_name[track.track_info.name];
    }

    onTrackReplaced(params) {
        this.onTrackAdded(params);
        setTimeout(() => this.restartIce(), 5000);
    }

    removeTrackById(params) {
        var { track } = params;

        if(!this.outgoing_tracks[track.id]) return;

        return this.removeTrack(params);
    }

    addTrack(params) {
        var { stream, stream_id } = params;

        if(!stream && stream_id) 
            params.stream = this.streams[stream_id];
    }

    replaceTrack(params) {}

    getTrack(params) {
        var { user_id, track_type, track_kind } = params;

        var track_name = this.rtc_client.makeTrackName({ user_id, track_type, track_kind });
        var track_underway = this.tracks_underway[track_name];
        var existing_track_id = this.tracks_wrt_name[track_name];
        var existing_track = this.tracks[existing_track_id];

        console.log(
            'getTrack', 
            'track_name:', track_name,
            'track_underway:', track_underway,
            'existing_track_id', existing_track_id,
            'existing_track', existing_track,
            'tracks_underway', this.tracks_underway
        );

        return { track_name, track_underway, existing_track };
    }

    trackUnderway({ track_name }) {
        this.tracks_underway[track_name] = true;

        setTimeout(() => {
            delete this.tracks_underway[track_name];
        }, 50000);
    }

    retryAddTrack(params) {
        console.log('imp retryAddTrack init', this.retry_add_track_attempt, this.max_track_retry_attempts);
        if(this.retry_add_track_attempt >= this.max_track_retry_attempts) return;

        console.log('imp retryAddTrack inside');

        this.closeConnection();
        this.addTrack(params);

        this.retry_add_track_attempt++;
    }

    retryGetTrack(params) {
        var { track_name } = params;

        console.log('imp retryGetTrack init', this.retry_get_track_attempt, this.max_track_retry_attempts);
        if(this.retry_get_track_attempt >= this.max_track_retry_attempts) return;

        delete this.tracks_underway[track_name];

        console.log('imp retryGetTrack inside');
        
        this.closeConnection();
        this.getTrack(params);

        this.retry_get_track_attempt++;
    }

    streamsReconnecting() {
        Object.keys(this.streams).forEach(stream_id => {
            
            this.rtc_client.callCallback({
                callback: this.rtc_client.onStreamReconnecting, 
                data: { stream_id }
            });
        });
    }

    streamsReconnected() {
        Object.keys(this.streams).forEach(stream_id => {
            
            this.rtc_client.callCallback({
                callback: this.rtc_client.onStreamReconnected, 
                data: { stream_id }
            });
        });
    }

    onOtherEndReconnecting() {
        this.streamsReconnecting();
    }

    onOtherEndReconnected() {
        this.streamsReconnected();
    }

    close(params = {}) {
        var { soft_close } = params;

        // stopping remote streams from this end
        for(var track_id in this.tracks) {
            var track = this.tracks[track_id];
            
            if(soft_close && this.outgoing_tracks[track_id]) continue;
            if(this.outgoing_tracks[track_id]) continue;
            
            track.stopTrack();
        }

        if(!soft_close){
            this.tracks_wrt_name = {};
            
            clearInterval(this.reconnect_interval_id);
            this.reconnect_interval_id = false;

            this.connector.onConnectionClosed({ connection: this });
        }
    }

    closeConnection() {}

    reconnectInit() {
        if(this.is_reconnecting) return;

        this.is_reconnecting = true;
        this.streamsReconnecting();

        if(!this.is_reconnecting) {
            this.rtc_client.socketEmit({
                event: 'room:connection-reconnecting',
                data: { 
                    connection: { id: this.id, type: this.type, user_id: this.user_id } 
                }
            });
        }
    }

    reconnect() {}

    keepReconnecting() {
        console.log('keepReconnecting type', this.type, 'user_id', this.user_id, ' reconnect_interval_id', this.reconnect_interval_id);
        
        if(this.reconnect_interval_id) return;

        console.log('keepReconnecting doing');

        this.reconnect();

        this.reconnect_interval_id = setInterval(() => {
            this.reconnect();
            this.current_reconnection_attempt++;

            console.log('keepReconnecting setInterval', this.current_reconnection_attempt, this.max_reconnection_attempts);
            
            if(this.current_reconnection_attempt >= this.max_reconnection_attempts) {
                console.log('keepReconnecting setInterval clearInterval');
                clearInterval(this.reconnect_interval_id);

                this.current_reconnection_attempt = 0;
                this.reconnect_interval_id = false;
            }
        }, 10000);
    }

    reconnected() {
        console.log('rtc_connection reconnected');

        this.is_reconnecting = false;

        clearInterval(this.reconnect_interval_id);
        this.reconnect_interval_id = false;
        this.current_reconnection_attempt = 0;

        this.streamsReconnected();

        this.rtc_client.socketEmit({
            event: 'room:connection-reconnected',
            data: { 
                connection: { id: this.id, type: this.type, user_id: this.user_id } 
            }
        });
    }

    async getStats() {
        var peer = this.getPeer();
        if(!peer) return;

        return await peer.getStats();
    }

    async getStatsByType({ type, single }) {
        var stats = await this.getStats();
        var reports = [];

        stats && stats.forEach((report) => {
            if(report.type == type) {
                reports.push(report);
            }
        });

        return single ? reports[0] : reports;
    }

    async getRoundTripTime() {
        var candidate_pairs_stats = await this.getStatsByType({ type: 'candidate-pair' });
        var succeeded_pair_rtt = -1;
        var in_progress_pair_rtt = -1;

        candidate_pairs_stats.forEach(candidate_pair => {
            if(candidate_pair.state == 'succeeded' && candidate_pair.currentRoundTripTime >= 0) {
                succeeded_pair_rtt = candidate_pair.currentRoundTripTime;
            }

            if(candidate_pair.state == 'in-progress' && candidate_pair.currentRoundTripTime >= 0) {
                in_progress_pair_rtt = candidate_pair.currentRoundTripTime;
            }
        });

        if(succeeded_pair_rtt == -1 && in_progress_pair_rtt == -1) return -1;

        var rtt = succeeded_pair_rtt != -1 ? succeeded_pair_rtt : in_progress_pair_rtt;
        return parseFloat(rtt.toFixed(1));
    }

    toJSON() {
        var data = {};

        var ignore_properties = [
            'rtc_client'
        ]

        Object.getOwnPropertyNames(this).forEach(property_name => {
            if(ignore_properties.indexOf(property_name) !== -1) return;

            data[property_name] = this[property_name];
        });
        
        return data;
    }
}

export default RtcConnection