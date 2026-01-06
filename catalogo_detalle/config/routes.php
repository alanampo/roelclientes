<?php
/**
 * Detección dinámica de rutas del proyecto
 * Funciona en cualquier ubicación: /catalogo_detalle, / o subdominios
 *
 * Genera variables JS globales:
 * - window.APP_BASE_URL: ruta base del proyecto (ej: "/catalogo_detalle/", "/")
 * - window.API_BASE_URL: ruta base para API (ej: "/catalogo_detalle/api/", "/api/")
 */

// Detectar la ruta base dinámicamente
$scriptPath = dirname($_SERVER['SCRIPT_NAME']); // Ej: /catalogo_detalle
$baseUrl = rtrim($scriptPath, '/') . '/';         // Ej: /catalogo_detalle/
$apiBaseUrl = $baseUrl . 'api/';                  // Ej: /catalogo_detalle/api/

// Función helper para construir URLs
function buildUrl($path) {
    global $baseUrl;
    return $baseUrl . ltrim($path, '/');
}

function buildApiUrl($path) {
    global $apiBaseUrl;
    return $apiBaseUrl . ltrim($path, '/');
}
?>

<script>
/**
 * Rutas dinámicas del proyecto
 * Se generan automáticamente desde PHP para funcionar en cualquier ubicación
 */

// Ruta base del proyecto (ej: "/catalogo_detalle/" o "/")
window.APP_BASE_URL = '<?php echo htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8'); ?>';

// Ruta base para API (ej: "/catalogo_detalle/api/" o "/api/")
window.API_BASE_URL = '<?php echo htmlspecialchars($apiBaseUrl, ENT_QUOTES, 'UTF-8'); ?>';

/**
 * Helper para construir URLs dinámicas
 * @param {string} path - Ruta relativa (ej: "auth/login.php" o "/auth/login.php")
 * @returns {string} - URL absoluta completa
 *
 * Ejemplos:
 *   buildUrl('catalogo_tabla.php') -> "/catalogo_detalle/catalogo_tabla.php"
 *   buildApiUrl('auth/login.php') -> "/catalogo_detalle/api/auth/login.php"
 */
function buildUrl(path) {
    return window.APP_BASE_URL + path.replace(/^\//, '');
}

function buildApiUrl(path) {
    return window.API_BASE_URL + path.replace(/^\//, '');
}

// Log para debugging (remover en producción si es necesario)
console.log('🌐 Dynamic Routes Loaded:');
console.log('  APP_BASE_URL:', window.APP_BASE_URL);
console.log('  API_BASE_URL:', window.API_BASE_URL);
</script>
