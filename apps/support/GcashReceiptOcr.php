<?php

final class GcashReceiptOcr
{
    private string $executable;

    public function __construct(?string $executable = null)
    {
        $configured = trim((string) ($executable ?? ($_ENV['TESSERACT_PATH'] ?? getenv('TESSERACT_PATH') ?: '')));
        $candidates = array_filter([
            $configured,
            'C:\\Program Files\\Tesseract-OCR\\tesseract.exe',
            '/usr/bin/tesseract',
            '/usr/local/bin/tesseract',
            'tesseract',
        ]);

        $this->executable = 'tesseract';
        foreach ($candidates as $candidate) {
            if ($candidate === 'tesseract' || is_file($candidate)) {
                $this->executable = $candidate;
                break;
            }
        }
    }

    public function extract(string $imagePath): array
    {
        if (!is_file($imagePath) || !is_readable($imagePath)) {
            throw new RuntimeException('The uploaded receipt could not be read.');
        }

        $command = [
            $this->executable,
            $imagePath,
            'stdout',
            '-l',
            'eng',
            '--psm',
            '6',
        ];
        $pipes = [];
        $process = @proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            null,
            null,
            ['bypass_shell' => true]
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Receipt scanning is temporarily unavailable.');
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || trim($output) === '') {
            error_log('Tesseract receipt scan failed: ' . trim($error));
            throw new RuntimeException('The receipt text could not be read. You can enter the details manually.');
        }

        return self::parse($output);
    }

    public static function parse(string $text): array
    {
        $normalized = preg_replace('/[\t\r]+/', ' ', $text);
        $normalized = preg_replace('/[ ]{2,}/', ' ', (string) $normalized);

        $reference = null;
        if (preg_match('/\bRef(?:erence)?\s*(?:No\.?|Number)?\s*[:#-]?\s*((?:\d[\s-]*){10,20})/i', $normalized, $match)) {
            $candidate = preg_replace('/\D+/', '', $match[1]);
            if (strlen($candidate) >= 10 && strlen($candidate) <= 20) {
                $reference = $candidate;
            }
        }

        $amount = null;
        $amountPatterns = [
            '/Total\s+Amount\s+Sent\s*(?:PHP|P|₱)?\s*([0-9][0-9,]*\.\d{2})/iu',
            '/\bAmount\s*(?:PHP|P|₱)?\s*([0-9][0-9,]*\.\d{2})/iu',
        ];
        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $normalized, $match)) {
                $amount = number_format((float) str_replace(',', '', $match[1]), 2, '.', '');
                break;
            }
        }

        $transactionAt = null;
        $month = '(?:Jan(?:uary)?|Feb(?:ruary)?|Mar(?:ch)?|Apr(?:il)?|May|Jun(?:e)?|Jul(?:y)?|Aug(?:ust)?|Sep(?:t(?:ember)?)?|Oct(?:ober)?|Nov(?:ember)?|Dec(?:ember)?)';
        if (preg_match('/\b' . $month . '\s+\d{1,2},?\s+\d{4}\s+\d{1,2}:\d{2}\s*(?:AM|PM)\b/i', $normalized, $match)) {
            try {
                $date = new DateTimeImmutable(preg_replace('/\s+/', ' ', trim($match[0])));
                $transactionAt = $date->format('Y-m-d\TH:i');
            } catch (Throwable $exception) {
                $transactionAt = null;
            }
        }

        $fields = [
            'amount' => $amount,
            'reference_number' => $reference,
            'transaction_at' => $transactionAt,
        ];

        return [
            'recognized_receipt' => stripos($normalized, 'GCash') !== false
                || (stripos($normalized, 'Total Amount Sent') !== false && stripos($normalized, 'Ref No') !== false),
            'fields' => $fields,
            'missing' => array_keys(array_filter($fields, static fn($value) => $value === null || $value === '')),
        ];
    }
}
