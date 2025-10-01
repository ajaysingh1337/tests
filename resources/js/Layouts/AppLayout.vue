<template>
    <div class="loader-container" :class="{ 'd-none': !showLoader }">
        <div class="loader">
            <img width="200" :src="$page.props.settings.logo" alt="logo" />
        </div>
        <div class="lds-ellipsis">
            <div></div>
            <div></div>
            <div></div>
            <div></div>
        </div>
    </div>
    <!-- Page Content -->
    <main class="page-content">
        <navbar @showLoaderEvent="showLoaderEvent"> </navbar>
        <header class="header">
            <slot name="header"> </slot>
        </header>
        <slot></slot>
        <Footer />
    </main>
    <div class="parallax bg-primary">
        <div class="text-center" style="margin-top: 160px">
            <h1 class="fw-bolder mb-0">{{ __("Become a Tutor?") }}</h1>
            <Link
                :href="route('register')"
                class="text-decoration-none fw-semibold btn btn-outline-light mb-5 shadow-none"
                >{{ __("Get Started Now") }}</Link
            >
            <!-- <button class="btn btn-outline-light mb-5 shadow-none">{{ __('Get Started Now') }}</button> -->
        </div>
    </div>
</template>

<script>
import { Head, Link } from "@inertiajs/inertia-vue3";
import Footer from "@/Layouts/AppIncludes/Footer.vue";
import Navbar from "@/Layouts/AppIncludes/Navbar.vue";
import { Inertia } from "@inertiajs/inertia";
import PusherMixin from "@/Mixins/PusherMixin.vue";
import { messaging, onMessage, getToken } from "@/Libraries/Firebase/firebase";
import { connect, state } from "@/Libraries/socket";
import "device-uuid";
import axios from "axios";

export default {
    props: {
        title: String,
        showLoader: Boolean,
    },
    mixins: [PusherMixin],

    components: {
        Head,
        Link,
        Footer,
        Navbar,
    },

    data() {
        return {
            showingNavigationDropdown: false,
            showLoader: false,
            pusherDeviceId: "",
            pusher_sender_id: "",
        };
    },
    watch: {
        "$page.props.flash.alert": {
            handler() {
                this.showAlert();
            },
            deep: true,
        },
    },
    created() {
        Inertia.on("start", (event) => {
            if (
                event.detail.visit.method == "get" &&
                event.detail.visit.url.pathname != this.path
            ) {
                this.showLoader = true;
            }
        });
        Inertia.on("finish", (event) => {
            this.showLoader = false;
        });
    },
    mounted() {
        this.showAlert();
        
        if (this.$page.props.auth && !state.connected) {
            connect(this.$page.props.auth);
        }
        
        if (this.$page.props.auth) {
            this.saveFcmToken()
                .then((success) => {
                    if (success) {
                        console.log("FCM token saved successfully");
                    }
                })
                .catch((error) => {
                    console.error("Error saving FCM token:", error);
                });

            if (this.$page.props.auth.logged_in_as == "teacher") {
                this.pusher_sender_id = this.$page.props.auth.user.teacher.id;
            }
            if (this.$page.props.auth.logged_in_as == "student") {
                this.pusher_sender_id = this.$page.props.auth.user.student.id;
            }
            if (this.$page.props.auth.logged_in_as == "academy") {
                this.pusher_sender_id = this.$page.props.auth.user.academy.id;
            }
        }

        // Listen for targeted live request prompts for teachers across all pages
        document.addEventListener("live_request_prompt", this.onLiveRequestPrompt);
        document.addEventListener("live_request_rejected", this.onLiveRequestRejection);
    },
    beforeUnmount() {
        document.removeEventListener("live_request_prompt", this.onLiveRequestPrompt);
        document.removeEventListener("live_request_rejected", this.onLiveRequestRejection);
    },
    methods: {
        showLoaderEvent(data) {
            this.showLoader = data;
        },
        logout() {
            if (this.$page.props.settings.pusher_beams_instance_id) {
                const VITE_PUSHER_BEAMS_INSTANCE_ID =
                    this.$page.props.settings.pusher_beams_instance_id;
                const beamsClient = new PusherPushNotifications.Client({
                    instanceId: VITE_PUSHER_BEAMS_INSTANCE_ID,
                });
                beamsClient
                    .clearAllState()
                    .then(() => {
                        console.log("Beams state has been cleared");
                    })
                    .catch((e) =>
                        console.error("Could not clear Beams state", e)
                    );
            }
            this.$inertia.post(route("logout"));
        },
        showAlert() {
            if (this.$page.props.flash.alert) {
                if (this.$page.props.flash.alert.type == "success") {
                    this.$toast.success(this.$page.props.flash.alert.message);
                } else if (this.$page.props.flash.alert.type == "error") {
                    this.$toast.error(this.$page.props.flash.alert.message);
                } else if (this.$page.props.flash.alert.type == "warning") {
                    this.$toast.warning(this.$page.props.flash.alert.message);
                } else if (this.$page.props.flash.alert.type == "info") {
                    this.$toast.info(this.$page.props.flash.alert.message);
                } else {
                    this.$toast.show(this.$page.props.flash.alert.message);
                }
            }
        },
        getDeviceId() {
            var du = new DeviceUUID().parse();
            var dua = {
                language: du.language,
                platform: du.platform,
                os: du.os,
                cpuCores: du.cpuCores,
                isAuthoritative: du.isAuthoritative,
                silkAccelerated: du.silkAccelerated,
                isKindleFire: du.isKindleFire,
                isDesktop: du.isDesktop,
                isMobile: du.isMobile,
                isTablet: du.isTablet,
                isWindows: du.isWindows,
                isLinux: du.isLinux,
                isLinux64: du.isLinux64,
                isMac: du.isMac,
                isiPad: du.isiPad,
                isiPhone: du.isiPhone,
                isiPod: du.isiPod,
                isSmartTV: du.isSmartTV,
                pixelDepth: du.pixelDepth,
                isTouchScreen: du.isTouchScreen,
            };
            var uuid = new DeviceUUID().get();

            // Store device info globally
            window.deviceInfo = dua;
            window.deviceId = uuid;

            return {
                deviceInfo: dua,
                deviceId: uuid,
            };
        },
        onLiveRequestPrompt(evt) {
            try {
                const payload = evt?.detail || {};
                const auth = this.$page?.props?.auth;
                if (!auth || auth.logged_in_as !== "teacher") return;
                const myTeacherId = auth.user?.teacher?.id;
                if (!myTeacherId || String(payload.teacher_id) !== String(myTeacherId)) return;

                // If this tab just rejected this very request, suppress any prompt
                try {
                    const rejectingId = sessionStorage.getItem('rejecting_live_request_id');
                    if (rejectingId && String(payload.live_request_id) === String(rejectingId)) {
                        return;
                    }
                } catch (e) {}

                // Deduplicate by live_request_id within session
                this._seenPrompts = this._seenPrompts || new Set();
                if (payload.live_request_id && this._seenPrompts.has(payload.live_request_id)) return;
                if (payload.live_request_id) this._seenPrompts.add(payload.live_request_id);

                const title = payload.title || "New Live Session Booking";
                const body = payload.body || "A student has requested a live session with you";
                const deepLink = payload.deep_link;

                // Show a minimal, universal prompt
                // const proceed = window.confirm(`${title}\n\n${body}\n\nOpen now?`);
                this.$swal.fire({
                    title: title,
                    text: body,
                    icon: 'info',
                    confirmButtonText: 'Open',
                    showCancelButton: true,
                    cancelButtonText: 'Cancel',
                }).then((result) => {
                    if (result?.isConfirmed && deepLink) {
                        try {
                            this.$inertia.visit(deepLink);
                        } catch (e) {
                            window.location.href = deepLink;
                        }
                    }
                });
            } catch (e) {
                console.error("Error handling live_request_prompt:", e);
            }
        },
        onLiveRequestRejection(evt) {
            try {
                this.$swal.close();
            } catch (e) {
                console.error("Error handling live_request_rejected:", e);
            }
        },
        getFcmToken() {
            return new Promise(async (resolve, reject) => {
                try {
                    const permission = await Notification.requestPermission();
                    if (permission === "granted") {
                        // Manually register the service worker
                        const registration =
                            await navigator.serviceWorker.register(
                                "/firebase-messaing-sw.js"
                            );

                        const token = await getToken(messaging, {
                            vapidKey: import.meta.env.VITE_VAPID_KEY,
                            serviceWorkerRegistration: registration,
                        });

                        console.log("FCM Token:", token);
                        window.fcmToken = token; // Store token globally
                        resolve(token);
                    } else {
                        console.warn("Notification permission denied");
                        resolve(null);
                    }
                } catch (error) {
                    console.error("Error getting FCM token:", error);
                    resolve(null);
                }
            });
        },
        saveFcmToken() {
            return new Promise(async (resolve, reject) => {
                try {
                    // Make sure we have both token and device info
                    if (!window.fcmToken) {
                        window.fcmToken = await this.getFcmToken();
                    }

                    if (!window.deviceInfo || !window.deviceId) {
                        this.getDeviceId();
                    }

                    if (!window.fcmToken) {
                        console.warn("No FCM token available to save");
                        resolve(false);
                    }

                    const response = await axios.post("/save_fcm_token", {
                        token: window.fcmToken,
                        device_id: window.deviceId,
                        device_info: window.deviceInfo,
                    });

                    console.log("FCM token saved successfully:", response.data);
                    resolve(true);
                } catch (error) {
                    console.error("Error saving FCM token:", error);
                    resolve(false);
                }
            });
        },
    },

    computed: {
        path() {
            return window.location.pathname;
        },
    },
};
</script>
