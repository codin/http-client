<?php

declare(strict_types=1);

namespace Codin\HttpClient\Exceptions;

use ErrorException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class ClientError extends ErrorException
{
    protected ResponseInterface $response;

    public function __construct(RequestInterface $request, ResponseInterface $response)
    {
        $this->response = $response;
        $code = $response->getStatusCode();
        $message = sprintf(
            'Http request "%s %s" returned %u response',
            $request->getMethod(),
            $request->getUri(),
            $code
        );

        if (strpos($this->response->getHeaderLine('Content-Type'), '/json') !== false) {
            $data = json_decode((string) $this->response->getBody(), true);
            if (isset($data['title']) || isset($data['detail'])) {
                $separator = isset($body['title'], $body['detail']) ? "\n\n" : '';
            }
            $message = ($data['title'] ?? '').$separator.($data['detail'] ?? '');
        }

        parent::__construct($message, $code);
    }

    public function getResponse(): ResponseInterface
    {
        return $this->response;
    }
}
