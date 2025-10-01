<script>
import Echo from "laravel-echo";
import Pusher from "pusher-js";
let echoInitialized = false;
export default {
  created() {
     if (echoInitialized) {
      console.log("🔁 Echo already initialized. Skipping re-init.");
      return;
    }
    const auth = this.$page?.props?.auth;
    const user = auth?.user;

    if (!user) {
      console.error("No user found in page props.");
      return;
    }

    const getUserId = (user) => {
      if (user?.student?.id) {
        return user.student.id;
      } else if (user?.teacher?.id) {
        return user.teacher.id;
      } else if (user?.academy?.id) {
        return user.academy.id;
      } else {
        console.error("No valid ID found in user object");
        return null;
      }
    };

    const userId = getUserId(user);

    if (!userId) {
      console.error("Cannot subscribe to channel: User ID is missing");
      return;
    }

    window.Pusher = Pusher;
    Pusher.logToConsole = true; // Enable Pusher debug logs
    window.Echo = new Echo({
      broadcaster: "pusher",
      key: "dc045394404acc7f6a5a",
      cluster: "ap2",
      forceTLS: true,
      authEndpoint: "/broadcasting/auth",
      auth: {
        headers: {
          Authorization: `Bearer ${localStorage.getItem("auth_token")}`,
        },
      },
    });

    // Subscribe to the private channel and listen for notifications
    window.Echo.private(`App.Models.User.${userId}`).listen(
      ".notification",
      (event) => {
        console.log("Notification received:", event);

        // Show toaster notification
        this.$toast.show(event.title, {
          type: "info", // You can use 'success', 'error', 'warning', or 'info'
          position: "top-right",
          duration: 5000, // Duration in milliseconds
          onClick: () => {
            // Redirect to the deep link when the toaster is clicked
            if (event.deepLink) {
              this.$inertia.visit(event.deepLink); // Use Inertia to navigate to the deep link
            }
          },
        });
      }
    );
    echoInitialized = true; 
    console.log("✅ Echo initialized successfully.");
  },
};
</script>