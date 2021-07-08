<?php

namespace spec\Codin\HttpClient;

use PhpSpec\ObjectBehavior;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;

class RequestBuilderSpec extends ObjectBehavior
{
    public function it_should_build_requests(
        ServerRequestFactoryInterface $serverRequestFactory,
        StreamFactoryInterface $streamFactory,
        ServerRequestInterface $request
    ) {
        $this->beConstructedWith($serverRequestFactory, $streamFactory);

        $serverRequestFactory->createServerRequest('GET', '/')->shouldBeCalled()->willReturn($request);

        $this->build('get', '/', [])->shouldReturn($request);
    }
}
