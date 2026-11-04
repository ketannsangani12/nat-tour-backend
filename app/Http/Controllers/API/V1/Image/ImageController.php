<?php

namespace App\Http\Controllers\API\V1\Image;

use App\Http\Controllers\API\V1\ApiController;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use finfo;

class ImageController extends ApiController
{
    /**
     * Validate and sanitize the image path.
     */
    private function validatePath(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        $path = ltrim($path, '/');

        // Prevent directory traversal
        if (str_contains($path, '..')) {
            return null;
        }

        // Only allow images from tour-operator directory
        if (!str_starts_with($path, 'images/tour-operator/')) {
            return null;
        }

        return $path;
    }

    /**
     * Proxy an S3 image - serves the image directly with CORS headers.
     * Use: /api/v1/image/proxy?path=images/tour-operator/123/logo/logo.png
     */
    public function proxy(Request $request): Response
    {
        $path = $this->validatePath($request->input('path'));

        if (!$path) {
            return response('Invalid path', 400);
        }

        $disk = Storage::disk('s3');

        if (!$disk->exists($path)) {
            return response('Not found', 404);
        }

        try {
            $content = $disk->get($path);

            $mimeType = 'image/png';
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedType = $finfo->buffer($content);
            if ($detectedType) {
                $mimeType = $detectedType;
            }

            return response($content, 200, [
                'Content-Type' => $mimeType,
                'Cache-Control' => 'public, max-age=86400',
                'Access-Control-Allow-Origin' => '*',
            ]);
        } catch (\Exception $e) {
            return response('Failed to load image', 500);
        }
    }

    /**
     * Convert an S3 image to base64.
     * Accepts path as query parameter: ?path=images/tour-operator/123/logo/logo.png
     */
    public function toBase64(Request $request): JsonResponse
    {
        $path = $this->validatePath($request->input('path'));

        if (!$path) {
            return $this->setCode(400)
                ->setMessage('Invalid or missing path')
                ->respond(null);
        }

        $disk = Storage::disk('s3');

        if (!$disk->exists($path)) {
            return $this->setCode(404)
                ->setMessage('Image not found')
                ->respond(null);
        }

        try {
            $content = $disk->get($path);

            $mimeType = 'image/png';
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedType = $finfo->buffer($content);
            if ($detectedType) {
                $mimeType = $detectedType;
            }

            $base64 = 'data:' . $mimeType . ';base64,' . base64_encode($content);

            return $this->respond([
                'base64' => $base64,
                'mimeType' => $mimeType,
            ]);
        } catch (\Exception $e) {
            return $this->setCode(500)
                ->setMessage('Failed to process image')
                ->respond(null);
        }
    }
}
