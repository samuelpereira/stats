<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="theme-color" content="#000000" />
<meta name="mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<meta name="apple-mobile-web-app-status-bar-style" content="default" />
<meta name="apple-mobile-web-app-title" content="{{ config('app.name', 'Laravel') }}" />

<title>
    @if (($titleAsGiven ?? false) && filled($title ?? null))
        {{ $title }}
    @elseif (filled($title ?? null))
        {{ __($title).' - '.config('app.name', 'Laravel') }}
    @else
        {{ config('app.name', 'Laravel') }}
    @endif
</title>

<link rel="manifest" href="{{ route('manifest') }}">
<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/icons/favicon-32.png" type="image/png" sizes="32x32">
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])
@fluxAppearance
