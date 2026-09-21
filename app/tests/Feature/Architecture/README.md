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

Any citation to other `Architecture/<X>Test` names found in this tree came from the `e737094c1` import and names a lint in the sibling `goaiez-review-system` project, rather than a guard here. We currently have 43 distinct cited names, of which 40 are missing in this repository.

To check which cited tests are present or missing, you can run this command from the `app/` directory:

```bash
for t in $(grep -rhoE "Architecture[\/][A-Za-z]+Test" app/ resources/ tests/ | sed 's|Architecture.||' | sort -u); do
  [ -f "tests/Feature/Architecture/$t.php" ] && echo "OK      $t" || echo "MISSING $t"
done
```

Note that `tests/Support/ScreenStates.php` is an imported parser that currently has no caller here. It is **kept deliberately** as it contains the whole machinery for that lint (`ScreenStatesTest`), should the suite ever be adopted in this repository. Do not delete it.
