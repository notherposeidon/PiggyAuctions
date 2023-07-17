<?php

declare(strict_types=1);

namespace DaPigGuy\PiggyAuctions\utils;

use pocketmine\utils\TextFormat;

class Utils
{
    public static function formatDuration(int $duration): string
    {
        if ($duration > 86400) return (int)$duration / 86400 . " days";
        if ($duration > 3600) return (int)$duration / 3600 . " hours";
        if ($duration > 60) return (int)$duration / 60 . " minutes";
        return $duration . " seconds";
    }

    public static function formatDetailedDuration(int $duration): string
    {
        $days = (int) ($duration / 8640);
        $hours = (int) ($duration / 3600) % 24;
        $minutes = (int) ($duration / 60) % 60;
        $seconds = (int) $duration % 60;

        if ($days >= 1) {
            $dateString = $days . "d";
        } elseif ($hours > 6) {
            $dateString = $hours . "h";
        } elseif ($minutes >= 1) {
            $dateString = ($hours > 0 ? $hours . "h" : "") . $minutes . "m" . ($seconds == 0 ? "" : $seconds . "s");
        } else {
            $dateString = $seconds . "s";
        }

        return $dateString;
    }

    public static function translateColorTags(string $message): string
    {
        $replacements = [
            "{BLACK}" => TextFormat::BLACK,
            "{DARK_BLUE}" => TextFormat::DARK_BLUE,
            "{DARK_GREEN}" => TextFormat::DARK_GREEN,
            "{DARK_AQUA}" => TextFormat::DARK_AQUA,
            "{DARK_RED}" => TextFormat::DARK_RED,
            "{DARK_PURPLE}" => TextFormat::DARK_PURPLE,
            "{GOLD}" => TextFormat::GOLD,
            "{GRAY}" => TextFormat::GRAY,
            "{DARK_GRAY}" => TextFormat::DARK_GRAY,
            "{BLUE}" => TextFormat::BLUE,
            "{GREEN}" => TextFormat::GREEN,
            "{AQUA}" => TextFormat::AQUA,
            "{RED}" => TextFormat::RED,
            "{LIGHT_PURPLE}" => TextFormat::LIGHT_PURPLE,
            "{YELLOW}" => TextFormat::YELLOW,
            "{WHITE}" => TextFormat::WHITE,
            "{OBFUSCATED}" => TextFormat::OBFUSCATED,
            "{BOLD}" => TextFormat::BOLD,
            "{STRIKETHROUGH}" => TextFormat::STRIKETHROUGH,
            "{UNDERLINE}" => TextFormat::UNDERLINE,
            "{ITALIC}" => TextFormat::ITALIC,
            "{RESET}" => TextFormat::RESET
        ];
        return str_replace(array_keys($replacements), $replacements, $message);
    }
}