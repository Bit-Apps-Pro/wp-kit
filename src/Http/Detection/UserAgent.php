<?php

namespace BitApps\WPKit\Http\Detection;

/**
 * Classifies the visitor's browser and operating system from the User-Agent header.
 */
final class UserAgent
{
    /**
     * Check device info.
     */
    public static function checkDevice()
    {
        return isset(
            $_SERVER['HTTP_USER_AGENT']
        ) ? self::getBrowserName(wp_kses($_SERVER['HTTP_USER_AGENT'], [])) . '|' . self::getOS(wp_kses($_SERVER['HTTP_USER_AGENT'], [])) : '';
    }

    /**
     * Get browser name.
     *
     * @param string $userAgent $_SERVER['HTTP_USER_AGENT']
     *
     * @see https://stackoverflow.com/questions/18070154/get-operating-system-info
     */
    private static function getBrowserName($userAgent)
    {
        // Make case insensitive.
        $t = strtolower($userAgent);

        // If the string *starts* with the string, strpos returns 0 (i.e., FALSE). Do a ghetto hack and start with a space.
        // "[strpos()] may return Boolean FALSE, but may also return a non-Boolean value which evaluates to FALSE."
        //     http://php.net/manual/en/function.strpos.php
        $t = ' ' . $t;

        // Humans / Regular Users
        if (strpos($t, 'opera') || strpos($t, 'opr/')) {
            return 'Opera';
        }

        if (strpos($t, 'edge')) {
            return 'Edge';
        }

        if (strpos($t, 'Edg')) {
            return 'Edge';
        }

        if (strpos($t, 'chrome')) {
            return 'Chrome';
        }

        if (strpos($t, 'safari')) {
            return 'Safari';
        }

        if (strpos($t, 'firefox')) {
            return 'Firefox';
        }

        if (strpos($t, 'msie') || strpos($t, 'trident/7')) {
            return 'Internet Explorer';
        }

        if (strpos($t, 'google')) {
            return 'Googlebot';
        }

        if (strpos($t, 'bing')) {
            return 'Bingbot';
        }

        if (strpos($t, 'slurp')) {
            return 'Yahoo! Slurp';
        }

        if (strpos($t, 'duckduckgo')) {
            return 'DuckDuckBot';
        }

        if (strpos($t, 'baidu')) {
            return 'Baidu';
        }

        if (strpos($t, 'yandex')) {
            return 'Yandex';
        }

        if (strpos($t, 'sogou')) {
            return 'Sogou';
        }

        if (strpos($t, 'exabot')) {
            return 'Exabot';
        }

        if (strpos($t, 'msn')) {
            return 'MSN';
        }

        // Common Tools and Bots
        if (strpos($t, 'mj12bot')) {
            return 'Majestic';
        }

        if (strpos($t, 'ahrefs')) {
            return 'Ahrefs';
        }

        if (strpos($t, 'semrush')) {
            return 'SEMRush';
        }

        if (strpos($t, 'rogerbot') || strpos($t, 'dotbot')) {
            return 'Moz';
        }

        if (strpos($t, 'frog') || strpos($t, 'screaming')) {
            return 'Screaming Frog';
        }

        if (strpos($t, 'facebook')) {
            return 'Facebook';
        }

        if (strpos($t, 'pinterest')) {
            return 'Pinterest';
        }

        if (
            strpos($t, 'crawler')
            || strpos($t, 'api')
            || strpos($t, 'spider')
            || strpos($t, 'http')
            || strpos($t, 'bot')
            || strpos($t, 'archive')
            || strpos($t, 'info')
            || strpos($t, 'data')
        ) {
            return 'Bot';
        }

        return 'Other (Unknown)';
    }

    /**
     * Provide Operating System Information of User.
     *
     * @param mixed $userAgent
     *
     * @see https://stackoverflow.com/questions/18070154/get-operating-system-info
     */
    private static function getOS($userAgent)
    {
        $ros[] = ['Windows XP', 'Windows XP'];
        $ros[] = ['Windows NT 5.1|Windows NT5.1', 'Windows XP'];
        $ros[] = ['Windows 2000', 'Windows 2000'];
        $ros[] = ['Windows NT 5.0', 'Windows 2000'];
        $ros[] = ['Windows NT 4.0|WinNT4.0', 'Windows NT'];
        $ros[] = ['Windows NT 5.2', 'Windows Server 2003'];
        $ros[] = ['Windows NT 6.0', 'Windows Vista'];
        $ros[] = ['Windows NT 7.0', 'Windows 7'];
        $ros[] = ['Windows CE', 'Windows CE'];
        $ros[] = [
            '(media center pc).([0-9]{1,2}\.[0-9]{1,2})',
            'Windows Media Center',
        ];
        $ros[] = ['(win)([0-9]{1,2}\.[0-9x]{1,2})', 'Windows'];
        $ros[] = ['(win)([0-9]{2})', 'Windows'];
        $ros[] = ['(windows)([0-9x]{2})', 'Windows'];
        $ros[] = ['Windows ME', 'Windows ME'];
        $ros[] = ['Win 9x 4.90', 'Windows ME'];
        $ros[] = ['Windows 98|Win98', 'Windows 98'];
        $ros[] = ['Windows 95', 'Windows 95'];
        $ros[] = ['(windows)([0-9]{1,2}\.[0-9]{1,2})', 'Windows'];
        $ros[] = ['win32', 'Windows'];
        $ros[] = ['(java)([0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,2})', 'Java'];
        $ros[] = ['(Solaris)([0-9]{1,2}\.[0-9x]{1,2}){0,1}', 'Solaris'];
        $ros[] = ['dos x86', 'DOS'];
        $ros[] = ['unix', 'Unix'];
        // Android
        $ros[] = ['SM', 'Samsung'];
        $ros[] = ['HTC', 'HTC'];
        $ros[] = ['LG', 'LG'];
        $ros[] = ['Microsoft', 'Microsoft'];
        $ros[] = ['Pixel', 'Pixel'];
        $ros[] = ['MI', 'Xiaomi'];
        $ros[] = ['Xiaomi', 'Xiaomi'];
        $ros[] = ['Android', 'Android'];
        $ros[] = ['android', 'Android'];

        // iPhone
        $ros[] = ['iPhone', 'iPhone'];

        $ros[] = ['Mac OS X', 'Mac OS X'];
        $ros[] = ['Mac OS X Puma', 'Mac OS X 10.1[^0-9]'];
        $ros[] = ['Mac_PowerPC', 'Macintosh PowerPC'];
        $ros[] = ['(mac|Macintosh)', 'Mac OS'];
        $ros[] = ['(sunos)([0-9]{1,2}\.[0-9]{1,2}){0,1}', 'SunOS'];
        $ros[] = ['(beos)([0-9]{1,2}\.[0-9]{1,2}){0,1}', 'BeOS'];
        $ros[] = ['(risc os)([0-9]{1,2}\.[0-9]{1,2})', 'RISC OS'];
        $ros[] = ['os\/2', 'OS/2'];
        $ros[] = ['freebsd', 'FreeBSD'];
        $ros[] = ['openbsd', 'OpenBSD'];
        $ros[] = ['netbsd', 'NetBSD'];
        $ros[] = ['irix', 'IRIX'];
        $ros[] = ['plan9', 'Plan9'];
        $ros[] = ['osf', 'OSF'];
        $ros[] = ['aix', 'AIX'];
        $ros[] = ['GNU Hurd', 'GNU Hurd'];
        $ros[] = ['(fedora)', 'Linux - Fedora'];
        $ros[] = ['(kubuntu)', 'Linux - Kubuntu'];
        $ros[] = ['(ubuntu)', 'Linux - Ubuntu'];
        $ros[] = ['(debian)', 'Linux - Debian'];
        $ros[] = ['(CentOS)', 'Linux - CentOS'];
        $ros[] = [
            '(Mandriva).([0-9]{1,3}(\.[0-9]{1,3})?(\.[0-9]{1,3})?)',
            'Linux - Mandriva',
        ];
        $ros[] = [
            '(SUSE).([0-9]{1,3}(\.[0-9]{1,3})?(\.[0-9]{1,3})?)',
            'Linux - SUSE',
        ];
        $ros[] = ['(Dropline)', 'Linux - Slackware (Dropline GNOME)'];
        $ros[] = ['(ASPLinux)', 'Linux - ASPLinux'];
        $ros[] = ['(Red Hat)', 'Linux - Red Hat'];
        $ros[] = ['(linux)', 'Linux'];
        $ros[] = ['(amigaos)([0-9]{1,2}\.[0-9]{1,2})', 'AmigaOS'];
        $ros[] = ['amiga-aweb', 'AmigaOS'];
        $ros[] = ['amiga', 'Amiga'];
        $ros[] = ['AvantGo', 'PalmOS'];
        $ros[] = ['[0-9]{1,2}\.[0-9]{1,2}\.[0-9]{1,3})', 'Linux'];
        $ros[] = ['(webtv)/([0-9]{1,2}\.[0-9]{1,2})', 'WebTV'];
        $ros[] = ['Dreamcast', 'Dreamcast OS'];
        $ros[] = ['GetRight', 'Windows'];
        $ros[] = ['go!zilla', 'Windows'];
        $ros[] = ['gozilla', 'Windows'];
        $ros[] = ['gulliver', 'Windows'];
        $ros[] = ['ia archiver', 'Windows'];
        $ros[] = ['NetPositive', 'Windows'];
        $ros[] = ['mass downloader', 'Windows'];
        $ros[] = ['microsoft', 'Windows'];
        $ros[] = ['offline explorer', 'Windows'];
        $ros[] = ['teleport', 'Windows'];
        $ros[] = ['web downloader', 'Windows'];
        $ros[] = ['webcapture', 'Windows'];
        $ros[] = ['webcollage', 'Windows'];
        $ros[] = ['webcopier', 'Windows'];
        $ros[] = ['webstripper', 'Windows'];
        $ros[] = ['webzip', 'Windows'];
        $ros[] = ['wget', 'Windows'];
        $ros[] = ['Java', 'Unknown'];
        $ros[] = ['flashget', 'Windows'];
        $ros[] = ['MS FrontPage', 'Windows'];
        $ros[] = ['(msproxy)/([0-9]{1,2}.[0-9]{1,2})', 'Windows'];
        $ros[] = ['(msie)([0-9]{1,2}.[0-9]{1,2})', 'Windows'];
        $ros[] = ['libwww-perl', 'Unix'];
        $ros[] = ['UP.Browser', 'Windows CE'];
        $ros[] = ['NetAnts', 'Windows'];
        $ros[] = ['Android', 'Android'];
        $file  = \count($ros);
        $os    = '';
        for ($n = 0; $n < $file; ++$n) {
            if (@preg_match('/' . $ros[$n][0] . '/i', $userAgent)) {
                $os = @$ros[$n][1];

                break;
            }
        }

        return trim($os);
    }
}
