<?php
// app/Services/BusquedaService.php
//
// FIX H-1: Helper reutilizable para búsquedas por palabras no consecutivas.
//
// Ejemplo: si el usuario busca "Carlos Castillo", el sistema debe encontrar a
// "Carlos Blanco Castillo" (las palabras no están juntas en ese orden).
//
// Implementación:
//   1. Normaliza el texto: lowercase, sin acentos, sin espacios múltiples.
//   2. Divide por espacios → array de palabras.
//   3. Genera N where('columna', 'like', '%palabra%') encadenados con AND.
//
// Uso típico:
//   BusquedaService::aplicarBusquedaPalabras($query, 'nombre_completo', $q);
//   // → WHERE LOWER(nombre_completo) LIKE '%carlos%' AND LOWER(nombre_completo) LIKE '%castillo%'

namespace App\Services;

class BusquedaService
{
    /**
     * Normaliza un texto para búsqueda: lowercase, sin acentos, sin espacios múltiples.
     */
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower($texto, 'UTF-8');
        // Quitar acentos
        $texto = transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $texto);
        // Si transliterator no está disponible, fallback manual
        if ($texto === false) {
            $texto = str_replace(
                ['á','é','í','ó','ú','ü','ñ','Á','É','Í','Ó','Ú','Ü','Ñ'],
                ['a','e','i','o','u','u','n','A','E','I','O','U','U','N'],
                mb_strtolower($texto, 'UTF-8')
            );
        }
        // Colapsar espacios múltiples
        $texto = preg_replace('/\s+/', ' ', trim($texto));
        return $texto ?? '';
    }

    /**
     * Aplica búsqueda por palabras no consecutivas a una columna del query.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $columna  Nombre de la columna (ej: 'nombre_completo')
     * @param string $texto    Texto de búsqueda del usuario
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function aplicarBusquedaPalabras($query, string $columna, string $texto)
    {
        $texto = self::normalizar($texto);
        if ($texto === '') return $query;

        $palabras = explode(' ', $texto);
        $palabras = array_filter($palabras, fn($p) => strlen($p) >= 2); // ignorar palabras de 1 char

        if (empty($palabras)) return $query;

        // MySQL: LOWER() respeta el collation, pero para ignorar acentos necesitamos
        // comparar con texto sin acentos. Usamos LOWER(columna) LIKE '%palabra%'.
        // Si la BD usa utf8mb4_unicode_ci (recomendado), los acentos se ignoran solos.
        return $query->where(function ($q) use ($columna, $palabras) {
            foreach ($palabras as $palabra) {
                $q->where($columna, 'like', '%' . $palabra . '%');
            }
        });
    }

    /**
     * Versión que busca en múltiples columnas (OR entre columnas, AND entre palabras).
     *
     * Ejemplo: buscar "Carlos" en 'nombre_completo' OR 'username'
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param array $columnas  ['nombre_completo', 'username']
     * @param string $texto
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function aplicarBusquedaPalabrasMultiColumna($query, array $columnas, string $texto)
    {
        $texto = self::normalizar($texto);
        if ($texto === '') return $query;

        $palabras = explode(' ', $texto);
        $palabras = array_filter($palabras, fn($p) => strlen($p) >= 2);

        if (empty($palabras)) return $query;

        // Para cada palabra, debe aparecer en ALGUNA de las columnas (OR entre columnas).
        // Todas las palabras deben estar (AND entre palabras).
        return $query->where(function ($q) use ($columnas, $palabras) {
            foreach ($palabras as $palabra) {
                $q->where(function ($sub) use ($columnas, $palabra) {
                    foreach ($columnas as $i => $col) {
                        if ($i === 0) {
                            $sub->where($col, 'like', '%' . $palabra . '%');
                        } else {
                            $sub->orWhere($col, 'like', '%' . $palabra . '%');
                        }
                    }
                });
            }
        });
    }
}