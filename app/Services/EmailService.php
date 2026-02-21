<?php

namespace App\Services;

use App\DTO\JobPayload;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EmailService
{
    /**
     * Send job recommendation email with attachments
     *
     * @param JobPayload $job Job payload
     * @param string $coverLetterPdfPath Path to cover letter PDF
     * @param string $resumePdfPath Path to resume PDF
     * @param array $data Additional data (score, justification, etc.)
     * @return void
     * @throws \Exception
     */
    public function sendRecommendation(
        JobPayload $job,
        string $coverLetterPdfPath,
        string $resumePdfPath,
        array $data
    ): void {
        $startTime = microtime(true);

        $jobData = $job->data['job'] ?? [];
        $candidateData = $job->data['candidate'] ?? [];

        $recipientEmail = $candidateData['email'] ?? null;

        if (!$recipientEmail) {
            throw new \Exception('Candidate email not provided in job data');
        }

        $candidateName = $candidateData['name'] ?? 'Candidate';
        $jobTitle = $jobData['title'] ?? 'Position';
        $company = $jobData['company'] ?? 'Company';
        $score = $data['score'] ?? 0;
        $justification = $data['justification'] ?? '';

        try {
            Log::info('[EmailService] Sending recommendation email', [
                'job_id' => $job->jobId,
                'recipient' => $recipientEmail,
                'job_title' => $jobTitle,
                'score' => $score,
            ]);

            Mail::send([], [], function ($message) use (
                $recipientEmail,
                $candidateName,
                $jobTitle,
                $company,
                $score,
                $justification,
                $coverLetterPdfPath,
                $resumePdfPath,
                $job
            ) {
                $message->to($recipientEmail, $candidateName)
                    ->subject("Job Recommendation: {$jobTitle} at {$company} (Score: {$score})")
                    ->html($this->buildEmailHtml(
                        $candidateName,
                        $jobTitle,
                        $company,
                        $score,
                        $justification
                    ));

                // Attach PDFs
                if (file_exists($coverLetterPdfPath)) {
                    $message->attach($coverLetterPdfPath, [
                        'as' => 'cover_letter.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }

                if (file_exists($resumePdfPath)) {
                    $message->attach($resumePdfPath, [
                        'as' => 'resume.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }

                Log::debug('[EmailService] Email prepared', [
                    'job_id' => $job->jobId,
                    'attachments' => [
                        'cover_letter' => file_exists($coverLetterPdfPath),
                        'resume' => file_exists($resumePdfPath),
                    ],
                ]);
            });

            $duration = microtime(true) - $startTime;

            Log::info('[EmailService] Email sent successfully', [
                'job_id' => $job->jobId,
                'recipient' => $recipientEmail,
                'duration' => round($duration, 2),
            ]);
        } catch (\Exception $e) {
            $duration = microtime(true) - $startTime;

            Log::error('[EmailService] Failed to send email', [
                'job_id' => $job->jobId,
                'recipient' => $recipientEmail,
                'duration' => round($duration, 2),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Re-throw exception
            throw $e;
        }
    }

    /**
     * Build HTML email body
     *
     * @param string $candidateName
     * @param string $jobTitle
     * @param string $company
     * @param int $score
     * @param string $justification
     * @return string
     */
    private function buildEmailHtml(
        string $candidateName,
        string $jobTitle,
        string $company,
        int $score,
        string $justification
    ): string {
        $scoreColor = $score >= 80 ? '#27ae60' : ($score >= 60 ? '#f39c12' : '#95a5a6');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #3498db;
            color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .score-badge {
            display: inline-block;
            background-color: {$scoreColor};
            color: white;
            padding: 10px 20px;
            border-radius: 25px;
            font-weight: bold;
            font-size: 18px;
            margin: 15px 0;
        }
        .content {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .job-details {
            margin-bottom: 15px;
        }
        .job-details strong {
            color: #2c3e50;
        }
        .justification {
            background-color: white;
            padding: 15px;
            border-left: 4px solid #3498db;
            margin-top: 15px;
        }
        .footer {
            text-align: center;
            color: #7f8c8d;
            font-size: 12px;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ecf0f1;
        }
        .attachments {
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🎯 Job Recommendation</h1>
    </div>

    <p>Hello <strong>{$candidateName}</strong>,</p>

    <p>We've analyzed a job opportunity that matches your profile and generated personalized application materials for you.</p>

    <div class="content">
        <div class="job-details">
            <strong>Position:</strong> {$jobTitle}<br>
            <strong>Company:</strong> {$company}
        </div>

        <div style="text-align: center;">
            <div class="score-badge">Match Score: {$score}/100</div>
        </div>

        <div class="justification">
            <strong>Analysis:</strong><br>
            {$justification}
        </div>
    </div>

    <div class="attachments">
        <strong>📎 Attached Documents:</strong>
        <ul>
            <li>Cover Letter (tailored for this position)</li>
            <li>Resume (optimized for this job posting)</li>
        </ul>
    </div>

    <p>Review the attached documents and consider applying to this opportunity. The materials have been customized based on the job requirements.</p>

    <p>Good luck with your application!</p>

    <div class="footer">
        This is an automated recommendation generated by AI.<br>
        Please review all materials before submitting your application.
    </div>
</body>
</html>
HTML;
    }



    /**
     * Build HTML email body for application
     *
     * @param string $candidateName
     * @param string $jobTitle
     * @param string $company
     * @param int $score
     * @param string $justification
     * @return string
     */
    private function buildApplicationEmailHtml(
        string $candidateName,
        string $jobTitle,
        string $company,
        int $score,
        string $justification
    ): string {
        $scoreColor = $score >= 80 ? '#27ae60' : ($score >= 60 ? '#f39c12' : '#95a5a6');

        return <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .header {
            background-color: #3498db;
            color: white;
            padding: 20px;
            text-align: center;
            border-radius: 5px 5px 0 0;
        }
        .content {
            background-color: #f9f9f9;
            padding: 20px;
            border-radius: 0 0 5px 5px;
        }
        .job-details {
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .score-badge {
            display: inline-block;
            background-color: {$scoreColor};
            color: white;
            padding: 10px 20px;
            border-radius: 20px;
            font-weight: bold;
            font-size: 18px;
            margin: 15px 0;
        }
        .justification {
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 15px;
        }
        .attachments {
            background-color: #fff3cd;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #ffc107;
            margin-top: 20px;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 12px;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>📄 Application Materials Ready</h1>
    </div>

    <div class="content">
        <p>Hello <strong>{$candidateName}</strong>,</p>

        <p>Your personalized application materials for the <strong>{$jobTitle}</strong> position at <strong>{$company}</strong> have been generated and are attached to this email.</p>

        <div class="job-details">
            <strong>Position:</strong> {$jobTitle}<br>
            <strong>Company:</strong> {$company}
        </div>

        <div style="text-align: center;">
            <div class="score-badge">Match Score: {$score}/100</div>
        </div>

        <div class="justification">
            <strong>Match Analysis:</strong><br>
            {$justification}
        </div>

        <div class="attachments">
            <strong>📎 Attached Documents:</strong>
            <ul>
                <li><strong>Cover Letter</strong> - Personalized for this position</li>
                <li><strong>Resume</strong> - Optimized and tailored for the job requirements</li>
            </ul>
        </div>

        <p>Please review the attached documents. They have been customized based on the job description and your profile to maximize your chances of success.</p>

        <p>Best of luck with your application!</p>
    </div>

    <div class="footer">
        This is an automated service. Please review all materials before submitting.<br>
        Generated by AI-powered job matching system.
    </div>
</body>
</html>
HTML;
    }

    /**
     * Send job application email with attachments
     *
     * @param JobApplication $jobApplication
     * @param string $coverLetter
     * @param string $resumePath
     * @param string $subject
     * @param string $body
     * @return void
     */
    public function sendApplication(
        $jobApplication,
        string $coverLetter,
        string $resumePath,
        string $subject,
        string $body
    ): void {
        $startTime = microtime(true);

        $candidateData = config('candidate.profile')();
        $recipientEmail = $candidateData['email'] ?? null;

        if (!$recipientEmail) {
            throw new \Exception('Candidate email not configured');
        }

        $candidateName = $candidateData['name'] ?? 'Candidate';

        try {
            Log::info('[EmailService] Sending application email', [
                'job_id' => $jobApplication->job_id,
                'recipient' => $recipientEmail,
            ]);

            Mail::send([], [], function ($message) use (
                $recipientEmail,
                $candidateName,
                $subject,
                $body,
                $coverLetter,
                $resumePath
            ) {
                $message->to($recipientEmail, $candidateName)
                    ->subject($subject)
                    ->html(nl2br($body));

                // Attach cover letter as text or PDF if path exists
                if (file_exists($resumePath)) {
                    $message->attach($resumePath, [
                        'as' => 'resume.pdf',
                        'mime' => 'application/pdf',
                    ]);
                }
            });

            $duration = microtime(true) - $startTime;

            Log::info('[EmailService] Application email sent successfully', [
                'job_id' => $jobApplication->job_id,
                'duration' => round($duration, 2),
            ]);

        } catch (\Exception $e) {
            Log::error('[EmailService] Failed to send application email', [
                'job_id' => $jobApplication->job_id,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}
