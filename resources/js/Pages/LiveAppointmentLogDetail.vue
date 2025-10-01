<template>
    <app-layout title="Live Appointment Request">
        <template #default>
            <div class="container-fluid py-4">
                <!-- Page Header -->
                <div class="row">
                    <div class="col-12 mt-2 text-end">
                        <a href="javascript:history.back()" class="btn btn-primary mb-3">
                            &larr; Back
                        </a>
                    </div>
                    <div class="col-12 mt-4 text-center">
                        <h2 class="fs-2 fw-bold text-primary">Live Session Request</h2>
                        <p class="text-muted">Review and respond to the student's request</p>
                    </div>
                </div>

                <!-- Request Card -->
                <div class="card shadow-sm">
                    <div class="card-header bg-light">
                        <h3 class="card-title mb-0">Session Details</h3>
                    </div>
                    
                    <div class="card-body">
                        <!-- Student Info -->
                        <div class="d-flex align-items-center mb-4">
                            <img 
                                :src="appointment.student?.image || '/images/default-avatar.png'" 
                                alt="Student" 
                                class="rounded-circle me-3"
                                style="width: 64px; height: 64px; object-fit: cover;"
                            >
                            <div>
                                <h4 class="h5 mb-1">
                                    {{ appointment.student?.first_name }} {{ appointment.student?.last_name }}
                                </h4>
                                <p class="text-muted mb-0">
                                    {{ appointment.student?.user?.email || 'No email provided' }}
                                </p>
                            </div>
                        </div>

                        <!-- Session Details -->
                        <div class="row mb-4">
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted small mb-1">Category</h6>
                                        <p class="mb-0">{{ getLocalizedCategoryName(appointment.category) || 'N/A' }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted small mb-1">Status</h6>
                                        <span 
                                            class="badge"
                                            :class="{
                                                'bg-warning text-dark': appointment.status === 'pending',
                                                'bg-success': appointment.status === 'accepted',
                                                'bg-danger': appointment.status === 'rejected' || appointment.status === 'expired',
                                                'bg-info text-dark': appointment.status === 'completed'
                                            }"
                                        >
                                            {{ appointment.status }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted small mb-1">Start Time</h6>
                                        <p class="mb-0">{{ formatDateTime(appointment.start_time) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted small mb-1">End Time</h6>
                                        <p class="mb-0">{{ formatDateTime(appointment.end_time) }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-2 col-md-4 mb-3">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h6 class="text-muted small mb-1">Duration</h6>
                                        <p class="mb-0">{{ calculateDuration(appointment.start_time, appointment.end_time) }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex gap-3 mb-4" v-if="appointment.status === 'pending'">
                            <template v-if="isCountdownActive">
                                <button
                                    @click="handleAction('accept')"
                                    class="btn btn-success d-flex align-items-center"
                                >
                                    <i class="fas fa-check me-2"></i>
                                    Accept Request
                                </button>
                                <button
                                    @click="handleAction('reject')"
                                    class="btn btn-outline-secondary d-flex align-items-center"
                                >
                                    <i class="fas fa-times me-2"></i>
                                    Reject ({{ countdown }}s)
                                </button>
                            </template>
                            <div v-else>
                                <template v-if="showCallbtn">
                                    <a
                                        :href="'/call/' + bookedAppointment?.id"
                                        class="btn btn-outline-secondary d-flex align-items-center"
                                    >
                                        <i class="fas fa-times me-2"></i>
                                        Join Call
                                    </a>
                                </template>
                                <div v-else class="alert alert-info"><b>*</b> Waiting for the student to complete the payment. <br/> Once the Payment is complete, the Live session will be started automatically.</div>
                            </div>
                        </div>

                        <!-- Status Message -->
                        <div v-else class="alert mb-4" :class="{
                            'alert-success': appointment.status === 'accepted',
                            'alert-warning': appointment.status === 'pending',
                            'alert-danger': appointment.status === 'rejected' || appointment.status === 'expired',
                            'alert-info': appointment.status === 'completed'
                        }">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 me-3">
                                    <i v-if="appointment.status === 'accepted'" class="fas fa-check-circle fa-lg"></i>
                                    <i v-else-if="appointment.status === 'rejected' || appointment.status === 'expired'" class="fas fa-times-circle fa-lg"></i>
                                    <i v-else-if="appointment.status === 'pending'" class="fas fa-clock fa-lg"></i>
                                    <i v-else class="fas fa-info-circle fa-lg"></i>
                                </div>
                                <div>
                                    <p class="mb-0 fw-medium">
                                        {{ getStatusMessage(appointment.status) }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </app-layout>
</template>

<script>
import { defineComponent } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import axios from 'axios';
import PageHeader from "@/Components/PageHeader.vue";
import Navbar from "@/Layouts/AppIncludes/Navbar.vue";
import { socket } from "@/Libraries/socket";

export default defineComponent({
    components: {
        AppLayout,
        Navbar,
        PageHeader,
    },

    props: {
        appointment: {
            type: Object,
            required: true
        }
    },
    data() {
        return {
            timeout: null,
            countdown: 15,
            countdownInterval: null,
            isCountdownActive: false,
            showCallbtn: false,
            bookedAppointment: null
        };
    },
    mounted() {
        this.startCountdown();

        // Request countdown sync from student's side
        try {
            socket?.emit("teacher_countdown_sync_request", {
                live_request_id: this.appointment.id,
                teacher_id: this.appointment.teacher_id,
            });

            socket?.once("teacher_countdown_sync_response", (payload) => {
                try {
                    payload = typeof payload === "string" ? JSON.parse(payload) : payload;
                } catch (e) {}

                if (
                    payload &&
                    payload.teacher_id === this.appointment.teacher_id &&
                    payload.live_request_id === this.appointment.id &&
                    typeof payload.remaining_seconds === "number"
                ) {
                    this.applySyncedCountdown(payload.remaining_seconds);
                }
            });
        } catch (e) {
            // fail silently; fallback to local countdown
        }

        document.addEventListener('live_payment_completed', (event) => {
            console.log("CUSTOM EVENT RECEIVED:", event.detail);
            this.showCallbtn = true;
            this.bookedAppointment = event.detail;
        });
    },
    beforeUnmount() {
        this.clearCountdown();
    },

    methods: {
        startCountdown(initialSeconds = 15) {
            if (this.appointment.status !== 'pending') return;
            
            this.isCountdownActive = true;
            this.countdown = initialSeconds;
            
            this.countdownInterval = setInterval(() => {
                this.countdown--;
                
                if (this.countdown <= 0) {
                    this.clearCountdown();
                    this.handleAction('reject');
                }
            }, 1000);
        },
        
        applySyncedCountdown(remainingSeconds) {
            const clamped = Math.max(0, Math.min(15, Math.floor(remainingSeconds)));
            this.clearCountdown();
            if (clamped <= 0) {
                this.handleAction('reject');
                return;
            }
            this.startCountdown(clamped);
        },
        
        clearCountdown() {
            if (this.countdownInterval) {
                clearInterval(this.countdownInterval);
                this.countdownInterval = null;
                this.isCountdownActive = false;
            }
        },
        
        handleAction(action) {
            this.clearCountdown();
            
            if (action === 'accept') {
                this.acceptRequest();
            } else if (action === 'reject') {
                // Immediately reflect rejection in UI to hide pending/payment messages
                if (this.appointment && this.appointment.status === 'pending') {
                    this.appointment.status = 'rejected';
                }
                // Mark this live request as being rejected to suppress any immediate prompts
                try {
                    sessionStorage.setItem('rejecting_live_request_id', String(this.appointment?.id ?? ''));
                } catch (e) {}
                this.rejectRequest();
            }
        },
        getLocalizedCategoryName(category) {
            if (!category || !category.name) return null;
            const locale = this.$page.props.locale || 'en';
            return category.name[locale] || category.name.en || category.name;
        },
        
        formatDateTime(dateTime) {
            if (!dateTime) return 'N/A';
            const options = { 
                year: 'numeric', 
                month: 'short', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            };
            return new Date(dateTime).toLocaleString(undefined, options);
        },

        calculateDuration(start, end) {
            if (!start || !end) return 'N/A';
            
            const startTime = new Date(start);
            const endTime = new Date(end);
            const durationMs = endTime - startTime;
            
            // Convert to minutes
            const durationMinutes = Math.floor(durationMs / (1000 * 60));
            
            if (durationMinutes < 60) {
                return `${durationMinutes} min`;
            } else {
                const hours = Math.floor(durationMinutes / 60);
                const minutes = durationMinutes % 60;
                return `${hours}h ${minutes > 0 ? `${minutes}m` : ''}`.trim();
            }
        },

        getStatusMessage(status) {
            const messages = {
                'pending': 'This request is pending your response.',
                'accepted': 'You have accepted this request.',
                'rejected': 'You have rejected this request.',
                'expired': 'This request has expired.',
                'completed': 'This session has been completed.'
            };
            return messages[status] || 'Unknown status';
        },
        async acceptRequest() {
            try {
                // First update the status of the live request
                const response = await axios.post("/accept_live_appointment", {
                    live_request_id: this.appointment.id
                });

                console.log(response.data);
            } catch (error) {
                console.error('Error updating status:', error);
                this.$toast.error(error.response?.data?.message || 'Failed to update status');
                location.href = route('home');
            }
        },
        
        async rejectRequest() {            
            // Fire-and-forget the API call; do not block navigation
            try {
                axios.post("/reject_live_appointment", {
                    live_request_id: this.appointment.id
                }).catch((error) => {
                    console.error('Error rejecting request:', error);
                });
            } catch (e) {
                console.error('Error initiating reject request:', e);
            }

            // Redirect immediately regardless of network response
            try {
                if (window.history && window.history.length > 1) {
                    window.history.back();
                } else if (typeof route === 'function') {
                    window.location.href = route('home');
                } else {
                    window.location.href = '/';
                }
            } catch (e) {
                window.location.href = '/';
            }
        }
    }
});
</script>

<style scoped>
/* Add any custom styles here */
</style>
