<!DOCTYPE html>
<html>
<head>
    <title>Upload Image to R2</title>
</head>
<body>
    <h1>Upload Image to Cloudflare R2</h1>

    @if ($errors->any())
        <div style="color: red;">
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif


    <!-- @if(session('success'))
        <div>{{ session('success') }}</div>
        <img src="{{ Str::after(session('success'), 'File URL: ') }}" width="200">
    @endif -->

@php
    $url = Str::after(session('success'), 'File URL: ');
@endphp

@if(session('success'))
    <div>{{ Str::before(session('success'), 'File URL:') }}</div>

    @if($url)
        <a href="{{ $url }}" target="_blank">
            <img src="{{ $url }}" width="200">
        </a>
    @endif
@endif


    <!-- <form action="{{ url('/upload-to-r2') }}" method="POST" enctype="multipart/form-data">
        @csrf
        <input type="file" name="image" required />
        <button type="submit">Upload Image</button>
    </form> -->

    <br><br><br><br><br><br><br><br>

    <form action="{{ route('upload-to-r2') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="images[]" multiple>
    <button type="submit">Upload multiple image</button>
    </form>

    @if ($errors->any())
    <div style="color:red;">
        <ul>
            @foreach ($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('success'))
    <div style="color:green; margin-top: 20px;">
        <strong>{{ session('success') }}</strong>
    </div>
@endif

@if (session('uploadedUrls'))
    <div style="margin-top: 20px;">
        <h3>Uploaded Images:</h3>
        <div style="display: flex; flex-wrap: wrap; gap: 20px;">
            @foreach (session('uploadedUrls') as $url)
                <div style="border: 1px solid #ccc; padding: 10px; width: 200px;">
                    <img src="{{ $url }}" alt="Uploaded Image" style="max-width: 100%; height: auto;">
                    <p style="word-wrap: break-word; font-size: 14px;">
                        <a href="{{ $url }}" target="_blank">{{ $url }}</a>
                    </p>
                </div>
            @endforeach
        </div>
    </div>
@endif


</body>
</html>
