<?php

final class PasswordPolicy
{
    public const MESSAGE = 'Password must be at least 8 characters and include uppercase and lowercase letters, a number, and a special character.';

    public static function isValid(string $password): bool
    {
        return strlen($password) >= 8
            && preg_match('/[A-Z]/', $password) === 1
            && preg_match('/[a-z]/', $password) === 1
            && preg_match('/[0-9]/', $password) === 1
            && preg_match('/[\x21-\x2f\x3a-\x40\x5b-\x60\x7b-\x7e]/', $password) === 1;
    }
}
