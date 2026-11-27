<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Formatos de texto por tipo de campo. Cada campo usa la expresión que
 * corresponde a su finalidad: un nombre de persona no admite números, pero el
 * nombre de un producto o una dirección sí pueden contenerlos.
 *
 * Las constantes *_HTML son el equivalente para el atributo `pattern` de los
 * formularios (el navegador las evalúa con el indicador `v`, por eso los
 * caracteres especiales dentro de las clases van escapados).
 */
final class Formatos
{
    /** Letras (con tildes, ñ, ü), espacios, apóstrofo, punto y guion; empieza con letra. */
    public const NOMBRE_PERSONA = "/^\\p{L}[\\p{L}\\p{M} '’.\\-]*$/u";
    public const NOMBRE_PERSONA_HTML = "\\p{L}[\\p{L}\\p{M} '’\\.\\-]*";

    /** "+" opcional y de 7 a 14 dígitos (formato de los teléfonos registrados: 71234567). */
    public const TELEFONO = '/^\+?[0-9]{7,14}$/';
    public const TELEFONO_HTML = '\+?[0-9]{7,14}';

    /** Carnet de identidad: 4 a 12 dígitos y complemento opcional (1234567-1B). */
    public const CI = '/^[0-9]{4,12}(?:-?[0-9A-Za-z]{1,3})?$/';
    public const CI_HTML = '[0-9]{4,12}(-?[0-9A-Za-z]{1,3})?';

    /** Nombre de usuario sin espacios. */
    public const NOMBRE_USUARIO = '/^[A-Za-z0-9._-]{3,30}$/';
    public const NOMBRE_USUARIO_HTML = '[A-Za-z0-9\._\-]{3,30}';

    /** Nombres comerciales (productos, categorías, métodos de pago, sucursales): admiten números. */
    public const NOMBRE_COMERCIAL = "/^(?=.*\\p{L})[\\p{L}\\p{M}\\p{N} .,'’&()\\/%+\\-]+$/u";
    public const NOMBRE_COMERCIAL_HTML = "(?=.*\\p{L})[\\p{L}\\p{M}\\p{N} \\.,'’&\\(\\)\\/%\\+\\-]+";

    /** Direcciones: letras, números y signos usuales (#, °, º, /, -). */
    public const DIRECCION = "/^(?=.*\\p{L})[\\p{L}\\p{M}\\p{N} .,#°º'’()\\/\\-]+$/u";
    public const DIRECCION_HTML = "(?=.*\\p{L})[\\p{L}\\p{M}\\p{N} \\.,#°º'’\\(\\)\\/\\-]+";

    /** Nombre de rol: empieza con letra; admite "Cajero/Vendedor". */
    public const NOMBRE_ROL = '/^\p{L}[\p{L}\p{M}\p{N} \/.\-]*$/u';
    public const NOMBRE_ROL_HTML = '\p{L}[\p{L}\p{M}\p{N} \/\.\-]*';

    /** Aplica espacios() a los campos de texto que llegaron en la solicitud. */
    public static function normalizar(Request $request, array $campos): void
    {
        $valores = [];
        foreach ($campos as $campo) {
            if ($request->has($campo) && (is_string($request->input($campo)) || $request->input($campo) === null)) {
                $valores[$campo] = self::espacios($request->input($campo));
            }
        }
        $request->merge($valores);
    }

    /** Normaliza espacios: recorta extremos y reduce los repetidos a uno. */
    public static function espacios(?string $valor): ?string
    {
        if ($valor === null) {
            return null;
        }
        $limpio = trim(preg_replace('/\s+/u', ' ', $valor));

        return $limpio === '' ? null : $limpio;
    }
}
