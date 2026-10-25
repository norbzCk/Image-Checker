<?php
declare(strict_types=1);

final class InferenceUnavailable extends RuntimeException
{
}

final class InferenceRejected extends RuntimeException
{
}

final class InferenceClient
{
    public function __construct(
        private string $baseUrl,
        private string $token,
        private int $timeoutSeconds = 60,
    ) {
    }

    public static function fromEnv(): self
    {
        return new self(
            rtrim(getenv('INFERENCE_URL') ?: 'http://127.0.0.1:8000', '/'),
            getenv('INFERENCE_TOKEN') ?: '',
            (int) (getenv('INFERENCE_TIMEOUT') ?: 60),
        );
    }

    /** @return array<string,mixed> decoded JSON assessment */
    public function analyze(string $path, string $mime): array
    {
        $curl = curl_init($this->baseUrl . '/analyze');
        curl_setopt_array($curl, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => ['file' => new CURLFile($path, $mime, 'upload')],
            CURLOPT_HTTPHEADER     => ['X-Internal-Token: ' . $this->token],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => $this->timeoutSeconds,
        ]);

        $body   = curl_exec($curl);
        $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        $error  = curl_error($curl);
        curl_close($curl);

        if ($body === false) {
            throw new InferenceUnavailable('Inference service unreachable: ' . $error);
        }

        $data = json_decode((string) $body, true);

        if ($status === 422 || $status === 413) {
            $detail = is_array($data) && is_string($data['detail'] ?? null) ? $data['detail'] : 'Image rejected.';
            throw new InferenceRejected($detail);
        }
        if ($status !== 200 || !is_array($data)) {
            throw new InferenceUnavailable('Inference service returned HTTP ' . $status);
        }

        return $data;
    }
}
