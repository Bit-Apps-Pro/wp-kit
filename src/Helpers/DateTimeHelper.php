<?php

namespace BitApps\WPKit\Helpers;

use DateTime;
use DateTimeZone;

final class DateTimeHelper
{
    private $_dateFormat;

    private $_timeFormat;

    private $_timezone;

    private $_currentTime;

    private string $_currentFormat;

    public function __construct()
    {
        $this->_dateFormat    = get_option('date_format');
        $this->_timeFormat    = get_option('time_format');
        $this->_timezone      = self::wp_timezone();
        $this->_currentTime   = current_time('mysql');
        $this->_currentFormat = 'Y-m-d H:i:s';
    }

    public function getDate($date = null, $currentFormat = null, $currentTZ = null, $expectedFormat = null, $expectedTZ = null): string|false
    {
        if (\is_null($date)) {
            $date          = $this->_currentTime;
            $currentFormat = $this->_currentFormat;
            $currentTZ     = $this->_timezone;
        }

        $currentFormat  ??= $this->_currentFormat;
        $currentTZ      ??= $this->_timezone;
        $expectedFormat ??= $this->_dateFormat;
        $expectedTZ     ??= $this->_timezone;

        return $this->getFormated($date, $currentFormat, $currentTZ, $expectedFormat, $expectedTZ);
    }

    public function getTime($date = null, $currentFormat = null, $currentTZ = null, $expectedFormat = null, $expectedTZ = null): string|false
    {
        if (\is_null($date)) {
            $date          = $this->_currentTime;
            $currentFormat = $this->_currentFormat;
            $currentTZ     = $this->_timezone;
        }

        $currentFormat  ??= $this->_currentFormat;
        $currentTZ      ??= $this->_timezone;
        $expectedFormat ??= $this->_timeFormat;
        $expectedTZ     ??= $this->_timezone;

        return $this->getFormated($date, $currentFormat, $currentTZ, $expectedFormat, $expectedTZ);
    }

    public function getDay($nameType, $date = null, $currentFormat = null, $currentTZ = null, $expectedTZ = null): string|false
    {
        if (\is_null($date)) {
            $date          = $this->_currentTime;
            $currentFormat = $this->_currentFormat;
            $currentTZ     = $this->_timezone;
        }

        $currentFormat ??= $this->_currentFormat;
        $currentTZ     ??= $this->_timezone;
        $expectedTZ    ??= $this->_timezone;

        $expectedFormat = match ($nameType) {
            'numeric-with-leading'    => 'd',
            'numeric-without-leading' => 'j',
            'short-name'              => 'D',
            'full-name'               => 'l',
            default                   => 'd',
        };

        return $this->getFormated($date, $currentFormat, $currentTZ, $expectedFormat, $expectedTZ);
    }

    public function getMonth($nameType, $date = null, $currentFormat = null, $currentTZ = null, $expectedTZ = null): string|false
    {
        if (\is_null($date)) {
            $date          = $this->_currentTime;
            $currentFormat = $this->_currentFormat;
            $currentTZ     = $this->_timezone;
        }

        $currentFormat ??= $this->_currentFormat;
        $currentTZ     ??= $this->_timezone;
        $expectedTZ    ??= $this->_timezone;

        $expectedFormat = match ($nameType) {
            'numeric-with-leading'    => 'm',
            'numeric-without-leading' => 'n',
            'short-name'              => 'M',
            'full-name'               => 'F',
            default                   => 'd',
        };

        return $this->getFormated($date, $currentFormat, $currentTZ, $expectedFormat, $expectedTZ);
    }

    public function getFormated($dateString, $currentFormat, $currentTZ, $expectedFormat, $expectedTZ): string|false
    {
        if ($currentFormat === false) {
            $dateObject = new DateTime($dateString, $currentTZ);
        } else {
            $dateObject = DateTime::createFromFormat($currentFormat, $dateString, $currentTZ);
        }

        if (!\is_null($expectedTZ)) {
            $dateObject->setTimezone($expectedTZ);
        }

        if ($dateObject) {
            return $dateObject->format($expectedFormat);
        }

        return false;
    }

    public function getUnicodeLikeFormat($type, $format = null)
    {
        $type = strtolower($type);

        switch ($type) {
            case 'date':
                $format ??= $this->_dateFormat;

                break;

            case 'time':
                $format ??= $this->_timeFormat;

                break;

            case 'timestamp':
                $format ??= $this->_currentFormat;

                break;

            default:
                break;
        }

        if (str_contains($format, 'd')) {
            $format = str_replace('d', 'dd', $format);
        }

        if (str_contains($format, 'j')) {
            $format = str_replace('j', 'd', $format);
        }

        if (str_contains($format, 'D')) {
            $format = str_replace('D', 'eee', $format);
        }

        if (str_contains($format, 'I')) {
            $format = str_replace('I', 'eeee', $format);
        }

        if (str_contains($format, 'S')) {
            $format = str_replace('S', 'F', $format);
        }

        if (str_contains($format, 'M')) {
            $format = str_replace('M', 'MMM', $format);
        }

        if (str_contains($format, 'F')) {
            $format = str_replace('F', 'MMMM', $format);
        }

        if (str_contains($format, 'm')) {
            $format = str_replace('m', 'MM', $format);
        }

        if (str_contains($format, 'n')) {
            $format = str_replace('n', 'M', $format);
        }

        if (str_contains($format, 'y')) {
            $format = str_replace('y', 'yy', $format);
        }

        if (str_contains($format, 'Y')) {
            $format = str_replace('Y', 'yyyy', $format);
        }

        if (str_contains($format, 'a')) {
            $format = str_replace('a', 'aaaa', $format);
        }

        if (str_contains($format, 'A')) {
            $format = str_replace('A', 'aaaa', $format);
        }

        if (str_contains($format, 'g')) {
            $format = str_replace('g', 'h', $format);
        }

        if (str_contains($format, 'G')) {
            $format = str_replace('G', 'H', $format);
        }

        if (str_contains($format, 'h')) {
            $format = str_replace('h', 'hh', $format);
        }

        if (str_contains($format, 'H')) {
            $format = str_replace('H', 'HH', $format);
        }

        if (str_contains($format, 'i')) {
            $format = str_replace('i', 'mm', $format);
        }

        if (str_contains($format, 's')) {
            $format = str_replace('s', 'ss', $format);
        }

        return $format;
    }

    public function getUnicodeToPhpFormat($type, $format = null)
    {
        $type = strtolower($type);

        switch ($type) {
            case 'date':
                $format ??= $this->_dateFormat;

                break;

            case 'time':
                $format ??= $this->_timeFormat;

                break;

            case 'timestamp':
                $format ??= $this->_currentFormat;

                break;

            default:
                break;
        }

        if (str_contains($format, 'd')) {
            $format = str_replace('dd', 'd', $format);
        }

        if (str_contains($format, 'E')) {
            $format = str_replace('E', 'D', $format);
        }

        if (str_contains($format, 'MMMM')) {
            $format = str_replace('MMMM', 'F', $format);
        } elseif (str_contains($format, 'MMM')) {
            $format = str_replace('MMM', 'M', $format);
        } elseif (str_contains($format, 'MM')) {
            $format = str_replace('MM', 'm', $format);
        }

        if (str_contains($format, 'yyyy')) {
            $format = str_replace('yyyy', 'Y', $format);
        } elseif (str_contains($format, 'yy')) {
            $format = str_replace('yy', 'y', $format);
        }

        return $format;
    }

    public static function wp_timezone_string()
    {
        if (\function_exists('wp_timezone_string')) {
            return wp_timezone_string();
        }

        $timezoneString = get_option('timezone_string');

        if ($timezoneString) {
            return $timezoneString;
        }

        $offset  = (float) get_option('gmt_offset');
        $hours   = (int) $offset;
        $minutes = ($offset - $hours);

        $sign    = ($offset < 0) ? '-' : '+';
        $absHour = abs($hours);
        $absMins = abs($minutes * 60);

        return \sprintf('%s%02d:%02d', $sign, $absHour, $absMins);
    }

    public static function wp_timezone()
    {
        if (\function_exists('wp_timezone')) {
            return wp_timezone();
        }

        return new DateTimeZone(self::wp_timezone_string());
    }

    public function getCurrentDateTime(): string
    {
        $dateTime = new DateTime('now', self::wp_timezone());

        return $dateTime->format($this->_currentFormat);
    }
}
