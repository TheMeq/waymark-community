<?php

namespace App\Domain\Operations\Installation;

use App\Domain\Operations\Installation\Contracts\PublicApplicationExposureProbe;
use Illuminate\Http\Request;

final class NativeSharedHostingEnvironment
{
    public function __construct(private readonly PublicApplicationExposureProbe $exposureProbe) {}

    public function capture(Request $request): SharedHostingEnvironment
    {
        $documentRoot = trim((string) $request->server('DOCUMENT_ROOT', public_path()));

        return new SharedHostingEnvironment(
            documentRoot: $documentRoot !== '' ? $documentRoot : public_path(),
            applicationRoot: base_path(),
            publicPath: public_path(),
            environmentPath: base_path('.env'),
            production: config('app.env') === 'production',
            debug: (bool) config('app.debug'),
            protectedPublicHtmlLayout: config('waymark.deployment_layout') === 'public-html'
                && $this->exposureProbe->protected($request->root()) === true,
        );
    }
}
