const { redis } = require("../config");

const userSockets = {};
const teacherSockets = {};

// Initialize Redis message listener once when the module loads
redis.on("message", (channel, message) => {
    try {
        const msgParsed = JSON.parse(message);
        console.log(`Received message from ${channel}:`, msgParsed);

        if (msgParsed.event === "live_request_accepted" && userSockets[msgParsed.data.student_id]) {
            userSockets[msgParsed.data.student_id].emit(msgParsed.event, msgParsed.data);
            return;
        }

        if (msgParsed.event === "live_payment_completed" && teacherSockets[msgParsed.data.teacher_id]) {
            teacherSockets[msgParsed.data.teacher_id].emit(msgParsed.event, msgParsed.data);
            return;
        }

        // Emit to all connected sockets for generic events
        Object.values(userSockets).forEach(socket => {
            socket.emit(msgParsed.event, msgParsed.data);
        });
        Object.values(teacherSockets).forEach(socket => {
            socket.emit(msgParsed.event, msgParsed.data);
        });

    } catch (error) {
        console.error('Error processing Redis message:', error);
    }
});

const onConnect = (socket) => {
    if (!socket.user) {
        console.log("Unauthorized user connected");
        return;
    }

    console.log(`A ${socket.user?.teacher || socket.user.logged_in_as === "teacher" ? "teacher" : socket.user?.student || socket.user.logged_in_as === "student" ? "student" : "user"} connected`, socket.user.id);
    
    // Store the socket reference
    if (socket.user?.teacher) {
        teacherSockets[socket.user.teacher.id] = socket;
    } else if (socket.user?.student) {
        userSockets[socket.user.student.id] = socket;
    } else if (socket.user?.logged_in_as === "teacher") {
        teacherSockets[socket.user.login_info.id] = socket;
    } else if (socket.user?.logged_in_as === "student") {
        userSockets[socket.user.login_info.id] = socket;
    }

    socket.emit("connected");

    // Clean up on disconnect
    const onDisconnect = () => {
        console.log("User disconnected", socket.user?.id);
        if (socket.user?.teacher?.id) {
            delete teacherSockets[socket.user.teacher.id];
        }
        if (socket.user?.student?.id) {
            delete userSockets[socket.user.student.id];
        }
        if (socket.user?.logged_in_as === "teacher") {
            delete teacherSockets[socket.user.login_info.id];
        }
        if (socket.user?.logged_in_as === "student") {
            delete userSockets[socket.user.login_info.id];
        }
    };

    socket.on("disconnect", onDisconnect);

    // Handle room joining
    socket.on("joinRoom", (data, ack) => {
        try {
            const roomData = typeof data === 'string' ? JSON.parse(data) : data;
            
            if (!roomData || !roomData.appointment_id) {
                return ack?.({
                    code: 400,
                    success: false,
                    status: "error",
                    msg: "appointment_id is required"
                });
            }

            socket.join(roomData.appointment_id);
            console.log(`User ${socket.user.id} joined room ${roomData.appointment_id}`);

            ack?.({
                code: 200,
                success: true,
                status: "success",
                msg: `Joined room ${roomData.appointment_id}`,
            });
        } catch (error) {
            console.error('Error in joinRoom:', error);
            ack?.({
                code: 500,
                success: false,
                status: "error",
                msg: "Internal server error"
            });
        }
    });
};

module.exports = { onConnect };