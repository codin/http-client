<?php

namespace spec\Codin\HttpClient;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument\Token\TypeToken;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Message\StreamInterface;
use function json_encode;

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

    public function it_should_build_requests_with_options(
        ServerRequestFactoryInterface $serverRequestFactory,
        StreamFactoryInterface $streamFactory,
        ServerRequestInterface $request,
        UriInterface $uri,
        StreamInterface $stream
    ) {
        $this->beConstructedWith($serverRequestFactory, $streamFactory);

        $serverRequestFactory->createServerRequest('GET', '/')->shouldBeCalled()->willReturn($request);

        $request->withHeader('foo', 'bar')->shouldBeCalled()->willReturn($request);

        $request->getUri()->shouldBeCalled()->willReturn($uri);

        $uri->withQuery(http_build_query(['foo' => 'bar']))->shouldBeCalled()->willReturn($uri);

        $request->withUri($uri)->shouldBeCalled()->willReturn($request);

        $request->withBody($stream)->shouldBeCalled()->willReturn($request);

        $streamFactory->createStream('foo')->shouldBeCalled()->willReturn($stream);

        $streamFactory->createStream(json_encode(['foo' => 'bar']))->shouldBeCalled()->willReturn($stream);

        $streamFactory->createStream(http_build_query(['foo' => 'bar']))->shouldBeCalled()->willReturn($stream);

        $request->withHeader('Content-Type', 'application/x-www-form-urlencoded')->shouldBeCalled()->willReturn($request);

        $request->withBody($stream)->shouldBeCalled()->willReturn($request);

        $streamFactory->createStream('')->shouldBeCalled()->willReturn($stream);

        $request->withHeader('Content-Type', new TypeToken('string'))->shouldBeCalled()->willReturn($request);

        $this->build('get', '/', [
            'headers' => [
                'foo' => 'bar',
            ],
            'query' => [
                'foo' => 'bar',
            ],
            'stream' => $stream,
            'body' => 'foo',
            'json' => [
                'foo' => 'bar',
            ],
            'form' => [
                'foo' => 'bar',
            ],
            'multipart' => [
                'foo' => 'bar',
            ],
        ])->shouldReturn($request);
    }
}
