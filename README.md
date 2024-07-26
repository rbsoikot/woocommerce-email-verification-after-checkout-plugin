=== WooCommerce Email Verification After Checkout ===

Contributors: RB Soikot
Donate link: buymeacoffee.com/rbsoikot
Tags: woocommerce, email verification, checkout, user verification
Requires at least: 5.0
Tested up to: 6.2
Requires PHP: 7.2
Stable tag: 1.0.0

This plugin forces users to verify their email addresses after 5 minutes after account creation or checkout.

== Description ==

WooCommerce Email Verification After Checkout is a plugin designed to ensure that users verify their email addresses after 5 minutes of account creation or checkout. If users fail to verify their email within the allotted time, they will be prompted to verify their email address upon attempting to log in.

= Features =
* Sends verification email upon account creation or checkout.
* Requires email verification after 5 minutes.
* Prompts unverified users to verify their email upon login attempts.
* Displays a list of verified users in the admin dashboard, including the time and date of verification.



= Requirements =
For the plugin to function correctly, you need to create a page named **Verify Email** and add the shortcode `[email_verification_message]` to it.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/woocommerce-email-verification-after-checkout` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Create a page named **Verify Email**.
4. Add the shortcode `[email_verification_message]` to the **Verify Email** page.

== Frequently Asked Questions ==

= How does the plugin work? =
When a user creates an account or completes a checkout, the plugin sends a verification email. The user has 5 minutes to verify their email address. If they attempt to log in without verifying their email, they are prompted to verify their email.

= What happens if a user doesn't verify their email within 5 minutes? =
The user will be prompted to verify their email address upon attempting to log in.

= Can the verification email be customized? =
Yes, you can customize the email subject and body in the plugin settings.


== Changelog ==

= 1.0.0 =
* Initial release with email verification features for account creation, checkout, and login attempts.

== Upgrade Notice ==

= 1.0.0 =
* Initial release.


