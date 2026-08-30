<title>{{ $pageTitle }}</title>
<meta name="description" content="{{ $pageDescription }}">
<link rel="canonical" href="{{ url()->current() }}">

{{-- Open Graph: what Slack, LinkedIn and Facebook read. --}}
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ $pageTitle }}">
<meta property="og:description" content="{{ $pageDescription }}">
<meta property="og:type" content="{{ $type }}">
<meta property="og:url" content="{{ url()->current() }}">
@if ($imageUrl)
    <meta property="og:image" content="{{ $imageUrl }}">
@endif
@if ($published)
    <meta property="article:published_time" content="{{ \Illuminate\Support\Carbon::parse($published)->toIso8601String() }}">
@endif

{{-- Twitter/X uses its own namespace but falls back to og: for the rest. --}}
<meta name="twitter:card" content="{{ $imageUrl ? 'summary_large_image' : 'summary' }}">
<meta name="twitter:title" content="{{ $pageTitle }}">
<meta name="twitter:description" content="{{ $pageDescription }}">
@if ($imageUrl)
    <meta name="twitter:image" content="{{ $imageUrl }}">
@endif
