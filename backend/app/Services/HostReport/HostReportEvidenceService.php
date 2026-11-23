<?php

namespace App\Services\HostReport;

use App\Models\HostReport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

final class HostReportEvidenceService
{
    public function newPath(int $bookingId, string $mime): string
    {
        return 'host-report-evidence/'.$bookingId.'/'.Str::uuid().'.'.HostReport::EVIDENCE_EXTENSIONS[$mime];
    }

    public function store(string $path, UploadedFile $file): void
    {
        if (Storage::disk('local')->putFileAs(dirname($path), $file, basename($path), 'private') === false) {
            throw new RuntimeException('Unable to store report evidence.');
        }
    }

    public function cleanupUncommitted(string $path, int $bookingId): void
    {
        try {
            if (HostReport::query()->useWritePdo()->where('booking_id', $bookingId)->where('evidence_path', $path)->exists()
                || Storage::disk('local')->delete($path)) {
                return;
            }
        } catch (Throwable) {
            // Retain the file when the database commit outcome cannot be checked.
        }

        Log::warning('Unable to reconcile report evidence after a write failure.', ['booking_id' => $bookingId]);
    }

    public function download(int $reportId): StreamedResponse
    {
        $report = HostReport::query()->findOrFail($reportId);
        $evidence = $report->evidenceMetadata();
        $disk = Storage::disk('local');
        $directory = 'host-report-evidence/'.$report->booking_id.'/';

        if ($evidence === null
            || !preg_match('~\A'.preg_quote($directory, '~').'[a-f0-9]{8}(?:-[a-f0-9]{4}){3}-[a-f0-9]{12}\.'.HostReport::EVIDENCE_EXTENSIONS[$evidence['mime']].'\z~', $report->evidence_path)
            || !$disk->exists($report->evidence_path)) {
            throw new NotFoundHttpException('Không tìm thấy tệp chứng cứ.');
        }

        return $disk->download($report->evidence_path, $evidence['filename'], [
            'Cache-Control' => 'private, no-store',
            'Content-Type' => $evidence['mime'],
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
