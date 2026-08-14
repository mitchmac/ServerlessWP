<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Adapters;

// Implements the Blob wire protocol; Vercel has no PHP SDK.
class VercelBlobAdapter implements StorageAdapterInterface
{
    private const DEFAULT_API_BASE = 'https://blob.vercel-storage.com';
    private string $apiBase;
    private string $downloadBase;
    public function __construct(
        private readonly string $token,
        private readonly string $storeId,
        private readonly string $access = 'public',
        ?string $apiBase = null,
        ?string $downloadBase = null,
    ) {
        $this->apiBase      = rtrim($apiBase ?: self::DEFAULT_API_BASE, '/');
        $this->downloadBase = rtrim(
            $downloadBase ?: "https://{$storeId}.{$access}.blob.vercel-storage.com",
            '/',
        );
    }

    private function blobUrl(string $key): string
    {
        return $this->downloadBase . '/' . $this->encodedPath($key);
    }

    private function encodedPath(string $key): string
    {
        return implode('/', array_map('rawurlencode', explode('/', ltrim($key, '/'))));
    }

    public function get(string $key): string|false
    {
        $result = $this->fetch($key);
        return $result['status'] === self::FETCH_FOUND ? (string) $result['contents'] : false;
    }

    public function fetch(string $key): array
    {
        $response = $this->download($key);

        if ($response['status'] !== 200) {
            return [
                'status'   => $response['status'] === 404 ? self::FETCH_NOT_FOUND : self::FETCH_ERROR,
                'contents' => null,
                'etag'     => null,
            ];
        }

        return [
            'status'   => self::FETCH_FOUND,
            'contents' => $response['body'],
            'etag'     => $this->strongEtag($response['headers']['etag'] ?? null),
        ];
    }

    private function download(string $key): array
    {
        return $this->request('GET', $this->blobUrl($key) . '?cache=0', [
            'Authorization' => "Bearer {$this->token}",
        ]);
    }

    private function strongEtag(?string $etag): ?string
    {
        if ($etag === null || $etag === '') {
            return null;
        }
        return preg_replace('#^W/#', '', $etag);
    }

    public function put(string $key, string $contents, ?Precondition $condition = null): bool
    {
        $url      = $this->apiBase . '/?' . http_build_query(
            ['pathname' => ltrim($key, '/')],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
        $headers = [
            'Authorization'          => "Bearer {$this->token}",
            'x-vercel-blob-store-id' => $this->storeId,
            'x-content-type'         => $this->detectMimeType($key),
        ];

        // Omitting this header means create-only.
        if (!$condition?->requireAbsent) {
            $headers['x-allow-overwrite'] = '1';
        }

        if ($condition?->ifMatch !== null) {
            $headers['x-if-match'] = $condition->ifMatch;
        }

        $response = $this->request('PUT', $url, $headers, $contents);

        if ($condition?->ifMatch !== null && $response['status'] === 412) {
            throw new PreconditionFailedException(
                "conditional write on '{$key}' lost: blob changed since it was read",
            );
        }

        if ($condition?->requireAbsent && $response['status'] === 400) {
            throw new PreconditionFailedException(
                "conditional create of '{$key}' lost: another writer created it first",
            );
        }

        return $response['status'] === 200;
    }

    public function delete(string $key): bool
    {
        $response = $this->request('POST', $this->apiBase . '/delete', [
            'Authorization' => "Bearer {$this->token}",
            'Content-Type'  => 'application/json',
        ], json_encode(['urls' => [$this->blobUrl($key)]]));
        return $response['status'] === 200;
    }

    public function exists(string $key): bool
    {
        return $this->stat($key) !== false;
    }

    public function stat(string $key): array|false
    {
        $url      = $this->apiBase . '/?' . http_build_query(
            ['url' => $this->blobUrl($key)],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
        $response = $this->request('GET', $url, [
            'Authorization' => "Bearer {$this->token}",
        ]);

        if ($response['status'] !== 200) {
            return false;
        }

        $meta = json_decode($response['body'], true);
        if (!is_array($meta)) {
            return false;
        }

        $mtime = time();
        if (!empty($meta['uploadedAt'])) {
            $parsed = strtotime($meta['uploadedAt']);
            if ($parsed !== false) {
                $mtime = $parsed;
            }
        }

        return [
            'size'  => (int) ($meta['size'] ?? 0),
            'mtime' => $mtime,
            'type'  => 'file',
        ];
    }

    public function listPrefix(string $prefix): array
    {
        $url      = $this->apiBase . '/?' . http_build_query(
            [
                'prefix'  => ltrim($prefix, '/'),
                'storeId' => $this->storeId,
            ],
            '',
            '&',
            PHP_QUERY_RFC3986,
        );
        $response = $this->request('GET', $url, [
            'Authorization' => "Bearer {$this->token}",
        ]);

        if ($response['status'] !== 200 || $response['body'] === '') {
            return [];
        }

        $data = json_decode($response['body'], true);
        $keys = [];
        foreach ($data['blobs'] ?? [] as $blob) {
            $keys[] = $blob['pathname'];
        }
        return $keys;
    }

    public function rename(string $from, string $to): bool
    {
        $contents = $this->get($from);
        if ($contents === false) {
            return false;
        }
        if (!$this->put($to, $contents)) {
            return false;
        }
        $this->delete($from);
        return true;
    }

    /** @return array{status:int, headers:array<string,string>, body:string} */
    protected function request(string $method, string $url, array $headers = [], ?string $body = null): array
    {
        $ch = curl_init($url);

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $curlHeaders[] = "{$name}: {$value}";
        }

        $opts = [
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
        ];

        if ($method === 'HEAD') {
            $opts[CURLOPT_NOBODY] = true;
        }

        if ($body !== null) {
            $opts[CURLOPT_POSTFIELDS] = $body;
        }

        curl_setopt_array($ch, $opts);

        $raw    = (string) curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $hSize  = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $headerBlock  = substr($raw, 0, $hSize);
        $responseBody = substr($raw, $hSize);

        $parsedHeaders = [];
        foreach (explode("\r\n", $headerBlock) as $line) {
            if (str_contains($line, ':')) {
                [$name, $value]                         = explode(':', $line, 2);
                $parsedHeaders[strtolower(trim($name))] = trim($value);
            }
        }

        return ['status' => $status, 'headers' => $parsedHeaders, 'body' => $responseBody];
    }

    private function detectMimeType(string $key): string
    {
        return match (strtolower(pathinfo($key, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'webp'        => 'image/webp',
            'svg'         => 'image/svg+xml',
            'css'         => 'text/css',
            'js'          => 'application/javascript',
            'html', 'htm' => 'text/html',
            'txt'         => 'text/plain',
            'pdf'         => 'application/pdf',
            default       => 'application/octet-stream',
        };
    }
}
