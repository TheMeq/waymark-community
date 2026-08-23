<!DOCTYPE html>
<html lang="en" style="@foreach ($theme->cssVariables() as $property => $value) {{ $property }}: {{ $value }}; @endforeach">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ ucfirst($viewport) }} preview</title>@vite(['resources/css/app.css'])</head>
<body class="bg-surface p-6">
    <p class="mb-4 text-sm font-semibold">{{ ucfirst($viewport) }} preview</p>
    <div class="mx-auto overflow-hidden rounded-[var(--wm-radius-md)] border border-border bg-surface-raised shadow-[var(--wm-shadow-card)]" style="max-width: {{ $viewport === 'mobile' ? '390px' : ($viewport === 'tablet' ? '768px' : '1440px') }}">
        <x-public.site-header :site="$site" />
        <section class="bg-surface-soft p-10"><h1 class="text-4xl">Great walks. <span class="text-brand">Good people.</span></h1><p class="mt-4">Preview branding across the public visual system.</p><x-public.button class="mt-5" href="#">Upcoming walks</x-public.button></section>
        <x-public.site-footer :site="$site" />
    </div>
</body>
</html>
