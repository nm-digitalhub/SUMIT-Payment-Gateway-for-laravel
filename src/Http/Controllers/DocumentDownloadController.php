<?php

declare(strict_types=1);

namespace OfficeGuy\LaravelSumitGateway\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use OfficeGuy\LaravelSumitGateway\Models\OfficeGuyDocument;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentDownloadController extends Controller
{
    /**
     * Download a document PDF.
     *
     * Uses Route Model Binding to automatically load the document by ID.
     * The route parameter {document} will be resolved to the OfficeGuyDocument model.
     *
     * @param  OfficeGuyDocument  $document  Document model (auto-loaded via route model binding)
     */
    public function download(OfficeGuyDocument $document): Response | BinaryFileResponse
    {
        // Document is automatically loaded via route model binding
        // No need for manual query - Laravel does it for us!

        // Authorization check - ensure user can view this document
        $this->authorizeDocumentAccess($document);

        // Check if file exists locally
        if (! empty($document->file_path) && Storage::disk($document->disk ?? 'local')->exists($document->file_path)) {
            return Storage::disk($document->disk ?? 'local')->download($document->file_path);
        }

        // If no local file, try download URL from SUMIT
        if (! empty($document->document_download_url)) {
            return redirect($document->document_download_url);
        }

        abort(404, 'Document file not found');
    }

    /**
     * Check if the current user can access this document.
     *
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException
     */
    protected function authorizeDocumentAccess(OfficeGuyDocument $document): void
    {
        // If user is not authenticated, deny access
        if (! Auth::check()) {
            abort(403, 'Authentication required');
        }

        $user = Auth::user();

        // Check if document belongs to this user
        // First try direct documentable relationship
        if ($document->documentable) {
            $owner = $this->getDocumentOwner($document->documentable);

            if ($owner && $owner->id === $user->id) {
                return; // User owns this document
            }
        }

        // Check if user is admin (can view all documents)
        if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
            return; // Admin can view all
        }

        if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
            return; // Admin can view all
        }

        // If we got here, user cannot access this document
        abort(403, 'Unauthorized to access this document');
    }

    /**
     * Get the owner (user/client) of a documentable model.
     *
     * @param  mixed  $documentable
     */
    protected function getDocumentOwner($documentable): mixed
    {
        // If documentable IS a User/Client, return it
        if ($documentable instanceof \Illuminate\Contracts\Auth\Authenticatable) {
            return $documentable;
        }

        // Try common relationships
        $relationships = ['user', 'client', 'customer', 'owner'];

        foreach ($relationships as $relation) {
            if (method_exists($documentable, $relation) && $documentable->relationLoaded($relation)) {
                return $documentable->{$relation};
            }

            if (method_exists($documentable, $relation)) {
                try {
                    return $documentable->{$relation}()->first();
                } catch (\Exception) {
                    continue;
                }
            }
        }

        return null;
    }
}
