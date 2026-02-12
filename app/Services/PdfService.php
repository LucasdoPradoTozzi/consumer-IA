<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PdfService
{
    /**
     * Generate PDF from HTML content
     *
     * @param string $html HTML content to convert to PDF
     * @param string $filename Filename without extension (e.g., 'cover_letter_123')
     * @return string Full path to generated PDF file
     * @throws \Exception
     */
    public function generate(string $html, string $filename): string
    {
        $startTime = microtime(true);

        try {
            Log::debug('[PdfService] Generating PDF', [
                'filename' => $filename,
                'html_length' => strlen($html),
            ]);

            // Ensure directory exists
            $directory = 'generated';
            if (!Storage::exists($directory)) {
                Storage::makeDirectory($directory);
            }

            // Generate PDF
            $pdf = Pdf::loadHTML($html);
            $pdf->setPaper('a4', 'portrait');

            // Generate filename with extension
            $fullFilename = $filename . '.pdf';
            $path = $directory . '/' . $fullFilename;

            // Save to storage
            Storage::put($path, $pdf->output());

            $fullPath = Storage::path($path);

            $duration = microtime(true) - $startTime;

            Log::info('[PdfService] PDF generated successfully', [
                'filename' => $fullFilename,
                'path' => $fullPath,
                'size' => Storage::size($path),
                'duration' => round($duration, 2),
            ]);

            return $fullPath;
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            Log::error('[PdfService] Failed to generate PDF', [
                'filename' => $filename,
                'html_length' => strlen($html),
                'duration' => round($duration, 2),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw exception
            throw $e;
        }
    }

    /**
     * Generate cover letter PDF
     *
     * @param string $coverLetter Cover letter text
     * @param array $jobData Job information
     * @param array $candidateProfile Candidate information
     * @param string $jobId Job ID for filename
     * @return string Path to generated PDF
     * @throws \Exception
     */
    public function generateCoverLetterPdf(
        string $coverLetter,
        array $jobData,
        array $candidateProfile,
        string $jobId
    ): string {
        $candidateName = $candidateProfile['name'] ?? 'Candidate';
        $candidateEmail = $candidateProfile['email'] ?? '';
        $candidatePhone = $candidateProfile['phone'] ?? '';

        $jobTitle = $jobData['title'] ?? 'Position';
        $company = $jobData['company'] ?? 'Company';

        $date = now()->format('F d, Y');

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11pt;
            line-height: 1.6;
            color: #333;
            margin: 40px;
        }
        .header {
            margin-bottom: 30px;
        }
        .contact-info {
            margin-bottom: 20px;
        }
        .date {
            margin-bottom: 20px;
        }
        .recipient {
            margin-bottom: 30px;
        }
        .content {
            text-align: justify;
            white-space: pre-wrap;
        }
        .signature {
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="header">
        <strong>{$candidateName}</strong><br>
        {$candidateEmail}<br>
        {$candidatePhone}
    </div>

    <div class="date">
        {$date}
    </div>

    <div class="recipient">
        Hiring Manager<br>
        <strong>{$company}</strong><br>
        Re: {$jobTitle}
    </div>

    <div class="content">
{$coverLetter}
    </div>

    <div class="signature">
        Sincerely,<br><br>
        {$candidateName}
    </div>
</body>
</html>
HTML;

        $filename = "cover_letter_{$jobId}";
        return $this->generate($html, $filename);
    }

    /**
     * Generate resume PDF
     *
     * @param string $resumeText Resume text
     * @param array $candidateProfile Candidate information
     * @param string $jobId Job ID for filename
     * @return string Path to generated PDF
     * @throws \Exception
     */
    public function generateResumePdf(
        string $resumeText,
        array $candidateProfile,
        string $jobId
    ): string {
        $candidateName = $candidateProfile['name'] ?? 'Candidate';

        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #333;
            margin: 30px;
        }
        h1 {
            font-size: 18pt;
            margin-bottom: 10px;
            color: #2c3e50;
        }
        .content {
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <h1>{$candidateName}</h1>
    <div class="content">
{$resumeText}
    </div>
</body>
</html>
HTML;

        $filename = "resume_{$jobId}";
        return $this->generate($html, $filename);
    }
}
