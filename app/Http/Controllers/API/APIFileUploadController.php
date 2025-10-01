<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Aws\S3\S3Client;

class APIFileUploadController extends Controller {
    public function __construct()
    {
        $this->middleware(['api', 'auth:api', 'verified', 'api_setting']);
    }

    public function uploadFile(Request $request)
    {
        $user = auth()->user();
        
        if(!$user){
            $response = generateResponse(null, false, "Unauthorized", null, 'collection');
            return response()->json($response);
        }
        
        $validator = Validator::make($request->all(), [
            'file' => 'required|file',
        ]);

        if ($validator->fails()) {
            $response = generateResponse(null, false, $validator->errors()->first(), null, 'collection');
            return response()->json($response, 422);
        }
        
        $s3 = Storage::disk('s3');
        $file = $request->file('file');
        $path = $s3->put('uploads', $file);
        // $s3->setVisibility($path, 'public');

        $response = generateResponse([
            'url' => $s3->url($path),
            'key' => $path
        ], true, "File uploaded successfully", null, 'collection');

        return response()->json($response);
    }

    public function presignedUpload(Request $request)
    {
        $user = auth()->user();
        
        if(!$user){
            $response = generateResponse(null, false, "Unauthorized", null, 'collection');
            return response()->json($response);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'string|required'
        ]);

        if ($validator->fails()) {
            $response = generateResponse(null, false, $validator->errors()->first(), null, 'collection');
            return response()->json($response, 422);
        }

        $expiry = "+10 minutes";
        $filename = $request->name;

        $client = new S3Client([
            'credentials' => [
                'key' => env('AWS_ACCESS_KEY_ID'),
                'secret' => env('AWS_SECRET_ACCESS_KEY'),
            ],
            'region' => env('AWS_REGION'),
            'version' => 'latest',
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT'),
        ]);

        $cmd = $client->getCommand('PutObject', [
            'Bucket' => env('AWS_BUCKET'),
            'Key' => "uploads/{$filename}",
            'ACL' => 'private'
        ]);

        $request = $client->createPresignedRequest($cmd, $expiry);

        $presignedUrl = (string)$request->getUri();

        $response = generateResponse([
            'upload_url' => $presignedUrl,
            'key' => "uploads/{$filename}",
            'file_url' => env('AWS_URL') . "/uploads/{$filename}",
        ], true, "Presigned URL generated successfully", null, 'collection');

        return response()->json($response);
    }
}

?>