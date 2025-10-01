import PeerConnection from "./peer_connection";
import Connector from "../connector";

class PeerConnector extends Connector {
    constructor(params) {
        super(params);

        this.type = 'p2p';
        this.streams_info = {};

        this.attachSocketListeners();

        this.rtc_client.socket_events = [
            ...this.rtc_client.socket_events,
            'room:offer',
            'room:answer',
            'room:candidate',
            'room:track-info'
        ]
    }

    attachSocketListeners() {
        this.rtc_client.socket.on('room:offer', (params) => this.onOffer(params));
        this.rtc_client.socket.on('room:answer', (params) => this.onAnswer(params));
        this.rtc_client.socket.on('room:candidate', (params) => this.onCandidate(params));
        this.rtc_client.socket.on('room:track-info', (params) => this.onTrackInfo(params));
    }

    prepare() {}

    addAllStreams() {
        this.addStreamsToUsers();
    }

    addStreamsToUsers() {
        this.addStream({ stream: this.rtc_client.stream, stream_type: 'screen', track_kind: 'audio' });
        this.addStream({ stream: this.rtc_client.screen_stream, stream_type: 'screen' });
    }

    addStreamsToUser({ user_id }) {
        this.addStreamToUser({ user_id, stream: this.rtc_client.stream, stream_type: 'screen', track_kind: 'audio' });
        this.addStreamToUser({ user_id, stream: this.rtc_client.screen_stream, stream_type: 'screen' });
    }

    addStreamToUser({ user_id, stream, stream_type, track_kind }) {
        console.log('ddddd addStreamToUser', user_id, stream, stream_type, track_kind);
        if(!stream) return;
        
        stream.getTracks().forEach(track => {
            if(track_kind && track.kind != track_kind) return;
            this.addTrackToUser({ user_id, track, track_type: stream_type, stream });
        });
    }

    addTrackToUsers(params) {
        console.log('ddddd addTrackToUsers', this.rtc_client.participants_ids);

        this.rtc_client.participants_ids.forEach(user_id => {
            if(user_id == this.rtc_client.user.id) return;

            this.addTrackToUser({ ...params, user_id });
        });
    }

    addTrackToUser(params) {
        var { user_id } = params;

        var connection = this.getOrMakeConnection({ user_id, type: 'outgoing' });
        connection && connection.addTrack({ ...params, user_id });
    }

    addTrack(params) {
        console.log('dddd addTrack', params);
        this.addTrackToUsers(params);
    }

    removeTrack(params) {
        Object.values(this.connections).forEach(connection => {
            if(connection.type != 'outgoing') return;

            connection.removeTrack(params);
        });
    }

    removeTrackFromUser(params) {
        var { user_id } = params;

        var connection = this.getOrMakeConnection({ user_id, type: 'outgoing' });
        connection && connection.removeTrack(params);
    }

    replaceTrack(params) {
        Object.values(this.connections).forEach(connection => {
            if(connection.type != 'outgoing') return;
            
            connection.replaceTrack(params);
        });
    }

    getTrack(params) {
        var { user_id, track_type, track_kind } = params;
        
        console.log('peer_connector getTrack', 'user_id', user_id, 'track_type', track_type, 'track_kind', track_kind);

        var connection = this.getOrMakeConnection({ user_id, type: 'incoming' });
        connection && connection.getTrack(params);
    }

    dropTrack(params) {
        var { user_id } = params;

        var connection = this.getOrMakeConnection({ user_id, type: 'incoming' });
        connection && connection.dropTrack(params);
    }

    onLocalTrackEnded(params){
        this.removeTrack(params);
    }

    onOffer(params) {
        var { user_id, connection_type } = params;
        
        var type = connection_type == 'outgoing' ? 'incoming' : 'outgoing'; 
        var connection = this.getOrMakeConnection({ user_id, type });
        connection && connection.onOffer(params);
    }

    onAnswer(params) {
        var { user_id, connection_type } = params;

        var type = connection_type == 'outgoing' ? 'incoming' : 'outgoing'; 
        var connection = this.getOrMakeConnection({ user_id, type });
        connection && connection.onAnswer(params);
    }

    onCandidate(params) {
        var { user_id, connection_type } = params;

        var type = connection_type == 'outgoing' ? 'incoming' : 'outgoing'; 
        var connection = this.getOrMakeConnection({ user_id, type });
        connection.onCandidate(params);
    }

    onTrackInfo(params) {
        var { track_id, track_type, stream_id } = params;

        this.streams_info[stream_id] = { track_id, track_type };
    }

    onJoined(params) {
        var { user } = params;

        if(user.id == this.rtc_client.user.id) {
            this.addStreamsToUsers();
        }else {
            this.closeConnection({ user_id: user.id });
            this.addStreamsToUser({ user_id: user.id });
        }
    }

    onRequestGetTrack({ user_id }) {
        if(!this.rtc_client.stream) return;

        var stream = this.rtc_client.stream;
        var track = stream.getVideoTracks()[0];
        this.addTrackToUser({ user_id, track, track_type: 'user', stream });
    }

    onRequestRemoveTrack({ user_id }) {
        if(!this.rtc_client.stream) return;

        var stream = this.rtc_client.stream;
        var track = stream.getVideoTracks()[0];
        this.removeTrackFromUser({ user_id, track, track_type: 'user' });
    }

    onVideoSwitched({ user_id, is_video_on }) {
        if(!is_video_on) return;

        this.rtc_client.callCallback({
            callback_name: `onPotentialUserVideo`,
            data: { user_id }
        });
    }

    onPostJoinData() {}

    getOrMakeConnection(params) {
        var { user_id, type } = params;

        var id = `${user_id}_${type}`;
        var existing_connection = this.connections[id];

        console.log('dddd getOrMakeConnection', 'id', id, 'existing_connection', existing_connection);

        if(existing_connection) return existing_connection;

        params.id = id;
        params.rtc_client = this.rtc_client;
        params.connector = this;
        params.is_polite = user_id < this.rtc_client.user.id;

        var connection = new PeerConnection(params);
        this.connections[connection.id] = connection;
        this.connections_wrt_user[user_id] = this.connections_wrt_user[user_id] || [];
        this.connections_wrt_user[user_id].push(connection.id);

        return connection;
    }
}

export default PeerConnector