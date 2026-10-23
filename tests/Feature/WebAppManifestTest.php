<?php

test('web app manifest is available for installation', function () {
    $response = $this->get(route('manifest'));

    $response
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('display', 'standalone')
        ->assertJsonPath('start_url', '/dashboard')
        ->assertJsonPath('name', config('app.name'))
        ->assertJsonPath('icons.0.src', '/brand/icon-192.png')
        ->assertJsonPath('icons.1.src', '/brand/icon-512.png');
});

test('pages advertise standalone web app metadata', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<meta name="apple-mobile-web-app-capable" content="yes"', false)
        ->assertSee('<meta name="mobile-web-app-capable" content="yes"', false)
        ->assertSee('rel="manifest"', false)
        ->assertSee(route('manifest'), false)
        ->assertSee('brand/favicon-32.png', false);
});
