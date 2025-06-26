<?php

namespace App\Services;

class PasswordService
{
    /**
     * Generate random password
     */
    public static function generate(?int $length = null): string
    {
        $length = $length ?? config('business.user.password_length');
        $chars = config('business.user.password_chars');

        return substr(str_shuffle($chars), 0, $length);
    }

    /**
     * Hash password
     */
    public static function hash(string $password): string
    {
        return bcrypt($password);
    }

    /**
     * Generate and hash password
     *
     * @return array [plain, hashed]
     */
    public static function generateAndHash(?int $length = null): array
    {
        $plain = self::generate($length);
        $hashed = self::hash($plain);

        return [
            'plain' => $plain,
            'hashed' => $hashed,
        ];
    }

    /**
     * Validate password strength
     */
    public static function isStrong(string $password): bool
    {
        // Minimal 6 karakter, ada huruf dan angka
        return strlen($password) >= 6
            && preg_match('/[a-zA-Z]/', $password)
            && preg_match('/[0-9]/', $password);
    }

    /**
     * Get password validation rules
     */
    public static function getValidationRules(): array
    {
        return [
            'required',
            'string',
            'min:6',
            'regex:/^(?=.*[a-zA-Z])(?=.*\d).+$/', // Must contain letter and number
        ];
    }

    /**
     * Get password validation messages
     */
    public static function getValidationMessages(): array
    {
        return [
            'password.required' => 'Password wajib diisi.',
            'password.string' => 'Password harus berupa teks.',
            'password.min' => 'Password minimal 6 karakter.',
            'password.regex' => 'Password harus mengandung huruf dan angka.',
        ];
    }
}
