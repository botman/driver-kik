<?php

namespace BotMan\Drivers\Kik;

use BotMan\BotMan\Users\User;
use Illuminate\Support\Collection;
use BotMan\BotMan\Drivers\HttpDriver;
use BotMan\BotMan\Interfaces\UserInterface;
use BotMan\BotMan\Messages\Incoming\Answer;
use BotMan\BotMan\Messages\Attachments\File;
use BotMan\BotMan\Messages\Attachments\Audio;
use BotMan\BotMan\Messages\Attachments\Image;
use BotMan\BotMan\Messages\Attachments\Video;
use BotMan\BotMan\Messages\Outgoing\Question;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use BotMan\Drivers\Kik\Exceptions\KikException;
use BotMan\BotMan\Messages\Attachments\Location;
use Symfony\Component\HttpFoundation\ParameterBag;
use BotMan\BotMan\Messages\Incoming\IncomingMessage;
use BotMan\BotMan\Messages\Outgoing\OutgoingMessage;
use BotMan\Drivers\Kik\Exceptions\UnsupportedAttachmentType;

class KikDriver extends HttpDriver
{
    protected $headers = [];

    const DRIVER_NAME = 'Kik';

    /**
     * @param Request $request
     * @return void
     */
    public function buildPayload(Request $request)
    {
        $this->payload = new ParameterBag(json_decode($request->getContent(), true));
        $this->headers = $request->headers->all();
        $this->event = Collection::make($this->payload->get('messages'));
        $this->config = Collection::make($this->config->get('kik', []));
    }

    /**
     * Determine if the request is for this driver.
     *
     * @return bool
     */
    public function matchesRequest()
    {
        $matches = $this->event->filter(function ($message) {
            return array_key_exists('body', $message) ||
            array_key_exists('picUrl', $message) ||
            array_key_exists('stickerUrl', $message) ||
            array_key_exists('videoUrl', $message);
        })->isNotEmpty();

        return $matches && ! empty($this->headers['x-kik-username']);
    }

    /**
     * Retrieve the chat message(s).
     *
     * @return array
     */
    public function getMessages()
    {
        return $this->event->map(function ($message) {
            if (isset($message['picUrl'])) {
                $image = new Image($message['picUrl'], $message);
                $image->title($message['attribution']['name']);

                $incomingMessage = new IncomingMessage(Image::PATTERN, $message['from'], $message['chatId'], $message);
                $incomingMessage->setImages([$image]);
            } elseif (isset($message['stickerUrl'])) {
                $sticker = new Image($message['stickerUrl'], $message);
                $sticker->title($message['attribution']['name']);

                $incomingMessage = new IncomingMessage(Image::PATTERN, $message['from'], $message['chatId'], $message);
                $incomingMessage->setImages([$sticker]);
            } elseif (isset($message['videoUrl'])) {
                $incomingMessage = new IncomingMessage(Video::PATTERN, $message['from'], $message['chatId'], $message);
                $incomingMessage->setVideos([new Video($message['videoUrl'], $message)]);
            } else {
                $incomingMessage = new IncomingMessage($message['body'], $message['from'], $message['chatId'], $message);
            }

            return $incomingMessage;
        })->toArray();
    }

    /**
     * @return bool
     */
    public function isBot()
    {
        return false;
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        return ! empty($this->config->get('username')) && ! empty($this->config->get('key'));
    }

    /**
     * Retrieve User information.
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return UserInterface
     * @throws KikException
     */
    public function getUser(IncomingMessage $matchingMessage)
    {
        $response = $this->http->get('https://api.kik.com/v1/user/'.$matchingMessage->getSender(), [], [
            'Content-Type:application/json',
            'Authorization:Basic '.$this->getRequestCredentials(),
        ]);
        $profileData = json_decode($response->getContent(), true);

        if ($response->getStatusCode() != 200) {
            throw new KikException('Error getting user info: '.$response->getContent());
        }

        return new User($matchingMessage->getSender(), $profileData['firstName'], $profileData['lastName'], $matchingMessage->getSender(), $profileData);
    }

    /**
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $message
     * @return Answer
     */
    public function getConversationAnswer(IncomingMessage $message)
    {
        return Answer::create($message->getText())->setMessage($message);
    }

    /**
     * Convert a Question object into a valid Kik
     * keyboard object.
     *
     * @param \BotMan\BotMan\Messages\Outgoing\Question $question
     * @return array
     */
    private function convertQuestion(Question $question)
    {
        $buttons = $question->getButtons();
        if ($buttons) {
            return [
                [
                    'type' => 'suggested',
                    'responses' => Collection::make($buttons)->transform(function ($button) {
                        $buttonData = [
                            'type' => 'text',
                            'metadata' => [
                                'value' => $button['value'],
                            ],
                        ];
                        if ($button['image_url']) {
                            $buttonData['type'] = 'picture';
                            $buttonData['picUrl'] = $button['image_url'];
                        } else {
                            $buttonData['body'] = $button['text'];
                        }

                        return $buttonData;
                    })->toArray(),
                ],
            ];
        } else {
            return [[]];
        }
    }

    /**
     * @param OutgoingMessage|\BotMan\BotMan\Messages\Outgoing\Question $message
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @param array $additionalParameters
     * @return array
     * @throws UnsupportedAttachmentType
     */
    public function buildServicePayload($message, $matchingMessage, $additionalParameters = [])
    {
        $payload = [
            'to' => $matchingMessage->getSender(),
            'chatId' => $matchingMessage->getRecipient(),
        ];

        if ($message instanceof OutgoingMessage) {
            $attachment = $message->getAttachment();
            if ($attachment instanceof Image) {
                if (strtolower(pathinfo($attachment->getUrl(), PATHINFO_EXTENSION)) === 'gif') {
                    $payload['videoUrl'] = $attachment->getUrl();
                    $payload['type'] = 'video';
                } else {
                    $payload['picUrl'] = $attachment->getUrl();
                    $payload['type'] = 'picture';
                }
            } elseif ($attachment instanceof Video) {
                $payload['videoUrl'] = $attachment->getUrl();
                $payload['type'] = 'video';
            } elseif ($attachment instanceof Audio || $attachment instanceof Location || $attachment instanceof File) {
                throw new UnsupportedAttachmentType('The '.get_class($attachment).' is not supported (currently: Image, Video)');
            } else {
                $payload['body'] = $message->getText();
                $payload['type'] = 'text';
            }
        } elseif ($message instanceof Question) {
            $payload['body'] = $message->getText();
            $payload['keyboards'] = $this->convertQuestion($message);
            $payload['type'] = 'text';
        }

        return [
            'messages' => [$payload],
        ];
    }

    protected function getRequestCredentials()
    {
        return base64_encode($this->config->get('username').':'.$this->config->get('key'));
    }

    /**
     * @param mixed $payload
     * @return Response
     */
    public function sendPayload($payload)
    {
        return $this->http->post('https://api.kik.com/v1/message', [], $payload, [
            'Content-Type:application/json',
            'Authorization:Basic '.$this->getRequestCredentials(),
        ], true);
    }

    /**
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return Response
     */
    public function types(IncomingMessage $matchingMessage)
    {
        return $this->sendPayload([
            'messages' => [
                [
                    'to' => $matchingMessage->getSender(),
                    'type' => 'is-typing',
                    'chatId' => $matchingMessage->getRecipient(),
                    'isTyping' => true,
                ],
            ],
        ]);
    }

    /**
     * Low-level method to perform driver specific API requests.
     *
     * @param string $endpoint
     * @param array $parameters
     * @param \BotMan\BotMan\Messages\Incoming\IncomingMessage $matchingMessage
     * @return Response
     */
    public function sendRequest($endpoint, array $parameters, IncomingMessage $matchingMessage)
    {
        $payload = array_merge_recursive([
            'to' => $matchingMessage->getSender(),
            'chatId' => $matchingMessage->getRecipient(),
        ], $parameters);

        return $this->sendPayload(['messages' => [$payload]]);
    }
}
