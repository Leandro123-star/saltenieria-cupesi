<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UsuariosSeeder extends Seeder
{
    public const PASSWORD = 'password';

    /**
     * [nombres, apellidos, ci, teléfono, usuario|null, rol|null, activo]
     * Una persona sin usuario representa personal sin acceso al sistema.
     */
    private const PERSONAS = [
        ['Carlos Alberto', 'Rojas Mendoza', '7845123', '76012345', 'admin', 'Administrador', true],
        ['María Fernanda', 'Gutiérrez Paz', '8234567', '71234567', 'supervisor', 'Supervisor', true],
        ['Juan Pablo', 'Flores Vaca', '9123456', '72345678', 'cajero1', 'Cajero', true],
        ['Ana Lucía', 'Choque Mamani', '6543210', '73456789', 'cajero2', 'Cajero', true],
        ['Luis Miguel', 'Saavedra Ortiz', '7012345', '74567890', 'cajero3', 'Cajero', true],
        ['Rosa Elena', 'Quispe Cruz', '8456789', '75678901', 'cajero4', 'Cajero', true],
        ['Diego Armando', 'Vargas Suárez', '9345678', '76789012', 'cajero5', 'Cajero', false],
        ['Sofía', 'Méndez Rivera', '6789012', '77890123', 'practicante', 'Practicante', true],
        ['Pedro', 'Antezana Lima', '7567890', '78901234', null, null, true],
        ['Carmen Rosa', 'Arteaga Soliz', null, null, null, null, false],
    ];

    public function run(): void
    {
        $roles = DB::table('roles')->pluck('id_rol', 'nombre');
        $hash = Hash::make(self::PASSWORD);
        $ahora = now();
        $idAdmin = null;

        foreach (self::PERSONAS as [$nombres, $apellidos, $ci, $telefono, $usuario, $rol, $activo]) {
            $idPersona = DB::table('personas')->insertGetId([
                'nombres' => $nombres,
                'apellidos' => $apellidos,
                'ci' => $ci,
                'telefono' => $telefono,
                'activo' => $activo,
                'creado_por' => $idAdmin,
                'creado_el' => $ahora,
                'modificado_el' => $ahora,
            ], 'id_persona');

            if ($usuario === null) {
                continue;
            }

            $idUsuario = DB::table('usuarios')->insertGetId([
                'nombre_usuario' => $usuario,
                'password_hash' => $hash,
                'activo' => $activo,
                'id_persona' => $idPersona,
                'id_rol' => $roles[$rol],
                'creado_por' => $idAdmin,
                'creado_el' => $ahora,
                'modificado_el' => $ahora,
            ], 'id_usuario');

            if ($idAdmin === null) {
                // El primer registro es el administrador: se autoaudita y
                // pasa a ser el creador del resto de registros.
                $idAdmin = $idUsuario;
                DB::table('personas')->where('id_persona', $idPersona)->update(['creado_por' => $idAdmin]);
            }
        }

        // Los catálogos de seguridad quedan auditados por el administrador.
        DB::table('roles')->update(['creado_por' => $idAdmin]);
        DB::table('permisos')->update(['creado_por' => $idAdmin]);
    }
}
