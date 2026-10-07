<?php

namespace App\Http\Controllers;

use App\Dashboard\Services\DimensionEncodingService;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class SymbolController extends Controller
{
    public function __invoke(string $symbol, DimensionEncodingService $service): BinaryFileResponse
    {
        $path = $service->resolveSymbolFile($symbol);

        abort_if($path === null, 404);

        return response()->file($path, [
            'Content-Type'  => str_ends_with($path, '.png') ? 'image/png' : 'image/bmp',
            'Cache-Control' => 'private, max-age=86400',
        ]);
    }
}