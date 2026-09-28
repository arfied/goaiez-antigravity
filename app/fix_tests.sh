#!/bin/bash
CLASSES="BillingLedgerEngine InvoiceEngine InvoiceDraftAction TermsSetAction PlanProposeAction EmailWarmupAction LinkPitchAction InternalLinkRenderAction ConversionUploadEngine SignalScoreAction ProviderHealthAction"

for cls in $CLASSES; do
  # Replace 'new Class;' with 'new Class(app(\App\Support\DefaultsRegistry::class));'
  find tests -type f -name "*.php" -exec sed -i "s/new $cls;/new $cls(app(\\\\App\\\\Support\\\\DefaultsRegistry::class));/g" {} +
  # Replace 'new Class()' with 'new Class(app(\App\Support\DefaultsRegistry::class))'
  find tests -type f -name "*.php" -exec sed -i "s/new $cls()/new $cls(app(\\\\App\\\\Support\\\\DefaultsRegistry::class))/g" {} +
  # Replace 'new Class($arg)' with 'new Class($arg, app(\App\Support\DefaultsRegistry::class))'
  # Actually only InvoiceEngine might have multiple args, but we didn't add GatewayEngine to constructor, wait.
  # Let's just run pest to see what's left
done
