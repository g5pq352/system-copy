<?php
/**
 * Admin API Bridge
 * Provides PSR-11 and PSR-7 mocks to allow legacy procedural scripts 
 * to call Slim-based AdminController methods.
 */

namespace App\Bridges;

use App\Controllers\AdminController;

/**
 * Mock Container for PSR-11
 */
class MockContainer implements \Psr\Container\ContainerInterface {
    private $services = [];
    public function set($id, $service) { $this->services[$id] = $service; }
    public function get(string $id) { return $this->services[$id] ?? null; }
    public function has(string $id): bool { return isset($this->services[$id]); }
}

/**
 * Mock Request for PSR-7
 */
class MockRequest implements \Psr\Http\Message\ServerRequestInterface {
    public $body;
    public function getParsedBody(): mixed { return $this->body; }
    public function getAttribute(string $name, mixed $default = null): mixed { return $default; }
    public function getMethod(): string { return 'POST'; }

    // 實作 RequestInterface 必要方法
    public function getRequestTarget(): string { return '/'; }
    public function withRequestTarget(string $requestTarget): \Psr\Http\Message\RequestInterface { return $this; }
    public function withMethod(string $method): \Psr\Http\Message\RequestInterface { return $this; }
    public function getUri(): \Psr\Http\Message\UriInterface {
        return new class implements \Psr\Http\Message\UriInterface {
            public function getScheme(): string { return 'http'; }
            public function getAuthority(): string { return ''; }
            public function getUserInfo(): string { return ''; }
            public function getHost(): string { return 'localhost'; }
            public function getPort(): ?int { return null; }
            public function getPath(): string { return '/'; }
            public function getQuery(): string { return ''; }
            public function getFragment(): string { return ''; }
            public function withScheme(string $scheme): \Psr\Http\Message\UriInterface { return $this; }
            public function withUserInfo(string $user, ?string $password = null): \Psr\Http\Message\UriInterface { return $this; }
            public function withHost(string $host): \Psr\Http\Message\UriInterface { return $this; }
            public function withPort(?int $port): \Psr\Http\Message\UriInterface { return $this; }
            public function withPath(string $path): \Psr\Http\Message\UriInterface { return $this; }
            public function withQuery(string $query): \Psr\Http\Message\UriInterface { return $this; }
            public function withFragment(string $fragment): \Psr\Http\Message\UriInterface { return $this; }
            public function __toString(): string { return 'http://localhost/'; }
        };
    }
    public function withUri(\Psr\Http\Message\UriInterface $uri, bool $preserveHost = false): \Psr\Http\Message\RequestInterface { return $this; }

    // 實作 MessageInterface 必要方法
    public function getProtocolVersion(): string { return '1.1'; }
    public function withProtocolVersion(string $version): \Psr\Http\Message\MessageInterface { return $this; }
    public function getHeaders(): array { return []; }
    public function hasHeader(string $name): bool { return false; }
    public function getHeader(string $name): array { return []; }
    public function getHeaderLine(string $name): string { return ''; }
    public function withHeader(string $name, $value): \Psr\Http\Message\MessageInterface { return $this; }
    public function withAddedHeader(string $name, $value): \Psr\Http\Message\MessageInterface { return $this; }
    public function withoutHeader(string $name): \Psr\Http\Message\MessageInterface { return $this; }
    public function getBody(): \Psr\Http\Message\StreamInterface {
        return new class implements \Psr\Http\Message\StreamInterface {
            public function __toString(): string { return ''; }
            public function close(): void {}
            public function detach() { return null; }
            public function getSize(): ?int { return 0; }
            public function tell(): int { return 0; }
            public function eof(): bool { return true; }
            public function isSeekable(): bool { return false; }
            public function seek(int $offset, int $whence = SEEK_SET): void {}
            public function rewind(): void {}
            public function isWritable(): bool { return false; }
            public function write(string $string): int { return 0; }
            public function isReadable(): bool { return false; }
            public function read(int $length): string { return ''; }
            public function getContents(): string { return ''; }
            public function getMetadata(?string $key = null) { return null; }
        };
    }
    public function withBody(\Psr\Http\Message\StreamInterface $body): \Psr\Http\Message\MessageInterface { return $this; }

    // 實作 ServerRequestInterface 必要方法
    public function getServerParams(): array { return []; }
    public function getCookieParams(): array { return []; }
    public function withCookieParams(array $cookies): \Psr\Http\Message\ServerRequestInterface { return $this; }
    public function getQueryParams(): array { return []; }
    public function withQueryParams(array $query): \Psr\Http\Message\ServerRequestInterface { return $this; }
    public function getUploadedFiles(): array { return []; }
    public function withUploadedFiles(array $uploadedFiles): \Psr\Http\Message\ServerRequestInterface { return $this; }
    public function withParsedBody($data): \Psr\Http\Message\ServerRequestInterface { $this->body = $data; return $this; }
    public function getAttributes(): array { return []; }
    public function withAttribute(string $name, $value): \Psr\Http\Message\ServerRequestInterface { return $this; }
    public function withoutAttribute(string $name): \Psr\Http\Message\ServerRequestInterface { return $this; }
}

/**
 * Mock Response for PSR-7
 */
class MockResponse implements \Psr\Http\Message\ResponseInterface {
    public $status = 200;
    public $body = '';

    // 實作 ResponseInterface 必要方法
    public function getStatusCode(): int { return $this->status; }
    public function withStatus(int $code, string $reasonPhrase = ''): \Psr\Http\Message\ResponseInterface {
        $this->status = $code;
        return $this;
    }
    public function getReasonPhrase(): string { return ''; }

    // 實作 MessageInterface 必要方法
    public function getProtocolVersion(): string { return '1.1'; }
    public function withProtocolVersion(string $version): \Psr\Http\Message\MessageInterface { return $this; }
    public function getHeaders(): array { return []; }
    public function hasHeader(string $name): bool { return false; }
    public function getHeader(string $name): array { return []; }
    public function getHeaderLine(string $name): string { return ''; }
    public function withHeader(string $name, $value): \Psr\Http\Message\MessageInterface { return $this; }
    public function withAddedHeader(string $name, $value): \Psr\Http\Message\MessageInterface { return $this; }
    public function withoutHeader(string $name): \Psr\Http\Message\MessageInterface { return $this; }
    public function getBody(): \Psr\Http\Message\StreamInterface {
        return new class($this->body) implements \Psr\Http\Message\StreamInterface {
            private $b;
            public function __construct(&$b){ $this->b = &$b; }
            public function write(string $string): int { $this->b .= $string; return strlen($string); }
            public function __toString(): string { return $this->b; }
            public function close(): void {}
            public function detach() { return null; }
            public function getSize(): ?int { return strlen($this->b); }
            public function tell(): int { return 0; }
            public function eof(): bool { return true; }
            public function isSeekable(): bool { return false; }
            public function seek(int $offset, int $whence = SEEK_SET): void {}
            public function rewind(): void {}
            public function isWritable(): bool { return true; }
            public function isReadable(): bool { return true; }
            public function read(int $length): string { return $this->b; }
            public function getContents(): string { return $this->b; }
            public function getMetadata(?string $key = null) { return null; }
        };
    }
    public function withBody(\Psr\Http\Message\StreamInterface $body): \Psr\Http\Message\MessageInterface { return $this; }
}

/**
 * Bridge Helper
 */
class AdminApiBridge {
    public static function callController($conn, $controllerClass, $method, $postData) {
        $container = new MockContainer();
        $container->set(\PDO::class, $conn);

        // 【修正】使用全域的 DB 類別實例，而不是 PDO
        if (isset($GLOBALS['db']) && $GLOBALS['db'] instanceof \DB) {
            $container->set(\DB::class, $GLOBALS['db']);
        } else {
            // 如果沒有全域 DB，使用 PDO（但可能會有問題）
            $container->set(\DB::class, $conn);
        }

        $container->set(\Slim\Views\PhpRenderer::class, new class { public function getAttribute($n){return null; } });

        $controller = new $controllerClass($container);

        $req = new MockRequest();
        $req->body = $postData;
        $res = new MockResponse();

        $controller->$method($req, $res, []);
        return (string)$res->getBody();
    }
}
