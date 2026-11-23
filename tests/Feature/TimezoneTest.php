<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TimezoneTest extends TestCase
{
    public function test_laravel_y_postgresql_comparten_la_zona_horaria_de_bolivia(): void
    {
        $databaseTimezone = DB::selectOne("SELECT current_setting('TIMEZONE') AS timezone")->timezone;

        $this->assertSame('America/La_Paz', config('app.timezone'));
        $this->assertSame('America/La_Paz', $databaseTimezone);
        $this->assertSame('America/La_Paz', now()->getTimezone()->getName());
    }
}
