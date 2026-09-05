<?php

use BitApps\WPKit\Migration\Migration;

final class ContractMigration extends Migration
{
    public static $upCalls = 0;

    public static $downCalls = 0;

    public function up()
    {
        ++self::$upCalls;
    }

    public function down()
    {
        ++self::$downCalls;
    }
}
