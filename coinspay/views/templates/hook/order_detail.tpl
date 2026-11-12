{**
 * The order page in the customer account: a link to pay the order while its invoice is open.
 *}
<div class="box coinspay-order">
  <p>{$coinspay_message|escape:'html':'UTF-8'}</p>
  <p><a class="btn btn-primary button button-medium" href="{$coinspay_pay_url|escape:'html':'UTF-8'}"><span>{$coinspay_pay|escape:'html':'UTF-8'}</span></a></p>
</div>
