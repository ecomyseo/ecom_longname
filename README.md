# Ecom Longname — Nombres de producto largos para PrestaShop

**Ecom Longname** amplía el límite habitual de **128 caracteres** del nombre de producto de PrestaShop a una longitud configurable.

La configuración inicial es de **1.000 caracteres** y el módulo admite valores entre **128 y 8.000**.

El nombre largo se introduce directamente en la ficha de producto, sin campos alternativos ni necesidad de modificar manualmente el núcleo de PrestaShop.

## Características

- Amplía la columna `product_lang.name` de la base de datos.
- Adapta la validación de `Product` y el campo de nombre del formulario Symfony de producto.
- Aplica la validación también antes de crear o actualizar productos.
- Permite configurar la longitud y aplicar el cambio de esquema desde el panel del módulo.
- Incluye autocomprobación, registro de diagnóstico opcional y opción para limpiar el registro.
- Incluye traducciones al español (`es-ES`).
- Conserva los nombres largos al desinstalar: **no reduce automáticamente la columna**.

Los nombres guardados se utilizan en los flujos estándar de PrestaShop (carrito, pedidos, correos y facturas).

La presentación completa puede depender del tema, módulos de terceros, integraciones y límites de sus plantillas o sistemas externos.

---

## Compatibilidad

- **Versión del módulo:** 2.0.0.
- Declara compatibilidad desde PrestaShop 1.7.0.0 hasta la versión de PrestaShop instalada.
- La adaptación del formulario se realiza mediante `actionProductFormBuilderModifier`.

Se recomienda comprobar su funcionamiento en la versión concreta de PrestaShop, tema y módulos instalados antes de utilizarlo en producción.

---

## Instalación

1. Realiza una **copia de seguridad de la base de datos** y prueba primero en un entorno de desarrollo.
2. Descarga el módulo como ZIP asegurándote de que la carpeta raíz del paquete sea `ecom_longname/`.
3. En PrestaShop, abre **Módulos → Gestor de módulos → Subir un módulo** y selecciona el ZIP.
4. Instala el módulo y entra en **Configurar**.
5. Comprueba el diagnóstico del módulo.

Durante la instalación, el módulo intenta ampliar `product_lang.name` a 1.000 caracteres.

Si el cambio falla, la instalación puede terminar igualmente. Revisa el aviso y utiliza la opción para volver a aplicar el cambio.

También puedes copiar la carpeta `ecom_longname` dentro de `/modules/` e instalar el módulo desde el gestor.

---

## Configuración y uso

1. Accede a **Módulos → Ecom Longname → Configurar**.
2. Define la longitud máxima deseada (128–8.000 caracteres).
3. Guarda la configuración.
4. Aplica el cambio de columna cuando corresponda.
5. Comprueba el estado mostrado en la pantalla.
6. Abre una ficha de producto e introduce un nombre que supere los 128 caracteres.
7. Guarda el producto y verifica su presentación.

Es recomendable comprobar el nombre en:

- Ficha de producto.
- Listados y categorías.
- Carrito.
- Pedidos.
- Correos electrónicos.
- Facturas.
- Integraciones externas.

**Importante:** cambiar únicamente el ajuste no sustituye la comprobación de que la columna de la base de datos tenga la longitud esperada.

---

## ¿Cómo funciona?

El módulo interviene en tres capas:

| Capa | Acción |
|---|---|
| Base de datos | Modifica `PREFIX_product_lang.name` a `VARCHAR(longitud)` y gestiona el índice `name` cuando es necesario. |
| Modelo | Amplía la definición de validación del nombre de `Product`. |
| Formulario | Ajusta el límite del campo de nombre en el formulario Symfony de productos. |

### Hooks utilizados

```text
actionDispatcherBefore
actionProductFormBuilderModifier
actionObjectProductAddBefore
actionObjectProductUpdateBefore
```

### URLs amigables

El módulo también controla la longitud de `link_rewrite`, que sigue limitada a **128 caracteres**.

Un nombre largo no implica que la URL amigable pueda tener la misma longitud.

---

## Seguridad de los datos y desinstalación

El módulo **no reduce la columna al desinstalarse**, para evitar la pérdida de nombres de más de 128 caracteres.

Dispone de una acción independiente para volver al límite original, pero rechaza la operación si encuentra nombres que se truncarían.

Acorta primero esos nombres si necesitas restaurar el esquema.

### Precauciones

Las modificaciones de estructura mediante `ALTER TABLE` pueden:

- Bloquear temporalmente la tabla.
- Fallar por restricciones del motor de base de datos.
- Encontrar problemas con los índices.
- Fallar por permisos insuficientes.

Programa la operación en un momento adecuado y conserva una copia de seguridad.

---

## Diagnóstico

Si el formulario no acepta nombres largos o el producto no se guarda:

1. Comprueba en la configuración que el módulo esté habilitado.
2. Verifica que la longitud de la columna se haya aplicado correctamente.
3. Revisa los hooks registrados.
4. Activa temporalmente el registro de depuración.
5. Comprueba posibles validaciones adicionales en overrides, módulos de terceros, importadores y conectores.
6. Verifica el tamaño real de la columna `name` en la tabla `PREFIX_product_lang`.

Sustituye `PREFIX_` por el prefijo de tu tienda.

---

## Estructura del módulo

```text
ecom_longname/
├── ecom_longname.php
├── classes/
│   ├── EcomLongnameLogger.php
│   └── EcomLongnameSchema.php
├── logs/
├── sql/
├── translations/
│   └── es-ES/
└── views/
    ├── css/
    │   └── admin.css
    └── templates/
        └── admin/
            └── header.tpl
```

---

## Autor

**Ecom Experts**

Contacto: ecomyseo@gmail.com

---

## Licencia

Academic Free License 3.0 (AFL-3.0).

https://opensource.org/licenses/AFL-3.0
