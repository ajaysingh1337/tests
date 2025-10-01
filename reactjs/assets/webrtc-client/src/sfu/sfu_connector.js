import Connector from "../connector";
import * as mediasoup_client from "mediasoup-client"; 
import SfuConnection from "./sfu_connection";

class SfuConnector extends Connector {
    constructor(params) {
        super(params);

        this.type = 'sfu';
        this.device = false;
        this.producers = {};

        this.user_encodings = [
            {
              maxBitrate: 100000,
              scalabilityMode: 'S1T3',
            },
            {
              maxBitrate: 500000,
              scalabilityMode: 'S1T3',
            }
        ];

        this.screen_encodings = [
            {
              maxBitrate: 1000000
            },
        ];
        
        this.codec_options = {
            videoGoogleStartBitrate: 1000
        }

        this.rtc_client.socket_events = [
            ...this.rtc_client.socket_events,
            'room:consumer-closed',
            'room:producer-closed',
            'room:transport-closed',
            'room:new-producer',
            'room:get-producer'
        ]

        this.attachSocketListeners();
    }

    attachSocketListeners() {
        this.rtc_client.socket.on('room:consumer-closed', (params) => {
            this.onServerConsumerClosed(params);
        });

        this.rtc_client.socket.on('room:producer-closed', (params) => {
            this.onServerProducerClosed(params);
        });

        this.rtc_client.socket.on('room:transport-closed', (params) => {
            this.onServerTransportClosed(params);
        });

        this.rtc_client.socket.on('room:new-producer', (params) => {
            this.onNewProducer(params);
        });

        this.rtc_client.socket.on('room:get-producer', (params) => {
            this.onGetProducer(params);
        });
    }

    async prepare({ router }) {
        this.device = new mediasoup_client.Device();
        await this.device.load({ routerRtpCapabilities: router.rtp_capabilities });
    }

    async initiate() {
        super.initiate();
        this.considerAllProducersNew();
    }

    replaceTrack(params) {
        var { track } = params;

        var track_kind = track.kind;
        var track_type = track.track_info.type;

        var connection = this.getProducerConnection({ track_kind, track_type });
        connection && connection.replaceTrack(params);
    }

    addTrack({ track, track_type, stream }) {
        var producer_name = SfuConnection.makeProducerName({ user_id: this.rtc_client.user.id, track_type, track_kind: track.kind });
        var connection = this.getOrMakeConnection({ 
            id: producer_name,
            type: 'producer', 
            user: this.rtc_client.user,
            producer_name,
            track_type, 
            track_kind: track.kind
        });
                
        connection.addTrack({
            track,
            track_type,
            stream
        });
    }

    removeTrack({ track_type, track_kind }) {
        var connection = this.getProducerConnection({ track_type, track_kind });
        connection && connection.close();
    }

    pauseTrack({ track_kind }) {
        var connection = this.getProducerConnection({ track_kind });
        connection && connection.pauseProducer();
    }

    resumeTrack({ track_kind }) {
        var connection = this.getProducerConnection({ track_kind });
        connection && connection.resumeProducer();
    }

    getTrack({ user_id, track_type, track_kind }) {
        var producer_name = SfuConnection.makeProducerName({ user_id, track_type, track_kind });
        var producer_available = this.producers[producer_name];
        
        console.log(
            'ttttt getTrack',
            'track_type', track_type, 
            'track_kind', track_kind,
            'producer_name', producer_name,
            'producer_available', producer_available
        );

        if(!producer_available) return;

        var consumer_name = SfuConnection.makeConsumerName({ user_id, track_type, track_kind });
        var connection = this.getOrMakeConnection({
            id: consumer_name,
            type: 'consumer', 
            user: this.rtc_client.participants[user_id],
            producer_name,
            track_type, 
            track_kind
        });
                
        connection && connection.getTrack({
            user_id, 
            track_type, 
            track_kind
        });
    }

    dropTrack({ user_id, track_type, track_kind }) {
        var connection = this.getConsumerConnection({ user_id, track_type, track_kind });
        connection && connection.close();
    }

    onLocalTrackEnded(params){
        var { track } = params;
        
        var connection = this.connections[track.track_info.connection_id];
        connection && connection.close();
    }

    onJoined({ user, available_producers }) {
        if(user.id == this.rtc_client.user.id) {
            this.producers = available_producers;

            this.initiate();
        }
    }

    async onPostJoinData({ available_producers }) {
        this.producers = available_producers;
        this.considerAllProducersNew();
    }

    considerAllProducersNew() {
        Object.values(this.producers).forEach(producer => {
            this.onNewProducer({ producer, user_id: producer.user_id });
        });
    }

    getOrMakeConnection(params) {
        var { id, user, type } = params;

        console.log(
            'ttttt getOrMakeConnection',
            'id', id,
            'type', type,
            'existing_connection', this.connections[id],
            'params', params
        );

        if(this.connections[id]) return this.connections[id];

        params.rtc_client = this.rtc_client;
        params.connector = this;
        
        var connection = new SfuConnection(params);
        this.connections[connection.id] = connection;
        this.connections_wrt_user[user.id] = this.connections_wrt_user[user.id] || [];
        this.connections_wrt_user[user.id].push(connection.id);

        return connection;
    }

    _onConnectionReconnecting(params) {
        var { connection } = params;

        var user_id = connection.user_id;
        var type = connection.type;

        if(type != 'producer') return;

        Object.values(this.connections).forEach(connection => {

            if(connection.user_id != user_id || connection.type != 'consumer') return;

            connection.onOtherEndReconnecting(params);
        });
    }

    _onConnectionReconnected(params) {
        var { connection } = params;

        var user_id = connection.user_id;
        var type = connection.type;

        if(type != 'producer') return;

        Object.values(this.connections).forEach(connection => {

            if(connection.user_id != user_id || connection.type != 'consumer') return;

            connection.onOtherEndReconnected(params);
        });
    }

    onServerConsumerClosed(params) {
        Object.values(this.connections).forEach((connection) => {
            connection.onServerConsumerClosed(params);
        });
    }

    onServerTransportClosed(params) {
        Object.values(this.connections).forEach((connection) => {
            connection.onServerTransportClosed(params);
        });
    }

    onServerProducerClosed(params) {
        var { producer_name } = params;
        
        delete this.producers[producer_name];
    }

    onNewProducer(params) {
        var { producer, user_id } = params;

        console.log('sfu_rtc_client onNewProducer', producer);

        this.producers[producer.name] = producer;
        var track_kind = producer.track_kind.charAt(0).toUpperCase() + producer.track_kind.slice(1);
        var track_type = producer.track_type.charAt(0).toUpperCase() + producer.track_type.slice(1);

        Object.values(this.connections).forEach((connection) => {
            if(connection.user_id != user_id) return;

            connection.onNewProducer(params);
        });

        this.rtc_client.callCallback({
            callback_name: `onPotential${track_type}${track_kind}`,
            data: { user_id }
        });
    }

    onGetProducer(params) {
        var { producer_name, user_id, socket_response_event } = params;

        var producer = false;
        var connection = this.connections[producer_name];

        console.log('onGetProducer', connection);

        if(connection && !connection.is_reconnecting) {
            var connection_producer = connection.producer;
            
            producer = { 
                id: connection_producer.id,
                name: connection_producer.producer_name,
                transport_id: connection.transport.id
            }
        }

        this.socketEmit({
            data: {
                producer,
                to_user_id: user_id,
                socket_response_event
            }
        });
    }

    getProducerConnection({ track_type = 'user', track_kind }) {
        var connection_id = SfuConnection.makeProducerName({
            user_id: this.rtc_client.user.id,
            track_type,
            track_kind
        });

        console.log('tttttt getProducerConnection', connection_id, this.connections[connection_id]);

        return this.connections[connection_id];
    }

    getConsumerConnection({ user_id, track_type = 'user', track_kind }) {
        var connection_id = SfuConnection.makeConsumerName({
            user_id,
            track_type,
            track_kind
        });

        console.log('tttttt getConsumerConnection', connection_id, this.connections[connection_id]);

        return this.connections[connection_id];
    }
}

export default SfuConnector