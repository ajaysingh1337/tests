<template>

  <app-layout title="My Profile">
<template #header>
    <!-- <page-header >
    My Profile
    </page-header> -->
</template>
<template #default>
        <teacher-account v-if="$page.props.auth.logged_in_as == 'teacher'"></teacher-account>
        <student-account v-if="$page.props.auth.logged_in_as == 'student'"></student-account>
        <academy-account v-if="$page.props.auth.logged_in_as == 'academy'"></academy-account>
        <Modal style="background-color: white !important" ref="modal" class="rounded-0" :id="'teacherLiveModal'">
          <div class="modal-content p-md-4">
            <div class="modal-header border-0">
                <h1 class="modal-title fs-5" id="teacherLiveModalLabel">{{__("Change Live Availability")}}</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body mx-md-3 d-flex align-items-start flex-column pb-md-5 gap-2">
              <div class="form-check form-switch">
                <label class="form-check-label" for="switchCheckChecked">{{__("Available to take live classes")}}</label>
                <input class="form-check-input" type="checkbox" role="switch" id="switchCheckChecked" v-model="isOnline">
              </div>
              <br/>
              <div class="w-100 d-flex align-items-center justify-content-between gap-2"><span>{{__("Fee")}}:</span><input class="form-control" type="number" v-model="form.fee"></div>
              <div class="w-100 d-flex align-items-center justify-content-between gap-2"><span>{{__("Start Time")}}:</span><VueDatePicker :is24="false" v-model="form.start_time" :timezone="Intl.DateTimeFormat().resolvedOptions().timeZone" time-picker-inline></VueDatePicker></div>
              <div class="w-100 d-flex align-items-center justify-content-between gap-2"><span>{{__("End Time")}}:</span><VueDatePicker :is24="false" v-model="form.end_time" :timezone="Intl.DateTimeFormat().resolvedOptions().timeZone" time-picker-inline></VueDatePicker></div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">{{__("Close")}}</button>
              <button type="button" class="btn btn-primary" @click="submit">{{__("Save changes")}}</button>
            </div>
          </div>
        </Modal>
</template>

  </app-layout>

</template>

<script>
import { defineComponent } from "vue";
import AppLayout from "@/Layouts/AppLayout.vue";
import PageHeader from "@/Components/PageHeader.vue";
import Navbar from "@/Layouts/AppIncludes/Navbar.vue";
import TeacherAccount from "@/Components/Teachers/TeacherAccount.vue";
import StudentAccount from "@/Components/Students/StudentAccount.vue";
import AcademyAccount from "@/Components/Academies/AcademyAccount.vue";
import Modal from "@/Components/Modal.vue";
import {Modal as BootstrapModal} from "bootstrap";




export default defineComponent({
  components: {
    AppLayout,
    Navbar,
    PageHeader,
    TeacherAccount,
    StudentAccount,
    AcademyAccount,
    Modal
  },
  data(){
    return {
      form: {
        fee: this.$page.props.live_availability?.fee ?? null,
        status: this.$page.props.live_availability?.status ?? 'offline',
        start_time: this.$page.props.live_availability?.start_time ?? null,
        end_time: this.$page.props.live_availability?.end_time ?? null,
        latitude: null,
        longitude: null,
      },
    }
  },
  computed: {
    isOnline: {
      get() {
        return this.form.status === 'online' || this.form.status === 'in-call';
      },
      set(value) {
        this.form.status = value ? 'online' : 'offline';
      }
    }
  },
  methods: {
    async getCurrentLocation() {
      return new Promise((resolve, reject) => {
        if (!navigator.geolocation) {
          reject(new Error('Geolocation is not supported by your browser'));
          return;
        }

        navigator.geolocation.getCurrentPosition(
          (position) => {
            resolve({
              latitude: position.coords.latitude,
              longitude: position.coords.longitude
            });
          },
          (error) => {
            let errorMessage = 'Unable to retrieve your location';
            switch(error.code) {
              case error.PERMISSION_DENIED:
                errorMessage = 'Location permission denied. Please enable location services to use live features.';
                break;
              case error.POSITION_UNAVAILABLE:
                errorMessage = 'Location information is unavailable.';
                break;
              case error.TIMEOUT:
                errorMessage = 'The request to get user location timed out.';
                break;
            }
            reject(new Error(errorMessage));
          },
          {
            enableHighAccuracy: true,
            timeout: 10000,
            maximumAge: 0
          }
        );
      });
    },
    
    async submit() {
      try {
        // Get current location before submitting
        const location = await this.getCurrentLocation();
        
        // Update form with location data
        this.form.latitude = location.latitude;
        this.form.longitude = location.longitude;

        // const originalStart = this.form.start_time;
        // const originalEnd = this.form.end_time;
        // // Convert to local timezone before converting to ISO string
        // const startDate = new Date(this.form.start_time);
        // const endDate = new Date(this.form.end_time);
        // const timezoneOffset = startDate.getTimezoneOffset() * 60000; 
        
        // this.form.start_time = new Date(startDate - timezoneOffset).toISOString();
        // this.form.end_time = new Date(endDate - timezoneOffset).toISOString();

        // console.log(this.form)
        
        // Proceed with form submission
        this.$inertia.post(route('teacher.update_live_availability'), this.form, {
          preserveScroll: true,
          preserveState: true,
          onSuccess: () => {
            const modal = BootstrapModal.getInstance(document.getElementById('teacherLiveModal'));
            if (modal) {
              modal.hide();
            }
            
            if (this.$page.props.flash.alert) {
              this.$toast.show(this.$page.props.flash.alert.message);
            }
          },
          onError: (errors) => {
            const firstError = Object.values(errors)[0] || 'An error occurred while updating live availability';
            this.$toast.error(firstError);
          }
        });

        // this.form.start_time = originalStart;
        // this.form.end_time = originalEnd;

      } catch (error) {
        this.$toast.error(error.message);
        // Don't proceed with form submission if location is required but not available
        return;
      }
    }
  }
})
</script>
