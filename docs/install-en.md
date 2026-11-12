# CoinsPay for PrestaShop: installation

The module takes cryptocurrency payments through CoinsPay: the buyer picks a coin and a network
on the CoinsPay payment page, and the order gets the status you chose for each invoice event.
The money goes straight to your wallets.

One archive, `coinspay.zip`, works on PrestaShop 1.6.1 to 9.x with PHP 5.6 to 8.5. You need a
CoinsPay account with a shop.

## Installation

1. Upload `coinspay.zip` in the back office:
   - PrestaShop 1.7 to 9: Modules → Module Manager → "Upload a module";
   - PrestaShop 1.6: Modules and Services → "Add a new module".
2. Press "Configure" next to "CoinsPay: cryptocurrency payments".

PrestaShop 1.7 to 9 install the module as soon as the upload ends. PrestaShop 1.6 first warns
that the module "could not be verified by PrestaShop": it says so of every module that does not
come from the Addons marketplace. Confirm the installation.

The module adds two order statuses: "Awaiting crypto payment" and "CoinsPay: check the payment".

## Connection

1. In the CoinsPay dashboard open your shop.
2. Under "API keys" create a key with **"Invoices only"** access and paste it into "API key".
   The module needs nothing more, and such a key cannot withdraw money, even if someone with
   back office access reads it.
3. Leave "API address" empty: the module then uses `https://coinspay.app/api/v1`.
4. Under "Webhook" enter the address the module shows, save, and copy the `whsec_…` secret
   into "Webhook secret".
5. Save, press "Check connection", then "Send test event" in the dashboard: the module shows
   when the event arrived.

The currencies, countries and customer groups that see the method are set in Payment →
Preferences, as for any payment module.

## Order statuses

The "Order statuses" tab maps each invoice event to an order status. By default:

| Invoice event | Order status |
|---|---|
| Order placed, waiting for the payment | Awaiting crypto payment |
| Payment on its way, partially paid | not changed, a message only |
| Paid (on time, late, with overpayment, marked paid by hand) | Payment accepted |
| Expired unpaid, invoice cancelled | Canceled: PrestaShop returns the stock |
| Amount differs, the paid order was cancelled, a test payment not by the test buyer, expired or cancelled partially paid | CoinsPay: check the payment |

Your own statuses are in the lists too. A private message with amounts, coins and transaction
hashes goes to the order at every event; the buyer does not see it.

"Order cancel statuses" are the statuses that cancel the open invoice when you put an order
into one of them. Canceled is chosen by default.

## Test mode

With a test key (`cpk_test_…`) invoices are paid with a button on the payment page and no
coins move. By default the method then shows only to the test buyer: open the store with
"Open the shop as the test buyer" from the settings. The link works for a day in the browser
you open it in. A test payment completes only orders of the test buyer.

## When webhooks do not arrive

The module asks CoinsPay about open invoices itself: from shop pages at most every 5 minutes,
and when the "Address for cron" from the settings is opened. For a shop with few visitors add
that address to the cron of your hosting every 5 minutes.

The webhook address must stay the shop's own address, as the settings show it: PrestaShop
redirects other host names to it, and webhooks do not follow redirects. In maintenance mode
PrestaShop refuses webhooks; polling catches up afterwards.

## Several shops (multistore)

Settings are kept for each shop. Pick one shop at the top of the back office, then open the
module settings.

Shops may share one CoinsPay shop: its webhook then comes to one of them, which passes events
about the other shops' invoices on. Or give each shop a CoinsPay shop of its own, with its own
key and webhook. A shop added later with "Import data" from another shop gets the module
settings of that shop.

## Good to know

- The order is placed before the payment, as with bank wire: PrestaShop sends the order
  confirmation e-mail and reserves the stock at once.
- A buyer who closed the payment page can pay later: the order page in their account shows
  "Go to payment" while the invoice is open.
- The invoice is made in the order currency when CoinsPay accepts it, otherwise in the default
  currency of the shop.
- The debug log goes to Advanced Parameters → Logs. Errors are logged always; keys and secrets
  never.
