function Track(params) {
    var { track } = params;

    track.is_custom = true;

    track.stopTrack = () => {
        console.log('tttttt stopTrack', track.readyState, track.is_stopped);

        if(track.is_stopped) return;

        track.enabled = false;
        track.is_stopped = true;
        
        track.stop();
        track.dispatchEvent(new Event("ended"));
    }

    return track;
}

export default Track