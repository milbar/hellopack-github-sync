<?php

namespace MilBar\HelloPackGitHubSync;

class Helpers
{
    /**
     * Slugify a given string by converting it to lowercase, replacing non-alphanumeric characters with dashes,
     * and removing any invalid characters.
     *
     * @param string $text The text to slugify.
     * @return string The slugified string.
     */
    public static function slugify(string $text): string
    {
        // Replace non-letter or digits with dashes
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        // Transliterate to ASCII (e.g. 'é' becomes 'e')
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        // Remove remaining non-alphanumeric characters
        $text = preg_replace('~[^-\w]+~', '', $text);
        // Trim dashes from the beginning and end
        $text = trim($text, '-');
        // Replace multiple dashes with a single dash
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text) ?: 'n-a';  // Default to 'n-a' if the result is empty
    }
}
