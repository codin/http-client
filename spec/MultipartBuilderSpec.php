<?php

namespace spec\Codin\HttpClient;

use PhpSpec\ObjectBehavior;
use Prophecy\Argument\Token\TypeToken;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\StreamInterface;

class MultipartBuilderSpec extends ObjectBehavior
{
    public function it_should_build_multipart(StreamFactoryInterface $factory, StreamInterface $stream, RequestInterface $request)
    {
        $factory->createStream('')->shouldBeCalled()->willReturn($stream);

        $this->beConstructedWith($factory, 'phpspec-test');

        $stream->write('--phpspec-test'."\r\n")->shouldBeCalled();
        $stream->write('content-disposition: form-data; name="foo"'."\r\n")->shouldBeCalled();
        $stream->write('content-length: 3'."\r\n")->shouldBeCalled();
        $stream->write('x-user: 1'."\r\n")->shouldBeCalled();
        $stream->write("\r\n")->shouldBeCalled();
        $stream->write('bar'."\r\n")->shouldBeCalled();
        $stream->write('--phpspec-test--'."\r\n")->shouldBeCalled();

        $this->add('foo', 'bar', ['x-user' => 1]);

        $request->withBody($stream)->shouldBeCalled()->willReturn($request);
        $request->withHeader('Content-Type', new TypeToken('string'))->shouldBeCalled()->willReturn($request);

        $this->attach($request)->shouldReturn($request);
    }
}
