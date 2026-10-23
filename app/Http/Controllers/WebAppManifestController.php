<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class WebAppManifestController
{
    public function __invoke(): Response
    {
        $name = (string) config('app.name');

        return response(
            json_encode([
                'id' => '/',
                'name' => $name,
                'short_name' => $name,
                'description' => __('Collect live stats, box scores, and team reports in one place.'),
                'start_url' => '/dashboard',
                'scope' => '/',
                'display' => 'standalone',
                'orientation' => 'any',
                'background_color' => '#000000',
                'theme_color' => '#000000',
                'icons' => [
                    [
                        'src' => '/brand/icon-192.png',
                        'sizes' => '192x192',
                        'type' => 'image/png',
                        'purpose' => 'any',
                    ],
                    [
                        'src' => '/brand/icon-512.png',
                        'sizes' => '512x512',
                        'type' => 'image/png',
                        'purpose' => 'any',
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            200,
            [
                'Content-Type' => 'application/manifest+json',
                'Cache-Control' => 'public, max-age=3600',
            ],
        );
    }
}
