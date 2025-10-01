class RtcHelpers {
    constructor(options) {
        this.socket = options.socket;
        this.user = options.user;

        this.callbacks = [
            'onInvitation',
            'onInvitationCancelled'
        ];
        
        this.enableMultipleCallbacks();
        this.attachSocketListeners();
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
                        
                        console.log('rtc_helpers setter: ' + name); 
                    }
                }
            );
        });
    }

    attachSocketListeners() {
        if(!this.socket) return;

        this.socket.on('room:invitation', (data) => {
            console.log('room:invitation', data);
            this.callCallback({ name: 'onInvitation', data });
        });

        this.socket.on('room:invitation-cancelled', (data) => {
            console.log('room:invitation-cancelled', data);
            this.callCallback({ name: 'onInvitationCancelled', data });
        });
    }

    acceptInvitation(params) {
        this.socket.emit('room:invitation-updated', { ...params, status: 'accepted' });
    }

    rejectInvitation(params) {
        this.socket.emit('room:invitation-updated', { ...params, status: 'rejected' });
    }

    getRoom(params) {
        return new Promise((resolve) => {
            this.socket.emit('get-room', params, (room) => {
                resolve(room);
            });
        });
    }

    getUserRooms(params) {
        return new Promise((resolve) => {
            this.socket.emit('get-user-rooms', params, (rooms) => {
                resolve(rooms);
            });
        });
    }

    leaveAllRooms(params) {
        return new Promise((resolve) => {
            this.socket.emit('leave-all-rooms', params, () => {
                resolve();
            });
        });
    }

    deleteRoom(params) {
        this.socket.emit('delete-room', params);
    }

    saveToCache(params) {
        return new Promise((resolve) => {
            this.socket.emit('save-to-cache', params, () => {
                resolve();
            });
        });
    }

    saveToCacheTemporarily(params) {
        return this.saveToCache(params);
    }

    getFromCache(params) {
        return new Promise((resolve) => {
            this.socket.emit('get-from-cache', params, (data_json) => {
                resolve(data_json);
            });
        });
    }

    socketEmit(params) {
        var { event, data = {}, return_response = false } = params;
        console.log('socket emit', event, data);

        if(!return_response)
            return this.socket.emit(event, data);

        return new Promise((resolve, reject) => {
            this.socket.emit(event, data, (data) => {
                resolve(data);
            });
        });
    }

    notifyTheseUsers(params) {
        var { event, user_ids, data = {} } = params;

        data.event = event;
        data.user_ids = user_ids;

        this.socketEmit({ event: 'notify-these-users', data });
    }

    callCallback(params) {
        var { name, data = {} } = params;

        console.log('rtc_helpers callCallback', name, data);

        var callback = this[name];
        if(!callback) return;
        
        // use attached multiple callbacks so calling them all
        if(Array.isArray(callback)) {
            callback.forEach(call => {
                call(data);
            });
        
        }else {
            callback(data);
        }
    }
}

export default RtcHelpers