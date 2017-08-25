<?php
namespace BotMan\Drivers\Kik\Extensions;

use BotMan\BotMan\Interfaces\UserInterface;
use BotMan\BotMan\Users\User as BotManUser;

class User extends BotManUser implements UserInterface
{
    /**
     * A URL pointing to the profile image of the user.
     * Will be null if the user has not set a profile picture.
     *
     * @return string|null
     */
    public function getProfilePicUrl()
    {
        return $this->getInfo()['profilePicUrl'] ?? null;
    }

    /**
     * The user's IANA timezone name. Will be null if the user's timezone is unknown.
     *
     * @return string|null
     */
    public function getTimezone()
    {
        return $this->getInfo()['timezone'] ?? null;
    }

    /**
     * The epoch timestamp (in milliseconds) indicating when the profile picture
     * was last changed. Will be null if the user has not set a profile picture.
     *
     * @return string|null
     */
    public function getProfilePicLastModified()
    {
        return $this->getInfo()['profilePicLastModified'] ?? null;
    }
}