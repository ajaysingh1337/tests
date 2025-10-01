import RtcHelpers from "./rtc_helpers";
import Helpers from "./helpers";
import Participant from "./participant";
import Stream from "./stream";
import Track from "./track";
import PeerConnector from "./peer/peer_connector";
import SfuConnector from "./sfu/sfu_connector";

class RtcClient {
    get socket() {
        return this.sockett;
    }

    set socket(socket) {
        this.sockett = socket;
        this.rtc_helpers = new RtcHelpers({ socket });
        this.helpers = new Helpers();
        
        this.attachSocketListeners();
    }
    
    constructor() {
        this.connector = {};
        this.peer_connector = {};
        this.sfu_connector = {};
        this.have_joined = false;
        this.server_url = '';
        this.sockett = false;
        this.participants = {};
        // w.r.t participants join order
        this.participants_ids = [];
        this.host_ids = [];

        this.stream = false;
        this.screen_stream = false;
        // audio stream to do extra stuff like detect speaking, stopspeaking even if audio is off
        this.extra_audio_stream = false;

        this.user_media_constraints = {
            video: true,
            audio: {
                echoCancellation: true,
                noiseSuppression: true
            }
        };

        this.initial_user_media_constraints = this.user_media_constraints;

        this.display_media_constraints = {
            video: true
        };

        this.user = {
            id: ''
        };

        this.room_type = '';
        this.current_room_type = '';

        this.room = {
            id: ''
        }

        // receiving bandwidth
        this.bandwidth = {};

        // RTCConfiguration object
        this.rtc_config = {
            iceServers: [
                {
                    urls: "stun:stun.l.google.com:19302"
                }
            ]
        }

        // local video/audio
        this.is_video_on = false;
        this.is_audio_on = false;
        this.join_call_params = {};

        this.is_disconnected = false;
        this.reconnecting_timeout_id = false;
        this.reconnection_failed_timeout_id = false;
        this.http_ping_interval_id = false;
        
        // to enable multiple callbacks, see @function enableMultipleCallbacks 
        this.callbacks = [
            'onConnection',
            'onJoined',
            'onLeft',
            'onUserStream',
            'onUserVideoStream',
            'onUserAudioStream',
            'onScreenStream',
            'onPotentialUserVideo',
            'onPotentialUserAudio',
            'onPotentialScreenVideo',
            'onUserVideoStreamEnded',
            'onUserAudioStreamEnded',
            'onUserVideoTrackAdded',
            'onScreenStreamEnded',
            'onStreamReconnecting',
            'onStreamReconnected',
            'onLocalUserVideoStream',
            'onLocalUserAudioStream',
            'onScreenShared',
            'onShareScreenStopped',
            'onVideoSwitched',
            'onAudioSwitched',
            'onCallStarted',
            'onCallEnded',
            'onUsersAdded',
            'onHandRaised',
            'onHandLowered',
            'onReaction',
            'onSpeaking',
            'onStoppedSpeaking',
            'onHostAdded',
            'onHostRemoved',
            'onVideoError',
            'onAudioError',
            'onShareScreenError',
            'onRemoved',
            'onInitPeer',
            'onUserVideoSwitched',
            'onUserAudioSwitched',
            'onRecordingStarted',
            'onRecordingStopped',
            'onUserShareScreenStopped',
            'onReconnected',
            'onReconnecting',
            'onReconnectionFailed',
            'onConnectionReconnecting',
            'onConnectionReconnected',
            'onSocketReceived',
            'onSocketSent',
            'onSocketDisconnected',
            'onSocketConnected',
            'onLeaveCall',
            'onPostJoinData',
            'onRoomTypeChanged',
            'onSpeakRequest',
            'onAllowedToSpeak'
        ];

        this.socket_events = [
            'room:joined', 
            'room:left', 
            'room:removed', 
            'room:video-switched', 
            'room:audio-switched', 
            'room:can-make-connection', 
            'room:screen-shared',
            'room:share-screen-stopped',
            'room:created',
            'room:deleted',
            'room:users-added',
            'room:hand-raised',
            'room:hand-lowered',
            'room:recording-started',
            'room:recording-stopped',
            'room:reaction',
            'room:host-added',
            'room:host-removed',
            'room:switch-user-audio',
            'room:switch-user-video',
            'room:stop-user-share-screen',
            'room:reconnecting',
            'room:reconnected',
            'room:connection-reconnecting',
            'room:connection-reconnected',
            'room:current-type-changed',
            'room:invitation-updated',
            'room:request-get-track',
            'room:request-remove-track',
            'room:request-host-to-speak',
            'room:allow-user-to-speak'
        ];

        this.enableMultipleCallbacks();

        window.rtc_client = this;
    }

    // to enable attaching same callback multiple times for ease of access 
    enableMultipleCallbacks() {
        this.callbacks.forEach((name) => {
            Object.defineProperty(
                this, 
                name, 
                {
                    enumerable: true,
                    // callback accessed, returning array of callbacks to be called
                    get: function () {
                        return this[`__${name}`]; 
                    },
                    // callback attached
                    // making array of callbacks, later on, on access will call each on of these
                    set: function (callback) {
                        callback.callback_name = name;

                        this[`__${name}`] = this[`__${name}`] || [];
                        this[`__${name}`].push(callback); 
                        
                        console.log('setter: ' + name); 
                    }
                }
            );
        });
    }

    attachSocketListeners() {
        this.removeSocketListeners();

        this.socket.onAny((event, data) => {
            console.log('socket received:', event, data);

            this._onSocketReceived({ event, data });
        });

        this.socket.on('disconnect', (reason) => {
            console.log('socket disconnected: ', reason);
        
            this._onSocketDisconnected({ reason, socket_id: this.socket.id });
        });

        this.socket.on('connect', () => {
            // all socket events emitted during disconnection are saved in sendBuffer
            // when socket is reconnected then all events saved in sendBuffer are emitted automatically
            console.log('socket connected', JSON.parse(JSON.stringify(this.socket.sendBuffer)));

            this._onSocketConnected();
        });

        this.socket.on('room:joined', (params) => {
            this._onJoined(params);
        });

        this.socket.on('room:left', (params) => {
            this._onLeft(params);
        });

        this.socket.on('room:removed', (params) => {
            this.callCallback({
                callback: this.onRemoved,
                data: params
            });
        });

        this.socket.on('room:video-switched', (params) => {
            var { user_id } = params;
            if(user_id == this.user.id) return;

            this._onVideoSwitched(params);
        });

        this.socket.on('room:audio-switched', (params) => {
            var { user_id } = params;
            if(user_id == this.user.id) return;

            this._onAudioSwitched(params);
        });

        this.socket.on('room:can-make-connection', (params) => {
            this.initiateConnection(params);
        });

        this.socket.on('room:screen-shared', (params) => {
            this._onScreenShared(params);
        });

        this.socket.on('room:share-screen-stopped', (params) => {
            this._onShareScreenStopped(params);
        });

        this.socket.on('room:created', (params) => {
            this.callCallback({
                callback: this.onCallStarted, 
                data: params
            });
        });

        this.socket.on('room:deleted', (params) => {
            this._onRoomDeleted(params);
        });

        this.socket.on('room:users-added', (params) => {
            this.callCallback({
                callback: this.onUsersAdded, 
                data: params
            });
        });

        this.socket.on('room:hand-raised', (params) => {
            this._onHandRaised(params);
        });

        this.socket.on('room:hand-lowered', (params) => {
            this._onHandLowered(params);
        });

        this.socket.on('room:recording-started', (params) => {
            this._onRecordingStarted(params);
        });

        this.socket.on('room:recording-stopped', (params) => {
            this._onRecordingStopped(params);
        });

        this.socket.on('room:reaction', (params) => {
            this._onReaction(params);
        });

        this.socket.on('room:host-added', (params) => {
            this._onHostAdded(params);
        });

        this.socket.on('room:host-removed', (params) => {
            this._onHostRemoved(params);
        });

        this.socket.on('room:switch-user-audio', () => {
            this._onSwitchUserAudio();
        });

        this.socket.on('room:switch-user-video', () => {
            this._onSwitchUserVideo();
        });

        this.socket.on('room:stop-user-share-screen', () => {
            this._onStopUserShareScreen();
        });

        this.socket.on('room:request-get-track', (params) => {
            this.connector.onRequestGetTrack(params);
        });

        this.socket.on('room:request-remove-track', (params) => {
            this.connector.onRequestRemoveTrack(params);
        });

        this.socket.on('room:reconnecting', (params) => {
            this._onReconnecting(params);
        });

        this.socket.on('room:reconnected', (params) => {
            this._onReconnected(params);
        });

        this.socket.on('room:connection-reconnecting', (params) => {
            this._onConnectionReconnecting && this._onConnectionReconnecting(params);
        });

        this.socket.on('room:connection-reconnected', (params) => {
            this._onConnectionReconnected && this._onConnectionReconnected(params);
        });

        this.socket.on('room:current-type-changed', (params) => {
            this._onRoomTypeChanged(params)
        });

        this.socket.on('room:invitation-updated', (params) => {
            this.callCallback({
                callback: this.onInvitationUpdated, 
                data: params
            });
        });

        this.socket.on('room:request-host-to-speak', (params) => {
            this.callCallback({
                callback: this.onSpeakRequest, 
                data: params
            });
        });

        this.socket.on('room:allow-user-to-speak', (params) => {
            this.callCallback({
                callback: this.onAllowedToSpeak, 
                data: params
            });
        });
    }

    initiate() {
        this.current_room_type = this.room_type;

        if(this.room_type == 'p2p') {
            this.connector = new PeerConnector({ rtc_client: this });
        }
        else if(this.room_type == 'sfu') {
            this.connector = new SfuConnector({ rtc_client: this });
        }
        else if(this.room_type == 'p2p-sfu') {
            this.peer_connector = new PeerConnector({ rtc_client: this });
            this.sfu_connector = new SfuConnector({ rtc_client: this });
        }
    }

    _onSocketReceived(params) {
        this.callCallback({
            callback: this.onSocketReceived, 
            data: params
        });
    }

    _onScreenShared(params) {
        var { user_id } = params;

        this.participants[user_id] && this.participants[user_id].onScreenShared();

        this.callCallback({
            callback: this.onScreenShared,
            data: { user_id }
        });
    }

    _onShareScreenStopped(params) {
        var { user_id } = params;

        this.participants[user_id] && this.participants[user_id].onShareScreenStopped();

        this.callCallback({
            callback: this.onShareScreenStopped,
            data: { user_id }
        });
    }

    _onRecordingStarted(params) {
        var { user_id } = params;

        this.participants[user_id] && this.participants[user_id].onRecordingStarted();
        
        this.callCallback({
            callback: this.onRecordingStarted,
            data: { user_id }
        });
    }

    _onRecordingStopped(params) {
        var { user_id } = params;

        this.participants[user_id] && this.participants[user_id].onRecordingStopped();

        this.callCallback({
            callback: this.onRecordingStopped,
            data: { user_id }
        });
    }

    _onJoined(params) {
        var { user, participant, participants, participants_ids, host_ids } = params;

        this.loadParticipants({ participants });
        this.addParticipant({ participant });

        this.participants_ids = participants_ids;
        this.host_ids = host_ids;

        if(user.id == this.user.id) {
            this.have_joined = true;
        }

        this.connector.onJoined(params);

        this.callCallback({
            callback: this.onJoined, 
            data: { ...params, ...{ user_id: user.id } }
        });
    }

    _onLeft(params) {
        var { user_id, participants_ids } = params;

        this.removeParticipant({ user_id });
        this.connector.closeConnection({ user_id });

        this.participants_ids = participants_ids;

        if(user_id == this.user.id) {
            this.have_joined = false;
            // needed when call is ended by someone else
            this.connector.close();
            this.closeOutgoingStreams();
            this.removeSocketListeners();
            clearInterval(this.http_ping_interval_id);
            clearTimeout(this.reconnecting_timeout_id);
            clearTimeout(this.reconnection_failed_timeout_id);
        }

        this.callCallback({ 
            callback: this.onLeft, 
            data: params
        });
    }

    _onStopUserShareScreen() {
        if(!this.screen_stream) return;
        
        this.stopShareScreen();

        this.callCallback({
            callback: this.onUserShareScreenStopped
        });
    }

    _onSwitchUserVideo() {
        this.turnOffVideo();

        this.callCallback({
            callback: this.onUserVideoSwitched
        });
    }

    _onSwitchUserAudio() {
        this.pauseAudio();

        this.callCallback({
            callback: this.onUserAudioSwitched
        });
    }

    _onSpeaking({ user_id }) {
        this.participants[user_id] && this.participants[user_id].onSpeaking();

        this.callCallback({
            callback_name: 'onSpeaking',
            data: { user_id }
        });
    }

    _onStoppedSpeaking({ user_id }) {
        this.participants[user_id] && this.participants[user_id].onStoppedSpeaking();

        this.callCallback({
            callback_name: 'onStoppedSpeaking',
            data: { user_id }
        });
    }

    _onRoomDeleted(params) {
        this.leaveCall({ reason: 'room-deleted' });
        
        this.callCallback({
            callback: this.onCallEnded, 
            data: params
        });
    }

    _onHandRaised(params) {
        var { user_id } = params;

        this.participants[user_id] && this.participants[user_id].onHandRaised();
        
        this.callCallback({
            callback: this.onHandRaised, 
            data: { user_id }
        });
    }

    _onHandLowered(params) {
        var { user_id } = params;
        
        this.participants[user_id] && this.participants[user_id].onHandLowered();

        this.callCallback({
            callback: this.onHandLowered, 
            data: { user_id }
        });
    }

    _onReaction(params) {
        this.callCallback({
            callback: this.onReaction,
            data: params
        });
    }

    _onHostAdded(params) {
        var { user_id } = params;

        if (this.host_ids.indexOf(user_id) == -1) {
            this.host_ids.push(user_id);
        }

        this.callCallback({
            callback: this.onHostAdded,
            data: params
        });
    }

    _onHostRemoved(params) {
        var { user_id } = params;

        var index = this.host_ids.indexOf(user_id);
        if (index > -1) {
            this.host_ids.splice(index, 1);
        }

        this.callCallback({
            callback: this.onHostRemoved,
            data: params
        });
    }

    _onUserMediaError(params) {
        var { error, constraints, stream } = params;

        console.log('_onUserMediaError', 'error', error, 'constraints', constraints);

        var is_video_required = constraints.video;
        var is_audio_required = constraints.audio;
        var video_track = stream && stream.getVideoTracks().length;
        var audio_track = stream && stream.getAudioTracks().length;

        is_video_required && !video_track && this.callCallback({ callback: this.onVideoError, data: { error } });
        is_audio_required && !audio_track && this.callCallback({ callback: this.onAudioError, data: { error } });
    }

    _onDisplayMediaError(params) {
        var { error, constraints, stream } = params;

        console.log('_onDisplayMediaError', 'error', error, 'constraints', constraints);

        var is_video_required = constraints.video;
        var video_track = stream && stream.getVideoTracks().length;

        is_video_required && !video_track && this.callCallback({ callback: this.onShareScreenError, data: { error } });
    }

    async _onSocketDisconnected(params) {
        console.log('_onSocketDisconnected', 'is_disconnected', this.is_disconnected);

        this.is_disconnected = true;
        
        clearTimeout(this.reconnecting_timeout_id);
        clearTimeout(this.reconnection_failed_timeout_id);
        this.startSendingHttpPing();

        this._onReconnecting({ user_id: this.user.id });
        this.reconnection_failed_timeout_id = setTimeout(this._onReconnectionFailed.bind(this), 60000);
        
        this.callCallback({ callback: this.onSocketDisconnected, data: params });
    }

    _onSocketConnected() {
        console.log('_onSocketConnected', 'is_disconnected', this.is_disconnected);
        
        this._onMeReconnected();
        this.stopSendingHttpPing();
        this.socketEmit({
            event: 'room:reconnected',
            data: { user: this.user }
        });

        if(this.have_joined) {
            this.getPostJoinData();
        }

        this.callCallback({ callback: this.onSocketConnected });
    }

    _onReconnecting({ user_id }) {
        this.participants[user_id] && this.participants[user_id].onReconnecting();

        this.callCallback({ 
            callback: this.onReconnecting, 
            data: { user_id } 
        });
    }

    _onReconnected({ user_id }) {
        this.participants[user_id] && this.participants[user_id].onReconnected();

        this.callCallback({ 
            callback: this.onReconnected, 
            data: { user_id } 
        });
    }

    _onMeReconnected() {
        if(!this.is_disconnected) return;

        this.is_disconnected = false;
        clearTimeout(this.reconnecting_timeout_id);
        clearTimeout(this.reconnection_failed_timeout_id);

        this._onReconnected({ user_id: this.user.id });
    }

    _onReconnectionFailed() {
        console.log('_onReconnectionFailed', 'is_disconnected', this.is_disconnected);

        if(!this.is_disconnected) return;

        this.leaveCall();
        this.callCallback({ callback: this.onReconnectionFailed });
    }

    _onHttpPingSuccess() {
        this._onMeReconnected();
    }

    _onRoomTypeChanged({ current_type }) {
        if(current_type == 'p2p') this.switchToP2p();
        else if(current_type == 'sfu') this.switchToSfu();

        this.callCallback({ 
            callback: this.onRoomTypeChanged,
            data: { current_type }
        });
    }

    startSendingHttpPing() {
        console.log('startSendingHttpPing');

        this.sendHttpPing();
        clearInterval(this.http_ping_interval_id);
        this.http_ping_interval_id = setInterval(this.sendHttpPing.bind(this), 20000);
    }

    sendHttpPing() {
        var ping_url = `${this.server_url}/room/ping?room_id=${this.room.id}&room_type=${this.room_type}&user_id=${this.user.id}`;
        console.log('sendHttpPing ping_url', ping_url);
        
        return new Promise((resolve, reject) => {
            fetch(ping_url)
            .then(() => {
                this._onHttpPingSuccess();
                resolve(true);
            }).catch(() => {
                resolve(false);
            });
        });
    }

    stopSendingHttpPing() {
        console.log('stopSendingHttpPing');
        clearInterval(this.http_ping_interval_id);
    }

    async getPostJoinData() {
        var post_join_data = await this.socketEmit({
            event: 'room:get-post-join-data',
            return_response: true
        });

        post_join_data = post_join_data || {};
        var { participants, participants_ids, host_ids } = post_join_data;

        this.loadParticipants({ participants });
        this.participants_ids = participants_ids;
        this.host_ids = host_ids;

        this.connector.onPostJoinData(post_join_data);

        this.callCallback({ 
            callback: this.onPostJoinData,
            data: post_join_data
        });

        return post_join_data;
    }

    onLocalStream(params) {
        var { stream, stream_type = 'user' } = params;

        console.log('tttttt onLocalStream', params);

        if(!stream) return;

        stream.getTracks().forEach((track) => {
            var track_type_capital = stream_type.charAt(0).toUpperCase() + stream_type.slice(1);
            var track_kind_capital = track.kind.charAt(0).toUpperCase() + track.kind.slice(1);

            // onLocalUserVideoStream, onLocalUserAudioStream, onLocalScreenVideoStream
            this.callCallback({
                callback: this[`onLocal${track_type_capital}${track_kind_capital}Stream`],
                data: { stream, track }
            });
        });

        return this.prepareStream({ stream, stream_type, user_id: this.user.id });
    }

    onLocalStreamEnded(params) {
        var { stream } = params;

        console.log('tttttt onLocalStreamEnded', params);

        var stream_info = stream.getStreamInfo();
        var stream_type = stream_info.type;

        if(stream_type == 'screen') {
            this.stopShareScreen();
        }
    }

    removeSocketListeners() {
        this.socket_events.forEach(event_name => {
            this.socket.removeAllListeners(event_name);
        });

        this.socket.offAny();
    }

    // to access camera/mic
    getUserMedia(params = {}) {
        var { constraints, enable_errors = true } = params;

        constraints = constraints || this.user_media_constraints;

        return navigator.mediaDevices.getUserMedia(constraints)
        .then(stream => {
            return stream;
        })
        .catch(error => {
            this._onUserMediaError({ error, constraints, enable_errors });
        });
    }

    getUserMediaVideo() {
        return this.getUserMedia({
            constraints: {
              video: this.user_media_constraints.video,
            }
        });
    }

    getUserMediaAudio() {
        return this.getUserMedia({
            constraints: {
                audio: this.user_media_constraints.audio || true
            }
        });
    }

    // to access screen share
    getDisplayMedia(params = {}) {
        var { constraints, enable_errors = true } = params;

        constraints = constraints || this.display_media_constraints;
        
        return navigator.mediaDevices.getDisplayMedia(constraints)
        .then(stream => {
            return stream;
        })
        .catch(error => {
            this._onDisplayMediaError({ error, constraints, enable_errors });
        });
    }

    getInitialMedia() {
        return this.getUserMedia({ constraints: this.initial_user_media_constraints });
    }

    // Get user stream and make connection with peer
    async initiateConnection(params) {
        var { current_room_type } = params;

        if(this.room_type == 'p2p-sfu'){
            this.sfu_connector.prepare(params);
            
            this.current_room_type = current_room_type;
            this.connector = this.current_room_type == 'p2p' ? this.peer_connector : this.sfu_connector;
        }

        this.connector.prepare(params);

        // stream may have been fetched already
        this.stream = this.stream != false ? this.stream : await this.getInitialMedia();
        
        this.is_video_on = (this.stream && this.stream.getVideoTracks().length) ? true : false;
        this.is_audio_on = (this.stream && this.stream.getAudioTracks().length) ? true : false;

        this.onLocalStream({ stream: this.stream });
        if(this.is_audio_on) this.getExtraAudioStream();

        await this.joined();

        return this.stream;
    }

    joined() {
        return this.socketEmit({ 
            event: 'room:joined',
            return_response: true,
            simple_response: true,
            data: {
                user: this.user,
                is_video_on: this.is_video_on, 
                is_audio_on: this.is_audio_on,
                ...this.join_call_params
            }
        });
    }

    joinCall(params = {}) {
        this.rtc_helpers.user = this.user;
        this.join_call_params = params;
        
        this.socketEmit({ 
            event: 'room:request-join', 
            data: { room: this.room } 
        });
    }

    leaveCall(params = {}) {
        this.socketEmit({ event: 'room:left' });
        this._onLeft({ ...params, ...{user_id: this.user.id} });

        this.callCallback({ 
            callback: this.onLeaveCall, 
            data: params
        });
    }

    endCall() {
        this.socketEmit({ event: 'room:ended' });
        this._onLeft({ user_id: this.user.id });
    }

    turnOffVideo() {
        if(!this.stream) return;

        this.connector.removeTrack({
            track_type: 'user',
            track_kind: 'video',
            track: this.stream.getVideoTracks()[0]
        });
        
        this.stream.stopVideoTracks();
        this.stream.removeVideoTracks();

        this.is_video_on = false;
        this.socketEmit({ 
            event: 'room:video-switched',
            local_callback: this._onVideoSwitched,
            data: { is_video_on: false }
        });

        return true;
    }

    async turnOnVideo() {
        var stream = await this.getUserMedia({
            constraints: { 
                video: this.user_media_constraints.video || true  
            }
        });

        if(!stream) return;

        if(!this.stream || !this.stream.id) {
            this.stream = this.onLocalStream({ stream });
        
        }else {
            var track = stream.getVideoTracks()[0];
            this.stream.addNewTrack({ track });
        }

        this.is_video_on = true;

        this.socketEmit({ 
            event: 'room:video-switched',
            local_callback: this._onVideoSwitched,
            data: { is_video_on: true }
        });

        if(this.connector.type == 'sfu') {
            this.connector.addTrack({ 
                track: this.stream.getVideoTracks()[0], 
                track_type: 'user',
                stream: this.stream
            });
        }
    }

    async turnOnAudio() {
        var stream = await this.getUserMediaAudio();
        if(!stream) return;
        
        var track = stream.getAudioTracks()[0];

        if(!this.stream || !this.stream.id) {
            this.stream = this.onLocalStream({ stream });
        
        }else {
            this.stream.addNewTrack({ track });
        }

        this.is_audio_on = true;

        this.socketEmit({ 
            event: 'room:audio-switched', 
            local_callback: this._onAudioSwitched,
            data: { is_audio_on: true }
        });

        this.connector.addTrack({ 
            track: this.stream.getAudioTracks()[0], 
            track_type: 'user',
            stream: this.stream
        });
        
        this.getExtraAudioStream();

        return true;
    }

    pauseAudio() {
        if(!this.stream || !this.stream.getAudioTracks().length) return;

        this.stream.pauseAudioTracks();
        this.is_audio_on = false;

        this.socketEmit({ 
            event: 'room:audio-switched', 
            local_callback: this._onAudioSwitched,
            data: { is_audio_on: false }
        });

        // this.connector.pauseTrack({ track_kind: 'audio' });

        return true;
    }
    
    resumeAudio() {
        if(!this.stream || !this.stream.getAudioTracks().length) {
            this.turnOnAudio();
            return;
        };

        this.stream.resumeAudioTracks();
        this.is_audio_on = true;

        this.socketEmit({ 
            event: 'room:audio-switched', 
            local_callback: this._onAudioSwitched,
            data: { is_audio_on: true }
        });

        // this.connector.resumeTrack({ track_kind: 'audio' });

        return true;
    }

    pauseAudioTracks() {
        if(!this.stream || !this.stream.getAudioTracks().length) return;
        this.stream.pauseAudioTracks();
    }

    resumeAudioTracks() {
        if(!this.stream || !this.stream.getAudioTracks().length) return;
        this.stream.resumeAudioTracks();
    }

    async getExtraAudioStream() {
        console.log('ggggg getExtraAudioStream', this.extra_audio_stream);
        if(this.extra_audio_stream) this.extra_audio_stream.stopStream();

        var stream = await this.getUserMediaAudio();
        this.extra_audio_stream = new Stream({ stream });
        this.registerSpeechEvents({ stream: this.extra_audio_stream, user_id: this.user.id });
    }

    async switchMicrophone() {
        console.log('ggggg switchMicrophone is_audio_on', this.is_audio_on);
        this.stream.removeAudioTracks();
        
        var stream = await this.getUserMediaAudio();
        if(!stream) return;

        stream = this.onLocalStream({ stream });

        var track = stream.getAudioTracks()[0];
        this.stream.addNewTrack({ track });

        if(!this.is_audio_on) {
            console.log('ggggg switchMicrophone pauseAudioTracks');
            this.stream.pauseAudioTracks();
        }

        this.replaceUserTrack({ track });
        this.getExtraAudioStream();

        console.log('ggggg switchMicrophone track', track);
    }

    getUserVideoStream(params) {
        params.track_kind = 'video'

        return this.getUserTrack(params);
    }

    getUserAudioStream(params) {
        params.track_kind = 'audio'

        return this.getUserTrack(params);
    }

    dropUserVideoStream(params) {
        var { user_id } = params;
        
        this.connector.dropTrack({ 
            user_id, 
            track_type: 'user', 
            track_kind: 'video' 
        });
    }

    dropUserAudioStream(params) {
        var { user_id } = params;
        
        this.connector.dropTrack({ 
            user_id, 
            track_type: 'user', 
            track_kind: 'audio' 
        });
    }

    getUserStream(params) {
        var { user_id } = params;

        this.getUserTrack({ user_id, track_kind: 'video' });
        this.getUserTrack({ user_id, track_kind: 'audio' });
    }

    getScreenStream(params) {
        var { user_id } = params;

        return this.connector.getTrack({ 
            user_id, 
            track_type: 'screen', 
            track_kind: 'video' 
        });
    }

    dropScreenStream(params) {
        var { user_id } = params;
        
        this.connector.dropTrack({ 
            user_id, 
            track_type: 'screen', 
            track_kind: 'video' 
        });
    }

    dropUserStream(params) {
        var { user_id } = params;

        this.dropUserVideoStream({ user_id });
        this.dropUserAudioStream({ user_id });
    }

    getUserTrack(params) {
        return this.connector.getUserTrack(params);
    }

    replaceUserTrack(params) {
        var { track } = params;

        this.prepareTrack({ track, track_type: 'user', stream: this.stream, user_id: this.user.id });
        this.connector.replaceTrack({ track, stream: this.stream });
    }

    _onVideoSwitched(params) {
        var { user_id, is_video_on } = params;
        
        this.participants[user_id] && this.participants[user_id].onVideoSwitched({ is_video_on });
        this.connector.onVideoSwitched && this.connector.onVideoSwitched(params);

        this.callCallback({
            callback: this.onVideoSwitched, 
            data: { user_id, is_video_on }
        });
    }

    _onAudioSwitched(params) {
        var { user_id, is_audio_on } = params;
        
        this.participants[user_id] && this.participants[user_id].onAudioSwitched({ is_audio_on });

        this.callCallback({
            callback: this.onAudioSwitched, 
            data: { user_id, is_audio_on }
        });
    }

    // gets screen stream and shares it
    async shareScreen(params) {
        var { stream } = params;

        var stream = stream || await this.getDisplayMedia();
        if(!stream) return;

        this.shareScreenStream({ stream });
    }

    // share screen with given stream
    shareScreenStream(params) {
        var { stream } = params;

        this.screen_stream = this.onLocalStream({ stream, stream_type: 'screen' });

        this.socketEmit({ event: 'room:screen-shared' });

        this.connector.addTrack({ 
            track: stream.getVideoTracks()[0], 
            track_type: 'screen',
            stream
        });

        this.callCallback({ callback: this.onScreenShared, data: { user_id: this.user.id } });
    }

    stopShareScreen() {
        if(!this.screen_stream) return;

        this.connector.removeTrack({
            track_type: 'screen',
            track_kind: 'video',
            track: this.screen_stream.getVideoTracks()[0]
        });

        this.screen_stream.stopStream();
        this.screen_stream = false;

        this.socketEmit({ event: 'room:share-screen-stopped' });
    }

    stopUserShareScreen(params) {
        this.socketEmit({ 
            event: 'room:stop-user-share-screen',
            data: params
        });
    }

    stopAllUsersShareScreen(params) {
        this.notifyOtherUsers({ 
            event: 'room:stop-user-share-screen',
            data: params
        });
    }

    switchScreen(params) {
        var { user_id } = params;
        
        this.socketEmit({ 
            event: 'room:screen-switched', 
            data: { screen_user_id: user_id }
        });
    }

    registerSpeechEvents(params) {
        var { user_id } = params;

        params.on_speaking_callback = () => {
            this._onSpeaking({ user_id });
        };

        params.on_stopped_speaking_callback = () => {
            this._onStoppedSpeaking({ user_id });
        };
        
        this.helpers.registerSpeechEvents(params);
    }

    raiseHand() {
        this.socketEmit({ 
            event: 'room:hand-raised'
        });
    }

    lowerHand() {
        this.socketEmit({ 
            event: 'room:hand-lowered'
        });
    }

    startRecording() {
        this.socketEmit({ 
            event: 'room:recording-started'
        });
    }

    stopRecording() {
        this.socketEmit({ 
            event: 'room:recording-stopped'
        });
    }

    async addHost(params) {
        var result = await this.socketEmit({ 
            event: 'room:add-host',
            data: params,
            return_response: true,
            simple_response: true
        });

        if(result) this._onHostAdded({ user_id: params.target_user_id });

        return result;
    }

    async removeHost(params) {
        var result = await this.socketEmit({ 
            event: 'room:remove-host',
            data: params,
            return_response: true,
            simple_response: true
        });

        if(result) this._onHostRemoved({ user_id: params.target_user_id });

        return result;
    }

    removeUser(params) {
        return this.socketEmit({ 
            event: 'room:remove-user',
            data: params,
            return_response: true,
            simple_response: true
        });
    }

    sendReaction(params) {
        this.socketEmit({ 
            event: 'room:reaction',
            local_callback: this._onReaction,
            data: params
        });
    }

    switchUserAudio(params) {
        this.socketEmit({ 
            event: 'room:switch-user-audio',
            data: params
        });
    }

    switchUserVideo(params) {
        this.socketEmit({ 
            event: 'room:switch-user-video',
            data: params
        });
    }

    switchAllUsersAudio(params) {
        this.notifyOtherUsers({ 
            event: 'room:switch-user-audio',
            data: params
        });
    }

    switchAllUsersVideo(params) {
        this.notifyOtherUsers({ 
            event: 'room:switch-user-video',
            data: params
        });
    }

    requestHostToSpeak() {
        this.socketEmit({ event: 'room:request-host-to-speak' });
    }

    allowUserToSpeak(params) {
        this.socketEmit({ 
            event: 'room:allow-user-to-speak',
            data: params
        });
    }

    updateAppData(params) {
        this.socketEmit({ 
            event: 'room:update-app-data',
            data: params
        });
    }

    getRoom() {
        return this.rtc_helpers.getRoom({ room_id: this.room.id });
    }

    async getRoundTripTime() {
        return this.connector.getRoundTripTime();
    }

    // Send event to signaling server
    async socketEmit(params) {
        var { event, data = {}, return_response = false, simple_response = false, response_channel = 'callback', local_callback } = params;

        if(data.socket_response_event) {
            event = 'room:socket-response-event'
        }

        if(!event) return;

        // attaching common data
        data.room_id = this.room.id;
        data.room_type = this.room_type;
        data.user_id = this.user.id;

        console.log('socket emit', event, data);

        this.callCallback({
            callback: this.onSocketSent, 
            data: {
                event,
                data
            }
        });

        if(local_callback) {
            this.callCallback({
                callback: local_callback.bind(this),
                data
            });
        }
        
        if(!return_response) return this.socket.emit(event, data);

        if(response_channel == 'callback') {
            return await new Promise((resolve, reject) => {
                var timeout = false;
    
                this.socket.emit(event, data, (data) => {
                    clearTimeout(timeout);

                    if(simple_response) data = data && data.result;
                    resolve(data);
                });
    
                timeout = setTimeout(() => {
                    resolve();
                }, 10000);
            });
        }
        
        var socket_response_event = `${event}_${this.room.id}_${Date.now()}`;
        data.socket_response_event = socket_response_event;

        return await new Promise((resolve, reject) => {
            var timeout = false;

            this.socket.once(socket_response_event, (data) => {
                clearTimeout(timeout);
                resolve(data);
            });

            this.socket.emit(event, data);

            timeout = setTimeout(() => {
                resolve();
            }, 10000);
        });
    }

    // Calls callback
    callCallback(params) {
        var { callback, callback_name, data = {} } = params;

        console.log('callCallback', callback, callback_name, data);

        callback = callback || this[callback_name];
        if(!callback) return;
        
        // use attached multiple callbacks so calling them all
        if(Array.isArray(callback)) {
            callback.forEach(call => {
                call(data);
            });
        
        }else {
            callback(data);
        }

        var first_callback = Array.isArray(callback) ? callback[0] : callback;
        var callback_name = first_callback.callback_name;

        this.onCallback && this.onCallback({ callback_name, data });
    }

    // prepares stream, attach common things to it
    prepareStream(params) {
        var { stream, stream_type, user_id } = params;

        console.log('tttttt prepareStream', params);

        if(!stream || stream.is_prepared) return stream;

        var track_type = stream_type;

        stream = new Stream({ stream });
        stream.is_prepared = true;

        stream.getTracks().forEach(track => {
            this.prepareTrack({ track, track_type, stream, user_id });
        });

        stream.onTrackAdded = ({ track }) => {
            this.prepareTrack({ track, track_type, stream, user_id });
        }

        stream.onStreamEnded = () => {
            this.onStreamEnded({ stream });
        }

        stream.onTrackEnded = ({ track }) => {
            this.onTrackEnded({ track, stream });
        }

        return stream;
    }

    prepareTrack(params) {
        var { track, track_type, stream, user_id } = params;

        console.log('tttttt prepareTrack', params);

        if(!track.is_custom) 
            track = new Track({ track });

        track.is_prepared = true;

        track.track_info = {
            type: track_type,
            kind: track.kind,
            name: this.makeTrackName({ user_id, track_type, track_kind: track.kind }),
            user_id,
            stream_id: stream.id,
            is_remote: (user_id != this.user.id)
        }
    }

    makeTrackName({ user_id, track_type, track_kind }) {
        return `${user_id}_${track_type}_${track_kind}`;
    }

    switchToP2p() {
        this.sfu_connector.close();
        
        this.current_room_type = 'p2p';
        this.connector = this.peer_connector;

        this.peer_connector.initiate();
    }

    switchToSfu() {
        this.peer_connector.close();
        
        this.current_room_type = 'sfu';
        this.connector = this.sfu_connector;

        this.sfu_connector.initiate();
    }

    onTrackEnded(params) {
        var { track } = params;

        console.log('tttttt onTrackEnded', params);

        this.connector.onTrackEnded(params);
        if(!track.track_info.is_remote) this.onLocalTrackEnded(params);
    }

    onLocalTrackEnded({ track }) {
        console.log('tttttt onLocalTrackEnded', track);

        // if audio source is removed then we need to get new source and no need to close connection
        if(track.kind == 'audio' && !track.is_stopped) {
            this.switchMicrophone();
            return;
        }
        
        this.connector.onLocalTrackEnded({ track });

        return true;
    }

    onStreamEnded(params) {
        var { stream } = params;

        console.log('tttttt onStreamEnded', params);

        if(stream.getStreamInfo().is_remote) 
            this.connector.onRemoteStreamEnded({ stream });
        else 
            this.onLocalStreamEnded({ stream });
    }

    addParticipant(params) {
        var { participant } = params;
        
        if(!participant) return;

        participant.user = participant.user || {};
        var user = participant.user;

        if(user.is_media_server) return;

        this.participants[user.id] = new Participant({ participant });
    }

    removeParticipant(params) {
        var { user_id } = params;
        
        delete this.participants[user_id];
    }

    loadParticipants(params) {
        var { participants } = params;

        Object.keys(participants).forEach((user_id) => {
            if(this.participants[user_id]) {
                
                this.participants[user_id].loadParticipant({ participant: participants[user_id] });

                return;
            }

            this.addParticipant({ participant: participants[user_id] });
        });
    }

    notifyTheseUsers(params) {
        var { event, user_ids, data = {} } = params;

        data.event = event;
        data.user_ids = user_ids;

        this.socketEmit({ 
            event: 'room:notify-these-users',
            data
        });
    }

    notifyAllUsers(params) {
        var { event, data = {} } = params;

        data.event = event;

        this.socketEmit({ 
            event: 'room:notify-all-users',
            data
        });
    }

    notifyOtherUsers(params) {
        var { event, data = {} } = params;

        data.event = event;

        this.socketEmit({ 
            event: 'room:notify-other-users',
            data
        });
    }

    closeOutgoingStreams() {
        if(this.stream && this.stream.id) {
            this.stream.stopStream();
        }

        if(this.screen_stream && this.screen_stream.id) {
            this.screen_stream.stopStream();
        }

        if(this.extra_audio_stream) {
            this.extra_audio_stream.stopStream();
        }
    }

    // add users to call
    addUsers(params) {
        var { users } = params;

        this.socketEmit({
            event: 'room:users-added',
            data: { users }
        })
    }
}

window.RtcClient = RtcClient;

export default RtcClient