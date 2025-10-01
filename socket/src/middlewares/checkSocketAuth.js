module.exports = async (socket, next) => {
    if (socket.handshake.auth) {
        console.log(socket.handshake.auth);
        socket.user = socket.handshake.auth.user;
    } else {
        next(new Error("Authentication error"));
    }
    next();
};
