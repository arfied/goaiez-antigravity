# Architecture Tests

There are currently ten architecture tests present in this repository:

- `EmptyStateContractTest.php`
- `HeadingSeamTest.php`
- `NoRawSetBusinessIdTest.php`
- `OwnerNavTest.php`
- `PricesTest.php`
- `SampleStateModuleTest.php`
- `SchedulingTest.php`
- `SchemeTokenTest.php`
- `TenancyTest.php`
- `UnsetTenantDefectTest.php`

Any citation to other `Architecture/<X>Test` names found in this tree came from the `e737094c1` import and names a lint in the sibling `goaiez-review-system` project, rather than a guard here. We currently have 43 distinct cited names, of which 40 are missing in this repository (measured at `cc65dadc0`, 2026-09-21).

To check which cited tests are present or missing, you can run this command from the `app/` directory:

```bash
for t in $(grep -rhoE 'Architecture[\\/][A-Za-z]+Test' app/ resources/ tests/ | sed 's|Architecture.||' | sort -u); do
  [ -f "tests/Feature/Architecture/$t.php" ] && echo "OK      $t" || echo "MISSING $t"
done
```

Measured at `cc65dadc0` (2026-09-21):

```text
MISSING AccountScreensTest
MISSING ActivityTest
MISSING ActuationTest
MISSING AdminNavTest
MISSING AgentTest
MISSING AiTest
MISSING AutomationRunSurvivorTest
MISSING BillingTest
MISSING BladeScanningTest
MISSING CampaignPackTest
MISSING ConsentTest
MISSING ContentTest
MISSING ConventionsTest
MISSING CredentialsTest
MISSING CrmTest
MISSING GbpTest
MISSING InboxTest
MISSING LegalTest
MISSING LinksTest
MISSING MailTest
MISSING MarketingTest
MISSING MessageCanonTest
MISSING MessagingTest
MISSING ObservabilityTest
MISSING OutboundTest
MISSING OwnerChannelTest
OK      OwnerNavTest
MISSING PixelTest
OK      PricesTest
MISSING ProofHashTest
MISSING QueuePayloadTest
MISSING QueueRoutingTest
MISSING RegistryTest
MISSING RetentionTest
MISSING ReviewsTest
MISSING ScreenStatesTest
MISSING StaffTest
MISSING StorageTest
OK      TenancyTest
MISSING VisibilityTest
MISSING VoiceTest
MISSING WarehouseTest
MISSING WidgetTest
```


Note that `tests/Support/ScreenStates.php` is an imported parser that currently has no caller here. It is **kept deliberately** as it contains the whole machinery for that lint (`ScreenStatesTest`), should the suite ever be adopted in this repository. Do not delete it.
