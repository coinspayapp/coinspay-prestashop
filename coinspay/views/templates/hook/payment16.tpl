{**
 * The payment method block at checkout, PrestaShop 1.6.
 *}
<div class="row">
  <div class="col-xs-12">
    <p class="payment_module coinspay-payment">
      <a href="{$coinspay_url|escape:'html':'UTF-8'}" title="{$coinspay_title|escape:'html':'UTF-8'}" style="background: #fbfbfb url('{$coinspay_logo|escape:'html':'UTF-8'}') 15px 50% no-repeat; background-size: 64px auto; padding-left: 99px;">
        {$coinspay_title|escape:'html':'UTF-8'}
        <span>{$coinspay_description|escape:'html':'UTF-8'}</span>
        {if $coinspay_test}<span class="alert alert-info" style="display: block; margin: 8px 0 0;">{$coinspay_test|escape:'html':'UTF-8'}</span>{/if}
      </a>
    </p>
  </div>
</div>
