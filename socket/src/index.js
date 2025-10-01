require("dotenv").config();
const express = require("express");
const useragent = require("express-useragent");
const bodyParser = require("body-parser");
const cors = require("cors");
const axios = require("axios");
const compression = require("compression");
const cookieParser = require("cookie-parser");
const debug = require("debug");
const helmet = require("helmet");
const logger = require("morgan");
const headers = require("./middlewares/headers");
const logFunc = require("./helpers/requestHelper");
const socketIO = require("socket.io");

const { appName, redis, port, config, env } = require("./config");
const { handleError } = require("./helpers/routing");
const { onConnect } = require("./socket/socketEventHandlers");
const checkSocketAuth = require("./middlewares/checkSocketAuth");

const app = express();
const dbg = debug(`${appName}:app`);

app.use(helmet(headers));
app.use(useragent.express());
app.use(express.json({ limit: "50mb" }));
app.use(bodyParser.json(), cors());
app.options(/(.*)/s, cors());
app.use(compression());
app.use(cookieParser());
app.use(express.urlencoded({ extended: false, limit: "50mb" }));
app.set("port", port);
app.set("trust proxy", true);

//logs
app.use(logger("dev", { stream: { write: (msg) => dbg(msg) } }));
axios.interceptors.request.use(logFunc);
axios.interceptors.response.use(logFunc);

//log request
app.use((req, res, next) => {
    console.log(req.path, req.method);

    next();
});

//routes
app.use("/", async (req, res) => {
    const pong = await redis.ping();
    res.json({
        msg: `Welcome to the ${appName} Socket.io Server`,
        redis_connected: pong === "PONG" ? true : false,
    });
});

//handle error
app.use((err, req, res, next) => {
    const status = err.status || 500;
    const title = `Error ${err.status}`;

    if (err) dbg(`${title} %s`, err.stack);

    res.status(status).json({
        error: req.app.get("env") === "development" ? err : {},
        msg: err.message,
    });
});

//handle 404
app.get(/(.*)/s, (req, res, next) => {
    try {
        if (req.url.endsWith("/")) {
            return next();
        }
        //return 404
        return res.status(404).json({ msg: "Not Found" });
    } catch (e) {
        next(handleError(e));
    }
});

const server = app.listen(port, async () => {
    console.log(`listenning on port ${port} on ${env} Environment`);
});
const io = socketIO(server, {
    maxHttpBufferSize: 1e8,
    pingTimeout: 60000,
    cors: {
        origin: "*",
        methods: ["GET", "POST"],
        credentials: true,
    },
});

redis.subscribe("ogoultutor_database_events", (err, count) => {
    if (err) {
        console.error("Failed to subscribe:", err);
    } else {
        console.log(
            `Subscribed successfully! This client is currently subscribed to ${count} channel(s).`
        );
    }
});

// redis.on("message", (channel, message) => {
// 	console.log(`Received message from ${channel}:`, JSON.parse(message));
//     //send to all users in the room
//     io.emit(message.event, JSON.stringify(message.data));
// });

io.use(checkSocketAuth).on("connection", onConnect);
// io.on("connection", onConnect);

module.exports = app;
