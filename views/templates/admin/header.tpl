{*
 * Ecom Longname - Nombre de producto largo
 *
 * @author    Ecom Experts <ecomyseo@gmail.com>
 * @copyright 2026 Ecom Experts
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License (AFL 3.0)
 *}
<div class="lnm-header">

  {foreach from=$lnm_problems item=problem}
    <div class="alert alert-danger">{$problem|escape:'html':'UTF-8'}</div>
  {/foreach}

  {if !$lnm_enabled}
    <div class="alert alert-warning">
      {l s='The module is off: PrestaShop is validating 128 characters again. Turn it on in the General tab.' d='Modules.Ecomlongname.Admin'}
    </div>
  {/if}

  {if $lnm_multishop}
    <div class="alert alert-info">
      {l s='The column is the same for every shop: this setting is global.' d='Modules.Ecomlongname.Admin'}
    </div>
  {/if}

  <div class="panel">
    <div class="panel-heading">
      <i class="icon-text-width"></i> {l s='Long product name' d='Modules.Ecomlongname.Admin'}
    </div>

    <table class="table lnm-estado">
      <tbody>
        <tr>
          <td>{l s='Characters the database accepts now' d='Modules.Ecomlongname.Admin'}</td>
          <td>
            <strong class="{if $lnm_estado.columna >= $lnm_estado.configurada}text-success{else}text-danger{/if}">
              {$lnm_estado.columna|intval}
            </strong>
            <span class="text-muted">({l s='PrestaShop brings 128' d='Modules.Ecomlongname.Admin'})</span>
          </td>
        </tr>
        <tr>
          <td>{l s='Characters the module validates' d='Modules.Ecomlongname.Admin'}</td>
          <td><strong>{$lnm_estado.configurada|intval}</strong></td>
        </tr>
        <tr>
          <td>{l s='Longest name in the catalog right now' d='Modules.Ecomlongname.Admin'}</td>
          <td>
            <strong>{$lnm_estado.maximo_en_uso|intval}</strong>
            {if $lnm_estado.pasan_de_128 > 0}
              <span class="text-muted">
                &middot; {l s='names over 128 characters:' d='Modules.Ecomlongname.Admin'}
                {$lnm_estado.pasan_de_128|intval}
              </span>
            {/if}
          </td>
        </tr>
        <tr>
          <td>{l s='Index on the name column' d='Modules.Ecomlongname.Admin'}</td>
          <td>
            {if !$lnm_estado.indice}
              <span class="text-danger">{l s='missing' d='Modules.Ecomlongname.Admin'}</span>
            {elseif $lnm_estado.prefijo}
              {l s='prefix of %n% characters' sprintf=['%n%' => $lnm_estado.prefijo] d='Modules.Ecomlongname.Admin'}
            {else}
              {l s='whole column' d='Modules.Ecomlongname.Admin'}
            {/if}
          </td>
        </tr>
      </tbody>
    </table>

    <form method="post" action="{$lnm_module_url|escape:'html':'UTF-8'}" class="lnm-botones">
      <button type="submit" name="submitEcomLongnameApply" class="btn btn-primary">
        <i class="icon-database"></i> {l s='Apply to the database' d='Modules.Ecomlongname.Admin'}
      </button>
      <a class="btn btn-default" href="{$lnm_productos_url|escape:'html':'UTF-8'}">
        <i class="icon-tags"></i> {l s='Go to the product catalog' d='Modules.Ecomlongname.Admin'}
      </a>
      <button type="submit" name="submitEcomLongnameRevert" class="btn btn-default pull-right"
              onclick="return confirm('{l s='The name goes back to 128 characters. Shall we go on?' d='Modules.Ecomlongname.Admin' js=1}');">
        <i class="icon-undo"></i> {l s='Back to 128 characters' d='Modules.Ecomlongname.Admin'}
      </button>
      {if $lnm_debug}
        <button type="submit" name="submitEcomLongnameClearLog" class="btn btn-default">
          <i class="icon-trash"></i> {l s='Empty the log' d='Modules.Ecomlongname.Admin'}
        </button>
      {/if}
    </form>

    <p class="help-block lnm-oneline">
      {l s='The long name is written in the product page, in the Name field, like always. Nothing else changes.' d='Modules.Ecomlongname.Admin'}
    </p>

    {if $lnm_debug && $lnm_logs|@count > 0}
      <p class="help-block">
        {l s='Last log files:' d='Modules.Ecomlongname.Admin'}
        {foreach from=$lnm_logs item=logfile name=logs}
          <code>{$logfile|escape:'html':'UTF-8'}</code>{if !$smarty.foreach.logs.last}, {/if}
        {/foreach}
      </p>
    {/if}

    <div class="lnm-accordion">
      <a href="#" class="lnm-accordion-toggle" data-lnm-accordion="lnm-help">
        <i class="icon-question-sign"></i> {l s='Help / how it works' d='Modules.Ecomlongname.Admin'}
      </a>
      <div class="lnm-accordion-body" id="lnm-help" style="display:none;">

        <h4>{l s='What it changes' d='Modules.Ecomlongname.Admin'}</h4>
        <p>
          {l s='PrestaShop limits the name of a product to 128 characters, and that limit lives in three places at once: the column of the database, the validation of the product model and the field of the product page. This module raises the three.' d='Modules.Ecomlongname.Admin'}
        </p>
        <ul>
          <li>{l s='The column product_lang.name becomes as long as you set, and its index becomes a prefix index (a full index on a longer column does not fit in InnoDB).' d='Modules.Ecomlongname.Admin'}</li>
          <li>{l s='The size of the name in the product model is raised, so saving a longer name no longer throws an exception.' d='Modules.Ecomlongname.Admin'}</li>
          <li>{l s='The Name field of the product page stops rejecting more than 128 characters.' d='Modules.Ecomlongname.Admin'}</li>
        </ul>

        <h4>{l s='Where the long name shows up' d='Modules.Ecomlongname.Admin'}</h4>
        <p>
          {l s='Everywhere, by itself: the product page of the shop, the listings, the cart, the order, the order detail of the customer, the back-office order page, the invoices, the delivery slips, the confirmation email and every table. It is the real name of the product, not a copy.' d='Modules.Ecomlongname.Admin'}
        </p>

        <h4>{l s='The friendly URL' d='Modules.Ecomlongname.Admin'}</h4>
        <p>
          {l s='The friendly URL keeps its 128 characters: making it longer is bad for SEO and for the browsers. If it is built from a long name, the module cuts it at 128.' d='Modules.Ecomlongname.Admin'}
        </p>

        <h4>{l s='Going back' d='Modules.Ecomlongname.Admin'}</h4>
        <p>
          {l s='The button "Back to 128 characters" leaves the column as PrestaShop brings it. It refuses to do it while there are longer names, because they would be cut. Uninstalling the module does not shrink the column either: your names are safe.' d='Modules.Ecomlongname.Admin'}
        </p>

        <h4>{l s='Before touching a shop with a lot of products' d='Modules.Ecomlongname.Admin'}</h4>
        <p>
          {l s='Changing the column rewrites the table: on a big catalog it takes a while and the shop is slower meanwhile. Do it out of business hours and with a backup, like any other change to the database.' d='Modules.Ecomlongname.Admin'}
        </p>

      </div>
    </div>
  </div>
</div>

{literal}
<script type="text/javascript">
  (function () {
    if (window.lnmAccordionReady) { return; }
    window.lnmAccordionReady = true;
    document.addEventListener('click', function (event) {
      var target = event.target;
      var toggle = null;
      while (target && target !== document) {
        if (target.getAttribute && target.getAttribute('data-lnm-accordion')) { toggle = target; break; }
        target = target.parentNode;
      }
      if (!toggle) { return; }
      event.preventDefault();
      var body = document.getElementById(toggle.getAttribute('data-lnm-accordion'));
      if (!body) { return; }
      body.style.display = (body.style.display === 'none' || body.style.display === '') ? 'block' : 'none';
    });
  })();
</script>
{/literal}
