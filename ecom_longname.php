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

require_once _PS_MODULE_DIR_ . 'ecom_longname/classes/EcomLongnameLogger.php';
require_once _PS_MODULE_DIR_ . 'ecom_longname/classes/EcomLongnameSchema.php';

/**
 * Amplia el nombre del producto de 128 caracteres a los que se configuren
 * (1000 de fabrica), tocando lo que de verdad lo limita: la columna
 * product_lang.name, la validacion del ObjectModel y el formulario de la ficha
 * de producto. El nombre se escribe en la ficha, como cualquier otro.
 */
class Ecom_Longname extends Module
{
    const CONF_VERSION = 'ECOM_LONGNAME_VERSION';

    public function __construct()
    {
        $this->name = 'ecom_longname';
        $this->tab = 'administration';
        $this->version = '2.0.0';
        $this->author = 'Ecom Experts';
        $this->need_instance = 0;
        $this->bootstrap = true;
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => _PS_VERSION_);

        parent::__construct();

        $this->displayName = $this->trans('Ecom Long product name', array(), 'Modules.Ecomlongname.Admin');
        $this->description = $this->trans(
            'Raises the product name limit from 128 characters to the length you choose, in the database and in the product page. The long name then shows up by itself in the cart, the order, the emails, the invoices and the tables.',
            array(),
            'Modules.Ecomlongname.Admin'
        );
        $this->confirmUninstall = $this->trans(
            'The column keeps the length it has now: your long names are not touched.',
            array(),
            'Modules.Ecomlongname.Admin'
        );
    }

    /**
     * Amplia la visibilidad de trans() para las clases del modulo.
     *
     * @param string $id
     * @param array $parameters
     * @param string|null $domain
     * @param string|null $locale
     *
     * @return string
     */
    public function trans($id, array $parameters = array(), $domain = null, $locale = null)
    {
        return parent::trans($id, $parameters, $domain, $locale);
    }

    /**
     * @return bool
     */
    public function isUsingNewTranslationSystem()
    {
        return true;
    }

    /* ---------------------------------------------------------------------
     * Instalacion
     * ------------------------------------------------------------------ */

    /**
     * Hooks que usa el modulo.
     *
     * @return array
     */
    protected function getModuleHooks()
    {
        return array(
            // Lo antes posible en la peticion: amplia la validacion del ObjectModel.
            'actionDispatcherBefore',
            // Ficha de producto V2 (Symfony): relaja el limite del campo Nombre.
            'actionProductFormBuilderModifier',
            // Ultima red: amplia la validacion del objeto que se va a guardar.
            'actionObjectProductAddBefore',
            'actionObjectProductUpdateBefore',
        );
    }

    /**
     * @return bool
     */
    public function install()
    {
        if (!parent::install()) {
            return false;
        }

        foreach ($this->getModuleHooks() as $hook) {
            if (!$this->registerHook($hook)) {
                return false;
            }
        }

        Configuration::updateGlobalValue(EcomLongnameSchema::CONF_ENABLED, 1);
        Configuration::updateGlobalValue(
            EcomLongnameSchema::CONF_MAXLEN,
            EcomLongnameSchema::LONGITUD_POR_DEFECTO
        );
        Configuration::updateGlobalValue(EcomLongnameLogger::CONF_DEBUG, 0);
        Configuration::updateGlobalValue(self::CONF_VERSION, $this->version);

        // El cambio de la columna se aplica al instalar: es lo que el usuario
        // viene a buscar. Si falla, la instalacion no se cae; la pantalla del
        // modulo lo dice y trae el boton para reintentarlo.
        $resultado = EcomLongnameSchema::aplicar(EcomLongnameSchema::LONGITUD_POR_DEFECTO);
        if (!$resultado['ok']) {
            EcomLongnameLogger::log('La columna no se ha podido ampliar al instalar: ' . $resultado['error']);
        }

        return true;
    }

    /**
     * Al desinstalar NO se encoge la columna: si hay nombres de mas de 128
     * caracteres, encogerla los truncaria sin remedio. Se deja como esta y el
     * usuario decide desde el boton de la pantalla antes de desinstalar.
     *
     * @return bool
     */
    public function uninstall()
    {
        foreach (array(
            EcomLongnameSchema::CONF_ENABLED,
            EcomLongnameSchema::CONF_MAXLEN,
            EcomLongnameSchema::CONF_APPLIED,
            EcomLongnameLogger::CONF_DEBUG,
            self::CONF_VERSION,
        ) as $clave) {
            Configuration::deleteByName($clave);
        }

        return parent::uninstall();
    }

    /**
     * Migraciones y reparaciones, al abrir la configuracion.
     */
    public function addnewfeatures()
    {
        try {
            foreach ($this->getModuleHooks() as $hook) {
                if (!$this->isRegisteredInHook($hook)) {
                    $this->registerHook($hook);
                    EcomLongnameLogger::log('Hook registrado en la revisión: ' . $hook);
                }
            }

            if (Configuration::getGlobalValue(EcomLongnameSchema::CONF_MAXLEN) === false) {
                Configuration::updateGlobalValue(
                    EcomLongnameSchema::CONF_MAXLEN,
                    EcomLongnameSchema::LONGITUD_POR_DEFECTO
                );
            }

            Configuration::updateGlobalValue(self::CONF_VERSION, $this->version);
        } catch (Exception $e) {
            EcomLongnameLogger::log('Error en addnewfeatures: ' . $e->getMessage());
        }
    }

    /**
     * Comprueba que lo que el modulo promete esta de verdad puesto.
     *
     * @return array lista de problemas, vacia si todo esta bien
     */
    public function selfCheck()
    {
        $problemas = array();
        $estado = EcomLongnameSchema::estado();

        if ($estado['columna'] <= 0) {
            $problemas[] = $this->trans(
                'The column product_lang.name could not be read.',
                array(),
                'Modules.Ecomlongname.Admin'
            );
        } elseif ($estado['columna'] < $estado['configurada']) {
            $problemas[] = $this->trans(
                'The database still limits the name to %n% characters. Press "Apply to the database".',
                array('%n%' => $estado['columna']),
                'Modules.Ecomlongname.Admin'
            );
        }

        if ($estado['indice'] && $estado['columna'] > EcomLongnameSchema::PREFIJO_INDICE && !$estado['prefijo']) {
            $problemas[] = $this->trans(
                'The name index covers the whole column: it should be a prefix index.',
                array(),
                'Modules.Ecomlongname.Admin'
            );
        }

        foreach ($this->getModuleHooks() as $hook) {
            if (!$this->isRegisteredInHook($hook)) {
                $problemas[] = $this->trans(
                    'The module is not hooked to %hook%.',
                    array('%hook%' => $hook),
                    'Modules.Ecomlongname.Admin'
                );
            }
        }

        return $problemas;
    }

    /* ---------------------------------------------------------------------
     * Hooks
     * ------------------------------------------------------------------ */

    /**
     * Lo primero de la peticion: la validacion del ObjectModel Product pasa a
     * admitir la longitud configurada.
     *
     * @param array $params
     */
    public function hookActionDispatcherBefore(array $params)
    {
        if (!EcomLongnameSchema::isEnabled()) {
            return;
        }

        EcomLongnameSchema::ampliarDefinicion();
    }

    /**
     * Ficha de producto V2 (PrestaShop 8.1+ y 9.x).
     *
     * El campo Nombre lleva un constraint Length(max = 128) puesto en
     * HeaderType. Se le sube el maximo: el constraint es un objeto, asi que
     * cambiar su propiedad publica basta y no hay que rehacer el campo.
     *
     * @param array $params
     */
    public function hookActionProductFormBuilderModifier(array $params)
    {
        if (!EcomLongnameSchema::isEnabled()) {
            return;
        }

        $longitud = EcomLongnameSchema::getLongitudConfigurada();
        EcomLongnameSchema::ampliarDefinicion($longitud);

        if (!isset($params['form_builder']) || !is_object($params['form_builder'])) {
            return;
        }

        try {
            $constructor = $params['form_builder'];
            if (!method_exists($constructor, 'has') || !$constructor->has('header')) {
                return;
            }
            $cabecera = $constructor->get('header');
            if (!$cabecera->has('name')) {
                return;
            }

            $opciones = $cabecera->get('name')->getOptions();
            $tocados = 0;
            $tocados += $this->subirLimiteLongitud(
                isset($opciones['options']['constraints']) ? $opciones['options']['constraints'] : array(),
                $longitud
            );
            $tocados += $this->subirLimiteLongitud(
                isset($opciones['constraints']) ? $opciones['constraints'] : array(),
                $longitud
            );

            EcomLongnameLogger::log('Ficha de producto: límite del nombre subido a ' . $longitud, array(
                'constraints' => $tocados,
            ));
        } catch (Exception $e) {
            EcomLongnameLogger::log('No se ha podido tocar el formulario de producto: ' . $e->getMessage());
        }
    }

    /**
     * Sube el maximo de los constraints Length que encuentre.
     *
     * @param array $constraints
     * @param int $longitud
     *
     * @return int cuantos ha cambiado
     */
    protected function subirLimiteLongitud($constraints, $longitud)
    {
        $tocados = 0;
        foreach (is_array($constraints) ? $constraints : array() as $constraint) {
            if (!is_object($constraint)) {
                continue;
            }
            if (!($constraint instanceof \Symfony\Component\Validator\Constraints\Length)) {
                continue;
            }
            if ($constraint->max !== null && (int) $constraint->max < (int) $longitud) {
                $constraint->max = (int) $longitud;
                ++$tocados;
            }
        }

        return $tocados;
    }

    /**
     * @param array $params
     */
    public function hookActionObjectProductAddBefore(array $params)
    {
        $this->prepararProducto($params);
    }

    /**
     * @param array $params
     */
    public function hookActionObjectProductUpdateBefore(array $params)
    {
        $this->prepararProducto($params);
    }

    /**
     * Ultima red antes de que PrestaShop valide el producto: amplia la
     * definicion de ESTA instancia (el constructor ya la habia copiado) y
     * recorta el link_rewrite, que sigue siendo de 128.
     *
     * @param array $params
     */
    protected function prepararProducto(array $params)
    {
        if (!EcomLongnameSchema::isEnabled()) {
            return;
        }
        if (!isset($params['object']) || !($params['object'] instanceof Product)) {
            return;
        }

        $longitud = EcomLongnameSchema::getLongitudConfigurada();
        EcomLongnameSchema::ampliarDefinicion($longitud);
        EcomLongnameSchema::ampliarInstancia($params['object'], $longitud);
        EcomLongnameSchema::recortarLinkRewrite($params['object']);
    }

    /* ---------------------------------------------------------------------
     * Pantalla de configuracion
     * ------------------------------------------------------------------ */

    /**
     * @return string
     */
    public function getContent()
    {
        $this->addnewfeatures();

        $salida = '';

        if (Tools::isSubmit('submitEcomLongname')) {
            $salida .= $this->guardarConfiguracion();
        }

        if (Tools::isSubmit('submitEcomLongnameApply')) {
            $salida .= $this->aplicarColumna();
        }

        if (Tools::isSubmit('submitEcomLongnameRevert')) {
            $salida .= $this->revertirColumna();
        }

        if (Tools::isSubmit('submitEcomLongnameClearLog')) {
            $n = EcomLongnameLogger::clear();
            $salida .= $this->displayConfirmation(
                $this->trans('Log files deleted: %n%.', array('%n%' => $n), 'Modules.Ecomlongname.Admin')
            );
        }

        return $salida . $this->renderHeader() . $this->renderForm();
    }

    /**
     * @return string
     */
    protected function guardarConfiguracion()
    {
        $longitud = EcomLongnameSchema::acotar(Tools::getValue(EcomLongnameSchema::CONF_MAXLEN));

        Configuration::updateGlobalValue(
            EcomLongnameSchema::CONF_ENABLED,
            (int) Tools::getValue(EcomLongnameSchema::CONF_ENABLED)
        );
        Configuration::updateGlobalValue(EcomLongnameSchema::CONF_MAXLEN, (int) $longitud);
        Configuration::updateGlobalValue(
            EcomLongnameLogger::CONF_DEBUG,
            (int) Tools::getValue(EcomLongnameLogger::CONF_DEBUG)
        );
        EcomLongnameLogger::setEnabled((bool) Tools::getValue(EcomLongnameLogger::CONF_DEBUG));

        $mensaje = $this->displayConfirmation(
            $this->trans('Settings saved.', array(), 'Modules.Ecomlongname.Admin')
        );

        // Guardar la longitud sin llevarla a la base de datos no sirve de nada:
        // se aplica siempre que la columna no coincida, suba o baje.
        $estado = EcomLongnameSchema::estado();
        if ($estado['columna'] !== $longitud) {
            $mensaje .= $this->aplicarColumna($longitud);
        }

        return $mensaje;
    }

    /**
     * @param int|null $longitud
     *
     * @return string
     */
    protected function aplicarColumna($longitud = null)
    {
        if ($longitud === null) {
            $longitud = EcomLongnameSchema::getLongitudConfigurada();
        }

        $resultado = EcomLongnameSchema::aplicar($longitud);

        if (!$resultado['ok'] && $resultado['error'] === 'sobran') {
            return $this->displayError(
                $this->trans(
                    'It cannot go down to %len% characters: %n% product names are longer and would be cut. Shorten them first.',
                    array('%len%' => $resultado['longitud'], '%n%' => $resultado['cuantos']),
                    'Modules.Ecomlongname.Admin'
                )
            );
        }

        if (!$resultado['ok']) {
            return $this->displayError(
                $this->trans(
                    'The database could not be changed: %error%',
                    array('%error%' => $resultado['error']),
                    'Modules.Ecomlongname.Admin'
                )
            );
        }

        return $this->displayConfirmation(
            $this->trans(
                'The product name now accepts %n% characters in the database.',
                array('%n%' => EcomLongnameSchema::getLongitudColumna(true)),
                'Modules.Ecomlongname.Admin'
            )
        );
    }

    /**
     * @return string
     */
    protected function revertirColumna()
    {
        $resultado = EcomLongnameSchema::revertir();

        if (!$resultado['ok'] && $resultado['error'] === 'sobran') {
            return $this->displayError(
                $this->trans(
                    'It cannot go down to %len% characters: %n% product names are longer and would be cut. Shorten them first.',
                    array('%len%' => EcomLongnameSchema::LONGITUD_NUCLEO, '%n%' => $resultado['cuantos']),
                    'Modules.Ecomlongname.Admin'
                )
            );
        }

        if (!$resultado['ok']) {
            return $this->displayError(
                $this->trans(
                    'The database could not be changed: %error%',
                    array('%error%' => $resultado['error']),
                    'Modules.Ecomlongname.Admin'
                )
            );
        }

        return $this->displayConfirmation(
            $this->trans('The product name is back to 128 characters.', array(), 'Modules.Ecomlongname.Admin')
        );
    }

    /**
     * Cabecera: lo que hay que saber y los botones. Lo demas, en el acordeon.
     *
     * @return string
     */
    protected function renderHeader()
    {
        if (isset($this->context->controller) && method_exists($this->context->controller, 'addCSS')) {
            $this->context->controller->addCSS($this->_path . 'views/css/admin.css');
        }

        $estado = EcomLongnameSchema::estado();

        $this->context->smarty->assign(array(
            'lnm_problems' => $this->selfCheck(),
            'lnm_estado' => $estado,
            'lnm_enabled' => EcomLongnameSchema::isEnabled(),
            'lnm_module_url' => $this->context->link->getAdminLink('AdminModules', true)
                . '&configure=' . $this->name . '&module_name=' . $this->name,
            'lnm_productos_url' => $this->context->link->getAdminLink('AdminProducts'),
            'lnm_debug' => EcomLongnameLogger::isEnabled(),
            'lnm_logs' => array_map('basename', array_slice(EcomLongnameLogger::getFiles(), 0, 5)),
            'lnm_multishop' => Shop::isFeatureActive(),
        ));

        return $this->display(__FILE__, 'views/templates/admin/header.tpl');
    }

    /**
     * Un unico formulario con pestanas.
     *
     * @return string
     */
    protected function renderForm()
    {
        $fields_form = array();
        $fields_form[0]['form'] = array(
            'legend' => array(
                'title' => $this->trans('Settings', array(), 'Modules.Ecomlongname.Admin'),
                'icon' => 'icon-cogs',
            ),
            'tabs' => array(
                'lnmgeneral' => $this->trans('General', array(), 'Modules.Ecomlongname.Admin'),
                'lnmadvanced' => $this->trans('Advanced', array(), 'Modules.Ecomlongname.Admin'),
            ),
            'input' => array(
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Allow long names', array(), 'Modules.Ecomlongname.Admin'),
                    'name' => EcomLongnameSchema::CONF_ENABLED,
                    'tab' => 'lnmgeneral',
                    'desc' => $this->trans('If it is off, PrestaShop goes back to validating 128 characters.', array(), 'Modules.Ecomlongname.Admin'),
                    'is_bool' => true,
                    'values' => array(
                        array('id' => 'lnm_enabled_on', 'value' => 1, 'label' => $this->trans('Yes', array(), 'Modules.Ecomlongname.Admin')),
                        array('id' => 'lnm_enabled_off', 'value' => 0, 'label' => $this->trans('No', array(), 'Modules.Ecomlongname.Admin')),
                    ),
                ),
                array(
                    'type' => 'text',
                    'label' => $this->trans('Characters allowed', array(), 'Modules.Ecomlongname.Admin'),
                    'name' => EcomLongnameSchema::CONF_MAXLEN,
                    'tab' => 'lnmgeneral',
                    'class' => 'fixed-width-sm',
                    'desc' => $this->trans('Between 128 and 8000. Saving already applies it to the database.', array(), 'Modules.Ecomlongname.Admin'),
                ),
                array(
                    'type' => 'switch',
                    'label' => $this->trans('Debug log', array(), 'Modules.Ecomlongname.Admin'),
                    'name' => EcomLongnameLogger::CONF_DEBUG,
                    'tab' => 'lnmadvanced',
                    'desc' => $this->trans('Writes what the module does in the logs folder of the module.', array(), 'Modules.Ecomlongname.Admin'),
                    'is_bool' => true,
                    'values' => array(
                        array('id' => 'lnm_debug_on', 'value' => 1, 'label' => $this->trans('Yes', array(), 'Modules.Ecomlongname.Admin')),
                        array('id' => 'lnm_debug_off', 'value' => 0, 'label' => $this->trans('No', array(), 'Modules.Ecomlongname.Admin')),
                    ),
                ),
            ),
            'submit' => array(
                'title' => $this->trans('Save', array(), 'Modules.Ecomlongname.Admin'),
                'class' => 'btn btn-default pull-right',
            ),
        );

        $helper = new HelperForm();
        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = (int) $this->context->language->id;
        $helper->allow_employee_form_lang = (int) $this->context->language->id;
        $helper->title = $this->displayName;
        $helper->submit_action = 'submitEcomLongname';
        $helper->tpl_vars = array(
            'fields_value' => array(
                EcomLongnameSchema::CONF_ENABLED => (int) Configuration::getGlobalValue(EcomLongnameSchema::CONF_ENABLED),
                EcomLongnameSchema::CONF_MAXLEN => EcomLongnameSchema::getLongitudConfigurada(),
                EcomLongnameLogger::CONF_DEBUG => (int) Configuration::getGlobalValue(EcomLongnameLogger::CONF_DEBUG),
            ),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => (int) $this->context->language->id,
        );

        return $helper->generateForm($fields_form);
    }
}
