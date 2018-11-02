Introduction

This is the readme file for Paytm Payment Gateway Plugin Integration for WPeCommerce v3.11.x.
The provided Plugin integrate "Paytm" as a payment method in WPeCommerce.
After the customer has finished the transaction they are redirected back to an appropriate page on the merchant site depending on the status of the transaction.

The aim of this document is to explain the procedure of installation and configuration of the Package on the merchant website.


Installation and Configuration

- Unzip the Paytm module files
- Copy the file "paytm.php" and "paytm" folder to your WordPress installation in this folder: /wp-content/plugins/wp-e-commerce/wpsc-merchants/
- Log in to your WordPress administration
- Go to Settings -> Store
- Choose Payments at the top of the screen, and tick off Paytm. Press "Update" to save the settings.
- After module Installation, configure it.
- You should select the Transaction mode, enter paytm Merchant id, Merchant key, website in the listed parameters on configuration tab.
- Then click on Update.

# Paytm PG URL Details
	staging	
		Transaction URL             => https://securegw-stage.paytm.in/theia/processTransaction
		Transaction Status Url      => https://securegw-stage.paytm.in/merchant-status/getTxnStatus

	Production
		Transaction URL             => https://securegw.paytm.in/theia/processTransaction
		Transaction Status Url      => https://securegw.paytm.in/merchant-status/getTxnStatus
