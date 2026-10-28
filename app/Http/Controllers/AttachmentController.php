<?php

namespace App\Http\Controllers;

use App\Models\Attachment;
use App\Models\FundRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Supporting documents for a fund request. Files go to the PRIVATE "local"
 * disk (storage/app/private) — never public — and are only served through
 * download(), which re-checks that the user may view the request.
 *
 * Upload/remove follows the same window as editing the request itself
 * (FundRequestPolicy::attach): Requestor only, while Draft or Returned to
 * Requestor. After submission the document set is frozen with the record.
 */
class AttachmentController extends Controller
{
    private const DISK = 'local';

    public function store(Request $request, FundRequest $fundRequest)
    {
        $this->authorize('attach', $fundRequest);

        $request->validate([
            'files' => ['required', 'array', 'min:1', 'max:5'],
            'files.*' => ['file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'], // 5 MB each
        ], [
            'files.*.max' => 'Each file must be 5 MB or smaller.',
            'files.*.mimes' => 'Allowed types: PDF, JPG, PNG, Word, Excel.',
            'files.*.uploaded' => 'Upload failed — the file may exceed the server upload limit (php.ini upload_max_filesize).',
        ]);

        foreach ($request->file('files') as $file) {
            // Name on disk is generated; the client's file name is kept only as a label.
            $storedName = Str::uuid().'.'.$file->extension();
            $path = $file->storeAs("attachments/fund-requests/{$fundRequest->id}", $storedName, self::DISK);

            $fundRequest->attachments()->create([
                'original_file_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'stored_file_name' => $storedName,
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize(),
                'uploaded_by' => Auth::id(),
                'uploaded_at' => now(),
            ]);
        }

        $count = count($request->file('files'));

        return back()->with('status', $count === 1 ? 'File attached.' : "{$count} files attached.");
    }

    public function download(FundRequest $fundRequest, Attachment $attachment)
    {
        $this->authorize('view', $fundRequest);
        $this->assertBelongsTo($fundRequest, $attachment);

        abort_unless(Storage::disk(self::DISK)->exists($attachment->file_path), 404, 'File not found on storage.');

        return Storage::disk(self::DISK)->download($attachment->file_path, $attachment->original_file_name);
    }

    public function destroy(FundRequest $fundRequest, Attachment $attachment)
    {
        $this->authorize('attach', $fundRequest);
        $this->assertBelongsTo($fundRequest, $attachment);

        Storage::disk(self::DISK)->delete($attachment->file_path);
        $attachment->delete();

        return back()->with('status', 'Attachment removed.');
    }

    /** Stops /fund-requests/1/attachments/99 from reaching another request's file. */
    private function assertBelongsTo(FundRequest $fundRequest, Attachment $attachment): void
    {
        abort_unless(
            $attachment->attachable_type === $fundRequest->getMorphClass()
                && (int) $attachment->attachable_id === $fundRequest->id,
            404
        );
    }
}
