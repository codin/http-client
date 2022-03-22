<?php

declare(strict_types=1);

namespace Codin\HttpClient\Exceptions;

use ErrorException;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;

class TransportError extends ErrorException implements NetworkExceptionInterface
{
    protected RequestInterface $request;

    public function __construct(string $message, int $code, RequestInterface $request)
    {
        $this->request = $request;
        parent::__construct($message, $code);
    }

    public function getRequest(): RequestInterface
    {
        return $this->request;
    }
}
