<?php

namespace BitApps\WPKit\Tests\Helpers;

use BitApps\WPKit\Helpers\DateTimeHelper;
use BitApps\WPKit\Tests\TestCase;
use DateTimeZone;
use WpKitTestState;

/**
 * @internal
 *
 * @coversNothing
 */
final class DateTimeTest extends TestCase
{
    public function testDateTimeHelperDateAndTimeFormattingUseExplicitFormats(): void
    {
        $helper = new DateTimeHelper();
        $utc    = new DateTimeZone('UTC');

        assertSameValue(
            '03/02/2024',
            $helper->getDate('2024-02-03 14:05:06', 'Y-m-d H:i:s', $utc, 'd/m/Y', $utc),
            'date formatting changed',
        );
        assertSameValue(
            '02:05 PM',
            $helper->getTime('2024-02-03 14:05:06', 'Y-m-d H:i:s', $utc, 'h:i A', $utc),
            'time formatting changed',
        );
    }

    public function testDateTimeHelperDayAndMonthNamesRemainSelectable(): void
    {
        $helper = new DateTimeHelper();
        $utc    = new DateTimeZone('UTC');

        assertSameValue(
            'Saturday',
            $helper->getDay('full-name', '2024-02-03', 'Y-m-d', $utc, $utc),
            'full day name changed',
        );
        assertSameValue(
            'Feb',
            $helper->getMonth('short-name', '2024-02-03', 'Y-m-d', $utc, $utc),
            'short month name changed',
        );
    }

    public function testDateTimeHelperTimezoneConversionIsAppliedBeforeFormatting(): void
    {
        $helper = new DateTimeHelper();

        assertSameValue(
            '2024-02-03 20:05',
            $helper->getFormated(
                '2024-02-03 14:05:00',
                'Y-m-d H:i:s',
                new DateTimeZone('UTC'),
                'Y-m-d H:i',
                new DateTimeZone('Asia/Dhaka'),
            ),
            'timezone conversion changed',
        );
    }

    public function testDateTimeHelperUnicodeDateFormatsConvertToPHPFormats(): void
    {
        $helper = new DateTimeHelper();

        assertSameValue('d/m/Y', $helper->getUnicodeToPhpFormat('custom', 'dd/MM/yyyy'), 'Unicode conversion changed');
    }

    public function testDateTimeHelperWordPressTimezoneRemainsExposedAsDateTimeZone(): void
    {
        WpKitTestState::$options['timezone_string'] = 'Asia/Dhaka';

        assertSameValue('Asia/Dhaka', DateTimeHelper::wp_timezone_string(), 'WordPress timezone string changed');
        assertSameValue('Asia/Dhaka', DateTimeHelper::wp_timezone()->getName(), 'WordPress timezone object changed');
    }
}
