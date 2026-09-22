<?php
/**
 * Ecom Longname - Nombres largos de producto
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Registro de depuracion del modulo. Solo escribe si la opcion esta activada
 * en la configuracion. Los ficheros viven dentro del propio modulo.
 */
class EcomLongnameLogger
{
    const CONF_DEBUG = 'ECOM_LONGNAME_DEBUG';

    /** @var bool|null */
    protected static $enabled = null;

    /**
     * Indica si el registro esta activado.
     *
     * @return bool
     */
    public static function isEnabled()
    {
        if (self::$enabled === null) {
            self::$enabled = (bool) Configuration::getGlobalValue(self::CONF_DEBUG);
        }

        return self::$enabled;
    }

    /**
     * Fuerza el estado del registro (lo usa la pantalla de configuracion al guardar).
     *
     * @param bool $enabled
     */
    public static function setEnabled($enabled)
    {
        self::$enabled = (bool) $enabled;
    }

    /**
     * Carpeta donde se guardan los ficheros de registro.
     *
     * @return string
     */
    public static function getDir()
    {
        return _PS_MODULE_DIR_ . 'ecom_longname/logs/';
    }

    /**
     * Escribe una linea en el registro del dia.
     *
     * @param string $message
     * @param array $context
     */
    public static function log($message, array $context = array())
    {
        if (!self::isEnabled()) {
            return;
        }

        $dir = self::getDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }

        $line = '[' . date('Y-m-d H:i:s') . '] ' . (string) $message;
        if (!empty($context)) {
            $json = json_encode($context, 64 | 256);
            if ($json === false) {
                $json = '';
            }
            $line .= ' ' . $json;
        }

        @file_put_contents(
            $dir . 'ecom_longname-' . date('Y-m-d') . '.log',
            $line . PHP_EOL,
            FILE_APPEND
        );
    }

    /**
     * Lista los ficheros de registro existentes, del mas nuevo al mas viejo.
     *
     * @return array
     */
    public static function getFiles()
    {
        $files = glob(self::getDir() . 'ecom_longname-*.log');
        if (!is_array($files)) {
            return array();
        }
        rsort($files);

        return $files;
    }

    /**
     * Borra todos los ficheros de registro.
     *
     * @return int numero de ficheros borrados
     */
    public static function clear()
    {
        $n = 0;
        foreach (self::getFiles() as $file) {
            if (@unlink($file)) {
                ++$n;
            }
        }

        return $n;
    }
}
