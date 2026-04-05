<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!function_exists('appAvailableLanguages')) {
    function appAvailableLanguages(): array {
        return [
            'en' => 'English',
            'fr' => 'Francais',
        ];
    }
}

if (!function_exists('appHandleLanguageSelection')) {
    function appHandleLanguageSelection(): void {
        $lang = strtolower(trim((string)($_GET['lang'] ?? '')));
        if ($lang === '') {
            return;
        }

        $allowed = appAvailableLanguages();
        if (isset($allowed[$lang])) {
            $_SESSION['app_lang'] = $lang;
        }
    }
}

if (!function_exists('appLang')) {
    function appLang(): string {
        $allowed = appAvailableLanguages();
        $lang = strtolower(trim((string)($_SESSION['app_lang'] ?? 'en')));
        return isset($allowed[$lang]) ? $lang : 'en';
    }
}

if (!function_exists('appT')) {
    function appT(string $en, string $fr = ''): string {
        if (appLang() === 'fr' && $fr !== '') {
            return $fr;
        }
        return $en;
    }
}

appHandleLanguageSelection();
