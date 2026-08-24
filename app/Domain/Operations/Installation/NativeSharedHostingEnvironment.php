<?php

namespace App\Domain\Operations\Installation;

use Illuminate\Http\Request;

final class NativeSharedHostingEnvironment
{
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
        );
    }
}
