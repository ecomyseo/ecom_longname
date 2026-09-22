<?php
/**
 * Ecom Longname - Nombre de producto largo
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * Todo lo que toca la columna real del nombre del producto:
 * `product_lang`.`name`, su indice y la validacion del ObjectModel.
 */
class EcomLongnameSchema
{
    const CONF_ENABLED = 'ECOM_LONGNAME_ENABLED';
    const CONF_MAXLEN = 'ECOM_LONGNAME_MAXLEN';
    const CONF_APPLIED = 'ECOM_LONGNAME_APPLIED';

    /** @var int longitud que trae PrestaShop de serie */
    const LONGITUD_NUCLEO = 128;

    /** @var int longitud que propone el modulo */
    const LONGITUD_POR_DEFECTO = 1000;

    /**
     * Prefijo del indice `name`. Con utf8mb4 un VARCHAR(1000) ocupa 4000 bytes
     * y no cabe en un indice de InnoDB (3072 bytes), asi que el indice pasa a
     * ser de prefijo. 191 caracteres es el maximo seguro (191 * 4 = 764 bytes)
     * y sigue sirviendo para ordenar y para buscar por el principio del nombre.
     */
    const PREFIJO_INDICE = 191;

    /** @var int|null longitud actual de la columna, leida una vez por peticion */
    protected static $longitudActual = null;

    /** @var bool si ya se ha ampliado la definicion en esta peticion */
    protected static $definicionAmpliada = false;

    /**
     * Longitud configurada por el usuario.
     *
     * @return int
     */
    public static function getLongitudConfigurada()
    {
        $n = (int) Configuration::getGlobalValue(self::CONF_MAXLEN);
        if ($n <= 0) {
            $n = self::LONGITUD_POR_DEFECTO;
        }

        return self::acotar($n);
    }

    /**
     * Mantiene la longitud dentro de lo razonable.
     *
     * @param int $n
     *
     * @return int
     */
    public static function acotar($n)
    {
        $n = (int) $n;
        if ($n < self::LONGITUD_NUCLEO) {
            return self::LONGITUD_NUCLEO;
        }
        // 16383 es el maximo de un VARCHAR utf8mb4 en una fila de InnoDB.
        if ($n > 8000) {
            return 8000;
        }

        return $n;
    }

    /**
     * Indica si el modulo esta activado.
     *
     * @return bool
     */
    public static function isEnabled()
    {
        return (bool) Configuration::getGlobalValue(self::CONF_ENABLED);
    }

    /**
     * Longitud REAL de la columna `product_lang`.`name` en la base de datos.
     *
     * @param bool $refrescar
     *
     * @return int 0 si no se ha podido averiguar
     */
    public static function getLongitudColumna($refrescar = false)
    {
        if (!$refrescar && self::$longitudActual !== null) {
            return self::$longitudActual;
        }

        $sql = 'SELECT CHARACTER_MAXIMUM_LENGTH
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
            AND TABLE_NAME = \'' . pSQL(_DB_PREFIX_ . 'product_lang') . '\'
            AND COLUMN_NAME = \'name\'';

        try {
            $valor = Db::getInstance()->getValue($sql);
        } catch (Exception $e) {
            EcomLongnameLogger::log('No se ha podido leer la longitud de la columna: ' . $e->getMessage());
            $valor = false;
        }

        self::$longitudActual = (int) $valor;

        return self::$longitudActual;
    }

    /**
     * Longitud del prefijo del indice `name`, o 0 si el indice es completo.
     *
     * @return int
     */
    public static function getPrefijoIndice()
    {
        $sql = 'SELECT SUB_PART FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
            AND TABLE_NAME = \'' . pSQL(_DB_PREFIX_ . 'product_lang') . '\'
            AND COLUMN_NAME = \'name\'';

        try {
            return (int) Db::getInstance()->getValue($sql);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Indica si el indice `name` existe.
     *
     * @return bool
     */
    public static function existeIndice()
    {
        $sql = 'SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
            WHERE TABLE_SCHEMA = \'' . pSQL(_DB_NAME_) . '\'
            AND TABLE_NAME = \'' . pSQL(_DB_PREFIX_ . 'product_lang') . '\'
            AND INDEX_NAME = \'name\'';

        try {
            return (int) Db::getInstance()->getValue($sql) > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Cuantos nombres de producto pasan de la longitud indicada. Sirve para
     * avisar antes de encoger la columna: esos nombres se perderian.
     *
     * @param int $longitud
     *
     * @return int
     */
    public static function contarMasLargosDe($longitud)
    {
        $sql = 'SELECT COUNT(*) FROM `' . _DB_PREFIX_ . 'product_lang`
            WHERE CHAR_LENGTH(`name`) > ' . (int) $longitud;

        try {
            return (int) Db::getInstance()->getValue($sql);
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * El nombre mas largo que hay ahora mismo en el catalogo.
     *
     * @return int
     */
    public static function longitudMaximaEnUso()
    {
        try {
            return (int) Db::getInstance()->getValue(
                'SELECT MAX(CHAR_LENGTH(`name`)) FROM `' . _DB_PREFIX_ . 'product_lang`'
            );
        } catch (Exception $e) {
            return 0;
        }
    }

    /**
     * Cambia de verdad la columna `product_lang`.`name`.
     *
     * Antes de tocar la columna hay que arreglar el indice: PrestaShop crea
     * `KEY name (name)` sobre la columna entera, y con mas de 191 caracteres en
     * utf8mb4 ese indice no cabe en InnoDB. Se rehace como indice de prefijo.
     *
     * @param int $longitud
     *
     * @return array array('ok' => bool, 'error' => string, 'pasos' => array)
     */
    public static function aplicar($longitud)
    {
        $longitud = self::acotar($longitud);
        $pasos = array();
        $db = Db::getInstance();

        // Encoger la columna trunca los nombres que no quepan, y eso no se
        // deshace. Antes de acortar se cuenta lo que se perderia.
        $actual = self::getLongitudColumna(true);
        if ($actual > 0 && $longitud < $actual) {
            $sobran = self::contarMasLargosDe($longitud);
            if ($sobran > 0) {
                return array(
                    'ok' => false,
                    'error' => 'sobran',
                    'cuantos' => $sobran,
                    'longitud' => $longitud,
                    'pasos' => array(),
                );
            }
        }

        if ($actual === $longitud) {
            return array('ok' => true, 'error' => '', 'pasos' => array('la columna ya estaba en ' . $longitud));
        }

        try {
            // 1. El indice, primero: si se queda sobre la columna entera, el
            //    ALTER de la columna falla con "Specified key was too long".
            if (self::existeIndice()) {
                $db->execute('ALTER TABLE `' . _DB_PREFIX_ . 'product_lang` DROP INDEX `name`');
                $pasos[] = 'índice `name` retirado';
            }

            $prefijo = min(self::PREFIJO_INDICE, $longitud);
            $db->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'product_lang`
                 MODIFY `name` VARCHAR(' . (int) $longitud . ') NOT NULL'
            );
            $pasos[] = 'columna `name` a VARCHAR(' . (int) $longitud . ')';

            $db->execute(
                'ALTER TABLE `' . _DB_PREFIX_ . 'product_lang`
                 ADD INDEX `name` (`name`(' . (int) $prefijo . '))'
            );
            $pasos[] = 'índice `name` rehecho con prefijo de ' . (int) $prefijo;
        } catch (Exception $e) {
            EcomLongnameLogger::log('Error aplicando el cambio de columna: ' . $e->getMessage(), $pasos);

            // Si el indice se quito y el resto fallo, hay que devolverlo.
            if (!self::existeIndice()) {
                try {
                    $actual = self::getLongitudColumna(true);
                    $prefijo = min(self::PREFIJO_INDICE, $actual > 0 ? $actual : self::PREFIJO_INDICE);
                    $db->execute(
                        'ALTER TABLE `' . _DB_PREFIX_ . 'product_lang`
                         ADD INDEX `name` (`name`(' . (int) $prefijo . '))'
                    );
                    $pasos[] = 'índice `name` repuesto tras el fallo';
                } catch (Exception $e2) {
                    $pasos[] = 'ATENCIÓN: el índice `name` no se ha podido reponer';
                }
            }

            return array('ok' => false, 'error' => $e->getMessage(), 'pasos' => $pasos);
        }

        Configuration::updateGlobalValue(self::CONF_MAXLEN, (int) $longitud);
        Configuration::updateGlobalValue(self::CONF_APPLIED, (int) $longitud);
        self::getLongitudColumna(true);

        EcomLongnameLogger::log('Columna product_lang.name ampliada', array(
            'longitud' => $longitud,
            'pasos' => $pasos,
        ));

        return array('ok' => true, 'error' => '', 'pasos' => $pasos);
    }

    /**
     * Deja la columna como la trae PrestaShop. Se niega a hacerlo si hay
     * nombres que se perderian.
     *
     * @return array
     */
    public static function revertir()
    {
        $resultado = self::aplicar(self::LONGITUD_NUCLEO);
        if ($resultado['ok']) {
            Configuration::updateGlobalValue(self::CONF_APPLIED, 0);
        }

        return $resultado;
    }

    /**
     * Amplia la validacion de longitud del ObjectModel Product.
     *
     * `Product::$definition['fields']['name']['size']` vale
     * ProductSettings::MAX_NAME_LENGTH (128) y es lo que mira validateFields():
     * sin esto, guardar un nombre mas largo lanza PrestaShopException aunque la
     * columna ya lo admita.
     *
     * @param int|null $longitud
     */
    public static function ampliarDefinicion($longitud = null)
    {
        if (self::$definicionAmpliada) {
            return;
        }
        if ($longitud === null) {
            $longitud = self::getLongitudConfigurada();
        }
        $longitud = (int) $longitud;

        try {
            $definicion = Product::$definition;
            if (!isset($definicion['fields']['name'])) {
                return;
            }
            if ((int) $definicion['fields']['name']['size'] >= $longitud) {
                self::$definicionAmpliada = true;

                return;
            }

            $definicion['fields']['name']['size'] = $longitud;
            Product::$definition = $definicion;

            // getDefinition() guarda el resultado en Cache, y el constructor de
            // ObjectModel cachea las propiedades de cada clase ya cargada: si no
            // se vacian las dos, se seguiria validando con el tamano viejo.
            Cache::clean('objectmodel_def_Product');
            ObjectModel::resetStaticCache();

            self::$definicionAmpliada = true;
            EcomLongnameLogger::log('Definicion de Product ampliada a ' . $longitud);
        } catch (Exception $e) {
            EcomLongnameLogger::log('No se ha podido ampliar la definicion: ' . $e->getMessage());
        }
    }

    /**
     * Amplia la validacion de una instancia de Product ya construida.
     *
     * El constructor copia la definicion en $this->def, que es protegida, asi
     * que un objeto creado antes de ampliarDefinicion() seguiria con 128.
     *
     * @param ObjectModel $objeto
     * @param int $longitud
     */
    public static function ampliarInstancia($objeto, $longitud)
    {
        try {
            $propiedad = new ReflectionProperty('ObjectModelCore', 'def');
            $propiedad->setAccessible(true);
            $definicion = $propiedad->getValue($objeto);
            if (!is_array($definicion) || !isset($definicion['fields']['name'])) {
                return;
            }
            if ((int) $definicion['fields']['name']['size'] >= (int) $longitud) {
                return;
            }
            $definicion['fields']['name']['size'] = (int) $longitud;
            $propiedad->setValue($objeto, $definicion);
        } catch (Exception $e) {
            EcomLongnameLogger::log('No se ha podido ampliar la instancia: ' . $e->getMessage());
        } catch (ReflectionException $e) {
            EcomLongnameLogger::log('No se ha podido ampliar la instancia: ' . $e->getMessage());
        }
    }

    /**
     * `link_rewrite` sigue siendo VARCHAR(128) y su definicion tambien: si se
     * genera a partir de un nombre largo, hay que recortarlo o el guardado
     * revienta.
     *
     * @param ObjectModel $producto
     */
    public static function recortarLinkRewrite($producto)
    {
        if (!isset($producto->link_rewrite)) {
            return;
        }

        if (is_array($producto->link_rewrite)) {
            foreach ($producto->link_rewrite as $idLang => $valor) {
                if (Tools::strlen($valor) > self::LONGITUD_NUCLEO) {
                    $producto->link_rewrite[$idLang] = rtrim(
                        Tools::substr($valor, 0, self::LONGITUD_NUCLEO),
                        '-'
                    );
                }
            }
        } elseif (Tools::strlen($producto->link_rewrite) > self::LONGITUD_NUCLEO) {
            $producto->link_rewrite = rtrim(
                Tools::substr($producto->link_rewrite, 0, self::LONGITUD_NUCLEO),
                '-'
            );
        }
    }

    /**
     * Resumen del estado, para pintarlo en la pantalla del modulo.
     *
     * @return array
     */
    public static function estado()
    {
        $columna = self::getLongitudColumna(true);
        $configurada = self::getLongitudConfigurada();

        return array(
            'columna' => $columna,
            'configurada' => $configurada,
            'nucleo' => self::LONGITUD_NUCLEO,
            'aplicado' => $columna >= $configurada && $columna > self::LONGITUD_NUCLEO,
            'indice' => self::existeIndice(),
            'prefijo' => self::getPrefijoIndice(),
            'maximo_en_uso' => self::longitudMaximaEnUso(),
            'pasan_de_128' => self::contarMasLargosDe(self::LONGITUD_NUCLEO),
            'definicion' => isset(Product::$definition['fields']['name']['size'])
                ? (int) Product::$definition['fields']['name']['size']
                : 0,
        );
    }
}
