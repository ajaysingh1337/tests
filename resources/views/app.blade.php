@php
    $site_title = App\Models\GeneralSetting::where('name' , 'site_title')->first();
    $site_logo = App\Models\GeneralSetting::where('name' , 'dark_logo')->first();
    $is_ai_chatbot_enable = App\Models\GeneralSetting::where('name', 'is_ai_chatbot_enable')->first();

@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title inertia>{{ $site_title && $site_title->value ? $site_title->value : config('app.name', 'Admin') }}</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('images/favicon.png') }}">
    <link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    />
    <!-- Fonts -->
    {{-- <link rel="stylesheet" href="https://fonts.bunny.net/css2?family=Nunito:wght@400;600;700&display=swap"> --}}

    <link href="https://assets.calendly.com/assets/external/widget.css" rel="stylesheet">
    <script src="https://assets.calendly.com/assets/external/widget.js" type="text/javascript" async></script>
    @routes
    @vite('resources/js/app.js')
    @inertiaHead
</head>

<body class="font-sans antialiased">
    @inertia
</body>

@if ($is_ai_chatbot_enable->value == 1)

    <script>
        var botmanWidget = {
            title: '{{ $site_title && $site_title->value ? $site_title->value : config('app.name', 'Admin') }}',
            aboutText: '',
            mainColor: '#fac14d',
            aboutLink: '',
            bubbleBackground: '#fac14d',
            bubbleAvatarUrl: '{{ $site_logo && $site_logo->value ? $site_logo->value : null }}',
            introMessage: "✋ Hello! Welcome to <b>{{ $site_title->value }}</b>. How can I assist you today with your legal needs?"
        };
    </script>

    <script src='https://cdn.jsdelivr.net/npm/botman-web-widget@0/build/js/widget.js'></script>

@else
    <script type="text/javascript" src="https://chatterpal.me/build/js/chatpal.js?8.3"
        integrity="sha384-+YIWcPZjPZYuhrEm13vJJg76TIO/g7y5B14VE35zhQdrojfD9dPemo7q6vnH44FR" crossorigin="anonymous"
        data-cfasync="false"></script>
    <script>
        var chatPal = new ChatPal({
            embedId: 'RWvE8Za29KJL',
            remoteBaseUrl: 'https://chatterpal.me/',
            version: '8.3'
        });
    </script>
@endif

<script src="https://cdn.rawgit.com/davidshimjs/qrcodejs/gh-pages/qrcode.min.js"></script>
<script src="https://js.pusher.com/beams/1.0/push-notifications-cdn.js"></script>
<script>

const script = document.createElement('script');
//script.src = `https://maps.googleapis.com/maps/api/js?key=669654626553-ina4a3hp5nng9nm8hu1e1jhh6tngko6e.apps.googleusercontent.com&libraries=places`;
script.src = "https://maps.googleapis.com/maps/api/js?key=AIzaSyAeirrkfO92G0Xz37zXwAqC_xLco636W9E&libraries=drawing,places&v=3.45.8"
script.async = true;
script.defer = true;
document.head.appendChild(script);
</script>
</html>

