<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Aws\S3\S3Client;

class FileController extends Controller
{

    public function showForm()
    {
        // dd(1);
        return view('upload-form');
    }

    public function uploadImage(Request $request)
    {
        Log::info('Upload process started.');

        $request->validate([
            'image' => 'required|file|mimes:jpeg,png,jpg,gif|max:10240', // max 10MB
        ]);
        Log::info('Validation passed.');

        $image = $request->file('image');
        $filename = time() . '_' . $image->getClientOriginalName();
        Log::info('File to upload: ' . $filename);
        dd($filename);

        try {
            $client = new S3Client([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'),
                'endpoint' => env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
                'http' => [
                    'verify' => false, // local SSL bypass
                ],
            ]);
            Log::info('S3 Client created.');

            $client->putObject([
                'Bucket' => env('AWS_BUCKET'),
                'Key' => $filename,
                'Body' => fopen($image->getPathname(), 'r'),
                'ACL' => 'public-read',
                'ContentType' => $image->getMimeType(),
            ]);
            Log::info('Upload successful on S3');

            // 🔥 Construct public URL using AWS_URL
            $publicUrl = rtrim(env('AWS_URL'), '/') . '/' . $filename;

            Log::info('Public image URL: ' . $publicUrl);

            return back()->with('success', 'Upload successful! File URL: ' . $publicUrl);
        } catch (\Exception $e) {
            Log::error('Upload failed.', ['error' => $e->getMessage()]);
            return back()->withErrors(['upload' => 'Upload failed: ' . $e->getMessage()]);
        }
    }


    public function uploadImages(Request $request)
    {
        Log::info('Multiple upload process started.');

        // Validation: images.* ka matlab har ek file ke liye validation hai
        $request->validate([
            'images.*' => 'required|file|mimes:jpeg,png,jpg,gif|max:10240', // 10MB max
        ]);
        Log::info('Validation passed.');

        $images = $request->file('images');

        // Agar images null hain ya array nahi hain to error bhejo
        if (!$images || !is_array($images)) {
            return back()->withErrors(['upload' => 'No files uploaded or invalid input.']);
        }

        $uploadedUrls = [];

        try {
            $client = new S3Client([
                'version' => 'latest',
                'region' => env('AWS_DEFAULT_REGION'),
                'endpoint' => env('AWS_ENDPOINT'),
                'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT'),
                'credentials' => [
                    'key' => env('AWS_ACCESS_KEY_ID'),
                    'secret' => env('AWS_SECRET_ACCESS_KEY'),
                ],
                'http' => [
                    'verify' => false, // Local SSL bypass agar zarurat ho to
                ],
            ]);
            Log::info('S3 Client created.');

            foreach ($images as $image) {
                $filename = time() . '_' . $image->getClientOriginalName();
                Log::info('Uploading file: ' . $filename);
                // dd($filename);

                $client->putObject([
                    'Bucket' => env('AWS_BUCKET'),
                    'Key' => $filename,
                    'Body' => fopen($image->getPathname(), 'r'),
                    'ACL' => 'public-read',
                    'ContentType' => $image->getMimeType(),
                ]);
                Log::info('Upload successful for file: ' . $filename);

                $publicUrl = rtrim(env('AWS_URL'), '/') . '/' . $filename;
                $uploadedUrls[] = $publicUrl;
            }

            Log::info('All uploads completed.');

            return back()->with('success', 'Uploads successful! File URLs: ' . implode(', ', $uploadedUrls));
            
            return back()->with([
                'success' => 'Uploads successful!',
                'uploadedUrls' => $uploadedUrls,
            ]);



        } catch (\Exception $e) {
            Log::error('Upload failed.', ['error' => $e->getMessage()]);
            return back()->withErrors(['upload' => 'Upload failed: ' . $e->getMessage()]);
        }
    }


}
