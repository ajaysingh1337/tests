<template>
  <div>
    <div class="container-fluid py-4">
      <div class="row">
        <div class="col-12 mt-2 text-end">
          <a href="javascript:history.back()" class="btn btn btn-primary mb-3">
            &larr; Back
          </a>
        </div>
        <div class="col-12 mt-4">
          <h2 class="fs-2 text-center">
            <span class="fw-bold text-primary display-2">{{ __("Appointment Detail") }}</span>
          </h2>
          <!-- <p class="text-center mb-0">{{ __("Our team of highly skilled attorneys comprises seasoned professionals") }}</p> -->
        </div>
      </div>
    </div>
    <div class="section py-5">
      <div class="container">
        <div class="row">
             <div class="col-12">

                <div class="row">
                <div class="col-lg-7">
                  <div class="row mx-0 mb-4" v-if="appointment.appointment_status_code == 1 && appointment.is_paid">
                  <div class="col-12 py-3 rounded-4 d-flex align-items-center" style="background-color: #E7F3FA;">
                      <div class="col-md-6">
                          <p class="fw-bold text-center ms-1 mb-0 fs-5">{{ __("Please accept the appointment from student") }}</p>
                      </div>
                      <div class="col-md-6 d-flex justify-content-end">
                      <button type="updatenStatus" @click="updateAppointmentStatus(2)" class="btn btn-primary me-3">
                          {{ __("Accept") }}
                      </button>
                      <button type="button" @click="updateAppointmentStatus(3)" class="btn btn-danger me-3">
                          {{ __("Reject") }}
                      </button>
                      </div>
                  </div>
                  </div>
                  <div class="row mx-0 mb-4" v-if="appointment.appointment_status_code == 1 && !appointment.is_paid">
                    <div class="col-12 py-3 rounded-4 d-flex align-items-center" style="background-color: #dcdcaa;">
                      <div class="col-md-6">
                        <p class="fw-bold text-center ms-1 mb-0 fs-5" v-if="$page.props.auth.logged_in_as == 'teacher'">Payment is not completed yet.</p>
                      </div>
                    </div>
                  </div>
                    <div class="row py-3 rounded-4 justify-content-center" style="background-color: #E7F3FA;" v-if="appointment.appointment_status_code == 5">
                        <div class="col-md-12">
                        <p class="mb-0 fw-bold text-center fs-5">{{ __("Appointment has been completed") }}</p>
                        </div>
                    </div>
                    <div class="row mx-0 mb-4" v-if="appointment.appointment_status_code == 2">
                        <div v-if="appointment.appointment_type_id == 1 && appointment.appointment_status_code != 5">             
                          <call-button-component @showCompletedButton="() => this.showMarkedAsCompletedButton = true"  :appointment="appointment" type="video"></call-button-component>
                        </div>
                        <div v-if="appointment.appointment_type_id == 2 && appointment.appointment_status_code != 5">
                          <call-button-component @showCompletedButton="() => this.showMarkedAsCompletedButton = true"  :appointment="appointment" type="audio"></call-button-component>
                        </div>
                        <div v-if="appointment.appointment_type_id == 4 && appointment.appointment_status_code != 5">
                          <call-button-component @showCompletedButton="() => this.showMarkedAsCompletedButton = true"  :appointment="appointment" type="video"></call-button-component>
                        </div>
                        <div v-if="appointment.appointment_type_id == 3 && appointment.appointment_status_code == 2">
                          <chat-component @showCompletedButton="() => this.showMarkedAsCompletedButton = true"  :appointment="appointment"></chat-component>
                        </div>
                    </div>                   
                    

                    <div class="row justify-content-end" v-if="showMarkedAsCompletedButton">
                    <div class="col-md-6 d-flex justify-content-end flex-wrap">
                    <div class="mt-4" v-if="appointment.appointment_status_code == 2">
                        <button type="updatenStatus" @click="updateAppointmentStatus(5)" class="btn btn-primary px-5">
                        {{ __("mark as complete") }}
                        </button>
                    </div>
                    </div>
                    </div>



                </div>
                <div class="col-lg-5">

                <div class="card px-2 pb-4 d-flex flex-column mt-3 mt-md-0">
                <div class="card-body">
                    <div class="d-flex border-bottom border-info py-3 align-items-center justify-content-between">
                        <h4 class="text-primary fw-bolder fs-4">{{ __("Your Appointment Summary") }}</h4>
                        <h4 class="badge" :style="{ backgroundColor: getBadgeBackgroundColor(appointment.appointment_status_name) }"> {{ __(appointment.appointment_status_name) }} </h4>
                    </div>
                    <div class="d-flex border-bottom border-info py-3 flex-column align-content-start">
                    <h6 class="fs-5 mb-2 fw-bolder text-secondary">{{ __('Booking Date') }}</h6>
                    <span class="fs-5 text-secondary">{{ appointment.date }}</span>
                    </div>
                    <div class="d-flex border-bottom border-info py-3 flex-column align-content-start"
                    v-if="appointment.is_schedule_required"
                    >
                    <h5 class="mb-2 fs-5 fw-bolder text-secondary">{{ __('Start and End Time') }}</h5>
                    <span class="fs-5 text-secondary">{{ appointment.start_time }} - {{ appointment.end_time }} </span>
                    </div>
                    <div class="d-flex flex-column border-bottom border-info py-2 align-content-start"
                    v-if="appointment.teacher_name"
                    >
                    <h5 class="fs-5 text-secondary mb-2 fw-bolder">{{ __('Selected Tutor') }}</h5>
                    <span class="fs-5 text-secondary">{{ appointment.teacher_name }}</span>
                    </div>
                    <div class="d-flex flex-column border-bottom border-info py-2 align-content-start"
                    v-if="appointment.academy_name"
                    >
                    <h5 class="mb-2 fs-5 fw-bolder text-secondary">{{ __('Academy') }}</h5>
                    <span class="fs-5 text-secondary">{{ appointment.academy_name }}</span>
                    </div>
                    <div class="d-flex flex-column align-content-start py-2 border-bottom border-info">
                    <h5 class="mb-2 fs-5 text-secondary fw-bolder">{{ __('Appointment Status') }}</h5>
                    <span class="fs-5 text-secondary">{{ appointment.appointment_status_name }}</span>
                    </div>
                    <div class="d-flex flex-column align-content-start py-2 border-bottom border-info">
                    <h5 class="mb-2 fs-5 text-secondary fw-bolder">{{ __('Appointment Type') }}</h5>
                    <span class="fs-5 text-secondary">{{ appointment.appointment_type_name }}</span>
                    </div>
                    <div class="d-flex flex-column align-content-start py-2 border-bottom border-info ">
                    <h5 class="mb-2 text-secondary fw-bolder fs-5">{{ __('Payment Status') }}</h5>
                    <span class="fs-5 text-secondary">{{ appointment.is_paid ? __('paid') : __('Unpaid') }}</span>
                    </div>
                    <div class="d-flex flex-column align-content-start py-2 border-bottom border-info ">
                    <h5 class="mb-2 text-secondary fw-bolder fs-5">{{ __('Payment Method') }}</h5>
                    <span class="fs-5 text-secondary">{{  gateway?.name ?? "wallet" }}</span>
                    </div>
                    <div class="d-flex flex-column align-content-start py-2 border-bottom border-info">
                    <h5 class="mb-2 fs-5 fw-bolder text-secondary">{{ __('Fee') }}</h5>
                    <span class="fs-5 text-secondary"> {{ getDisplayAmount(appointment.fee)  }}</span>
                    </div>
                    <div class="d-flex flex-column align-content-start py-2 border-bottom border-info">
                    <h5 class="mb-2 fs-5 fw-bolder text-secondary">{{ __('Question') }}</h5>
                    <span class="fs-5 text-secondary">{{ appointment.question }}</span>
                    </div>
                    <div class="d-flex flex-column py-2 border-bottom border-info align-content-start">
                    <h5 class="mb-2 text-secondary fs-5 fw-bolder">{{ __('Attachment') }}</h5>
                     <div v-if="isImage(appointment.attachment_url)">
                        <img
                          :src="appointment.attachment_url"
                          height="100"
                          width="100"
                          alt="Attachment Preview"
                        />
                      </div>
                      <div v-else-if="appointment.attachment_url">
                        <a :href="appointment.attachment_url" target="_blank" rel="noopener">
                          {{ getFileName(appointment.attachment_url) }}
                        </a>
                      </div>
                      <div v-else>
                        <span class="fs-5 text-secondary">N/A</span>
                      </div>
                    </div>
                    <div
                        v-if="appointment.appointment_status_code == 5 && userRating">

                        <div class="d-flex flex-column align-content-start border-bottom border-info py-2">
                            <div>
                            <h5 class="mb-2 text-secondary fs-5 fw-bolder text-capitalize">
                                {{ __("your feedback") }}
                            </h5>
                           <div class="d-flex align-items-center gap-2 mb-2">
                            <b class="fs-5 text-secondary">{{ __("rating") }}: </b>
                            <star-rating
                                :rating="userRating.rating"
                                :star-size="20"
                                :read-only="true"
                                :increment="0.01"
                                :show-rating="false"
                                class="mb-1"
                            ></star-rating>
                           </div>
                            <b class="fs-5">{{ __("comment") }}: </b> {{ userRating.comment }}
                            </div>
                        </div>
                    </div>
                </div>
                </div>

                </div>
                <div class="row mt-5" v-if="appointment.appointment_status_code == 5 && !userRating">
                            <div class="col-md-6"></div>
                            <div class="col-md-6 d-flex justify-content-end">
                                <button type="button"  class="btn btn-primary" data-bs-toggle="modal"
                                    data-bs-target="#ratingModel">
                                    {{ __("Rate Now") }}
                                </button>
                            </div>
                             <!-- Modal -->
                             <div
                  class="modal fade"
                  id="ratingModel"
                  tabindex="-1"
                  aria-labelledby="ratingModelLabel"
                  aria-hidden="true"
                >
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="ratingModelLabel">
                            {{ __("rate now") }}
                        </h5>
                        <button
                          type="button"
                          class="btn-close"
                          data-bs-dismiss="modal"
                          aria-label="Close"
                        ></button>
                      </div>
                      <div class="modal-body">
                        <label for="rating">{{ __("rating") }}:</label>
                        <div class="rating text-center fs-3 text-warning">
                          <star-rating
                            v-model="form.rating"
                            @update:rating="setRating"
                            :star-size="25"
                          ></star-rating>
                        </div>
                        <div class="form-group">
                          <label for="comment">{{ __("comment") }}:</label>
                          <textarea
                            v-model="form.comment"
                            class="form-control"
                            id=""
                            cols="30"
                            rows="3"
                          ></textarea>
                          <span v-if="form.errors.comment">
                            {{ form.errors.comment }}
                          </span>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button
                          type="button"
                          class="btn btn-secondary"
                          id="close"
                          data-bs-dismiss="modal"
                        >
                          {{ __("close") }}
                        </button>
                        <button
                          type="button"
                          @click="submit"
                          class="btn btn-primary"
                        >
                          {{ __("submit") }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div>
            </div>
                </div>



            </div>
            </div>
            
        <!-- <div class="px-4 mb-0 rounded py-4" style="background-color: #fff3db">
          <div class="row" v-if="appointment.appointment_status_code == 1">
            <div class="col-md-6">
              <p class="mb-0">{{ __("Please accept the appointment from student") }}</p>
            </div>
            <div class="col-md-6 d-flex justify-content-end flex-wrap">
              <button type="updatenStatus" @click="updateAppointmentStatus(2)" class="btn btn-primary me-lg-3 me-0 px-5">
                {{ __("accept") }}
              </button>
              <button type="button" @click="updateAppointmentStatus(3)" class="btn btn-danger px-5">
                {{ __("reject") }}
              </button>
            </div>
          </div>
          <div class="row" v-if="appointment.appointment_status_code == 5">
            <div class="col-md-6">
              <p class="mb-0">{{ __("Appointment has been completed") }}</p>
            </div>
         </div> -->
          <!-- <div v-if="appointment.appointment_type_id == 1 && appointment.appointment_status_code != 5">


            <call-button-component @showCompletedButton="() => this.showMarkedAsCompletedButton = true"  :appointment="appointment" type="video"></call-button-component>
             
          </div>
          <div v-if="appointment.appointment_type_id == 2 && appointment.appointment_status_code != 5">

            <call-button-component @showCompletedButton="() => this.showMarkedAsCompletedButton = true"  :appointment="appointment" type="audio"></call-button-component>
          </div>
          <div v-if="appointment.appointment_type_id == 3 && appointment.appointment_status_code == 2">
            <chat-component @showCompletedButton="() => this.showMarkedAsCompletedButton = true"  :appointment="appointment"></chat-component>
          </div>

          <div class="row justify-content-end" v-if="showMarkedAsCompletedButton">
            <div class="col-md-6 d-flex justify-content-end flex-wrap">
              <div class="mt-4" v-if="appointment.appointment_status_code == 2">
                <button type="updatenStatus" @click="updateAppointmentStatus(5)" class="btn btn-primary px-5">
                  {{ __("mark as complete") }}
                </button>
              </div>
            </div>
          </div> -->
          <!-- <div class="mb-0 rounded" style="background-color: #fff3db">
            <div v-if="appointment.appointment_status_code == 5 && userRating">
              <div class="col-md-4 d-flex">
                <div class="d-flex align-items-center mb-3">
                  <div>
                    <h5 class="mb-0 text-capitalize">
                      {{ __("your feedback") }}
                    </h5>
                    <b>{{ __("rating") }}: </b>
                    <star-rating :rating="userRating.rating" :star-size="20" :read-only="true" :increment="0.01"
                      :show-rating="false"></star-rating>
                    <b>{{ __("comment") }}: </b> {{ userRating.comment }}
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="row">
          <div class="col-12">


            <div class="pt-4">
              <div class="row">
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-calendar-x fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">
                        {{ __("booking date") }}
                      </h5>
                      <span>{{ appointment.date }}</span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-clock-history fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">
                        {{ __("start time") }}
                      </h5>
                      <span>{{ appointment.start_time }}</span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-clock-history fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">{{ __("end time") }}</h5>
                      <span>{{ appointment.end_time }}</span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-person-circle fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">{{ __("student") }}</h5>
                      <span>{{ appointment.student_name }}</span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-clipboard-check fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">
                        {{ __("appointment status") }}
                      </h5>
                      <span>{{ appointment.appointment_status_name }}</span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-webcam fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">
                        {{ __("appointment type") }}
                      </h5>
                      <span>{{ appointment.appointment_type_name }}</span>
                    </div>
                  </div>
                </div>

                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-check2-square fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">
                        {{ __("payment status") }}
                      </h5>
                      <span>
                        {{ appointment.is_paid ? __("paid") : __("un Paid") }}
                      </span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-coin fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">
                        {{ __("fee") }}
                      </h5>
                      <span>
                         {{  getDisplayAmount(appointment.fee)  }}
                      </span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-question-circle fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">{{ __("question") }}</h5>
                      <span>{{ appointment.question }}</span>
                    </div>
                  </div>
                </div>
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div class="me-3">
                      <i class="bi bi-file-earmark-post fs-1 text-primary"></i>
                    </div>
                    <div>
                      <h5 class="mb-0 text-capitalize">
                        {{ __("attachment") }}
                      </h5>
                      <img :src="appointment.attachment_url" height="100" width="100" v-if="appointment.attachment_url"
                        alt="" />
                      <span v-else>{{ __("n/a") }}</span>
                    </div>
                  </div>
                </div>
              <div
                v-if="appointment.appointment_status_code == 5 && userRating"
              >
                <div class="col-md-4 d-flex">
                  <div class="d-flex align-items-center mb-3">
                    <div>
                      <h5 class="mb-0 text-capitalize">
                        {{ __("your feedback") }}
                      </h5>
                      <b>{{ __("rating") }}: </b>
                      <star-rating
                        :rating="userRating.rating"
                        :star-size="20"
                        :read-only="true"
                        :increment="0.01"
                        :show-rating="false"
                      ></star-rating>
                      <b>{{ __("comment") }}: </b> {{ userRating.comment }}
                    </div>
                  </div>
                </div>
              </div>
              </div>
            </div>
          </div>
        </div>
        <div
                  class="modal fade"
                  id="ratingModel"
                  tabindex="-1"
                  aria-labelledby="ratingModelLabel"
                  aria-hidden="true"
                >
                  <div class="modal-dialog">
                    <div class="modal-content">
                      <div class="modal-header">
                        <h5 class="modal-title" id="ratingModelLabel">
                            {{ __("Rate Now") }}
                        </h5>
                        <button
                          type="button"
                          class="btn-close"
                          data-bs-dismiss="modal"
                          aria-label="Close"
                        ></button>
                      </div>
                      <div class="modal-body">
                        <label for="rating">{{ __("rating") }}:</label>
                        <div class="rating text-center fs-3 text-warning">
                          <star-rating
                            v-model="form.rating"
                            @update:rating="setRating"
                            :star-size="25"
                          ></star-rating>
                        </div>
                        <div class="form-group">
                          <label for="comment">{{ __("comment") }}:</label>
                          <textarea
                            v-model="form.comment"
                            class="form-control"
                            id=""
                            cols="30"
                            rows="3"
                          ></textarea>
                          <span v-if="form.errors.comment">
                            {{ form.errors.comment }}
                          </span>
                        </div>
                      </div>
                      <div class="modal-footer">
                        <button
                          type="button"
                          class="btn btn-secondary"
                          id="close"
                          data-bs-dismiss="modal"
                        >
                          {{ __("close") }}
                        </button>
                        <button
                          type="button"
                          @click="submit"
                          class="btn btn-primary"
                        >
                          {{ __("submit") }}
                        </button>
                      </div>
                    </div>
                  </div>
                </div> -->
      </div>
    </div>
  </div>
</template>

<script>
import { defineComponent } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import ValidationErrors from "@/Components/ValidationErrors.vue";
import ChatComponent from "@/Components/Shared/Appointment/ChatComponent.vue";
import CallButtonComponent from "@/Components/Shared/Appointment/CallButtonComponent.vue";

import { Head, Link } from "@inertiajs/inertia-vue3";
import Breadcrums from "../../../Components/Shared/Breadcrums.vue";

export default defineComponent({
  components: {
    Head,
    AppLayout,
    CallButtonComponent,
    ChatComponent,
    ValidationErrors,
    Link,
    Breadcrums,
  },
  props: ["appointment","gateway"],
  computed: {
    userRating() {
      var variable = this.appointment.ratings.find(
        (rating) =>
          rating.fromable_type == this.$page.props.auth.logged_in_as &&
          rating.fromable_id ==
          this.$page.props.auth.user[this.$page.props.auth.logged_in_as].id
      );
      return variable;
    },
  },
  data() {
    return {
      appointmentStatusForm: this.$inertia.form({
        appointment_id: this.appointment.id,
        status_code: "",
      }),
      form: this.$inertia.form({
        comment: "",
        rating: 0,
        teacher_id: this.appointment.teacher_id,
        booked_appointment_id: this.appointment.id,
      }),
      showMarkedAsCompletedButton:false,
      breadcrums: [
        {
          id: 1,
          name: "Home",
          link: "/",
        },
        {
          id: 2,
          name: "My Appointments",
          link: "/appointment_log",
        },
        {
          id: 3,
          name: "Appointment Detail",
          link: "",
        },
      ],
    };
  },
  async created() {
        if (this.appointment.is_started) {
            this.showMarkedAsCompletedButton = true
        }
        console.log("auth =========================>",this.$page.props.auth)
   },
  methods: {
    async payNow() {
      try {
        // Get the payment gateway (assuming Stripe is the default)
        const response = await axios.get(route('gateways'));
        const stripeGateway = response.data.find(gateway => gateway.code === 'stripe');
        
        if (!stripeGateway) {
          throw new Error('Stripe payment gateway is not available');
        }

        // Create a fund request for the appointment
        const fundResponse = await axios.post(route('students.add_fund_request'), {
          gateway: 'stripe',
          amount: this.appointment.fee,
          appointment_id: this.appointment.id
        });

        // Redirect to the payment confirmation page
        window.location.href = fundResponse.data.payment_url;
      } catch (error) {
        console.error('Payment error:', error);
        alert(error.response?.data?.message || 'Failed to process payment. Please try again.');
      }
    },
    getBadgeBackgroundColor(statusName) {
      switch (statusName) {
        case 'Pending':
          return '#ffc107'; // Yellow background color for Pending
        case 'Accepted':
          return '#28a745'; // Green background color for Accepted
        case 'Rejected':
          return '#dc3545'; // Red background color for Rejected
        case 'Cancelled':
          return '#6c757d'; // Grey background color for Cancelled
        case 'Completed':
          return '#007bff'; // Blue background color for Completed
        default:
          return ''; // Default background color or class if status name doesn't match
      }
    },
    close() {
      document.getElementById("close").click();
    },
    resetForm() {
      this.form = this.$inertia.form({
        comment: "",
        rating: 0,
      });
    },
    setRating(rating) {
      this.form.rating = rating;
    },
    updateAppointmentStatus(status_code) {
      this.appointmentStatusForm.status_code = status_code;
      this.appointmentStatusForm
        .transform((data) => ({
          ...data,
        }))
        .post(this.route("appointment_detail.updateStatus"), {
          onSuccess: () => {
            if (status_code == 5) {
              document.getElementById("rate_now_button").click();
            }
          },
        });
    },
    isImage(url) {
      if (!url) return false;
      const extension = url.split('.').pop().toLowerCase();
      return ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'].includes(extension);
    },
    getFileName(url) {
      if (!url) return '';
      return url.split('/').pop();
    },
    submit() {
      this.form.post(this.route("add_appointment_rating"), {
        preserveState: true,
        preserveScroll: true,
        onSuccess: () => {
          const modalBackdrop = document.querySelector(".modal-backdrop");
          if (modalBackdrop) {
            modalBackdrop.classList.remove("modal-backdrop");
          }
          this.resetForm();
        },
      });
    },
  },
});
</script>

<style>
.modal-backdrop.show {
  z-index: 1040;
  width:0px;
  height:0px
}
.modal.show {
  z-index: 1050;
}
</style>