import PeerRtcClient from "../../peer/peer_rtc_client";

class SfuRtcClient extends PeerRtcClient {
    constructor(params) {
        super(params);

        this.room_type = 'browser-sfu';
    }

    attachSocketListeners() {
        super.attachSocketListeners();

        this.socket.on('speaking', (params) => {
            this._onSpeaking(params);
        });

        this.socket.on('stopped-speaking', (params) => {
            this._onStoppedSpeaking(params);
        });
    }

    _onSpeaking(params) {
        this.callCallback({
            callback: this.onSpeaking,
            data: params
        });
    }

    _onStoppedSpeaking(params) {
        this.callCallback({
            callback: this.onStoppedSpeaking,
            data: params
        });
    }
}

export default SfuRtcClient