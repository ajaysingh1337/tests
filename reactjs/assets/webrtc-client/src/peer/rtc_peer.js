function RtcPeer({ rtc_config, is_polite }) {
    this.peer = new RTCPeerConnection(rtc_config);

    this.peer.onOffer = async (params) => {
        var { offer } = params;

        console.log('ddddd onoffer is_polite', is_polite);

        var offer_collision = false;
        
        //collision of offers
        //i received and offer white making an offer or i received and offer while i didn't need one i.e my signaling state is table
        if(this.peer.making_offer || this.peer.signalingState != "stable"){
            offer_collision = true;
        }

        console.log('ddddd onoffer offer_collision', offer_collision);

        // ignore offer
        // if i am impolite and there is some offer collision then i will not entertain that offer
        // I will stick to my offer and polite and has to answer my offer
        if(!is_polite && offer_collision) {
            return;
        }

        console.log('ddddd onoffer setRemoteDescription');
        
        this.peer.setRemoteDescription(offer);
        // await this.peer.setLocalDescription();

        var answer = await this.peer.createAnswer();
        answer = this.peer.processSdp({ sdp: answer });
        await this.peer.setLocalDescription(answer);

        this.peer.onAnswerGenerated({ answer });
    }

    this.peer.makeOffer = async (offer_options = {}) => {
        console.log('ddddd makeOffer offer_options', offer_options);

        try {
            this.peer.making_offer = true;
            
            var offer = await this.peer.createOffer(offer_options);
            offer = this.peer.processSdp({ sdp: offer });
            await this.peer.setLocalDescription(offer);

            console.log('ddddd makeOffer setLocalDescription');
            
            this.peer.onOfferGenerated({ offer: offer || this.peer.localDescription });
        
        } finally {
            this.peer.making_offer = false;
        }
    }

    this.peer.getSender = (params) => {
        var { track_id, track_name } = params;

        console.log('rtc_peer getSender', track_id, track_name);

        var sender = false

        this.peer.getSenders().forEach(sender_1 => {
            var track = sender_1.track || {};
            var track_info = track.track_info || {};

            console.log('replaceTrack sender', track, track_info);

            if(track_id && track.id == track_id){
                sender = sender_1;
            }

            if(track_name && track_info.name == track_name){
                sender = sender_1;
            }
        });

        return sender;
    }

    this.peer.setBandwidthForBundleStreams = ({ sdp, bandwidth }) => {
        if(!bandwidth) return sdp;

        var audio_bitrate = bandwidth.audio;
        var video_bitrate = bandwidth.video;

        var regex = new RegExp(`m=(audio).*?\\r\\n`, 'g');
        sdp = sdp.replace(regex, function (match) {
            console.log('match', match);
            return match + `b=AS:${audio_bitrate}\r\n`;
        });

        var video_sections = countVideoSections(sdp) || 1;
        video_sections = video_sections > 2 ? 2 : video_sections;
        var final_video_bitrate = video_bitrate * video_sections;

        var regex = new RegExp(`m=(video).*?\\r\\n`, 'g');
        sdp = sdp.replace(regex, function (match) {
            console.log('match', match);
            return match + `b=AS:${final_video_bitrate}\r\n`;
        });

        console.log('setBandwidthForBundleStreams', bandwidth, audio_bitrate, video_bitrate, final_video_bitrate, video_sections);

        function countVideoSections(sdp) {
            // Split the SDP into lines
            const lines = sdp.split("\n");
            let videoCount = 0;
        
            // Loop through each line and check for 'm=video'
            for (const line of lines) {
                if (line.startsWith("m=video")) {
                    videoCount++;
                }
            }
        
            return videoCount;
        }

        return sdp;
    }

    this.peer.setBandwidthOverall = ({ sdp, bitrate }) => {
        console.log('setBandwidthOverall bitrate', bitrate);

        var regex = new RegExp(`m=(video|audio).*?\\r\\n`, 'g');
        sdp = sdp.replace(regex, function (match) {
            console.log('setBandwidthOverall match', match);
            return match + `b=AS:${bitrate}\r\n`;
        });

        return sdp;
    }

    return this.peer;
}

export default RtcPeer