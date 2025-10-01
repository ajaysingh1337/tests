<template>
  <div class="chat-ui" v-if="fetching">
    <ul class="m-b-0 px-3 skeleton-structure" type="none" id="style-2">
      <!-- Loading skeletons here -->
    </ul>
  </div>
  <div v-else>
    <div class="row"> 
      <div class="col-12 mb-4 d-flex justify-content-end flex-wrap">
        <button type="button" @click="updateAppointmentIsStarted" v-if="showStartedButton && !appointment.is_started && $page.props.auth.logged_in_as == 'teacher'" class="btn btn-primary ms-lg-3 ms-0 px-5" plain>
          {{ __('Start Chat') }}
        </button>
      </div>
    </div>

    <div class="chat-ui" v-if="!showStartedButton">
      <ul class="m-b-0 px-3" type="none" id="style-2" style="height: 500px; overflow: auto" ref="chatContainer">
        <div v-for="message in messages" :key="message.id">
          <div>
            <li v-if="message.sender_type == $page.props.auth.logged_in_as && $page.props.auth.user[$page.props.auth.logged_in_as].id == message.sender_id" class="clearfix">
              <div class="d-flex flex-row justify-content-end my-4">
                <div class="float-right msg-bubble">
                  <span>{{ message.message }}</span><br />
                  <span v-if="message.is_attachment">
                    <template v-if="isImage(message.attachment_url)">
                      <img :src="message.attachment_url" height="100" alt="Attachment" />
                    </template>
                    <template v-else>
                      <a :href="message.attachment_url" target="_blank">
                        <i class="bi bi-file-earmark-text-fill me-2"></i>{{ getFileName(message.attachment_url) }}
                      </a>
                    </template>
                  </span>
                </div>
              </div>
            </li>
            <li v-else class="clearfix">
              <div class="d-flex my-4">
                <div class="msg-bubble">
                  <span>{{ message.message }}</span><br />
                  <span v-if="message.is_attachment">
                    <template v-if="isImage(message.attachment_url)">
                      <img :src="message.attachment_url" height="100" alt="Attachment" />
                    </template>
                    <template v-else>
                      <a :href="message.attachment_url" target="_blank">
                        <i class="bi bi-file-earmark-text-fill me-2"></i>{{ getFileName(message.attachment_url) }}
                      </a>
                    </template>
                  </span>
                </div>
              </div>
            </li>
          </div>
        </div>
      </ul>

      <div class="d-flex align-items-center form-control">
        <div class="upload-btn-wrapper align-items-center">
          <input type="hidden" name="icon" class="image" value="">
          <input type="file" id="attachment" ref="attachment" @change="processAttachmentFile($event)"
            class="custom-file-input" aria-describedby="IconError" aria-invalid="true">
        </div>
        <input :placeholder="__('enter here')" type="text" class="w-100 border-0" @keyup.enter="sendMessage"
          :disabled="disable" v-model="message" />
        <button type="button" class="btn btn-primary me-2" @click="sendMessage" :disabled="disable">
          <i class="bi bi-send"></i>
        </button>
      </div>

      <div v-if="attachment_file_url" class="d-flex align-items-end mt-3">
        <div class="border border-3 border-primary me-2">
          <template v-if="isImage(attachment_file_url)">
            <img :src="attachment_file_url" width="100" height="100" alt="Preview" />
          </template>
          <template v-else>
            <i class="bi bi-file-earmark-text-fill fs-1 text-primary"></i>
          </template>
        </div>
        <span v-if="attachment_file.name">{{ attachment_file.name }}</span>
      </div>
    </div>
  </div>
</template>

<script>
import { defineComponent } from "vue";
import ValidationErrors from "@/Components/ValidationErrors.vue";
import { Head, Link } from "@inertiajs/inertia-vue3";

export default defineComponent({
  components: {
    Head,
    ValidationErrors,
    Link,
  },
  props: ["appointment"],
  data() {
    return {
      fetching: false,
      messages: [],
      message: "",
      attachment_file: "",
      attachment_file_url: "",
      disable: false,
      showStartedButton: true
    };
  },
  async created() {
    Echo.private(`chat-message.${this.appointment.id}`).listen(".chat-message", (e) => {
      this.messages.push(e.message);
      this.$nextTick(() => this.scrollToEnd());
    });
    await this.getChatMessages();
  },
  methods: {
    getChatMessages() {
      this.fetching = true;
      axios
        .get(this.route("getApiChatMessages", { appointment_id: this.appointment.id }))
        .then((res) => {
          this.messages = res.data.data;
          this.fetching = false;
          this.$nextTick(() => this.scrollToEnd());
        });
    },
    sendMessage() {
      this.disable = true;
      if (!this.message && !this.attachment_file) {
        this.$toast.error("Please type message or attach a file");
        this.disable = false;
        return;
      }
      const headers = {
        "Content-Type": "multipart/form-data",
      };
      let formData = new FormData();
      formData.append("appointment_id", this.appointment.id);
      formData.append("message", this.message);
      formData.append("attachment_file", this.attachment_file);
      axios
        .post(this.route("postApiSendMessage"), formData, { headers })
        .then((res) => {
          if (res.data.success) {
            this.disable = false;
            this.message = "";
            this.attachment_file = "";
            this.attachment_file_url = "";
            this.getChatMessages();
          } else {
            this.$toast.error(res.data.message);
            this.disable = false;
          }
        }).catch((err) => {
          this.$toast.error(err.response.data.message);
          this.disable = false;
        });
    },
    processAttachmentFile(event) {
      this.attachment_file = event.target.files[0];
      this.attachment_file_url = URL.createObjectURL(this.attachment_file);
    },
    scrollToEnd() {
      const chatContainer = this.$refs.chatContainer;
      chatContainer.scrollTop = chatContainer.scrollHeight;
    },
    updateAppointmentIsStarted() {
      const routeName = this.$page.props.auth.logged_in_as === "teacher"
        ? "appointment_detail.updateStarted"
        : "academy.appointment_detail.updateStarted";

      axios.post(this.route(routeName, { appointment_id: this.appointment.id })).then((res) => {
        if (res.data.success) {
          this.$emit("showCompletedButton");
          this.showStartedButton = false;
        }
      }).catch((err) => {
        this.$toast.error(err.response.data.message);
      });
    },
    isImage(fileUrl) {
      if (!fileUrl) return false;
      const imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp'];
      const ext = fileUrl.split('.').pop().toLowerCase();
      return imageExtensions.includes(ext);
    },
    getFileName(fileUrl) {
      return fileUrl ? fileUrl.split('/').pop() : '';
    }
  },
});
</script>

<style scoped>
#style-2 {
  overflow-y: auto;
  scroll-behavior: smooth;
}
</style>
