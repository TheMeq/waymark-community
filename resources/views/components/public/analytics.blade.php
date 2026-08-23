@if($analytics?->provider === 'plausible')
    <script defer data-domain="{{ $analytics->trackingId }}" src="https://plausible.io/js/script.js"></script>
@elseif($analytics?->provider === 'ga4')
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ urlencode($analytics->trackingId) }}"></script>
    <script>
        window.dataLayer = window.dataLayer || [];
        function gtag(){dataLayer.push(arguments);}
        gtag('js', new Date());
        gtag('config', @json($analytics->trackingId));
    </script>
@endif
