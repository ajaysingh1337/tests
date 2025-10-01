import RtcConnection from "../rtc_connection";

class SfuConnection extends RtcConnection {
    constructor(params) {
        var { user, track_type, track_kind, producer_name } = params;

        super(params);

        this.transport = {};
        this.producer = {};
        this.consumer = {};
        // for reconnection
        this.old_outgoing_track = false;

        this.track_type = track_type;
        this.track_kind = track_kind;
        this.producer_name = producer_name;

        return this;
    }

    async addTrack(params) {
        var { track, track_type } = params;

        console.log('addTrack init');

        super.addTrack(params);

        var transport = await this.createProducerTransport();
        if(!transport) { this.retryAddTrack(params); return; }

        var producer = await this.produceTrack({  
            track, 
            track_type 
        });

        if(!producer) { this.retryAddTrack(params); return; }

        this.onTrackAdded(params);
    }

    removeTrack(params) {
        var { track } = params;

        this.closeProducer();
        this.onTrackRemoved({ track });
    }

    replaceTrack(params) {
        var { track } = params;

        if(!this.producer.id) return;

        this.producer.producer.replaceTrack({ track });
        this.onTrackReplaced(params);

        return true;
    }

    async getTrack(params) {
        var { user_id, track_type, track_kind, get_fresh = false } = params;

        console.log('sfu_rtc_client getTrack init');

        var producer_available = this.connector.producers[this.producer_name];
        if(!producer_available) return;
        
        var { track_underway, existing_track, track_name } = super.getTrack(params);
        if(!get_fresh && track_underway) return;

        if(get_fresh && existing_track) {
            this.dropTrack({ track: existing_track });
            
        } else if(existing_track) {
            this.onTrack({ track: existing_track, track_type });

            return;
        };

        this.trackUnderway({ track_name });

        var transport = await this.createTransport({ transport_type: 'consumer' });
        if(!transport) { this.retryGetTrack(params); return; }
        
        var track = await this.consumeTrack({ user_id, track_type, track_kind });
        if(!track) { this.retryGetTrack(params); return; }

        this.onTrack({ track, track_type });
    }

    dropTrack(params) {
        var { track } = params;

        if(!track) return;

        this.closeConsumer();
        this.closeTransport();

        this.consumer = {};
    }

    getScreenStream(params = {}) {
        params.user_id = this.user_id;
        params.track_type = 'screen';
        params.track_kind = 'video';

        return this.getTrack(params);
    }

    dropScreenStream() {
        var track_id = this.tracks_wrt_name['screen_video_incoming'];
        var track = this.tracks[track_id];

        this.dropTrack({ track });
    }

    dropUserStream() {
        var video_track_id = this.tracks_wrt_name['user_video_incoming'];
        var audio_track_id = this.tracks_wrt_name['user_audio_incoming'];
        
        var video_track = this.tracks[video_track_id];
        var audio_track = this.tracks[audio_track_id];

        this.dropTrack({ track: video_track });
        this.dropTrack({ track: audio_track });
    }

    createServerTransport(params) {
        var { transport_type } = params;

        return this.rtc_client.socketEmit({
            event: 'room:create-transport',
            return_response: true,
            data: { 
                connection_id: this.id,
                transport_type 
            } 
        });
    }

    async createTransport(params) {
        var { transport_type } = params;

        console.log('createTransport init');

        var { transport: server_transport } = await this.createServerTransport({
            transport_type
        });

        if(!server_transport) return;

        var create_transport_params = {
            id: server_transport.id,
            iceParameters: server_transport.ice_parameters,
            iceCandidates: server_transport.ice_candidates,
            dtlsParameters: server_transport.dtls_parameters,
            iceServers: this.rtc_client.rtc_config.iceServers
        };

        if(transport_type == 'producer') {
            var transport = this.connector.device.createSendTransport(create_transport_params);
        
        }else {
            var transport = this.connector.device.createRecvTransport(create_transport_params);
        }

        if(!transport) return;

        transport.on('connect', async (connect_parameters, callback, errback) => {

            await this.rtc_client.socketEmit({
                event: 'room:connect-transport',
                data: { 
                    transport_id: transport.id, 
                    connect_parameters: {
                        dtls_parameters: connect_parameters.dtlsParameters
                    }
                }
            });

            callback();
        });

        transport.handler._pc.onconnectionstatechange = (event) => {
            var connection_state = transport.handler._pc.connectionState;

            console.log('transport.handler._pc onconnectionstatechange', connection_state);

            if(connection_state == 'failed') {
                this.keepReconnecting();
            }

            if(connection_state == 'connected') {
                if(this.is_reconnecting){
                    this.reconnected();
                }
            }
        };

        this.transport = {
            id: transport.id,
            transport
        };

        return this.transport;
    }

    async createProducerTransport() {
        if(this.transport.id) return this.transport;

        var transport = await this.createTransport({ transport_type: 'producer' });
        if(!transport) return;

        var transport = transport.transport;

        transport.on('produce', async (produce_parameters, callback, errback) => {
            var track_type = produce_parameters.appData.track_type;
            var track_kind = produce_parameters.kind;

            var { producer: server_producer } = await this.rtc_client.socketEmit({
                event: 'room:produce-transport',
                return_response: true,
                data: {
                    transport_id: transport.id,
                    producer_name: this.producer_name,
                    track_type,
                    track_kind,
                    produce_parameters: {
                        kind: produce_parameters.kind,
                        rtp_parameters: produce_parameters.rtpParameters
                    }
                }
            });

            if(!server_producer) { errback(); return; }

            callback({ id: server_producer.id });
        });

        return transport;
    }

    async produceTrack(params) {
        var { track, track_type } = params;
        
        var encodings = this.connector[track_type + '_encodings'] || [];
        if(track.kind == 'audio') encodings = [];

        var producer = await this.transport.transport.produce({
            track,
            encodings,
            codecOptions: this.connector.codec_options,
            stopTracks: false,
            disableTrackOnPause: false,
            zeroRtpOnPause: true,
            appData: {
                track_type
            }
        }).catch((e) => { console.log('produceTrack produce catch', e) });

        if(!producer) return;

        producer.on("trackended", () => {
            console.log('producer trackended');
        });

        this.producer = {
            id: producer.id,
            producer,
            producer_name: this.producer_name
        };

        return producer;
    }

    async consumeTrack(params) {
        var { track_type, track_kind } = params;

        var transport_id = this.transport.id;

        var available_producer = this.connector.producers[this.producer_name];
        // during consume process, producer might have got closed
        if(!available_producer) return;

        var { consumer: server_consumer } = await this.rtc_client.socketEmit({
            event: 'room:consume-transport',
            return_response: true,
            data: {
                transport_id,
                producer_id: available_producer.id,
                producer_transport_id: available_producer.transport_id,
                device_rtp_capabilities: this.connector.device.rtpCapabilities
            }
        });

        if(!server_consumer) return;

        var consumer = await this.transport.transport.consume({
            id: server_consumer.id,
            producerId: server_consumer.producer_id,
            kind: server_consumer.kind,
            rtpParameters: server_consumer.rtp_parameters
        
        }).catch((e) => { console.log('consumeTrack produce catch', e) });

        if(!consumer) return;

        consumer.on("trackended", () => {
            console.log('consumer trackended');
        });

        consumer.observer.on("pause", () => {
            console.log('pause');
        });

        consumer.observer.on("close", () => {
            console.log('close');
        });
        
        this.rtc_client.socketEmit({
            event: 'room:resume-consumer',
            data: {
                consumer_id: consumer.id,
                transport_id
            }
        });

        var track = consumer.track;

        this.consumer = {
            id: consumer.id,
            consumer,
            producer_id: server_consumer.producer_id,
            transport_id,
            producer_name: this.producer_name,
            track_type,
            track_kind
        };

        return track;
    }

    static makeProducerName(params) {
        var { user_id, track_type, track_kind } = params;

        return `${user_id}_${track_type}_${track_kind}_producer`;
    }

    static makeConsumerName(params) {
        var { user_id, track_type, track_kind } = params;

        return `${user_id}_${track_type}_${track_kind}_consumer`;
    }

    pauseProducer() {
        this.producer.producer.pause();
        
        this.rtc_client.socketEmit({
            event: 'room:pause-producer',
            data: {
                producer_id: this.producer.id,
                transport_id: this.transport.id
            }
        });
    }

    resumeProducer() {
        this.producer.producer.resume();
        
        this.rtc_client.socketEmit({
            event: 'room:resume-producer',
            data: {
                producer_id: this.producer.id,
                transport_id: this.transport.id
            }
        });
    }

    closeProducer() {
        var producer = this.producer;
        if(!producer.id) return;

        producer.producer.close();

        this.rtc_client.socketEmit({
            event: 'room:close-producer',
            data: {
                producer_id: producer.id,
                transport_id: this.transport.id,
                is_reconnecting: this.is_reconnecting
            }
        });

        this.producer = {};
    }

    closeConsumer() {
        var consumer = this.consumer;
        if(!consumer.id) return;

        var track = consumer.consumer.track;

        consumer.consumer.close();
        track.stopTrack();

        this.rtc_client.socketEmit({
            event: 'room:close-consumer',
            data: {
                consumer_id: consumer.id,
                transport_id: consumer.transport_id
            }
        });

        this.consumer = {};
    }

    closeTransport() {
        if(!this.transport.id) return;

        this.transport.transport.close();
        this.transport = {};
    }

    close(params) {
        super.close(params);

        this.closeProducer();
        this.closeConsumer();
        this.closeTransport();
    }

    closeConnection() {
        this.closeTransport();
    }

    async restartIce() {
        var { ice_parameters } = await this.rtc_client.socketEmit({
            event: 'room:restart-ice',
            data: { 
                transport_id: this.transport.id,
            },
            return_response: true
        });

        console.log('reconnect ice_parameters', ice_parameters)

        if(!ice_parameters) return;

        this.transport.transport.restartIce({ iceParameters: ice_parameters });
    }

    async reconnect() {
        super.reconnectInit();
        this.restartIce();
    }

    mayBeRefreshConsumer({ producer }) {
        var consumer = this.consumer;
        
        console.log(
            'mayBeRefreshConsumer', 
            'producer', producer, 
            'consumer', consumer
        );
        
        if(this.producer_name != producer.name) return;

        console.log('mayBeRefreshConsumer refreshing');

        this.getTrack({
            user_id: this.user_id,
            track_type: this.track_type,
            track_kind: this.track_kind,
            get_fresh: true
        });
    }

    getPeer() {
        var transport = this.transport.transport || {};
        return transport.handler && transport.handler._pc;
    }

    onServerConsumerClosed(params) {
        var { consumer_id } = params;

        if(this.consumer.id != consumer_id) return;

        this.closeConsumer();
    }

    onServerTransportClosed(params) {
        var { transport_id } = params;

        if(this.transport.id != transport_id) return;
        
        this.closeTransport();
    }
    
    onNewProducer(params) {
        var { producer } = params;

        console.log('sfu_rtc_connection onNewProducer', producer, this.consumer);

        if(this.type == 'producer') return;
        if(producer.id == this.consumer.producer_id) return;
        
        this.mayBeRefreshConsumer({ producer });
    }
}

export default SfuConnection