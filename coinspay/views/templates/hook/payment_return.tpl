{**
 * The order confirmation page: what happens with the crypto payment.
 *}
<div class="coinspay-return">
  <p class="alert alert-info">{$coinspay_message|escape:'html':'UTF-8'}</p>
  {if $coinspay_pay_url}
    <p><a class="btn btn-primary" href="{$coinspay_pay_url|escape:'html':'UTF-8'}">{$coinspay_pay|escape:'html':'UTF-8'}</a></p>
  {/if}
</div>
