<?php

namespace App\Http\Controllers;

use App\Domain\Operations\Updates\Actions\FinalizeUpdateRollback;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final readonly class UpdateRollbackController
{
    public function __construct(private FinalizeUpdateRollback $rollback) {}

    public function __invoke(Request $request): Response
    {
        try {
            $this->rollback->handle((string) $request->input('token'));

            return response()->view('updates.rollback-complete')
                ->header('Referrer-Policy', 'no-referrer')
                ->header('Cache-Control', 'no-store, private');
        } catch (Throwable $exception) {
            report($exception);

            return response()->view('recovery.show', ['completed' => false], 500)
                ->withHeaders(['Referrer-Policy' => 'no-referrer', 'Cache-Control' => 'no-store, private']);
        }
    }
}
