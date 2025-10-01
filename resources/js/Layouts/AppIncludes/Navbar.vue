<template>

  <nav class="navbar shadow navbar-expand-lg navbar-light"  :class="{ 'opacity-90' : !view.topOfPage }">
    <div class="container">
      <Link class="navbar-brand" :href="route('home')">
        <img v-if="$page.props && $page.props.settings && $page.props.settings.logo" style="width: 200px;"
          :src="$page.props.settings.logo" alt="logo">
        <span v-else class="text-white mt-4">
          {{ $page.props && $page.props.settings && $page.props.settings.site_title ? $page.props.settings.site_title :
            __('teacher consultant') }}
        </span>
        </Link>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarSupportedContent"
        aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>

      <div class="collapse navbar-collapse bg-prim py-sm-3 py-md-0" id="navbarSupportedContent" style="flex-grow: inherit">
        <ul class="navbar-nav ml-auto fw-semibold fs-4 py-3 py-md-0">
          <li class="nav-item" :class="{ active: route().current('home') }">
            <Link class="nav-link" :href="route('home')">
            {{ __("Home") }}
            </Link>
          </li>
          <li class="nav-item">
            <Link class="nav-link" :href="route('company_pages.display', { slug: 'about' })">
            {{ __("About") }}
            </Link>
          </li>
          <li class="nav-item">
            <Link class="nav-link" :href="route('categories')">
            {{ __("Categories") }}
            </Link>
          </li>
          <li class="nav-item" :class="{ active: route().current('about') }">
            <Link class="nav-link" :href="route('teachers.listing')">
            {{ __n("Tutors") }}
            </Link>
          </li>
          <li class="nav-item">
            <Link class="nav-link" :href="route('events.listing')">
            {{ __n("Events") }}
            </Link>
          </li>
          <li class="nav-item">
            <Link class="nav-link" :href="route('services.listing')">
            {{ __n("Services") }}
            </Link>
          </li>

          <li class="nav-item">
            <Link class="nav-link" :href="route('academies.listing')">
            {{ __n("Academy") }}
            </Link>
          </li>
          <li class="nav-item" v-if="$page.props.auth && $page.props.auth.logged_in_as != 'super_admin'
            ">
            <div class="dropdown">
              <button class="dropdown-toggle d-flex align-items-center nav-link position-relative bg-transparent border-0"
                type="button" data-bs-toggle="dropdown" aria-expanded="false">
                <span class="position-absolute badge rounded-pill bg-primary" style="right: 8px; top: -10px;">{{
                  $page.props.auth.logged_in_as == 'teacher' ? 'tutor' : $page.props.auth.logged_in_as }}</span>
                {{ $page.props.auth[$page.props.auth.logged_in_as].name }}
              </button>
              <ul class="dropdown-menu">
                <li>
                  <Link :href="route('account')" class="dropdown-item">
                  {{ __("Account") }}
                  </Link>
                </li>
                <li v-if="($page.props.auth.user?.email_verified_at &&
                    hasRole('student') &&
                    $page.props.auth.logged_in_as == 'student') ||
                  (hasRole('teacher') &&
                    $page.props.auth.logged_in_as == 'teacher') ||
                  (hasRole('academy') &&
                    $page.props.auth.logged_in_as == 'academy')
                  ">
                  <Link :href="route('appointment_log')" class="dropdown-item">{{ __("My Appointments") }}</Link>
                </li>
                <li
                  v-if="
                    ($page.props.auth.user?.email_verified_at &&
                      hasRole('student') &&
                      $page.props.auth.logged_in_as == 'student') ||
                    (hasRole('teacher') &&
                      $page.props.auth.logged_in_as == 'teacher') ||
                    (hasRole('academy') &&
                      $page.props.auth.logged_in_as == 'academy')
                  "
                >
                  <Link
                    :href="route('service_log')"
                    class="dropdown-item"
                    >{{ __("My Services") }}</Link
                  >
                </li>
                <li v-if="$page.props.auth.user?.email_verified_at &&
                  hasRole('teacher') &&
                  $page.props.auth.logged_in_as == 'teacher'
                  ">
                  <Link :href="route('pricing', { type: 'teacher' })" class="dropdown-item">{{ __("Subscription") }}
                  </Link>
                </li>
                <li v-if="$page.props.auth.user?.email_verified_at &&
                  hasRole('academy') &&
                  $page.props.auth.logged_in_as == 'academy'
                  ">
                  <Link :href="route('pricing', { type: 'academy' })" class="dropdown-item">{{ __("Subscription") }}
                  </Link>
                </li>

                <li v-if="$page.props.auth.user?.email_verified_at &&
                  hasRole('teacher') &&
                  $page.props.auth.logged_in_as != 'teacher'
                  ">
                  <button @click="switchRole('teacher')" class="dropdown-item">
                    {{ __("Switch to Tutor") }}
                  </button>
                </li>
                <li v-if="$page.props.auth.user?.email_verified_at &&
                  !hasRole('teacher') &&
                  $page.props.auth.logged_in_as != 'teacher'
                  ">
                  <button @click="becomeTeacher()" class="dropdown-item">
                    {{ __("Become a Tutor") }}
                  </button>
                </li>

                <li v-if="$page.props.auth.user?.email_verified_at &&
                  hasRole('student') &&
                  $page.props.auth.logged_in_as != 'student'
                  ">
                  <button @click="switchRole('student')" class="dropdown-item">
                    {{ __("Switch to Student") }}
                  </button>
                </li>
                <li v-if="$page.props.auth.user?.email_verified_at &&
                  !hasRole('student') &&
                  $page.props.auth.logged_in_as != 'student'
                  ">
                  <button @click="becomeUser()" class="dropdown-item">
                    {{ __("Become an User") }}
                  </button>
                </li>

                <li v-if="$page.props.auth.user?.email_verified_at &&
                  hasRole('academy') &&
                  $page.props.auth.logged_in_as != 'academy'
                  ">
                  <button @click="switchRole('academy')" class="dropdown-item">
                    {{ __("Switch to Academy") }}
                  </button>
                </li>
                <li v-if="$page.props.auth.user?.email_verified_at &&
                  !hasRole('academy') &&
                  $page.props.auth.logged_in_as != 'academy'
                  ">
                  <button @click="becomeAcademy()" class="dropdown-item">
                    {{ __("Become an Academy") }}
                  </button>
                </li>
                <li v-if="(parseInt(this.$page.props.settings.enable_wallet_system) && $page.props.auth.user?.email_verified_at && hasRole('student') && $page.props.auth.logged_in_as == 'student') || (hasRole('teacher') && $page.props.auth.logged_in_as == 'teacher') || (hasRole('academy') && $page.props.auth.logged_in_as == 'academy')
                  ">
                  <Link :href="route('wallet')" class="dropdown-item">{{ __("Wallet") }}</Link>
                </li>
                <li>
                  <!-- <Link :href="route('logout')" class="dropdown-item">
                  <i class="bi bi-box-arrow-in-left"></i>
                    {{__("Logout")}}
                  </Link> -->
                  <button style="cursor: pointer" @click="logout()" class="dropdown-item">
                    <i class="bi bi-box-arrow-in-left"></i> {{ __("Logout") }}
                  </button>
                </li>
              </ul>
            </div>
          </li>

          <li class="nav-item dropdown position-relative" v-if="$page.props.translation_languages">

            <a class="nav-link dropdown-toggle" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown"
              aria-expanded="false">
              {{ __(getSelectedLocate) }}
            </a>
            <ul class="dropdown-menu dropdown-menu-end position-absolute start-0 mt-2" aria-labelledby="langDropdown">
              <li v-for="lang in $page.props.translation_languages" :key="lang.id">
                <Link class="dropdown-item" :href="route('language', { language: lang.code })">
                {{ lang.name }}
                </Link>
              </li>

            </ul>

          </li>

          <li class="nav-item login-nav ms-md-5 ms-0 me-3 me-md-0"
          v-if="!$page.props.auth"
          >
            <Link :href="route('login')" class="btn btn-primary rounded-pill fw-medium"> <span class='px-md-3' >{{ __("Login") }}</span></Link>
          </li>

          <li class="nav-item" v-if="$page.props.auth">
            <a id="notificationDropdown" class="nav-link" @click="showHideNotification()" href="#" role="button"
            aria-expanded="false" style="position: relative;">
              <i class="bi bi-bell"></i>
              <span v-if="unreadCount > 0" class="badge rounded-pill bg-danger" style="padding: 0.2rem 0.32rem; position: absolute; top: 0; right: 0;">{{ unreadCount }}</span>
            </a>
            <Wowerlay :gap="10" position="bottom" :target="targetElement" v-model:visible="visible" style="overflow: visible;">
              <ul style="max-height: 300px; overflow-y: auto; width: 310px; list-style: none; padding-left: 0;" @scroll.passive="onDropdownScroll">
                <li class="dropdown-item text-muted" style="padding: 3px; border-bottom: 1px solid lightgray; margin-bottom: 5px; display: flex; align-items: center; justify-content: space-between;">
                  <strong>Notifications</strong>
                  <a v-if="unreadCount > 0" href="#" class="text-primary" @click.prevent="markAllAsRead()" style="text-decoration: none; font-size: smaller;">Mark all as read</a>
                </li>

                <li v-if="notifications.length === 0 && !loading" class="dropdown-item text-muted" style="padding: 3px;">
                  No notifications
                </li>

                <li v-for="notification in notifications" :key="notification.id" style="padding: 5px; margin-bottom: 5px; border-radius: 4px;" :style="!notification.read_at ? 'background-color: rgba(10, 147, 226, 0.2)' : ''">
                  <a href="#" class="dropdown-item"  @click.prevent="handleNotificationClick(notification)">
                    <div class="d-flex justify-content-between gap-2">
                      <strong style="white-space: pre-wrap;">{{ notification.title }}</strong> 
                      <span class="text-primary fs-6">{{ formatTimeAgo(notification.created_at) }}</span>
                    </div>
                    <small class="text-muted" style="white-space: pre-wrap;">{{ notification.body }}</small>
                  </a>
                </li>

                <li v-if="loading" class="dropdown-item text-center" style="padding: 3px; background: transparent">
                  <div class="spinner-border spinner-border-sm text-primary" role="status"></div>
                </li>
              </ul>

              <template #arrow="{ side, placement }">
                <div class="arrow-top" style="width: 15px; height: 15px;"></div>
              </template>
            </Wowerlay>
          </li>
      <!-- <li class="nav-item position-relative" v-if="$page.props.auth">
        <a
          class="nav-link"
          href="#"
          id="notificationTest"
          role="button"
          aria-expanded="false"
          @click="testNotification()" 
        >
          Test
        </a>
      </li> -->
        </ul>
      </div>
    </div>
  </nav>
</template>
<script>
import { Link } from "@inertiajs/inertia-vue3";
import { Wowerlay } from 'wowerlay';
import 'wowerlay/style.css';

export default {
  components: {
    Link,
    Wowerlay,
  },
  data() {
    return {
      locale: this.$page.props.locale,
      view: {
        topOfPage: true,
        pusherDeviceId: "",
      },
      notifications:  [],
      unreadCount: 0,
      pagination: {
        currentPage: 1,
        lastPage: 1,
        nextPageUrl: null,
      },
      loading: false,
      hasMore: true,
      targetElement: null,
      visible: false,
    };
  },
  beforeMount() {
    window.addEventListener("scroll", this.handleScroll);
  },
  mounted() {
    if(this.$page.props.auth) {
      this.targetElement = document.getElementById('notificationDropdown');
      this.getNotifications();
    }

    if ("serviceWorker" in navigator) {
            navigator.serviceWorker.addEventListener("message", (event) => {
                const { type, payload } = event.data || {};
                if (type === "PUSH_RECEIVED") {
                    console.log("Push event received in Vue app", payload);
                    this.getNotifications();
                }
            });
        }
  },
  created() {
    console.log('$page.props.settings.', parseInt(this.$page.props.settings.enable_wallet_system));
  },
  methods: {
    formatTimeAgo(dateString) {
      const date = new Date(dateString);
      const now = new Date();
      const seconds = Math.floor((now - date) / 1000);
      
      // Calculate time difference in various units
      const intervals = {
        w: Math.floor(seconds / 604800),  // 60 * 60 * 24 * 7
        d: Math.floor(seconds / 86400),   // 60 * 60 * 24
        h: Math.floor(seconds / 3600),    // 60 * 60
        m: Math.floor(seconds / 60),      // 60
        s: Math.floor(seconds)            // 1
      };
      
      // Find the largest unit that's greater than 0
      if (intervals.w > 0) return `${intervals.w}w`;
      if (intervals.d > 0) return `${intervals.d}d`;
      if (intervals.h > 0) return `${intervals.h}h`;
      if (intervals.m > 0) return `${intervals.m}m`;
      return `${intervals.s}s`;
    },
    async getNotifications(page = 1) {
      if (this.loading || (!this.hasMore && page !== 1)) return;

      this.loading = true;
      try {
        const response = await axios.get(`/notifications?page=${page}`);
        const data = response.data.data;
        const {notifications, unread_count} = data;
        console.log("notification-data", data, notifications, unread_count);

        if (page === 1) {
          this.notifications = notifications.data;
        } else {
          this.notifications.push(...notifications.data);
        }

        this.pagination.currentPage = data.current_page;
        this.pagination.lastPage = data.last_page;
        this.pagination.nextPageUrl = data.next_page_url;
        this.hasMore = !!data.next_page_url;
        this.unreadCount = unread_count;
      } catch (err) {
        console.error("Error loading notifications:", err);
      } finally {
        this.loading = false;
      }
    },
    showHideNotification() {
      this.visible = !this.visible;
      this.getNotifications(1);
      console.log("bell icon clicked", this.visible);
    },
    async handleNotificationClick(notification) {
      this.markAsRead(notification);
      this.visible = false;
      if(notification.action) {
        window.location.href = notification.action;
      }
    },
    async markAllAsRead() {
      try {
        await axios.post(`/notifications/mark-as-read`);
        await this.getNotifications();
        this.unreadCount = 0;
      } catch (error) {
        console.error('Failed to mark as read:', error);
      }
    },
    async markAsRead(notification) {
      try {
        await axios.post(`/notifications/mark-as-read/${notification.id}`);
        this.unreadCount = this.unreadCount - 1;
      } catch (error) {
        console.error('Failed to mark as read:', error);
      }
    },
    // async testNotification() {
    //   try {
    //     const response = await axios.post(`/test_notification`);
    //     console.log(response.data);
    //   } catch (error) {
    //     console.error('Failed to test notification:', error);
    //   }
    // },
    onDropdownScroll(event) {
      const el = event.target;
      if (el.scrollTop + el.clientHeight >= el.scrollHeight - 10) {
        if (this.hasMore) {
          this.getNotifications(this.pagination.currentPage + 1);
        }
      }
    },
    logout() {
      if (this.$page.props.settings.pusher_beams_instance_id) {
        const VITE_PUSHER_BEAMS_INSTANCE_ID = this.$page.props.settings.pusher_beams_instance_id;
        const beamsClient = new PusherPushNotifications.Client({
          instanceId: VITE_PUSHER_BEAMS_INSTANCE_ID,
        });
        //   beamsClient
        //     .start()
        //     .then((beamsClient) => beamsClient.getDeviceId())
        //     .then((deviceId) => {
        //         console.log("Successfully registered with Beams. Device ID:", deviceId);
        //         this.pusherDeviceId = deviceId
        //     })
        beamsClient
          .clearAllState()
          .then(async () => {
            console.log("Beams state has been cleared");
          })
          .catch((e) => console.error("Could not clear Beams state", e));
      }

      this.$inertia.get(route("logout"));
    },
    switchLanguage() {
      this.$inertia.get(route("language", { language: this.locale }));
    },
    switchRole(role) {
      this.$emit('showLoaderEvent', 1);
      if (this.$page.props.settings.pusher_beams_instance_id) {

        const VITE_PUSHER_BEAMS_INSTANCE_ID = this.$page.props.settings.pusher_beams_instance_id;
        const beamsClient = new PusherPushNotifications.Client({
          instanceId: VITE_PUSHER_BEAMS_INSTANCE_ID,
        });
        beamsClient
          .clearAllState()
          .then(() => {
            console.log("Beams state has been cleared");
          })
          .catch((e) => console.error("Could not clear Beams state", e));
      }
      this.$inertia.post(this.route("account.switch_role", { role: role }), {
        onFinish: () => this.$toast.show("Switched To " + role),
      });
    },
    becomeTeacher() {
      this.$emit('showLoaderEvent', 1);
      this.$inertia.post(this.route("account.become_teacher"), {
        onFinish: () => this.$toast.show("You are now a Teacher"),
      });
    },
    becomeUser() {
      this.$emit('showLoaderEvent', 1);
      this.$inertia.post(this.route("account.become_user"), {
        onFinish: () => this.$toast.show("You are now a Student"),
      });
    },
    becomeAcademy() {
      this.$emit('showLoaderEvent', 1);
      this.$inertia.post(this.route("account.become_academy"), {
        onFinish: () => this.$toast.show("You are now a academy User"),
      });
    },
    handleScroll() {
      if (window.pageYOffset > 0) {
        if (this.view.topOfPage) this.view.topOfPage = false;
      } else {
        if (!this.view.topOfPage) this.view.topOfPage = true;
      }
    },
  },
  computed: {
    getSelectedLocate() {
      var index = this.$page.props.translation_languages.findIndex((obj) => obj.code === this.locale);
      if (index >= 0) {
        return this.$page.props.translation_languages[index].name
      }
    }
  }
};
</script>

<style lang="scss" scoped>
.opacity-90{
    opacity: 0.9;
}
</style>


