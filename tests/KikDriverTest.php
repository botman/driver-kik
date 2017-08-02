<?php

namespace Tests;

use BotMan\Drivers\Kik\KikDriver;
use PHPUnit_Framework_TestCase;
use Mockery as m;
use BotMan\BotMan\Http\Curl;
use Symfony\Component\HttpFoundation\Request;

class KikDriverTest extends PHPUnit_Framework_TestCase {

	public function tearDown() {
		m::close();
	}

	private function getDriver($responseData, $htmlInterface = null) {
		$request = m::mock(Request::class . '[getContent]');
		$request->shouldReceive('getContent')->andReturn(json_encode($responseData));
		if ($htmlInterface === null) {
			$htmlInterface = m::mock(Curl::class);
		}

		$request->headers->add(['x-kik-username' => 'Sergio']);
		return new KikDriver($request, [], $htmlInterface);
	}

	/** @test */
	public function it_returns_the_driver_name() {
		$driver = $this->getDriver([]);
		$this->assertSame('Kik', $driver->getName());
	}

	/** @test */
	public function it_matches_the_request() {
		$driver = $this->getDriver([
			'messages' => [
				['text' => 'bar'],
				['text' => 'foo'],
			]
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
				]
			]
		]);
		$this->assertTrue($driver->matchesRequest());
	}

	/** @test */
	public function it_returns_the_message_object() {
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
				]
			]
		]);
		$this->assertTrue(is_array($driver->getMessages()));
	}

}