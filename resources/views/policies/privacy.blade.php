@extends('layouts.public')

@section('title', 'Privacy')
@section('meta_description', 'Privacy information for '.$site['name'].', including accounts, photos, cookies and contact details.')
@section('site-header')<x-public.site-header :site="$site" />@endsection
@section('site-footer')<x-public.site-footer :site="$site" />@endsection

@section('content')
    <article class="bg-surface-raised py-10 sm:py-14">
        <div class="wm-container max-w-[var(--wm-container-copy)]">
            <h1 class="text-4xl text-ink sm:text-5xl">Privacy</h1>
            <p class="mt-5 text-lg text-ink-muted">This page explains how the {{ $site['name'] }} website uses information when you browse, create an account, contact the group or share photos.</p>

            <section class="mt-8" aria-labelledby="privacy-contact">
                <h2 id="privacy-contact" class="text-2xl text-ink">Who to contact</h2>
                <p class="mt-3 text-ink-muted">For questions about your information, membership records or the group’s privacy arrangements, <a class="font-semibold text-brand underline" href="{{ route('contact.create') }}">contact {{ $site['name'] }}</a>. Include enough detail to help the organisers find the relevant information, but avoid sending sensitive information through the contact form.</p>
            </section>

            <section class="mt-8" aria-labelledby="privacy-accounts">
                <h2 id="privacy-accounts" class="text-2xl text-ink">Accounts and email</h2>
                <p class="mt-3 text-ink-muted">An account stores your name, email address, password in a protected form, and any optional profile details, saved events and communication preferences you provide. These support sign-in, account security and the features you choose to use.</p>
                <p class="mt-3 text-ink-muted">Your account name is private. Your public display name can appear with contributions and, if enabled, your walk leader profile. Optional email subscriptions can be changed in <a class="font-semibold text-brand underline" href="{{ route('account.profile.edit') }}">profile settings</a>. Essential account and security messages are sent separately.</p>
            </section>

            <section class="mt-8" aria-labelledby="privacy-photos">
                <h2 id="privacy-photos" class="text-2xl text-ink">Photos and public contributions</h2>
                <p class="mt-3 text-ink-muted">Approved gallery photos, captions and photographer credits are public. Profile photos become public when saved and can appear on an enabled walk leader profile. Only upload photos you are entitled to share, and consider the privacy of anyone pictured.</p>
                <p class="mt-3 text-ink-muted">Published uploads are processed to remove embedded image metadata. You can replace or remove your profile photo in profile settings. Use the report link on a gallery photo, or contact the group, to raise a concern about a published photo.</p>
            </section>

            <section class="mt-8" aria-labelledby="privacy-cookies">
                <h2 id="privacy-cookies" class="text-2xl text-ink">Cookies and technical information</h2>
                <p class="mt-3 text-ink-muted">Essential cookies support sign-in, security and your cookie choices. Optional analytics, when configured, stays off unless you allow it. You can change your choice in <a class="font-semibold text-brand underline" href="{{ route('cookie-settings.edit') }}">cookie settings</a>.</p>
                <p class="mt-3 text-ink-muted">Operating the website also involves technical information such as request and error logs. Website hosting, email delivery and any enabled external services may process information needed to provide those services. Contact the group for details of its providers, storage locations and retention arrangements.</p>
            </section>

            <section class="mt-8" aria-labelledby="privacy-messages">
                <h2 id="privacy-messages" class="text-2xl text-ink">Contact messages</h2>
                <p class="mt-3 text-ink-muted">Information submitted through the contact form is used to route and respond to your enquiry. A copy may be held in the website’s recent submissions and in the recipient’s email system. The organisers can explain how long they retain correspondence.</p>
            </section>

            <section class="mt-8" aria-labelledby="privacy-choices">
                <h2 id="privacy-choices" class="text-2xl text-ink">Your information and choices</h2>
                <p class="mt-3 text-ink-muted">You can update your profile details and email preferences online. In <a class="font-semibold text-brand underline" href="{{ route('account.privacy.show') }}">account privacy settings</a>, you can request a copy of your account data or ask for account deletion. Deletion requests are reviewed by an administrator; event history is retained with an anonymised account record.</p>
                <p class="mt-3 text-ink-muted">Contact the group to ask about access, correction, removal or other concerns involving information held outside your account. Group membership is managed separately from this website account.</p>
            </section>
        </div>
    </article>
@endsection
