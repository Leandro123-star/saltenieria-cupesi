<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\Persona;
use App\Models\Producto;
use App\Models\Rol;
use App\Models\Sucursal;
use App\Models\Usuario;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ValidacionFormulariosTest extends TestCase
{
    private const CREDENCIALES = 'Usuario o contraseña incorrectos.';

    private function admin(array $sucursales = []): Usuario
    {
        return $this->createUsuario([], $sucursales, 'Administrador');
    }

    /* ------------------------------------------------------------------
     | Inicio de sesión desde el formulario web
     * ------------------------------------------------------------------ */

    public function test_login_web_correcto_ingresa_al_sistema(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['registrar_ventas'], [$sucursal]);

        $this->from('/login')->post('/login', [
            'nombre_usuario' => $usuario->nombre_usuario,
            'password' => 'password123',
        ])->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($usuario);
    }

    public function test_login_web_con_usuario_inexistente_vuelve_al_formulario_con_mensaje(): void
    {
        $response = $this->from('/login')->post('/login', [
            'nombre_usuario' => 'no_existe',
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('login'))
            ->assertSessionHasErrors(['nombre_usuario' => self::CREDENCIALES])
            ->assertSessionHasInput('nombre_usuario', 'no_existe')
            ->assertSessionMissing('_old_input.password');
        $this->assertStringNotContainsString('application/json', (string) $response->headers->get('Content-Type'));
        $this->assertGuest();
    }

    public function test_login_web_con_contrasena_incorrecta_muestra_el_error_en_la_vista(): void
    {
        $usuario = $this->createUsuario();

        $this->from('/login')->post('/login', [
            'nombre_usuario' => $usuario->nombre_usuario,
            'password' => 'incorrecta',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['nombre_usuario' => self::CREDENCIALES]);

        // La vista de login (siguiente solicitud) muestra el mensaje y conserva el usuario.
        $this->get('/login')
            ->assertOk()
            ->assertSee(self::CREDENCIALES)
            ->assertSee('value="'.$usuario->nombre_usuario.'"', false)
            ->assertDontSee('incorrecta')
            ->assertDontSee('"message"', false);
        $this->assertGuest();
    }

    public function test_login_web_con_usuario_inactivo_usa_el_mismo_mensaje_generico(): void
    {
        $usuario = $this->createUsuario();
        $usuario->update(['activo' => false]);

        $this->from('/login')->post('/login', [
            'nombre_usuario' => $usuario->nombre_usuario,
            'password' => 'password123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['nombre_usuario' => self::CREDENCIALES]);
    }

    public function test_login_web_con_contrasena_corta_no_revela_la_politica(): void
    {
        $usuario = $this->createUsuario();

        $this->from('/login')->post('/login', [
            'nombre_usuario' => $usuario->nombre_usuario,
            'password' => '123',
        ])->assertRedirect(route('login'))
            ->assertSessionHasErrors(['nombre_usuario' => self::CREDENCIALES]);
    }

    public function test_login_web_con_campos_vacios_muestra_mensajes_en_espanol(): void
    {
        $this->from('/login')->post('/login', [])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'nombre_usuario' => 'Ingresa tu nombre de usuario.',
                'password' => 'Ingresa tu contraseña.',
            ]);
    }

    public function test_login_por_api_conserva_la_respuesta_json(): void
    {
        $usuario = $this->createUsuario();

        $this->postJson('/login', [
            'nombre_usuario' => $usuario->nombre_usuario,
            'password' => 'incorrecta',
        ])->assertStatus(422)->assertJson(['message' => self::CREDENCIALES]);
    }

    /* ------------------------------------------------------------------
     | Personas
     * ------------------------------------------------------------------ */

    public function test_registra_personas_con_nombres_y_telefonos_validos(): void
    {
        $admin = $this->admin();
        $validos = [
            ['María José', 'Gutiérrez-Paz', '71234567', '7845123'],
            ["O'Brien", 'Ñuflo Müller', '+59171234567', '1234567-1B'],
            ['Ana   Lucía', '  Choque  Mamani ', null, null],
        ];

        foreach ($validos as [$nombres, $apellidos, $telefono, $ci]) {
            $this->actingAs($admin)->from(route('personas.create'))->post(route('personas.store'), [
                'nombres' => $nombres, 'apellidos' => $apellidos, 'telefono' => $telefono, 'ci' => $ci, 'activo' => 1,
            ])->assertRedirect(route('personas.index'))->assertSessionHasNoErrors();
        }

        $this->assertDatabaseHas('personas', ['nombres' => 'María José', 'apellidos' => 'Gutiérrez-Paz', 'telefono' => '71234567']);
        // Los espacios repetidos se normalizan.
        $this->assertDatabaseHas('personas', ['nombres' => 'Ana Lucía', 'apellidos' => 'Choque Mamani']);
    }

    public function test_rechaza_numeros_en_nombre_y_apellido(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->from(route('personas.create'))->post(route('personas.store'), [
            'nombres' => 'Juan2', 'apellidos' => 'Pérez3', 'activo' => 1,
        ])->assertRedirect(route('personas.create'))
            ->assertSessionHasErrors([
                'nombres' => 'El nombre no debe contener números ni símbolos.',
                'apellidos' => 'El apellido no debe contener números ni símbolos.',
            ])
            ->assertSessionHasInput('nombres', 'Juan2');

        $this->actingAs($admin)->post(route('personas.store'), [
            'nombres' => 'Ana@', 'apellidos' => 'Rojas', 'activo' => 1,
        ])->assertSessionHasErrors('nombres');

        $this->assertDatabaseMissing('personas', ['nombres' => 'Juan2']);
        $this->assertDatabaseMissing('personas', ['nombres' => 'Ana@']);
    }

    public function test_rechaza_telefono_invalido_y_campos_obligatorios(): void
    {
        $admin = $this->admin();

        foreach (['71-abc', '123', '7123 4567'] as $telefono) {
            $this->actingAs($admin)->post(route('personas.store'), [
                'nombres' => 'Luis', 'apellidos' => 'Saavedra', 'telefono' => $telefono, 'activo' => 1,
            ])->assertSessionHasErrors(['telefono' => 'El teléfono tiene un formato incorrecto: usa de 7 a 14 dígitos, con + opcional al inicio.']);
        }

        $this->actingAs($admin)->post(route('personas.store'), ['nombres' => '', 'apellidos' => '', 'activo' => 1])
            ->assertSessionHasErrors(['nombres' => 'Este campo es obligatorio.', 'apellidos' => 'Este campo es obligatorio.']);

        $this->assertDatabaseMissing('personas', ['nombres' => 'Luis']);
    }

    public function test_formulario_de_persona_muestra_el_error_junto_al_campo(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->from(route('personas.create'))->post(route('personas.store'), [
            'nombres' => 'Juan2', 'apellidos' => 'Pérez', 'activo' => 1,
        ]);

        $this->actingAs($admin)->get(route('personas.create'))
            ->assertOk()
            ->assertSee('class="field-error"', false)
            ->assertSee('El nombre no debe contener números ni símbolos.')
            ->assertSee('is-invalid', false)
            ->assertSee('Revisa los campos marcados en el formulario.')
            ->assertSee('value="Juan2"', false);
    }

    /* ------------------------------------------------------------------
     | Usuarios
     * ------------------------------------------------------------------ */

    public function test_nombre_de_usuario_sin_espacios_y_unico(): void
    {
        $admin = $this->admin();
        $rol = Rol::firstOrCreate(['nombre' => 'Supervisor'], ['descripcion' => 'Prueba', 'activo' => true]);
        $persona = Persona::create(['nombres' => 'Rosa', 'apellidos' => 'Quispe', 'activo' => true]);
        $datos = fn (string $nombre) => [
            'id_persona' => $persona->getKey(), 'id_rol' => $rol->getKey(),
            'nombre_usuario' => $nombre, 'password' => 'password123',
        ];

        $this->actingAs($admin)->post(route('usuarios.store'), $datos('juan perez'))
            ->assertSessionHasErrors('nombre_usuario')
            ->assertSessionMissing('_old_input.password');
        $this->actingAs($admin)->post(route('usuarios.store'), $datos($admin->nombre_usuario))
            ->assertSessionHasErrors(['nombre_usuario' => 'El nombre de usuario ya está registrado.']);
        $this->actingAs($admin)->post(route('usuarios.store'), $datos('cajero_6'))
            ->assertSessionHasNoErrors()->assertRedirect(route('usuarios.index'));

        $this->assertDatabaseHas('usuarios', ['nombre_usuario' => 'cajero_6']);
        $this->assertDatabaseMissing('usuarios', ['nombre_usuario' => 'juan perez']);
    }

    public function test_usuario_rechaza_rol_persona_y_sucursal_inexistentes(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('usuarios.store'), [
            'id_persona' => 999999, 'id_rol' => 999999, 'nombre_usuario' => 'valido_1', 'password' => 'password123',
            'sucursales' => [['id_sucursal' => 999999]],
        ])->assertSessionHasErrors([
            'id_persona' => 'Selecciona una persona registrada y activa.',
            'id_rol' => 'Selecciona un rol existente y activo.',
            'sucursales.0.id_sucursal' => 'Selecciona sucursales registradas.',
        ]);
    }

    /* ------------------------------------------------------------------
     | Productos y stock
     * ------------------------------------------------------------------ */

    public function test_registra_productos_con_nombres_comerciales_y_precio_valido(): void
    {
        $admin = $this->admin();
        $categoria = $this->createCategoria();

        foreach (['Gaseosa 2 L', 'Agua 600 ml', 'Combo 2x1 (salteña + refresco)'] as $nombre) {
            $this->actingAs($admin)->post(route('productos.store'), [
                'nombre' => $nombre, 'precio' => '15', 'activo' => 1, 'id_categoria' => $categoria->getKey(),
            ])->assertSessionHasNoErrors()->assertRedirect(route('productos.index'));
        }

        $this->assertDatabaseHas('productos', ['nombre' => 'Gaseosa 2 L', 'precio' => '15.00']);
    }

    public function test_rechaza_precios_invalidos_y_nombres_sin_letras(): void
    {
        $admin = $this->admin();
        $categoria = $this->createCategoria();

        foreach (['-5', '0', 'abc'] as $precio) {
            $this->actingAs($admin)->post(route('productos.store'), [
                'nombre' => 'Salteña especial', 'precio' => $precio, 'activo' => 1, 'id_categoria' => $categoria->getKey(),
            ])->assertSessionHasErrors(['precio' => 'El precio debe ser un número mayor que cero.']);
        }

        $this->actingAs($admin)->post(route('productos.store'), [
            'nombre' => '12345', 'precio' => '5', 'activo' => 1, 'id_categoria' => $categoria->getKey(),
        ])->assertSessionHasErrors('nombre');
        $this->actingAs($admin)->post(route('productos.store'), [
            'nombre' => '<script>', 'precio' => '5', 'activo' => 1, 'id_categoria' => $categoria->getKey(),
        ])->assertSessionHasErrors('nombre');

        $this->assertDatabaseMissing('productos', ['nombre' => 'Salteña especial']);
    }

    public function test_stock_acepta_enteros_no_negativos_y_rechaza_valores_invalidos(): void
    {
        $sucursal = $this->createSucursal();
        $admin = $this->admin([$sucursal]);
        $producto = $this->createProducto($this->createCategoria());

        foreach (['-1', '2.5', 'diez'] as $cantidad) {
            $this->actingAs($admin)->patch(route('productos.stock', $producto), [
                'id_sucursal' => $sucursal->getKey(), 'stock_actual' => $cantidad, 'stock_minimo' => 0,
            ])->assertSessionHasErrors('stock_actual');
        }
        $this->assertDatabaseMissing('producto_sucursal', ['id_producto' => $producto->getKey()]);

        $this->actingAs($admin)->patch(route('productos.stock', $producto), [
            'id_sucursal' => $sucursal->getKey(), 'stock_actual' => 20, 'stock_minimo' => 0,
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('producto_sucursal', ['id_producto' => $producto->getKey(), 'stock_actual' => 20]);
    }

    /* ------------------------------------------------------------------
     | Categorías, métodos de pago, sucursales y roles
     * ------------------------------------------------------------------ */

    public function test_categorias_validan_nombre_y_duplicados(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('categorias.store'), ['nombre' => 'Bebidas calientes', 'descripcion' => 'Café, té y api.', 'activo' => 1])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('categorias.store'), ['nombre' => 'Bebidas calientes', 'activo' => 1])
            ->assertSessionHasErrors(['nombre' => 'Ya existe una categoría con ese nombre.']);
        $this->actingAs($admin)->post(route('categorias.store'), ['nombre' => '$$$', 'activo' => 1])
            ->assertSessionHasErrors('nombre');
    }

    public function test_metodos_de_pago_validan_nombre(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('metodos-pago.store'), ['nombre' => 'Tarjeta de débito', 'activo' => 1])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('metodos-pago.store'), ['nombre' => '###', 'activo' => 1])
            ->assertSessionHasErrors('nombre');
        $this->actingAs($admin)->post(route('metodos-pago.store'), ['nombre' => 'Tarjeta de débito', 'activo' => 1])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un método de pago con ese nombre.']);
    }

    public function test_sucursales_validan_direccion_y_telefono(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('sucursales.store'), [
            'nombre' => 'Sucursal Mercado', 'direccion' => 'Villa Montes - Calle Tarija #45', 'telefono' => '46722105', 'activo' => 1,
        ])->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('sucursales.store'), [
            'nombre' => 'Sucursal Plaza', 'direccion' => 'Calle Tarija @45', 'telefono' => '467ABC', 'activo' => 1,
        ])->assertSessionHasErrors(['direccion', 'telefono']);

        $this->assertDatabaseHas('sucursales', ['nombre' => 'Sucursal Mercado']);
        $this->assertDatabaseMissing('sucursales', ['nombre' => 'Sucursal Plaza']);
    }

    public function test_roles_validan_nombre_y_aceptan_cajero_vendedor(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('roles.store'), ['nombre' => 'Vendedor/Turno-Noche', 'activo' => 1])
            ->assertSessionHasNoErrors();
        $this->actingAs($admin)->post(route('roles.store'), ['nombre' => '1Admin!', 'activo' => 1])
            ->assertSessionHasErrors('nombre');
        $this->actingAs($admin)->post(route('roles.store'), ['nombre' => 'Vendedor/Turno-Noche', 'activo' => 1])
            ->assertSessionHasErrors(['nombre' => 'Ya existe un rol con ese nombre.']);
    }

    public function test_desactivar_sucursal_con_ventas_redirige_con_mensaje_en_web(): void
    {
        $sucursal = $this->createSucursal();
        $admin = $this->admin([$sucursal]);
        $caja = Caja::create([
            'fecha_apertura' => now(), 'monto_inicial' => '0.00', 'estado' => 'ABIERTA',
            'id_sucursal' => $sucursal->getKey(), 'id_usuario_apertura' => $admin->getKey(),
        ]);
        DB::table('ventas')->insert([
            'fecha_venta' => now(), 'total' => '10.00', 'estado' => 'CONFIRMADA',
            'id_sucursal' => $sucursal->getKey(), 'id_usuario' => $admin->getKey(), 'id_caja' => $caja->getKey(),
        ]);

        $this->actingAs($admin)->delete(route('sucursales.destroy', $sucursal))
            ->assertRedirect(route('sucursales.index'))
            ->assertSessionHas('error', 'No se puede desactivar una sucursal con ventas.');
        $this->actingAs($admin)->deleteJson(route('sucursales.destroy', $sucursal))->assertStatus(422);
        $this->assertTrue($sucursal->fresh()->activo);
    }

    /* ------------------------------------------------------------------
     | Ventas y caja
     * ------------------------------------------------------------------ */

    public function test_venta_rechaza_cantidades_e_importes_invalidos(): void
    {
        $sucursal = $this->createSucursal();
        $cajero = $this->createUsuario(['registrar_ventas'], [$sucursal]);
        $producto = $this->createProducto($this->createCategoria(), ['precio' => '10.00']);
        $metodo = $this->createMetodoPago('Efectivo');

        $casos = [
            [0, '10.00', 'detalles.0.cantidad', 'La cantidad debe ser un número entero mayor que cero.'],
            [1.5, '15.00', 'detalles.0.cantidad', 'La cantidad debe ser un número entero válido.'],
            [1, '-10.00', 'pagos.0.importe', 'El importe debe ser un número mayor que cero.'],
        ];
        foreach ($casos as [$cantidad, $importe, $campo, $mensaje]) {
            $this->actingAs($cajero)->postJson(route('ventas.store'), [
                'id_sucursal' => $sucursal->getKey(),
                'detalles' => [['id_producto' => $producto->getKey(), 'cantidad' => $cantidad]],
                'pagos' => [['id_metodo_pago' => $metodo->getKey(), 'importe' => $importe]],
            ])->assertStatus(422)->assertJsonValidationErrors([$campo => $mensaje]);
        }

        $this->assertDatabaseCount('ventas', 0);
    }

    public function test_caja_rechaza_montos_negativos_y_acepta_cero(): void
    {
        $sucursal = $this->createSucursal();
        $usuario = $this->createUsuario(['gestionar_caja'], [$sucursal]);

        $this->actingAs($usuario)->post(route('cajas.abrir'), ['id_sucursal' => $sucursal->getKey(), 'monto_inicial' => '-50'])
            ->assertSessionHasErrors(['monto_inicial' => 'El monto inicial no puede ser negativo.']);
        $this->assertDatabaseCount('cajas', 0);

        $this->actingAs($usuario)->post(route('cajas.abrir'), ['id_sucursal' => $sucursal->getKey(), 'monto_inicial' => '0'])
            ->assertSessionHasNoErrors();
        $caja = Caja::firstOrFail();

        $this->actingAs($usuario)->post(route('cajas.cerrar', $caja), ['efectivo_contado' => '-1', 'qr_verificado' => 'abc'])
            ->assertSessionHasErrors([
                'efectivo_contado' => 'El efectivo contado no puede ser negativo.',
                'qr_verificado' => 'El monto QR verificado debe ser un número (0 o mayor).',
            ]);
        $this->assertSame('ABIERTA', $caja->fresh()->estado);
    }

    public function test_todos_los_formularios_cargan_con_sus_atributos_de_validacion(): void
    {
        $sucursal = $this->createSucursal();
        $admin = $this->admin([$sucursal]);
        $producto = $this->createProducto($this->createCategoria());

        $paginas = [
            route('personas.create') => ['pattern=', 'type="tel"'],
            route('usuarios.create') => ['pattern=', 'minlength="8"'],
            route('usuarios.edit', $admin) => ['pattern=', 'autocomplete="new-password"'],
            route('productos.create') => ['pattern=', 'min="0.01"'],
            route('productos.edit', $producto) => ['max="99999.99"'],
            route('productos.index') => ['step="1"'],
            route('categorias.create') => ['pattern='],
            route('metodos-pago.create') => ['pattern='],
            route('sucursales.create') => ['pattern=', 'type="tel"'],
            route('roles.create') => ['pattern='],
            route('cajas.index') => ['max="999999.99"'],
        ];
        foreach ($paginas as $url => $fragmentos) {
            $response = $this->actingAs($admin)->get($url)->assertOk();
            foreach ($fragmentos as $fragmento) {
                $response->assertSee($fragmento, false);
            }
        }

        // Tras un error, el formulario de usuario conserva las sucursales elegidas y no la contraseña.
        $this->actingAs($admin)->from(route('usuarios.create'))->post(route('usuarios.store'), [
            'nombre_usuario' => 'con espacio', 'password' => 'secreta123',
            'sucursales' => [['id_sucursal' => $sucursal->getKey()]],
        ]);
        $this->actingAs($admin)->get(route('usuarios.create'))->assertOk()
            ->assertSee('value="con espacio"', false)
            ->assertDontSee('secreta123')
            ->assertSee('<option value="'.$sucursal->getKey().'" selected', false);
    }

    public function test_mensajes_generales_de_laravel_salen_en_espanol(): void
    {
        $this->assertSame('es', app()->getLocale());
        $this->assertSame('Este campo es obligatorio.', __('validation.required', ['attribute' => 'nombre']));
    }
}
