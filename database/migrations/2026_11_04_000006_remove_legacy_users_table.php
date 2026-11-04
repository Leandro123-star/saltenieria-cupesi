<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        if (DB::table('users')->count() > 0) {
            throw new RuntimeException(
                'No se puede retirar users porque contiene registros.'
            );
        }

        $dependencies = DB::select(<<<'SQL'
            SELECT conname
            FROM pg_constraint
            WHERE contype = 'f'
              AND confrelid = 'public.users'::regclass
        SQL);

        if ($dependencies !== []) {
            throw new RuntimeException(
                'No se puede retirar users porque tiene dependencias de claves foráneas.'
            );
        }

        Schema::drop('users');
    }

    public function down(): void
    {
        throw new RuntimeException(
            'La retirada de users no es reversible automáticamente.'
        );
    }
};
