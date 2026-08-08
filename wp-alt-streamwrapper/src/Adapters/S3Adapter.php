<?php

declare(strict_types=1);

namespace WpAltStreamWrapper\Adapters;

use Aws\Exception\AwsException;
use Aws\S3\S3Client;

class S3Adapter implements StorageAdapterInterface
{
    private S3Client $client;
    private string $bucket;
    private string $prefix;
    private ?string $acl;

    public function __construct(
        string $bucket,
        string $region = 'us-east-1',
        string $prefix = '',
        ?string $endpoint = null,
        ?string $key = null,
        ?string $secret = null,
        bool $forcePathStyle = false,
        ?string $acl = null,
        ?S3Client $client = null,
    ) {
        $this->bucket = $bucket;
        $this->prefix = rtrim($prefix, '/');
        $this->acl    = $acl ?: null;

        if ($client !== null) {
            $this->client = $client;
            return;
        }

        $config = [
            'version' => 'latest',
            'region'  => $region,
        ];

        if ($endpoint) {
            $config['endpoint'] = $endpoint;
        }
        if ($endpoint || $forcePathStyle) {
            $config['use_path_style_endpoint'] = true; // required for MinIO and some S3-compatible stores
        }

        if ($key && $secret) {
            $config['credentials'] = ['key' => $key, 'secret' => $secret];
        }

        $this->client = new S3Client($config);
    }

    private function s3Key(string $key): string
    {
        $key = ltrim($key, '/');
        return $this->prefix ? $this->prefix . '/' . $key : $key;
    }

    /** Strip the configured prefix to recover the storage key. */
    private function stripPrefix(string $s3Key): string
    {
        if ($this->prefix && str_starts_with($s3Key, $this->prefix . '/')) {
            return substr($s3Key, strlen($this->prefix) + 1);
        }
        return $s3Key;
    }

    public function get(string $key): string|false
    {
        try {
            $result = $this->client->getObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->s3Key($key),
            ]);
            return (string) $result['Body'];
        } catch (AwsException) {
            return false;
        }
    }

    public function put(string $key, string $contents): bool
    {
        try {
            $params = [
                'Bucket'      => $this->bucket,
                'Key'         => $this->s3Key($key),
                'Body'        => $contents,
                'ContentType' => $this->detectMimeType($key),
            ];
            if ($this->acl !== null) {
                $params['ACL'] = $this->acl;
            }
            $this->client->putObject($params);
            return true;
        } catch (AwsException) {
            return false;
        }
    }

    public function delete(string $key): bool
    {
        try {
            $this->client->deleteObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->s3Key($key),
            ]);
            return true;
        } catch (AwsException) {
            return false;
        }
    }

    public function exists(string $key): bool
    {
        return $this->stat($key) !== false;
    }

    public function stat(string $key): array|false
    {
        try {
            $result = $this->client->headObject([
                'Bucket' => $this->bucket,
                'Key'    => $this->s3Key($key),
            ]);
            return [
                'size'  => (int) $result['ContentLength'],
                'mtime' => $result['LastModified']->getTimestamp(),
                'type'  => 'file',
            ];
        } catch (AwsException) {
            return false;
        }
    }

    public function listPrefix(string $prefix): array
    {
        $s3Prefix = $this->s3Key($prefix);
        // Ensure a trailing slash so we only list objects under this prefix.
        if ($s3Prefix !== '' && !str_ends_with($s3Prefix, '/')) {
            $s3Prefix .= '/';
        }

        $keys   = [];
        $params = ['Bucket' => $this->bucket, 'Prefix' => $s3Prefix];

        do {
            $result = $this->client->listObjectsV2($params);
            foreach ($result['Contents'] ?? [] as $obj) {
                $keys[] = $this->stripPrefix($obj['Key']);
            }
            $params['ContinuationToken'] = $result['NextContinuationToken'] ?? null;
        } while ($result['IsTruncated'] ?? false);

        return $keys;
    }

    public function rename(string $from, string $to): bool
    {
        try {
            $this->client->copyObject([
                'Bucket'     => $this->bucket,
                'CopySource' => $this->bucket . '/' . $this->s3Key($from),
                'Key'        => $this->s3Key($to),
            ]);
            $this->delete($from);
            return true;
        } catch (AwsException) {
            return false;
        }
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
