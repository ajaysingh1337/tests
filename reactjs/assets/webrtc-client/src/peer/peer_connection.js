import RtcConnection from "../rtc_connection";
import RtcPeer from "./rtc_peer";

class PeerConnection extends RtcConnection {
    constructor(params) {
        var { user_id, is_polite, rtc_config, rtc_client } = params;

        super(params);
        
        // for perfect negotiation, check @class RtcPeer 
        this.is_polite = is_polite;
        this.rtc_client = rtc_client;
        rtc_config = rtc_config || this.rtc_client.rtc_config;
        this.user_id = user_id;

        this.peer = new RtcPeer({ rtc_config, is_polite });
        this.peer.onnegotiationneeded = () => this.onNegotiationNeeded();
        this.peer.onicecandidate = (event) => this.onIceCandidateGenerated(event);
        this.peer.ontrack = (event) => this.onTrackReceived(event);
        this.peer.iceconnectionstatechange = (event) => this.onIceConnectionStateChange(event);
        this.peer.onsignalingstatechange = (event) => this.onSignalingStateChange(event);
        this.peer.onconnectionstatechange = (event) => this.onConnectionStateChange(event);
        this.peer.onAnswerGenerated = (params) => this.onAnswerGenerated(params);
        this.peer.onOfferGenerated = (params) => this.onOfferGenerated(params)
        this.peer.processSdp = (params) => this.onProcessSdp(params)

        return this;
    }

    onNegotiationNeeded() {
        console.log('onNegotiationNeeded', 'type', this.type, 'user_id', this.user_id);
        this.peer.makeOffer();
    }

    onIceCandidateGenerated(params) {
        var { candidate } = params;

        this.rtc_client.socketEmit({ 
            event: 'room:candidate', 
            data: { candidate, target_user_id: this.user_id, connection_type: this.type }
        });
    }

    onOfferGenerated(params) {
        var { offer } = params;

        this.rtc_client.socketEmit({ 
            event: 'room:offer', 
            data: { offer, target_user_id: this.user_id, connection_type: this.type } 
        });
    }

    onAnswerGenerated(params) {
        var { answer } = params;

        console.log('dddd onAnswerGenerated');

        this.rtc_client.socketEmit({ 
            event: 'room:answer', 
            data: { answer, target_user_id: this.user_id, connection_type: this.type }
        });
    }

    onIceConnectionStateChange() {
        console.log('onIceConnectionStateChange', this.peer.iceConnectionState);
    }

    onSignalingStateChange() {
        var signaling_state = this.peer.signalingState;
        
        console.log('peer.onsignalingstatechange', signaling_state);

        if(signaling_state == 'closed') {
            this.close();
        }
    }

    onConnectionStateChange() {
        var connection_state = this.peer.connectionState;
        
        console.log('peer.onconnectionstatechange connection_state', connection_state, 'type', this.type, 'user_id', this.user_id);
        
        if(connection_state == 'failed' && this.type == 'outgoing') {
            this.keepReconnecting();
        }

        if(connection_state == 'connected') {
            if(this.is_reconnecting){
                this.reconnected();
            }
        }
    }

    onOffer(params) {
        this.peer.onOffer(params);
    }

    onAnswer({ answer }) {
        console.log('dddd setRemoteDescription');
        answer = this.onProcessSdp({ sdp: answer });
        this.peer.setRemoteDescription(answer)
    }

    onCandidate({ candidate }) {
        console.log('dddd addIceCandidate');

        this.peer.addIceCandidate(candidate);
    }

    onTrackReceived({ track, streams }) {
        console.log('dddd onTrackReceived', track, streams);

        var stream = streams[0] || {};
        var stream_info = this.connector.streams_info[stream.id] || {};
        var track_type = stream_info.track_type || 'user';

        this.onTrack({ track, track_type });
    }

    onProcessSdp(params) {
        var { sdp } = params;

        if(!this.rtc_client.bandwidth) {
            return sdp;
        }

        sdp.sdp = this.peer.setBandwidthOverall({ sdp: sdp.sdp, bitrate: this.rtc_client.bandwidth.max_overall });

        return sdp;
    }

    addTrack(params) {
        console.log('ddddd addTrack', params);
        var { track, stream } = params;

        var participant = this.rtc_client.participants[this.user_id];
        if(this.rtc_client.user.is_bot && participant.user.is_bot) {
            console.log('addTrackToUser is_bot', this.user_id);
            return;
        }

        var already_added = this.peer.getSender({ track_id: track.id });
        console.log('dddd addtrack already_added', already_added);
        if(already_added) return;

        this.rtc_client.socketEmit({ 
            event: 'room:track-info', 
            data: { 
                track_id: track.id, 
                stream_id: stream.id, 
                track_type: track.track_info.type,
                target_user_id: this.user_id
            }
        });

        var sender = this.peer.addTrack(track, stream);
        this.applyBandwidth({ track, sender });
        this.onTrackAdded(params);
    }

    removeTrack(params) {
        var { track } = params;
        
        var sender = this.peer.getSender({ track_id: track.id });
        var transceivers = this.peer.getTransceivers();
        var transceiver = transceivers.find(t => t.sender === sender);

        console.log('removeTrack', track, sender, transceiver);
        if(!sender) return;

        this.peer.removeTrack(sender);
        // this is necessary to remove m-section from sdp in future negotiation
        // otherwise it will lead to incorrect estimation at b=as which we do to restrict bandwidth based upon m-sections
        if(transceiver) transceiver.stop();
        this.onTrackRemoved({ track });

        return true;
    }

    getTrack(params) {
        var { track_type, track_kind, get_fresh } = params;

        var { existing_track, track_underway, track_name } = super.getTrack(params);
        if(track_underway && !get_fresh) return;
        
        if(existing_track && !get_fresh) {
            this.onTrack({ track: existing_track, track_type });
            return;
        }

        this.trackUnderway({ track_name });

        // for video track need to request as video track is not added automatically by other peer
        // audio is added automatically so no action needed for that
        if(track_type == 'user' && track_kind == 'video') {
            this.requestGetTrack(params);
        }
    }

    requestGetTrack({ track_type, track_kind, user_id }) {
        console.log('requestGetTrack', user_id);
        this.rtc_client.socketEmit({ 
            event: 'room:request-get-track', 
            data: { track_type, track_kind, target_user_id: user_id }
        });
    }

    dropTrack({ track_type, track_kind, user_id }) {
        this.rtc_client.socketEmit({ 
            event: 'room:request-remove-track', 
            data: { track_type, track_kind, target_user_id: user_id }
        });
    }

    replaceTrack(params) {
        var { track } = params;

        var sender = this.peer.getSender({ track_name: track.track_info.name });
        if(!sender) return;

        sender.replaceTrack(track);
        this.onTrackReplaced(params);

        return true;
    }

    restartIce() {
        console.log('restartIce type', this.type, 'user_id', this.user_id);
        // restartIce() only triggers onnegotiationneeded first time and if other user is disconnected at that time
        // then on second restartIce it doesn't trigger onnegotiationneeded, so doing it manually
        // this.peer.restartIce();
        this.peer.makeOffer({ iceRestart: true });
    }

    getPeer() {
        return this.peer;
    }

    close(params = {}) {
        super.close(params);

        this.peer.close();
        this.peer = null;
    }

    reconnect() {
        console.log('reconnect type', this.type, 'user_id', this.user_id);
        super.reconnectInit();
        this.restartIce();
    }

    applyBandwidth(params) {
        this.doApplyBandwidth(params);

        setTimeout(() => {
            this.doApplyBandwidth(params);
        }, 5000);
    }

    doApplyBandwidth(params) {
        var { sender, track } = params;

        if(!this.rtc_client.bandwidth) return;

        // restricting upload bandwidth of the track, if applicable
        var parameters = sender.getParameters();
        var max_bitrate = this.rtc_client.bandwidth.max_overall;
        var track_type = track.track_info.type;
        
        if(track_type == 'user')
            max_bitrate = this.rtc_client.bandwidth[track.kind];
        else
            max_bitrate = this.rtc_client.bandwidth[track_type];
        
        console.log(
            'applyBandwidth track_type', track_type, 'track_kind', track.kind, 
            'max_bitrate', max_bitrate, 'bandwidth', this.rtc_client.bandwidth, 'total_encodings', parameters.encodings.length
        );
        
        if (!parameters.encodings || !parameters.encodings.length) {
            parameters.encodings = [{}];
        }
        
        parameters.encodings[0].maxBitrate = max_bitrate * 1000;

        sender.setParameters(parameters)
        .catch((e) => {
            console.log('applyBandwidth maxbitrate error', e);
        });
    }
}

export default PeerConnection