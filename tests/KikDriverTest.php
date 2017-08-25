<?php

namespace Tests;

use Mockery as m;
use BotMan\BotMan\Http\Curl;
use PHPUnit_Framework_TestCase;
use BotMan\Drivers\Kik\KikDriver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class KikDriverTest extends PHPUnit_Framework_TestCase
{
    public function tearDown()
    {
        m::close();
    }

    private function getDriver($responseData, $htmlInterface = null)
    {
        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($responseData));
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        $request->headers->add(['x-kik-username' => 'Sergio']);

        return new KikDriver($request, [], $htmlInterface);
    }

    /** @test */
    public function it_returns_the_driver_name()
    {
        $driver = $this->getDriver([]);
        $this->assertSame('Kik', $driver->getName());
    }

    /** @test */
    public function it_matches_the_request()
    {
        $driver = $this->getDriver([
            'messages' => [
                ['text' => 'bar'],
                ['text' => 'foo'],
            ],
        ]);
        $this->assertFalse($driver->matchesRequest());

        $driver = $this->getDriver([
            'messages' => [
                [
                    'chatId' => '0ee6d46753bfa6ac2f089149959363f3f59ae62b10cba89cc426490ce38ea92d',
                    'id' => '0115efde-e54b-43d5-873a-5fef7adc69fd',
                    'type' => 'text',
                    'from' => 'laura',
                    'participants' => ['laura'],
                    'body' => 'omg r u real?',
                    'timestamp' => 1439576628405,
                    'readReceiptRequested' => true,
                    'mention' => null,
                    'metadata' => null,
                    'chatType' => 'direct',
                ],
            ],
        ]);
        $this->assertTrue($driver->matchesRequest());
    }

    /** @test */
    public function it_returns_the_message_object()
    {
        $driver = $this->getDriver([
            'messages' => [
                [
                    'chatId' => '0ee6d46753bfa6ac2f089149959363f3f59ae62b10cba89cc426490ce38ea92d',
                    'id' => '0115efde-e54b-43d5-873a-5fef7adc69fd',
                    'type' => 'text',
                    'from' => 'laura',
                    'participants' => ['laura'],
                    'body' => 'omg r u real?',
                    'timestamp' => 1439576628405,
                    'readReceiptRequested' => true,
                    'mention' => null,
                    'metadata' => null,
                    'chatType' => 'direct',
                ],
            ],
        ]);
        $this->assertTrue(is_array($driver->getMessages()));
    }

    /** @test */
    public function it_returns_the_message_text()
    {
        $driver = $this->getDriver([
            'messages' => [
                [
                    'chatId' => '0ee6d46753bfa6ac2f089149959363f3f59ae62b10cba89cc426490ce38ea92d',
                    'id' => '0115efde-e54b-43d5-873a-5fef7adc69fd',
                    'type' => 'text',
                    'from' => 'laura',
                    'participants' => ['laura'],
                    'body' => 'Hi Marcel',
                    'timestamp' => 1439576628405,
                    'readReceiptRequested' => true,
                    'mention' => null,
                    'metadata' => null,
                    'chatType' => 'direct',
                ],
            ],
        ]);
        $this->assertSame('Hi Marcel', $driver->getMessages()[0]->getText());
    }

    /** @test */
    public function it_detects_bots()
    {
        $driver = $this->getDriver([
            'messages' => [
                [
                    'chatId' => '0ee6d46753bfa6ac2f089149959363f3f59ae62b10cba89cc426490ce38ea92d',
                    'id' => '0115efde-e54b-43d5-873a-5fef7adc69fd',
                    'type' => 'text',
                    'from' => 'laura',
                    'participants' => ['laura'],
                    'body' => 'Hi Marcel',
                    'timestamp' => 1439576628405,
                    'readReceiptRequested' => true,
                    'mention' => null,
                    'metadata' => null,
                    'chatType' => 'direct',
                ],
            ],
        ]);
        $this->assertFalse($driver->isBot());
    }

    /** @test */
    public function it_returns_the_user_id()
    {
        $driver = $this->getDriver([
            'messages' => [
                [
                    'chatId' => '0ee6d46753bfa6ac2f089149959363f3f59ae62b10cba89cc426490ce38ea92d',
                    'id' => '0115efde-e54b-43d5-873a-5fef7adc69fd',
                    'type' => 'text',
                    'from' => 'laura',
                    'participants' => ['laura'],
                    'body' => 'Hi Marcel',
                    'timestamp' => 1439576628405,
                    'readReceiptRequested' => true,
                    'mention' => null,
                    'metadata' => null,
                    'chatType' => 'direct',
                ],
            ],
        ]);
        $this->assertSame('laura', $driver->getMessages()[0]->getSender());
    }

    /** @test */
    public function it_returns_the_channel_id()
    {
        $driver = $this->getDriver([
            'messages' => [
                [
                    'chatId' => '0ee6d46753bfa6ac2f089149959363f3f59ae62b10cba89cc426490ce38ea92d',
                    'id' => '0115efde-e54b-43d5-873a-5fef7adc69fd',
                    'type' => 'text',
                    'from' => 'laura',
                    'participants' => ['laura'],
                    'body' => 'Hi Marcel',
                    'timestamp' => 1439576628405,
                    'readReceiptRequested' => true,
                    'mention' => null,
                    'metadata' => null,
                    'chatType' => 'direct',
                ],
            ],
        ]);
        $this->assertSame('0ee6d46753bfa6ac2f089149959363f3f59ae62b10cba89cc426490ce38ea92d', $driver->getMessages()[0]->getRecipient());
    }

    /** @test */
    public function it_returns_the_user()
    {
        $response = new Response('{
    "firstName": "Joe",
    "lastName": "User",
    "profilePicUrl": "http://profilepics.kik.com/foobar.jpg",
    "profilePicLastModified": 1439576628405,
    "timezone": "America/Toronto"
}');

        $htmlInterface = m::mock(Curl::class);
        $htmlInterface->shouldReceive('get')->with('https://api.kik.com/v1/user/laura', [], [
            'Content-Type:application/json',
            'Authorization:Basic Og==',
        ])->andReturn($response);

        $data = [
            'messages' => [
                [
                    'chatId' => '0ee6d46753bfa6ac2f089149959363f3f59ae62b10cba89cc426490ce38ea92d',
                    'id' => '0115efde-e54b-43d5-873a-5fef7adc69fd',
                    'type' => 'text',
                    'from' => 'laura',
                    'participants' => ['laura'],
                    'body' => 'Hi Marcel',
                    'timestamp' => 1439576628405,
                    'readReceiptRequested' => true,
                    'mention' => null,
                    'metadata' => null,
                    'chatType' => 'direct',
                ],
            ],
        ];

        $request = m::mock(Request::class.'[getContent]');
        $request->shouldReceive('getContent')->andReturn(json_encode($data));
        if ($htmlInterface === null) {
            $htmlInterface = m::mock(Curl::class);
        }

        $request->headers->add(['x-kik-username' => 'Sergio']);

        $driver = new KikDriver($request, [], $htmlInterface);

        $user = $driver->getUser($driver->getMessages()[0]);

        $this->assertSame('laura', $user->getId());
        $this->assertSame('Joe', $user->getFirstName());
        $this->assertSame('User', $user->getLastName());
        $this->assertSame('http://profilepics.kik.com/foobar.jpg', $user->getProfilePicUrl());
        $this->assertSame('America/Toronto', $user->getTimezone());
        $this->assertSame(1439576628405, $user->getProfilePicLastModified());
    }
}
