<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Persona;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AccesoSucursalesTest extends TestCase
{
    private Rol $rolAdmin;
    private Rol $rolCajero;

    protected function setUp(): void
    {
        parent::setUp();
        $this->rolAdmin = Rol::firstOrCreate(['nombre' => 'Administrador'], ['descripcion' => 'Administrador', 'activo' => true]);
        $this->rolCajero = Rol::firstOrCreate(['nombre' => 'Cajero'], ['descripcion' => 'Cajero', 'activo' => true]);
    }

    /** Administrador que gestiona usuarios (acceso global). */
    private function gestor(): Usuario
    {
        return $this->createUsuario(['gestionar_usuarios'], [], 'Administrador', accesoGlobal: true);
    }

    private function persona(string $nombre): Persona
    {
        return Persona::create(['nombres' => $nombre, 'apellidos' => 'Prueba', 'activo' => true]);
    }

    /** Envía el formulario web de alta tal como lo hace el navegador. */
    private function crearDesdeFormulario(Usuario $gestor, array $datos)
    {
        return $this->actingAs($gestor)->from(route('usuarios.create'))->post(route('usuarios.store'), $datos + [
            'password' => 'password123',
        ]);
    }

    private function sucursalesDelSelector(Usuario $usuario): array
    {
        return $this->actingAs($usuario)->get(route('branch.choose'))
            ->assertOk()
            ->viewData('branches')->pluck('nombre')->sort()->values()->all();
    }

    /* 1 y 2 ------------------------------------------------------------- */

    public function test_crea_administrador_con_acceso_global_y_cambia_entre_todas_las_sucursales_activas(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $norte = $this->createSucursal('Sucursal Norte');
        $cerrada = $this->createSucursal('Sucursal Cerrada');
        $cerrada->update(['activo' => false]);

        $this->crearDesdeFormulario($this->gestor(), [
            'id_persona' => $this->persona('Global')->getKey(),
            'id_rol' => $this->rolAdmin->getKey(),
            'nombre_usuario' => 'admin_global',
            'acceso_todas_sucursales' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));

        $admin = Usuario::where('nombre_usuario', 'admin_global')->firstOrFail();
        $this->assertTrue($admin->acceso_todas_sucursales);
        $this->assertTrue($admin->tieneAccesoGlobal());
        $this->assertDatabaseMissing('usuario_sucursal', ['id_usuario' => $admin->getKey()]);

        // Solo las activas aparecen en el selector de sucursal.
        $this->assertSame(['Sucursal Centro', 'Sucursal Norte'], $this->sucursalesDelSelector($admin));

        foreach ([$centro, $norte] as $sucursal) {
            $this->actingAs($admin)->post(route('branch.set'), ['id_sucursal' => $sucursal->getKey()])
                ->assertRedirect(route('dashboard'))
                ->assertSessionHas('sucursal_activa_id', $sucursal->getKey());
        }
        $this->actingAs($admin)->post(route('branch.set'), ['id_sucursal' => $cerrada->getKey()])->assertForbidden();

        // El layout muestra la sucursal activa aunque no tenga filas en usuario_sucursal.
        $this->actingAs($admin)->withSession(['sucursal_activa_id' => $norte->getKey()])
            ->get(route('usuarios.index'))->assertOk()->assertSee('Sucursal Norte')->assertSee('Todas las sucursales');
    }

    /* 3 y 4 ------------------------------------------------------------- */

    public function test_administrador_con_una_sucursal_no_entra_a_sucursales_no_autorizadas(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $norte = $this->createSucursal('Sucursal Norte');

        $this->crearDesdeFormulario($this->gestor(), [
            'id_persona' => $this->persona('Restringido')->getKey(),
            'id_rol' => $this->rolAdmin->getKey(),
            'nombre_usuario' => 'admin_centro',
            'acceso_todas_sucursales' => '0',
            'sucursales' => [['id_sucursal' => $centro->getKey()]],
        ])->assertSessionHasNoErrors();

        $admin = Usuario::where('nombre_usuario', 'admin_centro')->firstOrFail();
        $this->assertFalse($admin->acceso_todas_sucursales);
        $this->assertSame([$centro->getKey()], $admin->sucursales()->pluck('sucursales.id_sucursal')->all());

        $this->assertSame(['Sucursal Centro'], $this->sucursalesDelSelector($admin));
        $this->actingAs($admin)->post(route('branch.set'), ['id_sucursal' => $norte->getKey()])->assertForbidden();
        $this->actingAs($admin)->get(route('ventas.index', ['id_sucursal' => $norte->getKey()]))->assertForbidden();
        $this->actingAs($admin)->post(route('cajas.abrir'), ['id_sucursal' => $norte->getKey(), 'monto_inicial' => 0])->assertForbidden();
        $this->assertDatabaseCount('cajas', 0);

        // Las listas solo ofrecen su sucursal.
        $this->actingAs($admin)->get(route('cajas.index'))->assertOk()
            ->assertViewHas('sucursales', fn ($s) => $s->pluck('id_sucursal')->all() === [$centro->getKey()]);
        // Y el historial no incluye otras sucursales.
        $caja = Caja::create(['fecha_apertura' => now(), 'monto_inicial' => 0, 'estado' => 'ABIERTA',
            'id_sucursal' => $norte->getKey(), 'id_usuario_apertura' => $admin->getKey()]);
        $this->actingAs($admin)->getJson(route('cajas.index'))->assertOk()->assertJsonMissing(['id_caja' => $caja->getKey()]);
    }

    public function test_administrador_restringido_sin_sucursales_no_obtiene_acceso_global(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $admin = $this->createUsuario(['registrar_ventas'], [$centro], 'Administrador');
        DB::table('usuario_sucursal')->where('id_usuario', $admin->getKey())->delete();

        $this->assertFalse($admin->fresh()->tieneAccesoGlobal());
        $this->actingAs($admin->fresh())->get(route('ventas.index', ['id_sucursal' => $centro->getKey()]))->assertForbidden();

        // Desde el formulario tampoco se puede dejar sin sucursales.
        $this->actingAs($this->gestor())->from(route('usuarios.edit', $admin))->put(route('usuarios.update', $admin), [
            'nombre_usuario' => $admin->nombre_usuario, 'id_persona' => $admin->id_persona,
            'id_rol' => $this->rolAdmin->getKey(), 'acceso_todas_sucursales' => '0',
        ])->assertSessionHasErrors(['sucursales' => 'Selecciona al menos una sucursal o marca «Acceso a todas las sucursales».']);
    }

    /* 5 ----------------------------------------------------------------- */

    public function test_edita_administrador_y_cambia_su_modalidad_de_acceso(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $norte = $this->createSucursal('Sucursal Norte');
        $gestor = $this->gestor();
        $admin = $this->createUsuario([], [], 'Administrador', accesoGlobal: true);
        $this->assertTrue($admin->acceso_todas_sucursales);
        $base = ['nombre_usuario' => $admin->nombre_usuario, 'id_persona' => $admin->id_persona, 'id_rol' => $this->rolAdmin->getKey()];

        // De global a específico.
        $this->actingAs($gestor)->put(route('usuarios.update', $admin), $base + [
            'acceso_todas_sucursales' => '0',
            'sucursales' => [['id_sucursal' => $norte->getKey()]],
        ])->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));
        $admin->refresh();
        $this->assertFalse($admin->acceso_todas_sucursales);
        $this->assertSame([$norte->getKey()], $admin->sucursales()->pluck('sucursales.id_sucursal')->all());
        $this->actingAs($admin)->post(route('branch.set'), ['id_sucursal' => $centro->getKey()])->assertForbidden();

        // De vuelta a global: se eliminan las filas específicas (sin duplicados).
        $this->actingAs($gestor)->put(route('usuarios.update', $admin), $base + [
            'acceso_todas_sucursales' => '1',
            'sucursales' => [['id_sucursal' => $norte->getKey()]],
        ])->assertSessionHasNoErrors();
        $admin->refresh();
        $this->assertTrue($admin->acceso_todas_sucursales);
        $this->assertDatabaseMissing('usuario_sucursal', ['id_usuario' => $admin->getKey()]);
        $this->actingAs($admin)->post(route('branch.set'), ['id_sucursal' => $centro->getKey()])->assertRedirect(route('dashboard'));

        // El formulario de edición muestra la opción marcada y la lista deshabilitada por script.
        $this->actingAs($gestor)->get(route('usuarios.edit', $admin))->assertOk()
            ->assertSee('Acceso a todas las sucursales')
            ->assertSee('id="acceso-global" checked', false);

        // Si pasa a Cajero, pierde el acceso global y queda con una sucursal.
        $this->actingAs($gestor)->put(route('usuarios.update', $admin), [
            'nombre_usuario' => $admin->nombre_usuario, 'id_persona' => $admin->id_persona, 'id_rol' => $this->rolCajero->getKey(),
            'acceso_todas_sucursales' => '0', 'sucursales' => [['id_sucursal' => $centro->getKey()]],
        ])->assertSessionHasNoErrors();
        $this->assertFalse($admin->fresh()->acceso_todas_sucursales);
    }

    /* 6 ----------------------------------------------------------------- */

    public function test_cajero_solo_puede_tener_una_sucursal_y_no_acceso_global(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $norte = $this->createSucursal('Sucursal Norte');
        $gestor = $this->gestor();
        $datos = fn (string $usuario) => [
            'id_persona' => $this->persona(ucfirst(str_replace('_', '', $usuario)))->getKey(),
            'id_rol' => $this->rolCajero->getKey(),
            'nombre_usuario' => $usuario,
        ];

        $this->crearDesdeFormulario($gestor, $datos('cajero_dos') + [
            'sucursales' => [['id_sucursal' => $centro->getKey()], ['id_sucursal' => $norte->getKey()]],
        ])->assertSessionHasErrors(['sucursales' => 'Un Cajero debe tener exactamente una sucursal asignada.']);

        $this->crearDesdeFormulario($gestor, $datos('cajero_global') + [
            'acceso_todas_sucursales' => '1',
            'sucursales' => [['id_sucursal' => $centro->getKey()]],
        ])->assertSessionHasErrors(['acceso_todas_sucursales' => 'Solo el rol Administrador puede tener acceso a todas las sucursales.']);

        $this->crearDesdeFormulario($gestor, $datos('cajero_uno') + [
            'acceso_todas_sucursales' => '0',
            'sucursales' => [['id_sucursal' => $centro->getKey()]],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('usuarios', ['nombre_usuario' => 'cajero_dos']);
        $this->assertDatabaseMissing('usuarios', ['nombre_usuario' => 'cajero_global']);
        $cajero = Usuario::where('nombre_usuario', 'cajero_uno')->firstOrFail();
        $this->assertFalse($cajero->acceso_todas_sucursales);
        $this->assertSame([$centro->getKey()], $cajero->sucursales()->pluck('sucursales.id_sucursal')->all());

        // Aunque la columna se marcara a mano, un Cajero no tiene acceso global.
        $cajero->forceFill(['acceso_todas_sucursales' => true])->save();
        $this->assertFalse($cajero->fresh()->tieneAccesoGlobal());
    }

    /* 7 ----------------------------------------------------------------- */

    public function test_cajero_no_accede_a_otras_sucursales_manipulando_la_url(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $norte = $this->createSucursal('Sucursal Norte');
        $cajero = $this->createUsuario(['registrar_ventas', 'gestionar_caja'], [$centro], 'Cajero');

        $this->actingAs($cajero)->get(route('branch.choose'))->assertForbidden();
        $this->actingAs($cajero)->post(route('branch.set'), ['id_sucursal' => $norte->getKey()])->assertForbidden();
        $this->actingAs($cajero)->get(route('ventas.index', ['id_sucursal' => $norte->getKey()]))->assertForbidden();
        $this->actingAs($cajero)->post(route('cajas.abrir'), ['id_sucursal' => $norte->getKey(), 'monto_inicial' => 0])->assertForbidden();

        // Aunque la sesión apunte a otra sucursal, el middleware la recalcula.
        $this->actingAs($cajero)->withSession(['sucursal_activa_id' => $norte->getKey()])
            ->get(route('ventas.index'))->assertOk()->assertSessionHas('sucursal_activa_id', $centro->getKey());
        $this->assertDatabaseCount('cajas', 0);
    }

    /* 8 ----------------------------------------------------------------- */

    public function test_sucursal_nueva_queda_disponible_para_el_administrador_global(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $global = $this->createUsuario(['gestionar_sucursales', 'gestionar_caja'], [], 'Administrador', accesoGlobal: true);
        $restringido = $this->createUsuario(['gestionar_caja'], [$centro], 'Administrador');

        $this->actingAs($global)->post(route('sucursales.store'), [
            'nombre' => 'Sucursal Nueva', 'direccion' => 'Av. Principal #10', 'telefono' => '46722199', 'activo' => 1,
        ])->assertSessionHasNoErrors();
        $nueva = Sucursal::where('nombre', 'Sucursal Nueva')->firstOrFail();

        $this->assertContains('Sucursal Nueva', $this->sucursalesDelSelector($global));
        $this->actingAs($global)->post(route('branch.set'), ['id_sucursal' => $nueva->getKey()])->assertRedirect(route('dashboard'));
        $this->actingAs($global)->post(route('cajas.abrir'), ['id_sucursal' => $nueva->getKey(), 'monto_inicial' => 50])
            ->assertSessionHasNoErrors()->assertRedirect(route('cajas.index'));
        $this->assertDatabaseHas('cajas', ['id_sucursal' => $nueva->getKey(), 'estado' => 'ABIERTA']);

        $this->assertNotContains('Sucursal Nueva', $this->sucursalesDelSelector($restringido));
        $this->actingAs($restringido)->post(route('branch.set'), ['id_sucursal' => $nueva->getKey()])->assertForbidden();
    }

    public function test_quitar_la_sucursal_al_administrador_invalida_la_de_su_sesion(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $norte = $this->createSucursal('Sucursal Norte');
        $admin = $this->createUsuario(['registrar_ventas'], [$centro, $norte], 'Administrador');

        DB::table('usuario_sucursal')->where('id_usuario', $admin->getKey())->where('id_sucursal', $norte->getKey())->delete();

        $this->actingAs($admin)->withSession(['sucursal_activa_id' => $norte->getKey()])
            ->get(route('ventas.index'))->assertOk()->assertSessionHas('sucursal_activa_id', $centro->getKey());
    }

    public function test_migracion_agrega_la_columna_en_false_y_down_solo_la_quita(): void
    {
        $centro = $this->createSucursal();
        $sinSucursales = $this->createUsuario([], [], 'Administrador', accesoGlobal: true);
        $conSucursal = $this->createUsuario([], [$centro], 'Administrador');
        $cajero = $this->createUsuario([], [$centro], 'Cajero');
        $antes = DB::table('usuarios')->orderBy('id_usuario')->get(['id_usuario', 'nombre_usuario', 'password_hash', 'activo', 'id_persona', 'id_rol']);
        $filasPivote = DB::table('usuario_sucursal')->count();

        $migracion = require database_path('migrations/2026_11_28_000029_add_acceso_todas_sucursales_to_usuarios.php');

        // down(): quita solo la columna; usuarios y usuario_sucursal intactos.
        $migracion->down();
        $this->assertFalse(Schema::hasColumn('usuarios', 'acceso_todas_sucursales'));
        $this->assertEquals($antes, DB::table('usuarios')->orderBy('id_usuario')->get(['id_usuario', 'nombre_usuario', 'password_hash', 'activo', 'id_persona', 'id_rol']));
        $this->assertSame($filasPivote, DB::table('usuario_sucursal')->count());

        // up(): todos los usuarios existentes quedan en false, sin perder datos.
        $migracion->up();
        $this->assertTrue(Schema::hasColumn('usuarios', 'acceso_todas_sucursales'));
        foreach ([$sinSucursales, $conSucursal, $cajero] as $usuario) {
            $this->assertFalse($usuario->fresh()->acceso_todas_sucursales);
        }
        $this->assertEquals($antes, DB::table('usuarios')->orderBy('id_usuario')->get(['id_usuario', 'nombre_usuario', 'password_hash', 'activo', 'id_persona', 'id_rol']));
        $this->assertSame($filasPivote, DB::table('usuario_sucursal')->count());

        // Un usuario nuevo sin indicar el campo recibe el valor por defecto false.
        $persona = $this->persona('Nuevo');
        $id = DB::table('usuarios')->insertGetId([
            'nombre_usuario' => 'insert_directo', 'password_hash' => 'x', 'activo' => true,
            'id_persona' => $persona->getKey(), 'id_rol' => $this->rolCajero->getKey(),
        ], 'id_usuario');
        $this->assertFalse((bool) DB::table('usuarios')->where('id_usuario', $id)->value('acceso_todas_sucursales'));
    }

    public function test_registro_guarda_false_sin_casilla_y_true_solo_para_administrador(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $gestor = $this->gestor();
        $supervisor = Rol::firstOrCreate(['nombre' => 'Supervisor'], ['descripcion' => 'Supervisor', 'activo' => true]);

        // Administrador con sucursal específica y la casilla desmarcada (campo oculto = 0).
        $this->crearDesdeFormulario($gestor, [
            'id_persona' => $this->persona('Especifico')->getKey(), 'id_rol' => $this->rolAdmin->getKey(),
            'nombre_usuario' => 'admin_especifico', 'acceso_todas_sucursales' => '0',
            'sucursales' => [['id_sucursal' => $centro->getKey()]],
        ])->assertSessionHasNoErrors();
        $this->assertFalse(Usuario::where('nombre_usuario', 'admin_especifico')->value('acceso_todas_sucursales'));

        // Supervisor: no puede activarlo aunque lo envíe.
        $this->crearDesdeFormulario($gestor, [
            'id_persona' => $this->persona('Supervisor')->getKey(), 'id_rol' => $supervisor->getKey(),
            'nombre_usuario' => 'supervisor_global', 'acceso_todas_sucursales' => '1',
            'sucursales' => [['id_sucursal' => $centro->getKey()]],
        ])->assertSessionHasErrors(['acceso_todas_sucursales' => 'Solo el rol Administrador puede tener acceso a todas las sucursales.']);
        $this->assertDatabaseMissing('usuarios', ['nombre_usuario' => 'supervisor_global']);

        // Supervisor sin la casilla: se registra con false.
        $this->crearDesdeFormulario($gestor, [
            'id_persona' => $this->persona('Supervisora')->getKey(), 'id_rol' => $supervisor->getKey(),
            'nombre_usuario' => 'supervisor_normal', 'acceso_todas_sucursales' => '0',
            'sucursales' => [['id_sucursal' => $centro->getKey()]],
        ])->assertSessionHasNoErrors();
        $this->assertFalse(Usuario::where('nombre_usuario', 'supervisor_normal')->value('acceso_todas_sucursales'));
    }

    public function test_cajero_no_activa_acceso_global_manipulando_la_edicion(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $cajero = $this->createUsuario([], [$centro], 'Cajero');

        $this->actingAs($this->gestor())->from(route('usuarios.edit', $cajero))->put(route('usuarios.update', $cajero), [
            'nombre_usuario' => $cajero->nombre_usuario, 'id_persona' => $cajero->id_persona,
            'id_rol' => $this->rolCajero->getKey(), 'acceso_todas_sucursales' => '1',
        ])->assertRedirect(route('usuarios.edit', $cajero))
            ->assertSessionHasErrors(['acceso_todas_sucursales' => 'Solo el rol Administrador puede tener acceso a todas las sucursales.']);

        $this->assertFalse($cajero->fresh()->acceso_todas_sucursales);
        $this->assertSame([$centro->getKey()], $cajero->fresh()->sucursales()->pluck('sucursales.id_sucursal')->all());
    }

    public function test_usuarios_anteriores_siguen_funcionando(): void
    {
        $centro = $this->createSucursal('Sucursal Centro');
        $norte = $this->createSucursal('Sucursal Norte');
        // Usuarios "existentes": quedan con false y sus filas en usuario_sucursal.
        $cajero = $this->createUsuario(['registrar_ventas'], [$centro], 'Cajero');
        $admin = $this->createUsuario(['registrar_ventas'], [$centro, $norte], 'Administrador');

        foreach ([$cajero, $admin] as $usuario) {
            $this->postJson('/login', ['nombre_usuario' => $usuario->nombre_usuario, 'password' => 'password123'])->assertOk();
            $this->post('/logout');
        }

        $this->actingAs($cajero)->get(route('ventas.index'))->assertOk()->assertSessionHas('sucursal_activa_id', $centro->getKey());
        $this->assertSame(['Sucursal Centro', 'Sucursal Norte'], $this->sucursalesDelSelector($admin));
        $this->actingAs($admin)->post(route('branch.set'), ['id_sucursal' => $norte->getKey()])->assertRedirect(route('dashboard'));
    }
}
