import { reactive } from "vue";
import { io } from "socket.io-client";

export const state = reactive({
    connected: false,
});

// "undefined" means the URL will be computed from the `window.location` object
const URL = import.meta.env.VITE_SOCKET_URL || "http://localhost:4000";

export let socket = null;

export const connect = (auth = null) => {
    socket = io(URL, {
        query: {
            isEncrypted: false,
        },
        ...(auth && {
            auth: auth,
        }),
        transports: ["websocket"],
        forceNew: true,
        autoConnect: true,
    });

    socket.on("connect", () => {
        state.connected = true;
    });

    socket.on("disconnect", () => {
        state.connected = false;
    });

    socket.on("live_payment_completed", (appointment) => {
        appointment =
            typeof appointment === "string"
                ? JSON.parse(appointment)
                : appointment;
        const myCustomEvent = new CustomEvent("live_payment_completed", {
            detail: appointment,
            bubbles: true,
            cancelable: false,
        });
        document.dispatchEvent(myCustomEvent);
        setTimeout(() => {
            window.location.href = `${window.location.origin}/call/${appointment.id}?hasVideo=1`;
        }, 1000);
    });

    // Targeted prompt for teachers when a live request is created/advanced to them
    socket.on("live_request_prompt", (payload) => {
        try {
            payload = typeof payload === "string" ? JSON.parse(payload) : payload;
        } catch (e) {
            // ignore JSON parse errors and use raw payload
        }

        const evt = new CustomEvent("live_request_prompt", {
            detail: payload,
            bubbles: true,
            cancelable: false,
        });
        document.dispatchEvent(evt);
    });

    socket.on("live_request_rejected", (payload) => {
        try {
            payload = typeof payload === "string" ? JSON.parse(payload) : payload;
        } catch (e) {
            // ignore JSON parse errors and use raw payload
        }

        const evt = new CustomEvent("live_request_rejected", {
            detail: payload,
            bubbles: true,
            cancelable: false,
        });
        document.dispatchEvent(evt);
    });
};
