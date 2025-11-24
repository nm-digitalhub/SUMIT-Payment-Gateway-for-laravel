<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentDownloadController extends Controller
{
    public function download(string $documentId): Response|BinaryFileResponse
    {
        $doc = OfficeGuyDocument::where('document_id', $documentId)->first();
        if (!$doc || empty($doc->file_path) || !Storage::disk($doc->disk ?? 'local')->exists($doc->file_path)) {
            abort(404);
        }

        return Storage::disk($doc->disk ?? 'local')->download($doc->file_path);
    }
}
