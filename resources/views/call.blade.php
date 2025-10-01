<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Call </title>
     <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"
        integrity="sha512-..."
        crossorigin="anonymous"
        referrerpolicy="no-referrer"
    />
   
     @viteReactRefresh
    @vite('reactjs/main.js') 
</head>
<body>
    <div
        id="react-root"
        data-room-id="{{ $room_id }}"
        data-app-data='@json($app_data)'
    ></div>
      <script>
            var current_user = @json(auth()->user());
            const currentUser = @json($current_user);
            
            const app_data = @json($app_data);
            const room_id = @json($room_id);
            
            window.current_user = currentUser;

            window.app_data = app_data;
            window.room_id = room_id;
        </script>
</body>
</html>
