{**
 * Text under the payment option at checkout, PrestaShop 1.7 and newer.
 *}
<section class="coinspay-option">
  <p>{$coinspay_description|escape:'html':'UTF-8'}</p>
  {if $coinspay_test}
    <p class="alert alert-info">{$coinspay_test|escape:'html':'UTF-8'}</p>
  {/if}
</section>
