<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class LogController extends Controller
{
    /**
     * Display logs viewer page
     */
    public function index()
    {
        return view('logs.index');
    }

    /**
     * Get latest log entries (for live updates)
     */
    public function fetch(Request $request)
    {
        $lines = $request->get('lines', 100);
        $filter = $request->get('filter', ''); // 'error', 'info', 'warning', or empty for all

        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            return response()->json([
                'logs' => [],
                'message' => 'Log file not found'
            ]);
        }

        // Read last N lines
        $logs = $this->tailLog($logPath, $lines);

        // Filter by level if specified
        if ($filter) {
            $logs = array_filter($logs, function ($log) use ($filter) {
                return stripos($log, strtoupper($filter)) !== false;
            });
        }

        // Parse and format logs
        $formatted = array_map(function ($log) {
            return $this->parseLogLine($log);
        }, $logs);

        return response()->json([
            'logs' => array_values($formatted),
            'timestamp' => now()->toIso8601String()
        ]);
    }

    /**
     * Get worker status
     */
    public function workerStatus()
    {
        // Check if worker process is running by looking at recent logs
        $logPath = storage_path('logs/laravel.log');

        if (!File::exists($logPath)) {
            return response()->json([
                'status' => 'unknown',
                'message' => 'No log file found'
            ]);
        }

        $recentLogs = $this->tailLog($logPath, 50);
        $lastWorkerLog = null;

        foreach (array_reverse($recentLogs) as $log) {
            if (stripos($log, '[JobProcessor]') !== false || stripos($log, 'RabbitMQ') !== false) {
                $lastWorkerLog = $log;
                break;
            }
        }

        if (!$lastWorkerLog) {
            return response()->json([
                'status' => 'inactive',
                'message' => 'Worker has not processed anything recently',
                'last_activity' => null
            ]);
        }

        // Extract timestamp from log
        preg_match('/\[(.*?)\]/', $lastWorkerLog, $matches);
        $timestamp = $matches[1] ?? null;

        if ($timestamp) {
            $lastActivity = \Carbon\Carbon::parse($timestamp);
            $minutesAgo = $lastActivity->diffInMinutes(now());

            $status = $minutesAgo < 5 ? 'active' : 'idle';

            return response()->json([
                'status' => $status,
                'message' => "Last activity: {$lastActivity->diffForHumans()}",
                'last_activity' => $lastActivity->toIso8601String(),
                'minutes_ago' => $minutesAgo
            ]);
        }

        return response()->json([
            'status' => 'idle',
            'message' => 'Worker status unknown',
            'last_activity' => null
        ]);
    }

    /**
     * Clear logs
     */
    public function clear()
    {
        $logPath = storage_path('logs/laravel.log');

        if (File::exists($logPath)) {
            File::put($logPath, '');
            return response()->json(['message' => 'Logs cleared successfully']);
        }

        return response()->json(['message' => 'Log file not found'], 404);
    }

    /**
     * Tail log file (read last N lines)
     */
    private function tailLog(string $path, int $lines = 100): array
    {
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $offset = max(0, $lastLine - $lines);

        $result = [];
        $file->seek($offset);

        while (!$file->eof()) {
            $line = $file->current();
            if (!empty(trim($line))) {
                $result[] = $line;
            }
            $file->next();
        }

        return $result;
    }

    /**
     * Parse log line and extract info
     */
    private function parseLogLine(string $line): array
    {
        // Try to match Laravel log format: [timestamp] environment.LEVEL: message
        preg_match('/\[(.*?)\]\s+(\w+)\.(\w+):\s+(.*)/', $line, $matches);

        if (count($matches) >= 5) {
            $timestamp = $matches[1] ?? '';
            $env = $matches[2] ?? '';
            $level = $matches[3] ?? '';
            $message = $matches[4] ?? '';

            return [
                'timestamp' => $timestamp,
                'level' => strtoupper($level),
                'message' => $message,
                'raw' => $line
            ];
        }

        // If doesn't match pattern, return as-is
        return [
            'timestamp' => '',
            'level' => 'INFO',
            'message' => $line,
            'raw' => $line
        ];
    }
}
