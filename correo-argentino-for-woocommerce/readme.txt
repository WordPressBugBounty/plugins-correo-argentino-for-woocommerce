=== Correo Argentino for WooCommerce ===
Contributors: gdicorreo
Tags: woocommerce, shipping, logistics, argentina, correo argentino
Requires at least: 6.7
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 3.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrate your WooCommerce store with Correo Argentino to optimize the management of your PAQ.AR shipments nationwide.

== Description ==

**With the Correo Argentino plugin you will be able to:**
* Integrate with MiCorreo or API 2.0 according to your needs.
* Offer your customers Express or Classic shipping to branches and home delivery nationwide.
* Provide free shipping based on your commercial strategy.
* Apply discounts or surcharges on your shipments to adapt your costs flexibly.
* Track and manage all your shipments from a single place, simply and efficiently.
* Thanks to the MiCorreo online quoting tool, you will be able to know shipping costs in real-time directly from the platform.


**Previous steps:**
To operate with **MiCorreo**: Log in with your username and password. If you don't have one, you can create it by visiting: https://www.correoargentino.com.ar/MiCorreo/public/

To operate with the **Api PAQ.AR 2.0**: Enter the “Agreement” and “Apikey” previously granted by an account executive. If you do not have one assigned, you can contact us at: https://www.correoargentino.com.ar/MiCorreo/public/contact

== Installation ==

1. Upload the plugin to /wp-content/plugins/.
2. Activate it through the 'Plugins' menu in WordPress.
3. Configure your credentials and settings in WooCommerce -> Settings -> Shipping -> Correo Argentino.

== Frequently Asked Questions ==

= Do I need to register previously? =
Yes, but don't worry, it's a simple and fast process. You just need to visit https://www.correoargentino.com.ar/MiCorreo/public/, complete the 2-step form, and you are ready to start using MiCorreo. Remember that you must have an active account to operate with the Correo Argentino plugin.

= How do I integrate my WooCommerce store with Correo Argentino? =
To perform the integration, visit https://www.correoargentino.com.ar/MiCorreo/public/mi-correo#, where you can download the step-by-step manual that will guide you through the entire process.

= Can I offer free shipping? =
Yes! You can define a minimum purchase amount so that free shipping is activated automatically.

= Can I apply a surcharge/discount to my shipments? =
Yes. You can set a maximum or minimum amount for your orders in a simple way.

= What shipping methods can I offer my customers? =
You can offer your customers different shipping methods according to their needs: classic or express shipping, both to home delivery or branch, so they can choose the most comfortable and convenient option when receiving their purchase.


== Changelog ==

= 3.0.8 =
* Tested up to WordPress 7.1.
* Street name and street number are sent separately, so shipping labels no longer show the number twice.
* Shipping labels (Paq.Ar and MiCorreo): if the street number (altura) is missing, the plugin handles it correctly.
* MiCorreo and Paq.Ar settings are stored per service, so free shipping and related options no longer apply across services.
* WooCommerce shipping settings are backed up on first activation and restored on deactivation or uninstall.
* WooCommerce shipping debug mode can be toggled together with Sandbox credentials.
* Checkout: fixed an infinite reload loop when branch delivery was the first selected shipping method.
* Admin: confirmation notice when shipping adjustment settings are saved.
* Checkout and order import fixes.
* The free shipping label is no longer shown when free shipping is disabled.
* Fixes for branch destination address and hidden Paq.Ar orders in the orders list.
* Support for minimum order amounts on shipping methods.
* Checkout styles adapt better to the store theme.
* HPOS: order handling uses checkout completion instead of woocommerce_new_order.
* Translation and WordPress.org Plugin Check updates.

= 3.0.7 =
* Security improvements and data sanitization.
* Zip code validation in Checkout and My Account.
* Replacement of remote resources with local files and removal of inline scripts/styles (now uses wp_enqueue_*).
* Documentation of external services (endpoints, data flow, and policies).

== Upgrade Notice ==

= 3.0.8 =
Recommended update: shipping label addresses, per-service settings (MiCorreo / Paq.Ar), and compatibility with WordPress 7.1.

= 3.0.7 =
It is highly recommended to update to ensure compatibility with WordPress 6.9 and security improvements.

== Screenshots ==

1. Easily choose which service you want to operate with: PAQ.AR MiCorreo or API PAQ.AR 2.0, depending on what best suits your business.
2. From the Correo Argentino grid, you will be able to easily see all the sales you make.
