<template>
  <div class="call-actions">
    <div class="col-12" v-if="$page.props.auth.logged_in_as == 'student' && callReady">
        <button @click="startCall" :disabled="!canJoinCall"  class="btn btn-success">Join Call</button>
    </div>
  
  <div class="col-12" v-if="['teacher', 'academy'].includes($page.props.auth.logged_in_as)">
  <button
    @click="startCall"
    class="btn btn-primary"
    :disabled="!canJoinCall"
  >
    Start {{ isVideo ? 'Video' : 'Audio' }} Call
  </button>
  <div v-if="callStatusMessage" class="text-danger mt-2">
    {{ callStatusMessage }}
  </div>
</div>

    
    
    
  </div>
</template>

<script>
import { defineComponent } from 'vue'

export default defineComponent({
  name: 'CallButtonComponent',
  props: {
    appointment: {
      type: Object,
      required: true
    },
    type: {
      type: String,
      required: true,
      validator: (value) => ['video', 'audio'].includes(value)
    }
  },
computed: {
    isVideo() {
      return this.type === 'video'
    },
    callStartTime() {
      return this.parseDateTime(this.appointment.date, this.appointment.start_time);
    },
    callEndTime() {
      return this.parseDateTime(this.appointment.date, this.appointment.end_time);
    },
    canJoinCall() {
      const now = new Date();
      const start = new Date(this.callStartTime.getTime() - 5 * 60 * 1000);
      const end = new Date(this.callEndTime.getTime() + 5 * 60 * 1000);
      console.log('canJoinCall',this.appointment,now,this.callStartTime,this.callEndTime,this.callStartTime.getTime(),start,end);
      return now >= start && now <= end;
    },
    callStatusMessage() {
      const now = new Date();
      const start = new Date(this.callStartTime.getTime() - 5 * 60 * 1000);
      const end = new Date(this.callEndTime.getTime() + 5 * 60 * 1000);
     console.log('callStatusMessage',now,start,end);
      if (now < start) {
        return 'This call will be available at the scheduled time.';
      } else if (now > end) {
        return 'The scheduled time for this call has passed.';
      }

      return ''; 
    }
  },
  data() {
    return {
      callReady: false,
      callPollInterval: null
    }
  },
  mounted() {
    console.log('appointment',this.appointment);
    if (this.$page.props.auth.logged_in_as === 'student') {
      this.pollCallRoom()
    }
  },
  beforeUnmount() {
    if (this.callPollInterval) {
      clearInterval(this.callPollInterval)
    }
  },
  methods: {
    parseDateTime(dateStr, timeStr) {
      console.log('parseDateTime',dateStr, timeStr);
      const [day, month, year] = dateStr.split('/'); 
      const [time, modifier] = timeStr.split(' ');   
      let [hours, minutes] = time.split(':').map(Number);

      if (modifier === 'PM' && hours < 12) hours += 12;
      if (modifier === 'AM' && hours === 12) hours = 0;
      const formattedDateTime = `${year}-${month}-${day}T${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}:00`;
      console.log('parseDateTime',dateStr,timeStr,formattedDateTime);
      return new Date(formattedDateTime);
    },
    async pollCallRoom() {
      
      this.callPollInterval = setInterval(async () => {
        const resp = await this.checkCallRoomExists(this.appointment.id);
        if (resp) {
          this.callReady = true;
          clearInterval(this.callPollInterval);
          this.callPollInterval = null;
        }
      }, 5000); 
    },
    async checkCallRoomExists(externalId) {
      const baseUrl = import.meta.env.VITE_CALL_SERVER_URL;
      const url = `${baseUrl}/room/check_exists?external_id=${externalId}`;     
      console.log('checkCallRoomExists', url);

      try {
        const response = await axios.get(url);
        const respData = response.data?.data;
        console.log('respData',respData);
        if (respData && Array.isArray(respData.participants_ids) && respData.participants_ids.length > 0) {
          return respData;
        }

        return false;
      } catch (error) {
        console.error('Error checking call room:', error);
        return false;
      }
    },
    async startCall() {
      console.log('Starting call for:', this.appointment);
      

       const channel = new BroadcastChannel('calling_broadcast');
        let width = $(window).width();
        let height = $(window).height();
        let hasVideo = this.isVideo ? 1:0;
        
        let features = 'width=' + width + ',height=' + height + ',top=0,left=0,toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=no,resizable=no';
        let url = '/call/' + this.appointment.id + '?hasVideo=' + hasVideo;

       
        channel.postMessage({ leave_call: true });
        
        window.open(url, '_blank', features);
    },
   
  }
})
</script>


<style scoped>
.call-actions {
  display: flex;
  gap: 1rem;
  margin: 1rem 0;
}

button {
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 6px;
  font-weight: bold;
  cursor: pointer;
}
</style>
